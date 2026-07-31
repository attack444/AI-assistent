"""hosting_tools.py — VPS / WordPress helpers for the panel agent."""
from __future__ import annotations

import os
import shutil
import subprocess
from pathlib import Path
from typing import Any, Dict, List, Optional


def _site_domain(root: Path) -> str:
    f = root / ".ai-helper-domain"
    if f.is_file():
        return f.read_text(encoding="utf-8", errors="ignore").strip()
    return ""


def build_site_card(project_root: Optional[Path]) -> str:
    """Compact site card for the system prompt."""
    if not project_root or not project_root.is_dir():
        return ""
    root = project_root.resolve()
    lines = [f"Карточка сайта: {root.name}", f"Путь: {root}"]
    domain = _site_domain(root)
    if domain:
        lines.append(f"Домен: {domain}")
    lines.append(f"URL панели: /sites/{root.name}/")

    has_index = any(
        (root / name).is_file()
        for name in ("index.html", "index.htm", "index.php")
    )
    lines.append(f"index: {'да' if has_index else 'нет'}")

    is_wp = (
        (root / "wp-config.php").is_file()
        or (root / "wp-load.php").is_file()
        or (root / "wp-content").is_dir()
    )
    lines.append(f"WordPress: {'да' if is_wp else 'нет'}")

    for nested_name in ("public_html", "www", "wordpress", "public"):
        nested = root / nested_name
        if nested.is_dir() and (
            (nested / "wp-config.php").is_file()
            or (nested / "index.php").is_file()
            or (nested / "index.html").is_file()
        ):
            lines.append(f"Возможный webroot внутри: {nested_name}/")
            break

    try:
        entries = sorted(root.iterdir(), key=lambda p: (not p.is_dir(), p.name.lower()))
        top = []
        for e in entries[:18]:
            if e.name.startswith("."):
                continue
            top.append(e.name + ("/" if e.is_dir() else ""))
        if top:
            lines.append("Корень: " + ", ".join(top))
    except OSError:
        pass

    if is_wp:
        try:
            import wp_tools as wpt
            st = wpt.wp_status(root)
            if st.get("wp_config"):
                lines.append(f"wp-config: {st['wp_config']}")
            defs = st.get("defines") or {}
            if defs.get("DB_NAME"):
                lines.append(
                    f"DB: {defs.get('DB_NAME')}@{defs.get('DB_HOST', '?')} "
                    f"prefix={defs.get('table_prefix', 'wp_')}"
                )
            db = st.get("db") or {}
            if db.get("ok"):
                lines.append(f"MySQL: ok, tables={db.get('tables', '?')}")
            elif db.get("error"):
                lines.append(f"MySQL: ошибка — {db.get('error')}")
            urls = (st.get("urls") or {}).get("urls") or {}
            if urls:
                lines.append(
                    f"siteurl={urls.get('siteurl', '?')} home={urls.get('home', '?')}"
                )
        except Exception as exc:
            lines.append(f"WP status: {exc}")

    return "\n".join(lines)


