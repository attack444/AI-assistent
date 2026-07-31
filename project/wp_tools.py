"""
WordPress helpers: wp-config patch, SQL import, URL replace.
"""
from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple


def find_wp_config(site_root: Path) -> Optional[Path]:
    for candidate in (
        site_root / "wp-config.php",
        site_root / "wordpress" / "wp-config.php",
        site_root / "public_html" / "wp-config.php",
    ):
        if candidate.is_file():
            return candidate
    # shallow search
    for p in site_root.rglob("wp-config.php"):
        if "wp-content" in p.parts:
            continue
        return p
    return None


def _replace_define(text: str, key: str, value: str) -> Tuple[str, bool]:
    """Replace define('KEY', '...'); keep quoting style simple with single quotes."""
    # Escape for PHP single-quoted string
    safe = value.replace("\\", "\\\\").replace("'", "\\'")
    pattern = re.compile(
        rf"(define\s*\(\s*['\"]{re.escape(key)}['\"]\s*,\s*)(['\"].*?['\"]|[^,]+?)(\s*\))",
        re.IGNORECASE | re.DOTALL,
    )
    if pattern.search(text):
        new_text, n = pattern.subn(rf"\1'{safe}'\3", text, count=1)
        return new_text, n > 0
    # Insert before "That's all" or at end
    insert = f"define('{key}', '{safe}');\n"
    marker = re.search(r"/\*.*?(That's all|стоп|Stop editing).*?\*/", text, re.I | re.S)
    if marker:
        pos = marker.start()
        return text[:pos] + insert + text[pos:], True
    return text + "\n" + insert, True


def patch_wp_config(
    site_root: Path,
    *,
    db_name: str,
    db_user: str,
    db_password: str,
    db_host: str = "mysql",
    table_prefix: Optional[str] = None,
) -> Dict[str, Any]:
    cfg = find_wp_config(site_root)
    if not cfg:
        raise FileNotFoundError("wp-config.php не найден в сайте")
    text = cfg.read_text(encoding="utf-8", errors="ignore")
    backup = cfg.with_suffix(".php.bak-aihelper")
    if not backup.exists():
        backup.write_text(text, encoding="utf-8")

    changed = []
    for key, val in (
        ("DB_NAME", db_name),
        ("DB_USER", db_user),
        ("DB_PASSWORD", db_password),
        ("DB_HOST", db_host),
    ):
        text, ok = _replace_define(text, key, val)
        if ok:
            changed.append(key)

    if table_prefix is not None:
        # $table_prefix = 'wp_';
        pref_re = re.compile(r"(\$table_prefix\s*=\s*)(['\"].*?['\"])(\s*;)")
        if pref_re.search(text):
            safe = table_prefix.replace("\\", "\\\\").replace("'", "\\'")
            text, n = pref_re.subn(rf"\1'{safe}'\3", text, count=1)
            if n:
                changed.append("table_prefix")

    cfg.write_text(text, encoding="utf-8")
    return {
        "ok": True,
        "path": str(cfg),
        "backup": str(backup),
        "changed": changed,
        "db_host": db_host,
        "db_name": db_name,
        "db_user": db_user,
    }


def read_wp_defines(site_root: Path) -> Dict[str, str]:
    cfg = find_wp_config(site_root)
    if not cfg:
        return {}
    text = cfg.read_text(encoding="utf-8", errors="ignore")
    out: Dict[str, str] = {}
    for key in ("DB_NAME", "DB_USER", "DB_PASSWORD", "DB_HOST"):
        m = re.search(
            rf"define\s*\(\s*['\"]{key}['\"]\s*,\s*['\"]([^'\"]*)['\"]",
            text,
            re.I,
        )
        if m:
            out[key] = m.group(1)
    m = re.search(r"\$table_prefix\s*=\s*['\"]([^'\"]*)['\"]", text)
    if m:
        out["table_prefix"] = m.group(1)
    return out


