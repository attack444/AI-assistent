#!/usr/bin/env bash
# Убирает блок WPMS_* из wp-config.php, чтобы WP Mail SMTP
# снова давал ручной ввод в админке.
#
#   bash project/deploy/remove-smtp-constants-5mb2.sh
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-config.php" ] && WP="$cand" && break
done
[ -n "$WP" ] || { echo "[ERR] wp-config.php не найден в $ROOT"; exit 1; }

CFG="$WP/wp-config.php"
cp -a "$CFG" "${CFG}.bak-rm-smtp-$(date +%Y%m%d%H%M%S)"

if ! grep -q "BEGIN AI-HELPER SMTP\|WPMS_SMTP_PORT\|WPMS_ON" "$CFG"; then
  echo "Констант WPMS_* / блока AI-HELPER SMTP не найдено — уже чисто."
  echo "Открой WP Mail SMTP в админке и введи настройки вручную."
  exit 0
fi

# 1) блок между маркерами
if grep -q "BEGIN AI-HELPER SMTP" "$CFG"; then
  awk '
    /BEGIN AI-HELPER SMTP/ {skip=1; next}
    /END AI-HELPER SMTP/ {skip=0; next}
    !skip {print}
  ' "$CFG" > "${CFG}.tmp" && mv "${CFG}.tmp" "$CFG"
  echo "  ✓ удалён блок /* BEGIN AI-HELPER SMTP */"
fi

# 2) одиночные define WPMS_* (если кто-то вставлял без маркеров)
if grep -qE "define\s*\(\s*['\"]WPMS_" "$CFG"; then
  grep -vE "define\s*\(\s*['\"]WPMS_" "$CFG" > "${CFG}.tmp" && mv "${CFG}.tmp" "$CFG"
  echo "  ✓ убраны оставшиеся define('WPMS_…')"
fi

docker restart ai-helper-php >/dev/null 2>&1 || true

echo ""
echo "Готово. Обнови страницу плагина (Ctrl+F5):"
echo "  https://5mb2.ru/wp-admin/admin.php?page=wp-mail-smtp"
echo "Поля снова редактируются. Введи SMTP вручную."
