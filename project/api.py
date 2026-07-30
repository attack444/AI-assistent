"""
api.py — REST API сервер для AI Helper.

Запуск: python api.py  (порт 8502)
Используется для:
  - Интеграции с VS Code (задачи, команды)
  - Будущего виджета чата на сайте
  - Любых внешних клиентов

Endpoints:
  GET  /status           — проверить работоспособность
  POST /chat             — отправить сообщение, получить ответ
  POST /chat/stream      — потоковый ответ (SSE)
  POST /smart-commit     — сгенерировать коммит сообщение и закоммитить
  GET  /project/files    — список файлов проекта
  POST /project/read     — прочитать файл
"""
from __future__ import annotations

import json
import os
import sys
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any, Dict, Optional
from urllib.parse import parse_qs, urlparse

# ── Bootstrap: add project dir to sys.path ──────────────────────────────────
PROJECT_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(PROJECT_DIR))

from core import (
    AppSettings,
    check_ollama_status,
    load_projects,
    load_settings,
)
from memory import MemoryStore
from profile import UserProfile, load_profile
from tools import git_run, list_dir, read_file

DATA_DIR = Path.home() / ".ai-helper"
PORT = int(os.environ.get("AI_HELPER_API_PORT", "8502"))

# ---------------------------------------------------------------------------
# Simple helpers
# ---------------------------------------------------------------------------

def _json(obj: Any) -> bytes:
    return json.dumps(obj, ensure_ascii=False, indent=2).encode("utf-8")


def _run_agent_sync(
    message: str,
    project_root: Optional[Path],
    settings: AppSettings,
    profile: UserProfile,
    memory: MemoryStore,
    history: Optional[list] = None,
) -> str:
    """Run the agent loop and collect the full response text."""
    from agent import run_agent
    text_parts: list[str] = []
    for ev in run_agent(
        user_message=message,
        chat_history=history or [],
        project_root=project_root,
        profile=profile,
        memory=memory,
        llm_model=settings.llm_model,
        ollama_host=settings.ollama_host,
        context_window=settings.context_window,
        fast_llm_model=settings.fast_llm_model,
        groq_api_key=settings.groq_api_key,
        groq_model=settings.groq_model,
        deepseek_api_key=settings.deepseek_api_key,
        deepseek_model=settings.deepseek_model,
        http_proxy=settings.http_proxy,
    ):
        if ev.type == "text":
            text_parts.append(ev.content)
        elif ev.type == "error":
            text_parts.append(f"\n[Ошибка: {ev.content}]")
    return "".join(text_parts)


# ---------------------------------------------------------------------------
# HTTP handler
# ---------------------------------------------------------------------------

