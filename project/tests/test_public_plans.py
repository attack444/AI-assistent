#!/usr/bin/env python3
"""Unit tests for public plan limits (billing / abuse controls)."""
from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_plans as pp  # noqa: E402


class PublicPlansTests(unittest.TestCase):
    def test_normalize_plan_unknown_falls_back(self):
        self.assertEqual(pp.normalize_plan("starter"), "starter")
        self.assertEqual(pp.normalize_plan("nope"), pp.DEFAULT_PLAN if pp.DEFAULT_PLAN in pp.PLANS else "free")
        self.assertEqual(pp.normalize_plan(""), pp.DEFAULT_PLAN if pp.DEFAULT_PLAN in pp.PLANS else "free")

    def test_list_public_plans_hides_owner(self):
        ids = {p["id"] for p in pp.list_public_plans()}
        self.assertIn("free", ids)
        self.assertIn("starter", ids)
        self.assertIn("pro", ids)
        self.assertNotIn("owner", ids)

    def test_owner_email_gets_owner_plan(self):
        with mock.patch.object(pp, "OWNER_EMAIL", "boss@example.com"):
            self.assertEqual(pp.plan_for_email("boss@example.com", "free"), "owner")
            self.assertEqual(pp.plan_for_email("other@example.com", "starter"), "starter")

    def test_ensure_usage_resets_on_new_day(self):
        user = {"usage": {"day": "2000-01-01", "chat": 9, "deploy": 3}}
        with mock.patch.object(pp, "_day_key", return_value="2099-12-31"):
            usage = pp.ensure_usage(user)
        self.assertEqual(usage["day"], "2099-12-31")
        self.assertEqual(usage["chat"], 0)
        self.assertEqual(usage["deploy"], 0)

    def test_chat_limit_blocks_then_allows_unlimited_owner(self):
        user = {"usage": {}, "sites": []}
        with mock.patch.object(pp, "_day_key", return_value="2099-01-01"):
            for _ in range(30):
                ok, err, _ = pp.check_and_bump(user, "chat", "free")
                self.assertTrue(ok, err)
            ok, err, usage = pp.check_and_bump(user, "chat", "free")
            self.assertFalse(ok)
            self.assertIn("лимит", err.lower())
            self.assertEqual(usage["chat"], 30)

            ok, err, _ = pp.check_and_bump(user, "chat", "owner")
            self.assertTrue(ok)
            self.assertEqual(err, "")

    def test_site_cap_for_free(self):
        user = {"usage": {}, "sites": ["p11111111"]}
        ok, err, _ = pp.check_and_bump(user, "site", "free")
        self.assertFalse(ok)
        self.assertIn("сайт", err.lower())

        user2 = {"usage": {}, "sites": ["a", "b", "c", "d"]}
        ok2, _, _ = pp.check_and_bump(user2, "site", "starter")
        self.assertTrue(ok2)

    def test_deploy_bump_persists_on_user_record(self):
        user = {"usage": {}, "sites": []}
        with mock.patch.object(pp, "_day_key", return_value="2099-02-02"):
            ok, _, usage = pp.check_and_bump(user, "deploy", "free")
        self.assertTrue(ok)
        self.assertEqual(usage["deploy"], 1)
        self.assertEqual(user["usage"]["deploy"], 1)

    def test_usage_public_shape(self):
        user = {"usage": {"day": "2099-03-03", "chat": 2, "deploy": 1}, "sites": ["p1", "p2"]}
        with mock.patch.object(pp, "_day_key", return_value="2099-03-03"):
            snap = pp.usage_public(user, "starter")
        self.assertEqual(snap["plan"]["id"], "starter")
        self.assertEqual(snap["limits"]["chat_per_day"], 300)
        self.assertEqual(snap["usage"]["chat"], 2)
        self.assertEqual(snap["usage"]["sites"], 2)


if __name__ == "__main__":
    unittest.main()
