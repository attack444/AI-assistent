#!/usr/bin/env bash
# Диагностика SMTP для 5mb2 (сеть + константы + тест wp_mail).
#   bash project/deploy/diagnose-smtp-5mb2.sh
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
HOST="${SMTP_HOST:-smtp.yandex.ru}"

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-config.php" ] && WP="$cand" && break
done

echo "======== 1) Исходящие порты с ХОСТА ========"
for port in 587 465; do
  if timeout 5 bash -c "echo >/dev/tcp/${HOST}/${port}" 2>/dev/null; then
    echo "  OK  host → ${HOST}:${port}"
  else
    echo "  FAIL host → ${HOST}:${port}  (фаервол/провайдер режет исходящий SMTP)"
  fi
done

echo "======== 2) Из контейнера PHP (если есть) ========"
if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ai-helper-php'; then
  for port in 587 465; do
    if docker exec ai-helper-php timeout 5 bash -c "echo >/dev/tcp/${HOST}/${port}" 2>/dev/null; then
      echo "  OK  php-container → ${HOST}:${port}"
    else
      echo "  FAIL php-container → ${HOST}:${port}"
      echo "       ← частая причина: WordPress в Docker не достучится до SMTP"
    fi
  done
else
  echo "  (контейнер ai-helper-php не найден — PHP, возможно, на хосте)"
fi

echo "======== 3) wp-config константы WPMS_ ========"
if [ -n "$WP" ] && grep -qE "WPMS_ON|WPMS_SMTP_|BEGIN AI-HELPER SMTP" "$WP/wp-config.php" 2>/dev/null; then
  echo "  ЕСТЬ константы — поля в плагине могут быть заблокированы / конфликт настроек"
  grep -nE "WPMS_|AI-HELPER SMTP" "$WP/wp-config.php" | head -20 || true
  echo "  Снять: bash project/deploy/remove-smtp-constants-5mb2.sh"
else
  echo "  констант нет — ок для ручного ввода в плагине"
fi

echo "======== 4) Плагин и последняя ошибка ========"
if [ -n "$WP" ] && [ -f "$WP/wp-load.php" ]; then
  php -r "
  define('WP_USE_THEMES', false);
  require '${WP}/wp-load.php';
  require_once ABSPATH . 'wp-admin/includes/plugin.php';
  \$p = 'wp-mail-smtp/wp_mail_smtp.php';
  echo is_plugin_active(\$p) ? \"  plugin: active\n\" : \"  plugin: NOT active\n\";
  \$opt = get_option('wp_mail_smtp', []);
  if (is_array(\$opt)) {
    \$mailer = \$opt['mail']['mailer'] ?? '?';
    \$host = \$opt['smtp']['host'] ?? '?';
    \$port = \$opt['smtp']['port'] ?? '?';
    \$enc  = \$opt['smtp']['encryption'] ?? '?';
    \$user = \$opt['smtp']['user'] ?? '?';
    \$from = \$opt['mail']['from_email'] ?? '?';
    echo \"  mailer=\$mailer host=\$host port=\$port enc=\$enc\n\";
    echo \"  user=\$user from=\$from\n\";
  }
  // debug events (WP Mail SMTP Email Log / transient)
  \$dbg = get_option('wp_mail_smtp_debug', '');
  if (\$dbg) { echo \"  debug_option: \" . substr(strip_tags((string)\$dbg), 0, 400) . \"\n\"; }
  \$err = get_transient('wp_mail_smtp_mail_catcher_errors') ?: get_option('wp_mail_smtp_debug_events_errors');
  if (\$err) { echo \"  errors: \" . substr(print_r(\$err, true), 0, 600) . \"\n\"; }
  " 2>/dev/null || echo "  (php/wp-load не смог — смотри ошибку в админке плагина)"
else
  echo "  WP не найден"
fi

echo ""
echo "======== Что попробовать ========"
echo "1) TLS + 587 не ушёл → в плагине поставь SSL + порт 465 (часто хостинг режет 587)."
echo "2) Authentication failed → пароль ПРИЛОЖЕНИЯ Яндекса, не обычный пароль."
echo "3) From Email должен = Username (тот же ящик)."
echo "4) Если FAIL из php-container — открой исходящие 465/587 с VPS/Docker."
echo "5) Скопируй текст ошибки из WP Mail SMTP → Tools / Email Test и пришли."
