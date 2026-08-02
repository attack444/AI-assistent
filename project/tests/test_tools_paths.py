#!/usr/bin/env python3
"""Unit tests for agent workspace path sandbox."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import tools  # noqa: E402


class ToolsPathTests(unittest.TestCase):
    def test_relative_path_stays_in_root(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve()
            (root / "src").mkdir()
            (root / "src" / "a.py").write_text("x", encoding="utf-8")
            got = tools.resolve_workspace_path("src/a.py", root)
            self.assertEqual(got, (root / "src" / "a.py").resolve())

    def test_absolute_inside_root_ok(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve()
            target = root / "ok.txt"
            target.write_text("1", encoding="utf-8")
            got = tools.resolve_workspace_path(str(target), root)
            self.assertEqual(got, target.resolve())

    def test_escape_outside_root_denied(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve() / "site"
            root.mkdir()
            with self.assertRaises(PermissionError):
                tools.resolve_workspace_path("../secret.txt", root)
            outside = Path(tmp).resolve() / "outside.txt"
            outside.write_text("no", encoding="utf-8")
            with self.assertRaises(PermissionError):
                tools.resolve_workspace_path(str(outside), root)

    def test_empty_path_rules(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve()
            with self.assertRaises(ValueError):
                tools.resolve_workspace_path("", root)
            got = tools.resolve_workspace_path("", root, default_to_root=True)
            self.assertEqual(got, root)


if __name__ == "__main__":
    unittest.main()
