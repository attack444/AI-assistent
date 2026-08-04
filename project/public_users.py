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
# Origins allowed to use widget guest chat. Body field `source=widget` alone must NOT
# bypass PUBLIC_AUTH_REQUIRED — only matching Origin/Referer may. Comma-separated.
# Empty = deny guest bypass (fail closed).
_DEFAULT_WIDGET_ORIGINS = "https://5mb2.ru,https://www.5mb2.ru"
WIDGET_ORIGINS = tuple(
    o.strip().rstrip("/")
    for o in os.environ.get("PUBLIC_WIDGET_ORIGINS", _DEFAULT_WIDGET_ORIGINS).split(",")
    if o.strip()
)

_EMAIL_RE = re.compile(r"^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$")
_lock = threading.RLock()

_reg_hits: Dict[str, Deque[float]] = defaultdict(deque)
REG_LIMIT = int(os.environ.get("PUBLIC_REG_RATE_LIMIT", "5"))
REG_WINDOW = int(os.environ.get("PUBLIC_REG_RATE_WINDOW", "3600"))
SESSION_DAYS = int(os.environ.get("PUBLIC_SESSION_DAYS", "30"))


def _now() -> float:
    return time.time()


def check_register_rate(ip: str) -> Tuple[bool, str]:
    now = _now()
    with _lock:
        q = _reg_hits[ip or "unknown"]
        while q and now - q[0] > REG_WINDOW:
            q.popleft()
        if len(q) >= REG_LIMIT:
            return False, f"Лимит регистраций: {REG_LIMIT} / час"
        q.append(now)
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
    """Load JSON object/list. Missing file → default. Corrupt existing file → raise.

    Returning {} on parse errors previously let register/consume_quota rewrite
    users.json and wipe every account.
    """
    if not path.is_file():
        return default
    try:
        raw = path.read_text(encoding="utf-8")
    except OSError as exc:
        raise RuntimeError(f"Не удалось прочитать {path.name}: {exc}") from exc
    if not raw.strip():
        raise RuntimeError(f"Файл {path.name} пуст — отказ от перезаписи (защита данных)")
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Файл {path.name} повреждён (JSON) — отказ от перезаписи") from exc
    if type(data) is not type(default):
        raise RuntimeError(
            f"Файл {path.name}: ожидался {type(default).__name__}, получен {type(data).__name__}"
        )
    return data


def _save_json(path: Path, data: Any) -> None:
    USERS_DIR.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp")
    tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    tmp.replace(path)


def widget_guest_allowed(handler) -> bool:
    """True only when widget guest mode is on AND request Origin/Referer is allowlisted."""
    if not WIDGET_GUEST:
        return False
    if not WIDGET_ORIGINS:
        return False
    origin = (handler.headers.get("Origin") or "").strip().rstrip("/")
    referer = (handler.headers.get("Referer") or "").strip()
    for allowed in WIDGET_ORIGINS:
        if origin and origin == allowed:
            return True
        if referer:
            if referer.rstrip("/") == allowed or referer.startswith(allowed + "/"):
                return True
    return False


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
    if len(password) < 8:
        raise ValueError("Пароль минимум 8 символов")
    if len(password) > 200:
        raise ValueError("Пароль слишком длинный")

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
        }
        _save_json(USERS_FILE, users)
        token = _create_session_unlocked(uid, email)
        return {"ok": True, "token": token, "user": _public_user(users[email])}


def login(email: str, password: str) -> dict:
    email = (email or "").strip().lower()
    password = password or ""
    with _lock:
        users = _users()
        u = users.get(email)
        if not u or not _verify_password(password, u.get("salt", ""), u.get("password_hash", "")):
            raise PermissionError("Неверный email или пароль")
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


