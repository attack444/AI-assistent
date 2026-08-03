#!/usr/bin/env python3
"""Локальные тесты watchdog / system_health (без живого VPS)."""
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import system_health as sh  # noqa: E402


class SystemHealthTests(unittest.TestCase):
    def test_record_and_list_incident(self):
        with tempfile.TemporaryDirectory() as tmp:
            data = Path(tmp)
            with mock.patch.object(sh, "DATA_DIR", data), mock.patch.object(
                sh, "INCIDENTS_FILE", data / "system_incidents.jsonl"
            ), mock.patch.dict("sys.modules", {"public_feedback": mock.MagicMock()}):
                report = {
                    "ok": False,
                    "at": "2026-01-01 00:00:00",
                    "failed": ["5mb2", "api"],
                    "priority_failed": ["api"],
                    "checks": [
                        {"id": "api", "ok": False, "status": 0, "error": "down", "ms": 1},
                        {"id": "5mb2", "ok": False, "status": 500, "error": "fatal", "ms": 2},
                    ],
                }
                item = sh.record_incident(report)
                self.assertIsNotNone(item)
                self.assertEqual(item["source"], "watchdog")
                listed = sh.list_incidents(10)
                self.assertEqual(len(listed), 1)
                self.assertIn("5mb2", listed[0]["failed"])

    def test_healthy_no_incident(self):
        with tempfile.TemporaryDirectory() as tmp:
            data = Path(tmp)
            with mock.patch.object(sh, "DATA_DIR", data), mock.patch.object(
                sh, "INCIDENTS_FILE", data / "system_incidents.jsonl"
            ):
                self.assertIsNone(sh.record_incident({"ok": True, "failed": []}))

    def test_check_targets_ok(self):
        def fake_http(url, **kwargs):
            if "/status" in url:
                return {
                    "ok": True,
                    "status": 200,
                    "bytes": 10,
                    "ms": 1,
                    "url": url,
                    "json": {
                        "ok": True,
                        "deepseek": True,
                        "deepseek_model": "deepseek-chat",
                        "llm_prefer_free": False,
                    },
                }
            if "mb2_health" in url:
                return {
                    "ok": True,
                    "status": 200,
                    "bytes": 20,
                    "ms": 1,
                    "url": url,
                    "json": {"ok": True, "service": "5mb2"},
                }
            return {"ok": True, "status": 200, "bytes": 1000, "ms": 5, "url": url}

        with mock.patch.object(sh, "_http", side_effect=fake_http):
            report = sh.check_targets(base_url="https://example.test", host="5mb2.ru")
        self.assertTrue(report["ok"])
        self.assertTrue(report["priority_ok"])
        ids = [c["id"] for c in report["checks"]]
        self.assertIn("panel", ids)
        self.assertIn("deepseek", ids)
        self.assertIn("5mb2", ids)
        self.assertIn("neobrain", ids)

    def test_safe_remediate_restarts_priority(self):
        report = {"failed": ["api", "panel", "5mb2"]}
        with mock.patch.object(sh, "_docker_restart", return_value=[{"ok": True}]) as m:
            actions = sh.safe_remediate(report)
        self.assertTrue(actions)
        names = m.call_args[0][0]
        self.assertIn("ai-helper-app", names)
        self.assertIn("ai-helper-web", names)
        self.assertIn("ai-helper-php", names)

    def test_theme_version_constant(self):
        fn = PROJECT / "sites/5mb2/wp-content/themes/5mb2-dark/functions.php"
        text = fn.read_text(encoding="utf-8")
        self.assertIn("MB2_THEME_VER', '1.9.9'", text)
        self.assertIn("admin_init", text)
        self.assertIn("mb2_structure_lock", text)
        # seed больше не на фронтовом init
        self.assertNotRegex(text, r"add_action\(\s*'init'\s*,\s*function\s*\(\s*\)\s*\{[^}]*mb2_structure_ver")

    def test_mu_plugin_exists(self):
        p = PROJECT / "sites/5mb2/wp-content/mu-plugins/mb2-health-guard.php"
        self.assertTrue(p.is_file())
        self.assertIn("mb2_health", p.read_text(encoding="utf-8"))

    def test_api_routes_mention_system(self):
        api = (PROJECT / "api.py").read_text(encoding="utf-8")
        self.assertIn("/system/health", api)
        self.assertIn("/system/watchdog", api)
        self.assertIn("_post_system_watchdog", api)


if __name__ == "__main__":
    unittest.main()
