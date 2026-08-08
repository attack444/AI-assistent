#!/usr/bin/env python3
"""
cleanup.py — очистка AI Helper и Ollama.

Запуск: python cleanup.py
  или:  cleanup.bat

Варианты очистки:
  1. Только данные AI Helper (~/.ai-helper)
  2. Только модели Ollama на D:
  3. Только модели Ollama на C:
  4. Виртуальное окружение (.venv)
  5. Ярлык с рабочего стола
  6. Всё сразу (полный сброс)
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

PROJECT_DIR  = Path(__file__).resolve().parent
VENV_DIR     = PROJECT_DIR / ".venv"
DATA_DIR     = Path.home() / ".ai-helper"
C_MODELS     = Path.home() / ".ollama" / "models"
D_MODELS     = Path("D:/Ollama/.ollama/models")
C_OLLAMA_DIR = Path.home() / ".ollama"

GREEN  = "\033[92m"
RED    = "\033[91m"
YELLOW = "\033[93m"
CYAN   = "\033[96m"
BOLD   = "\033[1m"
RESET  = "\033[0m"


def log(msg: str, color: str = "") -> None:
    print(f"{color}{msg}{RESET}", flush=True)


def dir_size_gb(path: Path) -> str:
    if not path.exists():
        return "0 MB (нет)"
    total = sum(f.stat().st_size for f in path.rglob("*") if f.is_file())
    if total > 1_000_000_000:
        return f"{round(total / 1e9, 1)} GB"
    return f"{round(total / 1e6, 1)} MB"


def confirm(question: str) -> bool:
    ans = input(f"{YELLOW}  {question} (y/n): {RESET}").strip().lower()
    return ans in ("y", "yes", "д", "да")


def remove_dir(path: Path, label: str) -> bool:
    if not path.exists():
        log(f"  — {label}: не найдена, пропускаю", YELLOW)
        return True
    size = dir_size_gb(path)
    log(f"  Удаляю {label}: {path}  [{size}]", CYAN)
    try:
        shutil.rmtree(path)
        log(f"  ✓ Удалено", GREEN)
        return True
    except Exception as exc:
        log(f"  ✗ Ошибка: {exc}", RED)
        return False


def remove_shortcut() -> bool:
    if sys.platform != "win32":
        return True
    desktop = Path.home() / "Desktop"
    lnk = desktop / "AI Helper.lnk"
    ru_desktop = Path(os.environ.get("USERPROFILE", "")) / "Рабочий стол"
    lnk_ru = ru_desktop / "AI Helper.lnk"
    removed = False
    for lnk_path in (lnk, lnk_ru):
        if lnk_path.exists():
            try:
                lnk_path.unlink()
                log(f"  ✓ Ярлык удалён: {lnk_path}", GREEN)
                removed = True
            except Exception as exc:
                log(f"  ✗ {exc}", RED)
    flag = PROJECT_DIR / ".ai_helper_shortcut_created"
    if flag.exists():
        flag.unlink(missing_ok=True)
    if not removed:
        log("  — Ярлык не найден", YELLOW)
    return True


def remove_env_var(name: str) -> None:
    if sys.platform != "win32":
        return
    try:
        subprocess.run(
            ["reg", "delete", "HKCU\\Environment", "/v", name, "/f"],
            capture_output=True, timeout=10,
        )
        if name in os.environ:
            del os.environ[name]
        log(f"  ✓ Переменная среды {name} удалена", GREEN)
    except Exception:
        pass


def remove_ollama_paths_config() -> None:
    cfg = DATA_DIR / "ollama_paths.json"
    if cfg.exists():
        cfg.unlink(missing_ok=True)
        log("  ✓ Конфиг ollama_paths.json удалён", GREEN)


# ---------------------------------------------------------------------------
# Menu actions
# ---------------------------------------------------------------------------

def action_ai_data() -> None:
    log(f"\n  Данные AI Helper: {DATA_DIR}  [{dir_size_gb(DATA_DIR)}]")
    log("  Будут удалены: проекты, индексы, история, память, бэкапы, настройки")
    if confirm("Удалить данные AI Helper?"):
        remove_dir(DATA_DIR, "Данные AI Helper")
        log("  После следующего запуска всё настроится заново.", YELLOW)


def action_models_d() -> None:
    size = dir_size_gb(D_MODELS)
    log(f"\n  Модели Ollama на D:  {D_MODELS}  [{size}]")
    if not D_MODELS.exists():
        log("  Папки нет — нечего удалять.", YELLOW)
        return
    if confirm("Удалить модели Ollama с D:?"):
        remove_dir(D_MODELS, "Ollama модели (D:)")
        remove_ollama_paths_config()
        remove_env_var("OLLAMA_MODELS")
        log("  После удаления нужно скачать модели заново: ollama pull qwen2.5-coder:14b", YELLOW)


def action_models_c() -> None:
    size = dir_size_gb(C_MODELS)
    log(f"\n  Модели Ollama на C:  {C_MODELS}  [{size}]")
    if not C_MODELS.exists():
        log("  Папки нет — нечего удалять.", YELLOW)
        return
    if confirm("Удалить модели Ollama с C:?"):
        remove_dir(C_MODELS, "Ollama модели (C:)")


def action_venv() -> None:
    size = dir_size_gb(VENV_DIR)
    log(f"\n  Виртуальное окружение: {VENV_DIR}  [{size}]")
    if confirm("Удалить .venv? (будет пересоздан при следующем START.bat)"):
        remove_dir(VENV_DIR, "Виртуальное окружение (.venv)")


def action_shortcut() -> None:
    log("\n  Ярлык 'AI Helper' на рабочем столе")
    if confirm("Удалить ярлык с рабочего стола?"):
        remove_shortcut()


def action_all() -> None:
    log(f"""
{RED}{BOLD}  ПОЛНАЯ ОЧИСТКА:{RESET}
  • Данные AI Helper:         {DATA_DIR}  [{dir_size_gb(DATA_DIR)}]
  • Модели Ollama на D:       {D_MODELS}  [{dir_size_gb(D_MODELS)}]
  • Модели Ollama на C:       {C_MODELS}  [{dir_size_gb(C_MODELS)}]
  • Виртуальное окружение:    {VENV_DIR}  [{dir_size_gb(VENV_DIR)}]
  • Ярлык на рабочем столе
  • Переменная среды OLLAMA_MODELS

