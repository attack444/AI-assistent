"""SEO workflows: проверки сайтов, чеклист, авточерновики новостей."""
from __future__ import annotations

import json
import os
import re
import subprocess
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

PROJECT_DIR = Path(__file__).resolve().parent
SITES_ROOT = Path(os.environ.get("SITES_ROOT") or os.environ.get("HOST_SITES_PATH") or "/var/ai-helper/sites")
OPS_SCRIPT_HOST = PROJECT_DIR / "deploy" / "seo-news-drafts.php"
OPS_SCRIPT_SITES = SITES_ROOT / "_ops" / "seo-news-drafts.php"
STATE_DIR = Path(os.environ.get("AI_HELPER_DATA") or Path.home() / ".ai-helper")
STATE_FILE = STATE_DIR / "seo_workflow_state.json"

DEFAULT_SITES = [
    {
        "id": "5mb2",
        "name": "5MB2",
        "url": "https://5mb2.ru",
        "www_url": "https://www.5mb2.ru",
        "sitemap": "https://5mb2.ru/sitemap_index.xml",
        "robots": "https://5mb2.ru/robots.txt",
        "expect_title": "5MB2",
        "wordpress": True,
    },
    {
        "id": "neobrain",
        "name": "NeoBrain",
        "url": "https://neobrain.site",
        "www_url": "https://www.neobrain.site",
        "sitemap": "https://neobrain.site/sitemap.xml",
        "robots": "https://neobrain.site/robots.txt",
        "expect_title": "NeoBrain",
        "wordpress": False,
    },
]


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")


def _load_state() -> Dict[str, Any]:
    try:
        if STATE_FILE.is_file():
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    except Exception:
        pass
    return {}


def _save_state(patch: Dict[str, Any]) -> Dict[str, Any]:
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    state = _load_state()
    state.update(patch)
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")
    return state


def _http_get(url: str, timeout: float = 12.0) -> Tuple[int, str, Dict[str, str]]:
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "NeoBrain-SEO-Bot/1.0 (+https://neobrain.site/)"},
        method="GET",
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read(400_000).decode("utf-8", errors="replace")
            headers = {k.lower(): v for k, v in resp.headers.items()}
            return int(resp.status), body, headers
    except urllib.error.HTTPError as exc:
        try:
            body = exc.read(80_000).decode("utf-8", errors="replace")
        except Exception:
            body = ""
        return int(exc.code), body, {}
    except Exception as exc:
        return 0, "", {"error": str(exc)[:200]}


def _title_of(html: str) -> str:
    m = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)
    if not m:
        return ""
    return re.sub(r"\s+", " ", m.group(1)).strip()[:180]


def probe_site(site: Dict[str, Any]) -> Dict[str, Any]:
    checks: List[Dict[str, Any]] = []
    ok_all = True

    status, html, meta = _http_get(site["url"])
    title = _title_of(html) if html else ""
    https_ok = status == 200
    title_ok = bool(title) and (
        site["expect_title"].lower() in title.lower() if site.get("expect_title") else True
    )
    # NeoBrain must not look like 5MB2
    if site["id"] == "neobrain" and "5mb2" in title.lower():
        title_ok = False
    checks.append({
        "id": "https_home",
        "ok": https_ok,
        "detail": f"HTTP {status}" + (f" · {title}" if title else ""),
    })
    checks.append({
        "id": "title",
        "ok": title_ok,
        "detail": title or "title не найден",
    })
    if not https_ok or not title_ok:
        ok_all = False

    rs, robots, _ = _http_get(site["robots"])
    robots_ok = rs == 200 and "user-agent" in robots.lower()
    sitemap_line = "sitemap:" in robots.lower()
    checks.append({
        "id": "robots",
        "ok": robots_ok,
        "detail": f"HTTP {rs}" + (" · Sitemap указан" if sitemap_line else " · нет Sitemap в robots"),
    })
    if not robots_ok:
        ok_all = False

    ss, sm_body, _ = _http_get(site["sitemap"])
    sitemap_ok = ss == 200 and ("<urlset" in sm_body.lower() or "<sitemapindex" in sm_body.lower())
    checks.append({
        "id": "sitemap",
        "ok": sitemap_ok,
        "detail": f"HTTP {ss} · {site['sitemap']}",
    })
    if not sitemap_ok:
        ok_all = False

    has_desc = 'name="description"' in html.lower() or "name='description'" in html.lower()
    has_jsonld = "application/ld+json" in html.lower()
    has_ym = "mc.yandex.ru" in html or "ym(" in html
    has_ga = "gtag/js" in html or "googletagmanager.com" in html
    has_gsc = "google-site-verification" in html.lower()
    has_yandex_v = "yandex-verification" in html.lower()

    checks.append({"id": "meta_description", "ok": has_desc, "detail": "description" if has_desc else "нет description"})
    if site.get("wordpress"):
        checks.append({"id": "jsonld", "ok": has_jsonld, "detail": "JSON-LD" if has_jsonld else "нет JSON-LD"})
        if not has_jsonld:
            ok_all = False
    checks.append({"id": "metrika", "ok": has_ym, "detail": "Метрика на сайте" if has_ym else "Метрика не видна (добавь ID в Настройки)"})
    checks.append({"id": "ga4", "ok": has_ga, "detail": "GA/GTM на сайте" if has_ga else "GA не видна (опционально)"})
    checks.append({"id": "gsc_meta", "ok": has_gsc, "detail": "GSC meta" if has_gsc else "нет meta Google (после верификации)"})
    checks.append({"id": "yandex_meta", "ok": has_yandex_v, "detail": "Яндекс meta" if has_yandex_v else "нет meta Яндекс (после верификации)"})

    # soft: analytics/verification don't fail whole site unless home/sitemap broken
    if not has_desc and site.get("wordpress"):
        ok_all = False

    return {
        "id": site["id"],
        "name": site["name"],
        "url": site["url"],
        "ok": ok_all,
        "title": title,
        "checks": checks,
        "links": {
            "sitemap": site["sitemap"],
            "robots": site["robots"],
            "gsc": "https://search.google.com/search-console",
            "webmaster": "https://webmaster.yandex.ru/",
            "metrika": "https://metrika.yandex.ru/",
            "ga": "https://analytics.google.com/",
        },
        "error": meta.get("error"),
    }


