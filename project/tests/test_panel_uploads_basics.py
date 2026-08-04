#!/usr/bin/env python3
"""Unit tests for chunked panel uploads (validation / filename sanitization)."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import panel_uploads as pup  # noqa: E402


class PanelUploadBasicsTests(unittest.TestCase):
    def test_init_rejects_non_positive_size(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            with self.assertRaises(ValueError):
                pup.init_upload(root, filename="a.zip", size=0)
            with self.assertRaises(ValueError):
                pup.init_upload(root, filename="a.zip", size=-1)

    def test_init_sanitizes_cyrillic_filename(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="бэкап сайта.zip",
                size=10,
                chunk_size=10,
            )
            # ASCII-safe on-disk name; original preserved separately
            self.assertTrue(meta["filename"])
            self.assertNotRegex(meta["filename"], r"[^\x20-\x7e]")
            self.assertTrue(meta["filename"].endswith(".zip") or "zip" in meta["filename"])
            uid = meta["upload_id"]
            _, loaded = pup.load_meta(root, uid)
            self.assertEqual(loaded["original_filename"], "бэкап сайта.zip")
            self.assertEqual(loaded["filename"], meta["filename"])

    def test_init_preserves_sql_suffix_when_stripped(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="дамп.sql",
                size=5,
                chunk_size=5,
            )
            self.assertTrue(meta["filename"].lower().endswith(".sql"))

    def test_save_chunk_rejects_bad_index(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="site.zip",
                size=100,
                chunk_size=50,
            )
            uid = meta["upload_id"]
            with self.assertRaises(ValueError):
                pup.save_chunk(root, uid, -1, b"x" * 50)
            with self.assertRaises(ValueError):
                pup.save_chunk(root, uid, 99, b"x" * 50)

    def test_status_reports_missing_chunks(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="site.zip",
                size=100,
                chunk_size=50,
            )
            uid = meta["upload_id"]
            pup.save_chunk(root, uid, 0, b"a" * 50)
            st = pup.status(root, uid)
            self.assertEqual(st["received"], 1)
            self.assertEqual(st["missing"], [1])
            self.assertFalse(st["complete"])

    def test_assemble_requires_all_chunks(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="dump.sql",
                size=60,
                chunk_size=30,
            )
            uid = meta["upload_id"]
            pup.save_chunk(root, uid, 0, b"a" * 30)
            with self.assertRaises(ValueError) as ctx:
                pup.assemble(root, uid)
            self.assertIn("Не все чанки", str(ctx.exception))

    def test_assemble_happy_path(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            meta = pup.init_upload(
                root,
                filename="dump.sql",
                size=60,
                chunk_size=30,
            )
            uid = meta["upload_id"]
            pup.save_chunk(root, uid, 0, b"a" * 30)
            pup.save_chunk(root, uid, 1, b"b" * 30)
            out = pup.assemble(root, uid)
            self.assertEqual(out.read_bytes(), b"a" * 30 + b"b" * 30)
            # Second assemble returns cached path
            out2 = pup.assemble(root, uid)
            self.assertEqual(out2, out)

    def test_load_meta_missing(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            with self.assertRaises(FileNotFoundError):
                pup.load_meta(root, "deadbeef" * 4)


if __name__ == "__main__":
    unittest.main()
