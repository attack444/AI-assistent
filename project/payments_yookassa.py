"""
ЮKassa — фиксированные тарифы NeoBrain (Starter/Pro), оплата картой.

Ключи: панель → Настройки (shopId + secret) или .env
Webhook: https://neobrain.site/api/public/pay/webhook
"""
from __future__ import annotations

import base64
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
WEBHOOK_PATH = "/api/public/pay/webhook"


def _site() -> str:
    site = os.environ.get("PUBLIC_SITE_URL", "https://neobrain.site").rstrip("/")
    try:
        import owner_settings as osset

        site = (osset.get_raw().get("public_site_url") or site).rstrip("/")
    except Exception:
        pass
    return site


def _creds() -> tuple[str, str]:
    shop = os.environ.get("YOOKASSA_SHOP_ID", "").strip()
    secret = os.environ.get("YOOKASSA_SECRET_KEY", "").strip()
    try:
        import owner_settings as osset

        raw = osset.get_raw()
        shop = (raw.get("yookassa_shop_id") or shop).strip()
        secret = (raw.get("yookassa_secret_key") or secret).strip()
    except Exception:
        pass
    return shop, secret


def configured() -> bool:
    shop, secret = _creds()
    return bool(shop and secret)


def status() -> Dict[str, Any]:
    shop, secret = _creds()
    site = _site()
    return {
        "ok": True,
        "provider": "yookassa",
        "mode": "fixed_price",
        "configured": configured(),
        "shop_id_set": bool(shop),
        "return_url": site + "/?paid=1#start",
        "webhook_url": site + WEBHOOK_PATH,
        "self_serve": True,
        "currency": "RUB",
        "hint": None
        if configured()
        else "Панель → Настройки: shopId и секретный ключ. Webhook: "
        + site
        + WEBHOOK_PATH,
    }


def _auth_header() -> str:
    shop, secret = _creds()
    raw = f"{shop}:{secret}".encode("utf-8")
    return "Basic " + base64.b64encode(raw).decode("ascii")


def _plan_info(plan_id: str) -> Optional[Dict[str, Any]]:
    try:
        from public_plans import PLANS

        p = PLANS.get((plan_id or "").lower())
        if not p or p.get("hidden"):
            return None
        price = int(p.get("price_rub") or 0)
        if price <= 0:
            return None
        return {
            "id": p["id"],
            "name": p.get("name") or plan_id,
            "price_rub": price,
            "period": p.get("period") or "month",
        }
    except Exception:
        return None


def _append(record: Dict[str, Any]) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    with PAYMENTS_FILE.open("a", encoding="utf-8") as f:
        f.write(json.dumps(record, ensure_ascii=False) + "\n")


def _send_receipt() -> bool:
    # Чек 54-ФЗ (НПД без НДС). Отключить: YOOKASSA_SEND_RECEIPT=0
    return os.environ.get("YOOKASSA_SEND_RECEIPT", "1").strip() not in {"0", "false", "no"}


