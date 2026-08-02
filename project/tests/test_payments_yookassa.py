#!/usr/bin/env python3
from __future__ import annotations

import sys
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import payments_yookassa as pay  # noqa: E402


class PayTests(unittest.TestCase):
    def test_status_not_configured(self):
        st = pay.status()
        self.assertTrue(st["ok"])
        self.assertIn("configured", st)

    def test_create_manual_without_keys(self):
        # ensure no keys in env for this process
        import os
        os.environ.pop("YOOKASSA_SHOP_ID", None)
        os.environ.pop("YOOKASSA_SECRET_KEY", None)
        r = pay.create_payment(email="test@example.com", plan_id="starter")
        self.assertTrue(r["ok"])
        self.assertEqual(r["mode"], "manual")
        self.assertEqual(r["amount_rub"], 990)

    def test_create_rejects_free(self):
        with self.assertRaises(ValueError):
            pay.create_payment(email="test@example.com", plan_id="free")


if __name__ == "__main__":
    unittest.main()
