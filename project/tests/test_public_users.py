#!/usr/bin/env python3
"""Unit tests for public auth / sessions / quota persistence."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_users as pu  # noqa: E402


class PublicUsersTests(unittest.TestCase):
    def setUp(self):
        pu._reg_hits.clear()
        self._tmpdir = tempfile.TemporaryDirectory()
        self.data = Path(self._tmpdir.name)
        self.patches = [
            mock.patch.object(pu, "USERS_DIR", self.data),
            mock.patch.object(pu, "USERS_FILE", self.data / "users.json"),
            mock.patch.object(pu, "SESSIONS_FILE", self.data / "sessions.json"),
        ]
        for p in self.patches:
            p.start()

    def tearDown(self):
        for p in self.patches:
            p.stop()
        self._tmpdir.cleanup()
        pu._reg_hits.clear()

    def test_register_rejects_bad_email_and_short_password(self):
        with self.assertRaises(ValueError):
            pu.register("not-an-email", "password123")
        with self.assertRaises(ValueError):
            pu.register("ok@example.com", "short")

    def test_register_login_logout_roundtrip(self):
        out = pu.register("User@Example.COM", "password123", name="Ada", ip="1.2.3.4")
        self.assertTrue(out["ok"])
        self.assertTrue(out["token"])
        self.assertEqual(out["user"]["email"], "user@example.com")

        with self.assertRaises(ValueError):
            pu.register("user@example.com", "password123", ip="1.2.3.4")

        with self.assertRaises(PermissionError):
            pu.login("user@example.com", "wrong-password")

        logged = pu.login("user@example.com", "password123")
        me = pu.user_from_token(logged["token"])
        self.assertIsNotNone(me)
        self.assertEqual(me["email"], "user@example.com")

        pu.logout(logged["token"])
        self.assertIsNone(pu.user_from_token(logged["token"]))

    def test_expired_session_is_rejected(self):
        out = pu.register("exp@example.com", "password123", ip="9.9.9.9")
        token = out["token"]
        th = pu._hash_token(token)
        sessions = pu._sessions()
        sessions[th]["expires_at"] = 1.0
        pu._save_json(pu.SESSIONS_FILE, sessions)
        self.assertIsNone(pu.user_from_token(token))

    def test_register_rate_limit(self):
        with mock.patch.object(pu, "REG_LIMIT", 2), mock.patch.object(pu, "REG_WINDOW", 3600):
            self.assertTrue(pu.check_register_rate("10.0.0.1")[0])
            self.assertTrue(pu.check_register_rate("10.0.0.1")[0])
            ok, why = pu.check_register_rate("10.0.0.1")
            self.assertFalse(ok)
            self.assertIn("Лимит", why)

    def test_consume_quota_persists_chat_usage(self):
        pu.register("quota@example.com", "password123", ip="8.8.8.8")
        with mock.patch("public_plans._day_key", return_value="2099-04-04"):
            ok, why, snap = pu.consume_quota("quota@example.com", "chat")
            self.assertTrue(ok, why)
            self.assertEqual(snap["usage"]["chat"], 1)
            # reload from disk
            users = pu._users()
            self.assertEqual(users["quota@example.com"]["usage"]["chat"], 1)

    def test_bearer_token_headers(self):
        class H:
            headers = {"Authorization": "Bearer abc.def"}
        self.assertEqual(pu.bearer_token(H()), "abc.def")

        class H2:
            headers = {"X-User-Token": "tok2"}
        self.assertEqual(pu.bearer_token(H2()), "tok2")

    def test_attach_and_list_sites(self):
        pu.register("sites@example.com", "password123", ip="7.7.7.7")
        pu.attach_site("sites@example.com", "pabcdef12")
        pu.attach_site("sites@example.com", "pabcdef12")  # idempotent
        self.assertEqual(pu.list_sites("sites@example.com"), ["pabcdef12"])


if __name__ == "__main__":
    unittest.main()
