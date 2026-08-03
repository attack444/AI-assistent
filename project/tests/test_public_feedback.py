#!/usr/bin/env python3
"""Unit tests for public feedback validation and persistence."""
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import public_feedback as pf  # noqa: E402


class PublicFeedbackTests(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        data = Path(self._tmp.name)
        self._patchers = [
            mock.patch.object(pf, "DATA_DIR", data),
            mock.patch.object(pf, "FEEDBACK_FILE", data / "public_feedback.jsonl"),
        ]
        for p in self._patchers:
            p.start()

    def tearDown(self):
        for p in self._patchers:
            p.stop()
        self._tmp.cleanup()

    def test_save_rejects_too_short_and_too_long(self):
        with self.assertRaises(ValueError):
            pf.save_feedback(kind="idea", message="short")
        with self.assertRaises(ValueError):
            pf.save_feedback(kind="bug", message="x" * (pf.MAX_LEN + 1))

    def test_unknown_kind_falls_back_to_other(self):
        result = pf.save_feedback(kind="not-a-type", message="Достаточно длинное сообщение")
        self.assertTrue(result["ok"])
        items = pf.list_feedback()
        self.assertEqual(len(items), 1)
        self.assertEqual(items[0]["type"], "other")
        self.assertEqual(items[0]["type_label"], pf.TYPES["other"])

    def test_truncates_email_page_ip_and_lists_newest_first(self):
        pf.save_feedback(
            kind="idea",
            message="Первое сообщение для теста",
            email="a" * 300,
            page="p" * 600,
            ip="1" * 100,
        )
        pf.save_feedback(kind="bug", message="Второе сообщение для теста")
        items = pf.list_feedback(limit=10)
        self.assertEqual(len(items), 2)
        self.assertEqual(items[0]["type"], "bug")
        self.assertEqual(len(items[1]["email"]), 200)
        self.assertEqual(len(items[1]["page"]), 500)
        self.assertEqual(len(items[1]["ip"]), 64)

    def test_list_skips_corrupt_lines_and_clamps_limit(self):
        pf.FEEDBACK_FILE.write_text(
            "\n".join(
                [
                    json.dumps({"message": "ok1", "type": "idea"}),
                    "{bad",
                    json.dumps({"message": "ok2", "type": "bug"}),
                ]
            )
            + "\n",
            encoding="utf-8",
        )
        newest = pf.list_feedback(limit=1)
        self.assertEqual(len(newest), 1)
        self.assertEqual(newest[0]["message"], "ok2")
        # limit<=0 falsy → default 100; upper clamp at 500
        self.assertEqual(len(pf.list_feedback(limit=9999)), 2)
        self.assertEqual(len(pf.list_feedback(limit=None)), 2)


if __name__ == "__main__":
    unittest.main()
