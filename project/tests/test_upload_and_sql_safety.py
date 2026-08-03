#!/usr/bin/env python3
"""Regression: truncated chunked uploads + SQL wipe-before-validate."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import panel_uploads as pup  # noqa: E402
import wp_tools as wpt  # noqa: E402


class ChunkUploadSizeTests(unittest.TestCase):
    def test_short_chunk_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="dump.sql",
                size=100,
                chunk_size=50,
            )
            uid = meta["upload_id"]
            pup.save_chunk(root, uid, 0, b"a" * 50)
            with self.assertRaises(ValueError) as ctx:
                pup.save_chunk(root, uid, 1, b"b" * 10)
            self.assertIn("ждали 50", str(ctx.exception))

    def test_assemble_rejects_size_mismatch(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="site.zip",
                size=100,
                chunk_size=50,
                total_chunks=2,
            )
            uid = meta["upload_id"]
            upload_dir, loaded = pup.load_meta(root, uid)
            # Bypass save_chunk guards to simulate older truncated parts on disk.
            (upload_dir / pup.CHUNK_DIR / "000000.part").write_bytes(b"a" * 50)
            (upload_dir / pup.CHUNK_DIR / "000001.part").write_bytes(b"b" * 10)
            loaded["received"] = [0, 1]
            pup.save_meta(upload_dir, loaded)
            with self.assertRaises(ValueError) as ctx:
                pup.assemble(root, uid)
            self.assertIn("заявлено 100", str(ctx.exception))

    def test_assemble_exact_size_ok(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="dump.sql",
                size=100,
                chunk_size=40,
            )
            uid = meta["upload_id"]
            pup.save_chunk(root, uid, 0, b"a" * 40)
            pup.save_chunk(root, uid, 1, b"b" * 40)
            pup.save_chunk(root, uid, 2, b"c" * 20)
            out = pup.assemble(root, uid)
            self.assertEqual(out.stat().st_size, 100)


class SqlDumpValidateTests(unittest.TestCase):
    def test_garbage_rejected_before_drop(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "bad.sql"
            path.write_text("not a dump\n" * 300, encoding="utf-8")
            err = wpt.validate_sql_dump_for_import(path)
            self.assertIsNotNone(err)
            self.assertIn("CREATE TABLE", err or "")

    def test_tiny_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "tiny.sql"
            path.write_text("CREATE TABLE t (id INT);\n", encoding="utf-8")
            err = wpt.validate_sql_dump_for_import(path)
            self.assertIsNotNone(err)
            self.assertIn("маленький", err or "")

    def test_wp_dump_accepted(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "wp.sql"
            body = (
                "-- WordPress dump\n"
                "CREATE TABLE `wp_options` (\n"
                "  `option_id` bigint NOT NULL\n"
                ") ENGINE=InnoDB;\n"
                "INSERT INTO `wp_options` VALUES (1,'siteurl','https://x');\n"
            )
            path.write_text(body * 40, encoding="utf-8")
            self.assertIsNone(wpt.validate_sql_dump_for_import(path))

    def test_import_sql_skips_drop_on_invalid_dump(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "garbage.sql"
            path.write_text("x" * 3000, encoding="utf-8")
            drop = mock.Mock()
            with mock.patch.object(wpt, "ensure_mysql_user", return_value={"ok": True}), mock.patch.object(
                wpt, "_drop_all_tables", drop
            ), mock.patch.object(
                wpt, "_prepare_cleaned_sql_dump", side_effect=AssertionError("must not prepare")
            ):
                result = wpt.import_sql_file(path, drop_existing=True)
            self.assertFalse(result.get("ok"))
            drop.assert_not_called()
            self.assertTrue(any("CREATE TABLE" in e for e in result.get("errors") or []))


if __name__ == "__main__":
    unittest.main()
