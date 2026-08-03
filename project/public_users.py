"""Public platform users — register/login, separate from panel auth."""
from __future__ import annotations

import hashlib
import hmac
import json
import os
import re
import secrets
import threading
import time
from collections import defaultdict, deque
from pathlib import Path
from typing import Any, Deque, Dict, List, Optional, Tuple

USERS_DIR = Path(os.environ.get("PUBLIC_USERS_DIR", "/root/.ai-helper/public-users"))
USERS_FILE = USERS_DIR / "users.json"
SESSIONS_FILE = USERS_DIR / "sessions.json"

# Require login for public chat/deploy (1=yes). Set 0 to allow anonymous.
AUTH_REQUIRED = os.environ.get("PUBLIC_AUTH_REQUIRED", "1").strip() not in {"0", "false", "no"}
# Guest chat from embed widget (5mb2 etc.) without platform login. Deploy still needs auth.
WIDGET_GUEST = os.environ.get("PUBLIC_WIDGET_GUEST", "1").strip().lower() not in {
    "0", "false", "no", "off",
}

_EMAIL_RE = re.compile(r"^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$")
_lock = threading.RLock()

_reg_hits: Dict[str, Deque[float]] = defaultdict(deque)
_login_hits: Dict[str, Deque[float]] = defaultdict(deque)
REG_LIMIT = int(os.environ.get("PUBLIC_REG_RATE_LIMIT", "5"))
REG_WINDOW = int(os.environ.get("PUBLIC_REG_RATE_WINDOW", "3600"))
LOGIN_LIMIT = int(os.environ.get("PUBLIC_LOGIN_RATE_LIMIT", "20"))
LOGIN_WINDOW = int(os.environ.get("PUBLIC_LOGIN_RATE_WINDOW", "900"))
SESSION_DAYS = int(os.environ.get("PUBLIC_SESSION_DAYS", "30"))

_COMMON_PASSWORDS = {
    "password", "password1", "12345678", "123456789", "qwerty123",
    "11111111", "abcdefgh", "neo12345", "admin123", "letmein1",
}


def _now() -> float:
    return time.time()


def validate_password(password: str) -> None:
    """Пароль задаёт пользователь сам — не генерируем."""
    password = password or ""
    if len(password) < 8:
        raise ValueError("Пароль минимум 8 символов")
    if len(password) > 200:
        raise ValueError("Пароль слишком длинный")
    if password.lower() in _COMMON_PASSWORDS:
        raise ValueError("Слишком простой пароль — придумайте другой")
    if password.isdigit():
        raise ValueError("Пароль не должен состоять только из цифр")


def _rate_hit(store: Dict[str, Deque[float]], key: str, limit: int, window: int, label: str) -> Tuple[bool, str]:
    now = _now()
    with _lock:
        q = store[key or "unknown"]
        while q and now - q[0] > window:
            q.popleft()
        if len(q) >= limit:
            return False, f"{label}: лимит {limit}"
        q.append(now)
    return True, ""


def check_register_rate(ip: str) -> Tuple[bool, str]:
    return _rate_hit(_reg_hits, ip, REG_LIMIT, REG_WINDOW, "Регистрации")


def check_login_rate(ip: str, email: str = "") -> Tuple[bool, str]:
    ok, why = _rate_hit(_login_hits, "ip:" + (ip or "unknown"), LOGIN_LIMIT, LOGIN_WINDOW, "Входы")
    if not ok:
        return ok, why
    if email:
        return _rate_hit(_login_hits, "em:" + email.lower(), LOGIN_LIMIT, LOGIN_WINDOW, "Входы")
    return True, ""


def _hash_password(password: str, salt: Optional[bytes] = None) -> Tuple[str, str]:
    if salt is None:
        salt = secrets.token_bytes(16)
    digest = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt, 120_000)
    return digest.hex(), salt.hex()


def _verify_password(password: str, salt_hex: str, hash_hex: str) -> bool:
    try:
        salt = bytes.fromhex(salt_hex)
    except ValueError:
        return False
    digest, _ = _hash_password(password, salt)
    return hmac.compare_digest(digest, hash_hex)


