"""DNS lookup helpers for panel overview and DeepSeek tools."""
from __future__ import annotations

import os
import re
import shutil
import socket
import subprocess
from typing import Any, Dict, List, Optional

_DOMAIN_RE = re.compile(
    r"^(?=.{1,253}$)(?!-)[a-zA-Z0-9-]{1,63}(?<!-)(\.(?!-)[a-zA-Z0-9-]{1,63}(?<!-))+$"
)


def normalize_domain(raw: str) -> str:
    d = (raw or "").strip().lower()
    d = d.replace("https://", "").replace("http://", "").split("/")[0]
    if d.startswith("www."):
        # keep www as separate query; strip for apex helpers
        pass
    return d.strip(".")


def _dig(domain: str, rtype: str, resolver: str = "") -> List[str]:
    dig = shutil.which("dig")
    if not dig:
        return []
    cmd = [dig, "+short", "+time=3", "+tries=1"]
    if resolver:
        cmd.append(f"@{resolver}")
    cmd.extend([domain, rtype])
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=8)
    except Exception:
        return []
    out: List[str] = []
    for line in (r.stdout or "").splitlines():
        line = line.strip().rstrip(".")
        if line and not line.startswith(";"):
            out.append(line)
    return out


def _socket_a(domain: str) -> List[str]:
    ips: List[str] = []
    try:
        for fam, _, _, _, sockaddr in socket.getaddrinfo(domain, None):
            ip = sockaddr[0]
            if ip and ip not in ips:
                ips.append(ip)
    except Exception:
        pass
    return ips


def lookup_domain(domain: str, expected_ip: str = "") -> Dict[str, Any]:
    """
    Полный DNS-снимок: A/AAAA/NS/MX/TXT/CNAME + сравнение с IP VPS.
    """
    domain = normalize_domain(domain)
    if not domain or not _DOMAIN_RE.match(domain):
        return {"ok": False, "error": f"Некорректный домен: {domain or '(пусто)'}"}

    resolvers = ["", "8.8.8.8", "1.1.1.1"]
    records: Dict[str, List[str]] = {
        "A": [],
        "AAAA": [],
        "NS": [],
        "MX": [],
        "TXT": [],
        "CNAME": [],
    }
    for rtype in list(records.keys()):
        seen: List[str] = []
        for res in resolvers:
            for v in _dig(domain, rtype, res):
                if v not in seen:
                    seen.append(v)
            if seen:
                break
        records[rtype] = seen

    if not records["A"] and not records["AAAA"]:
        # fallback socket (system resolver)
        for ip in _socket_a(domain):
            if ":" in ip:
                if ip not in records["AAAA"]:
                    records["AAAA"].append(ip)
            else:
                if ip not in records["A"]:
                    records["A"].append(ip)

    expected_ip = (expected_ip or os.environ.get("VPS_PUBLIC_IP", "")).strip()
    a_ok = True
    issues: List[str] = []
    if expected_ip:
        if expected_ip not in records["A"] and expected_ip not in records["AAAA"]:
            a_ok = False
            if records["A"] or records["AAAA"]:
                issues.append(
                    f"A/AAAA указывает на {', '.join(records['A'] + records['AAAA'])}, "
                    f"ожидался VPS {expected_ip}"
                )
            else:
                issues.append(f"Нет A/AAAA записи; нужен A → {expected_ip}")

    # NS hint: still on old hosting?
    ns_join = " ".join(records["NS"]).lower()
    if "hosting.reg.ru" in ns_join or "reg.ru" in ns_join:
        issues.append(
            "NS всё ещё на hosting.reg.ru — сайт может открываться со старого хостинга. "
            "Нужны NS регистратора + A на IP VPS (см. DOMAIN_5mb2.md)."
        )

    www = f"www.{domain}" if not domain.startswith("www.") else ""
    www_a: List[str] = []
    if www:
        www_a = _dig(www, "A") or _socket_a(www)

    return {
        "ok": True,
        "domain": domain,
        "records": records,
        "www_a": www_a,
        "expected_ip": expected_ip or None,
        "points_to_vps": a_ok if expected_ip else None,
        "issues": issues,
        "healthy": not issues and bool(records["A"] or records["AAAA"]),
    }


def lookup_many(domains: List[str], expected_ip: str = "") -> List[Dict[str, Any]]:
    out: List[Dict[str, Any]] = []
    seen = set()
    for d in domains:
        nd = normalize_domain(d)
        if not nd or nd in seen:
            continue
        seen.add(nd)
        out.append(lookup_domain(nd, expected_ip=expected_ip))
    return out


def detect_vps_ip() -> str:
    env = os.environ.get("VPS_PUBLIC_IP", "").strip()
    if env:
        return env
    # best-effort from hostname -I (may be private)
    try:
        r = subprocess.run(
            ["hostname", "-I"], capture_output=True, text=True, timeout=3
        )
        for part in (r.stdout or "").split():
            if part and not part.startswith("127.") and not part.startswith("172."):
                return part
            if part.startswith("80.") or part.startswith("185.") or part.startswith("95."):
                return part
    except Exception:
        pass
    return os.environ.get("WATCHDOG_EXPECTED_IP", "80.78.248.195").strip()
