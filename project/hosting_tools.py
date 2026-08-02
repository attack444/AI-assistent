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
        edited = _local_flatten(root)
        after = [p.name for p in root.iterdir()][:40]
        return {
            "ok": True,
            "path": str(root),
            "before": before,
            "after": after,
            "edited": edited or before != after,
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}


def _merge_move(src: Path, dest: Path) -> None:
    """
    Move src → dest without destroying existing live content.
    Directories are merged; on file collision the destination (root) wins.
    """
    if not dest.exists():
        shutil.move(str(src), str(dest))
        return
    if src.is_dir() and dest.is_dir():
        for child in list(src.iterdir()):
            _merge_move(child, dest / child.name)
        shutil.rmtree(src, ignore_errors=True)
        return
    # Collision: keep the live root file/dir, drop the nested duplicate.
    if src.is_dir():
        shutil.rmtree(src, ignore_errors=True)
    else:
        try:
            src.unlink()
        except OSError:
            pass


def _local_flatten(root: Path) -> bool:
    """
    Unwrap public_html/www/... into site root.
    Returns True if a nested webroot folder was processed.

    Critical: never shutil.rmtree/unlink existing root destinations on name
    collision — that used to wipe live wp-content when a leftover public_html
    was flattened by site_health_check(auto_fix=True). Colliding paths are
    merged (dirs) or kept at root (files); only the nested duplicate is dropped.
    """
    for folder_name in ("public_html", "www", "htdocs", "httpdocs", "public", "wordpress"):
        nested = root / folder_name
        if not nested.is_dir():
            continue
        for item in list(nested.iterdir()):
            _merge_move(item, root / item.name)
        shutil.rmtree(nested, ignore_errors=True)
        return True
    return False


def _find_main_html(root: Path) -> Optional[Path]:
    for name in ("index.html", "index.htm"):
        p = root / name
        if p.is_file():
            return p
    for sub in ("public_html", "www", "public"):
        for name in ("index.html", "index.htm"):
            p = root / sub / name
            if p.is_file():
                return p
    # WP theme front — skip; prefer header.php later
    return None


def _find_header_css(root: Path) -> List[Path]:
    candidates: List[Path] = []
    for pattern in ("style.css", "styles.css", "main.css", "header.css", "theme.css"):
        for p in root.rglob(pattern):
            if any(s in p.parts for s in ("node_modules", ".git", "vendor")):
                continue
            candidates.append(p)
            if len(candidates) >= 8:
                return candidates
    return candidates


