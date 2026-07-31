"""
Chunked uploads for large hosting ZIP / WordPress backups.
Files land in SITES_ROOT/.uploads/<id>/ then assemble + extract into sites.
"""
from __future__ import annotations

import json
import os
import shutil
import time
import uuid
import zipfile
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

META_NAME = "meta.json"
CHUNK_DIR = "chunks"


def uploads_root(sites_root: Path) -> Path:
    root = sites_root / ".uploads"
    root.mkdir(parents=True, exist_ok=True)
    return root


def _meta_path(upload_dir: Path) -> Path:
    return upload_dir / META_NAME


def init_upload(
    sites_root: Path,
    *,
    filename: str,
    size: int,
    site_name: str = "",
    total_chunks: int = 0,
    chunk_size: int = 4 * 1024 * 1024,
) -> Dict[str, Any]:
    if size <= 0:
        raise ValueError("Размер файла должен быть > 0")
    max_bytes = int(os.environ.get("MAX_UPLOAD_BYTES", str(2 * 1024 * 1024 * 1024)))
    if size > max_bytes:
        raise ValueError(
            f"Файл слишком большой: {size // (1024 * 1024)} МБ "
            f"(лимит {max_bytes // (1024 * 1024)} МБ)"
        )
    uid = uuid.uuid4().hex
    upload_dir = uploads_root(sites_root) / uid
    (upload_dir / CHUNK_DIR).mkdir(parents=True, exist_ok=True)
    if total_chunks <= 0:
        total_chunks = max(1, (size + chunk_size - 1) // chunk_size)
    # Safe on-disk name (no Cyrillic) — keep original in meta
    original_name = Path(filename).name or "upload.bin"
    safe_name = "".join(ch if 32 <= ord(ch) < 127 else "_" for ch in original_name).strip("._") or "upload.bin"
    if not Path(safe_name).suffix and original_name.lower().endswith(".sql"):
        safe_name += ".sql"
    meta = {
        "id": uid,
        "filename": safe_name,
        "original_filename": original_name,
        "size": size,
        "site_name": site_name,
        "chunk_size": chunk_size,
        "total_chunks": total_chunks,
        "received": [],
        "created_at": int(time.time()),
        "assembled": False,
        "assembled_path": None,
    }
    _meta_path(upload_dir).write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")
    return {
        "ok": True,
        "upload_id": uid,
        "chunk_size": chunk_size,
        "total_chunks": total_chunks,
        "path": str(upload_dir),
        "filename": safe_name,
    }


def load_meta(sites_root: Path, upload_id: str) -> Tuple[Path, Dict[str, Any]]:
    upload_dir = uploads_root(sites_root) / upload_id
    meta_file = _meta_path(upload_dir)
    if not meta_file.is_file():
        raise FileNotFoundError(f"Загрузка не найдена: {upload_id}")
    meta = json.loads(meta_file.read_text(encoding="utf-8"))
    return upload_dir, meta


def save_meta(upload_dir: Path, meta: Dict[str, Any]) -> None:
    _meta_path(upload_dir).write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")


def save_chunk(
    sites_root: Path,
    upload_id: str,
    index: int,
    data: bytes,
) -> Dict[str, Any]:
    upload_dir, meta = load_meta(sites_root, upload_id)
    total = int(meta["total_chunks"])
    if index < 0 or index >= total:
        raise ValueError(f"Неверный индекс чанка: {index} (всего {total})")
    chunk_path = upload_dir / CHUNK_DIR / f"{index:06d}.part"
    chunk_path.write_bytes(data)
    received = set(meta.get("received") or [])
    received.add(index)
    meta["received"] = sorted(received)
    save_meta(upload_dir, meta)
    return {
        "ok": True,
        "upload_id": upload_id,
        "index": index,
        "received": len(meta["received"]),
        "total_chunks": total,
        "complete": len(meta["received"]) >= total,
    }


def status(sites_root: Path, upload_id: str) -> Dict[str, Any]:
    upload_dir, meta = load_meta(sites_root, upload_id)
    received = meta.get("received") or []
    total = int(meta["total_chunks"])
    return {
        "ok": True,
        "upload_id": upload_id,
        "filename": meta.get("filename"),
        "size": meta.get("size"),
        "received": len(received),
        "total_chunks": total,
        "missing": [i for i in range(total) if i not in set(received)],
        "complete": len(received) >= total,
        "assembled": bool(meta.get("assembled")),
        "assembled_path": meta.get("assembled_path"),
        "dir": str(upload_dir),
    }


def assemble(sites_root: Path, upload_id: str) -> Path:
    upload_dir, meta = load_meta(sites_root, upload_id)
    if meta.get("assembled") and meta.get("assembled_path"):
        existing = Path(meta["assembled_path"])
        if existing.is_file():
            return existing
    total = int(meta["total_chunks"])
    received = set(meta.get("received") or [])
    missing = [i for i in range(total) if i not in received]
    if missing:
        raise ValueError(f"Не все чанки получены. Нет: {missing[:20]}{'...' if len(missing) > 20 else ''}")
    out = upload_dir / meta["filename"]
    with out.open("wb") as dest:
        for i in range(total):
            part = upload_dir / CHUNK_DIR / f"{i:06d}.part"
            dest.write(part.read_bytes())
    actual = out.stat().st_size
    expected = int(meta["size"])
    # Allow small mismatch if client used ceil chunks
    if actual < expected * 0.5:
        raise ValueError(f"Собранный файл слишком маленький: {actual} байт (ждали ~{expected})")
    meta["assembled"] = True
    meta["assembled_path"] = str(out)
    save_meta(upload_dir, meta)
    # cleanup chunk parts to free disk
    shutil.rmtree(upload_dir / CHUNK_DIR, ignore_errors=True)
    (upload_dir / CHUNK_DIR).mkdir(exist_ok=True)
    return out


def list_pending(sites_root: Path) -> List[Dict[str, Any]]:
    root = uploads_root(sites_root)
    items = []
    for child in sorted(root.iterdir()):
        if not child.is_dir():
            continue
        meta_file = _meta_path(child)
        if not meta_file.is_file():
            continue
        try:
            meta = json.loads(meta_file.read_text(encoding="utf-8"))
        except Exception:
            continue
        received = meta.get("received") or []
        items.append({
            "id": meta.get("id", child.name),
            "filename": meta.get("filename"),
            "size": meta.get("size"),
            "site_name": meta.get("site_name"),
            "received": len(received),
            "total_chunks": meta.get("total_chunks"),
            "assembled": meta.get("assembled"),
            "assembled_path": meta.get("assembled_path"),
            "created_at": meta.get("created_at"),
        })
    return items


def detect_wordpress(root: Path) -> bool:
    if not root.is_dir():
        return False
    markers = (
        "wp-config.php",
        "wp-load.php",
        "wp-settings.php",
        "wp-blog-header.php",
    )
    for name in markers:
        if (root / name).is_file():
            return True
    if (root / "wp-content").is_dir() or (root / "wp-admin").is_dir() or (root / "wp-includes").is_dir():
        return True
    # Nested: public_html / wordpress / www
    for sub in ("public_html", "www", "htdocs", "httpdocs", "public", "wordpress", "WP"):
        nested = root / sub
        if nested.is_dir() and detect_wordpress(nested):
            return True
    # One-level scan for common leftovers
    try:
        for child in root.iterdir():
            if not child.is_dir() or child.name.startswith("."):
                continue
            if (child / "wp-config.php").is_file() or (child / "wp-content").is_dir():
                return True
    except OSError:
        pass
    return False


def top_entries(root: Path, limit: int = 40) -> List[Dict[str, Any]]:
    if not root.is_dir():
        return []
    entries = []
    for child in sorted(root.iterdir(), key=lambda p: (not p.is_dir(), p.name.lower())):
        if child.name.startswith(".") and child.name not in {".well-known"}:
            continue
        try:
            st = child.stat()
            entries.append({
                "name": child.name,
                "type": "dir" if child.is_dir() else "file",
                "size": st.st_size if child.is_file() else None,
            })
        except OSError:
            continue
        if len(entries) >= limit:
            break
    return entries


def find_wp_or_public(root: Path) -> Optional[Path]:
    """If ZIP left content in a nested folder, find the real web root."""
    candidates = [
        root,
        root / "public_html",
        root / "www",
        root / "htdocs",
        root / "httpdocs",
        root / "public",
        root / "wordpress",
    ]
    for c in candidates:
        if c.is_dir() and (
            (c / "index.php").exists()
            or (c / "index.html").exists()
            or (c / "wp-config.php").exists()
            or (c / "wp-content").is_dir()
        ):
            return c
    # Domain-style folder from shared hosting (e.g. 5mb2.ru/)
    try:
        for child in sorted(root.iterdir()):
            if not child.is_dir() or child.name.startswith("."):
                continue
            if (
                (child / "wp-config.php").is_file()
                or (child / "wp-content").is_dir()
                or (child / "index.php").is_file()
            ):
                return child
    except OSError:
        pass
    # deepest single-folder chain
    cur = root
    for _ in range(4):
        kids = [p for p in cur.iterdir() if not p.name.startswith(".")]
        if len(kids) == 1 and kids[0].is_dir():
            cur = kids[0]
            if (cur / "wp-config.php").exists() or (cur / "index.php").exists():
                return cur
        else:
            break
    return None
