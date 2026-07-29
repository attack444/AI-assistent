# agent.py — ReAct agent loop with Ollama tool calling
from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, Generator, List, Optional

from memory import MemoryStore
from profile import UserProfile
from tools import TOOL_FUNCTIONS, TOOLS_SCHEMA

MAX_STEPS = 12


@dataclass
class AgentEvent:
    type: str   # text | tool_call | tool_result | error | info | done
    content: str = ""
    tool_name: str = ""
    tool_args: Dict[str, Any] = field(default_factory=dict)
    tool_result: Dict[str, Any] = field(default_factory=dict)


# ---------------------------------------------------------------------------
# System prompt builder
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
    confirm   = "без подтверждения — применяй сразу" if not profile.confirm_before_apply else "спрашивай подтверждение"
    rules_txt = ("\nПравила пользователя:\n" + "\n".join(f"- {r}" for r in profile.rules)) if profile.rules else ""

    prompt = f"""Ты — персональный AI-ассистент программиста. Пользователь: {profile.name}.

Стиль: {profile.style}
Verbosity: {profile.verbosity}
Изменения файлов: {confirm}
Активный проект: {proj_name} ({proj_path})
Языки: {langs}
{rules_txt}

Инструкции:
- Кратко: факт → действие → результат. Без "конечно", "отлично", "давайте".
- Ссылайся на файл:строка при каждом изменении.
- Действуй — не спрашивай разрешения (если confirm=False).
- Не знаешь — используй web_search или search_code, не выдумывай.
- После write_file/create_file: одной строкой что сделал.
- При ошибке: причина + исправление немедленно.
- Обнаружил факт / предпочтение пользователя — сохрани в save_memory.
- Пиши по-русски.
{mem_ctx}""".strip()

    return prompt


# ---------------------------------------------------------------------------
# Tool dispatch
# ---------------------------------------------------------------------------

def _dispatch(
    name: str,
    args: Dict[str, Any],
    project_root: Optional[Path],
    memory: MemoryStore,
) -> Dict[str, Any]:
    # Fill missing defaults
    if name == "search_code" and "root" not in args and project_root:
        args["root"] = str(project_root)
    if name == "run_tests" and "project_root" not in args and project_root:
        args["project_root"] = str(project_root)
    if name == "run_command" and "cwd" not in args and project_root:
        args["cwd"] = str(project_root)

    # save_memory is handled here (needs MemoryStore)
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

    # Filter args to only those accepted by the function
    import inspect
    sig = inspect.signature(fn)
    valid_keys = set(sig.parameters.keys())
    filtered = {k: v for k, v in args.items() if k in valid_keys}
    return fn(**filtered)


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
    context_window: int = 64000,
) -> Generator[AgentEvent, None, None]:
    """
    ReAct agent loop.
    Yields AgentEvent objects; caller renders them.
    """
    from ollama import Client

    client = Client(host=ollama_host)
    system_prompt = build_system_prompt(profile, memory, project_root, user_message)

    messages: List[Dict[str, Any]] = [{"role": "system", "content": system_prompt}]
    # Include last 20 turns of history
    for msg in chat_history[-20:]:
        messages.append({"role": msg["role"], "content": msg["content"]})
    messages.append({"role": "user", "content": user_message})

    steps = 0
    while steps < MAX_STEPS:
        steps += 1

        try:
            response = client.chat(
                model=llm_model,
                messages=messages,
                tools=TOOLS_SCHEMA,
                options={"temperature": 0.05, "num_ctx": context_window},
            )
        except Exception as exc:
            yield AgentEvent(type="error", content=f"Ollama: {exc}")
            return

        msg = response.message
        tool_calls = getattr(msg, "tool_calls", None) or []

        if tool_calls:
            # Append assistant message with tool_calls to history
            messages.append(
                {
                    "role": "assistant",
                    "content": msg.content or "",
                    "tool_calls": [
                        {
                            "id": getattr(tc, "id", f"call_{i}"),
                            "type": "function",
                            "function": {
                                "name": tc.function.name,
                                "arguments": json.dumps(
                                    tc.function.arguments
                                    if isinstance(tc.function.arguments, dict)
                                    else {}
                                ),
                            },
                        }
                        for i, tc in enumerate(tool_calls)
                    ],
                }
            )

            for i, tc in enumerate(tool_calls):
                name = tc.function.name
                try:
                    raw_args = tc.function.arguments
                    args: Dict[str, Any] = raw_args if isinstance(raw_args, dict) else {}
                except Exception:
                    args = {}

                call_id = getattr(tc, "id", f"call_{i}")

                yield AgentEvent(type="tool_call", tool_name=name, tool_args=dict(args))

                result = _dispatch(name, dict(args), project_root, memory)

                yield AgentEvent(type="tool_result", tool_name=name, tool_result=result)

                messages.append(
                    {
                        "role": "tool",
                        "tool_call_id": call_id,
                        "content": json.dumps(result, ensure_ascii=False)[:6000],
                    }
                )

            # Continue loop to get next model response
            continue

        # No tool calls — deliver final text
        final = (msg.content or "").strip()
        if final:
            # Chunk text for streaming effect in UI
            chunk = 6
            for i in range(0, len(final), chunk):
                yield AgentEvent(type="text", content=final[i : i + chunk])

        yield AgentEvent(type="done")
        return

    yield AgentEvent(
        type="error",
        content=f"Превышен лимит шагов ({MAX_STEPS}). Упрости задачу или раздели на части.",
    )
