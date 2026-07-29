#!/usr/bin/env python3
"""
Перенос моделей Ollama на диск D: и удаление с C:.

Запуск: python setup_ollama_d.py
  или:  setup_d.bat
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

D_MODELS = Path("D:/Ollama/.ollama/models")
C_MODELS = Path.home() / ".ollama" / "models"
CONFIG_FILE = Path.home() / ".ai-helper" / "ollama_paths.json"


# ---------------------------------------------------------------------------
def log(msg: str) -> None:
    print(msg, flush=True)


def dir_size_mb(path: Path) -> float:
    if not path.exists():
        return 0.0
    total = sum(f.stat().st_size for f in path.rglob("*") if f.is_file())
    return round(total / 1024 / 1024, 1)


def has_data(path: Path, min_mb: float = 1.0) -> bool:
    return path.exists() and dir_size_mb(path) >= min_mb


def run_cmd(args: list[str]) -> str:
    try:
        r = subprocess.run(args, capture_output=True, text=True, timeout=20,
                           env={**os.environ, "OLLAMA_MODELS": str(D_MODELS)})
        return (r.stdout or r.stderr or "").strip()
    except Exception as exc:
        return f"(ошибка: {exc})"


def set_env_permanent(name: str, value: str) -> bool:
    try:
        subprocess.run(["setx", name, value], capture_output=True, text=True,
                       timeout=30, check=True)
        os.environ[name] = value
        return True
    except Exception as exc:
        log(f"  setx не сработал: {exc}")
        return False


def save_config() -> None:
    import json
    CONFIG_FILE.parent.mkdir(parents=True, exist_ok=True)
    CONFIG_FILE.write_text(
        json.dumps({"OLLAMA_MODELS": str(D_MODELS)}, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )


def copy_models(src: Path, dst: Path) -> bool:
    log(f"  Копирую {src}  ->  {dst}")
    log("  (может занять несколько минут...)")
    try:
        if dst.exists():
            for item in src.rglob("*"):
                if item.is_file():
                    rel = item.relative_to(src)
                    d = dst / rel
                    if not d.exists():
                        d.parent.mkdir(parents=True, exist_ok=True)
                        shutil.copy2(item, d)
        else:
            shutil.copytree(src, dst, dirs_exist_ok=True)
        copied_mb = dir_size_mb(dst)
        log(f"  [OK] Скопировано: {copied_mb} MB на D:")
        return True
    except Exception as exc:
        log(f"  [ERR] Ошибка копирования: {exc}")
        return False


def delete_c_models() -> None:
    log(f"\n  Удаляю модели с C: ({C_MODELS}) ...")
    try:
        shutil.rmtree(C_MODELS)
        C_MODELS.mkdir(parents=True, exist_ok=True)
        log("  [OK] Папка с C: очищена.")
    except Exception as exc:
        log(f"  [ERR] Не удалось удалить: {exc}")
        log(f"  Удали вручную: {C_MODELS}")


# ---------------------------------------------------------------------------
def main() -> int:
    log("")
    log("=" * 56)
    log("  Ollama: перенос моделей на D:  (удаление с C:)")
    log("=" * 56)
    log("")

    if not Path("D:/").exists():
        log("[ERR] Диск D: не найден.")
        log("      Подключи диск и запусти снова.")
        input("\nEnter...")
        return 1

    c_mb = dir_size_mb(C_MODELS)
    d_mb = dir_size_mb(D_MODELS)
    env_raw = os.environ.get("OLLAMA_MODELS", "(не задана)")

    log(f"OLLAMA_MODELS сейчас : {env_raw}")
    log(f"Модели на C:         : {C_MODELS}  [{c_mb} MB]")
    log(f"Модели на D:         : {D_MODELS}  [{d_mb} MB]")
    log("")

    # Ситуация: OLLAMA_MODELS уже указывает на пустую D: — объясняем
    if env_raw not in ("(не задана)", "") and not has_data(Path(env_raw)):
        log("[!] Переменная OLLAMA_MODELS указывает на пустую папку — поэтому")
        log("    ollama list пустой. Сейчас исправим.")
        log("")

    # Что делать
    need_copy = has_data(C_MODELS) and not has_data(D_MODELS)
    already_on_d = has_data(D_MODELS)

    if already_on_d:
        log(f"[OK] На D: уже есть модели ({d_mb} MB).")
        if has_data(C_MODELS):
            log(f"     Модели на C: ещё не удалены ({c_mb} MB).")
    elif need_copy:
        log(f"Модели найдены на C: ({c_mb} MB). Будут скопированы на D:.")
    else:
        log("[!] Моделей нет ни на C:, ни на D:.")
        log("    После настройки скачай:")
        log("      ollama pull qwen2.5-coder:7b")
        log("      ollama pull nomic-embed-text")

    log("")
    log("Будет сделано:")
    if need_copy:
        log(f"  1. Скопировать модели C: -> D:")
    else:
        log(f"  1. Модели уже на D: (копирование не нужно)")
    log(f"  2. Установить OLLAMA_MODELS={D_MODELS}  (постоянно, через setx)")
    log(f"  3. Сохранить путь в ~/.ai-helper/ollama_paths.json")
    if has_data(C_MODELS):
        log(f"  4. Удалить папку с C:  ({C_MODELS})")
    log("")
    answer = input("Продолжить? (y / n): ").strip().lower()
    if answer not in ("y", "yes", "д", "да"):
        log("Отменено.")
        return 0

    # Шаг 1: Копирование
    if need_copy:
        log("")
        log("Шаг 1: Копирование моделей...")
        D_MODELS.parent.mkdir(parents=True, exist_ok=True)
        if not copy_models(C_MODELS, D_MODELS):
            log("\n[ERR] Копирование не удалось. Настрой путь вручную и попробуй ещё раз.")
            input("\nEnter...")
            return 1
    else:
        log("Шаг 1: пропуск — модели уже на D:")

    # Шаг 2: Переменная среды
    log("\nШаг 2: Прописываю OLLAMA_MODELS ...")
    if set_env_permanent("OLLAMA_MODELS", str(D_MODELS)):
        log(f"  [OK] OLLAMA_MODELS={D_MODELS}  (постоянно)")
    else:
        log("  [!] Пропиши вручную:")
        log("      Win+R -> sysdm.cpl -> Переменные среды")
        log(f"      OLLAMA_MODELS = {D_MODELS}")

    # Шаг 3: Конфиг
    log("\nШаг 3: Сохраняю конфиг ...")
    save_config()
    log(f"  [OK] {CONFIG_FILE}")

    # Шаг 4: Удаление с C:
    if has_data(C_MODELS):
        log("\nШаг 4: Удаление моделей с C: ...")
        delete_c_models()
    else:
        log("\nШаг 4: C: уже пустой, удалять нечего.")

    # Итог
    os.environ["OLLAMA_MODELS"] = str(D_MODELS)
    log("")
    log("=" * 56)
    log("  Готово!")
    log("")
    log("  ВАЖНО: перезапусти Ollama Desktop полностью:")
    log("  1. ПКМ на иконке Ollama в трее  ->  Quit")
    log("  2. Запусти Ollama из меню Пуск (или Apps)")
    log("  3. Открой новый PowerShell и проверь:  ollama list")
    log("")
    log("  Теперь ollama pull  сохраняет модели на D:")
    log("=" * 56)
    log("")
    input("Enter для выхода...")
    return 0


if __name__ == "__main__":
    sys.exit(main())
