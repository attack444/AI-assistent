"""Regression: failed redeploy must not wipe an existing site."""
from __future__ import annotations

import tempfile
import time
import unittest
import zipfile
from pathlib import Path

import public_deploy as pd


def _write_zip(path: Path, files: dict[str, str]) -> None:
    with zipfile.ZipFile(path, "w") as zf:
        for name, content in files.items():
            zf.writestr(name, content)


class PublicDeployExtractTests(unittest.TestCase):
    def test_failed_extract_preserves_existing_site(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            tmp_path = Path(tmp)
            old_max = pd.MAX_FILES
            pd.MAX_FILES = 2
            try:
                root = tmp_path / "psite123"
                root.mkdir()
                (root / "index.html").write_text("<h1>live</h1>", encoding="utf-8")
                (root / "app.js").write_text("console.log(1)", encoding="utf-8")

                bad_zip = tmp_path / "too-many.zip"
                _write_zip(
                    bad_zip,
                    {
                        "index.html": "<h1>new</h1>",
                        "a.js": "1",
                        "b.js": "2",
                        "c.js": "3",
                    },
                )

                with self.assertRaisesRegex(ValueError, "Лимит"):
                    pd.extract_public_zip(bad_zip, root)

                self.assertEqual(
                    (root / "index.html").read_text(encoding="utf-8"), "<h1>live</h1>"
                )
                self.assertEqual(
                    (root / "app.js").read_text(encoding="utf-8"), "console.log(1)"
                )
                self.assertFalse(list(tmp_path.glob(".staging-*")))
                self.assertFalse(list(tmp_path.glob(".backup-*")))
            finally:
                pd.MAX_FILES = old_max

    def test_non_zip_redeploy_preserves_site(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            tmp_path = Path(tmp)
            old_sites, old_meta = pd.SITES_ROOT, pd.META_DIR
            pd.SITES_ROOT = tmp_path
            pd.META_DIR = tmp_path / "meta"
            try:
                name = "pabcdef12"
                root = tmp_path / name
                root.mkdir()
                (root / "index.html").write_text("<h1>live</h1>", encoding="utf-8")

                token = "secret-token-value"
                now = time.time()
                pd.save_meta(
                    name,
                    {
                        "name": name,
                        "token_hash": pd._hash_token(token),
                        "created_at": now,
                        "expires_at": now + 86400 * 30,
                    },
                )

                junk = tmp_path / "not-a-zip.bin"
                junk.write_bytes(b"definitely-not-a-zip")

                with self.assertRaisesRegex(ValueError, r"ZIP|Поддерживаются"):
                    pd.redeploy(name, token, junk)

                self.assertEqual(
                    (root / "index.html").read_text(encoding="utf-8"), "<h1>live</h1>"
                )
            finally:
                pd.SITES_ROOT = old_sites
                pd.META_DIR = old_meta

    def test_successful_extract_replaces_site(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            tmp_path = Path(tmp)
            root = tmp_path / "psite456"
            root.mkdir()
            (root / "old.html").write_text("old", encoding="utf-8")

            good_zip = tmp_path / "good.zip"
            _write_zip(good_zip, {"index.html": "<h1>fresh</h1>", "style.css": "body{}"})

            stats = pd.extract_public_zip(good_zip, root)

            self.assertEqual(stats["files_kept"], 2)
            self.assertEqual(
                (root / "index.html").read_text(encoding="utf-8"), "<h1>fresh</h1>"
            )
            self.assertEqual(
                (root / "style.css").read_text(encoding="utf-8"), "body{}"
            )
            self.assertFalse((root / "old.html").exists())


if __name__ == "__main__":
    unittest.main()