def mysql_connect_params() -> Dict[str, Any]:
    return {
        "host": os.environ.get("MYSQL_HOST", "mysql"),
        "port": int(os.environ.get("MYSQL_PORT", "3306")),
        "user": os.environ.get("MYSQL_USER", "wp"),
        "password": os.environ.get("MYSQL_PASSWORD", ""),
        "database": os.environ.get("MYSQL_DATABASE", "wordpress"),
        "root_password": os.environ.get("MYSQL_ROOT_PASSWORD", ""),
    }


def _password_variants(password: str) -> List[Any]:
    """pymysql encodes str as latin-1; try encodings that match how MySQL stored the hash."""
    if password is None:
        return [""]
    if isinstance(password, bytes):
        return [password]
    text = str(password)
    out: List[Any] = []
    seen = set()

    def add(val: Any) -> None:
        key = val if isinstance(val, (bytes, bytearray)) else ("str", val)
        if key in seen:
            return
        seen.add(key)
        out.append(val)

    # latin-1-safe → pass str (pymysql default)
    try:
        text.encode("latin-1")
        add(text)
    except UnicodeEncodeError:
        pass
    add(text.encode("utf-8"))
    try:
        add(text.encode("cp1251"))
    except UnicodeEncodeError:
        pass
    add(text.encode("utf-8", errors="replace"))
    return out or [""]


def _pymysql():
    try:
        import pymysql
        return pymysql
    except ImportError as exc:
        raise RuntimeError(
            "Нет pymysql. Добавь в requirements и пересобери Docker."
        ) from exc


def _connect_raw(
    *,
    user: str,
    password: Any,
    database: Optional[str] = None,
    host: Optional[str] = None,
    port: Optional[int] = None,
):
    pymysql = _pymysql()
    params = mysql_connect_params()
    kwargs = dict(
        host=host or params["host"],
        port=port or params["port"],
        user=user,
        password=password if password is not None else "",
        charset="utf8mb4",
        autocommit=True,
        connect_timeout=8,
    )
    if database:
        kwargs["database"] = database
    return pymysql.connect(**kwargs)


def _try_login(
    user: str,
    password: str,
    database: Optional[str] = None,
) -> Tuple[Any, Optional[str]]:
    """Return (connection, None) or (None, error)."""
    last_err = "unknown"
    for pwd in _password_variants(password):
        try:
            conn = _connect_raw(user=user, password=pwd, database=database)
            return conn, None
        except Exception as exc:
            last_err = str(exc)
            # wrong password / unknown db — try next encoding
            continue
    # retry without selecting database (db may not exist yet)
    if database:
        for pwd in _password_variants(password):
            try:
                conn = _connect_raw(user=user, password=pwd, database=None)
                return conn, None
            except Exception as exc:
                last_err = str(exc)
                continue
    return None, last_err


def _root_password_candidates() -> List[str]:
    params = mysql_connect_params()
    candidates = [
        params.get("root_password") or "",
        "root_change_me",
        "смени_root_пароль",
        "strong_root_pass",
        "root",
        "password",
        "",
    ]
    # unique preserve order
    seen = set()
    out = []
    for c in candidates:
        if c in seen:
            continue
        seen.add(c)
        out.append(c)
    return out