class APIHandler(BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        pass  # suppress default access log

    def _send(self, code: int, body: bytes, content_type: str = "application/json") -> None:
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Access-Control-Allow-Origin", "*")   # CORS for web widget
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self._send(204, b"")

    def _read_body(self) -> Dict[str, Any]:
        length = int(self.headers.get("Content-Length", 0))
        if length:
            return json.loads(self.rfile.read(length).decode("utf-8"))
        return {}

    def _load_context(self, project_name: str = "") -> tuple:
        settings = load_settings()
        profile  = load_profile()
        memory   = MemoryStore()
        projects = load_projects()
        if project_name and project_name in projects:
            project_root = Path(projects[project_name].root)
        elif projects:
            project_root = Path(list(projects.values())[0].root)
        else:
            project_root = None
        return settings, profile, memory, project_root

    # ── GET /status ──────────────────────────────────────────────────────────
    def _get_status(self):
        settings = load_settings()
        ost      = check_ollama_status(settings.ollama_host)
        projects = load_projects()
        self._send(200, _json({
            "ok":           True,
            "ollama":       ost.reachable,
            "models":       ost.models,
            "groq":         bool(settings.groq_api_key),
            "groq_model":   settings.groq_model,
            "llm_model":    settings.llm_model,
            "fast_model":   settings.fast_llm_model,
            "projects":     list(projects.keys()),
            "version":      "1.0",
        }))

    # ── GET /project/files ────────────────────────────────────────────────────
    def _get_project_files(self):
        qs = parse_qs(urlparse(self.path).query)
        proj_name = qs.get("project", [""])[0]
        settings, profile, memory, project_root = self._load_context(proj_name)
        if not project_root:
            self._send(404, _json({"error": "Нет активного проекта"}))
            return
        r = list_dir(str(project_root), recursive=True, extensions="")
        self._send(200, _json({"project": project_root.name, "path": str(project_root), **r}))

    # ── POST /project/read ────────────────────────────────────────────────────
    def _post_project_read(self):
        body = self._read_body()
        path = body.get("path", "")
        if not path:
            self._send(400, _json({"error": "Нужен путь (path)"}))
            return
        r = read_file(path)
        self._send(200 if r["ok"] else 404, _json(r))

    # ── POST /chat ────────────────────────────────────────────────────────────
    def _post_chat(self):
        body = self._read_body()
        message = body.get("message", "").strip()
        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"}))
            return
        proj_name = body.get("project", "")
        history   = body.get("history", [])
        settings, profile, memory, project_root = self._load_context(proj_name)

        t0   = time.time()
        text = _run_agent_sync(message, project_root, settings, profile, memory, history)
        elapsed = round(time.time() - t0, 2)

        self._send(200, _json({
            "ok":          True,
            "response":    text,
            "elapsed_sec": elapsed,
            "project":     project_root.name if project_root else None,
        }))

    # ── POST /chat/stream (SSE) ───────────────────────────────────────────────
    def _post_chat_stream(self):
        from agent import run_agent

        body      = self._read_body()
        message   = body.get("message", "").strip()
        proj_name = body.get("project", "")
        history   = body.get("history", [])

        if not message:
            self._send(400, _json({"error": "Нужно поле 'message'"})); return

        settings, profile, memory, project_root = self._load_context(proj_name)

        # CRITICAL: close connection when streaming ends so Node.js 'end' event fires
        self.close_connection = True
        self.send_response(200)
        self.send_header("Content-Type",                "text/event-stream; charset=utf-8")
        self.send_header("Cache-Control",               "no-cache")
        self.send_header("Connection",                  "close")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()

        def _sse(obj: dict) -> None:
            data = json.dumps(obj, ensure_ascii=False)
            try:
                self.wfile.write(f"data: {data}\n\n".encode("utf-8"))
                self.wfile.flush()
            except (BrokenPipeError, ConnectionResetError):
                pass

        try:
            for ev in run_agent(
                user_message=message,
                chat_history=history,
                project_root=project_root,
                profile=profile,
                memory=memory,
                llm_model=settings.llm_model,
                ollama_host=settings.ollama_host,
                context_window=settings.context_window,
                fast_llm_model=settings.fast_llm_model,
                groq_api_key=settings.groq_api_key,
                groq_model=settings.groq_model,
                deepseek_api_key=settings.deepseek_api_key,
                deepseek_model=settings.deepseek_model,
                http_proxy=settings.http_proxy,
            ):
                if ev.type == "text":
                    _sse({"type": "text", "content": ev.content})
                elif ev.type == "error":
                    _sse({"type": "error", "content": ev.content})
                    _sse({"type": "done"})
                    return
                elif ev.type == "tool_call":
                    _sse({"type": "tool_call", "name": ev.tool_name, "args": ev.tool_args})
                elif ev.type == "info":
                    _sse({"type": "info", "content": ev.content})
                elif ev.type == "done":
                    _sse({"type": "done"})
                    return
        except Exception as exc:
            _sse({"type": "error", "content": str(exc)})
            _sse({"type": "done"})

    # ── POST /smart-commit ────────────────────────────────────────────────────
    def _post_smart_commit(self):
        body      = self._read_body()
        proj_name = body.get("project", "")
        push      = body.get("push", False)
        settings, profile, memory, project_root = self._load_context(proj_name)

        if not project_root:
            self._send(404, _json({"error": "Нет активного проекта"}))
            return

        # Get git diff
        diff_result = git_run("diff --cached --stat", str(project_root))
        if not diff_result.get("output", "").strip():
            # Nothing staged — stage all
            git_run("add -A", str(project_root))
            diff_result = git_run("diff --cached --stat", str(project_root))

        diff_text = diff_result.get("output", "нет изменений")

        # Ask AI to generate commit message
        prompt = (
            f"Сгенерируй краткое git commit message (1 строка, английский, формат 'type: description') "
            f"для следующих изменений:\n\n{diff_text[:3000]}\n\n"
            f"Только сообщение, без объяснений."
        )
        commit_msg = _run_agent_sync(prompt, project_root, settings, profile, memory)
        commit_msg = commit_msg.strip().split("\n")[0].strip('"').strip("'")

        # Commit
        git_run("add -A", str(project_root))
        commit_r = git_run(f'commit -m "{commit_msg}"', str(project_root))
        result   = {"ok": commit_r["ok"], "message": commit_msg, "output": commit_r.get("output")}

        if push and commit_r["ok"]:
            push_r = git_run("push", str(project_root))
            result["pushed"]      = push_r["ok"]
            result["push_output"] = push_r.get("output")

        self._send(200 if result["ok"] else 500, _json(result))

    # ── Routing ───────────────────────────────────────────────────────────────
    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/status":
            self._get_status()
        elif path == "/project/files":
            self._get_project_files()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))

    def do_POST(self):
        path = urlparse(self.path).path
        if path == "/chat":
            self._post_chat()
        elif path == "/chat/stream":
            self._post_chat_stream()
        elif path == "/smart-commit":
            self._post_smart_commit()
        elif path == "/project/read":
            self._post_project_read()
        else:
            self._send(404, _json({"error": f"Unknown endpoint: {path}"}))


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    server = ThreadingHTTPServer(("localhost", PORT), APIHandler)
    print(f"AI Helper API  →  http://localhost:{PORT}", flush=True)
    print(f"  GET  /status          — статус", flush=True)
    print(f"  POST /chat            — чат", flush=True)
    print(f"  POST /chat/stream     — SSE стриминг", flush=True)
    print(f"  POST /smart-commit    — AI коммит", flush=True)
    print(f"  GET  /project/files   — файлы проекта", flush=True)
    print(f"  POST /project/read    — прочитать файл", flush=True)
    print(f"\nCtrl+C для остановки", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nAPI server stopped.")


if __name__ == "__main__":
    main()
