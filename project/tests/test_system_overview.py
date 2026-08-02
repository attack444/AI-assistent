#!/usr/bin/env python3
from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import dns_tools as dt  # noqa: E402
import system_overview as so  # noqa: E402


class DnsToolsTests(unittest.TestCase):
    def test_normalize(self):
        self.assertEqual(dt.normalize_domain("https://5mb2.ru/path"), "5mb2.ru")

    def test_lookup_invalid(self):
        r = dt.lookup_domain("not a domain!!!")
        self.assertFalse(r.get("ok"))

    def test_lookup_mocked(self):
        with mock.patch.object(dt, "_dig", side_effect=lambda d, t, r="": {
            "A": ["80.78.248.195"],
            "AAAA": [],
            "NS": ["ns1.example.com"],
            "MX": [],
            "TXT": [],
            "CNAME": [],
        }.get(t, [])), mock.patch.object(dt, "_socket_a", return_value=[]):
            r = dt.lookup_domain("5mb2.ru", expected_ip="80.78.248.195")
        self.assertTrue(r["ok"])
        self.assertTrue(r["points_to_vps"])
        self.assertEqual(r["records"]["A"], ["80.78.248.195"])

    def test_hosting_ns_issue(self):
        with mock.patch.object(dt, "_dig", side_effect=lambda d, t, r="": {
            "A": ["31.31.197.13"],
            "NS": ["ns1.hosting.reg.ru", "ns2.hosting.reg.ru"],
            "AAAA": [],
            "MX": [],
            "TXT": [],
            "CNAME": [],
        }.get(t, [])), mock.patch.object(dt, "_socket_a", return_value=[]):
            r = dt.lookup_domain("5mb2.ru", expected_ip="80.78.248.195")
        self.assertFalse(r["points_to_vps"])
        self.assertTrue(any("hosting.reg.ru" in x for x in r["issues"]))


class OverviewTests(unittest.TestCase):
    def test_build_overview(self):
        fake_health = {
            "ok": True,
            "failed": [],
            "checks": [],
            "priority_ok": True,
        }
        fake_dns = [{
            "ok": True,
            "domain": "5mb2.ru",
            "site": "5mb2",
            "records": {"A": ["80.78.248.195"], "NS": [], "MX": [], "TXT": [], "AAAA": [], "CNAME": []},
            "issues": [],
            "healthy": True,
        }]
        with mock.patch.object(so.sh, "check_targets", return_value=fake_health), mock.patch.object(
            so.sh, "list_incidents", return_value=[]
        ), mock.patch.object(so, "_collect_domains", return_value=fake_dns), mock.patch.object(
            so, "_docker_ps", return_value=[{"name": "ai-helper-app", "status": "Up", "ports": "8502"}]
        ), mock.patch.object(so.dns_tools, "detect_vps_ip", return_value="80.78.248.195"):
            ov = so.build_overview(api_status={"deepseek": True, "version": "2.10.0"})
        self.assertTrue(ov["ok"])
        self.assertEqual(ov["vps_ip"], "80.78.248.195")
        self.assertTrue(ov["dns"])
        self.assertTrue(any(c["id"] == "backend" for c in ov["capabilities"]))
        self.assertTrue(any(c["id"] == "dns" for c in ov["capabilities"]))

    def test_report_exists(self):
        p = PROJECT / "deploy" / "SYSTEM_REPORT_RU.md"
        self.assertTrue(p.is_file())
        text = p.read_text(encoding="utf-8")
        self.assertIn("DeepSeek", text)
        self.assertIn("watchdog", text)
        self.assertIn("1.9.", text)

    def test_api_has_overview_routes(self):
        api = (PROJECT / "api.py").read_text(encoding="utf-8")
        self.assertIn("/system/overview", api)
        self.assertIn("/system/dns", api)
        self.assertIn('site in {"server", "panel", "backend"}', api)

    def test_compose_mounts_repo(self):
        yml = (PROJECT / "deploy" / "docker-compose.prod.yml").read_text(encoding="utf-8")
        self.assertIn("/opt/ai-helper:/opt/ai-helper", yml)
        self.assertIn("AI_HELPER_PROJECT=/opt/ai-helper/project", yml)


if __name__ == "__main__":
    unittest.main()