def _hash_token(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def _load_json(path: Path, default: Any) -> Any:
    if not path.is_file():
        return default
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default


def _save_json(path: Path, data: Any) -> None:
    USERS_DIR.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp")
    tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    tmp.replace(path)


def _users() -> Dict[str, dict]:
    return _load_json(USERS_FILE, {})


def _sessions() -> Dict[str, dict]:
    return _load_json(SESSIONS_FILE, {})


def _public_user(u: dict) -> dict:
    import public_plans as pp

    email = u.get("email") or ""
    plan_id = pp.plan_for_email(email, u.get("plan") or "")
    # ensure usage day bucket exists (read-only snapshot)
    tmp = dict(u)
    pp.ensure_usage(tmp)
    return {
        "id": u.get("id"),
        "email": email,
        "name": u.get("name") or "",
        "created_at": u.get("created_at"),
        "plan": plan_id,
        "billing": pp.usage_public(tmp, plan_id),
    }


def register(email: str, password: str, name: str = "", ip: str = "") -> dict:
    email = (email or "").strip().lower()
    password = password or ""
    name = (name or "").strip()[:80]
    if not _EMAIL_RE.match(email):
        raise ValueError("Некорректный email")
    validate_password(password)

    ok, why = check_register_rate(ip)
    if not ok:
        raise PermissionError(why)

    with _lock:
        users = _users()
        if email in users:
            raise ValueError("Такой email уже зарегистрирован")
        pwd_hash, salt = _hash_password(password)
        uid = secrets.token_hex(8)
        import public_plans as pp

        plan = pp.plan_for_email(email, pp.DEFAULT_PLAN)
        users[email] = {
            "id": uid,
            "email": email,
            "name": name,
            "password_hash": pwd_hash,
            "salt": salt,
            "created_at": _now(),
            "sites": [],
            "plan": plan,
            "usage": {},
            "auth": "password",
        }
        _save_json(USERS_FILE, users)
        token = _create_session_unlocked(uid, email)
        return {"ok": True, "token": token, "user": _public_user(users[email])}


def login(email: str, password: str, ip: str = "") -> dict:
    email = (email or "").strip().lower()
    password = password or ""
    ok, why = check_login_rate(ip, email)
    if not ok:
        raise PermissionError(why)
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            raise PermissionError("Неверный email или пароль")
        if not u.get("password_hash") or not u.get("salt"):
            raise PermissionError(
                "Для этого аккаунта вход по паролю не задан — войдите через Google/GitHub "
                "или установите пароль в кабинете"
            )
        if not _verify_password(password, u.get("salt", ""), u.get("password_hash", "")):
            raise PermissionError("Неверный email или пароль")
        token = _create_session_unlocked(u["id"], email)
        return {"ok": True, "token": token, "user": _public_user(u)}


def change_password(email: str, old_password: str, new_password: str) -> dict:
    """Смена пароля в личном кабинете — только тот, что задал пользователь."""
    email = (email or "").strip().lower()
    validate_password(new_password)
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            raise ValueError("Пользователь не найден")
        # OAuth-only: old password may be empty → allow set first password
        has_pwd = bool(u.get("password_hash") and u.get("salt"))
        if has_pwd:
            if not _verify_password(old_password or "", u.get("salt", ""), u.get("password_hash", "")):
                raise PermissionError("Текущий пароль неверный")
        elif old_password:
            raise PermissionError("Текущий пароль неверный")
        pwd_hash, salt = _hash_password(new_password)
        u["password_hash"] = pwd_hash
        u["salt"] = salt
        u["auth"] = "password"
        u["password_changed_at"] = _now()
        users[email] = u
        _save_json(USERS_FILE, users)
        # сбросить другие сессии? оставляем текущую — клиент обновит UI
        return {"ok": True, "user": _public_user(u)}


def login_or_register_oauth(
    *,
    email: str,
    name: str,
    provider: str,
    oauth_id: str,
) -> dict:
    email = (email or "").strip().lower()
    if not _EMAIL_RE.match(email):
        raise ValueError("Некорректный email от провайдера")
    with _lock:
        users = _users()
        u = users.get(email)
        import public_plans as pp

        if not u:
            uid = secrets.token_hex(8)
            plan = pp.plan_for_email(email, pp.DEFAULT_PLAN)
            u = {
                "id": uid,
                "email": email,
                "name": (name or "")[:80],
                "password_hash": "",
                "salt": "",
                "created_at": _now(),
                "sites": [],
                "plan": plan,
                "usage": {},
                "auth": provider,
                "oauth": {provider: oauth_id},
            }
            users[email] = u
        else:
            oauth = dict(u.get("oauth") or {})
            if oauth_id:
                oauth[provider] = oauth_id
            u["oauth"] = oauth
            if name and not u.get("name"):
                u["name"] = name[:80]
            users[email] = u
        _save_json(USERS_FILE, users)
        token = _create_session_unlocked(u["id"], email)
        return {"ok": True, "token": token, "user": _public_user(u)}


def _create_session_unlocked(user_id: str, email: str) -> str:
    sessions = _sessions()
    # prune expired
    now = _now()
    sessions = {k: v for k, v in sessions.items() if float(v.get("expires_at") or 0) > now}
    token = secrets.token_urlsafe(32)
    sessions[_hash_token(token)] = {
        "user_id": user_id,
        "email": email,
        "created_at": now,
        "expires_at": now + SESSION_DAYS * 86400,
    }
    _save_json(SESSIONS_FILE, sessions)
    return token


def logout(token: str) -> dict:
    if not token:
        return {"ok": True}
    with _lock:
        sessions = _sessions()
        sessions.pop(_hash_token(token), None)
        _save_json(SESSIONS_FILE, sessions)
    return {"ok": True}


def user_from_token(token: str) -> Optional[dict]:
    token = (token or "").strip()
    if not token:
        return None
    with _lock:
        sessions = _sessions()
        sess = sessions.get(_hash_token(token))
        if not sess:
            return None
        if float(sess.get("expires_at") or 0) < _now():
            sessions.pop(_hash_token(token), None)
            _save_json(SESSIONS_FILE, sessions)
            return None
        users = _users()
        email = (sess.get("email") or "").lower()
        u = users.get(email)
        if not u or u.get("id") != sess.get("user_id"):
            return None
        return _public_user(u)


def attach_site(email: str, site_name: str) -> None:
    email = (email or "").strip().lower()
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return
        sites = list(u.get("sites") or [])
        if site_name not in sites:
            sites.append(site_name)
            u["sites"] = sites[-50:]
            users[email] = u
            _save_json(USERS_FILE, users)


def list_sites(email: str) -> List[str]:
    email = (email or "").strip().lower()
    with _lock:
        u = _users().get(email) or {}
        return list(u.get("sites") or [])


def set_plan(email: str, plan_id: str) -> dict:
    import public_plans as pp

    email = (email or "").strip().lower()
    plan_id = pp.normalize_plan(plan_id)
    if plan_id == "owner" and email != (pp.OWNER_EMAIL or "").lower():
        raise PermissionError("План owner только для OWNER_EMAIL")
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            raise ValueError("Пользователь не найден")
        u["plan"] = plan_id
        users[email] = u
        _save_json(USERS_FILE, users)
        return {"ok": True, "user": _public_user(u)}


def consume_quota(email: str, kind: str) -> Tuple[bool, str, dict]:
    """Check plan limits and bump daily usage. kind: chat|deploy|site"""
    import public_plans as pp

    email = (email or "").strip().lower()
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return False, "Нужен вход", {}
        plan_id = pp.plan_for_email(email, u.get("plan") or "")
        ok, why, usage = pp.check_and_bump(u, kind, plan_id)
        if ok and kind in {"chat", "deploy"}:
            users[email] = u
            _save_json(USERS_FILE, users)
        return ok, why, pp.usage_public(u, plan_id)


def bearer_token(handler) -> str:
    auth = handler.headers.get("Authorization", "") or ""
    if auth.lower().startswith("bearer "):
        return auth[7:].strip()
    return (handler.headers.get("X-User-Token") or "").strip()
