#!/usr/bin/env python3
"""Unit tests for public chat sanitization and rate limits (no LLM network)."""
from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

# public_chat imports agent/core at module load; stub heavy deps.
_agent = mock.MagicMock()
_agent.DEEPSEEK_API_URL = "https://example.test/v1"
_agent.DEEPSEEK_DEFAULT_MODEL = "deepseek-chat"
_agent._groq_stream = mock.MagicMock()
sys.modules.setdefault("agent", _agent)
_core = mock.MagicMock()
_core.load_settings = mock.MagicMock()
sys.modules.setdefault("core", _core)

import public_chat as pc  # noqa: E402


class PublicChatTests(unittest.TestCase):
    def setUp(self):
        pc._hits.clear()

    def tearDown(self):
        pc._hits.clear()

    def test_sanitize_history_drops_system_and_truncates(self):
        with mock.patch.object(pc, "_MAX_HISTORY", 4), mock.patch.object(pc, "_MAX_MSG", 10):
            out = pc._sanitize_history(
                [
                    {"role": "system", "content": "ignore me"},
                    {"role": "user", "content": "one"},
                    {"role": "assistant", "content": "two"},
                    {"role": "tool", "content": "nope"},
                    {"role": "user", "content": "three-extra-long"},
                    {"role": "user", "content": "four"},
                ]
            )
        # window is applied before filtering; system/tool roles are dropped
        self.assertEqual([m["role"] for m in out], ["assistant", "user", "user"])
        self.assertEqual(out[-1]["content"], "four")
        self.assertEqual(out[1]["content"], "three-extr")  # truncated to _MAX_MSG

    def test_sanitize_history_non_list(self):
        self.assertEqual(pc._sanitize_history(None), [])
        self.assertEqual(pc._sanitize_history({"role": "user"}), [])

    def test_build_messages_widget_5mb2_vs_default(self):
        msgs = pc.build_messages("привет", [], widget=True, site_hint="https://5mb2.ru")
        self.assertEqual(msgs[0]["role"], "system")
        self.assertIn("5MB2", msgs[0]["content"])
        self.assertEqual(msgs[-1]["content"], "привет")

        msgs2 = pc.build_messages("hi", [{"role": "user", "content": "prev"}], widget=False)
        self.assertIn("AI Helper", msgs2[0]["content"])
        self.assertEqual(msgs2[1]["role"], "user")
        self.assertEqual(msgs2[1]["content"], "prev")

    def test_rate_limit_separates_guest_and_user(self):
        with mock.patch.object(pc, "_RATE_LIMIT", 2), mock.patch.object(
            pc, "_GUEST_RATE_LIMIT", 1
        ), mock.patch.object(pc, "_RATE_WINDOW", 3600):
            self.assertTrue(pc.check_rate_limit("2.2.2.2", guest=True)[0])
            ok, _ = pc.check_rate_limit("2.2.2.2", guest=True)
            self.assertFalse(ok)
            # same IP as authenticated user still has its own bucket
            self.assertTrue(pc.check_rate_limit("2.2.2.2", guest=False)[0])
            self.assertTrue(pc.check_rate_limit("2.2.2.2", guest=False)[0])
            ok2, _ = pc.check_rate_limit("2.2.2.2", guest=False)
            self.assertFalse(ok2)

    def test_client_ip_prefers_x_real_ip(self):
        class H:
            headers = {"X-Real-IP": "10.1.2.3", "X-Forwarded-For": "9.9.9.9, 8.8.8.8"}
            client_address = ("1.1.1.1", 1234)

        self.assertEqual(pc.client_ip(H()), "10.1.2.3")

        class H2:
            headers = {"X-Forwarded-For": "9.9.9.9, 8.8.8.8"}
            client_address = ("1.1.1.1", 1234)

        self.assertEqual(pc.client_ip(H2()), "9.9.9.9")


if __name__ == "__main__":
    unittest.main()
