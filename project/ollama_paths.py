"""Управление путём к моделям Ollama (D: > конфиг > C:)."""
from __future__ import annotations

import json
import os
from pathlib import Path

D_MODELS   = Path("D:/Ollama/.ollama/models")
C_MODELS   = Path.home() / ".ollama" / "models"
CONFIG_FILE = Path.home() / ".ai-helper" / "ollama_paths.json"


def dir_size_mb(path: Path) -> float:
    if not path.exists():
        return 0.0
    total = sum(f.stat().st_size for f in path.rglob("*") if f.is_file())
    return round(total / 1024 / 1024, 1)


def has_data(path: Path, min_mb: float = 1.0) -> bool:
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
    """Возвращает все существующие папки с моделями, сортировка по размеру."""
    candidates: list[Path] = []

    cfg_raw = str(load_config().get("OLLAMA_MODELS", "")).strip()
    if cfg_raw:
        candidates.append(Path(cfg_raw))

    candidates += [
        D_MODELS,
        Path("D:/.ollama/models"),
        Path("D:/Ollama/models"),
        Path("D:/ollama/models"),
        C_MODELS,
    ]

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
    return sorted(found, key=lambda x: x[1], reverse=True)


def resolve_ollama_models_path() -> Path:
    """
    Выбрать целевую папку моделей.

    Приоритет:
    1. Переменная среды OLLAMA_MODELS (выставляется START.bat)
    2. Конфиг ~/.ai-helper/ollama_paths.json
    3. D:\\Ollama\\.ollama\\models  (если диск D: доступен)
    4. ~/.ollama/models
    """
    env_raw = os.environ.get("OLLAMA_MODELS", "").strip()
    if env_raw:
        return Path(env_raw)

    cfg_raw = str(load_config().get("OLLAMA_MODELS", "")).strip()
    if cfg_raw:
        return Path(cfg_raw)

    if Path("D:/").exists():
        return D_MODELS

    return C_MODELS


def apply_ollama_models_env() -> Path:
    """Убедиться что OLLAMA_MODELS выставлена и папка существует."""
    path = resolve_ollama_models_path()
    path.mkdir(parents=True, exist_ok=True)
    os.environ["OLLAMA_MODELS"] = str(path)
    return path


def diagnose_models() -> list[str]:
    """Предупреждения, если OLLAMA_MODELS указывает на пустую папку."""
    lines: list[str] = []
    current = Path(os.environ.get("OLLAMA_MODELS", resolve_ollama_models_path()))

    if has_data(current):
        return lines

    locations = find_models_dirs()
    if not locations:
        lines.append("[!] Моделей Ollama нет. Будут скачаны при первом запуске.")
        return lines

    best_path, best_mb = locations[0]
    if str(best_path).lower() == str(current).lower():
        return lines

    lines.append("[!] ollama list пустой: OLLAMA_MODELS указывает на пустую папку.")
    lines.append(f"    Текущий путь : {current}")
    lines.append(f"    Файлы есть   : {best_path}  ({best_mb} MB)")
    lines.append("    Запусти setup_d.bat — скрипт скопирует модели на D: и удалит с C:.")
    lines.append("    После этого перезапусти Ollama (Quit в трее -> запустить снова).")
    return lines
