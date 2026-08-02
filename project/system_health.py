"""
system_health.py — проверка панели, API/DeepSeek, 5mb2 и NeoBrain.

Приоритет: панель + API + DeepSeek должны жить всегда; сайты — следом.
Безопасные авто-фиксы: restart docker app/web/php. Код сайтов сам не правит —
инцидент в inbox + опционально запрос в DeepSeek (tools) для разбора.
"""
from __future__ import annotations

import json
import os
import subprocess
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any, Dict, List, Optional

DATA_DIR = Path(os.environ.get("AI_HELPER_DATA", str(Path.home() / ".ai-helper")))
INCIDENTS_FILE = DATA_DIR / "system_incidents.jsonl"
LAST_OK_FILE = DATA_DIR / "system_health_last.json"

DEFAULT_BASE = os.environ.get("WATCHDOG_BASE_URL", "http://127.0.0.1").rstrip("/")
DEFAULT_HOST = os.environ.get("WATCHDOG_HOST_HEADER", "5mb2.ru")
API_PORT = os.environ.get("WATCHDOG_API_PORT", "8502")
PANEL_PORT = os.environ.get("WATCHDOG_PANEL_PORT", "3000")


def _http(
    url: str,
    *,
    host: str = "",
    timeout: float = 12.0,
    expect_json: bool = False,
) -> Dict[str, Any]:
    headers = {"User-Agent": "ai-helper-watchdog/1.0"}
    if host:
        headers["Host"] = host
    req = urllib.request.Request(url, headers=headers, method="GET")
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read()
            elapsed = round(time.time() - t0, 3)
            text = body.decode("utf-8", errors="replace")
            out: Dict[str, Any] = {
                "ok": 200 <= resp.status < 400,
                "status": resp.status,
                "bytes": len(body),
                "ms": int(elapsed * 1000),
                "url": url,
            }
            if expect_json:
                try:
                    out["json"] = json.loads(text)
                except json.JSONDecodeError:
                    out["ok"] = False
                    out["error"] = "invalid json"
            else:
                low = text.lower()
                if any(
                    x in low
                    for x in (
                        "fatal error",
                        "parse error",
                        "there has been a critical error",
                        "error establishing a database connection",
                    )
                ):
                    out["ok"] = False
                    out["error"] = "php/wp critical in body"
                if "</html>" not in low and resp.status == 200 and len(body) < 200:
                    out["ok"] = False
                    out["error"] = out.get("error") or "truncated body"
            return out
    except Exception as exc:
        return {
            "ok": False,
            "status": 0,
            "bytes": 0,
            "ms": int((time.time() - t0) * 1000),
            "url": url,
            "error": str(exc)[:300],
        }


