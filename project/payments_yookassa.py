"""
ЮKassa (YooKassa) — каркас оплаты тарифов NeoBrain.

Полный боевой режим только после:
  YOOKASSA_SHOP_ID=...
  YOOKASSA_SECRET_KEY=...
  PUBLIC_SITE_URL=https://neobrain.site

Пока ключей нет — create_payment возвращает инструкции, webhook не списывает.
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import time
import urllib.error
import urllib.request
import uuid
from pathlib import Path
from typing import Any, Dict, Optional

DATA_DIR = Path(os.environ.get("AI_HELPER_DATA", str(Path.home() / ".ai-helper")))
PAYMENTS_FILE = DATA_DIR / "yookassa_payments.jsonl"

API_URL = "https://api.yookassa.ru/v3/payments"


def configured() -> bool:
    return bool(
        os.environ.get("YOOKASSA_SHOP_ID", "").strip()
        and os.environ.get("YOOKASSA_SECRET_KEY", "").strip()
    )


def status() -> Dict[str, Any]:
    return {
        "ok": True,
        "provider": "yookassa",
        "configured": configured(),
        "shop_id_set": bool(os.environ.get("YOOKASSA_SHOP_ID", "").strip()),
        "return_url": os.environ.get("PUBLIC_SITE_URL", "https://neobrain.site").rstrip("/")
        + "/#pricing",
        "hint": None
        if configured()
        else "Добавь YOOKASSA_SHOP_ID и YOOKASSA_SECRET_KEY в project/.env и перезапусти app",
    }


def _auth_header() -> str:
    shop = os.environ.get("YOOKASSA_SHOP_ID", "").strip()
    secret = os.environ.get("YOOKASSA_SECRET_KEY", "").strip()
    raw = f"{shop}:{secret}".encode("utf-8")
    return "Basic " + base64.b64encode(raw).decode("ascii")


def _plan_amount(plan_id: str) -> Optional[int]:
    try:
        from public_plans import PLANS

        p = PLANS.get((plan_id or "").lower())
        if not p or p.get("hidden"):
            return None
        price = int(p.get("price_rub") or 0)
        return price if price > 0 else None
    except Exception:
        return None


def _append(record: Dict[str, Any]) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    with PAYMENTS_FILE.open("a", encoding="utf-8") as f:
        f.write(json.dumps(record, ensure_ascii=False) + "\n")


def create_payment(
    *,
    email: str,
    plan_id: str,
    return_url: str = "",
) -> Dict[str, Any]:
    email = (email or "").strip().lower()
    plan_id = (plan_id or "").strip().lower()
    amount = _plan_amount(plan_id)
    if not email or "@" not in email:
        raise ValueError("Нужен email")
    if not amount:
        raise ValueError("Оплата только для starter/pro")

    if not configured():
        rec = {
            "at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "status": "pending_manual",
            "email": email,
            "plan": plan_id,
            "amount_rub": amount,
        }
        _append(rec)
        return {
            "ok": True,
            "mode": "manual",
            "message": (
                f"ЮKassa ещё не подключена. Напишите владельцу для активации {plan_id} "
                f"({amount} ₽) на {email}. Или задайте YOOKASSA_* в .env."
            ),
            "amount_rub": amount,
            "plan": plan_id,
        }

    site = (os.environ.get("PUBLIC_SITE_URL") or "https://neobrain.site").rstrip("/")
    return_url = (return_url or f"{site}/#pricing").strip()
    idem = str(uuid.uuid4())
    body = {
        "amount": {"value": f"{amount}.00", "currency": "RUB"},
        "confirmation": {"type": "redirect", "return_url": return_url},
        "capture": True,
        "description": f"NeoBrain {plan_id} — {email}",
        "metadata": {"email": email, "plan": plan_id, "brand": "NeoBrain"},
    }
    req = urllib.request.Request(
        API_URL,
        data=json.dumps(body).encode("utf-8"),
        method="POST",
        headers={
            "Authorization": _auth_header(),
            "Content-Type": "application/json",
            "Idempotence-Key": idem,
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"ЮKassa HTTP {exc.code}: {detail}") from exc

    conf = (data.get("confirmation") or {}).get("confirmation_url") or ""
    rec = {
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": data.get("status") or "pending",
        "payment_id": data.get("id"),
        "email": email,
        "plan": plan_id,
        "amount_rub": amount,
        "confirmation_url": conf,
    }
    _append(rec)
    return {
        "ok": True,
        "mode": "yookassa",
        "payment_id": data.get("id"),
        "confirmation_url": conf,
        "amount_rub": amount,
        "plan": plan_id,
    }


def apply_paid_plan(email: str, plan_id: str) -> Dict[str, Any]:
    from public_users import set_plan

    return set_plan(email, plan_id)


def handle_webhook(payload: Dict[str, Any]) -> Dict[str, Any]:
    """Обработка notification от ЮKassa (event payment.succeeded)."""
    event = (payload.get("event") or "").strip()
    obj = payload.get("object") or {}
    if event != "payment.succeeded":
        return {"ok": True, "ignored": True, "event": event}
    meta = obj.get("metadata") or {}
    email = (meta.get("email") or "").strip().lower()
    plan = (meta.get("plan") or "").strip().lower()
    if not email or plan not in {"starter", "pro"}:
        return {"ok": False, "error": "metadata email/plan missing"}
    result = apply_paid_plan(email, plan)
    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": "activated",
        "payment_id": obj.get("id"),
        "email": email,
        "plan": plan,
    })
    return {"ok": True, "activated": result}
