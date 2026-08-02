#!/usr/bin/env python3
"""Unit tests for free Ollama model helpers (tools gating / selection)."""
from __future__ import annotations

import io
import sys
import unittest
import urllib.error
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import free_llm as fl  # noqa: E402


class FreeLlmTests(unittest.TestCase):
    def test_tiny_models_do_not_support_tools(self):
        with mock.patch.dict("os.environ", {"FREE_LLM_TOOLS": ""}, clear=False):
            # clear override if present
            pass
        with mock.patch.dict("os.environ", {}, clear=False):
            env = dict(**{k: v for k, v in __import__("os").environ.items() if k != "FREE_LLM_TOOLS"})
            with mock.patch.dict("os.environ", env, clear=True):
                self.assertFalse(fl.model_supports_tools("qwen2.5:1.5b"))
                self.assertFalse(fl.model_supports_tools("llama3.2:1b"))
                self.assertTrue(fl.model_supports_tools("qwen2.5:7b"))
                self.assertTrue(fl.model_supports_tools("qwen2.5-coder:14b"))
                self.assertTrue(fl.model_supports_tools("mistral:latest"))

    def test_tools_env_override(self):
        with mock.patch.dict("os.environ", {"FREE_LLM_TOOLS": "1"}):
            self.assertTrue(fl.model_supports_tools("qwen2.5:1.5b"))
        with mock.patch.dict("os.environ", {"FREE_LLM_TOOLS": "0"}):
            self.assertFalse(fl.model_supports_tools("qwen2.5:7b"))

    def test_prefer_free_flag(self):
        with mock.patch.dict("os.environ", {"LLM_PREFER_FREE": "0"}):
            self.assertFalse(fl.prefer_free())
        with mock.patch.dict("os.environ", {"LLM_PREFER_FREE": "1"}):
            self.assertTrue(fl.prefer_free())

    def test_free_model_prefers_env_then_non_deepseek(self):
        with mock.patch.dict("os.environ", {"FREE_LLM_MODEL": "custom:3b"}):
            self.assertEqual(fl.free_model("deepseek-chat", "x"), "custom:3b")
        with mock.patch.dict("os.environ", {"FREE_LLM_MODEL": ""}):
            self.assertEqual(fl.free_model("qwen2.5:1.5b", "deepseek-chat"), "qwen2.5:1.5b")
            self.assertEqual(fl.free_model("deepseek-chat", "deepseek-coder"), fl.DEFAULT_FREE_MODEL)

    def test_http_error_detail_includes_body(self):
        fp = io.BytesIO(b'{"error":"tools not supported"}')
        exc = urllib.error.HTTPError(
            url="http://x", code=400, msg="Bad Request", hdrs=None, fp=fp
        )
        detail = fl.http_error_detail(exc)
        self.assertIn("400", detail)
        self.assertIn("tools not supported", detail)


if __name__ == "__main__":
    unittest.main()
