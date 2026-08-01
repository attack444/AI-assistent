"""
Полный обзор системы для панели: LLM, сайты, DNS, мониторинг, возможности DeepSeek.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

import dns_tools
import system_health as sh


CAPABILITIES: List[Dict[str, Any]] = [
    {
        "id": "site_files",
        "label": "Файлы сайтов (/opt/sites/*)",
        "deepseek": True,
        "panel": True,
        "how": "Чат с сайтом → write_file / str_replace / FileManager",
    },
    {
        "id": "backend",
        "label": "Бэкенд панели (api.py, agent, web)",
        "deepseek": True,
        "panel": True,
        "how": "Чат site=server (workspace /opt/ai-helper/project) + apply_self_improvement; нужен mount репозитория в Docker",
        "note": "После правок api/web — docker compose build/up",
    },
    {
        "id": "wordpress_db",
        "label": "WordPress URL / БД",
        "deepseek": True,
        "panel": True,
        "how": "wp_replace_urls, site_health_check, панель Sites → WordPress",
    },
    {
        "id": "nginx_write",
        "label": "Nginx vhost (запись)",
        "deepseek": False,
        "panel": True,
        "how": "Панель: привязка домена / setup-domain.sh. Tool агента: только nginx_test",
    },
    {
        "id": "dns",
        "label": "DNS записи (просмотр)",
        "deepseek": True,
        "panel": True,
        "how": "dns_lookup + раздел Обзор. Смена NS/A — у регистратора (не на VPS)",
    },
    {
        "id": "docker_restart",
        "label": "Docker restart (safe)",
        "deepseek": False,
        "panel": True,
        "how": "Watchdog / Здоровье → safe fix. Агент не имеет docker.sock по умолчанию",
    },
    {
        "id": "monitoring",
        "label": "Мониторинг 5mb2 + ai + панель",
        "deepseek": True,
        "panel": True,
        "how": "system_health + cron; при сбое DeepSeek (cooldown 8 мин)",
    },
    {
        "id": "feedback",
        "label": "Обратная связь с сайтов",
        "deepseek": False,
        "panel": True,
        "how": "Inbox /feedback, source=5mb2|ai-helper|watchdog",
    },
]


def _docker_ps() -> List[Dict[str, str]]:
    if not shutil.which("docker"):
        return []
    try:
        r = subprocess.run(
            [
                "docker",
                "ps",
                "-a",
                "--format",
                "{{.Names}}\t{{.Status}}\t{{.Ports}}",
            ],
            capture_output=True,
            text=True,
            timeout=15,
        )
    except Exception:
        return []
    rows: List[Dict[str, str]] = []
    for line in (r.stdout or "").splitlines():
        parts = line.split("\t")
        if len(parts) >= 2:
            rows.append({
                "name": parts[0],
                "status": parts[1],
                "ports": parts[2] if len(parts) > 2 else "",
            })
    return rows


def _collect_domains(sites_root: Path) -> List[Dict[str, Any]]:
    domains: List[Dict[str, Any]] = []
    # known production domain
    known = [("5mb2.ru", "5mb2")]
    if sites_root.is_dir():
        for child in sorted(sites_root.iterdir()):
            if not child.is_dir() or child.name.startswith("."):
                continue
            df = child / ".ai-helper-domain"
            if df.is_file():
                d = df.read_text(encoding="utf-8", errors="ignore").strip()
                if d:
                    known.append((d, child.name))
    seen = set()
    vps_ip = dns_tools.detect_vps_ip()
    for domain, site in known:
        key = domain.lower()
        if key in seen:
            continue
        seen.add(key)
        info = dns_tools.lookup_domain(domain, expected_ip=vps_ip)
        info["site"] = site
        domains.append(info)
    return domains


def build_overview(
    *,
    api_status: Optional[Dict[str, Any]] = None,
    sites_root: Optional[Path] = None,
    include_health: bool = True,
    include_dns: bool = True,
) -> Dict[str, Any]:
    sites_root = sites_root or Path(
        os.environ.get("SITES_ROOT", "/opt/sites")
    )
    health = None
    if include_health:
        try:
            health = sh.check_targets()
        except Exception as exc:
            health = {"ok": False, "error": str(exc)[:200]}

    dns_list: List[Dict[str, Any]] = []
    if include_dns:
        try:
            dns_list = _collect_domains(sites_root)
        except Exception as exc:
            dns_list = [{"ok": False, "error": str(exc)[:200]}]

    incidents = []
    try:
        incidents = sh.list_incidents(15)
    except Exception:
        pass

    server_root = Path(
        os.environ.get("AI_HELPER_PROJECT", "")
        or os.environ.get("AI_HELPER_ROOT", "/opt/ai-helper/project")
    )
    if not server_root.is_dir():
        alt = Path("/opt/ai-helper/project")
        server_root = alt if alt.is_dir() else Path("/opt/ai-helper")

    backend_editable = server_root.is_dir() and (
        (server_root / "api.py").is_file()
        or (server_root / "project" / "api.py").is_file()
    )

    caps = []
    for c in CAPABILITIES:
        item = dict(c)
        if c["id"] == "backend":
            item["available_now"] = backend_editable
            item["workspace"] = str(server_root)
        else:
            item["available_now"] = True
        caps.append(item)

    return {
        "ok": True,
        "at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "vps_ip": dns_tools.detect_vps_ip(),
        "api_status": api_status or {},
        "health": health,
        "dns": dns_list,
        "docker": _docker_ps(),
        "incidents": incidents,
        "capabilities": caps,
        "workspaces": {
            "sites_root": str(sites_root),
            "server_project": str(server_root),
            "server_editable": backend_editable,
            "chat_server_hint": "В чате выбери сайт «server» — DeepSeek правит бэкенд /opt/ai-helper/project",
        },
        "links": {
            "health": "/health",
            "feedback": "/feedback",
            "sites": "/sites",
            "chat_server": "/chat?site=server",
            "docs_report": "project/deploy/SYSTEM_REPORT_RU.md",
            "docs_health": "project/deploy/SYSTEM_HEALTH_RU.md",
            "docs_dns": "project/deploy/DOMAIN_5mb2.md",
        },
    }
