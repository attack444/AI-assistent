#!/usr/bin/env python3
"""Regression tests for public auth/plan/path critical bugs."""
from __future__ import annotations

import json
import os
import sys
import tempfile
import time
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_deploy as pd  # noqa: E402
import public_plans as pp  # noqa: E402
import public_users as pu  # noqa: E402


class _H:
    def __init__(self, origin="", referer=""):
        self.headers = {}
        if origin:
            self.headers["Origin"] = origin
        if referer:
            self.headers["Referer"] = referer


class WidgetGuestGuardTests(unittest.TestCase):
    def test_source_field_alone_does_not_allow_guest(self):
        with mock.patch.object(pu, "WIDGET_GUEST", True), mock.patch.object(
            pu, "WIDGET_ORIGINS", ("https://5mb2.ru",)
        ):
            self.assertFalse(pu.widget_guest_allowed(_H()))
            self.assertFalse(pu.widget_guest_allowed(_H(origin="https://evil.example")))

    def test_allowlisted_origin_permits_guest(self):
        with mock.patch.object(pu, "WIDGET_GUEST", True), mock.patch.object(
            pu, "WIDGET_ORIGINS", ("https://5mb2.ru", "https://www.5mb2.ru")
        ):
            self.assertTrue(pu.widget_guest_allowed(_H(origin="https://5mb2.ru")))
            self.assertTrue(
                pu.widget_guest_allowed(_H(referer="https://www.5mb2.ru/contacts/"))
            )

    def test_empty_allowlist_fails_closed(self):
        with mock.patch.object(pu, "WIDGET_GUEST", True), mock.patch.object(
            pu, "WIDGET_ORIGINS", ()
        ):
            self.assertFalse(pu.widget_guest_allowed(_H(origin="https://5mb2.ru")))


class UsersJsonFailClosedTests(unittest.TestCase):
    def test_corrupt_users_json_does_not_return_empty_dict(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td) / "users.json"
            path.write_text("{not-json", encoding="utf-8")
            with self.assertRaises(RuntimeError):
                pu._load_json(path, {})

    def test_empty_users_json_does_not_return_empty_dict(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td) / "users.json"
            path.write_text("   \n", encoding="utf-8")
            with self.assertRaises(RuntimeError):
                pu._load_json(path, {})

    def test_missing_file_returns_default(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td) / "missing.json"
            self.assertEqual(pu._load_json(path, {}), {})


class SiteReserveAndDeployQuotaTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.users_dir = Path(self.tmp.name)
        self.patches = [
            mock.patch.object(pu, "USERS_DIR", self.users_dir),
            mock.patch.object(pu, "USERS_FILE", self.users_dir / "users.json"),
            mock.patch.object(pu, "SESSIONS_FILE", self.users_dir / "sessions.json"),
        ]
        for p in self.patches:
            p.start()
        self.email = "free@example.com"
        users = {
            self.email: {
                "id": "abc",
                "email": self.email,
                "name": "",
                "password_hash": "x",
                "salt": "y",
                "created_at": time.time(),
                "sites": [],
                "plan": "free",
                "usage": {},
            }
        }
        pu._save_json(pu.USERS_FILE, users)

    def tearDown(self):
        for p in self.patches:
            p.stop()
        self.tmp.cleanup()

    def test_parallel_site_reserve_respects_free_cap(self):
        ok1, _, rid1 = pu.reserve_site_slot(self.email)
        ok2, why2, rid2 = pu.reserve_site_slot(self.email)
        self.assertTrue(ok1)
        self.assertTrue(rid1.startswith("pending:"))
        self.assertFalse(ok2)
        self.assertIn("сайт", why2.lower())
        self.assertEqual(rid2, "")
        pu.commit_site_slot(self.email, rid1, "pabcdef12")
        sites = pu.list_sites(self.email)
        self.assertEqual(sites, ["pabcdef12"])

    def test_deploy_quota_blocks_at_limit(self):
        with mock.patch.object(pp, "_day_key", return_value="2099-01-01"):
            for _ in range(5):
                ok, err, _ = pu.consume_quota(self.email, "deploy")
                self.assertTrue(ok, err)
            ok, err, _ = pu.consume_quota(self.email, "deploy")
            self.assertFalse(ok)
            self.assertIn("лимит", err.lower())

    def test_refund_quota_restores_deploy_count(self):
        with mock.patch.object(pp, "_day_key", return_value="2099-01-02"):
            ok, _, _ = pu.consume_quota(self.email, "deploy")
            self.assertTrue(ok)
            pu.refund_quota(self.email, "deploy")
            users = pu._users()
            self.assertEqual(int(users[self.email]["usage"]["deploy"]), 0)

    def test_set_plan_rejects_owner_for_non_owner_email(self):
        with mock.patch.object(pp, "OWNER_EMAIL", "boss@example.com"):
            with self.assertRaises(PermissionError):
                pu.set_plan(self.email, "owner")


class PathGuardTests(unittest.TestCase):
    def test_is_under_root_rejects_prefix_sibling(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td) / "paaaaaaaa"
            sibling = Path(td) / "paaaaaaaab"
            root.mkdir()
            sibling.mkdir()
            target = sibling / "evil.html"
            target.write_text("x", encoding="utf-8")
            self.assertTrue(pd._is_under_root(root / "index.html", root))
            self.assertFalse(pd._is_under_root(target, root))
            # classic startswith false-positive
            self.assertTrue(str(target.resolve()).startswith(str(root.resolve())))


class CheckAndBumpPeekTests(unittest.TestCase):
    def test_bump_false_does_not_increment(self):
        user = {"usage": {}, "sites": []}
        with mock.patch.object(pp, "_day_key", return_value="2099-05-05"):
            ok, _, usage = pp.check_and_bump(user, "chat", "free", bump=False)
        self.assertTrue(ok)
        self.assertEqual(int(usage.get("chat") or 0), 0)
        self.assertEqual(int(user["usage"].get("chat") or 0), 0)


if __name__ == "__main__":
    unittest.main()
