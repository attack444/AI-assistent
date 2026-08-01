#!/usr/bin/env bash
# SMTP для WordPress 5mb2 через WP Mail SMTP (Яндекс по умолчанию).
# SSL сайта (https://5mb2.ru) уже не трогаем — для писем нужен SMTP-провайдер.
#
#   SMTP_USER='hello@5mb2.ru' \
#   SMTP_PASS='пароль-приложения' \
#   bash project/deploy/setup-smtp-5mb2.sh
#
# Опционально:
#   SMTP_HOST=smtp.yandex.ru SMTP_PORT=587 SMTP_SSL=tls
#   SMTP_FROM_NAME='5MB2 Digital' SMTP_TEST_TO='you@mail.ru'
#
# По умолчанию TLS (STARTTLS) на 587 — как рекомендует Яндекс.
# Старый вариант SSL/465: SMTP_PORT=465 SMTP_SSL=ssl
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
SMTP_USER="${SMTP_USER:-}"
SMTP_PASS="${SMTP_PASS:-}"
SMTP_HOST="${SMTP_HOST:-smtp.yandex.ru}"
SMTP_PORT="${SMTP_PORT:-587}"
SMTP_SSL="${SMTP_SSL:-tls}"
SMTP_FROM_NAME="${SMTP_FROM_NAME:-5MB2 Digital}"
SMTP_TEST_TO="${SMTP_TEST_TO:-}"

[ -d "$ROOT" ] || { echo "[ERR] Нет $ROOT"; exit 1; }

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-config.php" ] && WP="$cand" && break
done
[ -n "$WP" ] || { echo "[ERR] wp-config.php не найден"; exit 1; }

if [ -z "$SMTP_USER" ] || [ -z "$SMTP_PASS" ]; then
  cat <<EOF
[ERR] Нужны переменные окружения:

  SMTP_USER='hello@5mb2.ru' \\
  SMTP_PASS='пароль-приложения-яндекс' \\
  bash project/deploy/setup-smtp-5mb2.sh

Подробно: project/deploy/SETUP_SMTP_RU.md

Пояснение про сертификат:
  HTTPS сайта уже стоит на nginx — для SMTP его копировать не надо.
  Письма уходят на smtp.yandex.ru по SSL провайдера.
EOF
  exit 1
fi

# экранирование для PHP single-quoted strings
php_esc() {
  printf "%s" "$1" | sed "s/'/\\\\'/g"
}

U=$(php_esc "$SMTP_USER")
P=$(php_esc "$SMTP_PASS")
H=$(php_esc "$SMTP_HOST")
N=$(php_esc "$SMTP_FROM_NAME")
PORT="$SMTP_PORT"
SSL="$SMTP_SSL"

BLOCK_BEGIN="/* BEGIN AI-HELPER SMTP */"
BLOCK_END="/* END AI-HELPER SMTP */"

TMP=$(mktemp)
cat > "$TMP" <<PHP
${BLOCK_BEGIN}
if (!defined('WPMS_ON')) {
  define('WPMS_ON', true);
  define('WPMS_MAIL_FROM', '${U}');
  define('WPMS_MAIL_FROM_FORCE', true);
  define('WPMS_MAIL_FROM_NAME', '${N}');
  define('WPMS_MAIL_FROM_NAME_FORCE', true);
  define('WPMS_MAILER', 'smtp');
  define('WPMS_SET_RETURN_PATH', true);
  define('WPMS_SMTP_HOST', '${H}');
  define('WPMS_SMTP_PORT', ${PORT});
  define('WPMS_SSL', '${SSL}');
  define('WPMS_SMTP_AUTH', true);
  define('WPMS_SMTP_AUTOTLS', true);
  define('WPMS_SMTP_USER', '${U}');
  define('WPMS_SMTP_PASS', '${P}');
}
${BLOCK_END}
PHP

echo "[>>] WP: $WP"
CFG="$WP/wp-config.php"
cp -a "$CFG" "${CFG}.bak-smtp-$(date +%Y%m%d%H%M%S)"

# убрать старый блок, если был
if grep -q "BEGIN AI-HELPER SMTP" "$CFG"; then
  # awk: drop between markers inclusive
  awk '
    /BEGIN AI-HELPER SMTP/ {skip=1; next}
    /END AI-HELPER SMTP/ {skip=0; next}
    !skip {print}
  ' "$CFG" > "${CFG}.tmp" && mv "${CFG}.tmp" "$CFG"
fi

# вставить перед That's all / <?php closing logic
if grep -q "That's all" "$CFG"; then
  # insert file contents before that line
  awk -v blockfile="$TMP" '
    /That.s all/ && !done {
      while ((getline line < blockfile) > 0) print line
      close(blockfile)
      done=1
    }
    { print }
  ' "$CFG" > "${CFG}.tmp" && mv "${CFG}.tmp" "$CFG"
else
  cat "$TMP" >> "$CFG"
fi
rm -f "$TMP"
echo "  ✓ константы WPMS_* в wp-config.php"

# активировать плагин
echo "[>>] Активация WP Mail SMTP"
php -r "
define('WP_USE_THEMES', false);
require '${WP}/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
\$p = 'wp-mail-smtp/wp_mail_smtp.php';
if (!is_plugin_active(\$p) && file_exists(WP_PLUGIN_DIR . '/wp-mail-smtp/wp_mail_smtp.php')) {
  activate_plugin(\$p);
  echo \"plugin activated\n\";
} else {
  echo is_plugin_active(\$p) ? \"plugin already active\n\" : \"plugin files missing\n\";
}
update_option('admin_email', '${U}');
echo 'admin_email=' . get_option('admin_email') . PHP_EOL;
" 2>/dev/null || echo "(php activate — проверь плагин вручную в админке)"

# тест письма
TO="${SMTP_TEST_TO:-$SMTP_USER}"
echo "[>>] Тест письма → $TO"
php -r "
define('WP_USE_THEMES', false);
require '${WP}/wp-load.php';
\$ok = wp_mail('${TO}', 'Тест SMTP 5MB2', \"Письмо с сайта 5mb2.ru отправлено через SMTP.\nВремя: \" . current_time('mysql') . \"\n\");
echo \$ok ? \"OK: wp_mail вернул true\n\" : \"FAIL: wp_mail вернул false — смотри логи WP Mail SMTP\n\";
" 2>/dev/null || echo "(тест не удалось запустить из CLI)"

docker restart ai-helper-php >/dev/null 2>&1 || true

echo ""
echo "============================================"
echo "  SMTP: ${SMTP_HOST}:${SMTP_PORT} (${SMTP_SSL})"
echo "  From: ${SMTP_USER}"
echo "  Админка: https://5mb2.ru/wp-admin/"
echo "  Плагин: WP Mail SMTP → Send a Test Email"
echo ""
echo "  DNS для почты на домене (Яндекс 360):"
echo "    MX + SPF (+ DKIM) — см. SETUP_SMTP_RU.md"
echo "============================================"
