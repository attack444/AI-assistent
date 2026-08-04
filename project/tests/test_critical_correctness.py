#!/usr/bin/env python3
"""Regression tests for high-severity correctness bugs (data loss / crash / races)."""
from __future__ import annotations

import importlib
import io
import os
import sys
import tempfile
import threading
import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))


def _import_api():
    """Import api.py with heavy optional deps stubbed (llama_index, etc.)."""
    import types
    from unittest import mock

    if "api" in sys.modules:
        return sys.modules["api"]

    def _ensure(name: str, mod=None):
        if name not in sys.modules:
            sys.modules[name] = mod if mod is not None else types.ModuleType(name)
        return sys.modules[name]

    li = _ensure("llama_index")
    core = _ensure("llama_index.core", mock.MagicMock())
    li.core = core
    _ensure("llama_index.core.node_parser", mock.MagicMock())
    emb = _ensure("llama_index.embeddings")
    emb_o = _ensure("llama_index.embeddings.ollama", mock.MagicMock())
    emb.ollama = emb_o
    llms = _ensure("llama_index.llms")
    llms_o = _ensure("llama_index.llms.ollama", mock.MagicMock())
    llms.ollama = llms_o
    _ensure("ddgs", mock.MagicMock())
    try:
        import pydantic  # noqa: F401
    except ImportError:
        _ensure("pydantic", mock.MagicMock())

    import api as api_mod

    return api_mod


class UsersJsonCorruptWipeTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory()
        os.environ["PUBLIC_USERS_DIR"] = self.tmp.name
        import public_users as pu

        importlib.reload(pu)
        self.pu = pu

    def tearDown(self) -> None:
        self.tmp.cleanup()

    def test_corrupt_users_json_does_not_wipe_on_register(self) -> None:
        self.pu.register("a@example.com", "password123", ip="1.1.1.1")
        self.pu.register("b@example.com", "password123", ip="1.1.1.2")
        self.assertEqual(len(self.pu._users()), 2)

        self.pu.USERS_FILE.write_text("{not-json", encoding="utf-8")
        with self.assertRaises(RuntimeError):
            self.pu.register("c@example.com", "password123", ip="1.1.1.3")

        # File must remain the corrupt payload — not rewritten to a single new user.
        raw = self.pu.USERS_FILE.read_text(encoding="utf-8")
        self.assertIn("{not-json", raw)
        self.assertNotIn("c@example.com", raw)

    def test_empty_users_json_refuses_rewrite(self) -> None:
        self.pu.USERS_FILE.write_text("", encoding="utf-8")
        with self.assertRaises(RuntimeError):
            self.pu.register("c@example.com", "password123", ip="1.1.1.3")


class SiteSlotReservationTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory()
        os.environ["PUBLIC_USERS_DIR"] = self.tmp.name
        os.environ["PUBLIC_DEFAULT_PLAN"] = "free"
        import public_users as pu
        import public_plans as pp

        importlib.reload(pp)
        importlib.reload(pu)
        self.pu = pu
        self.email = "slot@example.com"
        self.pu.register(self.email, "password123", ip="9.9.9.9")

    def tearDown(self) -> None:
        self.tmp.cleanup()

    def test_parallel_reserve_respects_max_sites(self) -> None:
        results: list[tuple[bool, str, str]] = []
        barrier = threading.Barrier(2)

        def worker() -> None:
            barrier.wait()
            results.append(self.pu.reserve_site_slot(self.email))

        threads = [threading.Thread(target=worker) for _ in range(2)]
        for t in threads:
            t.start()
        for t in threads:
            t.join()

        oks = [r for r in results if r[0]]
        fails = [r for r in results if not r[0]]
        self.assertEqual(len(oks), 1, results)
        self.assertEqual(len(fails), 1, results)

        # pending reservation counts toward cap
        ok2, _, _ = self.pu.reserve_site_slot(self.email)
        self.assertFalse(ok2)


class TempFileFdLeakTests(unittest.TestCase):
    def test_temp_file_closes_descriptor(self) -> None:
        import resource

        api_mod = _import_api()

        soft, hard = resource.getrlimit(resource.RLIMIT_NOFILE)
        paths: list[Path] = []
        try:
            resource.setrlimit(resource.RLIMIT_NOFILE, (64, hard))
            for _ in range(80):
                p = api_mod._temp_file(".bin")
                paths.append(p)
                p.write_bytes(b"x")
            # If FDs leaked, the loop would have raised OSError before 80.
            self.assertEqual(len(paths), 80)
        finally:
            resource.setrlimit(resource.RLIMIT_NOFILE, (soft, hard))
            for p in paths:
                try:
                    p.unlink()
                except OSError:
                    pass


