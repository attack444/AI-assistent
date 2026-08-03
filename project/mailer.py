"""SMTP из настроек владельца — письма NeoBrain."""
from __future__ import annotations

import os
import smtplib
import ssl
from email.message import EmailMessage
from typing import Any, Dict


def smtp_config() -> Dict[str, Any]:
    host = os.environ.get("SMTP_HOST", "").strip()
    port = os.environ.get("SMTP_PORT", "587").strip()
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
    return {
        "host": host,
        "port": int(port or 587),
        "user": user,
        "password": password,
        "configured": bool(host and user and password),
    }


def send_mail(*, to: str, subject: str, body: str) -> Dict[str, Any]:
    cfg = smtp_config()
    if not cfg["configured"]:
        return {"ok": False, "error": "SMTP не настроен (панель → Настройки)"}
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
        with smtplib.SMTP(cfg["host"], cfg["port"], timeout=25) as smtp:
            smtp.ehlo()
            if cfg["port"] == 587:
                smtp.starttls(context=context)
                smtp.ehlo()
            smtp.login(cfg["user"], cfg["password"])
            smtp.send_message(msg)
        return {"ok": True}
    except Exception as exc:
        return {"ok": False, "error": str(exc)[:300]}