def site_status(path: str = ".") -> Dict[str, Any]:
    """Статус сайта: WordPress?, домен, index, БД, URL."""
    try:
        root = Path(path).expanduser().resolve()
        if not root.is_dir():
            return {"ok": False, "error": f"Не директория: {root}"}
        card = build_site_card(root)
        out: Dict[str, Any] = {
            "ok": True,
            "name": root.name,
            "path": str(root),
            "domain": _site_domain(root) or None,
            "has_index": any(
                (root / n).is_file() for n in ("index.html", "index.htm", "index.php")
            ),
            "is_wordpress": (
                (root / "wp-config.php").is_file()
                or (root / "wp-content").is_dir()
            ),
            "card": card,
        }
        try:
            import wp_tools as wpt
            if out["is_wordpress"] or wpt.find_wp_config(root):
                st = wpt.wp_status(root)
                out["wordpress"] = {
                    "has_wp_config": st.get("has_wp_config"),
                    "wp_config": st.get("wp_config"),
                    "defines": st.get("defines"),
                    "db": st.get("db"),
                    "urls": st.get("urls"),
                }
                out["is_wordpress"] = True
        except Exception as exc:
            out["wordpress_error"] = str(exc)
        return out
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def wp_replace_urls(
    new_url: str,
    old_url: str = "AUTO",
    table_prefix: str = "",
    path: str = ".",
) -> Dict[str, Any]:
    """Заменяет siteurl/home и URL в контенте WordPress."""
    try:
        import wp_tools as wpt
        root = Path(path).expanduser().resolve()
        defines = wpt.read_wp_defines(root)
        prefix = table_prefix or defines.get("table_prefix") or "wp_"
        result = wpt.replace_site_url(old_url or "AUTO", new_url, prefix)
        result["table_prefix"] = prefix
        result["site"] = root.name
        return result
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def site_fix_perms(path: str = ".") -> Dict[str, Any]:
    """Выставляет права 755/644 чтобы nginx мог читать сайт."""
    try:
        root = Path(path).expanduser().resolve()
        if not root.is_dir():
            return {"ok": False, "error": f"Не директория: {root}"}
        fixed = 0
        os.chmod(root, 0o755)
        for p in root.rglob("*"):
            try:
                if p.is_dir():
                    os.chmod(p, 0o755)
                elif p.is_file():
                    os.chmod(p, 0o644)
                fixed += 1
            except OSError:
                continue
        return {
            "ok": True,
            "path": str(root),
            "fixed_entries": fixed,
            "hint": "Права 755/644 выставлены",
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def php_lint(path: str) -> Dict[str, Any]:
    """Проверяет синтаксис PHP-файла (php -l)."""
    try:
        p = Path(path).expanduser().resolve()
        if not p.is_file():
            return {"ok": False, "error": f"Файл не найден: {p}"}
        if shutil.which("php") is None:
            return {"ok": False, "error": "php не установлен в контейнере/на сервере"}
        proc = subprocess.run(
            ["php", "-l", str(p)],
            capture_output=True,
            text=True,
            timeout=30,
            encoding="utf-8",
            errors="replace",
        )
        out = ((proc.stdout or "") + "\n" + (proc.stderr or "")).strip()
        return {
            "ok": proc.returncode == 0,
            "path": str(p),
            "output": out[:4000],
            "returncode": proc.returncode,
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def nginx_test() -> Dict[str, Any]:
    """Проверяет конфиг nginx (nginx -t), если доступен."""
    try:
        nginx = shutil.which("nginx")
        if not nginx:
            return {
                "ok": False,
                "error": "nginx не найден в этом окружении (часто снаружи Docker)",
                "hint": "На хосте: nginx -t && systemctl reload nginx",
            }
        proc = subprocess.run(
            [nginx, "-t"],
            capture_output=True,
            text=True,
            timeout=15,
            encoding="utf-8",
            errors="replace",
        )
        out = ((proc.stdout or "") + "\n" + (proc.stderr or "")).strip()
        return {"ok": proc.returncode == 0, "output": out[:4000], "returncode": proc.returncode}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def flatten_site_layout(path: str = ".") -> Dict[str, Any]:
    """Разворачивает public_html/www/wordpress в корень сайта."""
    try:
        root = Path(path).expanduser().resolve()
        if not root.is_dir():
            return {"ok": False, "error": f"Не директория: {root}"}
        before = [p.name for p in root.iterdir()][:40]
        _local_flatten(root)
        after = [p.name for p in root.iterdir()][:40]
        return {
            "ok": True,
            "path": str(root),
            "before": before,
            "after": after,
            "edited": before != after,
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def _local_flatten(root: Path) -> None:
    for folder_name in ("public_html", "www", "htdocs", "httpdocs", "public", "wordpress"):
        nested = root / folder_name
        if nested.is_dir():
            for item in list(nested.iterdir()):
                dest = root / item.name
                if dest.exists():
                    if dest.is_dir():
                        shutil.rmtree(dest)
                    else:
                        dest.unlink()
                shutil.move(str(item), str(dest))
            shutil.rmtree(nested, ignore_errors=True)
            return
