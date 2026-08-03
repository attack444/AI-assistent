#!/usr/bin/env python3
from __future__ import annotations

import os
import sys
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import payments_yookassa as pay  # noqa: E402


class PayTests(unittest.TestCase):
    def test_status_fixed_price(self):
        st = pay.status()
        self.assertTrue(st["ok"])
        self.assertEqual(st.get("mode"), "fixed_price")
        self.assertIn("webhook_url", st)
        self.assertTrue(st["webhook_url"].endswith("/api/public/pay/webhook"))

    def test_create_without_keys(self):
        os.environ.pop("YOOKASSA_SHOP_ID", None)
        os.environ.pop("YOOKASSA_SECRET_KEY", None)
        with mock.patch.object(pay, "_creds", return_value=("", "")):
            r = pay.create_payment(email="test@example.com", plan_id="starter")
        self.assertFalse(r["ok"])
        self.assertEqual(r["mode"], "not_configured")
        self.assertEqual(r["amount_rub"], 990)
        self.assertIn("rekvizity", r.get("rekvizity_url", ""))

    def test_create_rejects_free(self):
        with self.assertRaises(ValueError):
            pay.create_payment(email="test@example.com", plan_id="free")

    def test_create_builds_receipt(self):
        captured = {}

        class FakeResp:
            def __enter__(self):
                return self

            def __exit__(self, *a):
                return False

            def read(self):
                return b'{"id":"pay-1","status":"pending","confirmation":{"confirmation_url":"https://yookassa.ru/pay"}}'

        def fake_urlopen(req, timeout=25):
            captured["body"] = req.data.decode("utf-8")
            return FakeResp()

        with mock.patch.object(pay, "_creds", return_value=("shop", "secret")), mock.patch.object(
            pay, "_append"
        ), mock.patch("urllib.request.urlopen", side_effect=fake_urlopen):
            r = pay.create_payment(email="buyer@example.com", plan_id="pro")
        self.assertTrue(r["ok"])
        self.assertEqual(r["amount_rub"], 2990)
        self.assertIn("confirmation_url", r)
        self.assertIn('"receipt"', captured["body"])
        self.assertIn("buyer@example.com", captured["body"])
        self.assertIn("2990.00", captured["body"])


if __name__ == "__main__":
    unittest.main()
