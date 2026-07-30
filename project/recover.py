#!/usr/bin/env python3
"""
recover.py — восстановление работоспособности AI Helper.

Запуск: python recover.py
  или:  RECOVERY.bat  (не требует venv)

Что делает:
  1. Проверяет и чинит зависимости (pip install)
  2. Восстанавливает исходники из последнего бэкапа (если сломаны)
  3. Очищает кэш Streamlit и __pycache__
  4. Перепроверяет компиляцию всех файлов
  5. Сбрасывает настройки если повреждены
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parent
VENV_DIR    = PROJECT_DIR / ".venv"
BACKUP_DIR  = Path.home() / ".ai-helper" / "backups" / "source"
DATA_DIR    = Path.home() / ".ai-helper"

SOURCE_FILES = [
    "app.py", "core.py", "agent.py", "tools.py",
    "memory.py", "profile.py", "launcher.py",
    "self_update.py", "ollama_paths.py",
]

GREEN = "\033[92m"
RED   = "\033[91m"
YELLOW= "\033[93m"
CYAN  = "\033[96m"
RESET = "\033[0m"


def log(msg: str, color: str = "") -> None:
    print(f"{color}{msg}{RESET}", flush=True)


def sep(title: str = "") -> None:
    line = "─" * 52
    if title:
        print(f"\n{CYAN}┌ {title} {'─' * max(0, 48 - len(title))}┐{RESET}")
    else:
        print(f"{CYAN}{line}{RESET}")


# ---------------------------------------------------------------------------
# Step 1: Check & fix Python files (compile)
# ---------------------------------------------------------------------------

def check_compile_all() -> dict[str, str]:
    errors: dict[str, str] = {}
    for name in SOURCE_FILES:
        p = PROJECT_DIR / name
        if not p.exists():
            continue
        r = subprocess.run(
            [sys.executable, "-m", "py_compile", str(p)],
            capture_output=True, text=True,
        )
        if r.returncode != 0:
            errors[name] = (r.stderr or r.stdout or "Ошибка компиляции").strip()
    return errors


# ---------------------------------------------------------------------------
# Step 2: Restore from backup
# ---------------------------------------------------------------------------

def find_latest_backup() -> Path | None:
    if not BACKUP_DIR.exists():
        return None
    dirs = sorted([d for d in BACKUP_DIR.iterdir() if d.is_dir()], reverse=True)
    return dirs[0] if dirs else None


def restore_file_from_backup(name: str, backup_dir: Path) -> bool:
    src = backup_dir / name
    dst = PROJECT_DIR / name
    if not src.exists():
        return False
    shutil.copy2(src, dst)
    return True


def restore_broken_files(broken: list[str]) -> list[str]:
    bk = find_latest_backup()
    if not bk:
        log("Бэкапов не найдено.", RED)
        return []
    restored = []
    for name in broken:
        if restore_file_from_backup(name, bk):
            ok, _ = check_compile_all().get(name, ("", "")), ""
            restored.append(name)
            log(f"  ✓ Восстановлен из бэкапа: {name}  ← {bk.name}", GREEN)
        else:
            log(f"  ✗ {name} не найден в бэкапе {bk.name}", RED)
    return restored


# ---------------------------------------------------------------------------
# Step 3: Reinstall dependencies
# ---------------------------------------------------------------------------

def get_python() -> str:
    """Найти Python: сначала системный, потом venv."""
    venv_py = VENV_DIR / ("Scripts/python.exe" if sys.platform == "win32" else "bin/python")
    if venv_py.exists():
        return str(venv_py)
    return sys.executable


def reinstall_deps(python: str) -> bool:
    req = PROJECT_DIR / "requirements.txt"
    if not req.exists():
        log("requirements.txt не найден", RED)
        return False
    log("Устанавливаю зависимости...", CYAN)
    r = subprocess.run(
        [python, "-m", "pip", "install", "-r", str(req), "-q", "--no-warn-script-location"],
        cwd=str(PROJECT_DIR),
    )
    return r.returncode == 0


def recreate_venv() -> bool:
    log("Пересоздаю виртуальное окружение...", CYAN)
    if VENV_DIR.exists():
        shutil.rmtree(VENV_DIR)
    r = subprocess.run([sys.executable, "-m", "venv", str(VENV_DIR)])
    return r.returncode == 0


# ---------------------------------------------------------------------------
# Step 4: Clear caches
# ---------------------------------------------------------------------------

def clear_caches() -> None:
    cleared = []

    # Streamlit cache
    st_cache_dirs = [
        Path.home() / ".streamlit" / "cache",
        PROJECT_DIR / ".streamlit_cache",
    ]
    for d in st_cache_dirs:
        if d.exists():
            shutil.rmtree(d, ignore_errors=True)
            cleared.append(str(d))

    # __pycache__
    for pycache in PROJECT_DIR.rglob("__pycache__"):
        shutil.rmtree(pycache, ignore_errors=True)
        cleared.append(str(pycache))

    # *.pyc
    for pyc in PROJECT_DIR.rglob("*.pyc"):
        pyc.unlink(missing_ok=True)

    if cleared:
        log(f"Очищен кэш: {len(cleared)} папок", GREEN)
    else:
        log("Кэш был пустым", YELLOW)


# ---------------------------------------------------------------------------
# Step 5: Reset corrupted settings
# ---------------------------------------------------------------------------

def reset_settings_if_broken() -> bool:
    settings_file = DATA_DIR / "settings.json"
    if not settings_file.exists():
        return False
    try:
        json.loads(settings_file.read_text(encoding="utf-8"))
        return False  # OK
    except Exception:
        log(f"settings.json повреждён — сбрасываю...", YELLOW)
        settings_file.unlink(missing_ok=True)
        return True


# ---------------------------------------------------------------------------
# Step 6: Reset projects list if corrupted
# ---------------------------------------------------------------------------

def reset_projects_if_broken() -> bool:
    pfile = DATA_DIR / "projects.json"
    if not pfile.exists():
        return False
    try:
        json.loads(pfile.read_text(encoding="utf-8"))
        return False
    except Exception:
        log("projects.json повреждён — сбрасываю...", YELLOW)
        pfile.unlink(missing_ok=True)
        return True


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main() -> int:
    print()
    print(f"{CYAN}╔══════════════════════════════════════════╗")
    print(f"║    AI Helper — Восстановление              ║")
    print(f"╚══════════════════════════════════════════╝{RESET}")
    print()

    any_action = False

    # 1. Check compile
    sep("Проверка исходного кода")
    errors = check_compile_all()
    ok_files = [f for f in SOURCE_FILES if (PROJECT_DIR / f).exists() and f not in errors]
    log(f"  ОК: {len(ok_files)} файлов", GREEN)

    if errors:
        log(f"  Ошибки компиляции ({len(errors)}):", RED)
        for name, err in errors.items():
            log(f"    ✗ {name}: {err[:120]}", RED)
        print()
        ans = input("  Восстановить из бэкапа? (y/n): ").strip().lower()
        if ans in ("y", "yes", "д", "да"):
            restored = restore_broken_files(list(errors.keys()))
            any_action = True
            if restored:
                # Re-check
                errors2 = check_compile_all()
                if not errors2:
                    log("  ✓ Все файлы скомпилированы после восстановления", GREEN)
                else:
                    log(f"  Остались ошибки: {list(errors2.keys())}", RED)
    else:
        log("  ✓ Все файлы компилируются", GREEN)

    # 2. Settings
    sep("Проверка конфигурации")
    if reset_settings_if_broken():
        log("  ✓ settings.json сброшен (будет создан заново при запуске)", GREEN)
        any_action = True
    else:
        log("  ✓ settings.json OK", GREEN)

    if reset_projects_if_broken():
        log("  ✓ projects.json сброшен", GREEN)
        any_action = True
    else:
        log("  ✓ projects.json OK", GREEN)

    # 3. Cache
    sep("Очистка кэша")
    clear_caches()
    any_action = True

    # 4. Dependencies
    sep("Зависимости")
    venv_py = VENV_DIR / ("Scripts/python.exe" if sys.platform == "win32" else "bin/python")
    if not venv_py.exists():
        log("  Виртуальное окружение не найдено", YELLOW)
        ans = input("  Создать заново? (y/n): ").strip().lower()
        if ans in ("y", "yes", "д", "да"):
            if recreate_venv():
                log("  ✓ venv создан", GREEN)
                if reinstall_deps(str(venv_py)):
                    log("  ✓ Зависимости установлены", GREEN)
                    any_action = True
                else:
                    log("  ✗ Ошибка установки зависимостей", RED)
            else:
                log("  ✗ Не удалось создать venv", RED)
    else:
        log(f"  venv: {venv_py}", GREEN)
        ans = input("  Переустановить зависимости? (y/n): ").strip().lower()
        if ans in ("y", "yes", "д", "да"):
            if reinstall_deps(str(venv_py)):
                log("  ✓ Зависимости переустановлены", GREEN)
                any_action = True
            else:
                log("  ✗ Ошибка установки", RED)

    # 5. Summary
    sep("Итог")
    if any_action:
        log("  Восстановление завершено.", GREEN)
    else:
        log("  Всё в порядке, изменений не было.", GREEN)

    log("""
  Если проблема осталась:
  1. Удали папку .venv и запусти START.bat (пересоздаст автоматически)
  2. Запусти cleanup.bat → «Только данные» → снова START.bat
  3. Проверь ошибку в конце этого окна — скопируй текст ошибки и
     спроси у ассистента: "Исправь ошибку: <текст>"
""", YELLOW)

    input("Enter для выхода...")
    return 0


if __name__ == "__main__":
    sys.exit(main())