def check_targets(
    base_url: str = "",
    host: str = "",
) -> Dict[str, Any]:
    base = (base_url or DEFAULT_BASE).rstrip("/")
    host = host or DEFAULT_HOST
    api_base = os.environ.get("WATCHDOG_API_URL", f"http://127.0.0.1:{API_PORT}").rstrip("/")
    panel_url = os.environ.get("WATCHDOG_PANEL_URL", f"http://127.0.0.1:{PANEL_PORT}/").rstrip("/") + "/"

    checks: List[Dict[str, Any]] = []

    # 1) API — сердце панели
    api = _http(f"{api_base}/status", timeout=8, expect_json=True)
    api["id"] = "api"
    api["priority"] = 1
    api["label"] = "API панели"
    js = api.get("json") or {}
    if api.get("ok") and not js.get("ok", True):
        api["ok"] = False
        api["error"] = "status.ok=false"
    checks.append(api)

    # 2) DeepSeek (из /status) — приоритет для ремонта
    ds_ok = bool(js.get("deepseek"))
    checks.append({
        "id": "deepseek",
        "priority": 1,
        "label": "DeepSeek",
        "ok": ds_ok if api.get("ok") else False,
        "status": 200 if ds_ok else 0,
        "bytes": 0,
        "ms": 0,
        "url": "status.deepseek",
        "error": None if ds_ok else "DeepSeek ключ/статус false — ремонт через tools ненадёжен",
        "model": js.get("deepseek_model"),
        "llm_prefer_free": js.get("llm_prefer_free"),
    })

    # 3) Панель Next
    panel = _http(panel_url, timeout=10)
    panel["id"] = "panel"
    panel["priority"] = 1
    panel["label"] = "Панель сервера"
    checks.append(panel)

    # 4) 5mb2 homepage + health mu-plugin
    site5 = _http(f"{base}/", host=host, timeout=15)
    site5["id"] = "5mb2"
    site5["priority"] = 2
    site5["label"] = "Сайт 5mb2"
    checks.append(site5)

    health = _http(f"{base}/?mb2_health=1", host=host, timeout=10, expect_json=True)
    health["id"] = "5mb2_health"
    health["priority"] = 2
    health["label"] = "5mb2 health JSON"
    # mu-plugin может ещё не быть задеплоен — warn, не hard fail если главная ок
    if not health.get("ok") and site5.get("ok"):
        health["ok"] = True
        health["warn"] = health.get("error") or f"health endpoint HTTP {health.get('status')}"
        health["error"] = None
    checks.append(health)

    cab = _http(f"{base}/cabinet/", host=host, timeout=15)
    cab["id"] = "5mb2_cabinet"
    cab["priority"] = 2
    cab["label"] = "Кабинет 5mb2"
    checks.append(cab)

    # 5) NeoBrain public (domain first, fallback /sites/ai/)
    neo_domain = os.environ.get("NEOBRAIN_DOMAIN", "neobrain.site").strip() or "neobrain.site"
    neo = _http(f"https://{neo_domain}/", timeout=12)
    if not neo.get("ok"):
        neo = _http(f"{base}/sites/ai/", host=host, timeout=12)
        neo["warn"] = neo.get("warn") or f"https://{neo_domain}/ недоступен — проверен /sites/ai/"
    neo["id"] = "neobrain"
    neo["priority"] = 2
    neo["label"] = "Сайт NeoBrain"
    checks.append(neo)

    panel_dom = os.environ.get(
        "NEOBRAIN_PANEL_DOMAIN", f"panel.{neo_domain}"
    ).strip()
    if panel_dom:
        pp = _http(f"https://{panel_dom}/", timeout=10)
        pp["id"] = "panel_domain"
        pp["priority"] = 1
        pp["label"] = f"Панель {panel_dom}"
        if not pp.get("ok"):
            # optional until DNS for panel.* exists
            pp["warn"] = pp.get("error") or "нет DNS/SSL — добавь A panel → VPS"
            pp["ok"] = True
            pp["optional"] = True
        checks.append(pp)

    failed = [c for c in checks if not c.get("ok")]
    priority_failed = [c for c in failed if int(c.get("priority") or 9) <= 1]
    return {
        "ok": not failed,
        "priority_ok": not priority_failed,
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "checks": checks,
        "failed": [c["id"] for c in failed],
        "priority_failed": [c["id"] for c in priority_failed],
        "api_status": js if api.get("ok") else {},
    }


