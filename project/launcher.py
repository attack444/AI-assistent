#!/usr/bin/env python3
"""
Автоматический запуск AI Helper.

Режимы:
  python launcher.py                  — обычный запуск
  python launcher.py --install-shortcut  — только создать ярлык на рабочем столе
"""
from __future__ import annotations

import json
import os
import platform
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Tuple

from ollama_paths import apply_ollama_models_env, diagnose_models, find_models_dirs

PROJECT_DIR = Path(__file__).resolve().parent
VENV_DIR = PROJECT_DIR / ".venv"
REQUIREMENTS = PROJECT_DIR / "requirements.txt"
DEFAULT_OLLAMA_HOST = os.environ.get("OLLAMA_HOST", "http://localhost:11434")
REQUIRED_MODELS = ["qwen2.5-coder:14b", "nomic-embed-text"]
SHORTCUT_FLAG = PROJECT_DIR / ".ai_helper_shortcut_created"

STREAMLIT_ENV = {
    "STREAMLIT_BROWSER_GATHER_USAGE_STATS": "false",
    "STREAMLIT_SERVER_SHOW_EMAIL_PROMPT": "false",
}


def log(msg: str) -> None:
    text = str(msg)
    try:
        print(text, flush=True)
    except UnicodeEncodeError:
        safe = text.encode("ascii", errors="ignore").decode("ascii")
        if not safe.strip():
            safe = text.encode(
                sys.stdout.encoding or "utf-8", errors="replace"
            ).decode(sys.stdout.encoding or "utf-8", errors="replace")
        print(safe, flush=True)


# ---------------------------------------------------------------------------
# Desktop shortcut (Windows)
# ---------------------------------------------------------------------------

def create_desktop_shortcut() -> Tuple[bool, str]:
    """
    Создаёт ярлык «AI Helper» на рабочем столе Windows.
    Использует временный .ps1 файл — полностью обходит проблемы
    с экранированием путей в cmd.exe.
    """
    if platform.system() != "Windows":
        return False, "Ярлыки поддерживаются только на Windows"

    start_bat = PROJECT_DIR / "START.bat"
    if not start_bat.exists():
        return False, f"Не найден START.bat:\n  {start_bat}"

    # Пути в PowerShell here-string (@'...'@) — никаких проблем с backslash,
    # пробелами или кириллицей в именах папок/пользователей
    ps_script = (
        "$ErrorActionPreference = 'Stop'\n"
        "$WshShell = New-Object -ComObject WScript.Shell\n"
        "$Desktop = [Environment]::GetFolderPath('Desktop')\n"
        "$LnkPath = Join-Path $Desktop 'AI Helper.lnk'\n"
        "$Shortcut = $WshShell.CreateShortcut($LnkPath)\n"
        f"$Shortcut.TargetPath = @'\r\n{start_bat}\r\n'@\n"
        f"$Shortcut.WorkingDirectory = @'\r\n{PROJECT_DIR}\r\n'@\n"
        "$Shortcut.Description = 'AI Helper'\n"
        "$Shortcut.WindowStyle = 1\n"
        "$Shortcut.Save()\n"
        "Write-Host \"Shortcut: $LnkPath\"\n"
    )

    ps_path = None
    try:
        fd, ps_path_str = tempfile.mkstemp(suffix=".ps1", prefix="ai_helper_")
        ps_path = Path(ps_path_str)
        with os.fdopen(fd, "w", encoding="utf-8-sig") as f:
            f.write(ps_script)

        result = subprocess.run(
            [
                "powershell",
                "-NoProfile",
                "-ExecutionPolicy", "Bypass",
                "-File", str(ps_path),
            ],
            capture_output=True,
            text=True,
            timeout=30,
        )
        if result.returncode == 0:
            SHORTCUT_FLAG.touch(exist_ok=True)
            return True, "Ярлык 'AI Helper' создан на рабочем столе"
        err = (result.stderr or result.stdout or "").strip()
        return False, f"PowerShell ошибка:\n{err}"
    except FileNotFoundError:
        return False, "PowerShell не найден. Установи Windows PowerShell."
    except subprocess.TimeoutExpired:
        return False, "Время ожидания PowerShell истекло"
    except Exception as exc:
        return False, str(exc)
    finally:
        if ps_path and ps_path.exists():
            try:
                ps_path.unlink()
            except Exception:
                pass


