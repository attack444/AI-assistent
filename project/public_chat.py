"""Public AI Helper chat — free Ollama first, DeepSeek fallback. No server tools."""
from __future__ import annotations

import os
import threading
import time
from collections import defaultdict, deque
from typing import Any, Deque, Dict, Generator, List, Tuple

from agent import DEEPSEEK_API_URL, DEEPSEEK_DEFAULT_MODEL, _groq_stream
from core import load_settings
import free_llm

SYSTEM_PROMPT = """Ты ассистент публичной платформы AI Helper.
Помогаешь с деплоем ZIP (статика), редактором по token, тарифами и виджетом чата для сайтов.
Отвечай по-русски, коротко и по делу.

Факты о моделях:
- На витрине чат идёт через бесплатную Ollama (лёгкая модель) и при необходимости DeepSeek.
- Правки живых сайтов / WordPress делает только панель владельца или VS Code + DeepSeek (tools) — не этот чат.
- Виджет: подключить /sites/ai/widget.js на любом сайте (в т.ч. WordPress).

У тебя НЕТ доступа к файлам сервера — не притворяйся, что читаешь диск.
Не раскрывай пароли, пути сервера и внутренности админ-панели."""

_RATE_LIMIT = int(os.environ.get("PUBLIC_CHAT_RATE_LIMIT", "30"))
_RATE_WINDOW = int(os.environ.get("PUBLIC_CHAT_RATE_WINDOW", "3600"))
_GUEST_RATE_LIMIT = int(os.environ.get("PUBLIC_WIDGET_RATE_LIMIT", "20"))
_MAX_MSG = int(os.environ.get("PUBLIC_CHAT_MAX_MSG", "2000"))
_MAX_HISTORY = int(os.environ.get("PUBLIC_CHAT_MAX_HISTORY", "12"))

_lock = threading.Lock()
_hits: Dict[str, Deque[float]] = defaultdict(deque)


def check_rate_limit(ip: str, *, guest: bool = False) -> Tuple[bool, str]:
    now = time.time()
    limit = _GUEST_RATE_LIMIT if guest else _RATE_LIMIT
    key = ("g:" if guest else "u:") + (ip or "unknown")
    with _lock:
        # Drop idle IPs so the map does not grow forever
        if len(_hits) > 5000:
            stale = [k for k, q in _hits.items() if not q or now - q[-1] > _RATE_WINDOW]
            for k in stale[:2000]:
                _hits.pop(k, None)
        q = _hits[key]
        while q and now - q[0] > _RATE_WINDOW:
            q.popleft()
        if len(q) >= limit:
            return False, f"Лимит: {limit} сообщений / час. Подожди немного."
        q.append(now)
    return True, ""


def _sanitize_history(history: Any) -> List[Dict[str, str]]:
    out: List[Dict[str, str]] = []
    if not isinstance(history, list):
        return out
    for item in history[-_MAX_HISTORY:]:
        if not isinstance(item, dict):
            continue
        role = (item.get("role") or "").strip()
        content = (item.get("content") or "").strip()
        if role not in ("user", "assistant") or not content:
            continue
        out.append({"role": role, "content": content[:_MAX_MSG]})
    return out


WIDGET_SYSTEM = """Ты вежливый помощник на сайте клиента (виджет AI Helper).
Отвечай по-русски, коротко, по делу. Помогаешь посетителям: услуги, цены в общих чертах, контакты, как оставить заявку.
Не выдумывай точные цены и гарантии — предложи связаться с владельцем сайта или оставить заявку через форму.
У тебя НЕТ доступа к админке, файлам и базе — не притворяйся.
Не раскрывай внутренности сервера и чужие сайты."""

WIDGET_SYSTEM_5MB2 = """Ты помощник сайта 5MB2 Digital (SEO-продвижение сайтов по России).
Отвечай по-русски, кратко и по делу: SEO, поисковое продвижение, заявки, консультация.
Не называй точные цены, если их нет на сайте — предложи оставить заявку или связаться с Вячеславом.
Не выдумывай кейсы. Нет доступа к админке и файлам сервера."""


