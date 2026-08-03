#!/usr/bin/env python3
"""Unit tests for agent tool-call parsing and routing heuristics."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import agent  # noqa: E402
import hosting_tools as ht  # noqa: E402


class ParseArgsTests(unittest.TestCase):
    def test_dict_passthrough(self):
        self.assertEqual(agent._parse_args({"path": "a.py"}), {"path": "a.py"})

    def test_json_string(self):
        self.assertEqual(agent._parse_args('{"path": "a.py", "n": 1}'), {"path": "a.py", "n": 1})

    def test_invalid_or_non_object_returns_empty(self):
        self.assertEqual(agent._parse_args("{not-json"), {})
        self.assertEqual(agent._parse_args('["x"]'), {})
        self.assertEqual(agent._parse_args(None), {})
        self.assertEqual(agent._parse_args(12), {})


class ExtractToolCallsTests(unittest.TestCase):
    def test_extracts_named_calls_and_skips_blank(self):
        raw = {
            "tool_calls": [
                {
                    "id": "call_1",
                    "function": {"name": "read_file", "arguments": '{"path":"x.py"}'},
                },
                {"function": {"name": "  ", "arguments": "{}"}},
                {
                    "function": {
                        "name": "list_dir",
                        "arguments": {"path": "."},
                    },
                },
            ]
        }
        calls = agent._extract_tool_calls(raw)
        self.assertEqual(len(calls), 2)
        self.assertEqual(calls[0]["id"], "call_1")
        self.assertEqual(calls[0]["name"], "read_file")
        self.assertEqual(calls[0]["arguments"], {"path": "x.py"})
        self.assertEqual(calls[1]["name"], "list_dir")
        self.assertTrue(calls[1]["id"].startswith("call_"))

    def test_missing_tool_calls_is_empty(self):
        self.assertEqual(agent._extract_tool_calls({}), [])
        self.assertEqual(agent._extract_tool_calls({"tool_calls": None}), [])


class RoutingHeuristicTests(unittest.TestCase):
    def test_needs_tools_detects_file_and_site_actions(self):
        self.assertTrue(agent._needs_tools("открой файл config.py"))
        self.assertTrue(agent._needs_tools("Покажи список файлов в проекте"))
        self.assertFalse(agent._needs_tools("привет, как дела?"))

    def test_site_review_short_vague_ask(self):
        self.assertTrue(agent._is_site_review("ну как сайт?"))
        self.assertTrue(agent._is_site_review("оцени лендинг"))
        self.assertFalse(agent._is_site_review("расскажи анекдот про пиратов пожалуйста"))

    def test_access_refusal_markers(self):
        self.assertTrue(agent._looks_like_access_refusal("Я не могу получить доступ к файлам"))
        self.assertTrue(agent._looks_like_access_refusal("I don't have access to the workspace"))
        self.assertFalse(agent._looks_like_access_refusal("Вот содержимое index.html"))


class HostingPureHelpersTests(unittest.TestCase):
    def test_find_main_html_prefers_root_then_nested(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.assertIsNone(ht._find_main_html(root))
            nested = root / "public_html"
            nested.mkdir()
            (nested / "index.html").write_text("<h1>n</h1>", encoding="utf-8")
            self.assertEqual(ht._find_main_html(root), nested / "index.html")
            (root / "index.html").write_text("<h1>r</h1>", encoding="utf-8")
            self.assertEqual(ht._find_main_html(root), root / "index.html")

    def test_build_site_card_includes_domain_and_wp_hint(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp) / "mysite"
            root.mkdir()
            (root / ".ai-helper-domain").write_text("example.test\n", encoding="utf-8")
            (root / "wp-config.php").write_text("<?php", encoding="utf-8")
            (root / "index.php").write_text("<?php", encoding="utf-8")
            card = ht.build_site_card(root)
            self.assertIn("example.test", card)
            self.assertIn("mysite", card)
            self.assertIn("WordPress", card)
            self.assertEqual(ht.build_site_card(None), "")


if __name__ == "__main__":
    unittest.main()