{YELLOW}  AI Helper и Ollama продолжат работать, но:
  - Все проекты и индексы будут удалены
  - Модели нужно скачать заново ({dir_size_gb(D_MODELS)} / {dir_size_gb(C_MODELS)})
  - Зависимости переустановятся при следующем запуске{RESET}
""")
    if not confirm("ПОДТВЕРДИ полную очистку"):
        log("  Отменено.", YELLOW)
        return
    if not confirm("Уверен? Это необратимо"):
        log("  Отменено.", YELLOW)
        return

    log("\n  Начинаю полную очистку...", CYAN)
    remove_dir(DATA_DIR, "Данные AI Helper")
    remove_dir(D_MODELS, "Ollama модели (D:)")
    remove_dir(C_MODELS, "Ollama модели (C:)")
    remove_dir(VENV_DIR, ".venv")
    remove_shortcut()
    remove_env_var("OLLAMA_MODELS")
    log("\n  ✓ Полная очистка завершена.", GREEN)
    log("  Запусти START.bat — всё будет настроено заново.", YELLOW)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

MENU = """
  ╔══════════════════════════════════════════╗
  ║     AI Helper — Очистка                  ║
  ╚══════════════════════════════════════════╝

  Что удалить?

  [1] Данные AI Helper (~/.ai-helper)
      Проекты, индексы, память, история, настройки, бэкапы

  [2] Модели Ollama на D: (D:\\Ollama\\.ollama\\models)

  [3] Модели Ollama на C: (C:\\Users\\...\\AppData\\...)

  [4] Виртуальное окружение (.venv)
      Зависимости — пересоздаётся автоматически

  [5] Ярлык на рабочем столе

  [6] ВСЁ — полный сброс

  [0] Выход
"""


def main() -> int:
    while True:
        print(CYAN + MENU + RESET)

        # Показываем текущие размеры
        log(f"  Текущие размеры:", BOLD)
        log(f"    ~/.ai-helper:     {dir_size_gb(DATA_DIR)}")
        log(f"    Ollama D::        {dir_size_gb(D_MODELS)}")
        log(f"    Ollama C::        {dir_size_gb(C_MODELS)}")
        log(f"    .venv:            {dir_size_gb(VENV_DIR)}")
        print()

        choice = input("  Выбор (0–6): ").strip()

        if choice == "1":
            action_ai_data()
        elif choice == "2":
            action_models_d()
        elif choice == "3":
            action_models_c()
        elif choice == "4":
            action_venv()
        elif choice == "5":
            action_shortcut()
        elif choice == "6":
            action_all()
        elif choice == "0":
            break
        else:
            log("  Неверный выбор.", YELLOW)

        print()
        input("  Enter для продолжения...")

    log("\n  Выход.\n", GREEN)
    return 0


if __name__ == "__main__":
    sys.exit(main())
