# agent.py — ReAct agent with smart routing for fast responses
# Uses raw HTTP to bypass Pydantic validation bug in ollama client.
from __future__ import annotations

import inspect
import json
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, Generator, List, Optional

from memory import MemoryStore
from profile import UserProfile
from tools import TOOL_FUNCTIONS, TOOLS_SCHEMA, resolve_workspace_path

MAX_STEPS = 12
SELF_DIR  = Path(__file__).resolve().parent
_IS_LINUX = __import__("platform").system() != "Windows"


@dataclass
class AgentEvent:
    type: str        # text | tool_call | tool_result | error | done
    content: str = ""
    tool_name: str = ""
    tool_args: Dict[str, Any] = field(default_factory=dict)
    tool_result: Dict[str, Any] = field(default_factory=dict)


# ---------------------------------------------------------------------------
# Smart query routing
# ---------------------------------------------------------------------------

# Keywords that indicate the agent MUST use tools
_TOOL_TRIGGERS = frozenset([
    "файл", "прочитай", "открой", "создай", "удали", "запиши", "папк",
    "директор", "список файл", "покажи файл", "посмотри", "проверь",
    "исправь", "измени", "замени", "отредактируй", "редактир", "поправ",
    "добавь", "вставь", "убери", "html", "css", "php", "js", "index.",
    "что не так", "проблем", "сайт", "wordpress", "wp-", "белый экран",
    "siteurl", "права", "permission", "nginx", "mysql", "базу",
    "переимен", "скопир", "перемес",
    "git", "коммит", "ветка", "diff", "пуш", "пул", "stash",
    "запусти", "команд", "powershell", "terminal", "тест", "pytest",
    "процесс", "диск", "переменн", ".exe", "реестр",
    "поиск", "найди в", "web search", "загугли",
    "зависимост", "npm", "pip install", "requirements",
    "сканир", "проект", "индекс",
    "буфер", "clipboard", "скопируй в буфер",
    "уведомлен",
    "улучши себя", "обнови модель", "бэкап", "бекап",
    "read_file", "write_file", "list_dir", "str_replace", "apply_edits",
])

# Tool names grouped by category — only relevant ones sent per request
_TOOLS_FILE    = {"read_file", "read_file_lines", "write_file", "str_replace",
                  "create_file", "delete_file", "mkdir_path", "copy_path",
                  "move_path", "apply_edits", "list_dir", "find_files"}
_TOOLS_CODE    = {"search_code", "format_code", "str_replace", "diff_preview", "apply_edits"}
_TOOLS_HOST    = {"site_status", "wp_replace_urls", "site_fix_perms",
                  "flatten_site_layout", "php_lint", "nginx_test"}
_TOOLS_GIT     = {"git_run", "diff_preview"}
_TOOLS_CMD     = {"run_command", "run_powershell", "run_tests"}
_TOOLS_WIN     = {"get_env_var", "set_env_var", "get_windows_info",
                  "get_processes", "kill_process", "open_in_explorer", "open_file"}
_TOOLS_WEB     = {"web_search"}
_TOOLS_PROJ    = {"scan_for_projects", "check_deps"}
_TOOLS_MEM     = {"save_memory"}
_TOOLS_CLIP    = {"clipboard_get", "clipboard_set"}
_TOOLS_NOTIFY  = {"notify_windows"}
_TOOLS_SELF    = {"apply_self_improvement", "self_update_check",
                  "search_better_models", "self_code_analyze"}

_CATEGORY_KEYWORDS: List[tuple[frozenset[str], frozenset[str]]] = [
    (frozenset(["файл", "прочитай", "открой", "создай", "удали", "запиши",
                "папк", "директор", "список", "дерев", "покажи файл",
                "измени", "замени", "редактир", "поправ", "добавь",
                "переимен", "скопир", "перемес", "html", "css", "php",
                "сайт"]), _TOOLS_FILE | _TOOLS_CODE),
    (frozenset(["код", "исправь", "найди в коде", "форматир", "pylint",
                "ошибк в коде", "что не так", "str_replace", "apply_edits"]),
     _TOOLS_CODE | _TOOLS_FILE),
    (frozenset(["wordpress", "wp-", "siteurl", "белый экран", "mysql",
                "базу", "права", "permission", "nginx", "public_html",
                "хостинг", "домен"]), _TOOLS_HOST | _TOOLS_FILE),
    (frozenset(["git", "коммит", "ветка", "diff", "пуш", "пул", "stash",
                "merge", "rebase"]),                                      _TOOLS_GIT),
    (frozenset(["команд", "запусти", "powershell", "terminal",
                "тест", "pytest", "npm run", "python "]),                _TOOLS_CMD),
    (frozenset(["процесс", "диск", "переменн", "env", "реестр",
                "windows", "sistema", "система"]),                       _TOOLS_WIN),
    (frozenset(["поиск", "найди в инт", "web", "google", "что такое",
                "загугли", "в интернете"]),                              _TOOLS_WEB),
    (frozenset(["проект", "сканир", "зависимост", "npm", "pip",
                "requirements", "package.json"]),                        _TOOLS_PROJ),
    (frozenset(["запомни", "сохрани в память", "помни"]),               _TOOLS_MEM),
    (frozenset(["буфер", "clipboard", "скопируй в буфер"]),             _TOOLS_CLIP),
    (frozenset(["уведомлен", "notify"]),                                 _TOOLS_NOTIFY),
    (frozenset(["улучши себя", "обнови модель", "бэкап кода",
                "самообновл", "self_code"]),                             _TOOLS_SELF),
]