class StreamBodyCompleteTests(unittest.TestCase):
    def test_truncated_body_raises(self) -> None:
        api_mod = _import_api()

        handler = api_mod.APIHandler.__new__(api_mod.APIHandler)
        handler.headers = {"Content-Length": "100"}
        handler.rfile = io.BytesIO(b"only-20-bytes-here!!!!")
        fd, name = tempfile.mkstemp(suffix=".bin")
        os.close(fd)
        dest = Path(name)
        try:
            with self.assertRaises(ValueError) as ctx:
                handler._stream_body_to_file(dest, max_bytes=10_000_000)
            self.assertIn("не полностью", str(ctx.exception))
        finally:
            dest.unlink(missing_ok=True)


class PanelZipStagingTests(unittest.TestCase):
    def test_failed_extract_keeps_live_site(self) -> None:
        api_mod = _import_api()

        site = Path(tempfile.mkdtemp(prefix="live-site-"))
        (site / "index.html").write_text("LIVE", encoding="utf-8")
        (site / "keep.txt").write_text("important", encoding="utf-8")

        # Corrupt zip bytes
        fd, name = tempfile.mkstemp(suffix=".zip")
        os.close(fd)
        bad = Path(name)
        bad.write_bytes(b"PK\x03\x04not-a-real-zip")

        handler = api_mod.APIHandler.__new__(api_mod.APIHandler)
        with self.assertRaises(ValueError):
            handler._extract_zip_file(bad, site)

        self.assertEqual((site / "index.html").read_text(encoding="utf-8"), "LIVE")
        self.assertEqual((site / "keep.txt").read_text(encoding="utf-8"), "important")
        bad.unlink(missing_ok=True)

    def test_successful_extract_replaces_site(self) -> None:
        api_mod = _import_api()

        site = Path(tempfile.mkdtemp(prefix="live-site-"))
        (site / "old.html").write_text("OLD", encoding="utf-8")

        fd, name = tempfile.mkstemp(suffix=".zip")
        os.close(fd)
        zpath = Path(name)
        with zipfile.ZipFile(zpath, "w") as zf:
            zf.writestr("index.html", "<html>NEW</html>")

        handler = api_mod.APIHandler.__new__(api_mod.APIHandler)
        handler._extract_zip_file(zpath, site)

        self.assertTrue((site / "index.html").is_file())
        self.assertIn("NEW", (site / "index.html").read_text(encoding="utf-8"))
        self.assertFalse((site / "old.html").exists())
        zpath.unlink(missing_ok=True)


class ChunkMetaRaceTests(unittest.TestCase):
    def test_parallel_chunks_keep_all_indices(self) -> None:
        import panel_uploads as pup

        root = Path(tempfile.mkdtemp(prefix="uploads-root-"))
        init = pup.init_upload(root, filename="x.bin", size=12, total_chunks=3, chunk_size=4)
        uid = init["upload_id"]
        barrier = threading.Barrier(3)
        errors: list[BaseException] = []

        def send(i: int) -> None:
            try:
                barrier.wait()
                pup.save_chunk(root, uid, i, b"xxxx")
            except BaseException as exc:  # noqa: BLE001
                errors.append(exc)

        threads = [threading.Thread(target=send, args=(i,)) for i in range(3)]
        for t in threads:
            t.start()
        for t in threads:
            t.join()
        self.assertEqual(errors, [])
        st = pup.status(root, uid)
        self.assertTrue(st["complete"])
        self.assertEqual(st["received"], 3)


class PathPrefixEscapeTests(unittest.TestCase):
    def test_startswith_sibling_rejected(self) -> None:
        import public_deploy as pd

        root = Path(tempfile.mkdtemp(prefix="pabcdef0"))
        sibling = Path(str(root) + "evil")
        sibling.mkdir()
        # Classic startswith false positive: str(sibling).startswith(str(root)) is True
        self.assertTrue(str(sibling).startswith(str(root)))
        self.assertFalse(pd._is_under_root(sibling / "x.html", root))


class WidgetGuestOriginTests(unittest.TestCase):
    def test_source_widget_alone_denied(self) -> None:
        import public_users as pu

        importlib.reload(pu)

        class H:
            headers = {"Origin": "https://evil.example", "Referer": ""}

        self.assertFalse(pu.widget_guest_allowed(H()))

    def test_allowlisted_origin_ok(self) -> None:
        os.environ["PUBLIC_WIDGET_ORIGINS"] = "https://5mb2.ru"
        import public_users as pu

        importlib.reload(pu)

        class H:
            headers = {"Origin": "https://5mb2.ru", "Referer": ""}

        self.assertTrue(pu.widget_guest_allowed(H()))


if __name__ == "__main__":
    unittest.main()
