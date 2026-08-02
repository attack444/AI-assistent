#!/usr/bin/env python3
"""Security-focused tests for public deploy sandbox (path traversal / tokens)."""
from __future__ import annotations

import sys
import tempfile
import time
import unittest
import zipfile
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_deploy as pd  # noqa: E402


class PublicDeploySecurityTests(unittest.TestCase):
    def setUp(self):
        pd._hits.clear()
        self._tmpdir = tempfile.TemporaryDirectory()
        self.root = Path(self._tmpdir.name)
        self.sites = self.root / "sites"
        self.meta = self.root / "meta"
        self.sites.mkdir()
        self.meta.mkdir()
        self.patches = [
            mock.patch.object(pd, "SITES_ROOT", self.sites),
            mock.patch.object(pd, "META_DIR", self.meta),
        ]
        for p in self.patches:
            p.start()

    def tearDown(self):
        for p in self.patches:
            p.stop()
        self._tmpdir.cleanup()
        pd._hits.clear()

    def test_safe_member_rejects_traversal_and_dotfiles(self):
        self.assertFalse(pd._safe_member("../etc/passwd"))
        self.assertFalse(pd._safe_member("/etc/passwd"))
        self.assertFalse(pd._safe_member("foo/../../secret.txt"))
        self.assertFalse(pd._safe_member(".env"))
        self.assertFalse(pd._safe_member("css/.hidden.css"))
        self.assertTrue(pd._safe_member("index.html"))
        self.assertTrue(pd._safe_member("assets/app.js"))
        self.assertTrue(pd._safe_member(".well-known/acme-challenge/token.txt"))
        self.assertFalse(pd._safe_member("malware.exe"))

    def test_zip_skips_traversal_members(self):
        zpath = self.root / "evil.zip"
        with zipfile.ZipFile(zpath, "w") as zf:
            zf.writestr("index.html", "<html>ok</html>")
            zf.writestr("../escape.txt", "pwned")
            zf.writestr("nested/../../outside.js", "bad")
            zf.writestr(".env", "SECRET=1")
        dest = self.sites / "pabcdef12"
        dest.mkdir()
        stats = pd.extract_public_zip(zpath, dest)
        self.assertGreaterEqual(stats["files_skipped"], 2)
        self.assertTrue((dest / "index.html").is_file())
        self.assertFalse((self.sites / "escape.txt").exists())
        self.assertFalse((dest / ".env").exists())

    def test_verify_token_and_expiry(self):
        name = "pabcdef12"
        token = "super-secret-token"
        pd.save_meta(
            name,
            {
                "name": name,
                "token_hash": pd._hash_token(token),
                "expires_at": time.time() + 3600,
            },
        )
        self.assertTrue(pd.verify_token(name, token))
        self.assertFalse(pd.verify_token(name, "wrong"))
        self.assertFalse(pd.verify_token("ai", token))  # reserved / unsafe name
        self.assertFalse(pd.verify_token("not-a-public", token))

        pd.save_meta(
            name,
            {
                "name": name,
                "token_hash": pd._hash_token(token),
                "expires_at": time.time() - 10,
            },
        )
        self.assertFalse(pd.verify_token(name, token))

    def test_write_file_blocks_path_escape(self):
        name = "pabcdef12"
        token = "tok"
        site = self.sites / name
        site.mkdir()
        (site / "index.html").write_text("<html></html>", encoding="utf-8")
        pd.save_meta(
            name,
            {
                "name": name,
                "token_hash": pd._hash_token(token),
                "expires_at": time.time() + 3600,
            },
        )
        with self.assertRaises(ValueError):
            pd.write_file(name, token, "../escape.html", "<b>x</b>")
        with self.assertRaises(PermissionError):
            pd.write_file(name, "bad-token", "index.html", "<b>x</b>")
        out = pd.write_file(name, token, "page.html", "<p>hi</p>")
        self.assertTrue(out["ok"])
        self.assertEqual((site / "page.html").read_text(encoding="utf-8"), "<p>hi</p>")

    def test_read_file_blocks_path_escape(self):
        name = "pabcdef12"
        token = "tok"
        site = self.sites / name
        site.mkdir()
        (site / "index.html").write_text("<html>ok</html>", encoding="utf-8")
        pd.save_meta(
            name,
            {
                "name": name,
                "token_hash": pd._hash_token(token),
                "expires_at": time.time() + 3600,
            },
        )
        with self.assertRaises(ValueError):
            pd.read_file(name, token, "../meta.json")
        got = pd.read_file(name, token, "index.html")
        self.assertIn("ok", got["content"])

    def test_detect_format(self):
        html = self.root / "page.html"
        html.write_text("<!doctype html><html></html>", encoding="utf-8")
        self.assertEqual(pd.detect_format(html, "page.html"), "html")

        zpath = self.root / "a.zip"
        with zipfile.ZipFile(zpath, "w") as zf:
            zf.writestr("index.html", "<html></html>")
        self.assertEqual(pd.detect_format(zpath, "a.zip"), "zip")

    def test_rate_limit(self):
        with mock.patch.object(pd, "RATE_LIMIT", 2), mock.patch.object(pd, "RATE_WINDOW", 3600):
            self.assertTrue(pd.check_rate_limit("1.1.1.1")[0])
            self.assertTrue(pd.check_rate_limit("1.1.1.1")[0])
            ok, why = pd.check_rate_limit("1.1.1.1")
            self.assertFalse(ok)
            self.assertIn("Лимит", why)


if __name__ == "__main__":
    unittest.main()