def create_payment(
    *,
    email: str,
    plan_id: str,
    return_url: str = "",
) -> Dict[str, Any]:
    """Создать платёж на фиксированную сумму тарифа → redirect на ЮKassa."""
    email = (email or "").strip().lower()
    plan_id = (plan_id or "").strip().lower()
    info = _plan_info(plan_id)
    if not email or "@" not in email:
        raise ValueError("Нужен email аккаунта")
    if not info:
        raise ValueError("Оплата только для фиксированных тарифов Starter / Pro")

    amount = int(info["price_rub"])
    plan_name = str(info["name"])
    site = _site()

    if not configured():
        rec = {
            "at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "status": "pending_keys",
            "email": email,
            "plan": plan_id,
            "amount_rub": amount,
        }
        _append(rec)
        return {
            "ok": False,
            "mode": "not_configured",
            "error": "ЮKassa ещё не подключена в панели (shopId + секретный ключ).",
            "amount_rub": amount,
            "plan": plan_id,
            "rekvizity_url": site + "/rekvizity/",
        }

    ret = (return_url or f"{site}/?paid=1#start").strip()
    if not ret.startswith("http"):
        ret = f"{site}/?paid=1#start"

    amount_value = f"{amount}.00"
    description = f"NeoBrain {plan_name} — 1 месяц ({amount} ₽)"
    body: Dict[str, Any] = {
        "amount": {"value": amount_value, "currency": "RUB"},
        "confirmation": {"type": "redirect", "return_url": ret},
        "capture": True,
        "description": description[:128],
        "metadata": {
            "email": email,
            "plan": plan_id,
            "brand": "NeoBrain",
            "period": "month",
            "amount_rub": str(amount),
        },
    }
    if _send_receipt():
        # vat_code 1 = без НДС (НПД)
        body["receipt"] = {
            "customer": {"email": email},
            "items": [
                {
                    "description": f"NeoBrain {plan_name} — 1 месяц"[ :128],
                    "quantity": "1.00",
                    "amount": {"value": amount_value, "currency": "RUB"},
                    "vat_code": 1,
                    "payment_mode": "full_payment",
                    "payment_subject": "service",
                }
            ],
        }

    idem = str(uuid.uuid4())
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
        detail = exc.read().decode("utf-8", errors="replace")[:700]
        raise RuntimeError(f"ЮKassa HTTP {exc.code}: {detail}") from exc

    conf = (data.get("confirmation") or {}).get("confirmation_url") or ""
    if not conf:
        raise RuntimeError("ЮKassa не вернула confirmation_url")

    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": data.get("status") or "pending",
        "payment_id": data.get("id"),
        "email": email,
        "plan": plan_id,
        "amount_rub": amount,
        "confirmation_url": conf,
    })
    return {
        "ok": True,
        "mode": "yookassa",
        "payment_id": data.get("id"),
        "confirmation_url": conf,
        "amount_rub": amount,
        "plan": plan_id,
        "plan_name": plan_name,
        "description": description,
    }


def apply_paid_plan(email: str, plan_id: str) -> Dict[str, Any]:
    from public_users import set_plan

    return set_plan(email, plan_id)


def fetch_payment(payment_id: str) -> Dict[str, Any]:
    """Подтверждение платежа у ЮKassa (не доверяем сырому webhook-телу)."""
    pid = (payment_id or "").strip()
    if not pid:
        raise ValueError("payment_id пуст")
    if not configured():
        raise RuntimeError("ЮKassa не настроена")
    req = urllib.request.Request(
        f"{API_URL}/{pid}",
        method="GET",
        headers={"Authorization": _auth_header(), "Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=25) as resp:
        return json.loads(resp.read().decode("utf-8"))


def handle_webhook(payload: Dict[str, Any]) -> Dict[str, Any]:
    """Notification payment.succeeded → активация тарифа после GET-верификации."""
    event = (payload.get("event") or "").strip()
    obj = payload.get("object") or {}
    if event != "payment.succeeded":
        return {"ok": True, "ignored": True, "event": event}
    payment_id = (obj.get("id") or "").strip()
    if not payment_id:
        return {"ok": False, "error": "payment id missing"}
    try:
        verified = fetch_payment(payment_id)
    except Exception as exc:
        return {"ok": False, "error": f"verify failed: {exc}"}
    if (verified.get("status") or "").lower() != "succeeded":
        return {"ok": False, "error": f"status={verified.get('status')}"}
    meta = verified.get("metadata") or obj.get("metadata") or {}
    email = (meta.get("email") or "").strip().lower()
    plan = (meta.get("plan") or "").strip().lower()
    if not email or plan not in {"starter", "pro"}:
        return {"ok": False, "error": "metadata email/plan missing"}
    result = apply_paid_plan(email, plan)
    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": "activated",
        "payment_id": payment_id,
        "email": email,
        "plan": plan,
        "verified": True,
    })
    return {"ok": True, "activated": result, "verified": True}
