#!/usr/bin/env python3
"""Regression: site_health_check detection + safe auto_fix (charset/viewport/clearfix)."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import hosting_tools as ht  # noqa: E402


class SiteHealthCheckTests(unittest.TestCase):
    def test_not_a_directory(self):
        with tempfile.TemporaryDirectory() as tmp:
            missing = Path(tmp) / "nope"
            out = ht.site_health_check(str(missing))
            self.assertFalse(out["ok"])
            self.assertIn("Не директория", out.get("error", ""))

    def test_missing_index_is_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "readme.txt").write_text("x", encoding="utf-8")
            out = ht.site_health_check(str(root), auto_fix=False)
            self.assertFalse(out["ok"])
            kinds = [i["kind"] for i in out["issues"]]
            self.assertIn("structure", kinds)
            self.assertGreaterEqual(out["errors"], 1)

    def test_nested_public_html_detected_without_fix(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            nested = root / "public_html"
            nested.mkdir()
            (nested / "index.html").write_text(
                "<html><head></head><body>hi</body></html>", encoding="utf-8"
            )
            out = ht.site_health_check(str(root), auto_fix=False)
            layout = [i for i in out["issues"] if i["kind"] == "layout"]
            self.assertTrue(layout)
            self.assertTrue(any("public_html" in i["message"] for i in layout))
            self.assertTrue((root / "public_html" / "index.html").is_file())
            self.assertFalse(out.get("edited"))

    def test_auto_fix_flattens_nested_when_root_empty(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            nested = root / "www"
            nested.mkdir()
            (nested / "index.html").write_text(
                "<html><head></head><body>ok</body></html>", encoding="utf-8"
            )
            out = ht.site_health_check(str(root), auto_fix=True)
            self.assertTrue((root / "index.html").is_file())
            self.assertFalse((root / "www").exists())
            actions = [f.get("action") for f in out.get("fixes_applied") or []]
            self.assertIn("flatten_site_layout", actions)

    def test_auto_fix_adds_charset_and_viewport(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            html = root / "index.html"
            html.write_text(
                "<html><head><title>t</title></head><body><h1>Hi</h1></body></html>",
                encoding="utf-8",
            )
            out = ht.site_health_check(str(root), auto_fix=True)
            text = html.read_text(encoding="utf-8")
            self.assertIn('charset="utf-8"', text.lower().replace(" ", ""))
            self.assertIn('name="viewport"', text.lower())
            actions = {f.get("action") for f in out.get("fixes_applied") or []}
            self.assertIn("add_charset", actions)
            self.assertIn("add_viewport", actions)
            # Re-run should not keep re-adding forever
            out2 = ht.site_health_check(str(root), auto_fix=True)
            actions2 = {f.get("action") for f in out2.get("fixes_applied") or []}
            self.assertNotIn("add_charset", actions2)
            self.assertNotIn("add_viewport", actions2)

    def test_auto_fix_strips_h1_inline_shift(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            html = root / "index.html"
            html.write_text(
                '<html><head><meta charset="utf-8">'
                '<meta name="viewport" content="width=device-width, initial-scale=1">'
                "</head><body>"
                '<h1 style="margin-left: -240px">Title</h1>'
                "</body></html>",
                encoding="utf-8",
            )
            out = ht.site_health_check(str(root), auto_fix=True)
            text = html.read_text(encoding="utf-8")
            self.assertNotIn("margin-left", text)
            actions = [f.get("action") for f in out.get("fixes_applied") or []]
            self.assertIn("strip_h1_inline_shift", actions)

    def test_auto_fix_css_clearfix_for_floated_nav(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "index.html").write_text(
                '<html><head><meta charset="utf-8">'
                '<meta name="viewport" content="width=device-width, initial-scale=1">'
                '<link rel="stylesheet" href="style.css">'
                "</head><body><header><nav></nav></header></body></html>",
                encoding="utf-8",
            )
            css = root / "style.css"
            css.write_text(
                ".logo { float: left; }\n.menu { float: left; }\n",
                encoding="utf-8",
            )
            out = ht.site_health_check(str(root), auto_fix=True)
            css_text = css.read_text(encoding="utf-8")
            self.assertIn("AI Helper auto-fix", css_text)
            self.assertIn("clear: both", css_text)
            actions = [f.get("action") for f in out.get("fixes_applied") or []]
            self.assertIn("css_clearfix_header", actions)
            # Idempotent: second pass must not duplicate patch
            before = css_text
            ht.site_health_check(str(root), auto_fix=True)
            self.assertEqual(css.read_text(encoding="utf-8"), before)


if __name__ == "__main__":
    unittest.main()
