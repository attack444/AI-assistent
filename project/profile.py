# profile.py — user profile & personalization
from __future__ import annotations

import json
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import List

PROFILE_FILE = Path.home() / ".ai-helper" / "profile.json"


@dataclass
class UserProfile:
    name: str = "Пользователь"
    style: str = "кратко, без воды, код > объяснения"
    preferred_languages: List[str] = field(default_factory=list)
    preferred_model: str = "llama3.1:8b"
    auto_apply_edits: bool = True
    auto_index: bool = True
    verbosity: str = "minimal"        # minimal | normal | verbose
    rules: List[str] = field(default_factory=list)
    confirm_before_apply: bool = False
    private_mode: bool = True          # full FS + shell access, no sandboxing


def load_profile() -> UserProfile:
    if not PROFILE_FILE.exists():
        p = UserProfile()
        save_profile(p)
        return p
    try:
        data = json.loads(PROFILE_FILE.read_text(encoding="utf-8"))
        defaults = asdict(UserProfile())
        merged = {k: data.get(k, v) for k, v in defaults.items()}
        return UserProfile(**merged)
    except Exception:
        return UserProfile()


def save_profile(p: UserProfile) -> None:
    PROFILE_FILE.parent.mkdir(parents=True, exist_ok=True)
    PROFILE_FILE.write_text(
        json.dumps(asdict(p), indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
