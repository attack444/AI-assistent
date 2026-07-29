# tools.py — all agent tools + Ollama tools schema
from __future__ import annotations

import json
import re
import shutil
import subprocess
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

AGENT_LOG = Path.home() / ".ai-helper" / "agent.log"

_SKIP_DIRS = {
    "node_modules", "__pycache__", ".venv", "venv", "env",
    ".git", "dist", "build", "out", ".next", "AppData",
    "Windows", "System32", "$Recycle.Bin",
}


# ---------------------------------------------------------------------------
# Internal helpers
# ---------------------------------------------------------------------------

def _log(tool: str, args: Dict, result: Dict) -> None:
    AGENT_LOG.parent.mkdir(parents=True, exist_ok=True)
    with AGENT_LOG.open("a", encoding="utf-8") as f:
        f.write(
            json.dumps(
                {
                    "ts": datetime.now().isoformat(),
                    "tool": tool,
                    "args": {k: str(v)[:200] for k, v in args.items()},
                    "ok": result.get("ok", False),
                },
                ensure_ascii=False,
            )
            + "\n"
        )


def _backup(path: Path) -> Optional[str]:
    try:
        from core import BACKUPS_DIR

        ts = datetime.now().strftime("%Y%m%d_%H%M%S_%f")
        bd = BACKUPS_DIR / "agent" / ts
        bd.mkdir(parents=True, exist_ok=True)
        dst = bd / path.name
        shutil.copy2(str(path), str(dst))
        return str(dst)
    except Exception:
        return None


# ---------------------------------------------------------------------------
# Tool functions
# ---------------------------------------------------------------------------

def read_file(path: str) -> Dict[str, Any]:
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if not p.is_file():
            r: Dict[str, Any] = {"ok": False, "error": f"Не найден: {p}"}
        else:
            text = p.read_text(encoding="utf-8", errors="ignore")
            if len(text) > 100_000:
                text = text[:100_000] + "\n...[обрезано до 100 KB]"
            r = {"ok": True, "content": text, "path": str(p), "lines": text.count("\n") + 1}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("read_file", args, r)
    return r


def write_file(path: str, content: str) -> Dict[str, Any]:
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if p.exists():
            _backup(p)
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(content, encoding="utf-8")
        r: Dict[str, Any] = {"ok": True, "path": str(p), "bytes": len(content.encode())}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("write_file", args, r)
    return r


def create_file(path: str, content: str = "") -> Dict[str, Any]:
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if p.exists():
            r: Dict[str, Any] = {"ok": False, "error": f"Файл существует. Используй write_file: {p}"}
        else:
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(content, encoding="utf-8")
            r = {"ok": True, "path": str(p), "created": True}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("create_file", args, r)
    return r


def delete_file(path: str) -> Dict[str, Any]:
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if not p.exists():
            r: Dict[str, Any] = {"ok": False, "error": f"Не найдено: {p}"}
        else:
            backup = _backup(p) if p.is_file() else None
            if p.is_dir():
                shutil.rmtree(p)
            else:
                p.unlink()
            r = {"ok": True, "deleted": str(p), "backup": backup}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("delete_file", args, r)
    return r


