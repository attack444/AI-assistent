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


def _mysql_password(password: str):
    """pymysql does str.encode('latin1') — Cyrillic MYSQL_PASSWORD crashes.

    If password is not latin-1, pass UTF-8 bytes so pymysql skips that encode.
    Matches how MySQL Docker stores Unicode passwords from env.
    """
    if isinstance(password, bytes):
        return password
    text = password or ""
    try:
        text.encode("latin-1")
        return text
    except UnicodeEncodeError:
        return text.encode("utf-8")


def _get_connection(database: Optional[str] = None):
    try:
        import pymysql
    except ImportError as exc:
        raise RuntimeError(
            "Нет pymysql. Добавь в requirements и пересобери Docker."
        ) from exc
    params = mysql_connect_params()
    return pymysql.connect(
        host=params["host"],
        port=params["port"],
        user=params["user"],
        password=_mysql_password(params["password"]),
        database=database or params["database"],
        charset="utf8mb4",
        autocommit=True,
        connect_timeout=10,
    )


def test_db() -> Dict[str, Any]:
    params = mysql_connect_params()
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

    conn = _get_connection(database)
    statements = 0
    errors: List[str] = []
    buf: List[str] = []
    try:
        # Ensure connection talks utf8mb4
        with conn.cursor() as cur:
            cur.execute("SET NAMES utf8mb4")
            cur.execute("SET CHARACTER SET utf8mb4")

        for line in text.splitlines(keepends=True):
            s = line.strip()
            if not s or s.startswith("--"):
                continue
            if s.startswith("/*"):
                if "*/" in s:
                    continue
                # skip until end of block — handled loosely line by line
                continue
            if s.endswith("*/"):
                continue
            buf.append(line)
            if ";" in line:
                stmt = "".join(buf).strip()
                buf = []
                if not stmt or stmt == ";":
                    continue
                # skip pure comment blocks
                if stmt.startswith("/*") and stmt.endswith("*/"):
                    continue
                try:
                    with conn.cursor() as cur:
                        cur.execute(stmt)
                    statements += 1
                except Exception as exc:
                    msg = str(exc)
                    if "already exists" not in msg.lower():
                        # keep errors ASCII-safe for logs
                        errors.append(msg.encode("utf-8", errors="replace").decode("utf-8")[:200])
                        if len(errors) > 30:
                            break
        return {
            "ok": len(errors) == 0 or statements > 0,
            "statements": statements,
            "errors": errors[:10],
            "size_bytes": size,
            "path": str(sql_path),
            "encoding": used_encoding,
        }
    finally:
        conn.close()


def replace_site_url(old_url: str, new_url: str, table_prefix: str = "wp_") -> Dict[str, Any]:
    old_url = old_url.rstrip("/")
    new_url = new_url.rstrip("/")
    if not old_url or not new_url:
        raise ValueError("Нужны old_url и new_url")
    conn = _get_connection()
    updated = {}
    try:
        with conn.cursor() as cur:
            # options
            for opt in ("siteurl", "home"):
                cur.execute(
                    f"UPDATE `{table_prefix}options` SET option_value=%s WHERE option_name=%s",
                    (new_url, opt),
                )
                updated[opt] = cur.rowcount
            # rough search-replace in posts/content (serialized data may break — warn)
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
    conn = _get_connection()
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