def maybe_create_shortcut_once() -> None:
    """Создать ярлык один раз при первом успешном запуске."""
    if platform.system() != "Windows":
        return
    if SHORTCUT_FLAG.exists():
        return
    ok, msg = create_desktop_shortcut()
    if ok:
        log(f"[OK] {msg}")
    # Тихо пропускаем ошибку — ярлык не критичен для работы


# ---------------------------------------------------------------------------
# Venv & dependencies
# ---------------------------------------------------------------------------

def venv_python() -> Path:
    if platform.system() == "Windows":
        return VENV_DIR / "Scripts" / "python.exe"
    return VENV_DIR / "bin" / "python"


def ensure_venv() -> Path:
    py = venv_python()
    if py.exists():
        return py
    log("Создаю виртуальное окружение...")
    subprocess.run([sys.executable, "-m", "venv", str(VENV_DIR)], check=True)
    return venv_python()


def ensure_dependencies(python: Path) -> None:
    log("Устанавливаю зависимости (первый запуск — несколько минут)...")
    subprocess.run(
        [str(python), "-m", "pip", "install", "--upgrade", "pip", "-q"],
        check=True,
    )
    subprocess.run(
        [str(python), "-m", "pip", "install", "-r", str(REQUIREMENTS), "-q"],
        check=True,
    )
    log("[OK] Зависимости установлены")


# ---------------------------------------------------------------------------
# Ollama
# ---------------------------------------------------------------------------

def ollama_reachable(host: str = DEFAULT_OLLAMA_HOST, timeout: float = 3.0) -> bool:
    try:
        urllib.request.urlopen(f"{host.rstrip('/')}/api/tags", timeout=timeout)
        return True
    except Exception:
        return False


def get_installed_models(host: str = DEFAULT_OLLAMA_HOST) -> list[str]:
    try:
        with urllib.request.urlopen(
            f"{host.rstrip('/')}/api/tags", timeout=5
        ) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        return [str(m.get("name", "")) for m in data.get("models", []) if m.get("name")]
    except Exception:
        return []


def model_is_installed(required: str, installed: list[str]) -> bool:
    base = required.split(":")[0]
    for name in installed:
        if name == required or name.startswith(f"{required}:") or name.startswith(f"{base}:"):
            return True
    return False


def find_ollama_exe() -> Path | None:
    if platform.system() == "Windows":
        for p in [
            Path(os.environ.get("LOCALAPPDATA", "")) / "Programs" / "Ollama" / "Ollama.exe",
            Path("C:/Program Files/Ollama/Ollama.exe"),
        ]:
            if p.exists():
                return p
    return None


def ollama_env() -> dict[str, str]:
    """Окружение для запуска Ollama — включает OLLAMA_MODELS."""
    return {**os.environ}


def ensure_ollama_models_permanent() -> None:
    """
    Прописать OLLAMA_MODELS через setx, чтобы Ollama Desktop при автозапуске
    тоже использовал D: (действует с следующего сеанса Windows).
    """
    target = os.environ.get("OLLAMA_MODELS", "")
    if not target or platform.system() != "Windows":
        return
    try:
        result = subprocess.run(
            ["setx", "OLLAMA_MODELS", target],
            capture_output=True, text=True, timeout=15,
        )
        if result.returncode == 0:
            log(f"[OK] OLLAMA_MODELS={target} прописан постоянно (setx)")
        else:
            log(f"[!] setx не сработал: {(result.stderr or result.stdout).strip()}")
    except Exception as exc:
        log(f"[!] setx: {exc}")


def kill_ollama_process() -> bool:
    """Завершить все процессы Ollama на Windows."""
    if platform.system() != "Windows":
        return False
    try:
        result = subprocess.run(
            ["taskkill", "/IM", "ollama.exe", "/F"],
            capture_output=True, text=True, timeout=10,
        )
        if result.returncode == 0:
            log("[OK] Ollama остановлен")
            time.sleep(2)
            return True
    except Exception:
        pass
    return False


