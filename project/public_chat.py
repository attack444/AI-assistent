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

SYSTEM_PROMPT = """Ты ассистент публичной платформы AI Helper (витрина на /sites/ai/).
Отвечай по-русски, коротко и по делу. Помогаешь посетителям с деплоем, кабинетом и тарифами.

Что умеет платформа:
- Чат на витрине: бесплатная лёгкая Ollama (qwen), при необходимости DeepSeek.
- Публичный деплой статики: ZIP, tar.gz / tgz, tar или один HTML-файл → превью /sites/p…/ + token для правок.
- Редактор файлов превью по token (без PHP и без доступа к чужим сайтам).
- Виджет чата: /sites/ai/widget.js — можно вставить на любой сайт (в т.ч. WordPress).
- Регистрация / вход → кабинет с лимитами (Free / Starter / Pro). Starter/Pro включает владелец после оплаты.

Что НЕ умеет этот чат:
- Править живые сайты на сервере, WordPress, БД — это только панель владельца или VS Code + DeepSeek с tools.
- Не читай диск и не притворяйся, что открыл файлы пользователя.

CTA: предложи задеплоить архив (#deploy), зарегистрироваться или написать на slavasundukov887@gmail.com по тарифу.
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
Не выдумывай гарантии и чужие кейсы. Если точных цен нет в подсказке — предложи форму заявки.
У тебя НЕТ доступа к админке, файлам и базе — не притворяйся.
Не раскрывай внутренности сервера и чужие сайты."""

WIDGET_SYSTEM_5MB2 = """Ты помощник сайта 5MB2 Digital (https://5mb2.ru) — SEO для бизнеса в России.
Исполнитель — Вячеслав, самозанятый (НПД). Отвечай по-русски, кратко, уверенно, без «воды».

ФАКТЫ С САЙТА (используй их, не говори «нет информации»):
Услуги и ориентиры цен (итог после брифа):
1) SEO-аудит — от 29 000 ₽, срок 5–10 раб. дней. Техника, индекс, конкуренты, план правок.
2) SEO-продвижение — от 55 000 ₽/мес, от 3 месяцев. Семантика, структура, контент, отчёт в кабинете.
3) Local SEO — от 40 000 ₽/мес. Карты, региональные посадочные, отзывы, NAP.
4) Техническое SEO — от 35 000 ₽. CWV, индексация, Schema, миграции.
5) Контент для SEO — от 4 500 ₽/стр. ТЗ, услуги, статьи.
6) Отчётность — входит в ежемесячный тариф; прогресс в личном кабинете.

Воронка для посетителя:
- Услуги: /services/ · инструменты: /instrumenty/ · кабинет: /cabinet/ · заявка: форма на сайте или /contacts/ · оферта: /oferta/
- После заявки — страница «Спасибо». Команда свяжется и уточнит бриф.
- Не обещай «топ-1 за неделю». Честно: SEO — накопительный эффект, первые сигналы часто через 1–3 месяца.

Как отвечать:
- На вопросы про SEO / цены / сроки — опирайся на список выше и предложи релевантную услугу + заявку или кабинет.
- На вопросы не про SEO (хостинг, разработка с нуля) — коротко скажи, что фокус сайта — SEO, и предложи заявку, если задача смежная.
- Не выдумывай кейсы. Реальный проект на сайте — VitrA Russia в разделе «Проекты» (/kejsy/).
- Нет доступа к админке, файлам сервера и чужим сайтам — не притворяйся.
- В конце полезного ответа часто добавляй один CTA: «Оставить заявку» или «Открыть кабинет»."""


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


_TRUSTED_PROXIES = {
    x.strip()
    for x in os.environ.get("TRUSTED_PROXIES", "127.0.0.1,::1").split(",")
    if x.strip()
}


def _peer_ip(handler) -> str:
    remote = handler.client_address[0] if handler.client_address else ""
    if remote.startswith("::ffff:"):
        remote = remote[7:]
    return remote or "unknown"


def client_ip(handler) -> str:
    """Client IP for rate limits.

    Trust X-Real-IP / X-Forwarded-For only when the TCP peer is a configured
    reverse proxy (default: loopback). Direct callers can no longer rotate
    spoofed X-Real-IP to bypass limits.
    """
    peer = _peer_ip(handler)
    if peer in _TRUSTED_PROXIES:
        xri = (handler.headers.get("X-Real-IP") or "").strip()
        if xri and len(xri) < 128 and "\n" not in xri and "\r" not in xri:
            return xri.split(",")[0].strip() or peer
        xff = (handler.headers.get("X-Forwarded-For") or "").strip()
        if xff:
            return xff.split(",")[0].strip() or peer
    return peer
