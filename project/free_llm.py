"""Free on-server LLM via Ollama (default qwen2.5:1.5b — RU-friendly, small RAM)."""
from __future__ import annotations

import json
import os
import re
import urllib.error
import urllib.request
from typing import Any, Dict, Generator, List, Optional, Tuple

DEFAULT_FREE_MODEL = os.environ.get("FREE_LLM_MODEL", "qwen2.5:1.5b").strip() or "qwen2.5:1.5b"
DEFAULT_HOST = os.environ.get("OLLAMA_HOST", "http://127.0.0.1:11434").strip() or "http://127.0.0.1:11434"

# Tiny models often reject OpenAI-style tools with HTTP 400 Bad Request.
_TINY_SIZE_RE = re.compile(
    r"(?:^|[^0-9])(0\.5b|0\.6b|1b|1\.5b|1\.7b|1\.8b|2b|360m|500m)(?:$|[^0-9a-z])",
    re.I,
)
_TOOL_OK_MARKERS = (
    "qwen2.5:7b",
    "qwen2.5:14b",
    "qwen2.5:32b",
    "qwen2.5-coder",
    "qwen3",
    "llama3.1",
    "llama3.2:3b",
    "llama3.3",
    "mistral",
    "command-r",
    "nemotron",
    "tool",
)


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


def model_supports_tools(model: str = "") -> bool:
    """
    Whether it is safe to send OpenAI-style `tools` to this Ollama model.

    qwen2.5:1.5b (default free model) commonly returns HTTP 400 when tools are set.
    Override with FREE_LLM_TOOLS=0|1.
    """
    flag = os.environ.get("FREE_LLM_TOOLS", "").strip().lower()
    if flag in {"0", "false", "no", "off"}:
        return False
    if flag in {"1", "true", "yes", "on"}:
        return True
    m = (model or free_model()).strip().lower()
    if not m:
        return False
    if _TINY_SIZE_RE.search(m):
        return False
    if any(x in m for x in _TOOL_OK_MARKERS):
        return True
    # Heuristic: 7b+ usually OK; unknown small tags → no tools
    if re.search(r"(?:^|[^0-9])([7-9]|[1-9][0-9]+)b(?:$|[^0-9a-z])", m):
        return True
    return False


def http_error_detail(exc: BaseException) -> str:
    """Readable Ollama/urllib HTTP error (include response body when present)."""
    if isinstance(exc, urllib.error.HTTPError):
        body = ""
        try:
            raw = exc.read()
            if isinstance(raw, bytes):
                body = raw.decode("utf-8", errors="replace")
            else:
                body = str(raw or "")
        except Exception:
            body = ""
        body = (body or "").strip().replace("\n", " ")[:400]
        reason = getattr(exc, "reason", None) or ""
        if body:
            return f"HTTP {exc.code}: {body}"
        return f"HTTP Error {exc.code}: {reason or 'Bad Request'}"
    return str(exc)


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
        tools_ok = model_supports_tools(model)
        return {
            "ok": True,
            "reachable": True,
            "model": model,
            "has_model": has or (model in names),
            "models": names,
            "tools_supported": tools_ok,
        }
    except Exception as exc:
        return {
            "ok": False,
            "reachable": False,
            "model": model,
            "has_model": False,
            "models": [],
            "tools_supported": model_supports_tools(model),
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
    try:
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
    except urllib.error.HTTPError as exc:
        raise RuntimeError(f"Ollama ({host}): {http_error_detail(exc)}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Ollama ({host}): {exc.reason or exc}") from exc


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
