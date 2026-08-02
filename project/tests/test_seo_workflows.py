#!/usr/bin/env python3
from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest import mock

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

import seo_workflows as sw  # noqa: E402


class SeoWorkflowTests(unittest.TestCase):
    def test_title_of(self):
        self.assertIn("NeoBrain", sw._title_of("<html><title> NeoBrain Platform </title></html>"))

    def test_probe_site_ok(self):
        html = (
            "<html><head><title>NeoBrain — AI</title>"
            '<meta name="description" content="x">'
            "</head><body>ok</body></html>"
        )
        robots = "User-agent: *\nAllow: /\nSitemap: https://neobrain.site/sitemap.xml\n"
        sm = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'

        def fake_get(url, timeout=12.0):
            if url.endswith("robots.txt"):
                return 200, robots, {}
            if "sitemap" in url:
                return 200, sm, {}
            return 200, html, {}

        site = dict(sw.DEFAULT_SITES[1])
        with mock.patch.object(sw, "_http_get", side_effect=fake_get):
            report = sw.probe_site(site)
        self.assertTrue(report["ok"])
        self.assertEqual(report["id"], "neobrain")

    def test_probe_rejects_5mb2_on_neobrain(self):
        html = "<html><title>SEO-продвижение | 5MB2 Digital</title></html>"
        robots = "User-agent: *\n"
        sm = "<urlset></urlset>"

        def fake_get(url, timeout=12.0):
            if "robots" in url:
                return 200, robots, {}
            if "sitemap" in url:
                return 200, sm, {}
            return 200, html, {}

        with mock.patch.object(sw, "_http_get", side_effect=fake_get):
            report = sw.probe_site(dict(sw.DEFAULT_SITES[1]))
        title = next(c for c in report["checks"] if c["id"] == "title")
        self.assertFalse(title["ok"])

    def test_checklist_marks_analytics(self):
        items = sw.workflow_checklist({"metrika_id": "123", "ga4_id": ""})
        analytics = next(i for i in items if i["id"] == "analytics_ids")
        self.assertTrue(analytics["done"])

    def test_api_routes_mention_seo(self):
        api = (PROJECT / "api.py").read_text(encoding="utf-8")
        self.assertIn("/system/seo", api)
        self.assertIn("/system/seo/news-drafts", api)


if __name__ == "__main__":
    unittest.main()
