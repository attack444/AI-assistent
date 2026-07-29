"""Пути к моделям Ollama: D:, C: и конфиг ~/.ai-helper/ollama_paths.json."""
from __future__ import annotations

import json
import os
from pathlib import Path

DEFAULT_D_ROOT = Path("D:/Ollama")
DEFAULT_D_MODELS = DEFAULT_D_ROOT / ".ollama" / "models"
DEFAULT_C_MODELS = Path.home() / ".ollama" / "models"
CONFIG_FILE = Path.home() / ".ai-helper" / "ollama_paths.json"

CANDIDATE_SUFFIXES = [
    DEFAULT_D_MODELS,
    DEFAULT_C_MODELS,
    Path("D:/.ollama/models"),
    Path("D:/Ollama/models"),
    Path("D:/ollama/models"),
]


def dir_size_mb(path: Path) -> float:
    if not path.exists():
        return 0.0
    total = sum(f.stat().st_size for f in path.rglob("*") if f.is_file())
    return round(total / 1024 / 1024, 1)


def has_model_data(path: Path, min_mb: float = 1.0) -> bool:
    return path.exists() and dir_size_mb(path) >= min_mb


def load_config() -> dict:
    if not CONFIG_FILE.exists():
        return {}
    try:
        return json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
    except Exception:
        return {}


def save_config(models_path: Path) -> None:
    CONFIG_FILE.parent.mkdir(parents=True, exist_ok=True)
    CONFIG_FILE.write_text(
        json.dumps({"OLLAMA_MODELS": str(models_path)}, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )


def find_models_dirs() -> list[tuple[Path, float]]:
    candidates: list[Path] = []
    env = os.environ.get("OLLAMA_MODELS", "").strip()
    if env:
        candidates.append(Path(env))
    cfg = load_config().get("OLLAMA_MODELS", "")
    if cfg:
        candidates.append(Path(str(cfg)))
    candidates.extend(CANDIDATE_SUFFIXES)

    found: list[tuple[Path, float]] = []
    seen: set[str] = set()
    for raw in candidates:
        try:
            path = raw.resolve()
        except Exception:
            path = raw
        key = str(path).lower()
        if key in seen:
            continue
        seen.add(key)
        size = dir_size_mb(path)
        if size > 1:
            found.append((path, size))
    return sorted(found, key=lambda item: item[1], reverse=True)


def resolve_ollama_models_path() -> Path:
    """
    Выбрать папку моделей Ollama.

    Приоритет:
    1. Папка из OLLAMA_MODELS / ollama_paths.json, если в ней уже есть модели
    2. Самая большая папка с моделями на диске
    3. D:\\Ollama\\.ollama\\models (если диск D: есть)
    4. ~/.ollama/models
    """
    env_raw = os.environ.get("OLLAMA_MODELS", "").strip()
    config_raw = str(load_config().get("OLLAMA_MODELS", "")).strip()

    for raw in (env_raw, config_raw):
        if not raw:
            continue
        path = Path(raw)
        if has_model_data(path):
            return path

    locations = find_models_dirs()
    if locations:
        return locations[0][0]

    if config_raw:
        return Path(config_raw)
    if env_raw:
        return Path(env_raw)
    if Path("D:/").exists():
        return DEFAULT_D_MODELS
    return DEFAULT_C_MODELS


def apply_ollama_models_env() -> Path:
    path = resolve_ollama_models_path()
    path.mkdir(parents=True, exist_ok=True)
    os.environ["OLLAMA_MODELS"] = str(path)
    return path


def diagnose_models() -> list[str]:
    """Сообщения для лога, если ollama list пустой, а файлы моделей есть."""
    lines: list[str] = []
    current = Path(os.environ.get("OLLAMA_MODELS", resolve_ollama_models_path()))
    locations = find_models_dirs()

    if has_model_data(current):
        return lines

    if not locations:
        lines.append("[!] Папка моделей пуста. Будут скачаны при первом запуске.")
        return lines

    best_path, best_size = locations[0]
    if str(best_path).lower() == str(current).lower():
        return lines

    lines.append("[!] ollama list пустой: OLLAMA_MODELS указывает на пустую папку")
    lines.append(f"    Сейчас: {current}")
    lines.append(f"    Найдены модели: {best_path} ({best_size} MB)")
    lines.append("    Запусти «Настроить Ollama на диск D.bat» или скопируй модели вручную.")
    lines.append("    После смены пути полностью перезапусти Ollama (Quit в трее).")
    return lines