def reserve_site_slot(email: str) -> Tuple[bool, str, str]:
    """Atomically reserve a site slot (pending) so parallel deploys cannot exceed max_sites.

    Returns (ok, error, reservation_id). reservation_id is "" on failure.
    """
    import public_plans as pp

    email = (email or "").strip().lower()
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return False, "Нужен вход", ""
        plan_id = pp.plan_for_email(email, u.get("plan") or "")
        ok, why, _ = pp.check_and_bump(u, "site", plan_id, bump=False)
        if not ok:
            return False, why, ""
        rid = "pending:" + secrets.token_hex(6)
        sites = list(u.get("sites") or [])
        sites.append(rid)
        u["sites"] = sites[-50:]
        users[email] = u
        _save_json(USERS_FILE, users)
        return True, "", rid


def commit_site_slot(email: str, reservation_id: str, site_name: str) -> None:
    email = (email or "").strip().lower()
    reservation_id = (reservation_id or "").strip()
    site_name = (site_name or "").strip()
    if not email or not reservation_id or not site_name:
        return
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return
        sites = list(u.get("sites") or [])
        replaced = False
        for i, s in enumerate(sites):
            if s == reservation_id:
                sites[i] = site_name
                replaced = True
                break
        if not replaced and site_name not in sites:
            sites.append(site_name)
        # drop duplicate pending leftovers for this name
        sites = [s for s in sites if s == site_name or s != reservation_id]
        # unique preserve order
        seen = set()
        uniq: List[str] = []
        for s in sites:
            if s in seen:
                continue
            seen.add(s)
            uniq.append(s)
        u["sites"] = uniq[-50:]
        users[email] = u
        _save_json(USERS_FILE, users)


def release_site_slot(email: str, reservation_id: str) -> None:
    email = (email or "").strip().lower()
    reservation_id = (reservation_id or "").strip()
    if not email or not reservation_id:
        return
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return
        sites = [s for s in list(u.get("sites") or []) if s != reservation_id]
        u["sites"] = sites
        users[email] = u
        _save_json(USERS_FILE, users)


def list_sites(email: str) -> List[str]:
    email = (email or "").strip().lower()
    with _lock:
        u = _users().get(email) or {}
        # hide in-flight reservations from cabinet UI
        return [s for s in list(u.get("sites") or []) if not str(s).startswith("pending:")]


def set_plan(email: str, plan_id: str) -> dict:
    import public_plans as pp

    email = (email or "").strip().lower()
    plan_id = pp.normalize_plan(plan_id)
    if plan_id == "owner" and email != pp.OWNER_EMAIL:
        raise PermissionError(
            "План owner можно назначить только OWNER_EMAIL (или оставь пустым OWNER_EMAIL)"
        )
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            raise ValueError("Пользователь не найден")
        u["plan"] = plan_id
        users[email] = u
        _save_json(USERS_FILE, users)
        return {"ok": True, "user": _public_user(u)}


def consume_quota(email: str, kind: str, *, bump: bool = True) -> Tuple[bool, str, dict]:
    """Check plan limits and optionally bump daily usage. kind: chat|deploy|site"""
    import public_plans as pp

    email = (email or "").strip().lower()
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return False, "Нужен вход", {}
        plan_id = pp.plan_for_email(email, u.get("plan") or "")
        ok, why, usage = pp.check_and_bump(u, kind, plan_id, bump=bump)
        if ok and bump and kind in {"chat", "deploy"}:
            users[email] = u
            _save_json(USERS_FILE, users)
        return ok, why, pp.usage_public(u, plan_id)


def refund_quota(email: str, kind: str) -> None:
    """Undo one daily bump (e.g. deploy failed after pre-check bump)."""
    import public_plans as pp

    email = (email or "").strip().lower()
    key = "chat" if kind == "chat" else "deploy"
    if kind not in {"chat", "deploy"}:
        return
    with _lock:
        users = _users()
        u = users.get(email)
        if not u:
            return
        usage = pp.ensure_usage(u)
        used = int(usage.get(key) or 0)
        if used > 0:
            usage[key] = used - 1
            u["usage"] = usage
            users[email] = u
            _save_json(USERS_FILE, users)


def bearer_token(handler) -> str:
    auth = handler.headers.get("Authorization", "") or ""
    if auth.lower().startswith("bearer "):
        return auth[7:].strip()
    return (handler.headers.get("X-User-Token") or "").strip()