def list_dir(path: str, recursive: bool = False) -> Dict[str, Any]:
    args = {"path": path, "recursive": recursive}
    try:
        p = Path(path).expanduser().resolve()
        if not p.exists():
            r: Dict[str, Any] = {"ok": False, "error": f"Не найдено: {p}"}
        elif not p.is_dir():
            r = {"ok": False, "error": f"Не директория: {p}"}
        else:
            items: List[str] = []
            if recursive:
                for item in sorted(p.rglob("*"))[:300]:
                    if not any(s in item.parts for s in _SKIP_DIRS):
                        items.append(str(item.relative_to(p)) + ("/" if item.is_dir() else ""))
            else:
                for item in sorted(p.iterdir())[:200]:
                    items.append(item.name + ("/" if item.is_dir() else ""))
            r = {"ok": True, "path": str(p), "items": items, "count": len(items)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("list_dir", args, r)
    return r


def search_code(query: str, root: str) -> Dict[str, Any]:
    args = {"query": query, "root": root}
    try:
        from core import iter_project_files

        p = Path(root).expanduser().resolve()
        results: List[str] = []
        pattern = re.compile(re.escape(query), re.IGNORECASE)
        for f in iter_project_files(p):
            try:
                for i, line in enumerate(
                    f.read_text(encoding="utf-8", errors="ignore").splitlines(), 1
                ):
                    if pattern.search(line):
                        rel = f.relative_to(p)
                        results.append(f"{rel}:{i}: {line.strip()[:120]}")
                        if len(results) >= 30:
                            break
            except Exception:
                pass
            if len(results) >= 30:
                break
        r: Dict[str, Any] = {"ok": True, "results": results, "count": len(results)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("search_code", args, r)
    return r


def run_command(command: str, cwd: Optional[str] = None, timeout: int = 60) -> Dict[str, Any]:
    args = {"command": command, "cwd": cwd}
    try:
        proc = subprocess.run(
            command,
            shell=True,
            cwd=cwd,
            capture_output=True,
            text=True,
            timeout=timeout,
            encoding="utf-8",
            errors="replace",
        )
        out = ((proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")).strip()
        r: Dict[str, Any] = {
            "ok": proc.returncode == 0,
            "output": out[:8000],
            "returncode": proc.returncode,
        }
    except subprocess.TimeoutExpired:
        r = {"ok": False, "error": f"Timeout {timeout}s", "output": ""}
    except Exception as exc:
        r = {"ok": False, "error": str(exc), "output": ""}
    _log("run_command", args, r)
    return r


def run_tests(project_root: str, cmd: Optional[str] = None) -> Dict[str, Any]:
    return run_command(cmd or "pytest -q", cwd=project_root, timeout=300)


def web_search(query: str, max_results: int = 5) -> Dict[str, Any]:
    args = {"query": query}
    try:
        from core import format_web_results, web_search_text

        results = web_search_text(query, max_results=max_results)
        r: Dict[str, Any] = {"ok": True, "output": format_web_results(results), "results": results}
    except Exception as exc:
        r = {"ok": False, "error": str(exc), "output": ""}
    _log("web_search", args, r)
    return r


def scan_for_projects(path: str, max_depth: int = 4) -> Dict[str, Any]:
    MARKERS = {
        ".git", "package.json", "pyproject.toml", "Cargo.toml",
        "go.mod", "pom.xml", "requirements.txt", "composer.json",
    }
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        found: List[Dict[str, Any]] = []

        def _scan(d: Path, depth: int) -> None:
            if depth > max_depth or len(found) >= 60:
                return
            try:
                entries = list(d.iterdir())
            except (PermissionError, OSError):
                return
            names = {e.name for e in entries}
            hit = names & MARKERS
            if hit:
                found.append({"path": str(d), "name": d.name, "markers": sorted(hit)})
                return
            for e in entries:
                if e.is_dir() and e.name not in _SKIP_DIRS and not e.name.startswith("."):
                    _scan(e, depth + 1)

        _scan(p, 0)
        r: Dict[str, Any] = {"ok": True, "projects": found, "count": len(found)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("scan_for_projects", args, r)
    return r


def save_memory(content: str, type: str = "fact", project: str = "") -> Dict[str, Any]:
    # Actual saving is done by agent.py via MemoryStore; this stub is for tool dispatch
    r: Dict[str, Any] = {"ok": True, "saved": content[:200], "type": type}
    _log("save_memory", {"content": content[:100], "type": type}, r)
    return r


# ---------------------------------------------------------------------------
# Ollama tools schema
# ---------------------------------------------------------------------------

TOOLS_SCHEMA: List[Dict[str, Any]] = [
    {
        "type": "function",
        "function": {
            "name": "read_file",
            "description": "Читает содержимое файла по пути",
            "parameters": {
                "type": "object",
                "properties": {"path": {"type": "string", "description": "Путь к файлу"}},
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "write_file",
            "description": "Записывает (перезаписывает) файл. Бэкап создаётся автоматически.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "content": {"type": "string", "description": "Полное содержимое файла"},
                },
                "required": ["path", "content"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "create_file",
            "description": "Создаёт новый файл. Ошибка если файл уже существует.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "content": {"type": "string"},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "delete_file",
            "description": "Удаляет файл или папку. Бэкап перед удалением автоматический.",
            "parameters": {
                "type": "object",
                "properties": {"path": {"type": "string"}},
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "list_dir",
            "description": "Список файлов и папок в директории",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "recursive": {"type": "boolean", "default": False},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "search_code",
            "description": "Ищет паттерн по коду проекта. Возвращает файл:строка:текст.",
            "parameters": {
                "type": "object",
                "properties": {
                    "query": {"type": "string"},
                    "root": {"type": "string", "description": "Корневая папка проекта"},
                },
                "required": ["query", "root"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "run_command",
            "description": "Выполняет команду в терминале. Возвращает stdout+stderr.",
            "parameters": {
                "type": "object",
                "properties": {
                    "command": {"type": "string"},
                    "cwd": {"type": "string", "description": "Рабочая директория"},
                    "timeout": {"type": "integer", "default": 60},
                },
                "required": ["command"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "run_tests",
            "description": "Запускает тесты в проекте",
            "parameters": {
                "type": "object",
                "properties": {
                    "project_root": {"type": "string"},
                    "cmd": {"type": "string", "description": "Команда (по умолчанию pytest -q)"},
                },
                "required": ["project_root"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "web_search",
            "description": "Поиск в интернете через DuckDuckGo",
            "parameters": {
                "type": "object",
                "properties": {"query": {"type": "string"}},
                "required": ["query"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "scan_for_projects",
            "description": "Сканирует диск в поисках git-репозиториев и проектов",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string", "description": "Путь для сканирования, например D:\\ или C:\\Users\\User"}
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "save_memory",
            "description": "Сохраняет факт, правило или предпочтение в долгосрочную память",
            "parameters": {
                "type": "object",
                "properties": {
                    "content": {"type": "string"},
                    "type": {
                        "type": "string",
                        "enum": ["fact", "preference", "rule", "project"],
                        "default": "fact",
                    },
                    "project": {"type": "string"},
                },
                "required": ["content"],
            },
        },
    },
]

# Dispatch map: tool name → function
TOOL_FUNCTIONS: Dict[str, Any] = {
    "read_file": read_file,
    "write_file": write_file,
    "create_file": create_file,
    "delete_file": delete_file,
    "list_dir": list_dir,
    "search_code": search_code,
    "run_command": run_command,
    "run_tests": run_tests,
    "web_search": web_search,
    "scan_for_projects": scan_for_projects,
    "save_memory": save_memory,
}
