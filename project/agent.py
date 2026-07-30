# agent.py — ReAct agent loop
# Uses raw HTTP for tool-calling to bypass Pydantic validation bug:
# some Ollama versions return tool_call arguments as JSON string instead of dict,
# which makes the ollama Python client crash before we can handle it.
from __future__ import annotations

import inspect
import json
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, Generator, List, Optional

from memory import MemoryStore
from profile import UserProfile
from tools import TOOL_FUNCTIONS, TOOLS_SCHEMA

MAX_STEPS = 8

# Directory of this file — used to tell agent where its own source code lives
SELF_DIR = Path(__file__).resolve().parent


@dataclass
class AgentEvent:
    type: str   # text | tool_call | tool_result | error | info | done
    content: str = ""
    tool_name: str = ""
    tool_args: Dict[str, Any] = field(default_factory=dict)
    tool_result: Dict[str, Any] = field(default_factory=dict)


# ---------------------------------------------------------------------------
# Raw HTTP helpers — no Pydantic, no surprises
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
    url = f"{host.rstrip('/')}/api/chat"
    body: Dict[str, Any] = {
        "model": model,
        "messages": messages,
        "stream": False,
        "options": options,
    }
    if tools:
        body["tools"] = tools
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(
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
    """POST /api/chat (stream=True). Yields text chunks."""
    url = f"{host.rstrip('/')}/api/chat"
    body = json.dumps(
        {"model": model, "messages": messages, "stream": True, "options": options},
        ensure_ascii=False,
    ).encode("utf-8")
    req = urllib.request.Request(
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
                txt = chunk.get("message", {}).get("content", "")
                if txt:
                    yield txt
                if chunk.get("done", False):
                    break
            except (json.JSONDecodeError, KeyError):
                continue


def _parse_args(raw: Any) -> Dict[str, Any]:
    """Parse tool arguments — handle both dict and JSON-string forms."""
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
    """Extract tool calls from raw Ollama message dict."""
    calls = []
    for i, tc in enumerate(raw_msg.get("tool_calls", []) or []):
        fn = tc.get("function", {})
        name = fn.get("name", "").strip()
        if not name:
            continue
        calls.append({
            "id": tc.get("id", f"call_{i}"),
            "name": name,
            "arguments": _parse_args(fn.get("arguments", {})),
        })
    return calls


# ---------------------------------------------------------------------------
# System prompt
# ---------------------------------------------------------------------------

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

    return f"""Ты — автономный AI-ассистент программиста на Windows. Пользователь: {profile.name}.
Стиль: {profile.style} | Verbosity: {profile.verbosity} | Изменения: {confirm}
Активный проект: {proj_name} ({proj_path})
Языки: {langs}
{rules_txt}

═══ ПРАВИЛА АВТОНОМНОСТИ (ОБЯЗАТЕЛЬНО) ═══

НИКОГДА не проси пользователя показать код, файл, фрагмент или вывод — читай сам:
  • Спрашивают «что не так с файлами?» → вызови list_dir(path, recursive=true), потом read_file
  • Спрашивают «посмотри на мой код» → найди файл через list_dir/find_files, прочитай read_file
  • Нужно проверить ошибку → прочитай лог/файл сам, не жди от пользователя
  • Не знаешь путь → используй find_files или list_dir от корня проекта
  • Нужна информация о системе → get_windows_info, get_env_var, get_processes

АЛГОРИТМ при запросе «что не так / почему не работает»:
  1. list_dir(project_path, recursive=true) — посмотреть всю структуру
  2. read_file(file) — читать нужные файлы
  3. Найти проблему → сразу write_file с исправлением
  4. Ответить: что нашёл, что исправил, строка файла

АЛГОРИТМ при запросе о Windows/системе:
  1. get_windows_info() — диски, RAM, версия
  2. get_env_var(name) — проверить переменные среды
  3. run_powershell(script) — для системных операций (предпочти cmd)
  4. get_processes(filter) — найти запущенные программы

После write_file/create_file — ВСЕГДА одной строкой: «Исправил X в file.py:строка»
При ошибке — причина + исправление сразу, без вопросов
Замечаешь предпочтение пользователя → save_memory
Пиши по-русски. Кратко: факт → действие → результат.

═══ САМОЭВОЛЮЦИЯ И ОБНОВЛЕНИЕ ═══
Исходный код: {SELF_DIR}
Файлы: app.py, core.py, agent.py, tools.py, memory.py, profile.py, launcher.py, self_update.py

АЛГОРИТМ самоулучшения (когда просят улучшить/оптимизировать себя):
  1. self_code_analyze(aspect) — анализируй свой код (aspect: performance/reliability/features/ui/tools/windows)
  2. Изучи результат, предложи конкретное изменение
  3. diff_preview(file, new_content) — покажи diff до применения
  4. apply_self_improvement(file, new_content, reason) — применяй ТОЛЬКО этим инструментом
     (он делает бэкап всех файлов + откат при ошибке компиляции)
  5. Сообщи что изменил и почему

АЛГОРИТМ обновления модели:
  1. self_update_check() — проверить обновления Ollama + зависимости
  2. Если устарела — результат содержит updated=True / outdated packages
  3. search_better_models(current_model) — найти лучшие модели в интернете
  4. Предложить пользователю перейти если найдена лучшая модель

ПРАВИЛА самомодификации:
  - НИКОГДА не используй write_file для изменения собственных файлов ассистента
  - ТОЛЬКО apply_self_improvement — он валидирует код и откатывает при ошибке
  - Одно изменение за раз, проверяй компиляцию
  - Сохраняй причину изменения в reason-параметре

{mem_ctx}""".strip()


# ---------------------------------------------------------------------------
# Tool dispatch
# ---------------------------------------------------------------------------

def _dispatch(
    name: str,
    args: Dict[str, Any],
    project_root: Optional[Path],
    memory: MemoryStore,
) -> Dict[str, Any]:
    # Fill implicit defaults
    if name == "search_code"    and "root"         not in args and project_root:
        args["root"]          = str(project_root)
    if name == "run_tests"      and "project_root" not in args and project_root:
        args["project_root"]  = str(project_root)
    if name == "run_command"    and "cwd"          not in args and project_root:
        args["cwd"]           = str(project_root)
    if name == "run_powershell" and "cwd"          not in args and project_root:
        args["cwd"]           = str(project_root)

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

    sig = inspect.signature(fn)
    valid = set(sig.parameters.keys())
    return fn(**{k: v for k, v in args.items() if k in valid})


# ---------------------------------------------------------------------------
# Main agent loop
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
) -> Generator[AgentEvent, None, None]:
    """
    ReAct loop. Yields AgentEvent objects.
    Uses raw HTTP to avoid Pydantic validation errors on tool_call arguments.
    """
    system_prompt = build_system_prompt(profile, memory, project_root, user_message)
    options       = {"temperature": 0.05, "num_ctx": context_window}

    messages: List[Dict[str, Any]] = [{"role": "system", "content": system_prompt}]
    for msg in chat_history[-20:]:
        messages.append({"role": msg["role"], "content": msg["content"]})
    messages.append({"role": "user", "content": user_message})

    steps = 0
    tool_calls_made = False

    while steps < MAX_STEPS:
        steps += 1

        # ── Tool-calling step: raw HTTP, handles string arguments ────────────
        try:
            raw_resp = _raw_chat(
                host=ollama_host,
                model=llm_model,
                messages=messages,
                options=options,
                tools=TOOLS_SCHEMA,
            )
        except Exception as exc:
            yield AgentEvent(type="error", content=f"Ollama ({ollama_host}): {exc}")
            return

        raw_msg     = raw_resp.get("message", {}) or {}
        content     = (raw_msg.get("content") or "").strip()
        tool_calls  = _extract_tool_calls(raw_msg)

        if tool_calls:
            tool_calls_made = True

            # Add assistant turn with tool_calls to history
            messages.append({
                "role": "assistant",
                "content": content,
                "tool_calls": [
                    {
                        "id": tc["id"],
                        "type": "function",
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

            # Continue loop — let model decide next step
            continue

        # ── Final text response ──────────────────────────────────────────────
        if tool_calls_made and not content:
            # Tools were used but model returned empty — stream a fresh summary
            try:
                for chunk in _stream_chat(ollama_host, llm_model, messages, options):
                    yield AgentEvent(type="text", content=chunk)
            except Exception as exc:
                yield AgentEvent(type="error", content=f"Stream error: {exc}")
        elif tool_calls_made and content:
            # Stream fresh answer so model uses tool results properly
            messages.append({"role": "assistant", "content": ""})
            messages.pop()  # remove temp
            try:
                for chunk in _stream_chat(ollama_host, llm_model, messages, options):
                    yield AgentEvent(type="text", content=chunk)
            except Exception:
                # Fallback: use content from non-streaming call
                for i in range(0, len(content), 6):
                    yield AgentEvent(type="text", content=content[i:i+6])
        else:
            # No tools at all — fake-stream the content (avoids a second API call)
            for i in range(0, len(content), 6):
                yield AgentEvent(type="text", content=content[i:i+6])

        yield AgentEvent(type="done")
        return

    yield AgentEvent(
        type="error",
        content=f"Превышен лимит шагов ({MAX_STEPS}). Раздели задачу на части.",
    )
