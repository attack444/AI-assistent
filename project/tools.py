# tools.py — agent tools + Ollama tools schema (Windows-first)
from __future__ import annotations

import json
import os
import platform
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
    "Windows", "System32", "$Recycle.Bin", ".cache", "tmp", "temp",
}

_IS_WINDOWS = platform.system() == "Windows"


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _log(tool: str, args: Dict, result: Dict) -> None:
    AGENT_LOG.parent.mkdir(parents=True, exist_ok=True)
    with AGENT_LOG.open("a", encoding="utf-8") as f:
        f.write(json.dumps({
            "ts": datetime.now().isoformat(),
            "tool": tool,
            "args": {k: str(v)[:200] for k, v in args.items()},
            "ok": result.get("ok", False),
        }, ensure_ascii=False) + "\n")


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


def _run(cmd: str | list, cwd: str | None = None, timeout: int = 60,
         encoding: str = "utf-8", shell: bool = True) -> tuple[int, str]:
    try:
        proc = subprocess.run(
            cmd, shell=shell, cwd=cwd,
            capture_output=True, text=True,
            timeout=timeout, encoding=encoding, errors="replace",
        )
        out = ((proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")).strip()
        return proc.returncode, out[:10_000]
    except subprocess.TimeoutExpired:
        return -1, f"Timeout ({timeout}s)"
    except Exception as exc:
        return -1, str(exc)


# ---------------------------------------------------------------------------
# File tools
# ---------------------------------------------------------------------------

def read_file(path: str) -> Dict[str, Any]:
    """Читает файл целиком. Для больших файлов — первые 100 KB."""
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if not p.is_file():
            r: Dict[str, Any] = {"ok": False, "error": f"Файл не найден: {p}"}
        else:
            text = p.read_text(encoding="utf-8", errors="ignore")
            truncated = len(text) > 100_000
            if truncated:
                text = text[:100_000] + "\n...[обрезано до 100 KB]"
            r = {"ok": True, "path": str(p), "content": text,
                 "lines": text.count("\n") + 1, "truncated": truncated}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("read_file", args, r)
    return r


def read_file_lines(path: str, start: int = 1, end: int = 200) -> Dict[str, Any]:
    """Читает диапазон строк файла (start..end включительно)."""
    args = {"path": path, "start": start, "end": end}
    try:
        p = Path(path).expanduser().resolve()
        if not p.is_file():
            r: Dict[str, Any] = {"ok": False, "error": f"Файл не найден: {p}"}
        else:
            lines = p.read_text(encoding="utf-8", errors="ignore").splitlines()
            total = len(lines)
            s, e = max(1, start) - 1, min(end, total)
            chunk = "\n".join(f"{i+s+1}: {l}" for i, l in enumerate(lines[s:e]))
            r = {"ok": True, "path": str(p), "content": chunk,
                 "from_line": s + 1, "to_line": e, "total_lines": total}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("read_file_lines", args, r)
    return r


def write_file(path: str, content: str) -> Dict[str, Any]:
    """Перезаписывает файл. Автоматический бэкап перед записью."""
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
    """Создаёт новый файл. Ошибка если уже существует — используй write_file."""
    args = {"path": path}
    try:
        p = Path(path).expanduser().resolve()
        if p.exists():
            r: Dict[str, Any] = {"ok": False, "error": f"Файл уже существует: {p}. Используй write_file."}
        else:
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(content, encoding="utf-8")
            r = {"ok": True, "path": str(p), "created": True}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("create_file", args, r)
    return r


def delete_file(path: str) -> Dict[str, Any]:
    """Удаляет файл или папку. Бэкап перед удалением автоматический."""
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


def list_dir(path: str, recursive: bool = False, extensions: str = "") -> Dict[str, Any]:
    """
    Список файлов в директории.
    extensions — фильтр по расширениям через запятую, напр. '.py,.js'
    При recursive=True возвращает до 1000 файлов (пропуская node_modules и пр.)
    """
    args = {"path": path, "recursive": recursive, "extensions": extensions}
    try:
        p = Path(path).expanduser().resolve()
        if not p.exists():
            r: Dict[str, Any] = {"ok": False, "error": f"Не найдено: {p}"}
        elif not p.is_dir():
            r = {"ok": False, "error": f"Не директория: {p}"}
        else:
            exts = {e.strip().lower() for e in extensions.split(",") if e.strip()} if extensions else set()
            items: List[str] = []
            dirs_count = 0
            files_count = 0

            if recursive:
                for item in sorted(p.rglob("*")):
                    if len(items) >= 1000:
                        break
                    if any(s in item.parts for s in _SKIP_DIRS):
                        continue
                    rel = str(item.relative_to(p)).replace("\\", "/")
                    if item.is_dir():
                        items.append(rel + "/")
                        dirs_count += 1
                    else:
                        if not exts or item.suffix.lower() in exts:
                            try:
                                size = item.stat().st_size
                                items.append(f"{rel}  ({size} bytes)")
                            except OSError:
                                items.append(rel)
                            files_count += 1
            else:
                for item in sorted(p.iterdir()):
                    if len(items) >= 500:
                        break
                    if item.is_dir():
                        items.append(item.name + "/")
                        dirs_count += 1
                    else:
                        if not exts or item.suffix.lower() in exts:
                            try:
                                size = item.stat().st_size
                                items.append(f"{item.name}  ({size} bytes)")
                            except OSError:
                                items.append(item.name)
                            files_count += 1

            r = {"ok": True, "path": str(p), "items": items,
                 "files": files_count, "dirs": dirs_count, "total": len(items)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("list_dir", args, r)
    return r


def find_files(pattern: str, root: str, max_depth: int = 6) -> Dict[str, Any]:
    """
    Ищет файлы по имени/маске (glob) в дереве папок.
    pattern: напр. '*.py', 'config.*', 'README*'
    """
    args = {"pattern": pattern, "root": root}
    try:
        p = Path(root).expanduser().resolve()
        found: List[str] = []

        def _walk(d: Path, depth: int) -> None:
            if depth > max_depth or len(found) >= 200:
                return
            try:
                for item in d.iterdir():
                    if any(s == item.name for s in _SKIP_DIRS):
                        continue
                    if item.is_file() and item.match(pattern):
                        try:
                            size = item.stat().st_size
                            found.append(f"{item}  ({size} bytes)")
                        except OSError:
                            found.append(str(item))
                    elif item.is_dir():
                        _walk(item, depth + 1)
            except (PermissionError, OSError):
                pass

        _walk(p, 0)
        r: Dict[str, Any] = {"ok": True, "found": found, "count": len(found)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("find_files", args, r)
    return r


def search_code(query: str, root: str) -> Dict[str, Any]:
    """Ищет паттерн по коду проекта. Возвращает файл:строка:текст."""
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
                        if len(results) >= 60:
                            break
            except Exception:
                pass
            if len(results) >= 60:
                break
        r: Dict[str, Any] = {"ok": True, "results": results, "count": len(results)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("search_code", args, r)
    return r


# ---------------------------------------------------------------------------
# Command / PowerShell tools
# ---------------------------------------------------------------------------

def run_command(command: str, cwd: Optional[str] = None, timeout: int = 60) -> Dict[str, Any]:
    """Выполняет команду cmd/bash. На Windows использует cmd.exe."""
    args = {"command": command, "cwd": cwd}
    code, out = _run(command, cwd=cwd, timeout=timeout)
    r: Dict[str, Any] = {"ok": code == 0, "output": out, "returncode": code}
    _log("run_command", args, r)
    return r


def run_powershell(script: str, cwd: Optional[str] = None, timeout: int = 60) -> Dict[str, Any]:
    """
    Запускает PowerShell-скрипт на Windows.
    Предпочтительнее run_command для работы с Windows API, реестром, переменными.
    """
    args = {"script": script[:200], "cwd": cwd}
    if not _IS_WINDOWS:
        r: Dict[str, Any] = {"ok": False, "error": "PowerShell доступен только на Windows"}
        _log("run_powershell", args, r)
        return r
    try:
        cmd = [
            "powershell", "-NoProfile", "-NonInteractive",
            "-ExecutionPolicy", "Bypass",
            "-OutputFormat", "Text",
            "-Command", script,
        ]
        proc = subprocess.run(
            cmd, cwd=cwd, capture_output=True, timeout=timeout,
            encoding="utf-8", errors="replace",
        )
        out = ((proc.stdout or "") + ("\n" + proc.stderr if proc.stderr else "")).strip()
        r = {"ok": proc.returncode == 0, "output": out[:10_000], "returncode": proc.returncode}
    except subprocess.TimeoutExpired:
        r = {"ok": False, "error": f"Timeout ({timeout}s)", "output": ""}
    except FileNotFoundError:
        r = {"ok": False, "error": "PowerShell не найден", "output": ""}
    except Exception as exc:
        r = {"ok": False, "error": str(exc), "output": ""}
    _log("run_powershell", args, r)
    return r


def run_tests(project_root: str, cmd: Optional[str] = None) -> Dict[str, Any]:
    """Запускает тесты в проекте (pytest по умолчанию)."""
    return run_command(cmd or "pytest -q", cwd=project_root, timeout=300)


# ---------------------------------------------------------------------------
# Windows environment & system tools
# ---------------------------------------------------------------------------

def get_env_var(name: str) -> Dict[str, Any]:
    """Читает переменную среды Windows."""
    args = {"name": name}
    value = os.environ.get(name)
    r: Dict[str, Any] = {
        "ok": value is not None,
        "name": name,
        "value": value,
        "error": f"Переменная '{name}' не найдена" if value is None else None,
    }
    _log("get_env_var", args, r)
    return r


def set_env_var(name: str, value: str, permanent: bool = True) -> Dict[str, Any]:
    """
    Устанавливает переменную среды Windows.
    permanent=True — сохраняется для пользователя через setx (после перезапуска терминала).
    permanent=False — только для текущей сессии.
    """
    args = {"name": name, "value": value, "permanent": permanent}
    os.environ[name] = value
    r: Dict[str, Any] = {"ok": True, "name": name, "value": value, "permanent": False}

    if permanent and _IS_WINDOWS:
        code, out = _run(f'setx {name} "{value}"', timeout=15)
        r["permanent"] = code == 0
        if code != 0:
            r["setx_error"] = out

    _log("set_env_var", args, r)
    return r


def get_windows_info() -> Dict[str, Any]:
    """Информация о системе Windows: версия, RAM, CPU, диски, Python."""
    args: Dict = {}
    try:
        import sys
        info: Dict[str, Any] = {
            "os": platform.system(),
            "version": platform.version(),
            "release": platform.release(),
            "machine": platform.machine(),
            "python": sys.version,
            "username": os.environ.get("USERNAME", os.environ.get("USER", "?")),
            "userprofile": os.environ.get("USERPROFILE", str(Path.home())),
            "drives": [],
            "ram_mb": None,
            "cpu": platform.processor(),
        }

        if _IS_WINDOWS:
            # Drives
            code, out = _run("wmic logicaldisk get Caption,Size,FreeSpace /format:csv", timeout=10)
            if code == 0:
                for line in out.splitlines():
                    parts = [p.strip() for p in line.split(",")]
                    if len(parts) >= 4 and parts[1]:
                        try:
                            free_gb = round(int(parts[2]) / 1e9, 1) if parts[2] else 0
                            total_gb = round(int(parts[3]) / 1e9, 1) if parts[3] else 0
                            info["drives"].append(
                                f"{parts[1]}  {free_gb} GB free / {total_gb} GB total"
                            )
                        except (ValueError, IndexError):
                            pass

            # RAM
            code2, out2 = _run("wmic computersystem get TotalPhysicalMemory /format:csv", timeout=10)
            if code2 == 0:
                for line in out2.splitlines():
                    parts = [p.strip() for p in line.split(",")]
                    if len(parts) >= 2 and parts[-1].isdigit():
                        info["ram_mb"] = round(int(parts[-1]) / 1e6)
                        break
        r: Dict[str, Any] = {"ok": True, **info}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("get_windows_info", args, r)
    return r


def get_processes(filter: str = "") -> Dict[str, Any]:
    """
    Список запущенных процессов Windows.
    filter — фильтр по имени (регистронезависимо), напр. 'ollama', 'python'
    """
    args = {"filter": filter}
    try:
        if _IS_WINDOWS:
            code, out = _run("tasklist /fo csv /nh", timeout=15)
        else:
            code, out = _run("ps aux", timeout=15)

        procs: List[str] = []
        pat = re.compile(re.escape(filter), re.IGNORECASE) if filter else None
        for line in out.splitlines():
            if not line.strip():
                continue
            if pat and not pat.search(line):
                continue
            procs.append(line.strip().strip('"').replace('","', " | "))

        r: Dict[str, Any] = {"ok": code == 0, "processes": procs[:100], "count": len(procs)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("get_processes", args, r)
    return r


def kill_process(name_or_pid: str) -> Dict[str, Any]:
    """Завершает процесс по имени или PID. Только Windows."""
    args = {"name_or_pid": name_or_pid}
    if not _IS_WINDOWS:
        r: Dict[str, Any] = {"ok": False, "error": "Только Windows"}
        _log("kill_process", args, r)
        return r
    try:
        if name_or_pid.isdigit():
            code, out = _run(f"taskkill /PID {name_or_pid} /F", timeout=10)
        else:
            code, out = _run(f'taskkill /IM "{name_or_pid}" /F', timeout=10)
        r = {"ok": code == 0, "output": out}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("kill_process", args, r)
    return r


def open_in_explorer(path: str) -> Dict[str, Any]:
    """Открывает файл или папку в Проводнике Windows."""
    args = {"path": path}
    if not _IS_WINDOWS:
        r: Dict[str, Any] = {"ok": False, "error": "Только Windows"}
        _log("open_in_explorer", args, r)
        return r
    try:
        p = Path(path).expanduser().resolve()
        if p.is_file():
            # Открыть папку и выделить файл
            subprocess.Popen(["explorer", "/select,", str(p)])
        else:
            subprocess.Popen(["explorer", str(p)])
        r: Dict[str, Any] = {"ok": True, "opened": str(p)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("open_in_explorer", args, r)
    return r


def open_file(path: str) -> Dict[str, Any]:
    """Открывает файл стандартной программой Windows (как двойной клик)."""
    args = {"path": path}
    if not _IS_WINDOWS:
        r: Dict[str, Any] = {"ok": False, "error": "Только Windows"}
        _log("open_file", args, r)
        return r
    try:
        p = Path(path).expanduser().resolve()
        os.startfile(str(p))
        r: Dict[str, Any] = {"ok": True, "opened": str(p)}
    except Exception as exc:
        r = {"ok": False, "error": str(exc)}
    _log("open_file", args, r)
    return r


# ---------------------------------------------------------------------------
# Web & project tools
# ---------------------------------------------------------------------------

def web_search(query: str, max_results: int = 5) -> Dict[str, Any]:
    """Поиск в интернете через DuckDuckGo."""
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
    """Сканирует диск в поисках git-репозиториев и проектов."""
    MARKERS = {
        ".git", "package.json", "pyproject.toml", "Cargo.toml",
        "go.mod", "pom.xml", "requirements.txt", "composer.json",
        "*.sln", "*.csproj", "Makefile",
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
    """Сохраняет факт, правило или предпочтение в долгосрочную память."""
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
            "description": (
                "Читает содержимое файла. "
                "ВСЕГДА используй этот инструмент вместо того чтобы просить пользователя показать код."
            ),
            "parameters": {
                "type": "object",
                "properties": {"path": {"type": "string", "description": "Абсолютный путь к файлу"}},
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "read_file_lines",
            "description": "Читает диапазон строк файла (start..end). Используй для больших файлов.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "start": {"type": "integer", "description": "Первая строка (1-based)", "default": 1},
                    "end": {"type": "integer", "description": "Последняя строка", "default": 200},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "write_file",
            "description": "Перезаписывает файл. Автоматический бэкап перед записью.",
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
            "description": "Создаёт новый файл. Ошибка если уже существует — тогда используй write_file.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "content": {"type": "string", "default": ""},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "delete_file",
            "description": "Удаляет файл или папку. Автобэкап перед удалением.",
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
            "description": (
                "Список файлов и папок. "
                "Используй recursive=true для полного сканирования проекта. "
                "ВСЕГДА вызывай этот инструмент когда пользователь спрашивает о файлах в папке."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "recursive": {"type": "boolean", "default": False,
                                  "description": "true — рекурсивно по всем подпапкам"},
                    "extensions": {"type": "string", "default": "",
                                   "description": "Фильтр по расширениям через запятую: '.py,.js'"},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "find_files",
            "description": "Ищет файлы по имени/маске (glob) в дереве папок. Пример: '*.py', 'config.*'",
            "parameters": {
                "type": "object",
                "properties": {
                    "pattern": {"type": "string", "description": "Glob-маска: '*.py', '*.env', 'README*'"},
                    "root": {"type": "string", "description": "Папка для поиска"},
                    "max_depth": {"type": "integer", "default": 6},
                },
                "required": ["pattern", "root"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "search_code",
            "description": "Ищет текст/паттерн по всем файлам проекта. Возвращает файл:строка:текст.",
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
            "description": "Выполняет команду в cmd/bash. Возвращает stdout+stderr.",
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
            "name": "run_powershell",
            "description": (
                "Запускает PowerShell-скрипт на Windows. "
                "Предпочтительнее run_command для: переменных среды, реестра, процессов, файловой системы Windows."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "script": {"type": "string", "description": "PowerShell код"},
                    "cwd": {"type": "string", "description": "Рабочая директория"},
                    "timeout": {"type": "integer", "default": 60},
                },
                "required": ["script"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_env_var",
            "description": "Читает переменную среды Windows (OLLAMA_MODELS, PATH, USERPROFILE и т.д.)",
            "parameters": {
                "type": "object",
                "properties": {"name": {"type": "string", "description": "Имя переменной"}},
                "required": ["name"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "set_env_var",
            "description": "Устанавливает переменную среды Windows. permanent=true — сохраняется навсегда через setx.",
            "parameters": {
                "type": "object",
                "properties": {
                    "name": {"type": "string"},
                    "value": {"type": "string"},
                    "permanent": {"type": "boolean", "default": True},
                },
                "required": ["name", "value"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_windows_info",
            "description": "Информация о Windows: версия ОС, RAM, CPU, список дисков со свободным местом.",
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_processes",
            "description": "Список запущенных процессов Windows. filter — фильтр по имени.",
            "parameters": {
                "type": "object",
                "properties": {
                    "filter": {"type": "string", "default": "",
                               "description": "Часть имени процесса для фильтрации, напр. 'ollama'"},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "kill_process",
            "description": "Завершает процесс Windows по имени или PID.",
            "parameters": {
                "type": "object",
                "properties": {
                    "name_or_pid": {"type": "string", "description": "Имя процесса (ollama.exe) или PID"}
                },
                "required": ["name_or_pid"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "open_in_explorer",
            "description": "Открывает папку или выделяет файл в Проводнике Windows.",
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
            "name": "open_file",
            "description": "Открывает файл стандартной программой Windows (как двойной клик).",
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
            "name": "run_tests",
            "description": "Запускает тесты в проекте (pytest по умолчанию).",
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
            "description": "Поиск в интернете через DuckDuckGo.",
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
            "description": "Сканирует диск в поисках git-репозиториев и проектов.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string", "description": "Путь: 'D:\\' или 'C:\\Users\\Вячеслав'"},
                },
                "required": ["path"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "save_memory",
            "description": "Сохраняет факт, правило или предпочтение в долгосрочную память.",
            "parameters": {
                "type": "object",
                "properties": {
                    "content": {"type": "string"},
                    "type": {"type": "string", "enum": ["fact", "preference", "rule", "project"],
                             "default": "fact"},
                    "project": {"type": "string", "default": ""},
                },
                "required": ["content"],
            },
        },
    },
]

TOOL_FUNCTIONS: Dict[str, Any] = {
    "read_file": read_file,
    "read_file_lines": read_file_lines,
    "write_file": write_file,
    "create_file": create_file,
    "delete_file": delete_file,
    "list_dir": list_dir,
    "find_files": find_files,
    "search_code": search_code,
    "run_command": run_command,
    "run_powershell": run_powershell,
    "get_env_var": get_env_var,
    "set_env_var": set_env_var,
    "get_windows_info": get_windows_info,
    "get_processes": get_processes,
    "kill_process": kill_process,
    "open_in_explorer": open_in_explorer,
    "open_file": open_file,
    "run_tests": run_tests,
    "web_search": web_search,
    "scan_for_projects": scan_for_projects,
    "save_memory": save_memory,
}
