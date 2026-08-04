"""Static guards: deploy scripts must not auto-wipe MySQL or foreign SSL vhosts."""
from __future__ import annotations

import unittest
from pathlib import Path

DEPLOY = Path(__file__).resolve().parents[1] / "deploy"


def _read(name: str) -> str:
    return (DEPLOY / name).read_text(encoding="utf-8")


class DeploySafetyGuardTests(unittest.TestCase):
    def test_finish_site_does_not_auto_reinit_mysql(self) -> None:
        text = _read("finish-site.sh")
        self.assertIn("--reinit", text)  # still documents manual recovery
        self.assertIn("НЕ делаю --reinit автоматически", text)
        # Dangerous pattern: reset || reset --reinit
        self.assertNotIn('|| bash "$DEPLOY/reset-mysql-password.sh" --reinit', text)
        self.assertNotIn("|| bash $DEPLOY/reset-mysql-password.sh --reinit", text)

    def test_reset_mysql_does_not_grep_wipe_all_mysql_volumes(self) -> None:
        text = _read("reset-mysql-password.sh")
        self.assertNotIn("grep -E 'mysql_data|deploy_mysql'", text)
        self.assertIn("_mysql_data_volume", text)
        # Cyrillic password rewrite must not assign REINIT=1 (comment may mention it)
        cyr_block = text.split("кириллица")[1].split("sql_escape")[0]
        self.assertNotIn(
            "REINIT=1",
            [
                ln.strip()
                for ln in cyr_block.splitlines()
                if not ln.strip().startswith("#")
            ],
        )

    def test_enable_https_does_not_disable_unrelated_ssl_vhosts(self) -> None:
        text = _read("enable-https-5mb2.sh")
        self.assertNotIn("отключаю чужой SSL vhost", text)
        self.assertIn("server_name[[:space:]]+[^;]*${DOMAIN}", text)
        self.assertIn("битый SSL vhost", text)

    def test_repair_https_scopes_to_target_domain(self) -> None:
        text = _read("repair-https-5mb2.sh")
        self.assertNotIn('server_name.*${DOMAIN}|listen[[:space:]]+443', text)
        self.assertIn("server_name[[:space:]]+[^;]*${DOMAIN}", text)


if __name__ == "__main__":
    unittest.main()
