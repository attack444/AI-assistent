"""Turnstile: оплата не должна блокироваться без токена."""
from __future__ import annotations

import os
import sys
import unittest
from pathlib import Path
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

import spam_guard as sg  # noqa: E402


class TurnstilePaySoftTests(unittest.TestCase):
    def test_no_secret_skips(self):
        with mock.patch.object(sg, "_turnstile_secret", return_value=""):
            self.assertTrue(sg.verify_turnstile("").get("ok"))

    def test_pay_empty_token_allowed(self):
        with mock.patch.object(sg, "_turnstile_secret", return_value="secret"):
            r = sg.verify_turnstile("", required=False)
            self.assertTrue(r.get("ok"))
            self.assertTrue(r.get("skipped"))

    def test_register_empty_token_blocked(self):
        with mock.patch.object(sg, "_turnstile_secret", return_value="secret"):
            r = sg.verify_turnstile("", required=True)
            self.assertFalse(r.get("ok"))
            self.assertIn("робот", (r.get("error") or "").lower())


if __name__ == "__main__":
    unittest.main()