# Pre-build schema index for fast lookup
_SCHEMA_BY_NAME: Dict[str, Dict] = {s["function"]["name"]: s for s in TOOLS_SCHEMA}


def _needs_tools(text: str) -> bool:
    lower = text.lower()
    return any(kw in lower for kw in _TOOL_TRIGGERS)


def _select_tools(text: str, *, force_workspace: bool = False) -> List[Dict[str, Any]]:
    """Return only tools relevant to this query — fewer tokens = faster."""
    lower = text.lower()
    selected: set[str] = set()

    for keywords, tool_names in _CATEGORY_KEYWORDS:
        if any(kw in lower for kw in keywords):
            selected |= tool_names

    # Always include save_memory so agent can remember things
    selected.add("save_memory")

    if force_workspace:
        # Site/project chat: file + hosting tools always available
        selected |= _TOOLS_FILE | _TOOLS_CODE | _TOOLS_HOST | {"run_command"}

    if not selected:
        # Fallback: minimal set for unknown complex queries
        selected = _TOOLS_FILE | _TOOLS_CODE | {"web_search", "save_memory"}

    # On Linux VPS / site workspace — drop Windows-only noise
    if _IS_LINUX or force_workspace:
        selected -= _TOOLS_WIN | _TOOLS_CLIP | _TOOLS_NOTIFY | {"run_powershell"}

    return [_SCHEMA_BY_NAME[n] for n in selected if n in _SCHEMA_BY_NAME]


# ---------------------------------------------------------------------------
# Groq API helpers (OpenAI-compatible, blazing fast)
# ---------------------------------------------------------------------------

GROQ_API_URL    = "https://api.groq.com/openai/v1/chat/completions"
GROQ_DEFAULT_MODEL = "llama-3.3-70b-versatile"

# DeepSeek (OpenAI-compatible, best for code)
DEEPSEEK_API_URL   = "https://api.deepseek.com/chat/completions"
DEEPSEEK_DEFAULT_MODEL = "deepseek-chat"

# OpenAI-compatible providers — mapped by key prefix or explicit name
_PROVIDER_URLS: Dict[str, str] = {
    "groq":      GROQ_API_URL,
    "deepseek":  DEEPSEEK_API_URL,
    "together":  "https://api.together.xyz/v1/chat/completions",
    "openrouter": "https://openrouter.ai/api/v1/chat/completions",
    "openai":    "https://api.openai.com/v1/chat/completions",
}

def _api_url_for_model(model: str, explicit_url: str = "") -> str:
    """Return the correct API URL based on model name prefix."""
    if explicit_url:
        return explicit_url
    m = model.lower()
    if m.startswith("deepseek"):   return DEEPSEEK_API_URL
    if m.startswith("gpt"):        return _PROVIDER_URLS["openai"]
    if m.startswith("together"):   return _PROVIDER_URLS["together"]
    return GROQ_API_URL   # default


def _groq_headers(api_key: str) -> Dict[str, str]:
    return {"Content-Type": "application/json", "Authorization": f"Bearer {api_key}"}


def _make_opener(proxy: str = "") -> "urllib.request.OpenerDirector":
    """Build urllib opener with optional HTTP proxy."""
    import urllib.request as _ur
    if proxy.strip():
        handler = _ur.ProxyHandler({"http": proxy, "https": proxy})
        return _ur.build_opener(handler)
    return _ur.build_opener()


class GroqAuthError(Exception):
    """Raised when Groq returns 401/403 — key invalid or revoked."""


def _groq_check_error(exc: Exception) -> Exception:
    """Convert urllib HTTP errors to more helpful exceptions."""
    import urllib.error as _ue
    if isinstance(exc, _ue.HTTPError):
        if exc.code == 401:
            return GroqAuthError(
                "Groq API: ключ недействителен (401). "
                "Создай новый на console.groq.com и введи в настройках."
            )
        if exc.code == 403:
            return GroqAuthError(
                "Groq API: доступ запрещён (403). "
                "Вероятно, ключ был скомпрометирован или отозван. "
                "Создай новый на console.groq.com → API Keys."
            )
        if exc.code == 429:
            return Exception(
                "Groq API: превышен лимит запросов (429). "
                "Подожди минуту или перейди на платный тариф."
            )
        if exc.code >= 500:
            return Exception(f"Groq API: ошибка сервера ({exc.code}). Попробуй позже.")
    return exc


