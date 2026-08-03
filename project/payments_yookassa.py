"""
ЮKassa — фиксированные цены:
  NeoBrain: тарифы Starter/Pro
  5MB2: пакеты SEO (аудит, продвижение, Local, техника)

Ключи: панель → Настройки (shopId + secret) или .env
Webhook (один на магазин): https://neobrain.site/api/public/pay/webhook
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

# Фиксированные пакеты 5MB2 (входная цена «от» = цена стандартного пакета картой)
PACKAGES: Dict[str, Dict[str, Any]] = {
    "mb2-seo-audit": {
        "id": "mb2-seo-audit",
        "brand": "5MB2",
        "name": "SEO-аудит",
        "price_rub": 29000,
        "period": "once",
        "service_slug": "seo-audit",
        "blurb": "Разовый стандартный аудит",
    },
    "mb2-seo-monthly": {
        "id": "mb2-seo-monthly",
        "brand": "5MB2",
        "name": "SEO-продвижение — 1 месяц",
        "price_rub": 55000,
        "period": "month",
        "service_slug": "prodvizhenie",
        "blurb": "Стартовый месяц продвижения",
    },
    "mb2-local-seo": {
        "id": "mb2-local-seo",
        "brand": "5MB2",
        "name": "Local SEO — 1 месяц",
        "price_rub": 40000,
        "period": "month",
        "service_slug": "local-seo",
        "blurb": "Локальное SEO, 1 месяц",
    },
    "mb2-tech-seo": {
        "id": "mb2-tech-seo",
        "brand": "5MB2",
        "name": "Техническое SEO",
        "price_rub": 35000,
        "period": "once",
        "service_slug": "tech-seo",
        "blurb": "Разовый технический пакет",
    },
}


def _site() -> str:
    site = os.environ.get("PUBLIC_SITE_URL", "https://neobrain.site").rstrip("/")
    try:
        import owner_settings as osset

        site = (osset.get_raw().get("public_site_url") or site).rstrip("/")
    except Exception:
        pass
    return site


def _clean_cred(raw: str) -> str:
    """Убирает кавычки/BOM/префиксы, которые ломают Basic Auth."""
    val = (raw or "").strip().lstrip("\ufeff")
    # Невидимые/странные пробелы из буфера обмена
    for ch in ("\u00a0", "\u200b", "\u200c", "\u200d", "\ufeff"):
        val = val.replace(ch, "")
    if len(val) >= 2 and val[0] == val[-1] and val[0] in {'"', "'"}:
        val = val[1:-1].strip()
    # Иногда вставляют целиком заголовок
    for prefix in ("Basic ", "Bearer ", "basic ", "bearer "):
        if val.startswith(prefix):
            val = val[len(prefix) :].strip()
    # Без пробелов/переносов внутри ключа
    val = "".join(val.split())
    return val


def normalize_creds(shop: str, secret: str) -> tuple[str, str]:
    """shopId = цифры; secret = test_/live_… Если вставили shop:secret в одно поле — разделим."""
    shop = _clean_cred(shop)
    secret = _clean_cred(secret)
    # В секрет случайно вставили "123456:test_xxx"
    if ":" in secret and not secret.startswith(("test_", "live_")):
        left, right = secret.split(":", 1)
        if left.isdigit() and right:
            if not shop:
                shop = left
            secret = right
    # В shopId вставили "shopId: 123456" или URL
    if not shop.isdigit():
        digits = "".join(ch for ch in shop if ch.isdigit())
        if len(digits) >= 5:
            shop = digits
    # Секрет ЮKassa — только ASCII (иначе Basic Auth падает / ключ «битый» из буфера)
    if secret and any(ord(c) > 127 for c in secret):
        secret = "".join(c for c in secret if ord(c) < 128)
    return shop, secret


def fingerprint(shop: str = "", secret: str = "") -> Dict[str, Any]:
    """Отпечаток ключей без раскрытия секрета — чтобы видеть, что реально сохранилось."""
    if shop or secret:
        shop, secret = normalize_creds(shop, secret)
    else:
        shop, secret = _creds()
    prefix = (
        "test_"
        if secret.startswith("test_")
        else ("live_" if secret.startswith("live_") else ("other" if secret else ""))
    )
    return {
        "shop_id": shop,
        "shop_id_len": len(shop),
        "shop_id_tail": shop[-4:] if len(shop) >= 4 else shop,
        "secret_prefix": prefix,
        "secret_len": len(secret),
        "secret_tail": secret[-4:] if len(secret) >= 4 else "",
        "format_ok": bool(shop and shop.isdigit() and prefix in {"test_", "live_"} and len(secret) >= 20),
    }


def _creds() -> tuple[str, str]:
    shop = os.environ.get("YOOKASSA_SHOP_ID", "")
    secret = os.environ.get("YOOKASSA_SECRET_KEY", "")
    try:
        import owner_settings as osset

        raw = osset.get_raw()
        shop = raw.get("yookassa_shop_id") or shop
        secret = raw.get("yookassa_secret_key") or secret
    except Exception:
        pass
    return normalize_creds(str(shop or ""), str(secret or ""))


def configured() -> bool:
    shop, secret = _creds()
    return bool(shop and secret)


def creds_diagnostics() -> Dict[str, Any]:
    """Публичная диагностика формата ключей (без самого секрета)."""
    fp = fingerprint()
    return {
        "configured": bool(fp.get("shop_id") and fp.get("secret_len")),
        "shop_id_set": bool(fp.get("shop_id")),
        "shop_id_digits": bool(fp.get("shop_id") and str(fp["shop_id"]).isdigit()),
        "shop_id_tail": fp.get("shop_id_tail") or "",
        "secret_set": bool(fp.get("secret_len")),
        "secret_prefix": fp.get("secret_prefix") or "",
        "secret_len": fp.get("secret_len") or 0,
        "secret_tail": fp.get("secret_tail") or "",
        "format_ok": bool(fp.get("format_ok")),
        "hint": (
            None
            if fp.get("format_ok")
            else (
                "Нужны: shopId (только цифры) + секретный ключ API, "
                "начинающийся на test_ или live_ (длина ≥ 20)."
            )
        ),
    }


def status() -> Dict[str, Any]:
    site = _site()
    diag = creds_diagnostics()
    return {
        "ok": True,
        "provider": "yookassa",
        "mode": "fixed_price",
        "configured": diag["configured"],
        "shop_id_set": diag["shop_id_set"],
        "shop_id_tail": diag.get("shop_id_tail") or "",
        "secret_prefix": diag.get("secret_prefix") or "",
        "format_ok": diag.get("format_ok"),
        "return_url": site + "/?paid=1#start",
        "webhook_url": site + WEBHOOK_PATH,
        "self_serve": True,
        "currency": "RUB",
        "packages": list_packages(),
        "brands": ["NeoBrain", "5MB2"],
        "hint": diag.get("hint")
        or (
            None
            if diag["configured"]
            else "Панель → Настройки: shopId и секретный ключ. Webhook: "
            + site
            + WEBHOOK_PATH
        ),
    }


def list_packages() -> list:
    return [
        {
            "id": p["id"],
            "brand": p["brand"],
            "name": p["name"],
            "price_rub": p["price_rub"],
            "period": p["period"],
            "service_slug": p.get("service_slug") or "",
            "blurb": p.get("blurb") or "",
        }
        for p in PACKAGES.values()
    ]


def _package_info(package_id: str) -> Optional[Dict[str, Any]]:
    return PACKAGES.get((package_id or "").strip().lower())


def _auth_header() -> str:
    shop, secret = _creds()
    raw = f"{shop}:{secret}".encode("ascii", errors="strict")
    return "Basic " + base64.b64encode(raw).decode("ascii")


def _friendly_http_error(code: int, detail: str) -> str:
    low = (detail or "").lower()
    if code == 401 or "invalid_credentials" in low:
        if "authentication type is not allowed" in low:
            return (
                "ЮKassa: Authentication type is not allowed — магазин не принимает эти ключи для API. "
                "В ЛК ЮKassa нужен магазин с интеграцией «API» и именно «Секретный ключ» "
                "(test_… / live_…), не OAuth-токен и не ключ SDK. "
                "Выпустите новый секретный ключ и вставьте в панель вместе с shopId (цифры). "
                "Проверка: Настройки → «Проверить ЮKassa»."
            )
        return (
            "ЮKassa HTTP 401: неверные shopId/секретный ключ. "
            "Пересохраните оба поля в панели (секрет начинается с test_ или live_). "
            "Частая причина — автозаполнение браузера подменило секрет. "
            f"Ответ ЮKassa: {detail[:240]}"
        )
    return f"ЮKassa HTTP {code}: {detail[:500]}"


def _yookassa_request(
    method: str,
    path: str,
    *,
    shop: str,
    secret: str,
    body: Optional[Dict[str, Any]] = None,
    idem: str = "",
) -> Dict[str, Any]:
    """Прямой HTTPS к api.yookassa.ru через http.client (без сюрпризов urllib)."""
    import http.client

    auth = base64.b64encode(f"{shop}:{secret}".encode("ascii")).decode("ascii")
    headers = {
        "Authorization": f"Basic {auth}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }
    if idem:
        headers["Idempotence-Key"] = idem
    payload = json.dumps(body).encode("utf-8") if body is not None else None
    conn = http.client.HTTPSConnection("api.yookassa.ru", timeout=25)
    try:
        conn.request(method, path, body=payload, headers=headers)
        resp = conn.getresponse()
        raw = resp.read().decode("utf-8", errors="replace")
        www = resp.getheader("WWW-Authenticate") or ""
        try:
            data = json.loads(raw) if raw else {}
        except Exception:
            data = {"raw": raw[:500]}
        return {
            "http_status": resp.status,
            "www_authenticate": www,
            "data": data,
            "raw": raw[:700],
        }
    finally:
        conn.close()


def verify_connection(
    shop_id: str = "",
    secret_key: str = "",
) -> Dict[str, Any]:
    """GET /v3/me — проверка, что ключи реально принимаются ЮKassa."""
    if shop_id or secret_key:
        shop, secret = normalize_creds(shop_id, secret_key)
    else:
        shop, secret = _creds()
    diag = fingerprint(shop, secret)
    diag["shop_id_digits"] = shop.isdigit() if shop else False
    if not shop or not secret:
        return {"ok": False, "error": "Нет shopId или секретного ключа", **diag}
    if not shop.isdigit():
        return {
            "ok": False,
            "error": "shopId должен быть числом (скопируйте «shopId» из ЛК ЮKassa)",
            **diag,
        }
    if not secret.startswith(("test_", "live_")):
        return {
            "ok": False,
            "error": (
                "Секретный ключ должен начинаться с test_ или live_. "
                f"Сейчас prefix={diag.get('secret_prefix')!r}, длина={diag.get('secret_len')}."
            ),
            **diag,
        }
    if len(secret) < 20:
        return {
            "ok": False,
            "error": f"Секрет слишком короткий ({len(secret)} символов) — похоже, скопировали не целиком.",
            **diag,
        }
    try:
        result = _yookassa_request("GET", "/v3/me", shop=shop, secret=secret)
    except Exception as exc:
        return {"ok": False, "error": f"Сеть/ЮKassa недоступны: {exc}", **diag}

    status = int(result.get("http_status") or 0)
    data = result.get("data") or {}
    if status == 200 and isinstance(data, dict) and not data.get("type") == "error":
        account = data.get("account_id") or data.get("id") or ""
        return {
            "ok": True,
            "message": (
                f"ЮKassa приняла ключи. shopId={shop}, секрет {diag['secret_prefix']}…"
                f"{diag['secret_tail']} (длина {diag['secret_len']}). Можно оплачивать."
            ),
            "account_id": str(account),
            "shop_status": data.get("status"),
            "test": bool(data.get("test")) if "test" in data else secret.startswith("test_"),
            "me": {
                k: data.get(k)
                for k in ("account_id", "status", "test", "fiscalization_enabled")
                if k in data
            },
            **diag,
        }

    detail = result.get("raw") or json.dumps(data, ensure_ascii=False)
    www = result.get("www_authenticate") or ""
    err = _friendly_http_error(status or 401, detail)
    if www:
        err += f" WWW-Authenticate: {www}."
    return {
        "ok": False,
        "http_status": status,
        "error": err,
        "raw": detail[:400],
        "www_authenticate": www,
        **diag,
    }


def save_and_verify(shop_id: str, secret_key: str) -> Dict[str, Any]:
    """Сохранить пару ключей и сразу проверить через /v3/me."""
    shop, secret = normalize_creds(shop_id, secret_key)
    fp = fingerprint(shop, secret)
    if not fp.get("format_ok"):
        return {
            "ok": False,
            "saved": False,
            "error": (
                "Ключи не похожи на ЮKassa API: shopId=цифры, секрет test_/live_ "
                f"длиной ≥20. Сейчас: shop={shop!r} (len={fp['shop_id_len']}), "
                f"prefix={fp['secret_prefix']!r}, secret_len={fp['secret_len']}."
            ),
            **fp,
        }
    try:
        import owner_settings as osset

        osset.update_settings({
            "yookassa_shop_id": shop,
            "yookassa_secret_key": secret,
        })
    except Exception as exc:
        return {"ok": False, "saved": False, "error": f"Не сохранилось: {exc}", **fp}

    # После save читаем обратно с диска и сверяем отпечаток
    stored_shop, stored_secret = _creds()
    stored_fp = fingerprint(stored_shop, stored_secret)
    if stored_fp.get("secret_tail") != fp.get("secret_tail") or stored_shop != shop:
        return {
            "ok": False,
            "saved": False,
            "error": (
                "После записи отпечаток на диске не совпал с тем, что отправили. "
                f"Отправили …{fp.get('secret_tail')} len={fp.get('secret_len')}, "
                f"на диске …{stored_fp.get('secret_tail')} len={stored_fp.get('secret_len')}."
            ),
            "sent": fp,
            "stored": stored_fp,
        }

    checked = verify_connection(shop_id=stored_shop, secret_key=stored_secret)
    checked["saved"] = True
    checked["stored"] = stored_fp
    return checked


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


def _create_yookassa_payment(
    *,
    email: str,
    amount: int,
    description: str,
    return_url: str,
    metadata: Dict[str, str],
    receipt_title: str,
) -> Dict[str, Any]:
    if not configured():
        site = _site()
        return {
            "ok": False,
            "mode": "not_configured",
            "error": "ЮKassa ещё не подключена в панели (shopId + секретный ключ).",
            "amount_rub": amount,
            "rekvizity_url": site + "/rekvizity/",
        }
    amount_value = f"{int(amount)}.00"
    body: Dict[str, Any] = {
        "amount": {"value": amount_value, "currency": "RUB"},
        "confirmation": {"type": "redirect", "return_url": return_url},
        "capture": True,
        "description": description[:128],
        "metadata": metadata,
    }
    if _send_receipt():
        body["receipt"] = {
            "customer": {"email": email},
            "items": [
                {
                    "description": receipt_title[:128],
                    "quantity": "1.00",
                    "amount": {"value": amount_value, "currency": "RUB"},
                    "vat_code": 1,
                    "payment_mode": "full_payment",
                    "payment_subject": "service",
                }
            ],
        }
    diag = creds_diagnostics()
    if not diag.get("format_ok"):
        raise RuntimeError(
            diag.get("hint")
            or "Неверный формат ключей ЮKassa. Панель → Настройки → Сохранить и проверить ЮKassa."
        )
    shop, secret = _creds()
    idem = str(uuid.uuid4())
    try:
        result = _yookassa_request(
            "POST",
            "/v3/payments",
            shop=shop,
            secret=secret,
            body=body,
            idem=idem,
        )
    except UnicodeEncodeError as exc:
        raise RuntimeError(
            "В ключах ЮKassa есть недопустимые символы. "
            "Вставьте shopId и секрет заново из ЛК (латиница/цифры)."
        ) from exc
    except Exception as exc:
        raise RuntimeError(f"ЮKassa недоступна: {exc}") from exc

    status = int(result.get("http_status") or 0)
    data = result.get("data") or {}
    if status >= 400 or (isinstance(data, dict) and data.get("type") == "error"):
        detail = result.get("raw") or json.dumps(data, ensure_ascii=False)
        raise RuntimeError(_friendly_http_error(status or 401, detail))

    conf = (data.get("confirmation") or {}).get("confirmation_url") or ""
    if not conf:
        raise RuntimeError("ЮKassa не вернула confirmation_url")
    return {
        "ok": True,
        "mode": "yookassa",
        "payment_id": data.get("id"),
        "confirmation_url": conf,
        "amount_rub": int(amount),
        "description": description,
        "status": data.get("status") or "pending",
    }


def create_payment(
    *,
    email: str,
    plan_id: str,
    return_url: str = "",
) -> Dict[str, Any]:
    """NeoBrain: фиксированный тариф Starter/Pro."""
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
    ret = (return_url or f"{site}/?paid=1#start").strip()
    if not ret.startswith("http"):
        ret = f"{site}/?paid=1#start"
    description = f"NeoBrain {plan_name} — 1 месяц ({amount} ₽)"

    result = _create_yookassa_payment(
        email=email,
        amount=amount,
        description=description,
        return_url=ret,
        metadata={
            "email": email,
            "plan": plan_id,
            "package": "",
            "brand": "NeoBrain",
            "period": "month",
            "amount_rub": str(amount),
        },
        receipt_title=f"NeoBrain {plan_name} — 1 месяц",
    )
    if not result.get("ok"):
        result["plan"] = plan_id
        return result
    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": result.get("status") or "pending",
        "payment_id": result.get("payment_id"),
        "email": email,
        "brand": "NeoBrain",
        "plan": plan_id,
        "amount_rub": amount,
        "confirmation_url": result.get("confirmation_url"),
    })
    result["plan"] = plan_id
    result["plan_name"] = plan_name
    return result


def create_package_payment(
    *,
    email: str,
    package_id: str,
    return_url: str = "",
) -> Dict[str, Any]:
    """5MB2: фиксированный пакет услуги → ЮKassa."""
    email = (email or "").strip().lower()
    package_id = (package_id or "").strip().lower()
    info = _package_info(package_id)
    if not email or "@" not in email:
        raise ValueError("Нужен email")
    if not info:
        raise ValueError("Неизвестный пакет. Доступны: " + ", ".join(PACKAGES.keys()))

    amount = int(info["price_rub"])
    name = str(info["name"])
    site_5mb2 = os.environ.get("MB2_SITE_URL", "https://5mb2.ru").rstrip("/")
    ret = (return_url or f"{site_5mb2}/spasibo/?paid=1&package={package_id}").strip()
    if not ret.startswith("http"):
        ret = f"{site_5mb2}/spasibo/?paid=1&package={package_id}"
    period = "1 месяц" if info.get("period") == "month" else "разово"
    description = f"5MB2 {name} — {period} ({amount} ₽)"

    result = _create_yookassa_payment(
        email=email,
        amount=amount,
        description=description,
        return_url=ret,
        metadata={
            "email": email,
            "plan": "",
            "package": package_id,
            "brand": "5MB2",
            "period": str(info.get("period") or "once"),
            "service_slug": str(info.get("service_slug") or ""),
            "amount_rub": str(amount),
        },
        receipt_title=f"5MB2 {name}",
    )
    if not result.get("ok"):
        result["package"] = package_id
        result["rekvizity_url"] = site_5mb2 + "/rekvizity/"
        return result
    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": result.get("status") or "pending",
        "payment_id": result.get("payment_id"),
        "email": email,
        "brand": "5MB2",
        "package": package_id,
        "amount_rub": amount,
        "confirmation_url": result.get("confirmation_url"),
    })
    result["package"] = package_id
    result["package_name"] = name
    return result


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
    shop, secret = _creds()
    result = _yookassa_request("GET", f"/v3/payments/{pid}", shop=shop, secret=secret)
    if int(result.get("http_status") or 0) >= 400:
        raise RuntimeError(_friendly_http_error(int(result["http_status"]), result.get("raw") or ""))
    data = result.get("data") or {}
    if not isinstance(data, dict):
        raise RuntimeError("ЮKassa вернула неожиданный ответ")
    return data


def _notify_owner_5mb2(email: str, package_id: str, amount: str, payment_id: str) -> None:
    info = _package_info(package_id) or {}
    name = info.get("name") or package_id
    try:
        import mailer

        owner = os.environ.get("OWNER_EMAIL", "").strip() or "hello@5mb2.ru"
        try:
            import owner_settings as osset

            owner = (osset.get_raw().get("owner_email") or owner).strip() or owner
        except Exception:
            pass
        mailer.send_mail(
            to=owner,
            subject=f"5MB2 оплата: {name}",
            body=(
                f"Оплачен пакет 5MB2.\n\n"
                f"Пакет: {name} ({package_id})\n"
                f"Сумма: {amount} ₽\n"
                f"Клиент: {email}\n"
                f"Payment ID: {payment_id}\n"
            ),
        )
    except Exception:
        pass


def handle_webhook(payload: Dict[str, Any]) -> Dict[str, Any]:
    """payment.succeeded → NeoBrain план или уведомление по пакету 5MB2."""
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
    brand = (meta.get("brand") or "NeoBrain").strip()
    plan = (meta.get("plan") or "").strip().lower()
    package = (meta.get("package") or "").strip().lower()
    amount = (meta.get("amount_rub") or "").strip()

    if brand == "5MB2" or package.startswith("mb2-"):
        if not email or package not in PACKAGES:
            return {"ok": False, "error": "metadata email/package missing"}
        _notify_owner_5mb2(email, package, amount, payment_id)
        _append({
            "at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "status": "paid_5mb2",
            "payment_id": payment_id,
            "email": email,
            "brand": "5MB2",
            "package": package,
            "amount_rub": amount,
            "verified": True,
        })
        return {"ok": True, "brand": "5MB2", "package": package, "verified": True}

    if not email or plan not in {"starter", "pro"}:
        return {"ok": False, "error": "metadata email/plan missing"}
    result = apply_paid_plan(email, plan)
    _append({
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "status": "activated",
        "payment_id": payment_id,
        "email": email,
        "brand": "NeoBrain",
        "plan": plan,
        "verified": True,
    })
    return {"ok": True, "activated": result, "verified": True}
