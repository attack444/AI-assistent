"""SMTP из настроек владельца — письма NeoBrain.

Для Яндекс 360 достаточно email + пароль приложения:
host/port подставляются сами (smtp.yandex.ru:465).
"""
from __future__ import annotations

import os
import smtplib
import ssl
from email.message import EmailMessage
from typing import Any, Dict, Tuple


def _guess_smtp(user: str, host: str, port: str) -> Tuple[str, int, str]:
    """host, port, mode(ssl|starttls)."""
    user_l = (user or "").lower()
    host = (host or "").strip()
    port_i = int(port or 0) if str(port or "").strip().isdigit() else 0
    yandex = (
        user_l.endswith("@yandex.ru")
        or user_l.endswith("@yandex.com")
        or user_l.endswith("@ya.ru")
        or user_l.endswith("@yandex.by")
        or user_l.endswith("@yandex.kz")
        or "yandex" in host.lower()
    )
    if not host and yandex:
        host = "smtp.yandex.ru"
    if not host and user_l.endswith("@gmail.com"):
        host = "smtp.gmail.com"
    if not host and user_l.endswith("@mail.ru"):
        host = "smtp.mail.ru"
    if not port_i:
        if host in {"smtp.yandex.ru", "smtp.gmail.com", "smtp.mail.ru"}:
            port_i = 465
        else:
            port_i = 587
    mode = "ssl" if port_i == 465 else "starttls"
    return host, port_i, mode


def smtp_config() -> Dict[str, Any]:
    host = os.environ.get("SMTP_HOST", "").strip()
    port = os.environ.get("SMTP_PORT", "").strip()
    user = os.environ.get("SMTP_USER", "").strip()
    password = os.environ.get("SMTP_PASSWORD", "").strip()
    try:
        import owner_settings as osset

        raw = osset.get_raw()
        host = (raw.get("smtp_host") or host).strip()
        port = str(raw.get("smtp_port") or port).strip()
        user = (raw.get("smtp_user") or user).strip()
        password = (raw.get("smtp_password") or password).strip()
    except Exception:
        pass
    host, port_i, mode = _guess_smtp(user, host, port)
    return {
        "host": host,
        "port": port_i,
        "mode": mode,
        "user": user,
        "password": password,
        "configured": bool(host and user and password),
    }


def send_mail(*, to: str, subject: str, body: str) -> Dict[str, Any]:
    cfg = smtp_config()
    if not cfg["configured"]:
        return {"ok": False, "error": "Почта не настроена (панель → Настройки: email + пароль)"}
    to = (to or "").strip()
    if not to or "@" not in to:
        return {"ok": False, "error": "Некорректный получатель"}
    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"] = cfg["user"]
    msg["To"] = to
    msg.set_content(body)
    try:
        context = ssl.create_default_context()
        if cfg["mode"] == "ssl":
            with smtplib.SMTP_SSL(cfg["host"], cfg["port"], timeout=25, context=context) as smtp:
                smtp.login(cfg["user"], cfg["password"])
                smtp.send_message(msg)
        else:
            with smtplib.SMTP(cfg["host"], cfg["port"], timeout=25) as smtp:
                smtp.ehlo()
                smtp.starttls(context=context)
                smtp.ehlo()
                smtp.login(cfg["user"], cfg["password"])
                smtp.send_message(msg)
        return {"ok": True}
    except Exception as exc:
        return {"ok": False, "error": str(exc)[:300]}