def _sql_quote(value: str) -> str:
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def ensure_mysql_user(*, force: bool = False) -> Dict[str, Any]:
    """Make sure MYSQL_USER can login with MYSQL_PASSWORD. Fix 1045 via root if needed."""
    params = mysql_connect_params()
    user = params["user"]
    password = params["password"] or "wp_change_me"
    database = params["database"]

    # Fast path
    if not force:
        conn, err = _try_login(user, password, database)
        if conn is not None:
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT 1")
                conn.close()
                return {
                    "ok": True,
                    "healed": False,
                    "user": user,
                    "database": database,
                    "message": "MySQL OK",
                }
            except Exception:
                try:
                    conn.close()
                except Exception:
                    pass

    root_errors: List[str] = []
    root_conn = None
    used_root_pass = None
    env_root = params.get("root_password") or ""
    for root_pass in _root_password_candidates():
        conn, err = _try_login("root", root_pass, None)
        if conn is not None:
            root_conn = conn
            used_root_pass = root_pass
            break
        root_errors.append(f"root:{err}")

    if root_conn is None:
        return {
            "ok": False,
            "healed": False,
            "user": user,
            "database": database,
            "error": (
                "1045: не удалось войти ни как wp, ни как root. "
                "Пароль в томе MySQL не совпадает с .env. "
                "На VPS: bash /opt/ai-helper/project/deploy/reset-mysql-password.sh --reinit"
            ),
            "root_errors": root_errors[:5],
            "hint": (
                "bash /opt/ai-helper/project/deploy/reset-mysql-password.sh --reinit"
            ),
        }

    try:
        uq = _sql_quote(user)
        pq = _sql_quote(password)
        db_ident = "`" + database.replace("`", "``") + "`"
        stmts = [
            f"CREATE DATABASE IF NOT EXISTS {db_ident} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            f"CREATE USER IF NOT EXISTS {uq}@'%' IDENTIFIED BY {pq}",
            f"ALTER USER {uq}@'%' IDENTIFIED BY {pq}",
            f"CREATE USER IF NOT EXISTS {uq}@'localhost' IDENTIFIED BY {pq}",
            f"ALTER USER {uq}@'localhost' IDENTIFIED BY {pq}",
            f"GRANT ALL PRIVILEGES ON {db_ident}.* TO {uq}@'%'",
            f"GRANT ALL PRIVILEGES ON {db_ident}.* TO {uq}@'localhost'",
            "FLUSH PRIVILEGES",
        ]
        # Also align root with current .env if we logged in with a fallback password
        if env_root and used_root_pass != env_root:
            rq = _sql_quote(env_root)
            stmts.extend(
                [
                    f"ALTER USER 'root'@'%' IDENTIFIED BY {rq}",
                    f"ALTER USER 'root'@'localhost' IDENTIFIED BY {rq}",
                    "FLUSH PRIVILEGES",
                ]
            )
        with root_conn.cursor() as cur:
            for sql in stmts:
                try:
                    cur.execute(sql)
                except Exception as exc:
                    msg = str(exc).lower()
                    if "exists" in msg or "duplicate" in msg:
                        continue
                    if sql.startswith("CREATE USER"):
                        continue
                    raise
        root_conn.close()
    except Exception as exc:
        try:
            root_conn.close()
        except Exception:
            pass
        return {
            "ok": False,
            "healed": False,
            "error": f"root вошёл, но сброс пароля не удался: {exc}",
            "user": user,
            "database": database,
        }

    conn2, err2 = _try_login(user, password, database)
    if conn2 is None:
        return {
            "ok": False,
            "healed": True,
            "error": f"Пароль сброшен, но вход {user} всё ещё fail: {err2}",
            "user": user,
            "database": database,
        }
    conn2.close()
    return {
        "ok": True,
        "healed": True,
        "user": user,
        "database": database,
        "message": f"MySQL починен: пользователь {user} → пароль из .env",
        "root_synced": bool(env_root and used_root_pass != env_root),
    }


def _get_connection(database: Optional[str] = None):
    params = mysql_connect_params()
    db = database or params["database"]
    conn, err = _try_login(params["user"], params["password"], db)
    if conn is not None:
        # ensure default database selected
        if db:
            try:
                with conn.cursor() as cur:
                    cur.execute(f"USE `{db.replace('`', '``')}`")
            except Exception:
                pass
        return conn

    heal = ensure_mysql_user(force=True)
    if not heal.get("ok"):
        raise RuntimeError(heal.get("error") or err or "MySQL 1045")

    conn2, err2 = _try_login(params["user"], params["password"], db)
    if conn2 is None:
        raise RuntimeError(err2 or "MySQL login failed after heal")
    if db:
        try:
            with conn2.cursor() as cur:
                cur.execute(f"USE `{db.replace('`', '``')}`")
        except Exception:
            pass
    return conn2


