#!/usr/bin/env python3
"""Unit tests for panel chat SQLite store (no live VPS)."""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import chat_store as cs  # noqa: E402


class ChatStoreTests(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        data = Path(self._tmp.name)
        self._db = data / "chats.db"
        self._patcher = mock.patch.object(cs, "DB_PATH", self._db)
        self._patcher.start()

    def tearDown(self):
        self._patcher.stop()
        self._tmp.cleanup()

    def test_create_list_get_and_site_filter(self):
        a = cs.create_chat(site_id="5mb2", title="Сайт")
        b = cs.create_chat(site_id="other", title="Другой")
        self.assertEqual(len(a["id"]), 16)
        self.assertEqual(a["messages"], [])

        all_chats = cs.list_chats()
        self.assertGreaterEqual(len(all_chats), 2)
        only_site = cs.list_chats(site_id="5mb2")
        self.assertEqual([c["id"] for c in only_site], [a["id"]])
        self.assertNotIn(b["id"], [c["id"] for c in only_site])

        got = cs.get_chat(a["id"])
        self.assertIsNotNone(got)
        self.assertEqual(got["title"], "Сайт")
        self.assertEqual(got["site_id"], "5mb2")
        self.assertEqual(got["messages"], [])

    def test_title_truncation_and_empty_fallback(self):
        long_title = "x" * 200
        chat = cs.create_chat(title=long_title)
        self.assertEqual(len(chat["title"]), 120)

        empty = cs.create_chat(title="   ")
        self.assertEqual(empty["title"], "Новый чат")

        renamed = cs.rename_chat(chat["id"], "  новый заголовок  ")
        self.assertEqual(renamed["title"], "новый заголовок")
        unchanged = cs.rename_chat(chat["id"], "   ")
        self.assertEqual(unchanged["title"], "новый заголовок")

    def test_add_message_and_history_filters_tool_roles(self):
        chat = cs.create_chat(site_id="panel")
        cs.add_message(chat["id"], "user", "Привет")
        cs.add_message(chat["id"], "assistant", "Ответ")
        cs.add_message(chat["id"], "tool", '{"ok": true}', meta={"name": "read_file"})
        cs.add_message(chat["id"], "system", "ignore me")
        cs.add_message(chat["id"], "assistant", "   ")

        full = cs.get_chat(chat["id"])
        self.assertEqual(len(full["messages"]), 5)
        self.assertEqual(full["messages"][2]["meta"]["name"], "read_file")

        history = cs.history_for_agent(chat["id"])
        self.assertEqual(
            history,
            [
                {"role": "user", "content": "Привет"},
                {"role": "assistant", "content": "Ответ"},
            ],
        )
        self.assertEqual(cs.history_for_agent("missing-id"), [])

    def test_history_respects_limit(self):
        chat = cs.create_chat()
        for i in range(10):
            cs.add_message(chat["id"], "user", f"u{i}")
            cs.add_message(chat["id"], "assistant", f"a{i}")
        history = cs.history_for_agent(chat["id"], limit=4)
        self.assertEqual(len(history), 4)
        self.assertEqual(history[0]["content"], "u8")
        self.assertEqual(history[-1]["content"], "a9")

    def test_delete_chat_removes_messages(self):
        chat = cs.create_chat()
        cs.add_message(chat["id"], "user", "bye")
        self.assertTrue(cs.delete_chat(chat["id"]))
        self.assertIsNone(cs.get_chat(chat["id"]))
        self.assertFalse(cs.delete_chat(chat["id"]))

    def test_auto_title_from_message(self):
        self.assertEqual(cs.auto_title_from_message(""), "Новый чат")
        self.assertEqual(cs.auto_title_from_message("  short  "), "short")
        long = "слово " * 20
        titled = cs.auto_title_from_message(long)
        self.assertTrue(titled.endswith("…"))
        self.assertLessEqual(len(titled), 48)

    def test_corrupt_meta_becomes_empty_dict(self):
        chat = cs.create_chat()
        with cs._conn() as con:
            con.execute(
                "INSERT INTO messages (chat_id, role, content, meta, created_at) VALUES (?,?,?,?,?)",
                (chat["id"], "assistant", "x", "{not-json", 1.0),
            )
        got = cs.get_chat(chat["id"])
        self.assertEqual(got["messages"][0]["meta"], {})


if __name__ == "__main__":
    unittest.main()
