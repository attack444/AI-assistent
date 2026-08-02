"""Cloudflare Turnstile + простые антибот проверки."""
from __future__ import annotations

import json
import os
import urllib.parse
import urllib.request
from typing import Any, Dict, Optional


def _turnstile_secret() -> str:
    secret = os.environ.get("TURNSTILE_SECRET_KEY", "").strip()
    try:
        import owner_settings as osset

        secret = (osset.get_raw().get("turnstile_secret_key") or secret).strip()
    except Exception:
        pass
    return secret


def turnstile_required() -> bool:
    return bool(_turnstile_secret())


def verify_turnstile(token: str, ip: str = "") -> Dict[str, Any]:
    secret = _turnstile_secret()
    if not secret:
        return {"ok": True, "skipped": True}
    token = (token or "").strip()
    if not token:
        return {"ok": False, "error": "Подтвердите, что вы не робот (Turnstile)"}
    body = urllib.parse.urlencode({
        "secret": secret,
        "response": token,
        "remoteip": ip or "",
    }).encode("utf-8")
    req = urllib.request.Request(
        "https://challenges.cloudflare.com/turnstile/v0/siteverify",
        data=body,
        method="POST",
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except Exception as exc:
        return {"ok": False, "error": f"Turnstile недоступен: {exc}"}
    if not data.get("success"):
        return {"ok": False, "error": "Проверка антибота не пройдена", "codes": data.get("error-codes")}
    return {"ok": True}
