"""Regression: failed redeploy must not wipe an existing site."""
from __future__ import annotations

import time
import zipfile
from pathlib import Path

import pytest

import public_deploy as pd


def _write_zip(path: Path, files: dict[str, str]) -> None:
    with zipfile.ZipFile(path, "w") as zf:
        for name, content in files.items():
            zf.writestr(name, content)


def test_failed_extract_preserves_existing_site(tmp_path: Path, monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setattr(pd, "MAX_FILES", 2)
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

    with pytest.raises(ValueError, match="Лимит"):
        pd.extract_public_zip(bad_zip, root)

    assert (root / "index.html").read_text(encoding="utf-8") == "<h1>live</h1>"
    assert (root / "app.js").read_text(encoding="utf-8") == "console.log(1)"
    assert not list(tmp_path.glob(".staging-*"))
    assert not list(tmp_path.glob(".backup-*"))


def test_non_zip_redeploy_preserves_site(tmp_path: Path, monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setattr(pd, "SITES_ROOT", tmp_path)
    monkeypatch.setattr(pd, "META_DIR", tmp_path / "meta")
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

    with pytest.raises(ValueError, match="ZIP"):
        pd.redeploy(name, token, junk)

    assert (root / "index.html").read_text(encoding="utf-8") == "<h1>live</h1>"


def test_successful_extract_replaces_site(tmp_path: Path):
    root = tmp_path / "psite456"
    root.mkdir()
    (root / "old.html").write_text("old", encoding="utf-8")

    good_zip = tmp_path / "good.zip"
    _write_zip(good_zip, {"index.html": "<h1>fresh</h1>", "style.css": "body{}"})

    stats = pd.extract_public_zip(good_zip, root)

    assert stats["files_kept"] == 2
    assert (root / "index.html").read_text(encoding="utf-8") == "<h1>fresh</h1>"
    assert (root / "style.css").read_text(encoding="utf-8") == "body{}"
    assert not (root / "old.html").exists()