def _groq_stream(
    api_key: str,
    model: str,
    messages: List[Dict[str, Any]],
    max_tokens: int = 768,
    temperature: float = 0.1,
    timeout: float = 60.0,
    proxy: str = "",
    _api_url: str = "",
) -> Generator[str, None, None]:
    """Stream text from Groq API. Much faster than local Ollama."""
    body = json.dumps({
        "model": model,
        "messages": messages,
        "stream": True,
        "temperature": temperature,
        "max_tokens": max_tokens,
    }, ensure_ascii=False).encode("utf-8")
    url = _api_url or GROQ_API_URL
    req = urllib.request.Request(
        url, data=body, method="POST",
        headers=_groq_headers(api_key),
    )
    try:
        resp_cm = _make_opener(proxy).open(req, timeout=timeout)
    except Exception as exc:
        raise _groq_check_error(exc) from exc
    with resp_cm as resp:
        for raw_line in resp:
            line = raw_line.decode("utf-8").strip()
            if not line or line == "data: [DONE]":
                continue
            if line.startswith("data: "):
                try:
                    chunk = json.loads(line[6:])
                    txt = chunk["choices"][0].get("delta", {}).get("content") or ""
                    if txt:
                        yield txt
                except (json.JSONDecodeError, KeyError, IndexError):
                    continue


def _groq_chat(
    api_key: str,
    model: str,
    messages: List[Dict[str, Any]],
    tools: Optional[List[Dict[str, Any]]] = None,
    max_tokens: int = 2048,
    temperature: float = 0.05,
    timeout: float = 60.0,
    proxy: str = "",
    _api_url: str = "",
) -> Dict[str, Any]:
    """Non-streaming Groq call (for tool-calling step). Returns our internal format."""
    payload: Dict[str, Any] = {
        "model":       model,
        "messages":    messages,
        "stream":      False,
        "temperature": temperature,
        "max_tokens":  max_tokens,
    }
    if tools:
        payload["tools"]       = tools
        payload["tool_choice"] = "auto"

    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    url  = _api_url or GROQ_API_URL
    req  = urllib.request.Request(
        url, data=body, method="POST",
        headers=_groq_headers(api_key),
    )
    try:
        resp_cm = _make_opener(proxy).open(req, timeout=timeout)
    except Exception as exc:
        raise _groq_check_error(exc) from exc
    with resp_cm as resp:
        data = json.loads(resp.read().decode("utf-8"))

    choice = data["choices"][0]
    msg    = choice.get("message", {})

    # Normalise to our internal message format (same as Ollama response)
    result: Dict[str, Any] = {
        "message": {
            "content":    msg.get("content") or "",
            "tool_calls": [],
        }
    }
    for tc in (msg.get("tool_calls") or []):
        fn = tc.get("function", {})
        result["message"]["tool_calls"].append({
            "id":       tc.get("id", ""),
            "function": {
                "name":      fn.get("name", ""),
                "arguments": fn.get("arguments", {}),
            },
        })
    return result


def _groq_stream_agent_summary(
    api_key: str,
    model: str,
    messages: List[Dict[str, Any]],
) -> Generator[str, None, None]:
    """Stream the final Groq response after tool calls."""
    yield from _groq_stream(api_key, model, messages, max_tokens=1024)


# ---------------------------------------------------------------------------
# Ollama Raw HTTP helpers
# ---------------------------------------------------------------------------