def sync_ops_script() -> Optional[str]:
    """Копирует php-скрипт в sites/_ops чтобы cron/php-контейнер его видели."""
    src = OPS_SCRIPT_HOST if OPS_SCRIPT_HOST.is_file() else None
    if not src:
        return None
    try:
        OPS_SCRIPT_SITES.parent.mkdir(parents=True, exist_ok=True)
        OPS_SCRIPT_SITES.write_text(src.read_text(encoding="utf-8"), encoding="utf-8")
        return str(OPS_SCRIPT_SITES)
    except Exception:
        return str(src) if src.is_file() else None


def run_news_drafts(*, dry_run: bool = False, max_new: int = 3) -> Dict[str, Any]:
    sync_ops_script()
    env = os.environ.copy()
    env["SITES_ROOT"] = str(SITES_ROOT)
    env["SEO_NEWS_MAX"] = str(max_new)
    if dry_run:
        env["DRY_RUN"] = "1"

    commands: List[List[str]] = []
    if OPS_SCRIPT_HOST.is_file():
        commands.append(["php", str(OPS_SCRIPT_HOST)])
    if OPS_SCRIPT_SITES.is_file():
        commands.append([
            "docker", "exec",
            "-e", f"SITES_ROOT={SITES_ROOT}",
            "-e", f"SEO_NEWS_MAX={max_new}",
            *(["-e", "DRY_RUN=1"] if dry_run else []),
            "ai-helper-php",
            "php", str(OPS_SCRIPT_SITES),
        ])
        # host php against sites copy
        commands.append(["php", str(OPS_SCRIPT_SITES)])

    last_err = "php/seo-news-drafts.php недоступен"
    for cmd in commands:
        try:
            proc = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=120,
                env=env,
            )
            out = ((proc.stdout or "") + "\n" + (proc.stderr or "")).strip()
            if proc.returncode == 0:
                _save_state({
                    "last_news_run": _now(),
                    "last_news_dry_run": dry_run,
                    "last_news_output": out[-2000:],
                })
                return {
                    "ok": True,
                    "dry_run": dry_run,
                    "command": " ".join(cmd),
                    "output": out[-4000:],
                }
            last_err = out or f"exit {proc.returncode}"
        except FileNotFoundError:
            last_err = f"нет бинаря: {cmd[0]}"
        except Exception as exc:
            last_err = str(exc)[:300]

    return {"ok": False, "dry_run": dry_run, "error": last_err}


