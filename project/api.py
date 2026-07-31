"""
api.py — REST API сервер для AI Helper.

Запуск: python api.py  (порт 8502)
Используется для:
  - Панели Next.js (чат, файлы, сайты)
  - Интеграции с VS Code
  - Внешних клиентов

Endpoints:
  GET    /status
  POST   /chat
  POST   /chat/stream
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
"""
from __future__ import annotations

import base64
import io
import json
import os
import re
import shutil
import sys
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

DATA_DIR = Path.home() / ".ai-helper"
PORT = int(os.environ.get("AI_HELPER_API_PORT", os.environ.get("API_PORT", "8502")))
BIND_HOST = os.environ.get("AI_HELPER_API_HOST", "0.0.0.0")

SITES_ROOT = Path(os.environ.get("SITES_ROOT", "/opt/sites")).resolve()
NGINX_SITES_DIR = Path(os.environ.get("NGINX_SITES_DIR", "/etc/nginx/sites-available"))

_SAFE_NAME = re.compile(r"^[a-zA-Z0-9][a-zA-Z0-9_-]{0,62}$")


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
    return {
        "name": name,
        "path": str(root),
        "url": f"/sites/{name}/",
        "files": files,
        "size_bytes": size,
        "has_index": (root / "index.html").is_file() or (root / "index.htm").is_file(),
    }


