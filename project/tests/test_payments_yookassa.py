#!/usr/bin/env python3
from __future__ import annotations

import json
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

    def test_packages_listed(self):
        pkgs = pay.list_packages()
        ids = {p["id"] for p in pkgs}
        self.assertIn("mb2-seo-audit", ids)
        self.assertEqual(
            next(p for p in pkgs if p["id"] == "mb2-seo-audit")["price_rub"], 29000
        )

    def test_create_package_without_keys(self):
        with mock.patch.object(pay, "_creds", return_value=("", "")):
            r = pay.create_package_payment(
                email="client@example.com", package_id="mb2-seo-audit"
            )
        self.assertFalse(r["ok"])
        self.assertEqual(r["mode"], "not_configured")
        self.assertEqual(r["amount_rub"], 29000)

    def test_create_builds_receipt(self):
        captured = {}

        def fake_yk(method, path, *, shop, secret, body=None, idem=""):
            captured["body"] = json.dumps(body or {})
            return {
                "http_status": 200,
                "www_authenticate": "",
                "data": {
                    "id": "pay-1",
                    "status": "pending",
                    "confirmation": {"confirmation_url": "https://yookassa.ru/pay"},
                },
                "raw": "",
            }

        with mock.patch.object(
            pay, "_creds", return_value=("123456", "test_secretkeyxxxxxxxxxxxxxxxxxxxx")
        ), mock.patch.object(pay, "_append"), mock.patch.object(
            pay, "_yookassa_request", side_effect=fake_yk
        ):
            r = pay.create_payment(email="buyer@example.com", plan_id="pro")
        self.assertTrue(r["ok"])
        self.assertEqual(r["amount_rub"], 2990)
        self.assertIn("confirmation_url", r)
        self.assertIn('"receipt"', captured["body"])
        self.assertIn("buyer@example.com", captured["body"])
        self.assertIn("2990.00", captured["body"])

    def test_normalize_creds_splits_combined(self):
        shop, secret = pay.normalize_creds("", "987654:test_abcXYZ")
        self.assertEqual(shop, "987654")
        self.assertEqual(secret, "test_abcXYZ")

    def test_normalize_strips_quotes_and_bearer(self):
        shop, secret = pay.normalize_creds(' "112233" ', "Bearer test_hello")
        self.assertEqual(shop, "112233")
        self.assertEqual(secret, "test_hello")

    def test_friendly_auth_type_error(self):
        msg = pay._friendly_http_error(
            401,
            '{"description":"Authentication type is not allowed","code":"invalid_credentials"}',
        )
        self.assertIn("API", msg)
        self.assertIn("Секретный ключ", msg)

    def test_verify_rejects_bad_prefix(self):
        r = pay.verify_connection(shop_id="123456", secret_key="oauth-token-xxx")
        self.assertFalse(r["ok"])
        self.assertIn("test_", r["error"])

    def test_save_and_verify_rejects_short_secret(self):
        r = pay.save_and_verify("123456", "test_short")
        self.assertFalse(r["ok"])
        self.assertFalse(r.get("saved"))

    def test_rejects_masked_secret_with_star(self):
        r = pay.save_and_verify(
            "1428273",
            "test_*gpwStZPZ37x0lF2Ydb33MTmhdhRTqJwP6jA_pLlQQQbs",
        )
        self.assertFalse(r["ok"])
        self.assertFalse(r.get("saved"))
        self.assertTrue(r.get("secret_masked"))
        self.assertIn("МАСК", (r.get("error") or "").upper())

    def test_validate_api_creds_masked(self):
        err = pay.validate_api_creds("1428273", "test_*abcDEFGHIJKLMNOPQRSTUVWXYZ0123")
        self.assertIsNotNone(err)
        self.assertIn("МАСК", err.upper())

    def test_fingerprint_hides_secret(self):
        fp = pay.fingerprint("998877", "test_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345")
        self.assertEqual(fp["shop_id"], "998877")
        self.assertEqual(fp["secret_prefix"], "test_")
        self.assertEqual(fp["secret_tail"], "2345")
        self.assertTrue(fp["format_ok"])
        self.assertNotIn("ABCDEF", json.dumps(fp))


if __name__ == "__main__":
    unittest.main()
