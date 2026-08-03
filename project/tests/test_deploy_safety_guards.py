"""Static guards: deploy scripts must not auto-wipe MySQL or foreign SSL vhosts."""
from __future__ import annotations

from pathlib import Path

DEPLOY = Path(__file__).resolve().parents[1] / "deploy"


def _read(name: str) -> str:
    return (DEPLOY / name).read_text(encoding="utf-8")


def test_finish_site_does_not_auto_reinit_mysql():
    text = _read("finish-site.sh")
    assert "--reinit" in text  # still documents manual recovery
    assert "НЕ делаю --reinit автоматически" in text
    # Dangerous pattern: reset || reset --reinit
    assert "|| bash \"$DEPLOY/reset-mysql-password.sh\" --reinit" not in text
    assert "|| bash $DEPLOY/reset-mysql-password.sh --reinit" not in text


def test_reset_mysql_does_not_grep_wipe_all_mysql_volumes():
    text = _read("reset-mysql-password.sh")
    assert "grep -E 'mysql_data|deploy_mysql'" not in text
    assert "_mysql_data_volume" in text
    # Cyrillic password rewrite must not force REINIT=1
    assert "REINIT=1" not in text.split("кириллица")[1].split("sql_escape")[0]


def test_enable_https_does_not_disable_unrelated_ssl_vhosts():
    text = _read("enable-https-5mb2.sh")
    assert "отключаю чужой SSL vhost" not in text
    assert "server_name[[:space:]]+[^;]*${DOMAIN}" in text
    assert "битый SSL vhost" in text


def test_repair_https_scopes_to_target_domain():
    text = _read("repair-https-5mb2.sh")
    assert 'server_name.*${DOMAIN}|listen[[:space:]]+443' not in text
    assert "server_name[[:space:]]+[^;]*${DOMAIN}" in text
