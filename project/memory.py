# memory.py — long-term memory store
from __future__ import annotations

import json
import re
import uuid
from dataclasses import asdict, dataclass, field
from datetime import datetime
from pathlib import Path
from typing import List, Optional

MEMORY_FILE = Path.home() / ".ai-helper" / "memory.json"
MAX_ENTRIES = 1000


@dataclass
class MemoryEntry:
    id: str
    content: str
    type: str        # fact | preference | rule | positive | negative | project
    project: str = ""
    timestamp: str = ""
    tags: List[str] = field(default_factory=list)


class MemoryStore:
    def __init__(self) -> None:
        self.entries: List[MemoryEntry] = []
        self._load()

    def _load(self) -> None:
        if not MEMORY_FILE.exists():
            return
        try:
            data = json.loads(MEMORY_FILE.read_text(encoding="utf-8"))
            self.entries = [MemoryEntry(**e) for e in data if isinstance(e, dict)]
        except Exception:
            self.entries = []

    def _save(self) -> None:
        MEMORY_FILE.parent.mkdir(parents=True, exist_ok=True)
        MEMORY_FILE.write_text(
            json.dumps([asdict(e) for e in self.entries], indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

    def add(
        self,
        content: str,
        type: str = "fact",
        project: str = "",
        tags: Optional[List[str]] = None,
    ) -> MemoryEntry:
        content = content.strip()
        # Remove near-duplicates
        self.entries = [e for e in self.entries if e.content.lower() != content.lower()]
        entry = MemoryEntry(
            id=uuid.uuid4().hex[:8],
            content=content,
            type=type,
            project=project,
            timestamp=datetime.now().isoformat(),
            tags=tags or [],
        )
        self.entries.append(entry)
        if len(self.entries) > MAX_ENTRIES:
            self.entries = self.entries[-MAX_ENTRIES:]
        self._save()
        return entry

    def search(self, query: str, top_k: int = 8, project: str = "") -> List[MemoryEntry]:
        q_words = set(re.findall(r"\w+", query.lower()))
        if not q_words:
            return []
        scored = []
        for e in self.entries:
            if project and e.project and e.project != project:
                continue
            score = len(q_words & set(re.findall(r"\w+", e.content.lower())))
            if score > 0:
                scored.append((score, e))
        scored.sort(key=lambda x: x[0], reverse=True)
        return [e for _, e in scored[:top_k]]

    def get_rules(self) -> List[MemoryEntry]:
        return [e for e in self.entries if e.type in ("rule", "preference")]

    def forget(self, entry_id: str) -> bool:
        before = len(self.entries)
        self.entries = [e for e in self.entries if e.id != entry_id]
        if len(self.entries) < before:
            self._save()
            return True
        return False

    def forget_matching(self, keyword: str) -> int:
        before = len(self.entries)
        kw = keyword.lower()
        self.entries = [e for e in self.entries if kw not in e.content.lower()]
        removed = before - len(self.entries)
        if removed:
            self._save()
        return removed

    def get_context(self, query: str, project: str = "") -> str:
        """Build memory context string to inject into agent system prompt."""
        relevant = self.search(query, top_k=6, project=project)
        rules = self.get_rules()[:4]
        seen: set = set()
        combined = []
        for e in list(rules) + list(relevant):
            if e.id not in seen:
                seen.add(e.id)
                combined.append(e)
        if not combined:
            return ""
        lines = ["[Из памяти]"]
        for e in combined[:10]:
            lines.append(f"- [{e.type}] {e.content}")
        return "\n".join(lines)

    def all_entries(self) -> List[MemoryEntry]:
        return list(reversed(self.entries))