def _write_nginx_vhost(name: str, domain: str = "") -> Optional[str]:
    """Write optional nginx vhost snippet for a custom domain (best-effort)."""
    if not domain.strip():
        return None
    domain = domain.strip().lower()
    conf = f"""# AI Helper site: {name}
server {{
    listen 80;
    server_name {domain} www.{domain};
    root {SITES_ROOT / name};
    index index.html index.htm;

    location / {{
        try_files $uri $uri/ =404;
    }}
}}
"""
    try:
        NGINX_SITES_DIR.mkdir(parents=True, exist_ok=True)
        out = NGINX_SITES_DIR / f"ai-helper-{name}.conf"
        out.write_text(conf, encoding="utf-8")
        return str(out)
    except Exception:
        # Inside Docker / without root — store next to site instead
        out = SITES_ROOT / name / "nginx.vhost.conf"
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(conf, encoding="utf-8")
        return str(out)


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
    def log_message(self, fmt, *args):
        print(f"[api] {self.address_string()} {fmt % args}", flush=True)

    def _send(self, code: int, body: bytes, content_type: str = "application/json") -> None:
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self._send(204, b"")

    def _read_body(self) -> Dict[str, Any]:
        length = int(self.headers.get("Content-Length", 0))
        if length:
            return json.loads(self.rfile.read(length).decode("utf-8"))
        return {}

    def _load_context(self, project_name: str = "") -> tuple:
        settings = load_settings()
        profile = load_profile()
        memory = MemoryStore()
        projects = load_projects()
        if project_name and project_name in projects:
            project_root = Path(projects[project_name].root)
        elif projects:
            project_root = Path(list(projects.values())[0].root)
        else:
            project_root = None
        return settings, profile, memory, project_root

    # ── GET /status ──────────────────────────────────────────────
    def _get_status(self):
        settings = load_settings()
        ost = check_ollama_status(settings.ollama_host)
        projects = load_projects()
        deepseek = bool(
            settings.deepseek_api_key
            or os.environ.get("DEEPSEEK_API_KEY", "").strip()
        )
        self._send(200, _json({
            "ok": True,
            "ollama": ost.reachable,
            "models": ost.models,
            "groq": bool(settings.groq_api_key or os.environ.get("GROQ_API_KEY", "").strip()),
            "groq_model": settings.groq_model,
            "deepseek": deepseek,
            "deepseek_model": settings.deepseek_model,
            "llm_model": settings.llm_model,
            "fast_model": settings.fast_llm_model,
            "projects": list(projects.keys()),
            "sites_root": str(SITES_ROOT),
            "allowed_roots": [str(r) for r in ALLOWED_ROOTS],
            "version": "1.1",
        }))

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
        if not path:
            self._send(400, _json({"error": "Нужен путь (path)"}))
            return
        r = read_file(path)
        self._send(200 if r["ok"] else 404, _json(r))

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
        body = self._read_body()
        try:
            dest_dir = _resolve_safe(body.get("path", "") or str(SITES_ROOT))
            if dest_dir.is_file():
                dest_dir = dest_dir.parent
            dest_dir.mkdir(parents=True, exist_ok=True)
            filename = Path(body.get("filename", "upload.bin")).name
            if not filename or filename in {".", ".."}:
                self._send(400, _json({"error": "Некорректное имя файла"}))
                return
            raw = base64.b64decode(body.get("content_b64", ""))
            out = (dest_dir / filename).resolve()
            if not _is_under(out, dest_dir) and out.parent != dest_dir:
                # still must be under allowed roots
                _resolve_safe(str(out))
            out.write_bytes(raw)
            self._send(200, _json({"ok": True, "path": str(out), "bytes": len(raw)}))
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
        body = self._read_body()
        name = (body.get("name") or "").strip()
        filename = body.get("filename") or "site.zip"
        if not _SAFE_NAME.match(name):
            self._send(400, _json({"error": "Некорректное имя сайта"}))
            return
        root = _ensure_sites_root() / name
        root.mkdir(parents=True, exist_ok=True)
        try:
            raw = base64.b64decode(body.get("content_b64", ""))
            if filename.lower().endswith(".zip") or raw[:2] == b"PK":
                with zipfile.ZipFile(io.BytesIO(raw)) as zf:
                    # Prevent zip-slip
                    for member in zf.infolist():
                        target = (root / member.filename).resolve()
                        if not _is_under(target, root) and target != root.resolve():
                            raise PermissionError(f"Небезопасный путь в ZIP: {member.filename}")
                    zf.extractall(root)
                # If zip had a single top-level folder, flatten common case
                children = [c for c in root.iterdir() if not c.name.startswith(".")]
                if len(children) == 1 and children[0].is_dir() and not (root / "index.html").exists():
                    top = children[0]
                    for item in top.iterdir():
                        dest = root / item.name
                        if dest.exists():
                            if dest.is_dir():
                                shutil.rmtree(dest)
                            else:
                                dest.unlink()
                        shutil.move(str(item), str(dest))
                    shutil.rmtree(top)
            else:
                out = root / Path(filename).name
                out.write_bytes(raw)
            self._send(200, _json({"ok": True, "site": _site_info(name)}))
        except Exception as exc:
            self._send(400, _json({"error": str(exc)}))

    # ── POST /chat ───────────────────────────────────────────────
    def _post_chat(self):
        body = self._read_body()
        message = body.get("message", "").strip()
        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"}))
            return
        proj_name = body.get("project", "")
        history = body.get("history", [])
        settings, profile, memory, project_root = self._load_context(proj_name)

        t0 = time.time()
        text = _run_agent_sync(message, project_root, settings, profile, memory, history)
        elapsed = round(time.time() - t0, 2)

        self._send(200, _json({
            "ok": True,
            "response": text,
            "elapsed_sec": elapsed,
            "project": project_root.name if project_root else None,
        }))

    # ── POST /chat/stream (SSE) ──────────────────────────────────
    def _post_chat_stream(self):
        from agent import run_agent

        body = self._read_body()
        message = body.get("message", "").strip()
        proj_name = body.get("project", "")
        history = body.get("history", [])

        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"}))
            return

        settings, profile, memory, project_root = self._load_context(proj_name)

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
                    _sse({"type": "text", "content": ev.content})
                elif ev.type == "error":
                    _sse({"type": "error", "content": ev.content})
                    _sse({"type": "done"})
                    return
                elif ev.type == "tool_call":
                    _sse({"type": "tool_call", "name": ev.tool_name, "args": ev.tool_args})
                elif ev.type == "info":
                    _sse({"type": "info", "content": ev.content})
                elif ev.type == "done":
                    _sse({"type": "done"})
                    return
        except Exception as exc:
            _sse({"type": "error", "content": str(exc)})
            _sse({"type": "done"})

    # ── POST /smart-commit ───────────────────────────────────────
    def _post_smart_commit(self):
        body = self._read_body()
        proj_name = body.get("project", "")
        push = body.get("push", False)
        settings, profile, memory, project_root = self._load_context(proj_name)

        if not project_root:
            self._send(404, _json({"error": "Нет активного проекта"}))
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

        git_run("add -A", str(project_root))
        commit_r = git_run(f'commit -m "{commit_msg}"', str(project_root))
        result = {"ok": commit_r["ok"], "message": commit_msg, "output": commit_r.get("output")}

        if push and commit_r["ok"]:
            push_r = git_run("push", str(project_root))
            result["pushed"] = push_r["ok"]
            result["push_output"] = push_r.get("output")

        self._send(200 if result["ok"] else 500, _json(result))

    # ── Routing ──────────────────────────────────────────────────
    def do_GET(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        if path == "/status":
            self._get_status()
        elif path == "/project/files":
            self._get_project_files()
        elif path == "/fs/list":
            self._get_fs_list()
        elif path == "/sites":
            self._get_sites()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))

    def do_POST(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        routes = {
            "/chat": self._post_chat,
            "/chat/stream": self._post_chat_stream,
            "/smart-commit": self._post_smart_commit,
            "/project/read": self._post_project_read,
            "/fs/read": self._post_fs_read,
            "/fs/write": self._post_fs_write,
            "/fs/mkdir": self._post_fs_mkdir,
            "/fs/delete": self._post_fs_delete,
            "/fs/upload": self._post_fs_upload,
            "/sites": self._post_sites,
            "/sites/deploy": self._post_sites_deploy,
        }
        handler = routes.get(path)
        if handler:
            handler()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))

    def do_DELETE(self):
        path = urlparse(self.path).path.rstrip("/") or "/"
        if path.startswith("/sites/"):
            name = path[len("/sites/"):]
            self._delete_site(name)
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))


def main():
    _ensure_sites_root()
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
