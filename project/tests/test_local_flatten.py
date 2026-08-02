#!/usr/bin/env python3
"""Regression: flatten must not wipe live site content on name collision."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import hosting_tools as ht  # noqa: E402


class LocalFlattenTests(unittest.TestCase):
    def test_preserves_live_wp_content_when_nested_public_html_collides(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "wp-content").mkdir()
            (root / "wp-content" / "important.php").write_text("LIVE_IMPORTANT", encoding="utf-8")
            (root / "index.php").write_text("<?php // live root\n", encoding="utf-8")

            nested = root / "public_html"
            (nested / "wp-content").mkdir(parents=True)
            (nested / "index.php").write_text("<?php // nested\n", encoding="utf-8")
            (nested / "wp-content" / "nested.php").write_text("NESTED", encoding="utf-8")

            result = ht.site_health_check(str(root), auto_fix=True)
            self.assertTrue(result.get("ok") is False or result.get("fixes_applied"))

            important = root / "wp-content" / "important.php"
            self.assertTrue(important.is_file(), "live wp-content file was destroyed")
            self.assertEqual(important.read_text(encoding="utf-8"), "LIVE_IMPORTANT")
            self.assertEqual(
                (root / "index.php").read_text(encoding="utf-8"),
                "<?php // live root\n",
            )
            # Unique nested files should still be promoted.
            self.assertTrue((root / "wp-content" / "nested.php").is_file())
            self.assertFalse((root / "public_html").exists())

    def test_flatten_empty_root_still_unwraps_nested(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            nested = root / "public_html"
            nested.mkdir()
            (nested / "index.html").write_text("<html>ok</html>", encoding="utf-8")
            (nested / "assets").mkdir()
            (nested / "assets" / "app.css").write_text("body{}", encoding="utf-8")

            edited = ht._local_flatten(root)
            self.assertTrue(edited)
            self.assertTrue((root / "index.html").is_file())
            self.assertTrue((root / "assets" / "app.css").is_file())
            self.assertFalse(nested.exists())

    def test_flatten_site_layout_reports_ok(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            nested = root / "www"
            nested.mkdir()
            (nested / "index.php").write_text("<?php\n", encoding="utf-8")
            out = ht.flatten_site_layout(str(root))
            self.assertTrue(out["ok"])
            self.assertTrue(out["edited"])
            self.assertTrue((root / "index.php").is_file())


if __name__ == "__main__":
    unittest.main()
