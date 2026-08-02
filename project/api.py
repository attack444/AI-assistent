"""
api.py — REST API сервер для AI Helper.

Запуск: python api.py  (порт 8502)
Используется для:
  - Панели Next.js (чат, файлы, сайты)
  - Интеграции с VS Code
  - Внешних клиентов

Endpoints:
  GET    /status
  GET    /chats  /chats/<id>  /context
  POST   /chats  /chats/rename  /chat  /chat/stream
  DELETE /chats/<id>
  POST   /smart-commit
  GET    /project/files
  POST   /project/read
  GET    /fs/list
  POST   /fs/read
  POST   /fs/write
  POST   /fs/mkdir
  POST   /fs/delete
  POST   /fs/upload
  GET    /sites
  POST   /sites
  DELETE /sites/<name>
  POST   /sites/deploy
  GET    /system/health
  GET    /system/incidents
  POST   /system/watchdog
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import io
import json
import os
import re
import shutil
import sys
import tempfile
import time
import zipfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import parse_qs, unquote, urlparse

PROJECT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(PROJECT_DIR))

from core import (
    AppSettings,
    check_ollama_status,
    load_projects,
    load_settings,
)
from memory import MemoryStore
from profile import UserProfile, load_profile
from tools import git_run, list_dir, read_file
import chat_store as chats
import panel_uploads as pup
import wp_tools as wpt

DATA_DIR = Path.home() / ".ai-helper"
PORT = int(os.environ.get("AI_HELPER_API_PORT", os.environ.get("API_PORT", "8502")))
BIND_HOST = os.environ.get("AI_HELPER_API_HOST", "0.0.0.0")

SITES_ROOT = Path(os.environ.get("SITES_ROOT", "/opt/sites")).resolve()
NGINX_SITES_DIR = Path(os.environ.get("NGINX_SITES_DIR", "/etc/nginx/sites-available"))
PANEL_PASSWORD = os.environ.get("PANEL_PASSWORD", "").strip()
SECRET_KEY = os.environ.get("SECRET_KEY", "dev-insecure-change-me").strip()
# Fail closed when PANEL_PASSWORD is empty unless explicitly opted in for local/dev.
ALLOW_INSECURE_NO_AUTH = os.environ.get("ALLOW_INSECURE_NO_AUTH", "").strip().lower() in {
    "1", "true", "yes", "on",
}
# Chunked uploads support up to 2 GB by default (WordPress backups)
MAX_UPLOAD_BYTES = int(os.environ.get("MAX_UPLOAD_BYTES", str(2 * 1024 * 1024 * 1024)))
MAX_JSON_BODY = int(os.environ.get("MAX_JSON_BODY", str(20 * 1024 * 1024)))
HOST_SITES_PATH = os.environ.get("HOST_SITES_PATH", "/var/ai-helper/sites")
CHUNK_SIZE = int(os.environ.get("UPLOAD_CHUNK_SIZE", str(4 * 1024 * 1024)))  # 4 MB

_SAFE_NAME = re.compile(r"^[a-zA-Z0-9][a-zA-Z0-9_-]{0,62}$")
_PUBLIC_PATHS = {
    "/status",
    "/auth/login",
    "/auth/check",
    "/public/chat",
    "/public/chat/stream",
    "/public/deploy",
    "/public/redeploy",
    "/public/files",
    "/public/fs/read",
    "/public/fs/write",
    "/public/auth/register",
    "/public/auth/login",
    "/public/auth/logout",
    "/public/auth/me",
    "/public/me/sites",
    "/public/plans",
    "/public/admin/set-plan",
    "/public/feedback",
}


def _token_for(password: str) -> str:
    return hmac.new(
        SECRET_KEY.encode("utf-8"),
        password.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()


def _auth_enabled() -> bool:
    """Whether privileged routes require a panel bearer token.

    Empty PANEL_PASSWORD used to disable auth (fail-open). That is now only
    allowed when ALLOW_INSECURE_NO_AUTH=1. Otherwise auth stays required and
    requests are rejected until a password is configured.
    """
    if not PANEL_PASSWORD and ALLOW_INSECURE_NO_AUTH:
        return False
    return True


def _default_roots() -> List[Path]:
    roots: List[Path] = [SITES_ROOT]
    for raw in os.environ.get(
        "WORKSPACE_ROOTS",
        "/opt/ai-helper,/opt/sites,/workspace",
    ).split(","):
        raw = raw.strip()
        if not raw:
            continue
        try:
            roots.append(Path(raw).expanduser().resolve())
        except Exception:
            continue
    # unique, existing-or-creatable sites root always kept
    uniq: List[Path] = []
    seen = set()
    for r in roots:
        key = str(r)
        if key in seen:
            continue
        seen.add(key)
        uniq.append(r)
    return uniq


ALLOWED_ROOTS = _default_roots()


def _json(obj: Any) -> bytes:
    return json.dumps(obj, ensure_ascii=False, indent=2).encode("utf-8")


def _ensure_sites_root() -> Path:
    SITES_ROOT.mkdir(parents=True, exist_ok=True)
    return SITES_ROOT


def _is_under(path: Path, root: Path) -> bool:
    try:
        path.resolve().relative_to(root.resolve())
        return True
    except ValueError:
        return False


def _resolve_safe(path_str: str, *, must_exist: bool = False) -> Path:
    """Resolve path and ensure it stays inside ALLOWED_ROOTS."""
    raw = (path_str or "").strip() or str(SITES_ROOT)
    p = Path(raw).expanduser()
    if not p.is_absolute():
        p = SITES_ROOT / p
    p = p.resolve()
    if must_exist and not p.exists():
        raise FileNotFoundError(f"Не найдено: {p}")
    if not any(_is_under(p, root) or p == root.resolve() for root in ALLOWED_ROOTS):
        raise PermissionError(f"Путь вне разрешённых директорий: {p}")
    return p


def _entry_info(p: Path) -> Dict[str, Any]:
    st = p.stat()
    return {
        "name": p.name,
        "path": str(p),
        "type": "dir" if p.is_dir() else "file",
        "size": st.st_size if p.is_file() else None,
        "mtime": int(st.st_mtime),
    }


def _dir_stats(path: Path) -> Tuple[int, int]:
    files = 0
    size = 0
    if not path.exists():
        return 0, 0
    for item in path.rglob("*"):
        if item.is_file():
            files += 1
            try:
                size += item.stat().st_size
            except OSError:
                pass
    return files, size


def _site_info(name: str) -> Dict[str, Any]:
    root = _ensure_sites_root() / name
    files, size = _dir_stats(root)
    domain = ""
    domain_file = root / ".ai-helper-domain"
    if domain_file.is_file():
        domain = domain_file.read_text(encoding="utf-8").strip()
    is_wp = pup.detect_wordpress(root)
    nested = pup.find_wp_or_public(root)
    return {
        "name": name,
        "path": str(root),
        "host_path": f"{HOST_SITES_PATH.rstrip('/')}/{name}",
        "url": f"/sites/{name}/",
        "files": files,
        "size_bytes": size,
        "has_index": (
            (root / "index.html").is_file()
            or (root / "index.htm").is_file()
            or (root / "index.php").is_file()
        ),
        "domain": domain or None,
        "is_wordpress": is_wp,
        "top_entries": pup.top_entries(root),
        "suggested_webroot": str(nested) if nested and nested.resolve() != root.resolve() else None,
    }


def _flatten_hosting_layout(root: Path) -> None:
    """Unwrap common hosting ZIP layouts: public_html, www, wordpress, domain folder."""
    # Already flat WordPress at site root — do not touch
    if (root / "wp-config.php").is_file() or (root / "wp-load.php").is_file():
        return
    if (root / "index.php").is_file() and (root / "wp-content").is_dir():
        return
    # Prefer known web roots nested inside the archive
    found = pup.find_wp_or_public(root)
    if found and found.resolve() != root.resolve():
        for item in list(found.iterdir()):
            dest = root / item.name
            if dest.exists():
                if dest.is_dir():
                    shutil.rmtree(dest)
                else:
                    dest.unlink()
            shutil.move(str(item), str(dest))
        try:
            cur = found
            while cur != root and cur.parent:
                parent = cur.parent
                if cur.exists() and cur.is_dir() and not any(cur.iterdir()):
                    cur.rmdir()
                cur = parent
                if cur.resolve() == root.resolve():
                    break
        except OSError:
            pass
        return
    for folder_name in ("public_html", "www", "htdocs", "httpdocs", "public", "wordpress"):
        nested = root / folder_name
        if nested.is_dir():
            for item in list(nested.iterdir()):
                dest = root / item.name
                if dest.exists():
                    if dest.is_dir():
                        shutil.rmtree(dest)
                    else:
                        dest.unlink()
                shutil.move(str(item), str(dest))
            shutil.rmtree(nested, ignore_errors=True)
            return
    children = [c for c in root.iterdir() if not c.name.startswith(".")]
    if len(children) == 1 and children[0].is_dir():
        top = children[0]
        for item in list(top.iterdir()):
            dest = root / item.name
            if dest.exists():
                if dest.is_dir():
                    shutil.rmtree(dest)
                else:
                    dest.unlink()
            shutil.move(str(item), str(dest))
        shutil.rmtree(top, ignore_errors=True)


def _fix_site_perms(root: Path) -> None:
    """Make site readable by host nginx (www-data). Docker often writes as root."""
    try:
        if root.is_dir():
            os.chmod(root, 0o755)
        for p in root.rglob("*"):
            try:
                if p.is_dir():
                    os.chmod(p, 0o755)
                elif p.is_file():
                    os.chmod(p, 0o644)
            except OSError:
                continue
        # Best-effort: also fix parents under sites root
        for parent in [root, root.parent]:
            try:
                if _is_under(parent, SITES_ROOT) or parent == SITES_ROOT:
                    os.chmod(parent, 0o755)
            except OSError:
                pass
    except OSError:
        pass


def _write_nginx_vhost(name: str, domain: str = "") -> Optional[str]:
    """Write nginx vhost so domain serves the site at / (like shared hosting)."""
    if not domain.strip():
        return None
    domain = domain.strip().lower()
    domain = domain.replace("https://", "").replace("http://", "").split("/")[0]
    # Prefer host path when sites are bind-mounted
    host_root = Path(HOST_SITES_PATH) / name
    root_path = host_root if host_root.parent.exists() else (SITES_ROOT / name)
    conf = f"""# AI Helper site: {name} → https://{domain}
