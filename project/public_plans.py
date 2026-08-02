"""Public platform plans & usage limits."""
from __future__ import annotations

import os
import threading
import time
from datetime import datetime, timezone
from typing import Any, Dict, Optional, Tuple

# plan_id -> limits (-1 = unlimited)
PLANS: Dict[str, Dict[str, Any]] = {
    "free": {
        "id": "free",
        "name": "Free",
        "price_rub": 0,
        "blurb": "Попробовать NeoBrain",
        "max_sites": 1,
        "chat_per_day": 30,
        "deploy_per_day": 5,
        "features": ["1 сайт", "30 сообщений / день", "5 деплоев / день", "DeepSeek"],
    },
    "starter": {
        "id": "starter",
        "name": "Starter",
        "price_rub": 990,
        "blurb": "Для личных проектов",
        "max_sites": 5,
        "chat_per_day": 300,
        "deploy_per_day": 30,
        "features": ["5 сайтов", "300 сообщений / день", "30 деплоев / день", "Приоритет поддержки"],
    },
    "pro": {
        "id": "pro",
        "name": "Pro",
        "price_rub": 2990,
        "blurb": "Для студий и клиентов",
        "max_sites": 25,
        "chat_per_day": 2000,
        "deploy_per_day": 100,
        "features": ["25 сайтов", "2000 сообщений / день", "100 деплоев / день", "Несколько моделей позже"],
    },
    "owner": {
        "id": "owner",
        "name": "Owner",
        "price_rub": 0,
        "blurb": "Владелец сервера",
        "max_sites": -1,
        "chat_per_day": -1,
        "deploy_per_day": -1,
        "features": ["Без лимитов"],
        "hidden": True,
    },
}

DEFAULT_PLAN = os.environ.get("PUBLIC_DEFAULT_PLAN", "free").strip() or "free"
OWNER_EMAIL = os.environ.get("OWNER_EMAIL", "").strip().lower()

_lock = threading.RLock()


def list_public_plans() -> list:
    return [
        {
            "id": p["id"],
            "name": p["name"],
            "price_rub": p["price_rub"],
            "blurb": p["blurb"],
            "features": p["features"],
            "max_sites": p["max_sites"],
            "chat_per_day": p["chat_per_day"],
            "deploy_per_day": p["deploy_per_day"],
        }
        for p in PLANS.values()
        if not p.get("hidden")
    ]


def normalize_plan(plan_id: str) -> str:
    pid = (plan_id or DEFAULT_PLAN).strip().lower()
    if pid not in PLANS:
        return DEFAULT_PLAN if DEFAULT_PLAN in PLANS else "free"
    return pid


def plan_for_email(email: str, stored_plan: str = "") -> str:
    email = (email or "").strip().lower()
    if OWNER_EMAIL and email == OWNER_EMAIL:
        return "owner"
    return normalize_plan(stored_plan or DEFAULT_PLAN)


def limits_for(plan_id: str) -> Dict[str, Any]:
    return dict(PLANS[normalize_plan(plan_id)])


def _day_key() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d")


def ensure_usage(user_record: dict) -> dict:
    """Mutate user_record usage counters for today."""
    usage = dict(user_record.get("usage") or {})
    day = _day_key()
    if usage.get("day") != day:
        usage = {"day": day, "chat": 0, "deploy": 0}
    user_record["usage"] = usage
    return usage


def check_and_bump(
    user_record: dict,
    kind: str,
    plan_id: str,
) -> Tuple[bool, str, dict]:
    """
    kind: chat | deploy | site
    Returns (ok, error, usage_snapshot)
    """
    plan = limits_for(plan_id)
    usage = ensure_usage(user_record)

    if kind == "site":
        max_sites = int(plan.get("max_sites") or 0)
        sites = list(user_record.get("sites") or [])
        if max_sites >= 0 and len(sites) >= max_sites:
            return (
                False,
                f"Лимит тарифа {plan['name']}: максимум {max_sites} сайт(ов). Нужен апгрейд.",
                usage,
            )
        return True, "", usage

    key = "chat" if kind == "chat" else "deploy"
    limit_key = "chat_per_day" if kind == "chat" else "deploy_per_day"
    limit = int(plan.get(limit_key) or 0)
    if limit < 0:
        return True, "", usage
    used = int(usage.get(key) or 0)
    if used >= limit:
        return (
            False,
            f"Дневной лимит тарифа {plan['name']}: {limit} ({kind}). Завтра обновится или апгрейд.",
            usage,
        )
    usage[key] = used + 1
    user_record["usage"] = usage
    return True, "", usage


def usage_public(user_record: dict, plan_id: str) -> dict:
    plan = limits_for(plan_id)
    usage = ensure_usage(user_record)
    sites = list(user_record.get("sites") or [])
    return {
        "plan": {
            "id": plan["id"],
            "name": plan["name"],
            "price_rub": plan["price_rub"],
        },
        "limits": {
            "max_sites": plan["max_sites"],
            "chat_per_day": plan["chat_per_day"],
            "deploy_per_day": plan["deploy_per_day"],
        },
        "usage": {
            "day": usage.get("day"),
            "chat": int(usage.get("chat") or 0),
            "deploy": int(usage.get("deploy") or 0),
            "sites": len(sites),
        },
    }
