"""Публичная обратная связь AI Helper (идеи / ошибки)."""
from __future__ import annotations

import json
import time
from pathlib import Path
from typing import Any, Dict

DATA_DIR = Path.home() / ".ai-helper"
FEEDBACK_FILE = DATA_DIR / "public_feedback.jsonl"
MAX_LEN = 4000
MIN_LEN = 8
TYPES = {
    "idea": "Идея / улучшение",
    "bug": "Ошибка на сайте",
    "need": "Мне нужно…",
    "other": "Другое",
}


def save_feedback(
    *,
    kind: str,
    message: str,
    email: str = "",
    page: str = "",
    source: str = "ai-helper",
    ip: str = "",
) -> Dict[str, Any]:
    kind = (kind or "other").strip().lower()
    if kind not in TYPES:
        kind = "other"
    message = (message or "").strip()
    if len(message) < MIN_LEN:
        raise ValueError("Опишите чуть подробнее (от 8 символов)")
    if len(message) > MAX_LEN:
        raise ValueError("Слишком длинное сообщение")

    item = {
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "type": kind,
        "type_label": TYPES[kind],
        "message": message,
        "email": (email or "").strip()[:200],
        "page": (page or "").strip()[:500],
        "source": source or "ai-helper",
        "ip": (ip or "").strip()[:64],
    }
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    with FEEDBACK_FILE.open("a", encoding="utf-8") as f:
        f.write(json.dumps(item, ensure_ascii=False) + "\n")
    return {"ok": True, "message": "Спасибо! Сообщение получили — учтём при улучшениях."}


def list_feedback(limit: int = 100) -> list[Dict[str, Any]]:
    limit = max(1, min(int(limit or 100), 500))
    if not FEEDBACK_FILE.exists():
        return []
    items: list[Dict[str, Any]] = []
    try:
        lines = FEEDBACK_FILE.read_text(encoding="utf-8").splitlines()
    except OSError:
        return []
    for line in reversed(lines):
        line = line.strip()
        if not line:
            continue
        try:
            items.append(json.loads(line))
        except json.JSONDecodeError:
            continue
        if len(items) >= limit:
            break
    return items