def workflow_checklist(settings: Optional[Dict[str, Any]] = None) -> List[Dict[str, Any]]:
    settings = settings or {}
    state = _load_state()
    items = [
        {
            "id": "dns_5mb2_apex",
            "title": "DNS: A-запись @ для 5mb2.ru → 80.78.248.195",
            "where": "reg.ru → 5mb2.ru",
            "done": None,  # filled by probe later
            "priority": 1,
        },
        {
            "id": "vhost_neobrain",
            "title": "На VPS: fix-neobrain-vhost (отдельный SSL NeoBrain)",
            "where": "SSH на сервер",
            "done": None,
            "priority": 1,
        },
        {
            "id": "panel_console",
            "title": "Открыть панель https://neobrain.site/console/",
            "where": "браузер",
            "done": False,
            "priority": 1,
        },
        {
            "id": "analytics_ids",
            "title": "Вписать Метрику / GA / meta Вебмастеров в Настройки",
            "where": "Панель → Настройки",
            "done": bool(settings.get("metrika_id") or settings.get("ga4_id")),
            "priority": 2,
        },
        {
            "id": "webmaster_submit",
            "title": "Добавить сайты в Google Search Console и Яндекс.Вебмастер + sitemap",
            "where": "GSC + Вебмастер",
            "done": bool(settings.get("gsc_verification") and settings.get("yandex_webmaster_verification")),
            "priority": 2,
        },
        {
            "id": "turnstile",
            "title": "Включить Cloudflare Turnstile (антибот форм)",
            "where": "Панель → Настройки",
            "done": bool(settings.get("turnstile_site_key") and settings.get("turnstile_secret_key")),
            "priority": 2,
        },
        {
            "id": "yookassa",
            "title": "Подключить ЮKassa для самооплаты тарифов",
            "where": "Панель → Настройки + webhook",
            "done": bool(settings.get("yookassa_shop_id") and settings.get("yookassa_secret_key")),
            "priority": 2,
        },
        {
            "id": "seo_news_cron",
            "title": "Поставить cron авточерновиков SEO-новостей",
            "where": "install-seo-cron.sh или кнопка ниже",
            "done": bool(state.get("last_news_run")),
            "priority": 3,
        },
        {
            "id": "content_review",
            "title": "Раз в день: черновики в WP → правишь → публикуешь",
            "where": "WordPress → Записи → Черновики",
            "done": False,
            "priority": 3,
        },
    ]
    return items


def build_report() -> Dict[str, Any]:
    try:
        import owner_settings as osset

        settings = osset.get_raw()
    except Exception:
        settings = {}

    sites = [probe_site(s) for s in DEFAULT_SITES]
    checklist = workflow_checklist(settings)

    # auto-mark DNS / vhost from probes
    by_id = {s["id"]: s for s in sites}
    mb2 = by_id.get("5mb2") or {}
    neo = by_id.get("neobrain") or {}
    for item in checklist:
        if item["id"] == "dns_5mb2_apex":
            # apex works if https_home ok on 5mb2.ru
            https = next((c for c in mb2.get("checks") or [] if c["id"] == "https_home"), None)
            item["done"] = bool(https and https.get("ok"))
        if item["id"] == "vhost_neobrain":
            title_ok = next((c for c in neo.get("checks") or [] if c["id"] == "title"), None)
            https = next((c for c in neo.get("checks") or [] if c["id"] == "https_home"), None)
            item["done"] = bool(https and https.get("ok") and title_ok and title_ok.get("ok"))
        if item["id"] == "panel_console":
            neo_https = next((c for c in neo.get("checks") or [] if c["id"] == "https_home"), None)
            item["done"] = bool(neo_https and neo_https.get("ok"))

    open_items = [i for i in checklist if not i.get("done")]
    state = _load_state()
    return {
        "ok": all(s.get("ok") for s in sites) and len([i for i in open_items if i["priority"] == 1]) == 0,
        "at": _now(),
        "sites": sites,
        "checklist": checklist,
        "open_count": len(open_items),
        "state": {
            "last_news_run": state.get("last_news_run"),
            "last_news_dry_run": state.get("last_news_dry_run"),
        },
        "actions": [
            {"id": "probe", "label": "Проверить SEO сейчас"},
            {"id": "news_drafts", "label": "Собрать черновики новостей"},
            {"id": "news_dry", "label": "Пробный прогон (без записи)"},
        ],
        "next_human": [
            "1) В reg.ru добавь A @ для 5mb2.ru",
            "2) На сервере выполни команды из NEXT_STEPS_SIMPLE_RU.md",
            "3) В панели Настройки — Метрика, Вебмастеры, ЮKassa, Turnstile",
            "4) Раздел SEO — кнопка черновиков + cron",
            "5) В WP публикуй черновики руками (бот сам не публикует)",
        ],
    }
