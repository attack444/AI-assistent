#!/usr/bin/env python3
"""Регрессия: вход в /console не должен редиректить на витрину /login."""
from __future__ import annotations

import unittest
from pathlib import Path

WEB = Path(__file__).resolve().parents[1] / "web"


class PanelLoginPathTests(unittest.TestCase):
    def test_api_client_has_console_aware_login_redirect(self):
        text = (WEB / "lib" / "api.ts").read_text(encoding="utf-8")
        self.assertIn("panelLoginPath", text)
        self.assertIn("skipAuthRedirect", text)
        self.assertIn("/console/login/", text)
        # старый баг: голый /login при basePath
        self.assertNotIn('window.location.href = "/login"', text)

    def test_login_page_stays_on_error(self):
        text = (WEB / "app" / "login" / "page.tsx").read_text(encoding="utf-8")
        self.assertIn("skipAuthRedirect", (WEB / "lib" / "api.ts").read_text(encoding="utf-8"))
        self.assertIn("setError", text)
        self.assertIn("/overview/", text)


if __name__ == "__main__":
    unittest.main()
