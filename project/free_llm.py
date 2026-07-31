"""Free on-server LLM via Ollama (default qwen2.5:1.5b — RU-friendly, small RAM)."""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from typing import Any, Dict, Generator, List, Optional, Tuple

DEFAULT_FREE_MODEL = os.environ.get("FREE_LLM_MODEL", "qwen2.5:1.5b").strip() or "qwen2.5:1.5b"
DEFAULT_HOST = os.environ.get("OLLAMA_HOST", "http://127.0.0.1:11434").strip() or "http://127.0.0.1:11434"


def prefer_free() -> bool:
    return os.environ.get("LLM_PREFER_FREE", "1").strip().lower() not in {"0", "false", "no"}


def ollama_host(settings_host: str = "") -> str:
    return (settings_host or DEFAULT_HOST).rstrip("/")


def free_model(settings_fast: str = "", settings_llm: str = "") -> str:
    env = os.environ.get("FREE_LLM_MODEL", "").strip()
    if env:
        return env
    # Prefer dedicated free model name over deepseek-* settings
    for cand in (settings_fast, settings_llm):
        c = (cand or "").strip()
        if c and not c.startswith("deepseek") and "groq" not in c.lower():
            return c
    return DEFAULT_FREE_MODEL


def check_ollama(host: str = "", model: str = "") -> Dict[str, Any]:
    host = ollama_host(host)
    model = model or free_model()
    try:
        req = urllib.request.Request(f"{host}/api/tags", method="GET")
        with urllib.request.urlopen(req, timeout=3) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        names = []
        for m in data.get("models") or []:
            name = (m.get("name") or m.get("model") or "").strip()
            if name:
                names.append(name)
        has = any(n == model or n.startswith(model + ":") or model.startswith(n.split(":")[0]) for n in names)
        # also match qwen2.5:1.5b vs qwen2.5:1.5b-instruct
        if not has:
            base = model.split(":")[0]
            has = any(n.startswith(base) for n in names)
            if has:
                # pick best matching installed tag
                for n in names:
                    if n.startswith(model) or model.startswith(n.split(":")[0]) and model.split(":")[-1] in n:
                        model = n
                        break
                else:
                    for n in names:
                        if n.startswith(base):
                            model = n
                            break
        return {
            "ok": True,
            "reachable": True,
            "model": model,
            "has_model": has or (model in names),
            "models": names,
        }
    except Exception as exc:
        return {
            "ok": False,
            "reachable": False,
            "model": model,
            "has_model": False,
            "models": [],
            "error": str(exc),
        }


def stream_ollama(
    messages: List[Dict[str, str]],
    host: str = "",
    model: str = "",
    temperature: float = 0.4,
    max_tokens: int = 768,
    timeout: float = 180.0,
) -> Generator[str, None, None]:
    host = ollama_host(host)
    model = model or free_model()
    body = json.dumps(
        {
            "model": model,
            "messages": messages,
            "stream": True,
            "options": {
                "temperature": temperature,
                "num_predict": max_tokens,
                "num_ctx": 4096,
            },
        },
        ensure_ascii=False,
    ).encode("utf-8")
    req = urllib.request.Request(
        f"{host}/api/chat",
        data=body,
        method="POST",
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        for raw_line in resp:
            line = raw_line.decode("utf-8").strip()
            if not line:
                continue
            try:
                chunk = json.loads(line)
            except json.JSONDecodeError:
                continue
            txt = (chunk.get("message") or {}).get("content") or ""
            if txt:
                yield txt
            if chunk.get("done"):
                break


def try_stream_free(
    messages: List[Dict[str, str]],
    host: str = "",
    model: str = "",
) -> Tuple[bool, str, Optional[Generator[str, None, None]], str]:
    """
    Returns (ok, model_used, generator_or_None, error).
    Generator is lazy — caller iterates.
    """
    st = check_ollama(host, model)
    if not st.get("reachable"):
        return False, model or free_model(), None, st.get("error") or "Ollama недоступна"
    if not st.get("has_model"):
        return (
            False,
            st.get("model") or free_model(),
            None,
            f"Модель не скачана. На VPS: bash project/deploy/install-free-llm.sh",
        )
    used = st.get("model") or free_model()

    def _gen() -> Generator[str, None, None]:
        yield from stream_ollama(messages, host=host, model=used)

    return True, used, _gen(), ""
