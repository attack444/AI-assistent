"""Секреты не затираются пустым полем / маской из панели."""
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))


class OwnerSettingsSecretTests(unittest.TestCase):
    def test_empty_secret_does_not_wipe(self):
        import owner_settings as osset

        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "owner_settings.json"
            with mock.patch.object(osset, "SETTINGS_FILE", path), mock.patch.object(
                osset, "DATA_DIR", Path(tmp)
            ):
                osset.update_settings({
                    "yookassa_shop_id": "123456",
                    "yookassa_secret_key": "test_realSecretKeyValue99",
                })
                osset.update_settings({
                    "yookassa_shop_id": "123456",
                    "yookassa_secret_key": "",
                })
                raw = json.loads(path.read_text(encoding="utf-8"))
                self.assertEqual(raw["yookassa_secret_key"], "test_realSecretKeyValue99")

    def test_mask_does_not_wipe(self):
        import owner_settings as osset

        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "owner_settings.json"
            with mock.patch.object(osset, "SETTINGS_FILE", path), mock.patch.object(
                osset, "DATA_DIR", Path(tmp)
            ):
                osset.update_settings({
                    "yookassa_shop_id": "123456",
                    "yookassa_secret_key": "test_realSecretKeyValue99",
                })
                osset.update_settings({"yookassa_secret_key": "••••ue99"})
                raw = json.loads(path.read_text(encoding="utf-8"))
                self.assertEqual(raw["yookassa_secret_key"], "test_realSecretKeyValue99")

    def test_new_secret_saves(self):
        import owner_settings as osset

        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "owner_settings.json"
            with mock.patch.object(osset, "SETTINGS_FILE", path), mock.patch.object(
                osset, "DATA_DIR", Path(tmp)
            ):
                osset.update_settings({
                    "yookassa_shop_id": "123456",
                    "yookassa_secret_key": "test_oldKeyAAAAAAAA",
                })
                osset.update_settings({"yookassa_secret_key": "test_newKeyBBBBBBBB"})
                raw = json.loads(path.read_text(encoding="utf-8"))
                self.assertEqual(raw["yookassa_secret_key"], "test_newKeyBBBBBBBB")

    def test_masked_response_not_real_secret(self):
        import owner_settings as osset

        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "owner_settings.json"
            with mock.patch.object(osset, "SETTINGS_FILE", path), mock.patch.object(
                osset, "DATA_DIR", Path(tmp)
            ):
                osset.update_settings({
                    "yookassa_shop_id": "123456",
                    "yookassa_secret_key": "test_realSecretKeyValue99",
                })
                view = osset.get_settings(mask_secrets=True)
                self.assertTrue(str(view["yookassa_secret_key"]).startswith("••••"))
                self.assertNotIn("realSecret", str(view["yookassa_secret_key"]))
                self.assertTrue(view.get("yookassa_secret_key_set"))


if __name__ == "__main__":
    unittest.main()
