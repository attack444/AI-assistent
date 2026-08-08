#!/usr/bin/env bash
# Сброс пароля администратора WordPress БЕЗ почты.
# Письмо «забыли пароль» на VPS почти никогда не доходит без SMTP —
# HTTPS на это не влияет.
#
#   bash project/deploy/reset-wp-admin-password.sh
#   NEW_PASS='МойНовыйПароль' USER_LOGIN=admin bash project/deploy/reset-wp-admin-password.sh
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
USER_LOGIN="${USER_LOGIN:-}"
NEW_PASS="${NEW_PASS:-}"

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html" "$ROOT/www"; do
  [ -f "$cand/wp-load.php" ] && WP="$cand" && break
done
[ -n "$WP" ] || { echo "[ERR] wp-load.php не найден в $ROOT"; exit 1; }

if [ -z "$NEW_PASS" ]; then
  NEW_PASS=$(openssl rand -base64 12 | tr -d '/+=' | head -c 14)
  NEW_PASS="Wp${NEW_PASS}!"
fi

export WP_PATH="$WP"
export WP_USER_LOGIN="$USER_LOGIN"
export WP_NEW_PASS="$NEW_PASS"

echo "[>>] WP: $WP"
echo "[>>] Ставлю новый пароль администратору…"

php <<'PHP'
<?php
$wp = getenv('WP_PATH');
$login = trim((string) getenv('WP_USER_LOGIN'));
$pass = (string) getenv('WP_NEW_PASS');
if ($wp === false || $wp === '' || !is_file($wp . '/wp-load.php')) {
    fwrite(STDERR, "wp-load.php not found\n");
    exit(1);
}
define('WP_USE_THEMES', false);
require $wp . '/wp-load.php';

$user = null;
if ($login !== '') {
    $user = get_user_by('login', $login) ?: get_user_by('email', $login);
}
if (!$user) {
    $admins = get_users([
        'role' => 'administrator',
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
    if (!empty($admins)) {
        $user = $admins[0];
    }
}
if (!$user) {
    fwrite(STDERR, "Администратор не найден\n");
    exit(1);
}

wp_set_password($pass, $user->ID);
clean_user_cache($user);
delete_user_meta($user->ID, 'default_password_nag');

echo "OK\n";
echo "user_id=" . $user->ID . "\n";
echo "login=" . $user->user_login . "\n";
echo "email=" . $user->user_email . "\n";
echo "home=" . home_url('/') . "\n";
PHP

echo ""
echo "============================================"
echo "  Пароль:   ${NEW_PASS}"
echo "  Вход:     смотри home= выше → /wp-login.php"
echo "  Сохрани пароль. Почта WP без SMTP обычно не ходит."
echo "============================================"
