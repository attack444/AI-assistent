#!/usr/bin/env python3
"""
Диагностика и настройка Ollama на диск D:.
Запуск: python setup_ollama_d.py
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

from ollama_paths import (
    DEFAULT_C_MODELS,
    DEFAULT_D_MODELS,
    apply_ollama_models_env,
    dir_size_mb,
    find_models_dirs,
    has_model_data,
    save_config,
)


def log(msg: str) -> None:
    print(msg, flush=True)


def run_ollama_list() -> str:
    try:
        env = os.environ.copy()
        apply_ollama_models_env()
        r = subprocess.run(
            ["ollama", "list"],
            capture_output=True,
            text=True,
            timeout=15,
            env=os.environ,
        )
        return (r.stdout or r.stderr or "").strip()
    except Exception as exc:
        return f"(ошибка: {exc})"


def set_user_env_var(name: str, value: str) -> bool:
    """Установить переменную среды пользователя Windows через setx."""
    try:
        subprocess.run(
            ["setx", name, value],
            capture_output=True,
            text=True,
            timeout=30,
            check=True,
        )
        os.environ[name] = value
        return True
    except Exception as exc:
        log(f"[ERR] setx не сработал: {exc}")
        return False


def main() -> int:
    log("=" * 55)
    log("  Настройка Ollama на диск D:")
    log("=" * 55)
    log("")

    current_env = os.environ.get("OLLAMA_MODELS", "(не задана)")
    log(f"OLLAMA_MODELS сейчас: {current_env}")
    log(f"Папка на C:         {DEFAULT_C_MODELS}  ({dir_size_mb(DEFAULT_C_MODELS)} MB)")
    log(f"Папка на D:         {DEFAULT_D_MODELS}  ({dir_size_mb(DEFAULT_D_MODELS)} MB)")
    log("")

    locations = find_models_dirs()
    if locations:
        log("Найдены папки с моделями:")
        for i, (path, size) in enumerate(locations, 1):
            log(f"  {i}. {path}  — {size} MB")
    else:
        log("Папок с моделями не найдено ни на C:, ни на D:.")
        log("Модели нужно скачать заново после настройки.")
    log("")

    if current_env not in ("(не задана)", "") and not has_model_data(Path(current_env)):
        log("[!] OLLAMA_MODELS указывает на пустую папку — поэтому ollama list пустой.")
        if locations:
            log(f"    Модели, скорее всего, здесь: {locations[0][0]}")
        log("")

    log("ollama list (до настройки):")
    log(run_ollama_list() or "(пусто)")
    log("")

    if not Path("D:/").exists():
        log("[ERR] Диск D: не найден. Подключи диск или укажи другой путь.")
        input("\nEnter для выхода...")
        return 1

    source: Path | None = locations[0][0] if locations else None
    if not source and has_model_data(DEFAULT_C_MODELS):
        source = DEFAULT_C_MODELS

    target = DEFAULT_D_MODELS
    target.parent.mkdir(parents=True, exist_ok=True)

    log("")
    log("Что будет сделано:")
    if source and source != target and not has_model_data(target, min_mb=10):
        log(f"  1. Скопировать модели {source} -> {target}")
    else:
        log(f"  1. Использовать папку {target}")
    log(f"  2. Установить OLLAMA_MODELS={target}")
    log("  3. Сохранить путь в ~/.ai-helper/ollama_paths.json")
    log("")
    answer = input("Продолжить? (y/n): ").strip().lower()
    if answer not in ("y", "yes", "д", "да"):
        log("Отменено.")
        return 0

    if not has_model_data(target, min_mb=10) and source and source != target:
        log(f"\nКопирую {source} -> {target} ...")
        log("(это может занять несколько минут)")
        try:
            if target.exists():
                for item in source.rglob("*"):
                    if item.is_file():
                        rel = item.relative_to(source)
                        dst = target / rel
                        if not dst.exists():
                            dst.parent.mkdir(parents=True, exist_ok=True)
                            shutil.copy2(item, dst)
            else:
                shutil.copytree(source, target, dirs_exist_ok=True)
            log(f"[OK] Скопировано. Размер на D: {dir_size_mb(target)} MB")
        except Exception as exc:
            log(f"[ERR] Ошибка копирования: {exc}")
            log("Скопируй вручную через Проводник и запусти скрипт снова.")
            input("\nEnter...")
            return 1
    elif has_model_data(target, min_mb=10):
        log(f"[OK] На D: уже есть модели ({dir_size_mb(target)} MB)")
    else:
        log("[!] Моделей нет — после перезапуска Ollama скачай:")
        log("    ollama pull qwen2.5-coder:7b")
        log("    ollama pull nomic-embed-text")

    log(f"\nУстанавливаю OLLAMA_MODELS={target} ...")
    if set_user_env_var("OLLAMA_MODELS", str(target)):
        log("[OK] Переменная установлена (постоянно)")
    else:
        log("[!] Установи вручную:")
        log("    Win+R -> sysdm.cpl -> Переменные среды")
        log(f"    OLLAMA_MODELS = {target}")

    save_config(target)
    log(f"[OK] Сохранено в ~/.ai-helper/ollama_paths.json")
    apply_ollama_models_env()

    log("")
    log("=" * 55)
    log("  ВАЖНО: полностью перезапусти Ollama!")
    log("  1. Правый клик на иконке Ollama в трее -> Quit")
    log("  2. Запусти Ollama снова из меню Пуск")
    log("  3. Открой новое окно PowerShell и выполни: ollama list")
    log("=" * 55)
    log("")
    input("Enter для выхода...")
    return 0


if __name__ == "__main__":
    sys.exit(main())