def site_health_check(path: str = ".", auto_fix: bool = False) -> Dict[str, Any]:
    """
    Автопроверка сайта: структура, WP, HTML/CSS типичные поломки (в т.ч. «съехавший» header).
    auto_fix=True — безопасные автоисправления.
    """
    import re

    try:
        root = Path(path).expanduser().resolve()
        if not root.is_dir():
            return {"ok": False, "error": f"Не директория: {root}"}

        issues: List[Dict[str, Any]] = []
        fixes: List[Dict[str, Any]] = []

        def add(kind: str, msg: str, severity: str = "warn", file: str = "", fixable: bool = False):
            issues.append({
                "kind": kind,
                "message": msg,
                "severity": severity,
                "file": file,
                "fixable": fixable,
            })

        # ── Structure ──────────────────────────────────────────────────────
        nested = None
        for folder_name in ("public_html", "www", "htdocs", "wordpress"):
            cand = root / folder_name
            if cand.is_dir() and (
                (cand / "index.php").is_file()
                or (cand / "index.html").is_file()
                or (cand / "wp-config.php").is_file()
            ):
                nested = folder_name
                break
        if nested:
            add(
                "layout",
                f"Сайт лежит во вложенной папке «{nested}/» — nginx может отдавать пустой корень",
                "error",
                nested + "/",
                True,
            )
            if auto_fix:
                before = [p.name for p in root.iterdir()][:20]
                _local_flatten(root)
                fixes.append({"action": "flatten_site_layout", "before": before})

        has_index = any((root / n).is_file() for n in ("index.html", "index.htm", "index.php"))
        if not has_index:
            add("structure", "В корне нет index.html / index.php", "error", fixable=False)

        # ── WordPress ──────────────────────────────────────────────────────
        is_wp = (root / "wp-config.php").is_file() or (root / "wp-content").is_dir()
        if is_wp:
            try:
                import wp_tools as wpt
                st = wpt.wp_status(root)
                db = st.get("db") or {}
                if not db.get("ok"):
                    add("wordpress", f"MySQL: {db.get('error') or 'нет соединения'}", "error")
                urls = (st.get("urls") or {}).get("urls") or {}
                domain = _site_domain(root)
                siteurl = str(urls.get("siteurl") or "")
                if domain and siteurl and domain not in siteurl and "localhost" not in siteurl:
                    add(
                        "wordpress",
                        f"siteurl={siteurl} не совпадает с доменом {domain}",
                        "warn",
                        "БД options",
                        True,
                    )
                    if auto_fix:
                        new_url = f"http://{domain}"
                        r = wpt.replace_site_url("AUTO", new_url, st.get("defines", {}).get("table_prefix") or "wp_")
                        fixes.append({"action": "wp_replace_urls", "new_url": new_url, "result": r.get("ok")})
            except Exception as exc:
                add("wordpress", f"Не удалось проверить WP: {exc}", "warn")

        # ── HTML head / header ─────────────────────────────────────────────
        html = _find_main_html(root)
        if html:
            text = html.read_text(encoding="utf-8", errors="ignore")
            rel = str(html.relative_to(root)).replace("\\", "/")
            if not re.search(r"<meta[^>]+charset=", text, re.I):
                add("html", "Нет <meta charset> — кириллица/вёрстка может ломаться", "warn", rel, True)
                if auto_fix:
                    if re.search(r"<head[^>]*>", text, re.I):
                        text2 = re.sub(
                            r"(<head[^>]*>)",
                            r'\1\n<meta charset="utf-8">',
                            text,
                            count=1,
                            flags=re.I,
                        )
                        html.write_text(text2, encoding="utf-8")
                        text = text2
                        fixes.append({"action": "add_charset", "file": rel})
            if not re.search(r"name=[\"']viewport[\"']", text, re.I):
                add(
                    "html",
                    "Нет viewport — на телефоне заголовок/блок часто «съезжает»",
                    "warn",
                    rel,
                    True,
                )
                if auto_fix:
                    if re.search(r"<head[^>]*>", text, re.I):
                        text2 = re.sub(
                            r"(<head[^>]*>)",
                            r'\1\n<meta name="viewport" content="width=device-width, initial-scale=1">',
                            text,
                            count=1,
                            flags=re.I,
                        )
                        html.write_text(text2, encoding="utf-8")
                        text = text2
                        fixes.append({"action": "add_viewport", "file": rel})

            # Unclosed header/h1 rough check in first 8KB
            head_chunk = text[:12000]
            for tag in ("header", "h1", "nav", "div"):
                opens = len(re.findall(rf"<{tag}(?:\s|>)", head_chunk, re.I))
                closes = len(re.findall(rf"</{tag}>", head_chunk, re.I))
                if opens > closes + 1:
                    add(
                        "html",
                        f"Возможно незакрытый <{tag}> в начале страницы (открыто {opens}, закрыто {closes}) — блок может «съехать»",
                        "warn",
                        rel,
                    )

            # Inline style on h1 with huge negative margin / left
            if re.search(
                r"<h1[^>]+style=[\"'][^\"']*(?:margin-left\s*:\s*-?\d{3,}|left\s*:\s*-?\d{3,}|transform\s*:\s*translate)",
                text,
                re.I,
            ):
                add(
                    "layout",
                    "У <h1> подозрительный inline-style (сдвиг) — заголовок может быть съехавшим",
                    "warn",
                    rel,
                    True,
                )
                if auto_fix:
                    text2 = re.sub(
                        r"(<h1[^>]*)\sstyle=[\"'][^\"']*[\"']",
                        r"\1",
                        text,
                        count=1,
                        flags=re.I,
                    )
                    if text2 != text:
                        html.write_text(text2, encoding="utf-8")
                        text = text2
                        fixes.append({"action": "strip_h1_inline_shift", "file": rel})

        # ── CSS clearfix / header float ──────────────────────────────────
        for css in _find_header_css(root):
            try:
                css_text = css.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            rel = str(css.relative_to(root)).replace("\\", "/")
            has_float_header = bool(
                re.search(
                    r"(header|\.site-header|\.header|\.main-header)[^{]*\{[^}]*float\s*:\s*left",
                    css_text,
                    re.I | re.S,
                )
            ) or bool(
                re.search(
                    r"(header|\.site-header|\.header)\s+[^{]*\{[^}]*float\s*:\s*left",
                    css_text,
                    re.I | re.S,
                )
            )
            # children floated but parent without clearfix
            floated_nav = bool(re.search(r"(nav|\.menu|\.logo|h1)[^{]*\{[^}]*float\s*:\s*left", css_text, re.I | re.S))
            has_clearfix = "clearfix" in css_text.lower() or "overflow:hidden" in css_text.replace(" ", "").lower() or "overflow: hidden" in css_text.lower()
            if floated_nav and not has_clearfix:
                add(
                    "layout",
                    f"В {rel}: float у меню/лого/h1 без clearfix — классическая причина «съехавшего» заголовка",
                    "error",
                    rel,
                    True,
                )
                if auto_fix:
                    patch = (
                        "\n\n/* AI Helper auto-fix: contain floated header children */\n"
                        "header, .site-header, .header, .main-header {\n"
                        "  overflow: hidden;\n"
                        "}\n"
                        "header::after, .site-header::after, .header::after {\n"
                        "  content: \"\";\n"
                        "  display: table;\n"
                        "  clear: both;\n"
                        "}\n"
                    )
                    if "AI Helper auto-fix" not in css_text:
                        css.write_text(css_text.rstrip() + patch, encoding="utf-8")
                        fixes.append({"action": "css_clearfix_header", "file": rel})

            # h1 with large negative margin
            if re.search(r"h1[^{]*\{[^}]*(margin-left|left)\s*:\s*-?\d{3,}px", css_text, re.I | re.S):
                add(
                    "layout",
                    f"В {rel}: у h1 большой сдвиг (margin/left) — заголовок может уехать",
                    "warn",
                    rel,
                    True,
                )
                if auto_fix:
                    text2 = re.sub(
                        r"(h1[^{]*\{[^}]*)((?:margin-left|left)\s*:\s*-?\d{3,}px\s*;?)",
                        r"\1/* fixed: \2 */ margin-left: 0;",
                        css_text,
                        count=1,
                        flags=re.I | re.S,
                    )
                    if text2 != css_text and "AI Helper h1-shift" not in css_text:
                        # simpler: zero out extreme left margins on h1 blocks
                        text2 = re.sub(
                            r"(h1\s*\{[^}]*)margin-left\s*:\s*-?\d{3,}px\s*;?",
                            r"\1margin-left: 0; /* AI Helper h1-shift */",
                            css_text,
                            count=1,
                            flags=re.I | re.S,
                        )
                        if text2 != css_text:
                            css.write_text(text2, encoding="utf-8")
                            fixes.append({"action": "css_h1_margin_reset", "file": rel})

        # ── Perms hint ─────────────────────────────────────────────────────
        try:
            st_mode = root.stat().st_mode & 0o777
            if st_mode & 0o004 == 0:
                add("perms", "Корень сайта может быть не читаем для nginx", "warn", str(root), True)
                if auto_fix:
                    site_fix_perms(str(root))
                    fixes.append({"action": "site_fix_perms"})
        except OSError:
            pass

        errors = sum(1 for i in issues if i["severity"] == "error")
        warns = sum(1 for i in issues if i["severity"] == "warn")
        return {
            "ok": errors == 0,
            "path": str(root),
            "is_wordpress": is_wp,
            "issues": issues,
            "errors": errors,
            "warnings": warns,
            "fixes_applied": fixes,
            "edited": bool(fixes),
            "summary": (
                f"Проверка: {errors} ошибок, {warns} предупреждений"
                + (f", исправлено: {len(fixes)}" if fixes else "")
            ),
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)}
