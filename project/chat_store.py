"""chat_store.py — persistent panel chat storage (SQLite)."""
from __future__ import annotations

import json
import sqlite3
import time
import uuid
from pathlib import Path
from typing import Any, Dict, List, Optional

DB_PATH = Path.home() / ".ai-helper" / "chats.db"


def _conn() -> sqlite3.Connection:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(str(DB_PATH), timeout=30)
    con.row_factory = sqlite3.Row
    con.execute("PRAGMA journal_mode=WAL")
    con.execute(
        """
        CREATE TABLE IF NOT EXISTS chats (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL DEFAULT 'Новый чат',
            site_id TEXT NOT NULL DEFAULT '',
            created_at REAL NOT NULL,
            updated_at REAL NOT NULL
        )
        """
    )
    con.execute(
        """
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id TEXT NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL DEFAULT '',
            meta TEXT NOT NULL DEFAULT '{}',
            created_at REAL NOT NULL,
            FOREIGN KEY(chat_id) REFERENCES chats(id) ON DELETE CASCADE
        )
        """
    )
    con.execute(
        "CREATE INDEX IF NOT EXISTS idx_messages_chat ON messages(chat_id, id)"
    )
    con.execute(
        "CREATE INDEX IF NOT EXISTS idx_chats_site ON chats(site_id, updated_at DESC)"
    )
    return con


def _row_chat(r: sqlite3.Row) -> Dict[str, Any]:
    return {
        "id": r["id"],
        "title": r["title"],
        "site_id": r["site_id"] or "",
        "created_at": r["created_at"],
        "updated_at": r["updated_at"],
    }


def _row_msg(r: sqlite3.Row) -> Dict[str, Any]:
    meta: Dict[str, Any] = {}
    try:
        meta = json.loads(r["meta"] or "{}")
    except (json.JSONDecodeError, TypeError):
        meta = {}
    return {
        "id": r["id"],
        "chat_id": r["chat_id"],
        "role": r["role"],
        "content": r["content"] or "",
        "meta": meta,
        "created_at": r["created_at"],
    }


def list_chats(site_id: str = "", limit: int = 80) -> List[Dict[str, Any]]:
    with _conn() as con:
        if site_id:
            rows = con.execute(
                "SELECT * FROM chats WHERE site_id = ? ORDER BY updated_at DESC LIMIT ?",
                (site_id, limit),
            ).fetchall()
        else:
            rows = con.execute(
                "SELECT * FROM chats ORDER BY updated_at DESC LIMIT ?",
                (limit,),
            ).fetchall()
        return [_row_chat(r) for r in rows]


def get_chat(chat_id: str) -> Optional[Dict[str, Any]]:
    with _conn() as con:
        row = con.execute("SELECT * FROM chats WHERE id = ?", (chat_id,)).fetchone()
        if not row:
            return None
        msgs = con.execute(
            "SELECT * FROM messages WHERE chat_id = ? ORDER BY id ASC",
            (chat_id,),
        ).fetchall()
        chat = _row_chat(row)
        chat["messages"] = [_row_msg(m) for m in msgs]
        return chat


def create_chat(site_id: str = "", title: str = "Новый чат") -> Dict[str, Any]:
    now = time.time()
    chat_id = uuid.uuid4().hex[:16]
    title = (title or "Новый чат").strip()[:120] or "Новый чат"
    site_id = (site_id or "").strip()
    with _conn() as con:
        con.execute(
            "INSERT INTO chats (id, title, site_id, created_at, updated_at) VALUES (?,?,?,?,?)",
            (chat_id, title, site_id, now, now),
        )
    return {
        "id": chat_id,
        "title": title,
        "site_id": site_id,
        "created_at": now,
        "updated_at": now,
        "messages": [],
    }


def rename_chat(chat_id: str, title: str) -> Optional[Dict[str, Any]]:
    title = (title or "").strip()[:120]
    if not title:
        return get_chat(chat_id)
    with _conn() as con:
        con.execute(
            "UPDATE chats SET title = ?, updated_at = ? WHERE id = ?",
            (title, time.time(), chat_id),
        )
    return get_chat(chat_id)


def delete_chat(chat_id: str) -> bool:
    with _conn() as con:
        cur = con.execute("DELETE FROM messages WHERE chat_id = ?", (chat_id,))
        cur2 = con.execute("DELETE FROM chats WHERE id = ?", (chat_id,))
        return cur2.rowcount > 0 or cur.rowcount > 0


def add_message(
    chat_id: str,
    role: str,
    content: str,
    meta: Optional[Dict[str, Any]] = None,
) -> Dict[str, Any]:
    now = time.time()
    meta_json = json.dumps(meta or {}, ensure_ascii=False)
    with _conn() as con:
        cur = con.execute(
            "INSERT INTO messages (chat_id, role, content, meta, created_at) VALUES (?,?,?,?,?)",
            (chat_id, role, content or "", meta_json, now),
        )
        con.execute(
            "UPDATE chats SET updated_at = ? WHERE id = ?",
            (now, chat_id),
        )
        msg_id = cur.lastrowid
    return {
        "id": msg_id,
        "chat_id": chat_id,
        "role": role,
        "content": content or "",
        "meta": meta or {},
        "created_at": now,
    }


def auto_title_from_message(message: str) -> str:
    text = " ".join((message or "").strip().split())
    if not text:
        return "Новый чат"
    if len(text) <= 48:
        return text
    return text[:45].rstrip() + "…"


def history_for_agent(chat_id: str, limit: int = 24) -> List[Dict[str, str]]:
    """Return user/assistant turns for the LLM (no tool bubbles)."""
    chat = get_chat(chat_id)
    if not chat:
        return []
    out: List[Dict[str, str]] = []
    for m in chat.get("messages") or []:
        if m["role"] in ("user", "assistant") and (m.get("content") or "").strip():
            out.append({"role": m["role"], "content": m["content"]})
    return out[-limit:]
