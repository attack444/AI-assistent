"""Public deploy sandbox — archive/HTML → /sites/pXXXX/ with edit token. No panel auth."""
from __future__ import annotations

import hashlib
import hmac
import json
import os
import re
import secrets
import shutil
import tarfile
import threading
import time
import zipfile
from collections import defaultdict, deque
from pathlib import Path
from typing import Any, Deque, Dict, List, Optional, Tuple

_SAFE_PUB = re.compile(r"^p[a-z0-9]{7,15}$")
_RESERVED = frozenset({"ai", "5mb2", "admin", "panel", "www", "root", "mysql", "api"})

_ALLOWED_EXT = frozenset({
    ".html", ".htm", ".css", ".js", ".mjs", ".json", ".svg", ".png", ".jpg",
    ".jpeg", ".gif", ".webp", ".ico", ".woff", ".woff2", ".ttf", ".otf",
    ".txt", ".md", ".map", ".xml", ".webmanifest", ".csv",
})

MAX_ZIP = int(os.environ.get("PUBLIC_DEPLOY_MAX_ZIP", str(15 * 1024 * 1024)))
MAX_FILES = int(os.environ.get("PUBLIC_DEPLOY_MAX_FILES", "400"))
MAX_UNCOMPRESSED = int(os.environ.get("PUBLIC_DEPLOY_MAX_BYTES", str(40 * 1024 * 1024)))
RATE_LIMIT = int(os.environ.get("PUBLIC_DEPLOY_RATE_LIMIT", "8"))
RATE_WINDOW = int(os.environ.get("PUBLIC_DEPLOY_RATE_WINDOW", "3600"))
TTL_DAYS = int(os.environ.get("PUBLIC_DEPLOY_TTL_DAYS", "14"))

META_DIR = Path(os.environ.get("PUBLIC_META_DIR", "/root/.ai-helper/public-sites"))
SITES_ROOT = Path(os.environ.get("SITES_ROOT", "/opt/sites")).resolve()

_lock = threading.Lock()
_hits: Dict[str, Deque[float]] = defaultdict(deque)


def check_rate_limit(ip: str) -> Tuple[bool, str]:
    now = time.time()
    with _lock:
        q = _hits[ip or "unknown"]
        while q and now - q[0] > RATE_WINDOW:
            q.popleft()
        if len(q) >= RATE_LIMIT:
            return False, f"Лимит деплоев: {RATE_LIMIT} / час. Подожди или запроси доступ."
        q.append(now)
    return True, ""