# Generated automatically. Panel stays on server IP; this domain is the site.
server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};

    root {root_path};
    index index.php index.html index.htm;
    client_max_body_size 200M;

    location / {{
        try_files $uri $uri/ /index.php?$args;
    }}

    location ~ \\.php$ {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_read_timeout 300s;
    }}

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff2?)$ {{
        expires 7d;
        access_log off;
        try_files $uri =404;
    }}

    location ~* /(?:uploads|files)/.*\\.php$ {{
        deny all;
    }}
}}
"""
    site_meta = SITES_ROOT / name / ".ai-helper-domain"
    site_meta.parent.mkdir(parents=True, exist_ok=True)
    site_meta.write_text(domain, encoding="utf-8")

    # Always keep a copy next to the site (works inside Docker)
    site_copy = SITES_ROOT / name / "nginx.vhost.conf"
    site_copy.parent.mkdir(parents=True, exist_ok=True)
    site_copy.write_text(conf, encoding="utf-8")

    try:
        NGINX_SITES_DIR.mkdir(parents=True, exist_ok=True)
        out = NGINX_SITES_DIR / f"ai-helper-{name}.conf"
        out.write_text(conf, encoding="utf-8")
        enabled = Path("/etc/nginx/sites-enabled") / f"ai-helper-{name}.conf"
        if enabled.parent.exists():
            try:
                if enabled.exists() or enabled.is_symlink():
                    enabled.unlink()
                enabled.symlink_to(out)
            except OSError:
                pass
        return str(out)
    except Exception:
        return str(site_copy)


def _run_agent_sync(
    message: str,
    project_root: Optional[Path],
    settings: AppSettings,
    profile: UserProfile,
    memory: MemoryStore,
    history: Optional[list] = None,
) -> str:
    from agent import run_agent

    text_parts: list[str] = []
    for ev in run_agent(
        user_message=message,
        chat_history=history or [],
        project_root=project_root,
        profile=profile,
        memory=memory,
        llm_model=settings.llm_model,
        ollama_host=settings.ollama_host,
        context_window=settings.context_window,
        fast_llm_model=settings.fast_llm_model,
        groq_api_key=settings.groq_api_key,
        groq_model=settings.groq_model,
        deepseek_api_key=settings.deepseek_api_key,
        deepseek_model=settings.deepseek_model,
        http_proxy=settings.http_proxy,
    ):
        if ev.type == "text":
            text_parts.append(ev.content)
        elif ev.type == "error":
            text_parts.append(f"\n[Ошибка: {ev.content}]")
    return "".join(text_parts)


class APIHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt, *args):
        try:
            msg = fmt % args
        except Exception:
            msg = fmt
        # Console may be latin-1 in some images — never crash on log
        try:
            print(f"[api] {self.address_string()} {msg}", flush=True)
        except Exception:
            print("[api] (log encode error)", flush=True)

    def send_response(self, code, message=None):
        # Status line is latin-1 only — never put Cyrillic in the reason phrase
        if message is None:
            try:
                message = self.responses[code][0]
            except Exception:
                message = "OK"
        safe = str(message).encode("ascii", "replace").decode("ascii") or "OK"
        super().send_response(code, safe)

    def send_header(self, keyword, value):
        safe = str(value).encode("latin-1", "replace").decode("latin-1")
        super().send_header(keyword, safe)

    def send_error(self, code, message=None, explain=None):
        """BaseHTTPRequestHandler encodes status message as latin-1 — Cyrillic crashes it."""
        safe_msg = "Error"
        if message:
            safe_msg = str(message).encode("ascii", "replace").decode("ascii") or "Error"
        detail = str(message) if message else safe_msg
        try:
            body = _json({"error": detail, "code": code})
            self.send_response(code, safe_msg)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(body)
        except Exception:
            try:
                super().send_error(code, safe_msg)
            except Exception:
                pass

    def _send(self, code: int, body: bytes, content_type: str = "application/json") -> None:
        reasons = {
            200: "OK",
            201: "Created",
            204: "No Content",
            400: "Bad Request",
            401: "Unauthorized",
            404: "Not Found",
            409: "Conflict",
            500: "Internal Server Error",
        }
        self.send_response(code, reasons.get(code, "OK"))
        self.send_header("Content-Type", f"{content_type}; charset=utf-8")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS")
        self.send_header(
            "Access-Control-Allow-Headers",
            "Content-Type, Authorization",
        )
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self._send(204, b"")

    def _safe_handler(self, fn):
        try:
            fn()
        except Exception as exc:
            # Never let Cyrillic exceptions hit default send_error status line
            try:
                self._send(500, _json({"error": str(exc)}))
            except Exception:
                self.send_error(500, "Internal Server Error")

    def _read_body(self) -> Dict[str, Any]:
        length = int(self.headers.get("Content-Length", 0) or 0)
        if length <= 0:
            return {}
        if length > MAX_JSON_BODY:
            raise ValueError(
                f"JSON слишком большой: {length // (1024 * 1024)} МБ "
                f"(лимит {MAX_JSON_BODY // (1024 * 1024)} МБ)"
            )
        raw = self.rfile.read(length)
        if not raw:
            return {}
        return json.loads(raw.decode("utf-8"))

    def _stream_body_to_file(self, dest: Path, max_bytes: int = MAX_UPLOAD_BYTES) -> int:
        """Stream raw request body to disk in chunks (no full in-memory buffer)."""
        length = int(self.headers.get("Content-Length", 0))
        if length <= 0:
            raise ValueError("Пустое тело запроса (нужен файл)")
        if length > max_bytes:
            raise ValueError(
                f"Файл слишком большой: {length // (1024 * 1024)} МБ "
                f"(лимит {max_bytes // (1024 * 1024)} МБ). "
                f"Залей через SCP или разбей архив."
            )
        dest.parent.mkdir(parents=True, exist_ok=True)
        written = 0
        remaining = length
        chunk_size = 1024 * 1024  # 1 MB
        with dest.open("wb") as out:
            while remaining > 0:
                data = self.rfile.read(min(chunk_size, remaining))
                if not data:
                    break
                out.write(data)
                written += len(data)
                remaining -= len(data)
        return written

    def _extract_zip_file(self, zip_path: Path, root: Path) -> None:
        with zipfile.ZipFile(zip_path, "r") as zf:
            for member in zf.infolist():
                target = (root / member.filename).resolve()
                if not _is_under(target, root) and target != root.resolve():
                    raise PermissionError(f"Небезопасный путь в ZIP: {member.filename}")
            zf.extractall(root)
        _flatten_hosting_layout(root)
        _fix_site_perms(root)

    def _qs(self) -> Dict[str, str]:
        return {k: v[0] for k, v in parse_qs(urlparse(self.path).query).items() if v}

    def _server_workspace(self) -> Optional[Path]:
        """Workspace бэкенда панели для чата site=server|panel|backend."""
        candidates = [
            Path(os.environ.get("AI_HELPER_PROJECT", "").strip())
            if os.environ.get("AI_HELPER_PROJECT", "").strip()
            else None,
            PROJECT_DIR,  # /opt/ai-helper/project внутри смонтирован
            Path("/opt/ai-helper/project"),
            Path("/opt/ai-helper"),
        ]
        for cand in candidates:
            if cand is None:
                continue
            try:
                p = cand.expanduser().resolve()
            except Exception:
                continue
            if p.is_dir() and ((p / "api.py").is_file() or (p / "project" / "api.py").is_file()):
                if (p / "project" / "api.py").is_file() and not (p / "api.py").is_file():
                    return p / "project"
                return p
        return None

    def _load_context(self, project_name: str = "", site_name: str = "") -> tuple:
        settings = load_settings()
        profile = load_profile()
        memory = MemoryStore()
        projects = load_projects()
        project_root: Optional[Path] = None
        # Site folder takes priority (hosting workspace for chat/tools)
        site = (site_name or "").strip().lower()
        # Специальный workspace: DeepSeek правит бэкенд, не только файлы сайта
        if site in {"server", "panel", "backend"}:
            project_root = self._server_workspace()
        elif site and _SAFE_NAME.match(site):
            candidate = _ensure_sites_root() / site
            if candidate.is_dir():
                project_root = candidate
        if project_root is None:
            if project_name and project_name in projects:
                project_root = Path(projects[project_name].root)
            elif projects:
                project_root = Path(list(projects.values())[0].root)
        return settings, profile, memory, project_root

    def _authorized(self) -> bool:
        if not _auth_enabled():
            return True
        if not PANEL_PASSWORD:
            return False
        auth = self.headers.get("Authorization", "")
        if not auth.startswith("Bearer "):
            return False
        token = auth[7:].strip()
        expected = _token_for(PANEL_PASSWORD)
        return hmac.compare_digest(token, expected)

    def _require_auth(self) -> bool:
        if self._authorized():
            return True
        if _auth_enabled() and not PANEL_PASSWORD:
            self._send(503, _json({
                "error": "PANEL_PASSWORD не задан — панель заблокирована",
                "auth_required": True,
                "misconfigured": True,
            }))
            return False
        self._send(401, _json({
            "error": "Нужен вход",
            "auth_required": True,
        }))
        return False

    def _post_auth_login(self):
        body = self._read_body()
        password = str(body.get("password", ""))
        if not _auth_enabled():
            self._send(200, _json({
                "ok": True,
                "auth_required": False,
                "token": "",
                "message": "Пароль панели не задан (ALLOW_INSECURE_NO_AUTH)",
            }))
            return
        if not PANEL_PASSWORD:
            self._send(503, _json({
                "ok": False,
                "error": "PANEL_PASSWORD не задан на сервере",
                "misconfigured": True,
            }))
            return
        if not password or not hmac.compare_digest(password, PANEL_PASSWORD):
            self._send(401, _json({"ok": False, "error": "Неверный пароль"}))
            return
        self._send(200, _json({
            "ok": True,
            "auth_required": True,
            "token": _token_for(PANEL_PASSWORD),
        }))

    def _get_auth_check(self):
        self._send(200, _json({
            "ok": self._authorized(),
            "auth_required": _auth_enabled(),
        }))

    # ── GET /status ──────────────────────────────────────────────
    def _get_status(self):
        import free_llm as fl

        settings = load_settings()
        ost = check_ollama_status(settings.ollama_host)
        projects = load_projects()
        deepseek = bool(
            settings.deepseek_api_key
            or os.environ.get("DEEPSEEK_API_KEY", "").strip()
        )
        free_st = fl.check_ollama(
            settings.ollama_host,
            fl.free_model(settings.fast_llm_model, settings.llm_model),
        )
        free_model_name = free_st.get("model") or fl.free_model()
        tools_ok = bool(
            free_st.get("tools_supported")
            if "tools_supported" in free_st
            else fl.model_supports_tools(free_model_name)
        )
        payload: Dict[str, Any] = {
            "ok": True,
            "ollama": ost.reachable,
            "models": list(ost.models or [])[:40],
            "groq": bool(settings.groq_api_key or os.environ.get("GROQ_API_KEY", "").strip()),
            "groq_model": settings.groq_model,
            "deepseek": deepseek,
            "deepseek_model": settings.deepseek_model,
            "free_llm": bool(free_st.get("reachable") and free_st.get("has_model")),
            "free_model": free_model_name,
            "free_tools": tools_ok,
            "llm_prefer_free": fl.prefer_free(),
            "llm_model": settings.llm_model,
            "fast_model": settings.fast_llm_model,
            "auth_required": _auth_enabled(),
            "max_upload_bytes": MAX_UPLOAD_BYTES,
            "upload_chunk_size": CHUNK_SIZE,
            "version": "2.10.0",
            "widget_guest": os.environ.get("PUBLIC_WIDGET_GUEST", "1").strip().lower()
            not in {"0", "false", "no", "off"},
        }
        # Paths / project names only for authenticated panel clients
        if (not _auth_enabled()) or self._authorized():
            payload["projects"] = list(projects.keys())
            payload["allowed_roots"] = [str(r) for r in ALLOWED_ROOTS]
            payload["sites_root"] = str(SITES_ROOT)
            payload["host_sites_path"] = HOST_SITES_PATH
        self._send(200, _json(payload))

    # ── GET /project/files ───────────────────────────────────────
    def _get_project_files(self):
        qs = parse_qs(urlparse(self.path).query)
        proj_name = qs.get("project", [""])[0]
        settings, profile, memory, project_root = self._load_context(proj_name)
        if not project_root:
            self._send(404, _json({"error": "Нет активного проекта"}))
            return
        r = list_dir(str(project_root), recursive=True, extensions="")
        self._send(200, _json({"project": project_root.name, "path": str(project_root), **r}))

    # ── POST /project/read ───────────────────────────────────────
    def _post_project_read(self):
        body = self._read_body()
        path = body.get("path", "")
        proj_name = body.get("project", "") or body.get("site", "")
        if not path:
            self._send(400, _json({"error": "Нужен путь (path)"}))
            return
        try:
            # Prefer explicit path under allowed roots; else resolve via site workspace
            try:
                safe = _resolve_safe(path, must_exist=True)
                r = read_file(str(safe))
            except Exception:
                _, _, _, project_root = self._load_context(proj_name)
                if not project_root:
                    raise
                from tools import resolve_workspace_path
                safe = resolve_workspace_path(path, project_root)
                r = read_file(str(safe))
        except Exception as exc:
            self._send(403, _json({"ok": False, "error": str(exc)}))
            return
        self._send(200 if r.get("ok") else 404, _json(r))

    # ── FS: list / read / write / mkdir / delete / upload ────────
    def _get_fs_list(self):
        qs = parse_qs(urlparse(self.path).query)
        path_str = qs.get("path", [""])[0]
        try:
            _ensure_sites_root()
            if not path_str.strip():
                # Top-level: show allowed roots that exist
                entries = []
                for root in ALLOWED_ROOTS:
                    if root.exists():
                        entries.append(_entry_info(root))
                    elif root == SITES_ROOT:
                        root.mkdir(parents=True, exist_ok=True)
                        entries.append(_entry_info(root))
                self._send(200, _json({
                    "ok": True,
                    "path": "",
                    "parent": None,
                    "entries": entries,
                }))
                return

            p = _resolve_safe(path_str, must_exist=True)
            if not p.is_dir():
                self._send(400, _json({"error": "Не директория"}))
                return
            entries = []
            for child in sorted(p.iterdir(), key=lambda x: (not x.is_dir(), x.name.lower())):
                if child.name.startswith(".") and child.name not in {".well-known"}:
                    continue
                try:
                    entries.append(_entry_info(child))
                except OSError:
                    continue
            parent = str(p.parent) if any(_is_under(p.parent, r) or p.parent == r for r in ALLOWED_ROOTS) else ""
            self._send(200, _json({
                "ok": True,
                "path": str(p),
                "parent": parent,
                "entries": entries,
            }))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_fs_read(self):
        body = self._read_body()
        try:
            p = _resolve_safe(body.get("path", ""), must_exist=True)
            if not p.is_file():
                self._send(400, _json({"error": "Не файл"}))
                return
            text = p.read_text(encoding="utf-8", errors="ignore")
            truncated = len(text) > 500_000
            if truncated:
                text = text[:500_000] + "\n...[обрезано]"
            self._send(200, _json({
                "ok": True,
                "path": str(p),
                "content": text,
                "truncated": truncated,
            }))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_fs_write(self):
        body = self._read_body()
        try:
            p = _resolve_safe(body.get("path", ""))
            content = body.get("content", "")
            if not isinstance(content, str):
                self._send(400, _json({"error": "content должен быть строкой"}))
                return
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(content, encoding="utf-8")
            self._send(200, _json({"ok": True, "path": str(p), "bytes": len(content.encode())}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_fs_mkdir(self):
        body = self._read_body()
        try:
            p = _resolve_safe(body.get("path", ""))
            p.mkdir(parents=True, exist_ok=True)
            self._send(200, _json({"ok": True, "path": str(p)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_fs_delete(self):
        body = self._read_body()
        try:
            p = _resolve_safe(body.get("path", ""), must_exist=True)
            # Never delete the sites root itself
            if p.resolve() == SITES_ROOT.resolve():
                self._send(400, _json({"error": "Нельзя удалить корневую папку сайтов"}))
                return
            if p.is_dir():
                shutil.rmtree(p)
            else:
                p.unlink()
            self._send(200, _json({"ok": True, "deleted": str(p)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_fs_upload(self):
        """Upload a file. Prefer raw body + ?path=&filename= (memory-safe).
        Legacy JSON {path, filename, content_b64} still works for tiny files.
        """
        try:
            ctype = (self.headers.get("Content-Type") or "").lower()
            qs = self._qs()
            if "application/json" in ctype:
                body = self._read_body()
                dest_dir = _resolve_safe(body.get("path", "") or str(SITES_ROOT))
                if dest_dir.is_file():
                    dest_dir = dest_dir.parent
                dest_dir.mkdir(parents=True, exist_ok=True)
                filename = Path(body.get("filename", "upload.bin")).name
                raw = base64.b64decode(body.get("content_b64", ""))
                if len(raw) > MAX_UPLOAD_BYTES:
                    raise ValueError("Файл слишком большой")
                out = (dest_dir / filename).resolve()
                _resolve_safe(str(out))
                out.write_bytes(raw)
                self._send(200, _json({"ok": True, "path": str(out), "bytes": len(raw)}))
                return

            path_str = qs.get("path", "") or str(SITES_ROOT)
            filename = Path(
                qs.get("filename")
                or self.headers.get("X-Filename")
                or "upload.bin"
            ).name
            if not filename or filename in {".", ".."}:
                self._send(400, _json({"error": "Некорректное имя файла"}))
                return
            dest_dir = _resolve_safe(path_str)
            if dest_dir.is_file():
                dest_dir = dest_dir.parent
            dest_dir.mkdir(parents=True, exist_ok=True)
            out = (dest_dir / filename).resolve()
            _resolve_safe(str(out))
            nbytes = self._stream_body_to_file(out)
            self._send(200, _json({"ok": True, "path": str(out), "bytes": nbytes}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    # ── Sites ────────────────────────────────────────────────────
    def _get_sites(self):
        root = _ensure_sites_root()
        sites = []
        for child in sorted(root.iterdir()):
            if child.is_dir() and not child.name.startswith("."):
                sites.append(_site_info(child.name))
        self._send(200, _json({"ok": True, "sites": sites, "sites_root": str(root)}))

    def _post_sites(self):
        body = self._read_body()
        name = (body.get("name") or "").strip()
        domain = (body.get("domain") or "").strip()
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Имя: латиница, цифры, _ и - (до 63 символов)"}))
            return
        root = _ensure_sites_root() / name
        if root.exists():
            self._send(409, _json({"error": f"Сайт уже существует: {name}"}))
            return
        root.mkdir(parents=True, exist_ok=False)
        (root / "index.html").write_text(
            f"""<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{name}</title>
  <style>
    body {{ font-family: Georgia, serif; margin: 0; min-height: 100vh;
      display: grid; place-items: center;
      background: linear-gradient(160deg, #e8efe9, #f7f3ee); color: #14201a; }}
    main {{ text-align: center; padding: 2rem; }}
    h1 {{ font-size: clamp(2rem, 6vw, 3rem); margin: 0 0 .5rem; }}
    p {{ opacity: .7; }}
  </style>
</head>
<body>
  <main>
    <h1>{name}</h1>
    <p>Сайт на AI Helper VPS. Замени этот файл на свой.</p>
  </main>
</body>
</html>
""",
            encoding="utf-8",
        )
        nginx_path = _write_nginx_vhost(name, domain)
        _fix_site_perms(root)
        info = _site_info(name)
        if nginx_path:
            info["nginx_conf"] = nginx_path
        if domain:
            info["domain"] = domain
        self._send(201, _json({"ok": True, "site": info}))

    def _delete_site(self, name: str):
        name = unquote(name).strip()
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя сайта"}))
            return
        root = _ensure_sites_root() / name
        if not root.exists():
            self._send(404, _json({"error": "Сайт не найден"}))
            return
        shutil.rmtree(root)
        conf = NGINX_SITES_DIR / f"ai-helper-{name}.conf"
        if conf.exists():
            try:
                conf.unlink()
            except OSError:
                pass
        self._send(200, _json({"ok": True, "deleted": name}))

    def _post_sites_deploy(self):
        """Deploy ZIP. Prefer raw body + ?name= (memory-safe)."""
        tmp: Optional[Path] = None
        try:
            qs = self._qs()
            ctype = (self.headers.get("Content-Type") or "").lower()
            name = (qs.get("name") or "").strip()
            filename = qs.get("filename") or self.headers.get("X-Filename") or "site.zip"

            if "json" in ctype:
                body = self._read_body()
                name = (body.get("name") or name or "").strip()
                filename = body.get("filename") or filename
                if not _SAFE_NAME.match(name):
                    self._send(400, _json({"error": "Некорректное имя сайта"}))
                    return
                root = _ensure_sites_root() / name
                root.mkdir(parents=True, exist_ok=True)
                raw = base64.b64decode(body.get("content_b64", ""))
                if len(raw) > MAX_UPLOAD_BYTES:
                    raise ValueError("Файл слишком большой")
                tmp = Path(tempfile.mkstemp(suffix=".zip")[1])
                tmp.write_bytes(raw)
            else:
                if not _SAFE_NAME.match(name):
                    self._send(400, _json({"error": "Некорректное имя сайта (?name=)"}))
                    return
                root = _ensure_sites_root() / name
                root.mkdir(parents=True, exist_ok=True)
                tmp = Path(tempfile.mkstemp(suffix=".zip")[1])
                self._stream_body_to_file(tmp)

            if str(filename).lower().endswith(".zip") or zipfile.is_zipfile(tmp):
                self._extract_zip_file(tmp, root)
            else:
                out = root / Path(str(filename)).name
                shutil.copyfile(tmp, out)
            self._send(200, _json({"ok": True, "site": _site_info(name)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))
        finally:
            if tmp and tmp.exists():
                tmp.unlink(missing_ok=True)

    def _post_sites_migrate(self):
        """One-shot migrate: create site + stream ZIP to disk + extract + optional domain.
        Memory-safe: POST /sites/migrate?name=&domain= with raw ZIP body.
        """
        tmp: Optional[Path] = None
        try:
            qs = self._qs()
            ctype = (self.headers.get("Content-Type") or "").lower()
            name = (qs.get("name") or "").strip()
            domain = (qs.get("domain") or "").strip()
            filename = qs.get("filename") or self.headers.get("X-Filename") or "site.zip"
            created = False

            if "json" in ctype:
                body = self._read_body()
                name = (body.get("name") or name or "").strip()
                domain = (body.get("domain") or domain or "").strip()
                filename = body.get("filename") or filename
                if not _SAFE_NAME.match(name):
                    self._send(400, _json({"error": "Имя: латиница, цифры, _ и -"}))
                    return
                if not body.get("content_b64"):
                    self._send(400, _json({"error": "Нужен ZIP"}))
                    return
                raw = base64.b64decode(body.get("content_b64", ""))
                if len(raw) > MAX_UPLOAD_BYTES:
                    raise ValueError(
                        f"Файл слишком большой (лимит {MAX_UPLOAD_BYTES // (1024 * 1024)} МБ)"
                    )
                tmp = Path(tempfile.mkstemp(suffix=".zip")[1])
                tmp.write_bytes(raw)
            else:
                if not _SAFE_NAME.match(name):
                    self._send(400, _json({"error": "Имя: латиница, цифры, _ и - (?name=)"}))
                    return
                tmp = Path(tempfile.mkstemp(suffix=".zip")[1])
                self._stream_body_to_file(tmp)

            root = _ensure_sites_root() / name
            if not root.exists():
                root.mkdir(parents=True, exist_ok=False)
                created = True
            else:
                root.mkdir(parents=True, exist_ok=True)

            self._extract_zip_file(tmp, root)
            nginx_path = _write_nginx_vhost(name, domain) if domain else None
            info = _site_info(name)
            if nginx_path:
                info["nginx_conf"] = nginx_path
            info["created"] = created
            info["filename"] = filename
            self._send(200, _json({"ok": True, "site": info}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))
        finally:
            if tmp and tmp.exists():
                tmp.unlink(missing_ok=True)

    def _post_sites_fix_perms(self):
        """Fix 403: chmod site files so host nginx can read them."""
        body: Dict[str, Any] = {}
        ctype = (self.headers.get("Content-Type") or "").lower()
        if "json" in ctype:
            body = self._read_body()
        qs = self._qs()
        name = (body.get("name") or qs.get("name") or "").strip()
        root = _ensure_sites_root()
        fixed: List[str] = []
        try:
            if name:
                if not _SAFE_NAME.match(name):
                    self._send(400, _json({"error": "Некорректное имя"}))
                    return
                target = root / name
                if not target.is_dir():
                    self._send(404, _json({"error": "Сайт не найден"}))
                    return
                _fix_site_perms(target)
                fixed.append(name)
            else:
                _fix_site_perms(root)
                for child in root.iterdir():
                    if child.is_dir() and not child.name.startswith("."):
                        _fix_site_perms(child)
                        fixed.append(child.name)
            self._send(200, _json({
                "ok": True,
                "fixed": fixed,
                "hint": "Если 403 остался — на VPS: bash project/deploy/fix-sites-403.sh",
            }))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _get_sites_inspect(self):
        """Where did my files go? Diagnose empty / nested / WP sites."""
        qs = self._qs()
        name = (qs.get("name") or "").strip()
        root = _ensure_sites_root()
        pending = pup.list_pending(root)
        if not name:
            sites = []
            for child in sorted(root.iterdir()):
                if child.is_dir() and not child.name.startswith("."):
                    sites.append(_site_info(child.name))
            self._send(200, _json({
                "ok": True,
                "sites_root": str(root),
                "host_sites_path": HOST_SITES_PATH,
                "sites": sites,
                "pending_uploads": pending,
                "hint": (
                    "Файлы сайтов на хосте: "
                    f"{HOST_SITES_PATH}/<имя>/  (= {root}/<имя>/ в Docker). "
                    "Незавершённые ZIP — в .uploads/"
                ),
            }))
            return
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя"}))
            return
        site_root = root / name
        info = _site_info(name) if site_root.exists() else None
        exists = site_root.exists()
        self._send(200, _json({
            "ok": True,
            "exists": exists,
            "site": info,
            "sites_root": str(root),
            "host_path": f"{HOST_SITES_PATH.rstrip('/')}/{name}",
            "container_path": str(site_root),
            "pending_uploads": pending,
            "diagnosis": (
                "Папка сайта пуста или не создана — ZIP не дошёл или не распаковался. "
                "Залей заново через chunked-загрузку (большие WP-архивы)."
                if not exists or (info and info["files"] == 0)
                else (
                    "Похоже на WordPress — нужен PHP+MySQL (см. WORDPRESS.md)."
                    if info and info.get("is_wordpress")
                    else "Файлы на месте."
                )
            ),
        }))

    def _post_upload_init(self):
        try:
            body = self._read_body()
            filename = body.get("filename") or body.get("original_filename") or "site.zip"
            # Force ASCII disk name
            filename = "".join(ch if 32 <= ord(ch) < 127 else "_" for ch in str(filename)) or "upload.bin"
            size = int(body.get("size") or 0)
            site_name = (body.get("site_name") or body.get("name") or "").strip()
            chunk_size = int(body.get("chunk_size") or CHUNK_SIZE)
            result = pup.init_upload(
                SITES_ROOT,
                filename=filename,
                size=size,
                site_name=site_name,
                chunk_size=chunk_size,
            )
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_upload_chunk(self):
        try:
            qs = self._qs()
            upload_id = (qs.get("id") or qs.get("upload_id") or "").strip()
            index = int(qs.get("index") or -1)
            if not upload_id:
                self._send(400, _json({"error": "Нужен id загрузки"}))
                return
            # Stream chunk to temp then save (chunk is small ~4MB)
            length = int(self.headers.get("Content-Length", 0))
            if length <= 0:
                self._send(400, _json({"error": "Пустой чанк"}))
                return
            if length > CHUNK_SIZE * 2:
                self._send(400, _json({"error": "Чанк слишком большой"}))
                return
            data = self.rfile.read(length)
            result = pup.save_chunk(SITES_ROOT, upload_id, index, data)
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _get_upload_status(self):
        try:
            qs = self._qs()
            upload_id = (qs.get("id") or qs.get("upload_id") or "").strip()
            if not upload_id:
                self._send(200, _json({"ok": True, "pending": pup.list_pending(SITES_ROOT)}))
                return
            self._send(200, _json(pup.status(SITES_ROOT, upload_id)))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_upload_complete(self):
        """Assemble chunks and optionally extract into a site (WordPress-ready)."""
        tmp_assembled: Optional[Path] = None
        try:
            body = self._read_body()
            upload_id = (body.get("upload_id") or body.get("id") or "").strip()
            name = (body.get("name") or body.get("site_name") or "").strip()
            domain = (body.get("domain") or "").strip()
            action = (body.get("action") or "migrate").strip()  # migrate | keep
            if not upload_id:
                self._send(400, _json({"error": "Нужен upload_id"}))
                return
            assembled = pup.assemble(SITES_ROOT, upload_id)
            tmp_assembled = assembled
            if action == "keep":
                self._send(200, _json({
                    "ok": True,
                    "assembled_path": str(assembled),
                    "host_hint": f"Файл в контейнере: {assembled}. На хосте смотри {HOST_SITES_PATH}/.uploads/",
                    "size": assembled.stat().st_size,
                }))
                return
            if not _SAFE_NAME.match(name):
                self._send(400, _json({"error": "Имя сайта: латиница, цифры, _ и -"}))
                return
            if not zipfile.is_zipfile(assembled):
                self._send(400, _json({
                    "error": "Собранный файл не ZIP. Для WordPress нужен .zip бэкап сайта.",
                    "assembled_path": str(assembled),
                    "size": assembled.stat().st_size,
                }))
                return
            root = _ensure_sites_root() / name
            root.mkdir(parents=True, exist_ok=True)
            self._extract_zip_file(assembled, root)
            if domain:
                _write_nginx_vhost(name, domain)
            info = _site_info(name)
            if info["files"] == 0:
                self._send(400, _json({
                    "error": (
                        "ZIP распакован, но файлов 0. "
                        f"Проверь архив. Путь: {info.get('host_path')}"
                    ),
                    "site": info,
                    "assembled_path": str(assembled),
                }))
                return
            self._send(200, _json({
                "ok": True,
                "site": info,
                "assembled_path": str(assembled),
                "message": (
                    "WordPress обнаружен — дальше PHP+MySQL (см. deploy/WORDPRESS.md)."
                    if info.get("is_wordpress")
                    else "Сайт развёрнут."
                ),
            }))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_sites_domain(self):
        body = self._read_body()
        name = (body.get("name") or "").strip()
        domain = (body.get("domain") or "").strip()
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя сайта"}))
            return
        if not domain:
            self._send(400, _json({"error": "Нужен domain"}))
            return
        root = _ensure_sites_root() / name
        if not root.is_dir():
            self._send(404, _json({"error": "Сайт не найден"}))
            return
        nginx_path = _write_nginx_vhost(name, domain)
        info = _site_info(name)
        info["nginx_conf"] = nginx_path
        info["domain"] = domain.strip().lower().replace("https://", "").replace("http://", "").split("/")[0]
        info["hint"] = (
            f"Домен {info['domain']} → сайт {name} (корень /, не /sites/{name}/).\n"
            f"1) DNS: A @ и www → IP VPS\n"
            f"2) На VPS: bash /opt/ai-helper/project/deploy/setup-domain.sh {name} {info['domain']}\n"
            f"3) SSL: тот же скрипт с --ssl\n"
            f"Конфиг: {nginx_path}"
        )
        self._send(200, _json({"ok": True, "site": info}))

    def _post_sites_health(self):
        """Auto-check site issues; optional auto_fix."""
        from hosting_tools import site_health_check

        body = self._read_body()
        name = (body.get("site") or body.get("name") or "").strip()
        auto_fix = bool(body.get("auto_fix") or body.get("fix"))
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя сайта"}))
            return
        root = _ensure_sites_root() / name
        if not root.is_dir():
            self._send(404, _json({"error": f"Сайт не найден: {name}"}))
            return
        result = site_health_check(str(root), auto_fix=auto_fix)
        result["site"] = name
        self._send(200, _json(result))

    def _post_sites_sync(self):
        """
        Write a file into a site from VS Code / remote clients.
        Body: { site, path, content } — path relative to site root (e.g. index.html).
        Instantly live via nginx (no separate deploy step).
        """
        body = self._read_body()
        name = (body.get("site") or body.get("name") or "").strip()
        rel = (body.get("path") or body.get("relative_path") or "").strip().lstrip("/")
        content = body.get("content", "")
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя сайта (site)"}))
            return
        if not rel or ".." in Path(rel).parts:
            self._send(400, _json({"error": "Нужен относительный path внутри сайта"}))
            return
        if not isinstance(content, str):
            self._send(400, _json({"error": "content должен быть строкой"}))
            return
        root = _ensure_sites_root() / name
        if not root.is_dir():
            self._send(404, _json({"error": f"Сайт не найден: {name}"}))
            return
        try:
            dest = (root / rel).resolve()
            if not _is_under(dest, root) and dest != root.resolve():
                raise PermissionError("Путь вне сайта")
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_text(content, encoding="utf-8")
            try:
                os.chmod(dest, 0o644)
            except OSError:
                pass
            self._send(200, _json({
                "ok": True,
                "site": name,
                "path": str(dest),
                "relative": rel.replace("\\", "/"),
                "bytes": len(content.encode("utf-8")),
                "url": f"/sites/{name}/{rel.replace(chr(92), '/')}",
                "live": True,
            }))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    # ── WordPress ────────────────────────────────────────────────
    def _get_wp_status(self):
        qs = self._qs()
        name = (qs.get("name") or "").strip()
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Нужен ?name= сайта"}))
            return
        root = _ensure_sites_root() / name
        if not root.is_dir():
            self._send(404, _json({"error": "Сайт не найден"}))
            return
        status = wpt.wp_status(root)
        status["site"] = _site_info(name)
        status["public_url"] = f"/sites/{name}/"
        domain = ""
        domain_file = root / ".ai-helper-domain"
        if domain_file.is_file():
            domain = domain_file.read_text(encoding="utf-8").strip()
        status["defaults"] = {
            "db_name": os.environ.get("MYSQL_DATABASE", "wordpress"),
            "db_user": os.environ.get("MYSQL_USER", "wp"),
            "db_host": os.environ.get("MYSQL_HOST", "mysql"),
            "db_password": os.environ.get("MYSQL_PASSWORD", ""),
            "suggested_site_url": (
                f"https://{domain}" if domain else f"http://SERVER_IP/sites/{name}"
            ),
            "domain": domain or None,
        }
        self._send(200, _json(status))

    def _post_wp_config(self):
        try:
            body = self._read_body()
            name = (body.get("name") or "").strip()
            if not _SAFE_NAME.match(name):
                self._send(400, _json({"error": "Некорректное имя сайта"}))
                return
            root = _ensure_sites_root() / name
            if not root.is_dir():
                self._send(404, _json({"error": "Сайт не найден"}))
                return
            # Empty password in form → use .env (never write blank DB_PASSWORD)
            db_password = (body.get("db_password") or "").strip() or os.environ.get(
                "MYSQL_PASSWORD", ""
            )
            root_password = (body.get("root_password") or "").strip() or None
            # Heal MySQL user to the SAME password we write into wp-config
            heal = wpt.ensure_mysql_user(
                force=True,
                password=db_password,
                root_password=root_password,
            )
            result = wpt.patch_wp_config(
                root,
                db_name=body.get("db_name") or os.environ.get("MYSQL_DATABASE", "wordpress"),
                db_user=body.get("db_user") or os.environ.get("MYSQL_USER", "wp"),
                db_password=db_password,
                db_host=body.get("db_host") or os.environ.get("MYSQL_HOST", "mysql"),
                table_prefix=body.get("table_prefix"),
            )
            result["mysql"] = heal
            _fix_site_perms(root)
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_wp_fix_db(self):
        try:
            body = self._read_body() if int(self.headers.get("Content-Length", 0) or 0) else {}
            body = body or {}
            force = bool(body.get("force", True))
            password = (body.get("password") or body.get("db_password") or "").strip() or None
            root_password = (body.get("root_password") or "").strip() or None
            result = wpt.ensure_mysql_user(
                force=force,
                password=password,
                root_password=root_password,
            )
            if result.get("ok"):
                result["db"] = wpt.test_db()
            self._send(200 if result.get("ok") else 400, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_sites_normalize(self):
        """Flatten nested hosting layout (e.g. 5mb2/5mb2.ru/ → 5mb2/)."""
        try:
            body = self._read_body()
            name = (body.get("name") or "").strip()
            if not _SAFE_NAME.match(name):
                self._send(400, _json({"error": "Некорректное имя сайта"}))
                return
            root = _ensure_sites_root() / name
            if not root.is_dir():
                self._send(404, _json({"error": "Сайт не найден"}))
                return
            before = pup.find_wp_or_public(root)
            _flatten_hosting_layout(root)
            _fix_site_perms(root)
            info = _site_info(name)
            self._send(
                200,
                _json(
                    {
                        "ok": True,
                        "site": info,
                        "webroot_before": str(before) if before else None,
                        "message": "Структура выровнена (wp-config должен быть в корне сайта)",
                    }
                ),
            )
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_wp_import_sql(self):
        """Import SQL: prefer upload_id from chunked upload, or path under sites."""
        try:
            body = self._read_body()
            name = (body.get("name") or "").strip()
            upload_id = (body.get("upload_id") or "").strip()
            sql_path_str = (body.get("path") or "").strip()
            if not _SAFE_NAME.match(name):
                self._send(400, _json({"error": "Некорректное имя сайта"}))
                return
            sql_path: Optional[Path] = None
            if upload_id:
                assembled = pup.assemble(SITES_ROOT, upload_id)
                sql_path = assembled
            elif sql_path_str:
                sql_path = _resolve_safe(sql_path_str, must_exist=True)
            else:
                self._send(400, _json({"error": "Нужен upload_id (chunked .sql) или path"}))
                return
            if not str(sql_path).lower().endswith((".sql", ".txt")) and sql_path.suffix.lower() not in {".sql", ".txt", ""}:
                # still try — some dumps have no extension
                pass
            drop_existing = bool(body.get("drop_existing", True))
            result = wpt.import_sql_file(sql_path, drop_existing=drop_existing)
            result["site"] = name
            self._send(200 if result.get("ok") else 400, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_wp_replace_url(self):
        try:
            body = self._read_body()
            name = (body.get("name") or "").strip()
            old_url = (body.get("old_url") or "").strip()
            new_url = (body.get("new_url") or "").strip()
            prefix = (body.get("table_prefix") or "wp_").strip() or "wp_"
            if name and _SAFE_NAME.match(name):
                defines = wpt.read_wp_defines(_ensure_sites_root() / name)
                prefix = defines.get("table_prefix", prefix)
            if not new_url:
                if name and _SAFE_NAME.match(name):
                    # best-effort public path; user should pass full URL with IP/domain
                    new_url = body.get("new_url") or f"/sites/{name}"
                else:
                    self._send(400, _json({"error": "Нужен new_url (http://IP/sites/mysite)"}))
                    return
            if not old_url:
                urls = wpt.get_site_urls(prefix)
                old_url = (urls.get("urls") or {}).get("siteurl") or ""
            if not old_url:
                self._send(400, _json({"error": "Не удалось определить old_url — укажи вручную"}))
                return
            result = wpt.replace_site_url(old_url, new_url, prefix)
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _get_wp_db_test(self):
        self._send(200, _json(wpt.test_db()))

    # ── POST /public/* (витрина ai — без панели / без чужих файлов) ─
    def _public_ip(self) -> str:
        import public_chat as pch
        return pch.client_ip(self)

    def _require_public_user(self, *, allow_widget_guest: bool = False):
        """Return user dict or send 401 and None."""
        import public_users as pu

        user = pu.user_from_token(pu.bearer_token(self))
        if user:
            return user
        if not pu.AUTH_REQUIRED:
            return {"id": "", "email": "", "name": "guest", "guest": True}
        # Guest widget chat (5mb2.ru etc.) — no platform login
        if allow_widget_guest and getattr(pu, "WIDGET_GUEST", True):
            return {"id": "", "email": "", "name": "widget-guest", "guest": True}
        self._send(401, _json({
            "error": "Нужен вход",
            "auth_required": True,
            "hint": "Зарегистрируйся или войди на витрине AI Helper",
        }))
        return None

    def _post_public_auth_register(self):
        import public_users as pu

        body = self._read_body()
        try:
            result = pu.register(
                body.get("email") or "",
                body.get("password") or "",
                name=body.get("name") or "",
                ip=self._public_ip(),
            )
            self._send(200, _json(result))
        except PermissionError as exc:
            self._send(429, _json({"error": str(exc)}))
        except ValueError as exc:
            self._send(400, _json({"error": str(exc)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_public_auth_login(self):
        import public_users as pu

        body = self._read_body()
        try:
            result = pu.login(body.get("email") or "", body.get("password") or "")
            self._send(200, _json(result))
        except PermissionError as exc:
            self._send(401, _json({"error": str(exc)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_public_auth_logout(self):
        import public_users as pu

        self._send(200, _json(pu.logout(pu.bearer_token(self))))

    def _get_public_auth_me(self):
        import public_users as pu

        user = pu.user_from_token(pu.bearer_token(self))
        if not user:
            self._send(401, _json({"error": "Нужен вход", "auth_required": True}))
            return
        self._send(200, _json({"ok": True, "user": user, "auth_required": pu.AUTH_REQUIRED}))

    def _post_public_me_sites(self):
        import public_users as pu
        import public_deploy as pd

        user = self._require_public_user()
        if not user or not user.get("email"):
            if user is not None and not user.get("email"):
                self._send(200, _json({"ok": True, "sites": []}))
            return
        names = pu.list_sites(user["email"])
        sites = []
        for name in names:
            meta = pd.load_meta(name) or {}
            sites.append({
                "name": name,
                "url": f"/sites/{name}/",
                "expires_at": meta.get("expires_at"),
                "created_at": meta.get("created_at"),
            })
        self._send(200, _json({"ok": True, "sites": sites, "billing": user.get("billing")}))

    def _get_public_plans(self):
        import public_plans as pp
        self._send(200, _json({"ok": True, "plans": pp.list_public_plans()}))

    def _post_public_admin_set_plan(self):
        """Owner/panel: set user plan. Requires panel Bearer token."""
        import public_users as pu

        if not self._authorized():
            self._send(401, _json({"error": "Нужен пароль панели"}))
            return
        body = self._read_body()
        try:
            result = pu.set_plan(body.get("email") or "", body.get("plan") or "")
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    def _post_public_chat(self):
        import public_chat as pch
        import public_users as pu

        body = self._read_body()
        is_widget = str(body.get("source") or "").strip().lower() in {"widget", "embed", "guest"}
        user = self._require_public_user(allow_widget_guest=is_widget)
        if user is None:
            return
        message = (body.get("message") or "").strip()
        history = body.get("history") or []
        site_hint = str(body.get("site") or body.get("site_hint") or "").strip()[:120]
        if not message:
            self._send(400, _json({"error": "Нужно поле message"}))
            return
        if user.get("email"):
            ok_q, why_q, _ = pu.consume_quota(user["email"], "chat")
            if not ok_q:
                self._send(429, _json({"error": why_q, "upgrade": True}))
                return
        ok, why = pch.check_rate_limit(pch.client_ip(self), guest=bool(user.get("guest")))
        if not ok:
            self._send(429, _json({"error": why}))
            return
        parts: List[str] = []
        err = ""
        for ev in pch.stream_public_chat(
            message, history, widget=is_widget or bool(user.get("guest")), site_hint=site_hint
        ):
            if ev["type"] == "text":
                parts.append(ev["content"])
            elif ev["type"] == "error":
                err = ev["content"]
        if err and not parts:
            self._send(400, _json({"error": err}))
            return
        self._send(200, _json({
            "ok": True,
            "response": "".join(parts),
            "model": "auto",
            "user": user.get("email") or None,
            "plan": user.get("plan"),
            "guest": bool(user.get("guest")),
        }))

    def _post_public_chat_stream(self):
        import public_chat as pch
        import public_users as pu

        body = self._read_body()
        is_widget = str(body.get("source") or "").strip().lower() in {"widget", "embed", "guest"}
        user = self._require_public_user(allow_widget_guest=is_widget)
        if user is None:
            return
        message = (body.get("message") or "").strip()
        history = body.get("history") or []
        site_hint = str(body.get("site") or body.get("site_hint") or "").strip()[:120]
        if not message:
            self._send(400, _json({"error": "Нужно поле message"}))
            return
        if user.get("email"):
            ok_q, why_q, _ = pu.consume_quota(user["email"], "chat")
            if not ok_q:
                self._send(429, _json({"error": why_q, "upgrade": True}))
                return
        ok, why = pch.check_rate_limit(pch.client_ip(self), guest=bool(user.get("guest")))
        if not ok:
            self._send(429, _json({"error": why}))
            return

        self.close_connection = True
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "close")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()

        def _sse(obj: dict) -> None:
            try:
                self.wfile.write(f"data: {json.dumps(obj, ensure_ascii=False)}\n\n".encode("utf-8"))
                self.wfile.flush()
            except (BrokenPipeError, ConnectionResetError):
                pass

        for ev in pch.stream_public_chat(
            message, history, widget=is_widget or bool(user.get("guest")), site_hint=site_hint
        ):
            _sse(ev)

    def _post_public_deploy(self):
        import public_deploy as pd
        import public_users as pu

        user = self._require_public_user()
        if user is None:
            return
        tmp: Optional[Path] = None
        try:
            if user.get("email"):
                ok_s, why_s, _ = pu.consume_quota(user["email"], "site")
                if not ok_s:
                    self._send(429, _json({"error": why_s, "upgrade": True}))
                    return
            ok, why = pd.check_rate_limit(self._public_ip())
            if not ok:
                self._send(429, _json({"error": why}))
                return
            ctype = (self.headers.get("Content-Type") or "").lower()
            filename = (
                self.headers.get("X-Filename")
                or self._qs().get("filename")
                or "site.zip"
            )
            tmp = Path(tempfile.mkstemp(suffix=".bin")[1])
            if "json" in ctype:
                body = self._read_body()
                filename = body.get("filename") or filename
                raw = base64.b64decode(body.get("content_b64") or "")
                if not raw:
                    self._send(400, _json({"error": "Нужен файл (content_b64): ZIP / tar.gz / HTML"}))
                    return
                if len(raw) > pd.MAX_ZIP:
                    self._send(400, _json({"error": f"Файл > {pd.MAX_ZIP // (1024*1024)} МБ"}))
                    return
                tmp.write_bytes(raw)
            else:
                self._stream_body_to_file(tmp, max_bytes=pd.MAX_ZIP)
            result = pd.create_deployment(
                tmp,
                ip=self._public_ip(),
                user_id=user.get("id") or "",
                user_email=user.get("email") or "",
                filename=str(filename),
            )
            if user.get("email"):
                pu.consume_quota(user["email"], "deploy")
            self._send(200, _json(result))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))
        finally:
            if tmp and tmp.exists():
                tmp.unlink(missing_ok=True)

    def _post_public_redeploy(self):
        import public_deploy as pd

        tmp: Optional[Path] = None
        try:
            qs = self._qs()
            name = (qs.get("name") or "").strip()
            token = (qs.get("token") or self.headers.get("X-Public-Token") or "").strip()
            ctype = (self.headers.get("Content-Type") or "").lower()
            filename = self.headers.get("X-Filename") or qs.get("filename") or "site.zip"
            if "json" in ctype:
                body = self._read_body()
                name = (body.get("name") or name).strip()
                token = (body.get("token") or token).strip()
                filename = body.get("filename") or filename
                raw = base64.b64decode(body.get("content_b64") or "")
                if not raw:
                    self._send(400, _json({"error": "Нужен файл (ZIP / tar.gz / HTML)"}))
                    return
                tmp = Path(tempfile.mkstemp(suffix=".bin")[1])
                tmp.write_bytes(raw)
            else:
                tmp = Path(tempfile.mkstemp(suffix=".bin")[1])
                self._stream_body_to_file(tmp, max_bytes=pd.MAX_ZIP)
            result = pd.redeploy(name, token, tmp, filename=str(filename))
            self._send(200, _json(result))
        except Exception as exc:
            code = 403 if "token" in str(exc).lower() or "Неверный" in str(exc) else 400
            self._send(code, _json({"error": str(exc)}))
        finally:
            if tmp and tmp.exists():
                tmp.unlink(missing_ok=True)

    def _post_public_files(self):
        import public_deploy as pd

        try:
            body = self._read_body()
            result = pd.list_files((body.get("name") or "").strip(), (body.get("token") or "").strip())
            self._send(200, _json(result))
        except Exception as exc:
            code = 403 if "token" in str(exc).lower() or "Неверный" in str(exc) else 400
            self._send(code, _json({"error": str(exc)}))

    def _post_public_fs_read(self):
        import public_deploy as pd

        try:
            body = self._read_body()
            result = pd.read_file(
                (body.get("name") or "").strip(),
                (body.get("token") or "").strip(),
                (body.get("path") or "").strip(),
            )
            self._send(200, _json(result))
        except Exception as exc:
            code = 403 if "token" in str(exc).lower() or "Неверный" in str(exc) else 400
            self._send(code, _json({"error": str(exc)}))

    def _post_public_fs_write(self):
        import public_deploy as pd

        try:
            body = self._read_body()
            result = pd.write_file(
                (body.get("name") or "").strip(),
                (body.get("token") or "").strip(),
                (body.get("path") or "").strip(),
                body.get("content") or "",
            )
            self._send(200, _json(result))
        except Exception as exc:
            code = 403 if "token" in str(exc).lower() or "Неверный" in str(exc) else 400
            self._send(code, _json({"error": str(exc)}))

    def _post_public_feedback(self):
        import public_feedback as pf

        try:
            body = self._read_body()
            # honeypot
            if (body.get("website") or "").strip():
                self._send(200, _json({"ok": True, "message": "Спасибо!"}))
                return
            ip = self.client_address[0] if self.client_address else ""
            result = pf.save_feedback(
                kind=(body.get("type") or body.get("kind") or "idea"),
                message=body.get("message") or "",
                email=body.get("email") or "",
                page=body.get("page") or "",
                source=(body.get("source") or "ai-helper"),
                ip=ip,
            )
            self._send(200, _json(result))
        except ValueError as exc:
            self._send(400, _json({"error": str(exc)}))
        except Exception as exc:
            self._send(500, _json({"error": str(exc)}))

    def _get_feedback(self):
        """Inbox для панели владельца (нужен PANEL_PASSWORD)."""
        import public_feedback as pf

        qs = self._qs()
        try:
            limit = int(qs.get("limit") or 100)
        except ValueError:
            limit = 100
        items = pf.list_feedback(limit=limit)
        self._send(200, _json({"ok": True, "items": items, "count": len(items)}))

    def _local_watchdog_ok(self) -> bool:
        """Cron на VPS может дергать watchdog без Bearer с localhost."""
        ip = ""
        try:
            ip = (self.client_address[0] if self.client_address else "") or ""
        except Exception:
            ip = ""
        return ip in {"127.0.0.1", "::1", "localhost"}

    def _get_system_health(self):
        import system_health as sh

        qs = self._qs()
        base = (qs.get("base") or "").strip()
        host = (qs.get("host") or "").strip()
        report = sh.check_targets(base_url=base, host=host)
        self._send(200, _json({"ok": True, **report}))

    def _get_system_incidents(self):
        import system_health as sh

        qs = self._qs()
        try:
            limit = int(qs.get("limit") or 50)
        except ValueError:
            limit = 50
        items = sh.list_incidents(limit=limit)
        self._send(200, _json({"ok": True, "items": items, "count": len(items)}))

    def _get_system_overview(self):
        import system_overview as so

        # reuse status payload bits
        status_payload: Dict[str, Any] = {}
        try:
            import free_llm as fl

            settings = load_settings()
            ost = check_ollama_status(settings.ollama_host)
            status_payload = {
                "deepseek": bool(
                    settings.deepseek_api_key
                    or os.environ.get("DEEPSEEK_API_KEY", "").strip()
                ),
                "deepseek_model": settings.deepseek_model,
                "ollama": ost.reachable,
                "free_llm": True,
                "llm_prefer_free": fl.prefer_free(),
                "version": "2.10.0",
                "allowed_roots": [str(r) for r in ALLOWED_ROOTS],
                "sites_root": str(SITES_ROOT),
            }
        except Exception as exc:
            status_payload = {"error": str(exc)[:200]}
        overview = so.build_overview(
            api_status=status_payload,
            sites_root=_ensure_sites_root(),
        )
        self._send(200, _json(overview))

    def _get_system_dns(self):
        import dns_tools as dt

        qs = self._qs()
        domain = (qs.get("domain") or "").strip()
        if not domain:
            # all known domains
            import system_overview as so

            items = so._collect_domains(_ensure_sites_root())
            self._send(200, _json({
                "ok": True,
                "vps_ip": dt.detect_vps_ip(),
                "items": items,
            }))
            return
        info = dt.lookup_domain(domain, expected_ip=dt.detect_vps_ip())
        self._send(200, _json(info))

    def _post_system_watchdog(self):
        import system_health as sh

        try:
            body = self._read_body()
        except Exception:
            body = {}
        if not isinstance(body, dict):
            body = {}
        remediate = bool(body.get("remediate", True))
        ask_ai = bool(body.get("ask_deepseek") or body.get("ask_ai"))
        result = sh.run_watchdog(
            remediate=remediate,
            ask_ai=ask_ai,
            base_url=(body.get("base") or "").strip(),
            host=(body.get("host") or "").strip(),
        )
        self._send(200, _json({"ok": True, **result}))

    # ── Chats (persistent) ───────────────────────────────────────
    def _get_chats(self):
        qs = self._qs()
        site = (qs.get("site") or "").strip()
        items = chats.list_chats(site_id=site)
        self._send(200, _json({"ok": True, "chats": items}))

    def _get_chat(self, chat_id: str):
        chat = chats.get_chat(chat_id)
        if not chat:
            self._send(404, _json({"error": "Чат не найден"}))
            return
        self._send(200, _json({"ok": True, "chat": chat}))

    def _post_chats(self):
        body = self._read_body()
        site = (body.get("site") or body.get("site_id") or "").strip()
        title = (body.get("title") or "Новый чат").strip()
        chat = chats.create_chat(site_id=site, title=title)
        self._send(200, _json({"ok": True, "chat": chat}))

    def _post_chat_rename(self):
        body = self._read_body()
        chat_id = (body.get("id") or body.get("chat_id") or "").strip()
        title = (body.get("title") or "").strip()
        if not chat_id:
            self._send(400, _json({"error": "Нужен id чата"}))
            return
        chat = chats.rename_chat(chat_id, title)
        if not chat:
            self._send(404, _json({"error": "Чат не найден"}))
            return
        self._send(200, _json({"ok": True, "chat": chat}))

    def _delete_chat(self, chat_id: str):
        ok = chats.delete_chat(chat_id)
        self._send(200 if ok else 404, _json({"ok": ok}))

    def _get_context(self):
        """Site/project snapshot for the panel chat UI."""
        from agent import _project_snapshot
        from hosting_tools import build_site_card

        qs = self._qs()
        site = (qs.get("site") or "").strip()
        proj = (qs.get("project") or "").strip()
        settings, profile, memory, project_root = self._load_context(proj, site)
        snapshot = _project_snapshot(project_root, max_files=80) if project_root else ""
        card = build_site_card(project_root) if project_root else ""
        tree: List[str] = []
        info: Dict[str, Any] = {}
        if project_root and project_root.is_dir():
            r = list_dir(str(project_root), recursive=False)
            if r.get("ok"):
                tree = list(r.get("items") or [])[:80]
            if site and _SAFE_NAME.match(site):
                try:
                    info = _site_info(site)
                except Exception:
                    info = {}
        self._send(200, _json({
            "ok": True,
            "site": site or None,
            "project": project_root.name if project_root else None,
            "project_root": str(project_root) if project_root else None,
            "snapshot": snapshot,
            "card": card,
            "tree": tree,
            "can_edit": bool(project_root),
            "is_wordpress": bool(info.get("is_wordpress")),
            "domain": info.get("domain"),
            "has_index": info.get("has_index"),
            "url": info.get("url"),
        }))

    # ── POST /chat ───────────────────────────────────────────────
    def _post_chat(self):
        body = self._read_body()
        message = body.get("message", "").strip()
        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"}))
            return
        proj_name = body.get("project", "")
        site_name = (body.get("site") or "").strip()
        history = body.get("history", [])
        chat_id = (body.get("chat_id") or "").strip()
        settings, profile, memory, project_root = self._load_context(proj_name, site_name)

        if chat_id:
            stored = chats.history_for_agent(chat_id)
            if stored:
                history = stored
            chats.add_message(chat_id, "user", message)

        t0 = time.time()
        text = _run_agent_sync(message, project_root, settings, profile, memory, history)
        elapsed = round(time.time() - t0, 2)

        if chat_id:
            chats.add_message(chat_id, "assistant", text)

        self._send(200, _json({
            "ok": True,
            "response": text,
            "elapsed_sec": elapsed,
            "project": project_root.name if project_root else None,
            "chat_id": chat_id or None,
        }))

    # ── POST /chat/stream (SSE) ──────────────────────────────────
    def _post_chat_stream(self):
        from agent import run_agent

        body = self._read_body()
        message = body.get("message", "").strip()
        proj_name = body.get("project", "")
        site_name = (body.get("site") or "").strip()
        history = body.get("history", [])
        chat_id = (body.get("chat_id") or "").strip()

        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"}))
            return

        settings, profile, memory, project_root = self._load_context(proj_name, site_name)

        # Auto-create / bind persistent chat
        if not chat_id:
            chat = chats.create_chat(
                site_id=site_name,
                title=chats.auto_title_from_message(message),
            )
            chat_id = chat["id"]
        else:
            existing = chats.get_chat(chat_id)
            if not existing:
                chat = chats.create_chat(
                    site_id=site_name,
                    title=chats.auto_title_from_message(message),
                )
                chat_id = chat["id"]
            else:
                stored = chats.history_for_agent(chat_id)
                if stored:
                    history = stored
                if existing.get("title") in ("", "Новый чат") and message:
                    chats.rename_chat(chat_id, chats.auto_title_from_message(message))

        chats.add_message(chat_id, "user", message)

        self.close_connection = True
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "close")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()

        def _sse(obj: dict) -> None:
            data = json.dumps(obj, ensure_ascii=False)
            try:
                self.wfile.write(f"data: {data}\n\n".encode("utf-8"))
                self.wfile.flush()
            except (BrokenPipeError, ConnectionResetError):
                pass

        assistant_parts: List[str] = []
        tool_events: List[Dict[str, Any]] = []
        _sse({"type": "chat", "chat_id": chat_id, "site": site_name or None,
              "project": project_root.name if project_root else None,
              "project_root": str(project_root) if project_root else None})

        try:
            for ev in run_agent(
                user_message=message,
                chat_history=history,
                project_root=project_root,
                profile=profile,
                memory=memory,
                llm_model=settings.llm_model,
                ollama_host=settings.ollama_host,
                context_window=settings.context_window,
                fast_llm_model=settings.fast_llm_model,
                groq_api_key=settings.groq_api_key,
                groq_model=settings.groq_model,
                deepseek_api_key=settings.deepseek_api_key,
                deepseek_model=settings.deepseek_model,
                http_proxy=settings.http_proxy,
            ):
                if ev.type == "text":
                    assistant_parts.append(ev.content)
                    _sse({"type": "text", "content": ev.content})
                elif ev.type == "error":
                    _sse({"type": "error", "content": ev.content})
                    if assistant_parts or tool_events:
                        chats.add_message(
                            chat_id, "assistant", "".join(assistant_parts),
                            meta={"tools": tool_events, "error": ev.content},
                        )
                    _sse({"type": "done", "chat_id": chat_id})
                    return
                elif ev.type == "tool_call":
                    tool_events.append({"name": ev.tool_name, "args": ev.tool_args})
                    _sse({"type": "tool_call", "name": ev.tool_name, "args": ev.tool_args})
                elif ev.type == "tool_result":
                    tr = ev.tool_result or {}
                    summary = {
                        "name": ev.tool_name,
                        "ok": bool(tr.get("ok")),
                        "path": tr.get("path") or tr.get("deleted") or tr.get("dst"),
                        "edited": bool(tr.get("edited")),
                        "added": tr.get("added"),
                        "removed": tr.get("removed"),
                        "applied": tr.get("applied"),
                        "total": tr.get("total"),
                        "error": tr.get("error"),
                        "diff": (tr.get("diff") or "")[:1200],
                    }
                    if ev.tool_name == "apply_edits" and tr.get("results"):
                        summary["paths"] = [
                            x.get("path") for x in tr["results"] if x.get("path")
                        ][:20]
                    tool_events.append({"result": summary})
                    chats.add_message(
                        chat_id, "tool",
                        f"{ev.tool_name}: {'ok' if summary['ok'] else 'fail'}"
                        + (f" → {summary['path']}" if summary.get("path") else ""),
                        meta=summary,
                    )
                    _sse({"type": "tool_result", "name": ev.tool_name, "result": summary})
                elif ev.type == "info":
                    _sse({"type": "info", "content": ev.content})
                elif ev.type == "done":
                    chats.add_message(
                        chat_id, "assistant", "".join(assistant_parts),
                        meta={"tools": tool_events} if tool_events else {},
                    )
                    _sse({"type": "done", "chat_id": chat_id})
                    return
        except Exception as exc:
            _sse({"type": "error", "content": str(exc)})
            chats.add_message(
                chat_id, "assistant", "".join(assistant_parts),
                meta={"error": str(exc), "tools": tool_events},
            )
            _sse({"type": "done", "chat_id": chat_id})

    # ── POST /smart-commit ───────────────────────────────────────
    def _post_smart_commit(self):
        import re
        import shlex

        body = self._read_body()
        # Prefer site name from extension; ignore local Windows paths
        proj_name = (body.get("site") or body.get("project") or "").strip()
        if proj_name and (":" in proj_name or proj_name.startswith("/") or "\\" in proj_name):
            # Looks like a local filesystem path — use configured site / default project
            proj_name = ""
        push = body.get("push", False)
        settings, profile, memory, project_root = self._load_context(proj_name)

        if not project_root:
            self._send(404, _json({"error": "Нет активного проекта / сайта. Укажи site в запросе."}))
            return

        diff_result = git_run("diff --cached --stat", str(project_root))
        if not diff_result.get("output", "").strip():
            git_run("add -A", str(project_root))
            diff_result = git_run("diff --cached --stat", str(project_root))

        diff_text = diff_result.get("output", "нет изменений")
        prompt = (
            f"Сгенерируй краткое git commit message (1 строка, английский, формат 'type: description') "
            f"для следующих изменений:\n\n{diff_text[:3000]}\n\n"
            f"Только сообщение, без объяснений."
        )
        commit_msg = _run_agent_sync(prompt, project_root, settings, profile, memory)
        commit_msg = commit_msg.strip().split("\n")[0].strip('"').strip("'")
        commit_msg = re.sub(r"[\r\n\x00\"\\]", " ", commit_msg).strip()[:180] or "chore: update"

        git_run("add -A", str(project_root))
        commit_r = git_run(f"commit -m {shlex.quote(commit_msg)}", str(project_root))
        result = {"ok": commit_r["ok"], "message": commit_msg, "output": commit_r.get("output")}

        if push and commit_r["ok"]:
            push_r = git_run("push", str(project_root))
            result["pushed"] = push_r["ok"]
            result["push_output"] = push_r.get("output")

        self._send(200 if result["ok"] else 500, _json(result))

    # ── Routing ──────────────────────────────────────────────────
    def do_GET(self):
        self._safe_handler(self._do_GET_inner)

    def _do_GET_inner(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        if path == "/status":
            self._get_status()
            return
        if path == "/auth/check":
            self._get_auth_check()
            return
        if path == "/public/auth/me":
            self._get_public_auth_me()
            return
        if path == "/public/plans":
            self._get_public_plans()
            return
        if path not in _PUBLIC_PATHS and not self._require_auth():
            return
        if path == "/project/files":
            self._get_project_files()
        elif path == "/fs/list":
            self._get_fs_list()
        elif path == "/sites":
            self._get_sites()
        elif path == "/sites/inspect":
            self._get_sites_inspect()
        elif path == "/upload/status":
            self._get_upload_status()
        elif path == "/wp/status":
            self._get_wp_status()
        elif path == "/wp/db-test":
            self._get_wp_db_test()
        elif path == "/chats":
            self._get_chats()
        elif path.startswith("/chats/"):
            self._get_chat(path[len("/chats/"):])
        elif path == "/context":
            self._get_context()
        elif path == "/feedback":
            self._get_feedback()
        elif path == "/system/health":
            self._get_system_health()
        elif path == "/system/incidents":
            self._get_system_incidents()
        elif path == "/system/overview":
            self._get_system_overview()
        elif path == "/system/dns":
            self._get_system_dns()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))

    def do_POST(self):
        self._safe_handler(self._do_POST_inner)

    def _do_POST_inner(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        if path == "/auth/login":
            self._post_auth_login()
            return
        if path == "/public/auth/register":
            self._post_public_auth_register()
            return
        if path == "/public/auth/login":
            self._post_public_auth_login()
            return
        if path == "/public/auth/logout":
            self._post_public_auth_logout()
            return
        if path == "/public/me/sites":
            self._post_public_me_sites()
            return
        if path == "/public/admin/set-plan":
            self._post_public_admin_set_plan()
            return
        if path == "/public/chat":
            self._post_public_chat()
            return
        if path == "/public/chat/stream":
            self._post_public_chat_stream()
            return
        if path == "/public/deploy":
            self._post_public_deploy()
            return
        if path == "/public/redeploy":
            self._post_public_redeploy()
            return
        if path == "/public/files":
            self._post_public_files()
            return
        if path == "/public/fs/read":
            self._post_public_fs_read()
            return
        if path == "/public/fs/write":
            self._post_public_fs_write()
            return
        if path == "/public/feedback":
            self._post_public_feedback()
            return
        if path == "/system/watchdog":
            if self._local_watchdog_ok() or self._require_auth():
                self._post_system_watchdog()
            return
        if path not in _PUBLIC_PATHS and not self._require_auth():
            return
        routes = {
            "/chat": self._post_chat,
            "/chat/stream": self._post_chat_stream,
            "/chats": self._post_chats,
            "/chats/rename": self._post_chat_rename,
            "/smart-commit": self._post_smart_commit,
            "/project/read": self._post_project_read,
            "/fs/read": self._post_fs_read,
            "/fs/write": self._post_fs_write,
            "/fs/mkdir": self._post_fs_mkdir,
            "/fs/delete": self._post_fs_delete,
            "/fs/upload": self._post_fs_upload,
            "/sites": self._post_sites,
            "/sites/deploy": self._post_sites_deploy,
            "/sites/migrate": self._post_sites_migrate,
            "/sites/domain": self._post_sites_domain,
            "/sites/sync": self._post_sites_sync,
            "/sites/health": self._post_sites_health,
            "/sites/fix-perms": self._post_sites_fix_perms,
            "/sites/normalize": self._post_sites_normalize,
            "/upload/init": self._post_upload_init,
            "/upload/chunk": self._post_upload_chunk,
            "/upload/complete": self._post_upload_complete,
            "/wp/config": self._post_wp_config,
            "/wp/fix-db": self._post_wp_fix_db,
            "/wp/import-sql": self._post_wp_import_sql,
            "/wp/replace-url": self._post_wp_replace_url,
        }
        handler = routes.get(path)
        if handler:
            handler()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))

    def do_DELETE(self):
        self._safe_handler(self._do_DELETE_inner)

    def _do_DELETE_inner(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        if not self._require_auth():
            return
        if path.startswith("/sites/"):
            name = path[len("/sites/"):]
            self._delete_site(name)
        elif path.startswith("/chats/"):
            self._delete_chat(path[len("/chats/"):])
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))


def main():
    _ensure_sites_root()
    try:
        heal = wpt.ensure_mysql_user(force=False)
        print(f"[mysql] {heal.get('message') or heal.get('error') or heal}", flush=True)
    except Exception as exc:
        print(f"[mysql] skip ensure on boot: {exc}", flush=True)
    server = ThreadingHTTPServer((BIND_HOST, PORT), APIHandler)
    print(f"AI Helper API  →  http://{BIND_HOST}:{PORT}", flush=True)
    print(f"  sites root    →  {SITES_ROOT}", flush=True)
    print(f"  GET  /status /fs/list /sites", flush=True)
    print(f"  POST /chat/stream /fs/* /sites /sites/deploy", flush=True)
    print(f"\nCtrl+C для остановки", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nAPI server stopped.")


if __name__ == "__main__":
    main()