def build_messages(
    message: str,
    history: Any,
    *,
    widget: bool = False,
    site_hint: str = "",
) -> List[Dict[str, str]]:
    if widget and "5mb2" in (site_hint or "").lower():
        system = WIDGET_SYSTEM_5MB2
    elif widget:
        system = WIDGET_SYSTEM
    else:
        system = SYSTEM_PROMPT
    if site_hint:
        system += f"\nСайт: {site_hint[:120]}."
    msgs: List[Dict[str, str]] = [{"role": "system", "content": system}]
    msgs.extend(_sanitize_history(history))
    msgs.append({"role": "user", "content": message[:_MAX_MSG]})
    return msgs


def stream_public_chat(
    message: str,
    history: Any = None,
    *,
    widget: bool = False,
    site_hint: str = "",
) -> Generator[Dict[str, str], None, None]:
    message = (message or "").strip()
    if not message:
        yield {"type": "error", "content": "Пустое сообщение"}
        yield {"type": "done", "content": ""}
        return

    settings = load_settings()
    messages = build_messages(message, history, widget=widget, site_hint=site_hint)
    host = free_llm.ollama_host(settings.ollama_host)
    model = free_llm.free_model(settings.fast_llm_model, settings.llm_model)

    # 1) Free local Ollama (always try when LLM_PREFER_FREE=1, default on)
    if free_llm.prefer_free():
        ok, used, gen, err = free_llm.try_stream_free(messages, host=host, model=model)
        if ok and gen is not None:
            yield {"type": "info", "content": f"free:{used}"}
            try:
                for chunk in gen:
                    if chunk:
                        yield {"type": "text", "content": chunk}
                yield {"type": "done", "content": ""}
                return
            except Exception as exc:
                yield {"type": "info", "content": f"free_fallback:{exc}"}
        elif err:
            yield {"type": "info", "content": f"free_skip:{err}"}

    # 2) DeepSeek paid fallback
    api_key = (settings.deepseek_api_key or os.environ.get("DEEPSEEK_API_KEY", "")).strip()
    ds_model = (settings.deepseek_model or os.environ.get("DEEPSEEK_MODEL", "") or DEEPSEEK_DEFAULT_MODEL).strip()
    proxy = (settings.http_proxy or os.environ.get("AI_HELPER_HTTP_PROXY", "")).strip()

    if not api_key:
        yield {
            "type": "error",
            "content": "Нет бесплатной модели (Ollama) и нет DEEPSEEK_API_KEY. "
            "На VPS: bash project/deploy/install-free-llm.sh",
        }
        yield {"type": "done", "content": ""}
        return

    yield {"type": "info", "content": f"deepseek:{ds_model}"}
    try:
        for chunk in _groq_stream(
            api_key,
            ds_model,
            messages,
            max_tokens=1024,
            temperature=0.4,
            timeout=90.0,
            proxy=proxy,
            _api_url=DEEPSEEK_API_URL,
        ):
            if chunk:
                yield {"type": "text", "content": chunk}
        yield {"type": "done", "content": ""}
    except Exception as exc:
        yield {"type": "error", "content": str(exc)}
        yield {"type": "done", "content": ""}


def client_ip(handler) -> str:
    # Prefer nginx X-Real-IP (set by trusted proxy). X-Forwarded-For is spoofable from clients.
    xri = (handler.headers.get("X-Real-IP") or "").strip()
    if xri:
        return xri
    xff = (handler.headers.get("X-Forwarded-For") or "").strip()
    if xff:
        # Last hop is usually the proxy-added value when clients cannot override chain end;
        # still prefer first only as weak fallback behind simple reverse proxies.
        return xff.split(",")[0].strip()
    return handler.client_address[0] if handler.client_address else "unknown"