def _raw_chat(
    host: str,
    model: str,
    messages: List[Dict[str, Any]],
    options: Dict[str, Any],
    tools: Optional[List[Dict[str, Any]]] = None,
    timeout: float = 180.0,
) -> Dict[str, Any]:
    """POST /api/chat (stream=False). Returns raw parsed JSON dict."""
    url  = f"{host.rstrip('/')}/api/chat"
    body: Dict[str, Any] = {
        "model": model, "messages": messages,
        "stream": False, "options": options,
    }
    if tools:
        body["tools"] = tools
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req  = urllib.request.Request(
        url, data=data, method="POST",
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8"))


def _stream_chat(
    host: str,
    model: str,
    messages: List[Dict[str, Any]],
    options: Dict[str, Any],
    timeout: float = 180.0,
) -> Generator[str, None, None]:
    """POST /api/chat (stream=True). Yields text chunks as they arrive."""
    url  = f"{host.rstrip('/')}/api/chat"
    body = json.dumps(
        {"model": model, "messages": messages, "stream": True, "options": options},
        ensure_ascii=False,
    ).encode("utf-8")
    req  = urllib.request.Request(
        url, data=body, method="POST",
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        for raw_line in resp:
            line = raw_line.decode("utf-8").strip()
            if not line:
                continue
            try:
                chunk = json.loads(line)
                txt   = chunk.get("message", {}).get("content", "")
                if txt:
                    yield txt
                if chunk.get("done", False):
                    break
            except (json.JSONDecodeError, KeyError):
                continue


def _parse_args(raw: Any) -> Dict[str, Any]:
    if isinstance(raw, dict):
        return raw
    if isinstance(raw, str):
        try:
            parsed = json.loads(raw)
            return parsed if isinstance(parsed, dict) else {}
        except (json.JSONDecodeError, ValueError):
            return {}
    return {}


def _extract_tool_calls(raw_msg: Dict[str, Any]) -> List[Dict[str, Any]]:
    calls = []
    for i, tc in enumerate(raw_msg.get("tool_calls", []) or []):
        fn   = tc.get("function", {})
        name = fn.get("name", "").strip()
        if not name:
            continue
        calls.append({
            "id":        tc.get("id", f"call_{i}"),
            "name":      name,
            "arguments": _parse_args(fn.get("arguments", {})),
        })
    return calls


# ---------------------------------------------------------------------------
# System prompts — short for fast mode, full for agent mode
# ---------------------------------------------------------------------------

_SKIP_DIRS = frozenset([
    "node_modules", "__pycache__", ".venv", "venv", ".git",
    "dist", "build", ".next", "out",
])

def _project_snapshot(project_root: Optional[Path], max_files: int = 40) -> str:
    """
    Compact snapshot of the project: file tree + recent content hints.
    Injected into ALL prompts so the AI always knows the project structure.
    """
    if not project_root or not project_root.exists():
        return ""
    lines: List[str] = [f"Проект: {project_root.name}  ({project_root})"]
    files_found: List[str] = []
    try:
        for item in sorted(project_root.rglob("*")):
            if any(s in item.parts for s in _SKIP_DIRS):
                continue
            if item.is_file():
                rel = str(item.relative_to(project_root)).replace("\\", "/")
                try:
                    sz = item.stat().st_size
                    files_found.append(f"  {rel}  ({sz} B)")
                except OSError:
                    files_found.append(f"  {rel}")
            if len(files_found) >= max_files:
                files_found.append("  ...[ещё файлы]")
                break
    except Exception:
        pass
    if files_found:
        lines.append("Файлы:")
        lines.extend(files_found)
    return "\n".join(lines)


def _fast_prompt(
    profile: UserProfile,
    project_root: Optional[Path],
    mem_ctx: str,
) -> str:
    snapshot = _project_snapshot(project_root)
    return "\n".join(filter(None, [
        f"Ты — AI-ассистент программиста. Пользователь: {profile.name}.",
        "Отвечай кратко, конкретно, по-русски. Код — в ```блоках```.",
        snapshot,
        mem_ctx.strip() or "",
    ])).strip()


def build_system_prompt(
    profile: UserProfile,
    memory: MemoryStore,
    project_root: Optional[Path],
    query: str,
) -> str:
    proj_name = project_root.name if project_root else "нет"
    proj_path = str(project_root) if project_root else "нет"
    mem_ctx   = memory.get_context(query, project=str(project_root) if project_root else "")
    langs     = ", ".join(profile.preferred_languages) if profile.preferred_languages else "любые"
    confirm   = "без подтверждения" if not profile.confirm_before_apply else "с подтверждением"
    rules_txt = ("\nПравила:\n" + "\n".join(f"- {r}" for r in profile.rules)) if profile.rules else ""
    snapshot  = _project_snapshot(project_root, max_files=50)
    site_card = ""
    if project_root:
        try:
            from hosting_tools import build_site_card
            site_card = build_site_card(project_root)
        except Exception:
            site_card = ""

    workspace_rules = ""
    if project_root:
        workspace_rules = f"""
РАБОЧАЯ ПАПКА САЙТА (на сервере): {proj_path}
- Пути относительные: index.html, wp-config.php, wp-content/...
- Правки РЕАЛЬНЫЕ на диске: str_replace / apply_edits / write_file
- Несколько файлов сразу → apply_edits
- Проблемы сайта → site_status; URL WP → wp_replace_urls; права → site_fix_perms
- Вложенный public_html → flatten_site_layout
- PHP синтаксис → php_lint
- Не выходи за пределы рабочей папки
"""

    return f"""Ты — автономный AI-ассистент владельца VPS. Пользователь: {profile.name}.
Стиль: {profile.style} | Изменения: {confirm}
Активный сайт/проект: {proj_name} ({proj_path}) | Языки: {langs}
{rules_txt}
{workspace_rules}

ПРАВИЛА:
- НИКОГДА не проси показать код — читай сам через read_file / list_dir
- Действуй сразу инструментами, не описывай план вместо действия
- Частичные правки: str_replace или apply_edits; новые файлы: create_file/write_file
- Кратко: факт → действие → результат
- Пиши по-русски

{site_card}

{snapshot}

{mem_ctx}""".strip()


# ---------------------------------------------------------------------------
# Tool dispatch
# ---------------------------------------------------------------------------

_PATH_ARG_KEYS: Dict[str, tuple[str, ...]] = {
    "read_file": ("path",),
    "read_file_lines": ("path",),
    "write_file": ("path",),
    "str_replace": ("path",),
    "create_file": ("path",),
    "delete_file": ("path",),
    "mkdir_path": ("path",),
    "copy_path": ("src", "dst"),
    "move_path": ("src", "dst"),
    "list_dir": ("path",),
    "find_files": ("root",),
    "search_code": ("root",),
    "diff_preview": ("path",),
    "format_code": ("path",),
    "php_lint": ("path",),
    "site_status": ("path",),
    "wp_replace_urls": ("path",),
    "site_fix_perms": ("path",),
    "flatten_site_layout": ("path",),
    "git_run": ("repo_path",),
    "run_tests": ("project_root",),
    "check_deps": ("project_path",),
    "github_create_pr": ("repo_path",),
    "github_pr_list": ("repo_path",),
    "github_issue_list": ("repo_path",),
}


def _dispatch(
    name: str,
    args: Dict[str, Any],
    project_root: Optional[Path],
    memory: MemoryStore,
) -> Dict[str, Any]:
    args = dict(args or {})

    _default_dot = {
        "list_dir", "site_status", "site_fix_perms", "flatten_site_layout", "wp_replace_urls",
    }
    if name in _default_dot and not str(args.get("path") or "").strip() and project_root:
        args["path"] = "."
    if name == "find_files" and not str(args.get("root") or "").strip() and project_root:
        args["root"] = "."
    if name == "search_code" and not str(args.get("root") or "").strip() and project_root:
        args["root"] = str(project_root)
    if name == "run_tests" and not str(args.get("project_root") or "").strip() and project_root:
        args["project_root"] = str(project_root)
    if name == "run_command" and not str(args.get("cwd") or "").strip() and project_root:
        args["cwd"] = str(project_root)
    if name == "run_powershell" and not str(args.get("cwd") or "").strip() and project_root:
        args["cwd"] = str(project_root)
    if name == "git_run" and not str(args.get("repo_path") or "").strip() and project_root:
        args["repo_path"] = str(project_root)
    if name == "check_deps" and not str(args.get("project_path") or "").strip() and project_root:
        args["project_path"] = str(project_root)

    # Sandbox paths inside apply_edits before calling
    if name == "apply_edits" and project_root:
        raw_edits = args.get("edits")
        if isinstance(raw_edits, str):
            try:
                raw_edits = json.loads(raw_edits)
            except Exception:
                pass
        if isinstance(raw_edits, list):
            fixed = []
            for item in raw_edits:
                if not isinstance(item, dict):
                    fixed.append(item)
                    continue
                item = dict(item)
                try:
                    item["path"] = str(resolve_workspace_path(str(item.get("path") or ""), project_root))
                except Exception as exc:
                    return {"ok": False, "error": str(exc), "tool": "apply_edits"}
                fixed.append(item)
            args["edits"] = fixed

    # Sandbox + resolve relative paths into the site workspace
    for key in _PATH_ARG_KEYS.get(name, ()):
        raw = args.get(key)
        if raw is None or (isinstance(raw, str) and not raw.strip()):
            if name in _default_dot and project_root and key == "path":
                raw = "."
            else:
                continue
        try:
            resolved = resolve_workspace_path(
                str(raw),
                project_root,
                default_to_root=(name in _default_dot),
            )
            args[key] = str(resolved)
        except Exception as exc:
            return {"ok": False, "error": str(exc), "tool": name, "path": str(raw)}

    if name == "run_command" and project_root and args.get("cwd"):
        try:
            args["cwd"] = str(resolve_workspace_path(str(args["cwd"]), project_root))
        except Exception as exc:
            return {"ok": False, "error": str(exc)}

    if name == "save_memory":
        entry = memory.add(
            content=args.get("content", ""),
            type=args.get("type", "fact"),
            project=args.get("project", str(project_root) if project_root else ""),
        )
        return {"ok": True, "saved": entry.content, "id": entry.id}

    fn = TOOL_FUNCTIONS.get(name)
    if fn is None:
        return {"ok": False, "error": f"Неизвестный инструмент: {name}"}

    sig   = inspect.signature(fn)
    valid = set(sig.parameters.keys())
    return fn(**{k: v for k, v in args.items() if k in valid})


# ---------------------------------------------------------------------------
# Main agent loop with smart routing
# ---------------------------------------------------------------------------

def run_agent(
    user_message: str,
    chat_history: List[Dict[str, str]],
    project_root: Optional[Path],
    profile: UserProfile,
    memory: MemoryStore,
    llm_model: str,
    ollama_host: str,
    context_window: int = 8192,
    fast_llm_model: str = "",
    groq_api_key: str = "",
    groq_model: str = GROQ_DEFAULT_MODEL,
    deepseek_api_key: str = "",
    deepseek_model: str = DEEPSEEK_DEFAULT_MODEL,
    http_proxy: str = "",
) -> Generator[AgentEvent, None, None]:
    """
    Four-tier routing (priority order):

    🚀 DEEPSEEK  (key set): best for code, cheap, OpenAI-compatible
    ☁️ GROQ      (key set): fast, free tier, good for chat
    ⚡ LOCAL FAST (no tools): small local model, minimal prompt
    🤖 LOCAL AGENT (tools): full local model, ReAct loop
    """
    agent_model = llm_model
    chat_model  = fast_llm_model.strip() or llm_model
    # With an active site/project workspace — always enable file tools
    use_tools   = _needs_tools(user_message) or bool(project_root)
    force_ws    = bool(project_root)

    # ── FREE LOCAL (Ollama) first — когда LLM_PREFER_FREE=1 ──────────────────
    skip_cloud = False
    try:
        import free_llm as _free
        if _free.prefer_free():
            free_name = _free.free_model(fast_llm_model, llm_model)
            st = _free.check_ollama(ollama_host, free_name)
            if st.get("reachable") and st.get("has_model"):
                used_model = st.get("model") or free_name
                mem_ctx0 = memory.get_context(
                    user_message, project=str(project_root) if project_root else ""
                )
                if not use_tools:
                    sys_msg0 = _fast_prompt(profile, project_root, mem_ctx0)
                    messages0: List[Dict[str, Any]] = [{"role": "system", "content": sys_msg0}]
                    for msg in chat_history[-6:]:
                        messages0.append({"role": msg["role"], "content": msg["content"]})
                    messages0.append({"role": "user", "content": user_message})
                    yield AgentEvent(type="info", content=f"free:{used_model}")
                    try:
                        for chunk in _free.stream_ollama(
                            messages0, host=ollama_host, model=used_model
                        ):
                            yield AgentEvent(type="text", content=chunk)
                        yield AgentEvent(type="done")
                        return
                    except Exception as exc:
                        yield AgentEvent(type="info", content=f"free_fallback:{exc}")
                else:
                    # tools → локальный agent на бесплатной модели
                    chat_model = used_model
                    agent_model = used_model
                    skip_cloud = True
                    yield AgentEvent(type="info", content=f"free-agent:{used_model}")
    except Exception:
        skip_cloud = False

    # ── DeepSeek PATH (paid / fallback) ──────────────────────────────────────
    if deepseek_api_key.strip() and not skip_cloud:
        mem_ctx = memory.get_context(user_message, project=str(project_root) if project_root else "")
        if not use_tools:
            sys_msg  = _fast_prompt(profile, project_root, mem_ctx)
            messages: List[Dict[str, Any]] = [{"role": "system", "content": sys_msg}]
            for msg in chat_history[-6:]:
                messages.append({"role": msg["role"], "content": msg["content"]})
            messages.append({"role": "user", "content": user_message})
            yield AgentEvent(type="info", content=f"deepseek:{deepseek_model}")
            try:
                for chunk in _groq_stream(
                    deepseek_api_key, deepseek_model, messages,
                    proxy=http_proxy, _api_url=DEEPSEEK_API_URL
                ):
                    yield AgentEvent(type="text", content=chunk)
            except GroqAuthError as exc:
                yield AgentEvent(type="error", content=str(exc))
            except Exception as exc:
                yield AgentEvent(type="error", content=f"DeepSeek: {exc}")
            yield AgentEvent(type="done")
            return
        else:
            # DeepSeek agent path
            relevant_tools = _select_tools(user_message, force_workspace=force_ws)
            system_prompt  = build_system_prompt(profile, memory, project_root, user_message)
            messages = [{"role": "system", "content": system_prompt}]
            for msg in chat_history[-8:]:
                messages.append({"role": msg["role"], "content": msg["content"]})
            messages.append({"role": "user", "content": user_message})
            yield AgentEvent(type="info", content=f"deepseek-agent:{deepseek_model}")
            steps = 0
            tool_calls_made = False
            while steps < MAX_STEPS:
                steps += 1
                try:
                    raw_resp = _groq_chat(
                        deepseek_api_key, deepseek_model, messages,
                        tools=relevant_tools, proxy=http_proxy, _api_url=DEEPSEEK_API_URL
                    )
                except GroqAuthError as exc:
                    yield AgentEvent(type="error", content=str(exc)); return
                except Exception as exc:
                    yield AgentEvent(type="error", content=f"DeepSeek: {exc}"); return
                raw_msg    = raw_resp.get("message", {}) or {}
                content    = (raw_msg.get("content") or "").strip()
                tool_calls = _extract_tool_calls(raw_msg)
                if tool_calls:
                    tool_calls_made = True
                    messages.append({"role": "assistant", "content": content, "tool_calls": [
                        {"id": tc["id"], "type": "function",
                         "function": {"name": tc["name"],
                                      "arguments": json.dumps(tc["arguments"], ensure_ascii=False)}}
                        for tc in tool_calls
                    ]})
                    for tc in tool_calls:
                        yield AgentEvent(type="tool_call", tool_name=tc["name"], tool_args=tc["arguments"])
                        result = _dispatch(tc["name"], dict(tc["arguments"]), project_root, memory)
                        yield AgentEvent(type="tool_result", tool_name=tc["name"], tool_result=result)
                        messages.append({"role": "tool", "tool_call_id": tc["id"],
                                         "content": json.dumps(result, ensure_ascii=False)[:6000]})
                    continue
                if content:
                    for i in range(0, len(content), 8):
                        yield AgentEvent(type="text", content=content[i:i+8])
                elif tool_calls_made:
                    try:
                        for chunk in _groq_stream(deepseek_api_key, deepseek_model, messages,
                                                  max_tokens=1024, proxy=http_proxy,
                                                  _api_url=DEEPSEEK_API_URL):
                            yield AgentEvent(type="text", content=chunk)
                    except Exception as exc:
                        yield AgentEvent(type="error", content=f"DeepSeek stream: {exc}")
                yield AgentEvent(type="done")
                return
            yield AgentEvent(type="error", content=f"Превышен лимит шагов ({MAX_STEPS}).")
            return

    mem_ctx = memory.get_context(user_message, project=str(project_root) if project_root else "")

    # ── GROQ PATH ────────────────────────────────────────────────────────────
    if groq_api_key.strip() and not use_tools and not skip_cloud:
        sys_msg  = _fast_prompt(profile, project_root, mem_ctx)
        messages: List[Dict[str, Any]] = [{"role": "system", "content": sys_msg}]
        for msg in chat_history[-6:]:
            messages.append({"role": msg["role"], "content": msg["content"]})
        messages.append({"role": "user", "content": user_message})

        yield AgentEvent(type="info", content=f"groq:{groq_model}")
        try:
            for chunk in _groq_stream(groq_api_key, groq_model, messages, proxy=http_proxy):
                yield AgentEvent(type="text", content=chunk)
        except GroqAuthError as exc:
            # Auth error — show clear message, DO NOT silently fall back
            yield AgentEvent(type="error", content=str(exc))
        except Exception as exc:
            # Other Groq error → fall back to local fast model silently
            yield AgentEvent(type="info", content=f"groq_fallback:{chat_model}")
            fast_opts = {"temperature": 0.1, "num_ctx": 4096, "num_predict": 768, "top_k": 20}
            sys_msg2  = _fast_prompt(profile, project_root, mem_ctx)
            msgs2: List[Dict[str, Any]] = [{"role": "system", "content": sys_msg2}]
            for m in chat_history[-6:]:
                msgs2.append({"role": m["role"], "content": m["content"]})
            msgs2.append({"role": "user", "content": user_message})
            try:
                for chunk in _stream_chat(ollama_host, chat_model, msgs2, fast_opts):
                    yield AgentEvent(type="text", content=chunk)
            except Exception as exc2:
                yield AgentEvent(type="error", content=f"Groq недоступен: {exc} | Ollama: {exc2}")
        yield AgentEvent(type="done")
        return

    # ── GROQ AGENT PATH (Groq key set + tools needed) ────────────────────────
    if groq_api_key.strip() and use_tools and not skip_cloud:
        relevant_tools = _select_tools(user_message, force_workspace=force_ws)
        system_prompt  = build_system_prompt(profile, memory, project_root, user_message)
        messages = [{"role": "system", "content": system_prompt}]
        for msg in chat_history[-8:]:
            messages.append({"role": msg["role"], "content": msg["content"]})
        messages.append({"role": "user", "content": user_message})

        yield AgentEvent(type="info", content=f"groq-agent:{groq_model}")

        steps = 0
        tool_calls_made = False
        while steps < MAX_STEPS:
            steps += 1
            try:
                raw_resp = _groq_chat(groq_api_key, groq_model, messages, proxy=http_proxy,
                                      tools=relevant_tools)
            except GroqAuthError as exc:
                yield AgentEvent(type="error", content=str(exc))
                return
            except Exception as exc:
                yield AgentEvent(type="error", content=f"Groq ошибка: {exc}")
                return

            raw_msg    = raw_resp.get("message", {}) or {}
            content    = (raw_msg.get("content") or "").strip()
            tool_calls = _extract_tool_calls(raw_msg)

            if tool_calls:
                tool_calls_made = True
                messages.append({
                    "role": "assistant", "content": content,
                    "tool_calls": [
                        {"id": tc["id"], "type": "function",
                         "function": {"name": tc["name"],
                                      "arguments": json.dumps(tc["arguments"], ensure_ascii=False)}}
                        for tc in tool_calls
                    ],
                })
                for tc in tool_calls:
                    yield AgentEvent(type="tool_call", tool_name=tc["name"], tool_args=tc["arguments"])
                    result = _dispatch(tc["name"], dict(tc["arguments"]), project_root, memory)
                    yield AgentEvent(type="tool_result", tool_name=tc["name"], tool_result=result)
                    messages.append({
                        "role": "tool", "tool_call_id": tc["id"],
                        "content": json.dumps(result, ensure_ascii=False)[:6000],
                    })
                continue

            if content:
                for i in range(0, len(content), 8):
                    yield AgentEvent(type="text", content=content[i:i+8])
            elif tool_calls_made:
                try:
                    for chunk in _groq_stream(groq_api_key, groq_model, messages, max_tokens=1024, proxy=http_proxy):
                        yield AgentEvent(type="text", content=chunk)
                except Exception as exc:
                    yield AgentEvent(type="error", content=f"Groq stream: {exc}")
            yield AgentEvent(type="done")
            return

        yield AgentEvent(type="error", content=f"Превышен лимит шагов ({MAX_STEPS}).")
        return

    # ── LOCAL FAST PATH (no Groq, no tools) ──────────────────────────────────
    if not use_tools:
        fast_opts = {
            "temperature": 0.1,
            "num_ctx":     min(4096, context_window),
            "num_predict": 768,
            "top_k":       20,
            "top_p":       0.9,
        }
        sys_msg  = _fast_prompt(profile, project_root, mem_ctx)
        messages = [{"role": "system", "content": sys_msg}]
        for msg in chat_history[-6:]:
            messages.append({"role": msg["role"], "content": msg["content"]})
        messages.append({"role": "user", "content": user_message})

        yield AgentEvent(type="info", content=f"fast:{chat_model}")
        try:
            for chunk in _stream_chat(ollama_host, chat_model, messages, fast_opts):
                yield AgentEvent(type="text", content=chunk)
        except Exception as exc:
            yield AgentEvent(type="error", content=f"Ollama: {exc}")
        yield AgentEvent(type="done")
        return

    # ── LOCAL AGENT PATH (tools needed, no Groq) ─────────────────────────────
    agent_opts = {
        "temperature": 0.05,
        "num_ctx":     context_window,
        "num_predict": 2048,
        "top_k":       40,
        "top_p":       0.95,
    }
    relevant_tools = _select_tools(user_message, force_workspace=force_ws)

    yield AgentEvent(type="info", content=f"agent:{agent_model}")

    system_prompt = build_system_prompt(profile, memory, project_root, user_message)
    messages = [{"role": "system", "content": system_prompt}]
    for msg in chat_history[-8:]:
        messages.append({"role": msg["role"], "content": msg["content"]})
    messages.append({"role": "user", "content": user_message})

    steps           = 0
    tool_calls_made = False

    while steps < MAX_STEPS:
        steps += 1

        try:
            raw_resp = _raw_chat(
                host=ollama_host, model=agent_model,
                messages=messages, options=agent_opts,
                tools=relevant_tools,
            )
        except Exception as exc:
            yield AgentEvent(type="error", content=f"Ollama ({ollama_host}): {exc}")
            return

        raw_msg    = raw_resp.get("message", {}) or {}
        content    = (raw_msg.get("content") or "").strip()
        tool_calls = _extract_tool_calls(raw_msg)

        if tool_calls:
            tool_calls_made = True
            messages.append({
                "role": "assistant",
                "content": content,
                "tool_calls": [
                    {
                        "id": tc["id"], "type": "function",
                        "function": {
                            "name": tc["name"],
                            "arguments": json.dumps(tc["arguments"], ensure_ascii=False),
                        },
                    }
                    for tc in tool_calls
                ],
            })

            for tc in tool_calls:
                yield AgentEvent(type="tool_call", tool_name=tc["name"], tool_args=tc["arguments"])
                result = _dispatch(tc["name"], dict(tc["arguments"]), project_root, memory)
                yield AgentEvent(type="tool_result", tool_name=tc["name"], tool_result=result)
                messages.append({
                    "role": "tool",
                    "tool_call_id": tc["id"],
                    "content": json.dumps(result, ensure_ascii=False)[:6000],
                })
            continue

        # ── Final answer ────────────────────────────────────────────────────
        if content:
            for i in range(0, len(content), 8):
                yield AgentEvent(type="text", content=content[i:i+8])
        elif tool_calls_made:
            # Tools ran but no final text — stream a fresh summary
            try:
                for chunk in _stream_chat(ollama_host, agent_model, messages, agent_opts):
                    yield AgentEvent(type="text", content=chunk)
            except Exception as exc:
                yield AgentEvent(type="error", content=f"Stream error: {exc}")

        yield AgentEvent(type="done")
        return

    yield AgentEvent(
        type="error",
        content=f"Превышен лимит шагов ({MAX_STEPS}). Раздели задачу на части.",
    )
