"""Настройки владельца NeoBrain — панель /settings, без коммита секретов в git."""
from __future__ import annotations

import json
import os
import threading
from pathlib import Path
from typing import Any, Dict, List

DATA_DIR = Path(os.environ.get("AI_HELPER_DATA", str(Path.home() / ".ai-helper")))
SETTINGS_FILE = DATA_DIR / "owner_settings.json"
_lock = threading.RLock()

# Ключи, которые можно править из панели
EDITABLE = {
    "owner_email",
    "public_site_url",
    "yookassa_shop_id",
    "yookassa_secret_key",
    "metrika_id",
    "ga4_id",
    "gtm_id",
    "gsc_verification",
    "yandex_webmaster_verification",
    "turnstile_site_key",
    "turnstile_secret_key",
    "smtp_host",
    "smtp_port",
    "smtp_user",
    "smtp_password",
    "brand_name",
    "google_client_id",
    "google_client_secret",
    "github_client_id",
    "github_client_secret",
}

SECRET_KEYS = {
    "yookassa_secret_key",
    "turnstile_secret_key",
    "smtp_password",
    "google_client_secret",
    "github_client_secret",
}

DEFAULTS: Dict[str, Any] = {
    "brand_name": "NeoBrain",
    "owner_email": os.environ.get("OWNER_EMAIL", ""),
    "public_site_url": os.environ.get("PUBLIC_SITE_URL", "https://neobrain.site"),
    "yookassa_shop_id": os.environ.get("YOOKASSA_SHOP_ID", ""),
    "yookassa_secret_key": os.environ.get("YOOKASSA_SECRET_KEY", ""),
    "metrika_id": os.environ.get("METRIKA_ID", "111275874"),
    "ga4_id": os.environ.get("GA4_ID", ""),
    "gtm_id": os.environ.get("GTM_ID", "GTM-5GWQ97XF"),
    "gsc_verification": os.environ.get("GSC_VERIFICATION", ""),
    "yandex_webmaster_verification": os.environ.get(
        "YANDEX_WEBMASTER_VERIFICATION", "1e58779d59cc0fce"
    ),
    "turnstile_site_key": os.environ.get("TURNSTILE_SITE_KEY", ""),
    "turnstile_secret_key": os.environ.get("TURNSTILE_SECRET_KEY", ""),
    "smtp_host": "",
    "smtp_port": "587",
    "smtp_user": "",
    "smtp_password": "",
    "google_client_id": os.environ.get("GOOGLE_CLIENT_ID", ""),
    "google_client_secret": os.environ.get("GOOGLE_CLIENT_SECRET", ""),
    "github_client_id": os.environ.get("GITHUB_CLIENT_ID", ""),
    "github_client_secret": os.environ.get("GITHUB_CLIENT_SECRET", ""),
}


def _load() -> Dict[str, Any]:
    data = dict(DEFAULTS)
    if SETTINGS_FILE.is_file():
        try:
            raw = json.loads(SETTINGS_FILE.read_text(encoding="utf-8"))
            if isinstance(raw, dict):
                for k, v in raw.items():
                    if k in EDITABLE:
                        data[k] = v
        except Exception:
            pass
    # env overrides empty file values for bootstrap
    for k, envk in (
        ("owner_email", "OWNER_EMAIL"),
        ("yookassa_shop_id", "YOOKASSA_SHOP_ID"),
        ("yookassa_secret_key", "YOOKASSA_SECRET_KEY"),
        ("turnstile_site_key", "TURNSTILE_SITE_KEY"),
        ("turnstile_secret_key", "TURNSTILE_SECRET_KEY"),
        ("metrika_id", "METRIKA_ID"),
        ("ga4_id", "GA4_ID"),
    ):
        if not data.get(k) and os.environ.get(envk, "").strip():
            data[k] = os.environ.get(envk, "").strip()
    return data


def _save(data: Dict[str, Any]) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    clean = {k: data.get(k, "") for k in EDITABLE}
    SETTINGS_FILE.write_text(
        json.dumps(clean, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def get_settings(*, mask_secrets: bool = True) -> Dict[str, Any]:
    with _lock:
        data = _load()
    out = dict(data)
    if mask_secrets:
        for k in SECRET_KEYS:
            v = str(out.get(k) or "")
            out[k] = ("••••" + v[-4:]) if len(v) > 4 else ("••••" if v else "")
            out[k + "_set"] = bool(v)
    out["yookassa_configured"] = bool(
        data.get("yookassa_shop_id") and data.get("yookassa_secret_key")
    )
    out["turnstile_configured"] = bool(
        data.get("turnstile_site_key") and data.get("turnstile_secret_key")
    )
    # host можно не задавать — mailer угадает для Яндекс 360
    out["smtp_configured"] = bool(data.get("smtp_user") and data.get("smtp_password"))
    out["oauth_google_configured"] = bool(
        data.get("google_client_id") and data.get("google_client_secret")
    )
    out["oauth_github_configured"] = bool(
        data.get("github_client_id") and data.get("github_client_secret")
    )
    return out


def get_raw() -> Dict[str, Any]:
    with _lock:
        return _load()


def update_settings(patch: Dict[str, Any]) -> Dict[str, Any]:
    with _lock:
        data = _load()
        for k, v in (patch or {}).items():
            if k not in EDITABLE:
                continue
            if k in SECRET_KEYS and isinstance(v, str) and v.startswith("••••"):
                continue  # не затирать маской из UI
            data[k] = "" if v is None else str(v).strip()
        _save(data)
    # sync critical into process env for payments module
    raw = get_raw()
    if raw.get("yookassa_shop_id"):
        os.environ["YOOKASSA_SHOP_ID"] = raw["yookassa_shop_id"]
    if raw.get("yookassa_secret_key"):
        os.environ["YOOKASSA_SECRET_KEY"] = raw["yookassa_secret_key"]
    if raw.get("owner_email"):
        os.environ["OWNER_EMAIL"] = raw["owner_email"]
    if raw.get("public_site_url"):
        os.environ["PUBLIC_SITE_URL"] = raw["public_site_url"]
    return get_settings(mask_secrets=True)


def public_config() -> Dict[str, Any]:
    """Публичные ключи для витрины/темы (без секретов)."""
    s = get_raw()
    return {
        "ok": True,
        "brand": s.get("brand_name") or "NeoBrain",
        "public_site_url": s.get("public_site_url") or "https://neobrain.site",
        "metrika_id": s.get("metrika_id") or "111275874",
        "ga4_id": s.get("ga4_id") or "",
        "gtm_id": s.get("gtm_id") or "GTM-5GWQ97XF",
        "gsc_verification": s.get("gsc_verification") or "",
        "yandex_webmaster_verification": s.get("yandex_webmaster_verification")
        or "1e58779d59cc0fce",
        "turnstile_site_key": s.get("turnstile_site_key") or "",
        "yookassa_ready": bool(s.get("yookassa_shop_id") and s.get("yookassa_secret_key")),
        "console_path": os.environ.get("PANEL_BASE_PATH", "/console") or "/console",
        "oauth_google": bool(s.get("google_client_id") and s.get("google_client_secret")),
        "oauth_github": bool(s.get("github_client_id") and s.get("github_client_secret")),
        "smtp_ready": bool(s.get("smtp_user") and s.get("smtp_password")),
    }