def test_db() -> Dict[str, Any]:
    params = mysql_connect_params()
    heal = ensure_mysql_user(force=False)
    if not heal.get("ok"):
        # one forced attempt
        heal = ensure_mysql_user(force=True)
    if not heal.get("ok"):
        return {
            "ok": False,
            "host": params["host"],
            "database": params["database"],
            "user": params["user"],
            "error": heal.get("error"),
            "hint": heal.get("hint"),
            "healed": heal.get("healed"),
        }
    try:
        conn = _get_connection()
        with conn.cursor() as cur:
            cur.execute("SELECT 1")
            cur.execute("SHOW TABLES")
            tables = [r[0] for r in cur.fetchall()]
        conn.close()
        return {
            "ok": True,
            "host": params["host"],
            "database": params["database"],
            "user": params["user"],
            "tables": len(tables),
            "sample_tables": tables[:15],
            "healed": bool(heal.get("healed")),
            "message": heal.get("message"),
        }
    except Exception as exc:
        return {
            "ok": False,
            "host": params["host"],
            "database": params["database"],
            "user": params["user"],
            "error": str(exc),
        }


def import_sql_file(sql_path: Path, database: Optional[str] = None) -> Dict[str, Any]:
    if not sql_path.is_file():
        raise FileNotFoundError(str(sql_path))
    # Always ensure credentials before import
    heal = ensure_mysql_user(force=False)
    if not heal.get("ok"):
        heal = ensure_mysql_user(force=True)
    if not heal.get("ok"):
        return {
            "ok": False,
            "statements": 0,
            "errors": [heal.get("error") or "MySQL 1045"],
            "hint": heal.get("hint"),
            "path": str(sql_path),
        }

    size = sql_path.stat().st_size
    raw = sql_path.read_bytes()
    text = None
    used_encoding = "utf-8"
    for enc in ("utf-8-sig", "utf-8", "cp1251", "latin-1"):
        try:
            text = raw.decode(enc)
            used_encoding = enc
            break
        except UnicodeDecodeError:
            continue
    if text is None:
        text = raw.decode("utf-8", errors="replace")
        used_encoding = "utf-8/replace"

    # Hosting dumps often have DEFINER=`user`@`host` that break import
    text = re.sub(
        r"DEFINER\s*=\s*`[^`]+`@`[^`]+`",
        "",
        text,
        flags=re.IGNORECASE,
    )
    text = re.sub(
        r"DEFINER\s*=\s*'[^']+'@'[^']+'",
        "",
        text,
        flags=re.IGNORECASE,
    )

    conn = _get_connection(database)
    statements = 0
    errors: List[str] = []
    buf: List[str] = []
    try:
        with conn.cursor() as cur:
            cur.execute("SET NAMES utf8mb4")
            cur.execute("SET FOREIGN_KEY_CHECKS=0")
            cur.execute("SET sql_mode='NO_ENGINE_SUBSTITUTION'")

        for line in text.splitlines(keepends=True):
            s = line.strip()
            if not s or s.startswith("--") or s.startswith("#"):
                continue
            if s.startswith("/*"):
                if "*/" in s:
                    continue
                continue
            if s.endswith("*/"):
                continue
            buf.append(line)
            if ";" in line:
                stmt = "".join(buf).strip()
                buf = []
                if not stmt or stmt == ";":
                    continue
                if stmt.startswith("/*") and stmt.endswith("*/"):
                    continue
                # Skip noisy session settings from phpMyAdmin
                upper = stmt.lstrip().upper()
                if upper.startswith(("LOCK TABLES", "UNLOCK TABLES", "SET @@")):
                    try:
                        with conn.cursor() as cur:
                            cur.execute(stmt)
                        statements += 1
                    except Exception:
                        pass
                    continue
                try:
                    with conn.cursor() as cur:
                        cur.execute(stmt)
                    statements += 1
                except Exception as exc:
                    msg = str(exc)
                    low = msg.lower()
                    if "already exists" in low or "unknown database" in low:
                        continue
                    errors.append(msg.encode("utf-8", errors="replace").decode("utf-8")[:200])
                    if len(errors) > 40:
                        break
        return {
            "ok": statements > 0 and len(errors) < 40,
            "statements": statements,
            "errors": errors[:10],
            "size_bytes": size,
            "path": str(sql_path),
            "encoding": used_encoding,
            "healed": bool(heal.get("healed")),
        }
    finally:
        try:
            with conn.cursor() as cur:
                cur.execute("SET FOREIGN_KEY_CHECKS=1")
        except Exception:
            pass
        conn.close()


