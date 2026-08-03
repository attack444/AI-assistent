"""OAuth Google / GitHub для кабинета NeoBrain."""
from __future__ import annotations

import json
import os
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any, Dict, Optional, Tuple

DATA_DIR = Path(os.environ.get("AI_HELPER_DATA", str(Path.home() / ".ai-helper")))
STATE_FILE = DATA_DIR / "oauth_states.json"


def _settings() -> Dict[str, str]:
    out = {
        "google_client_id": os.environ.get("GOOGLE_CLIENT_ID", "").strip(),
        "google_client_secret": os.environ.get("GOOGLE_CLIENT_SECRET", "").strip(),
        "github_client_id": os.environ.get("GITHUB_CLIENT_ID", "").strip(),
        "github_client_secret": os.environ.get("GITHUB_CLIENT_SECRET", "").strip(),
        "public_site_url": os.environ.get("PUBLIC_SITE_URL", "https://neobrain.site").rstrip("/"),
    }
    try:
        import owner_settings as osset

        raw = osset.get_raw()
        for k in list(out.keys()):
            if k == "public_site_url":
                out[k] = (raw.get("public_site_url") or out[k]).rstrip("/")
            else:
                out[k] = (raw.get(k) or out[k]).strip()
    except Exception:
        pass
    return out


def status() -> Dict[str, Any]:
    s = _settings()
    return {
        "ok": True,
        "google": bool(s["google_client_id"] and s["google_client_secret"]),
        "github": bool(s["github_client_id"] and s["github_client_secret"]),
    }


def _save_state(state: str, provider: str) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    data = {}
    if STATE_FILE.is_file():
        try:
            data = json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except Exception:
            data = {}
    now = time.time()
    data = {k: v for k, v in data.items() if float(v.get("exp") or 0) > now}
    data[state] = {"provider": provider, "exp": now + 600}
    STATE_FILE.write_text(json.dumps(data), encoding="utf-8")


def _pop_state(state: str) -> Optional[str]:
    if not STATE_FILE.is_file():
        return None
    try:
        data = json.loads(STATE_FILE.read_text(encoding="utf-8"))
    except Exception:
        return None
    item = data.pop(state, None)
    STATE_FILE.write_text(json.dumps(data), encoding="utf-8")
    if not item or float(item.get("exp") or 0) < time.time():
        return None
    return item.get("provider")


def start(provider: str) -> Dict[str, Any]:
    provider = (provider or "").lower().strip()
    s = _settings()
    state = secrets.token_urlsafe(24)
    _save_state(state, provider)
    site = s["public_site_url"]
    if provider == "google":
        if not (s["google_client_id"] and s["google_client_secret"]):
            raise ValueError("Google OAuth не настроен (панель → Настройки)")
        q = urllib.parse.urlencode({
            "client_id": s["google_client_id"],
            "redirect_uri": f"{site}/api/public/auth/oauth/google/callback",
            "response_type": "code",
            "scope": "openid email profile",
            "state": state,
            "access_type": "online",
            "prompt": "select_account",
        })
        return {"ok": True, "url": "https://accounts.google.com/o/oauth2/v2/auth?" + q}
    if provider == "github":
        if not (s["github_client_id"] and s["github_client_secret"]):
            raise ValueError("GitHub OAuth не настроен (панель → Настройки)")
        q = urllib.parse.urlencode({
            "client_id": s["github_client_id"],
            "redirect_uri": f"{site}/api/public/auth/oauth/github/callback",
            "scope": "read:user user:email",
            "state": state,
        })
        return {"ok": True, "url": "https://github.com/login/oauth/authorize?" + q}
    raise ValueError("provider: google|github")


def _http_json(url: str, *, data: Optional[bytes] = None, headers: Optional[Dict[str, str]] = None) -> dict:
    req = urllib.request.Request(url, data=data, headers=headers or {}, method="POST" if data else "GET")
    with urllib.request.urlopen(req, timeout=25) as resp:
        return json.loads(resp.read().decode("utf-8"))


def finish(provider: str, code: str, state: str) -> Dict[str, Any]:
    provider = (provider or "").lower().strip()
    saved = _pop_state(state or "")
    if saved != provider:
        raise PermissionError("OAuth state недействителен")
    s = _settings()
    site = s["public_site_url"]
    email = ""
    name = ""
    oauth_id = ""

    if provider == "google":
        token = _http_json(
            "https://oauth2.googleapis.com/token",
            data=urllib.parse.urlencode({
                "code": code,
                "client_id": s["google_client_id"],
                "client_secret": s["google_client_secret"],
                "redirect_uri": f"{site}/api/public/auth/oauth/google/callback",
                "grant_type": "authorization_code",
            }).encode(),
            headers={"Content-Type": "application/x-www-form-urlencoded"},
        )
        info = _http_json(
            "https://www.googleapis.com/oauth2/v2/userinfo",
            headers={"Authorization": "Bearer " + token["access_token"]},
        )
        email = (info.get("email") or "").lower()
        name = (info.get("name") or "")[:80]
        oauth_id = str(info.get("id") or "")
    elif provider == "github":
        token = _http_json(
            "https://github.com/login/oauth/access_token",
            data=urllib.parse.urlencode({
                "client_id": s["github_client_id"],
                "client_secret": s["github_client_secret"],
                "code": code,
                "redirect_uri": f"{site}/api/public/auth/oauth/github/callback",
            }).encode(),
            headers={
                "Content-Type": "application/x-www-form-urlencoded",
                "Accept": "application/json",
            },
        )
        access = token.get("access_token") or ""
        info = _http_json(
            "https://api.github.com/user",
            headers={
                "Authorization": "Bearer " + access,
                "Accept": "application/vnd.github+json",
                "User-Agent": "NeoBrain",
            },
        )
        oauth_id = str(info.get("id") or "")
        name = (info.get("name") or info.get("login") or "")[:80]
        email = (info.get("email") or "").lower()
        if not email:
            emails = _http_json(
                "https://api.github.com/user/emails",
                headers={
                    "Authorization": "Bearer " + access,
                    "Accept": "application/vnd.github+json",
                    "User-Agent": "NeoBrain",
                },
            )
            if isinstance(emails, list):
                for e in emails:
                    if e.get("primary") and e.get("email"):
                        email = e["email"].lower()
                        break
                if not email:
                    for e in emails:
                        if e.get("email"):
                            email = e["email"].lower()
                            break
    else:
        raise ValueError("provider")

    if not email:
        raise ValueError("Провайдер не вернул email")

    import public_users as pu

    return pu.login_or_register_oauth(
        email=email,
        name=name,
        provider=provider,
        oauth_id=oauth_id,
    )