def record_incident(report: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    if report.get("ok"):
        return None
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    item = {
        "at": report.get("at") or time.strftime("%Y-%m-%d %H:%M:%S"),
        "type": "bug",
        "type_label": "Ошибка на сайте",
        "source": "watchdog",
        "failed": report.get("failed") or [],
        "priority_failed": report.get("priority_failed") or [],
        "checks": [
            {
                "id": c.get("id"),
                "ok": c.get("ok"),
                "status": c.get("status"),
                "error": c.get("error"),
                "ms": c.get("ms"),
            }
            for c in (report.get("checks") or [])
            if not c.get("ok")
        ],
        "message": "Watchdog: сбой — "
        + ", ".join(report.get("failed") or ["unknown"]),
    }
    with INCIDENTS_FILE.open("a", encoding="utf-8") as f:
        f.write(json.dumps(item, ensure_ascii=False) + "\n")
    try:
        import public_feedback as pf

        pf.save_feedback(
            kind="bug",
            message=item["message"]
            + "\n"
            + json.dumps(item["checks"], ensure_ascii=False)[:3500],
            page="system-watchdog",
            source="watchdog",
        )
    except Exception:
        pass
    return item


def list_incidents(limit: int = 50) -> List[Dict[str, Any]]:
    limit = max(1, min(int(limit or 50), 200))
    if not INCIDENTS_FILE.exists():
        return []
    items: List[Dict[str, Any]] = []
    try:
        lines = INCIDENTS_FILE.read_text(encoding="utf-8").splitlines()
    except OSError:
        return []
    for line in reversed(lines):
        line = line.strip()
        if not line:
            continue
        try:
            items.append(json.loads(line))
        except json.JSONDecodeError:
            continue
        if len(items) >= limit:
            break
    return items


def _docker_restart(names: List[str]) -> List[Dict[str, Any]]:
    actions: List[Dict[str, Any]] = []
    if os.environ.get("WATCHDOG_ALLOW_RESTART", "1").strip().lower() in {
        "0",
        "false",
        "no",
    }:
        return [{"action": "restart_skipped", "reason": "WATCHDOG_ALLOW_RESTART=0"}]
    for name in names:
        try:
            r = subprocess.run(
                ["docker", "restart", name],
                capture_output=True,
                text=True,
                timeout=90,
            )
            actions.append({
                "action": "docker_restart",
                "container": name,
                "ok": r.returncode == 0,
                "stderr": (r.stderr or "")[:200],
            })
        except Exception as exc:
            actions.append({
                "action": "docker_restart",
                "container": name,
                "ok": False,
                "error": str(exc)[:200],
            })
    return actions


def safe_remediate(report: Dict[str, Any]) -> List[Dict[str, Any]]:
    """Только безопасные действия: restart критичных контейнеров."""
    failed = set(report.get("failed") or [])
    actions: List[Dict[str, Any]] = []
    restart: List[str] = []
    if "api" in failed or "deepseek" in failed:
        restart.append("ai-helper-app")
    if "panel" in failed:
        restart.append("ai-helper-web")
        if "ai-helper-app" not in restart:
            restart.append("ai-helper-app")
    if "5mb2" in failed or "5mb2_cabinet" in failed:
        restart.append("ai-helper-php")
    # unique preserve order
    seen = set()
    uniq = []
    for n in restart:
        if n not in seen:
            seen.add(n)
            uniq.append(n)
    if uniq:
        actions.extend(_docker_restart(uniq))
    return actions


def ask_deepseek_repair(report: Dict[str, Any]) -> Dict[str, Any]:
    """
    Просит DeepSeek (через agent) разобрать сбой.
    Не включает LLM_PREFER_FREE — ремонт всегда через cloud tools.
    """
    if os.environ.get("WATCHDOG_ASK_DEEPSEEK", "1").strip().lower() in {
        "0",
        "false",
        "no",
    }:
        return {"ok": False, "skipped": True, "reason": "WATCHDOG_ASK_DEEPSEEK=0"}
    if report.get("ok"):
        return {"ok": True, "skipped": True, "reason": "healthy"}
    if not (report.get("api_status") or {}).get("deepseek"):
        return {"ok": False, "skipped": True, "reason": "deepseek unavailable"}

    # Force DeepSeek path for this process
    os.environ["LLM_PREFER_FREE"] = "0"

    failed = ", ".join(report.get("failed") or [])
    detail = json.dumps(
        [
            {
                "id": c.get("id"),
                "status": c.get("status"),
                "error": c.get("error"),
            }
            for c in (report.get("checks") or [])
            if not c.get("ok")
        ],
        ensure_ascii=False,
    )
    message = (
        "СРОЧНО: watchdog зафиксировал сбой инфраструктуры. "
        f"Упали: {failed}. Детали: {detail}. "
        "Приоритет: панель сервера и DeepSeek/API должны работать без перебоев. "
        "Проверь site_health_check для /opt/sites/5mb2 и /opt/sites/ai, "
        "логи, nginx/php при необходимости. "
        "Безопасные фиксы ок; не ломай рабочие конфиги. Кратко что сделал."
    )
    try:
        from agent import run_agent
        from core import load_settings
        from memory import MemoryStore
        from profile import load_profile

        settings = load_settings()
        profile = load_profile()
        memory = MemoryStore()
        site_root = Path(os.environ.get("SITES_ROOT", "/opt/sites")) / "5mb2"
        project_root = site_root if site_root.is_dir() else None
        texts: List[str] = []
        tools: List[str] = []
        for ev in run_agent(
            user_message=message,
            chat_history=[],
            project_root=project_root,
            profile=profile,
            memory=memory,
            llm_model=settings.llm_model,
            ollama_host=settings.ollama_host,
            context_window=settings.context_window,
            fast_llm_model=settings.fast_llm_model,
            groq_api_key=settings.groq_api_key,
            groq_model=settings.groq_model,
            deepseek_api_key=settings.deepseek_api_key
            or os.environ.get("DEEPSEEK_API_KEY", ""),
            deepseek_model=settings.deepseek_model
            or os.environ.get("DEEPSEEK_MODEL", "deepseek-chat"),
            http_proxy=settings.http_proxy,
        ):
            if getattr(ev, "type", "") == "text" and getattr(ev, "content", ""):
                texts.append(ev.content)
            if getattr(ev, "type", "") == "tool_call" and getattr(ev, "tool_name", ""):
                tools.append(ev.tool_name)
        reply = "".join(texts).strip()
        return {
            "ok": True,
            "tools": tools[:20],
            "reply": reply[:4000],
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc)[:400]}