def replace_site_url(old_url: str, new_url: str, table_prefix: str = "wp_") -> Dict[str, Any]:
    new_url = new_url.rstrip("/")
    if not new_url:
        raise ValueError("Нужен new_url")
    ensure_mysql_user(force=False)
    # Auto-detect old URL from DB when empty / AUTO
    if not old_url or str(old_url).strip().upper() in {"", "AUTO", "FROM_DB"}:
        detected = get_site_urls(table_prefix)
        urls = detected.get("urls") or {}
        old_url = str(urls.get("siteurl") or urls.get("home") or "").rstrip("/")
        if not old_url:
            raise ValueError(
                "Не удалось определить old_url из БД. Укажи вручную "
                "(обычно https://5mb2.ru — как на старом хостинге)."
            )
    old_url = old_url.rstrip("/")
    if old_url == new_url:
        return {
            "ok": True,
            "old_url": old_url,
            "new_url": new_url,
            "updated": {},
            "warning": "old_url == new_url — замена не нужна",
        }
    conn = _get_connection()
    updated = {}
    try:
        with conn.cursor() as cur:
            for opt in ("siteurl", "home"):
                cur.execute(
                    f"UPDATE `{table_prefix}options` SET option_value=%s WHERE option_name=%s",
                    (new_url, opt),
                )
                updated[opt] = cur.rowcount
            for table, col in (
                (f"{table_prefix}posts", "post_content"),
                (f"{table_prefix}posts", "guid"),
                (f"{table_prefix}postmeta", "meta_value"),
            ):
                try:
                    cur.execute(
                        f"UPDATE `{table}` SET `{col}` = REPLACE(`{col}`, %s, %s)",
                        (old_url, new_url),
                    )
                    updated[f"{table}.{col}"] = cur.rowcount
                except Exception as exc:
                    updated[f"{table}.{col}"] = f"skip: {exc}"
        return {
            "ok": True,
            "old_url": old_url,
            "new_url": new_url,
            "updated": updated,
            "warning": (
                "Сериализованные данные плагинов могут сломаться. "
                "При проблемах — плагин Better Search Replace."
            ),
        }
    finally:
        conn.close()


def get_site_urls(table_prefix: str = "wp_") -> Dict[str, Any]:
    try:
        conn = _get_connection()
    except Exception as exc:
        return {"ok": False, "error": str(exc), "table_prefix": table_prefix}
    try:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT option_name, option_value FROM `{table_prefix}options` "
                "WHERE option_name IN ('siteurl','home')"
            )
            rows = {r[0]: r[1] for r in cur.fetchall()}
        return {"ok": True, "urls": rows, "table_prefix": table_prefix}
    except Exception as exc:
        return {"ok": False, "error": str(exc), "table_prefix": table_prefix}
    finally:
        conn.close()


def wp_status(site_root: Path) -> Dict[str, Any]:
    cfg = find_wp_config(site_root)
    defines = read_wp_defines(site_root) if cfg else {}
    db = test_db()
    urls = None
    if db.get("ok"):
        prefix = defines.get("table_prefix", "wp_")
        urls = get_site_urls(prefix)
    return {
        "ok": True,
        "has_wp_config": bool(cfg),
        "wp_config": str(cfg) if cfg else None,
        "defines": {k: ("***" if k == "DB_PASSWORD" else v) for k, v in defines.items()},
        "db": db,
        "urls": urls,
        "is_wordpress": bool(cfg) or (site_root / "wp-content").is_dir(),
    }