def ollama_uses_correct_path(host: str = DEFAULT_OLLAMA_HOST) -> bool:
    """
    Проверяет что запущенный Ollama использует правильный OLLAMA_MODELS.
    Считает 'правильным' если API возвращает хоть одну модель
    ИЛИ папка моделей пустая (ещё ничего не скачано).
    Возвращает False только когда: Ollama запущен, список пустой, но папка на D: непустая.
    """
    target = os.environ.get("OLLAMA_MODELS", "")
    if not target:
        return True  # не задан — считаем норм

    installed = get_installed_models(host)
    if installed:
        return True  # модели видны — всё хорошо

    # Список пуст. Проверим есть ли файлы на целевом диске
    from ollama_paths import has_data
    target_path = Path(target)
    if has_data(target_path):
        # Файлы есть, но Ollama их не видит — значит запущен с другим путём
        return False

    return True  # Список пуст и папка пуста — нормально (первый запуск)


def try_start_ollama(host: str = DEFAULT_OLLAMA_HOST, wait_seconds: int = 45) -> bool:
    """
    Запустить Ollama с правильным OLLAMA_MODELS.
    Если Ollama уже запущен, но использует неверный путь — перезапустить.
    """
    env = ollama_env()
    models_path = os.environ.get("OLLAMA_MODELS", "")
    system = platform.system()

    if ollama_reachable(host):
        if ollama_uses_correct_path(host):
            log(f"[OK] Ollama запущен, папка моделей: {models_path or 'default'}")
            return True
        # Запущен с неверным путём — перезапустим
        log(f"[!] Ollama запущен, но не видит модели на {models_path}")
        log("    Перезапускаю Ollama с правильным путём...")
        kill_ollama_process()

    log(f"Запускаю Ollama (OLLAMA_MODELS={models_path or 'default'})...")

    if system == "Windows":
        exe = find_ollama_exe()
        if not exe:
            log("[!] Ollama не найден. Установи: https://ollama.com/download")
            return False
        flags = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0
        subprocess.Popen(
            [str(exe)],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=flags,
            env=env,
        )
    elif system == "Darwin":
        subprocess.Popen(
            ["open", "-a", "Ollama"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            env=env,
        )
    else:
        ollama_bin = shutil.which("ollama")
        if not ollama_bin:
            log("[!] Ollama не найден. Установи: curl -fsSL https://ollama.com/install.sh | sh")
            return False
        subprocess.Popen(
            [ollama_bin, "serve"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            env=env,
        )

    for i in range(wait_seconds):
        time.sleep(1)
        if ollama_reachable(host):
            log("[OK] Ollama запущен")
            return True
        if i % 5 == 4:
            log(f"  жду Ollama... ({i + 1}/{wait_seconds} сек)")

    return ollama_reachable(host)


def warm_up_model(model: str, host: str = DEFAULT_OLLAMA_HOST) -> None:
    """
    Send a tiny dummy request so the model is loaded into memory.
    Subsequent real requests will skip the loading delay (~30s for 14b).
    Runs in background — no blocking wait.
    """
    import threading
    def _do():
        try:
            url  = f"{host.rstrip('/')}/api/generate"
            body = json.dumps({
                "model":       model,
                "prompt":      "hi",
                "stream":      False,
                "options":     {"num_predict": 1},
                "keep_alive":  "10m",   # keep loaded for 10 minutes
            }).encode()
            req  = urllib.request.Request(
                url, data=body, method="POST",
                headers={"Content-Type": "application/json"},
            )
            urllib.request.urlopen(req, timeout=300)
        except Exception:
            pass
    threading.Thread(target=_do, daemon=True).start()
    log(f"[OK] Прогрев модели {model} запущен в фоне")


def pull_model(model: str) -> bool:
    ollama_bin = shutil.which("ollama")
    if not ollama_bin:
        exe = find_ollama_exe()
        if exe:
            ollama_bin = str(exe.parent / ("ollama.exe" if platform.system() == "Windows" else "ollama"))
    if not ollama_bin or not Path(ollama_bin).exists():
        log(f"[!] ollama CLI не найден, пропускаю {model}")
        return False

    log(f"Скачиваю {model} (может занять несколько минут)...")
    result = subprocess.run([ollama_bin, "pull", model], env=ollama_env())
    if result.returncode == 0:
        log(f"[OK] {model} готова")
        return True
    log(f"[ERR] Не удалось скачать {model}")
    return False


def diagnose_running_ollama(host: str = DEFAULT_OLLAMA_HOST) -> list[str]:
    """Ollama уже запущен, но API не видит модели — типично после смены OLLAMA_MODELS."""
    if not ollama_reachable(host):
        return []
    if get_installed_models(host):
        return []

    locations = find_models_dirs()
    if not locations:
        return []

    best_path, best_size = locations[0]
    return [
        "[!] Ollama запущен, но ollama list пустой.",
        f"    Файлы моделей найдены: {best_path} ({best_size} MB)",
        "    Ollama Desktop читает OLLAMA_MODELS только при старте.",
        "    1. ПКМ на иконке Ollama в трее -> Quit",
        "    2. Запусти «Настроить Ollama на диск D.bat» (скопирует на D:)",
        "    3. Запусти Ollama снова и проверь: ollama list",
    ]


def ensure_models(host: str = DEFAULT_OLLAMA_HOST) -> None:
    installed = get_installed_models(host)
    missing = [m for m in REQUIRED_MODELS if not model_is_installed(m, installed)]
    if not missing:
        log(f"[OK] Модели установлены: {', '.join(REQUIRED_MODELS)}")
        return
    log(f"Скачиваю модели: {', '.join(missing)}")
    for model in missing:
        pull_model(model)


# ---------------------------------------------------------------------------
# Streamlit
# ---------------------------------------------------------------------------

def launch_streamlit(python: Path) -> int:
    log("")
    log("Запускаю AI Helper...")
    log("   Браузер: http://localhost:8501")
    log("   Остановка: Ctrl+C")
    log("")

    env = {**os.environ, **STREAMLIT_ENV}
    try:
        return subprocess.run(
            [str(python), "-m", "streamlit", "run", "app.py", "--server.headless=false"],
            cwd=str(PROJECT_DIR),
            env=env,
        ).returncode
    except FileNotFoundError:
        log("[ERR] Streamlit не найден. Перезапусти START.bat.")
        return 1


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def pause_on_error() -> None:
    if platform.system() == "Windows":
        input("\nНажми Enter для выхода...")


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main() -> int:
    # Режим установки ярлыка
    if "--install-shortcut" in sys.argv:
        log("")
        log("Создаю ярлык на рабочем столе...")
        ok, msg = create_desktop_shortcut()
        log(f"{'[OK]' if ok else '[ERR]'} {msg}")
        if ok:
            log("")
            log("Готово! Ярлык 'AI Helper' появился на рабочем столе.")
            log("Запускай AI Helper двойным кликом по этому ярлыку.")
            log("")
        else:
            log("")
            log("Не удалось создать ярлык автоматически.")
            log("Создай его вручную: ПКМ на рабочем столе → Создать ярлык")
            log(f"  Объект: {PROJECT_DIR / 'START.bat'}")
            pause_on_error()
        return 0 if ok else 1

    # Обычный запуск
    log("=" * 50)
    log("  AI Helper")
    log("=" * 50)
    log("")

    try:
        models_path = apply_ollama_models_env()
        log(f"Папка моделей Ollama: {models_path}")

        # Прописать путь постоянно через setx — чтобы Ollama Desktop при
        # автозапуске Windows тоже использовал D:
        ensure_ollama_models_permanent()

        python = ensure_venv()
        ensure_dependencies(python)

        # Создать ярлык при первом успешном запуске
        maybe_create_shortcut_once()

        for line in diagnose_models():
            log(line)

        if not try_start_ollama():
            if ollama_reachable():
                log("[OK] Ollama уже работает")
            else:
                log("")
                log("[ERR] Ollama недоступен.")
                log("  1. Установи с https://ollama.com/download")
                log("  2. Перезапусти START.bat")
                log("")
                log("  Ошибка 'порт 11434 занят' = Ollama уже работает,")
                log("  просто перезапусти START.bat.")
                pause_on_error()
                return 1

        for line in diagnose_running_ollama():
            log(line)

        ensure_models()

        # Pre-warm the main model in background so first chat request is instant
        warm_up_model(REQUIRED_MODELS[0])

        return launch_streamlit(python)

    except KeyboardInterrupt:
        log("\nОстановлено.")
        return 0
    except subprocess.CalledProcessError as exc:
        log(f"\n[ERR] Ошибка команды (код {exc.returncode})")
        pause_on_error()
        return exc.returncode or 1
    except Exception as exc:
        log(f"\n[ERR] {exc}")
        pause_on_error()
        return 1


if __name__ == "__main__":
    sys.exit(main())
