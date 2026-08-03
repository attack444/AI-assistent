#!/usr/bin/env python3
from __future__ import annotations

import sys
import tempfile
import unittest
from collections import defaultdict, deque
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_users as pu  # noqa: E402


class PublicAuthTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        root = Path(self.tmp.name)
        self.addCleanup(self.tmp.cleanup)
        pu._reg_hits = defaultdict(deque)
        pu._login_hits = defaultdict(deque)
        self._patchers = [
            mock.patch.object(pu, "USERS_DIR", root),
            mock.patch.object(pu, "USERS_FILE", root / "users.json"),
            mock.patch.object(pu, "SESSIONS_FILE", root / "sessions.json"),
        ]
        for p in self._patchers:
            p.start()
            self.addCleanup(p.stop)

    def test_password_not_auto_generated_on_register(self):
        with self.assertRaises(ValueError):
            pu.register("a@b.ru", "", ip="1.1.1.1")
        with self.assertRaises(ValueError):
            pu.register("a@b.ru", "123", ip="1.1.1.1")
        with self.assertRaises(ValueError):
            pu.register("a@b.ru", "password", ip="1.1.1.1")

    def test_register_login_change_password(self):
        with mock.patch("public_plans.plan_for_email", return_value="free"), mock.patch(
            "public_plans.ensure_usage", side_effect=lambda u: u
        ), mock.patch(
            "public_plans.usage_public",
            return_value={"usage": {}, "limits": {}},
        ):
            r = pu.register("user@test.ru", "MySecret9!", name="U", ip="2.2.2.2")
            self.assertTrue(r["ok"])
            self.assertTrue(r["token"])
            self.assertEqual(r["user"]["email"], "user@test.ru")

            bad = None
            try:
                pu.login("user@test.ru", "WrongPass9!", ip="2.2.2.2")
            except PermissionError as exc:
                bad = str(exc)
            self.assertIsNotNone(bad)

            ok = pu.login("user@test.ru", "MySecret9!", ip="2.2.2.2")
            self.assertTrue(ok["ok"])

            ch = pu.change_password("user@test.ru", "MySecret9!", "NewSecret9!")
            self.assertTrue(ch["ok"])
            again = pu.login("user@test.ru", "NewSecret9!", ip="2.2.2.2")
            self.assertTrue(again["ok"])

    def test_oauth_user_can_set_password_without_old(self):
        with mock.patch("public_plans.plan_for_email", return_value="free"), mock.patch(
            "public_plans.ensure_usage", side_effect=lambda u: u
        ), mock.patch(
            "public_plans.usage_public",
            return_value={"usage": {}, "limits": {}},
        ):
            r = pu.login_or_register_oauth(
                email="oauth@test.ru",
                name="O",
                provider="google",
                oauth_id="123",
            )
            self.assertTrue(r["ok"])
            with self.assertRaises(PermissionError):
                pu.login("oauth@test.ru", "Anything1!", ip="3.3.3.3")
            pu.change_password("oauth@test.ru", "", "Cabinet9!")
            ok = pu.login("oauth@test.ru", "Cabinet9!", ip="3.3.3.3")
            self.assertTrue(ok["ok"])

    def test_oauth_status_module(self):
        import oauth_public as oa

        with mock.patch.object(
            oa,
            "_settings",
            return_value={
                "google_client_id": "g",
                "google_client_secret": "s",
                "github_client_id": "",
                "github_client_secret": "",
                "public_site_url": "https://neobrain.site",
            },
        ):
            st = oa.status()
        self.assertTrue(st["google"])
        self.assertFalse(st["github"])

    def test_api_no_default_auto_panel_password(self):
        api = (PROJECT / "api.py").read_text(encoding="utf-8")
        self.assertIn("ALLOW_AUTO_PANEL_PASSWORD", api)
        self.assertIn("Пароль панели задаёт владелец", api)
        self.assertIn("/public/auth/oauth/status", api)
        self.assertIn("neobrain-user-token", api)

    def test_harden_requires_panel_password(self):
        sh = (PROJECT / "deploy" / "harden-vps.sh").read_text(encoding="utf-8")
        self.assertIn("reset-panel-password.sh", sh)
        self.assertIn("PANEL_PASSWORD_INIT", sh)
        self.assertIn("ALLOW_AUTO_PANEL_PASSWORD", sh)
        self.assertIn("exit 1", sh)


if __name__ == "__main__":
    unittest.main()