def run_watchdog(
    *,
    remediate: bool = True,
    ask_ai: bool = False,
    base_url: str = "",
    host: str = "",
) -> Dict[str, Any]:
    report = check_targets(base_url=base_url, host=host)
    incident = record_incident(report)
    actions: List[Dict[str, Any]] = []
    ai_result: Optional[Dict[str, Any]] = None
    if not report.get("ok") and remediate:
        actions = safe_remediate(report)
        # re-check after safe fix
        time.sleep(2)
        report_after = check_targets(base_url=base_url, host=host)
        report["after_remediate"] = {
            "ok": report_after.get("ok"),
            "failed": report_after.get("failed"),
        }
        if report_after.get("ok"):
            report["recovered"] = True
    # По умолчанию AI только если явно ask_ai=True.
    # Cron ставит WATCHDOG_ASK_ON_FAIL=1 — тогда при сбое зовём DeepSeek.
    ask_on_fail = os.environ.get("WATCHDOG_ASK_ON_FAIL", "0").strip().lower() in {
        "1",
        "true",
        "yes",
        "on",
    }
    if not report.get("ok") and (ask_ai or ask_on_fail):
        # Не дёргаем AI повторно чаще чем раз в 8 минут на тот же набор failed
        stamp = DATA_DIR / "watchdog_ai_last.json"
        should_ask = True
        try:
            if stamp.is_file():
                prev = json.loads(stamp.read_text(encoding="utf-8"))
                same = prev.get("failed") == report.get("failed")
                age = time.time() - float(prev.get("ts") or 0)
                if same and age < 480:
                    should_ask = False
                    ai_result = {"ok": False, "skipped": True, "reason": "cooldown"}
        except Exception:
            pass
        if should_ask:
            ai_result = ask_deepseek_repair(report)
            try:
                stamp.write_text(
                    json.dumps(
                        {"ts": time.time(), "failed": report.get("failed")},
                        ensure_ascii=False,
                    ),
                    encoding="utf-8",
                )
            except OSError:
                pass

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    LAST_OK_FILE.write_text(
        json.dumps(
            {
                "at": report.get("at"),
                "ok": report.get("ok"),
                "failed": report.get("failed"),
                "priority_ok": report.get("priority_ok"),
            },
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )
    out = {
        **report,
        "incident": incident,
        "actions": actions,
        "ai_repair": ai_result,
    }
    return out


if __name__ == "__main__":
    import argparse

    p = argparse.ArgumentParser(description="NeoBrain system watchdog")
    p.add_argument("--remediate", action="store_true", help="docker restart on fail")
    p.add_argument("--ask-deepseek", action="store_true", help="ask DeepSeek to repair")
    p.add_argument("--base", default="", help="public base URL")
    p.add_argument("--host", default="", help="Host header for 5mb2")
    p.add_argument("--json", action="store_true")
    args = p.parse_args()
    result = run_watchdog(
        remediate=args.remediate,
        ask_ai=args.ask_deepseek,
        base_url=args.base,
        host=args.host,
    )
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(
            "OK" if result.get("ok") else "FAIL",
            "| failed=",
            ",".join(result.get("failed") or []) or "-",
            "| priority_ok=",
            result.get("priority_ok"),
        )
        for c in result.get("checks") or []:
            mark = "✓" if c.get("ok") else "✗"
            err = c.get("error") or c.get("warn") or ""
            print(f"  {mark} {c.get('id')}: HTTP {c.get('status')} {err}")
        if result.get("actions"):
            print("actions:", json.dumps(result["actions"], ensure_ascii=False))
    raise SystemExit(0 if result.get("ok") else 1)
