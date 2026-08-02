#!/usr/bin/env python3
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import panel_uploads as pu  # noqa: E402
import payments_yookassa as pay  # noqa: E402
import growth_pack as gp  # noqa: E402


class SecurityHardeningTests(unittest.TestCase):
    def test_upload_id_rejects_traversal(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            with self.assertRaises(ValueError):
                pu.load_meta(root, "../etc")
            with self.assertRaises(ValueError):
                pu.load_meta(root, "abc/../def")
            with self.assertRaises(ValueError):
                pu.load_meta(root, "not-hex-id!!!")

    def test_webhook_verifies_via_api(self):
        payload = {
            "event": "payment.succeeded",
            "object": {
                "id": "pay-1",
                "metadata": {"email": "a@b.ru", "plan": "starter"},
            },
        }
        with mock.patch.object(pay, "configured", return_value=True), mock.patch.object(
            pay,
            "fetch_payment",
            return_value={
                "status": "succeeded",
                "metadata": {"email": "a@b.ru", "plan": "starter"},
            },
        ), mock.patch.object(pay, "apply_paid_plan", return_value={"ok": True}) as ap, mock.patch.object(
            pay, "_append"
        ):
            r = pay.handle_webhook(payload)
        self.assertTrue(r.get("ok"))
        self.assertTrue(r.get("verified"))
        ap.assert_called_once_with("a@b.ru", "starter")

    def test_webhook_rejects_unverified(self):
        payload = {
            "event": "payment.succeeded",
            "object": {"id": "pay-x", "metadata": {"email": "a@b.ru", "plan": "pro"}},
        }
        with mock.patch.object(pay, "configured", return_value=True), mock.patch.object(
            pay, "fetch_payment", side_effect=RuntimeError("nope")
        ):
            r = pay.handle_webhook(payload)
        self.assertFalse(r.get("ok"))

    def test_growth_pack(self):
        pack = gp.build_pack()
        self.assertTrue(pack["ok"])
        self.assertIn("channels", pack)
        self.assertTrue(any(c["id"] == "directories" for c in pack["channels"]))

    def test_compose_binds_localhost(self):
        yml = (PROJECT / "deploy" / "docker-compose.prod.yml").read_text(encoding="utf-8")
        self.assertIn("127.0.0.1:8502:8502", yml)
        self.assertIn("127.0.0.1:3000:3000", yml)
        self.assertIn('profiles: ["extra"]', yml)

    def test_dockerfile_streamlit_optional(self):
        df = (PROJECT / "deploy" / "Dockerfile").read_text(encoding="utf-8")
        self.assertIn("ENABLE_STREAMLIT", df)


if __name__ == "__main__":
    unittest.main()
