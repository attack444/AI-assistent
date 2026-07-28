#!/usr/bin/env python3
"""
Автоматический запуск AI Helper — без ручного ввода команд.

Делает всё сам:
  1. Создаёт виртуальное окружение (.venv)
  2. Устанавливает зависимости
  3. Запускает Ollama (если не работает)
  4. Скачивает нужные модели (если отсутствуют)
  5. Открывает интерфейс в браузере
"""
from __future__ import annotations

import json
import os
import platform
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parent
VENV_DIR = PROJECT_DIR / ".venv"
REQUIREMENTS = PROJECT_DIR / "requirements.txt"
DEFAULT_OLLAMA_HOST = os.environ.get("OLLAMA_HOST", "http://localhost:11434")
REQUIRED_MODELS = ["llama3.1:8b", "nomic-embed-text"]

STREAMLIT_ENV = {
    "STREAMLIT_BROWSER_GATHER_USAGE_STATS": "false",
    "STREAMLIT_SERVER_SHOW_EMAIL_PROMPT": "false",
}


def log(msg: str) -> None:
    print(msg, flush=True)


def venv_python() -> Path:
    if platform.system() == "Windows":
        return VENV_DIR / "Scripts" / "python.exe"
    return VENV_DIR / "bin" / "python"


def ollama_reachable(host: str = DEFAULT_OLLAMA_HOST, timeout: float = 3.0) -> bool:
    try:
        urllib.request.urlopen(f"{host.rstrip('/')}/api/tags", timeout=timeout)
        return True
    except Exception:
        return False


def get_installed_models(host: str = DEFAULT_OLLAMA_HOST) -> list[str]:
    try:
        with urllib.request.urlopen(f"{host.rstrip('/')}/api/tags", timeout=5) as resp:
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


def ensure_venv() -> Path:
    py = venv_python()
    if py.exists():
        return py
    log("📦 Создаю виртуальное окружение...")
    subprocess.run([sys.executable, "-m", "venv", str(VENV_DIR)], check=True)
    return venv_python()


def ensure_dependencies(python: Path) -> None:
    log("📦 Устанавливаю зависимости (первый запуск может занять несколько минут)...")
    subprocess.run([str(python), "-m", "pip", "install", "--upgrade", "pip", "-q"], check=True)
    subprocess.run(
        [str(python), "-m", "pip", "install", "-r", str(REQUIREMENTS), "-q"],
        check=True,
    )
    log("✓ Зависимости установлены")


def find_ollama_exe() -> Path | None:
    system = platform.system()
    if system == "Windows":
        candidates = [
            Path(os.environ.get("LOCALAPPDATA", "")) / "Programs" / "Ollama" / "Ollama.exe",
            Path("C:/Program Files/Ollama/Ollama.exe"),
        ]
        for p in candidates:
            if p.exists():
                return p
    return None


def try_start_ollama(host: str = DEFAULT_OLLAMA_HOST, wait_seconds: int = 45) -> bool:
    if ollama_reachable(host):
        return True

    log("🔄 Ollama не отвечает — пытаюсь запустить автоматически...")
    system = platform.system()

    if system == "Windows":
        exe = find_ollama_exe()
        if exe:
            flags = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0
            subprocess.Popen(
                [str(exe)],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=flags,
            )
        else:
            log("⚠ Ollama не найден. Установи с https://ollama.com/download")
            return False
    elif system == "Darwin":
        subprocess.Popen(
            ["open", "-a", "Ollama"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    else:
        ollama_bin = shutil.which("ollama")
        if ollama_bin:
            subprocess.Popen(
                [ollama_bin, "serve"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
        else:
            log("⚠ Ollama не найден. Установи: curl -fsSL https://ollama.com/install.sh | sh")
            return False

    for i in range(wait_seconds):
        time.sleep(1)
        if ollama_reachable(host):
            log("✓ Ollama запущен")
            return True
        if i % 5 == 4:
            log(f"  жду Ollama... ({i + 1}/{wait_seconds} сек)")

    return ollama_reachable(host)


def pull_model(model: str) -> bool:
    ollama_bin = shutil.which("ollama")
    if not ollama_bin:
        exe = find_ollama_exe()
        if exe:
            ollama_bin = str(exe.parent / "ollama.exe") if platform.system() == "Windows" else str(exe)
        if not ollama_bin or not Path(ollama_bin).exists():
            log(f"⚠ Не найден ollama CLI — пропускаю загрузку {model}")
            return False

    log(f"⬇ Скачиваю модель {model} (может занять несколько минут)...")
    result = subprocess.run([ollama_bin, "pull", model], cwd=str(PROJECT_DIR))
    if result.returncode == 0:
        log(f"✓ Модель {model} готова")
        return True
    log(f"✗ Не удалось скачать {model}")
    return False


def ensure_models(host: str = DEFAULT_OLLAMA_HOST) -> None:
    installed = get_installed_models(host)
    missing = [m for m in REQUIRED_MODELS if not model_is_installed(m, installed)]
    if not missing:
        log(f"✓ Все модели установлены ({', '.join(REQUIRED_MODELS)})")
        return
    log(f"📥 Нужно скачать модели: {', '.join(missing)}")
    for model in missing:
        pull_model(model)


def launch_streamlit(python: Path) -> int:
    log("")
    log("🚀 Запускаю AI Helper...")
    log("   Браузер откроется автоматически: http://localhost:8501")
    log("   Для остановки нажми Ctrl+C в этом окне")
    log("")

    env = {**os.environ, **STREAMLIT_ENV}
    return subprocess.run(
        [str(python), "-m", "streamlit", "run", "app.py", "--server.headless=false"],
        cwd=str(PROJECT_DIR),
        env=env,
    ).returncode


def pause_on_error() -> None:
    if platform.system() == "Windows":
        input("\nНажми Enter для выхода...")


def main() -> int:
    log("=" * 50)
    log("  AI Helper — автоматический запуск")
    log("=" * 50)
    log("")

    try:
        python = ensure_venv()
        ensure_dependencies(python)

        if not try_start_ollama():
            if ollama_reachable():
                log("✓ Ollama уже работает (возможно, запущен параллельно)")
            else:
                log("")
                log("✗ Ollama недоступен.")
                log("  1. Установи Ollama: https://ollama.com/download")
                log("  2. Перезапусти START.bat")
                log("")
                log("  Если видишь ошибку «порт 11434 занят» — Ollama уже работает,")
                log("  просто перезапусти START.bat через минуту.")
                pause_on_error()
                return 1

        ensure_models()
        return launch_streamlit(python)

    except KeyboardInterrupt:
        log("\nОстановлено пользователем.")
        return 0
    except subprocess.CalledProcessError as exc:
        log(f"\n✗ Ошибка выполнения команды (код {exc.returncode})")
        pause_on_error()
        return exc.returncode or 1
    except Exception as exc:
        log(f"\n✗ Ошибка: {exc}")
        pause_on_error()
        return 1


if __name__ == "__main__":
    sys.exit(main())
