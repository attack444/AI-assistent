#!/usr/bin/env python3
"""
ask.py — CLI-инструмент для VS Code интеграции.

Использование:
  python ask.py --message "объясни этот код" --file path/to/file.py
  python ask.py --commit [--push] [--project path/to/repo]
  python ask.py --message "..." [--api http://localhost:8502]

VS Code вызывает этот скрипт через tasks.json.
"""
from __future__ import annotations

import argparse
import json
import sys
import time
import urllib.request
import urllib.error
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parent
API_URL     = "http://localhost:8502"

BOLD  = "\033[1m"
GREEN = "\033[92m"
CYAN  = "\033[96m"
YELLOW= "\033[93m"
RED   = "\033[91m"
RESET = "\033[0m"


def _post(endpoint: str, body: dict, api: str = API_URL) -> dict:
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req  = urllib.request.Request(
        f"{api.rstrip('/')}{endpoint}",
        data=data, method="POST",
        headers={"Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req, timeout=300) as r:
            return json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        return {"ok": False, "error": f"HTTP {e.code}: {e.read().decode()[:200]}"}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def _get(endpoint: str, api: str = API_URL) -> dict:
    req = urllib.request.Request(f"{api.rstrip('/')}{endpoint}")
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            return json.loads(r.read().decode("utf-8"))
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def ensure_api_running(api: str) -> bool:
    """Check if API is up; if not, start it in background."""
    r = _get("/status", api=api)
    if r.get("ok"):
        return True

    # API not running — start it
    print(f"{YELLOW}Запускаю API сервер...{RESET}", flush=True)
    import subprocess, sys
    subprocess.Popen(
        [sys.executable, str(PROJECT_DIR / "api.py")],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        cwd=str(PROJECT_DIR),
    )
    for _ in range(20):
        time.sleep(0.5)
        r = _get("/status", api=api)
        if r.get("ok"):
            print(f"{GREEN}API сервер запущен{RESET}", flush=True)
            return True
    print(f"{RED}Не удалось запустить API сервер{RESET}", flush=True)
    return False


def cmd_chat(message: str, file_path: str | None, project: str | None, api: str) -> int:
    if not ensure_api_running(api):
        # Fallback: run agent directly without API
        return _direct_chat(message, file_path, project)

    # Build message with file context if provided
    full_message = message
    if file_path:
        p = Path(file_path)
        if p.exists():
            try:
                content = p.read_text(encoding="utf-8", errors="ignore")[:8000]
                full_message = f"[Файл: {p.name}]\n```{p.suffix.lstrip('.')}\n{content}\n```\n\n{message}"
                print(f"{CYAN}Файл: {p.name} ({len(content):,} симв){RESET}", flush=True)
            except Exception:
                pass

    print(f"\n{BOLD}Вопрос:{RESET} {message}")
    print(f"{CYAN}{'─' * 50}{RESET}", flush=True)

    body = {"message": full_message}
    if project:
        body["project"] = project

    t0 = time.time()
    r  = _post("/chat", body, api=api)
    elapsed = round(time.time() - t0, 2)

    if r.get("ok"):
        print(r.get("response", ""), flush=True)
        print(f"\n{CYAN}{'─' * 50}{RESET}")
        print(f"{YELLOW}⏱ {elapsed}с{RESET}", flush=True)
        return 0
    else:
        print(f"{RED}Ошибка: {r.get('error', '?')}{RESET}", flush=True)
        return 1


def cmd_commit(project: str | None, push: bool, api: str) -> int:
    if not ensure_api_running(api):
        print(f"{RED}API сервер недоступен{RESET}", flush=True)
        return 1

    print(f"{CYAN}Генерирую commit message...{RESET}", flush=True)
    body = {"push": push}
    if project:
        body["project"] = project

    r = _post("/smart-commit", body, api=api)
    if r.get("ok"):
        print(f"{GREEN}✓ Committed:{RESET} {r.get('message')}")
        if r.get("pushed"):
            print(f"{GREEN}✓ Pushed{RESET}")
        elif push and not r.get("pushed"):
            print(f"{YELLOW}Push не выполнен{RESET}: {r.get('push_output','')}")
        return 0
    else:
        print(f"{RED}Ошибка: {r.get('error', r.get('output','?'))}{RESET}")
        return 1


def _direct_chat(message: str, file_path: str | None, project: str | None) -> int:
    """Fallback: run agent directly without API server."""
    sys.path.insert(0, str(PROJECT_DIR))
    from agent import run_agent
    from core import load_settings
    from memory import MemoryStore
    from profile import load_profile

    settings = load_settings()
    profile  = load_profile()
    memory   = MemoryStore()
    project_root = Path(project) if project else None

    full_message = message
    if file_path:
        p = Path(file_path)
        if p.exists():
            content = p.read_text(encoding="utf-8", errors="ignore")[:8000]
            full_message = f"[Файл: {p.name}]\n```\n{content}\n```\n\n{message}"

    print(f"\n{BOLD}Вопрос:{RESET} {message}")
    print(f"{CYAN}{'─' * 50}{RESET}", flush=True)

    for ev in run_agent(
        user_message=full_message,
        chat_history=[],
        project_root=project_root,
        profile=profile,
        memory=memory,
        llm_model=settings.llm_model,
        ollama_host=settings.ollama_host,
        context_window=settings.context_window,
        fast_llm_model=settings.fast_llm_model,
        groq_api_key=settings.groq_api_key,
        groq_model=settings.groq_model,
    ):
        if ev.type == "text":
            print(ev.content, end="", flush=True)
        elif ev.type == "error":
            print(f"\n{RED}Ошибка: {ev.content}{RESET}", flush=True)
        elif ev.type == "done":
            print()

    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="AI Helper CLI для VS Code")
    parser.add_argument("--message",  "-m", default="",   help="Вопрос к AI")
    parser.add_argument("--file",     "-f", default="",   help="Путь к файлу для контекста")
    parser.add_argument("--project",  "-p", default="",   help="Путь к проекту")
    parser.add_argument("--commit",   "-c", action="store_true", help="Smart git commit")
    parser.add_argument("--push",           action="store_true", help="Пуш после коммита")
    parser.add_argument("--api",            default=API_URL,      help="API URL")
    args = parser.parse_args()

    if args.commit:
        return cmd_commit(args.project or None, args.push, args.api)
    elif args.message:
        return cmd_chat(args.message, args.file or None, args.project or None, args.api)
    else:
        parser.print_help()
        return 1


if __name__ == "__main__":
    sys.exit(main())