def _hash_token(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def new_site_name() -> str:
    for _ in range(20):
        name = "p" + secrets.token_hex(4)
        if name not in _RESERVED and not (SITES_ROOT / name).exists():
            return name
    raise RuntimeError("Не удалось выделить имя сайта")


def meta_path(name: str) -> Path:
    return META_DIR / f"{name}.json"


def save_meta(name: str, data: dict) -> None:
    META_DIR.mkdir(parents=True, exist_ok=True)
    meta_path(name).write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")


def load_meta(name: str) -> Optional[dict]:
    p = meta_path(name)
    if not p.is_file():
        return None
    try:
        return json.loads(p.read_text(encoding="utf-8"))
    except Exception:
        return None


def verify_token(name: str, token: str) -> bool:
    if not _SAFE_PUB.match(name or ""):
        return False
    meta = load_meta(name)
    if not meta:
        return False
    exp = float(meta.get("expires_at") or 0)
    if exp and time.time() > exp:
        return False
    expected = meta.get("token_hash") or ""
    return bool(expected) and hmac.compare_digest(expected, _hash_token(token or ""))


def is_public_site(name: str) -> bool:
    return bool(_SAFE_PUB.match(name or "")) and meta_path(name).is_file()


def _safe_member(name: str) -> bool:
    if not name or name.endswith("/"):
        return True
    p = Path(name)
    if p.is_absolute() or ".." in p.parts:
        return False
    if any(part.startswith(".") and part not in {".well-known"} for part in p.parts):
        # allow normal files; hide dotfiles except .well-known
        if any(part.startswith(".") and part != ".well-known" for part in p.parts[:-1]):
            return False
        if p.name.startswith(".") and p.name not in {".well-known"}:
            return False
    ext = p.suffix.lower()
    if not ext:
        return p.name.lower() in {"makefile", "license", "readme"}
    return ext in _ALLOWED_EXT


def _atomic_replace_root(root: Path, populate) -> Dict[str, Any]:
    """Populate a staging dir, then swap into root only after success.

    Avoids wiping a live site when the archive is invalid or over limits
    (critical for redeploy).
    """
    root = root.resolve()
    parent = root.parent
    parent.mkdir(parents=True, exist_ok=True)
    staging = parent / f".staging-{root.name}-{secrets.token_hex(8)}"
    if staging.exists():
        shutil.rmtree(staging)
    staging.mkdir(parents=True, exist_ok=True)

    try:
        stats = populate(staging)
        if root.exists():
            backup = parent / f".backup-{root.name}-{secrets.token_hex(8)}"
            root.rename(backup)
            try:
                staging.rename(root)
            except Exception:
                # Roll back so the previous site stays available.
                if not root.exists() and backup.exists():
                    backup.rename(root)
                raise
            shutil.rmtree(backup, ignore_errors=True)
        else:
            staging.rename(root)
        return stats
    except Exception:
        if staging.exists():
            shutil.rmtree(staging, ignore_errors=True)
        raise


def _flatten_single_folder(root: Path) -> None:
    entries = [e for e in root.iterdir() if e.name not in {".user.ini"}]
    if len(entries) == 1 and entries[0].is_dir():
        inner = entries[0]
        for child in list(inner.iterdir()):
            dest = root / child.name
            if dest.exists():
                if dest.is_dir():
                    shutil.rmtree(dest)
                else:
                    dest.unlink()
            shutil.move(str(child), str(dest))
        shutil.rmtree(inner, ignore_errors=True)


def _ensure_index(root: Path) -> None:
    if (root / "index.html").exists() or (root / "index.htm").exists():
        return
    htmls = sorted(root.rglob("*.html"))
    if htmls:
        shutil.copyfile(htmls[0], root / "index.html")
    else:
        (root / "index.html").write_text(
            "<!doctype html><meta charset=utf-8><title>Deploy</title>"
            "<body style='font-family:system-ui;padding:40px'>"
            "<h1>Деплой принят</h1><p>Добавь index.html в архив.</p></body>",
            encoding="utf-8",
        )
    (root / ".user.ini").write_text("auto_prepend_file =\n", encoding="utf-8")


def _write_member(root: Path, rel: str, data: bytes, *, kept: int, total_bytes: int) -> Tuple[int, int]:
    if not _safe_member(rel):
        return kept, total_bytes
    if len(data) > MAX_UNCOMPRESSED:
        raise ValueError("Файл в архиве слишком большой")
    total_bytes += len(data)
    if total_bytes > MAX_UNCOMPRESSED:
        raise ValueError(f"Распаковка > {MAX_UNCOMPRESSED // (1024*1024)} МБ")
    target = (root / rel).resolve()
    if not str(target).startswith(str(root.resolve())):
        raise PermissionError(f"Небезопасный путь: {rel}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(data)
    kept += 1
    if kept > MAX_FILES:
        raise ValueError(f"Лимит {MAX_FILES} файлов")
    return kept, total_bytes


def extract_public_zip(zip_path: Path, root: Path) -> Dict[str, Any]:
    """Extract allowed static files into root, replacing contents only after success."""
    if not zipfile.is_zipfile(zip_path):
        raise ValueError("Нужен ZIP-архив (html/css/js…)")

    def populate(staging: Path) -> Dict[str, Any]:
        kept = 0
        skipped = 0
        total_bytes = 0
        with zipfile.ZipFile(zip_path, "r") as zf:
            infos = zf.infolist()
            if len(infos) > MAX_FILES * 2:
                raise ValueError(f"Слишком много файлов в ZIP (>{MAX_FILES})")
            for info in infos:
                if info.is_dir():
                    continue
                if not _safe_member(info.filename):
                    skipped += 1
                    continue
                with zf.open(info) as src:
                    data = src.read()
                kept, total_bytes = _write_member(
                    staging, info.filename, data, kept=kept, total_bytes=total_bytes
                )

        _flatten_single_folder(staging)
        _ensure_index(staging)
        return {
            "files_kept": kept,
            "files_skipped": skipped,
            "bytes": total_bytes,
            "format": "zip",
        }

    return _atomic_replace_root(root, populate)


def extract_public_tar(tar_path: Path, root: Path, *, gzipped: bool = False) -> Dict[str, Any]:
    def populate(staging: Path) -> Dict[str, Any]:
        kept = 0
        skipped = 0
        total_bytes = 0
        mode = "r:gz" if gzipped else "r:"
        with tarfile.open(tar_path, mode) as tf:
            members = [m for m in tf.getmembers() if m.isfile()]
            if len(members) > MAX_FILES * 2:
                raise ValueError(f"Слишком много файлов в архиве (>{MAX_FILES})")
            for m in members:
                name = m.name
                if name.startswith("./"):
                    name = name[2:]
                if not _safe_member(name):
                    skipped += 1
                    continue
                if m.size > MAX_UNCOMPRESSED:
                    raise ValueError("Файл в архиве слишком большой")
                f = tf.extractfile(m)
                if f is None:
                    skipped += 1
                    continue
                data = f.read()
                kept, total_bytes = _write_member(
                    staging, name, data, kept=kept, total_bytes=total_bytes
                )

        _flatten_single_folder(staging)
        _ensure_index(staging)
        return {
            "files_kept": kept,
            "files_skipped": skipped,
            "bytes": total_bytes,
            "format": "tar.gz" if gzipped else "tar",
        }

    return _atomic_replace_root(root, populate)


def deploy_single_html(html_path: Path, root: Path) -> Dict[str, Any]:
    def populate(staging: Path) -> Dict[str, Any]:
        data = html_path.read_bytes()
        if len(data) > MAX_UNCOMPRESSED:
            raise ValueError("HTML слишком большой")
        # Basic sanity: look like markup
        sample = data[:2000].lower()
        if b"<" not in sample:
            raise ValueError("Файл не похож на HTML")
        (staging / "index.html").write_bytes(data)
        (staging / ".user.ini").write_text("auto_prepend_file =\n", encoding="utf-8")
        return {"files_kept": 1, "files_skipped": 0, "bytes": len(data), "format": "html"}

    return _atomic_replace_root(root, populate)


def detect_format(path: Path, filename: str = "") -> str:
    name = (filename or path.name or "").lower()
    head = b""
    try:
        with path.open("rb") as f:
            head = f.read(16)
    except OSError:
        pass

    if name.endswith(".tar.gz") or name.endswith(".tgz"):
        return "tar.gz"
    if name.endswith(".tar"):
        return "tar"
    if name.endswith((".html", ".htm")):
        return "html"
    if name.endswith(".zip") or zipfile.is_zipfile(path):
        return "zip"
    if head[:2] == b"\x1f\x8b":
        return "tar.gz"
    # ustar at offset 257
    try:
        with path.open("rb") as f:
            f.seek(257)
            magic = f.read(5)
            if magic in (b"ustar", b"ustar\x00"[:5]):
                return "tar"
    except OSError:
        pass
    sample = head.lower()
    if sample.startswith(b"<!doctype") or sample.startswith(b"<html") or b"<html" in sample:
        return "html"
    # gzip masquerading without extension
    if head[:2] == b"\x1f\x8b":
        return "tar.gz"
    raise ValueError(
        "Поддерживаются: ZIP, tar.gz / tgz, tar или один HTML-файл (статика HTML/CSS/JS)."
    )


def extract_upload(path: Path, root: Path, filename: str = "") -> Dict[str, Any]:
    fmt = detect_format(path, filename)
    if fmt == "zip":
        return extract_public_zip(path, root)
    if fmt == "tar.gz":
        return extract_public_tar(path, root, gzipped=True)
    if fmt == "tar":
        return extract_public_tar(path, root, gzipped=False)
    if fmt == "html":
        return deploy_single_html(path, root)
    raise ValueError("Неизвестный формат")


def create_deployment(
    zip_path: Path,
    ip: str = "",
    user_id: str = "",
    user_email: str = "",
    filename: str = "",
) -> Dict[str, Any]:
    if zip_path.stat().st_size > MAX_ZIP:
        raise ValueError(f"Файл больше {MAX_ZIP // (1024*1024)} МБ")

    name = new_site_name()
    token = secrets.token_urlsafe(24)
    root = SITES_ROOT / name
    stats = extract_upload(zip_path, root, filename=filename)

    now = time.time()
    meta = {
        "name": name,
        "token_hash": _hash_token(token),
        "created_at": now,
        "expires_at": now + TTL_DAYS * 86400,
        "ip": ip,
        "kind": "public_static",
        "format": stats.get("format") or "zip",
        "user_id": user_id or "",
        "user_email": user_email or "",
    }
    save_meta(name, meta)

    if user_email:
        try:
            import public_users as pu
            pu.attach_site(user_email, name)
        except Exception:
            pass

    return {
        "ok": True,
        "name": name,
        "token": token,
        "url": f"/sites/{name}/",
        "expires_days": TTL_DAYS,
        "stats": stats,
        "owner": user_email or None,
        "message": "Сайт опубликован. Сохрани token — им можно править файлы.",
    }


def redeploy(name: str, token: str, zip_path: Path, filename: str = "") -> Dict[str, Any]:
    if not verify_token(name, token):
        raise PermissionError("Неверный token или срок истёк")
    if zip_path.stat().st_size > MAX_ZIP:
        raise ValueError(f"Файл больше {MAX_ZIP // (1024*1024)} МБ")
    stats = extract_upload(zip_path, SITES_ROOT / name, filename=filename)
    meta = load_meta(name) or {}
    meta["updated_at"] = time.time()
    meta["format"] = stats.get("format") or meta.get("format")
    save_meta(name, meta)
    return {"ok": True, "name": name, "url": f"/sites/{name}/", "stats": stats}


def list_files(name: str, token: str) -> Dict[str, Any]:
    if not verify_token(name, token):
        raise PermissionError("Неверный token или срок истёк")
    root = SITES_ROOT / name
    files = []
    for p in sorted(root.rglob("*")):
        if not p.is_file():
            continue
        if p.name.startswith("."):
            continue
        rel = str(p.relative_to(root)).replace("\\", "/")
        files.append({"path": rel, "size": p.stat().st_size})
        if len(files) >= MAX_FILES:
            break
    return {"ok": True, "name": name, "url": f"/sites/{name}/", "files": files}


def read_file(name: str, token: str, rel: str) -> Dict[str, Any]:
    if not verify_token(name, token):
        raise PermissionError("Неверный token или срок истёк")
    rel = (rel or "").lstrip("/").replace("\\", "/")
    if not rel or ".." in rel.split("/"):
        raise ValueError("Некорректный путь")
    path = (SITES_ROOT / name / rel).resolve()
    root = (SITES_ROOT / name).resolve()
    if not str(path).startswith(str(root)) or not path.is_file():
        raise FileNotFoundError("Файл не найден")
    if path.suffix.lower() not in _ALLOWED_EXT and path.name.lower() not in {"makefile", "license", "readme"}:
        raise ValueError("Этот тип файла нельзя читать здесь")
    data = path.read_bytes()
    if len(data) > 500_000:
        raise ValueError("Файл слишком большой для редактора (500 КБ)")
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError:
        raise ValueError("Бинарный файл — скачай с превью, не через редактор")
    return {"ok": True, "path": rel, "content": text}


def write_file(name: str, token: str, rel: str, content: str) -> Dict[str, Any]:
    if not verify_token(name, token):
        raise PermissionError("Неверный token или срок истёк")
    rel = (rel or "").lstrip("/").replace("\\", "/")
    if not rel or ".." in rel.split("/"):
        raise ValueError("Некорректный путь")
    if not _safe_member(rel):
        raise ValueError("Этот тип файла нельзя записать")
    path = (SITES_ROOT / name / rel).resolve()
    root = (SITES_ROOT / name).resolve()
    if not str(path).startswith(str(root)):
        raise PermissionError("Путь вне сайта")
    raw = (content or "").encode("utf-8")
    if len(raw) > 500_000:
        raise ValueError("Файл > 500 КБ")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(raw)
    return {"ok": True, "path": rel, "bytes": len(raw)}
