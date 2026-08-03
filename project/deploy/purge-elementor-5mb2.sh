#!/bin/bash
# Отключает Elementor/Essential Addons (НЕ удаляет файлы) + включает тему 5mb2-dark.
#   bash project/deploy/purge-elementor-5mb2.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html" "$ROOT/www"; do
  [ -d "$cand/wp-content/plugins" ] && WP="$cand" && break
done
[ -n "$WP" ] || WP=$(find "$ROOT" -maxdepth 3 -type d -path '*/wp-content/plugins' 2>/dev/null | head -1 | xargs -r dirname | xargs -r dirname || true)
[ -d "$WP/wp-content" ] || { echo "[ERR] WP не найден"; exit 1; }

THEMES="$WP/wp-content/themes"
echo "[>>] WP: $WP"
echo "[>>] Режим: отключить конструкторы, оставить файлы"

# 1) Тема
if [ -d "$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark" ]; then
  mkdir -p "$THEMES/5mb2-dark"
  rsync -a --delete "$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark/" "$THEMES/5mb2-dark/"
  chown -R www-data:www-data "$THEMES/5mb2-dark" 2>/dev/null || true
  echo "  ✓ 5mb2-dark обновлена"
fi

# 2) Отключение через wp-cli или php helper
ok=0
if command -v wp >/dev/null 2>&1; then
  wp theme activate 5mb2-dark --path="$WP" --allow-root
  wp plugin deactivate elementor essential-addons-for-elementor-lite elementor-pro essential-addons-for-elementor \
    --path="$WP" --allow-root 2>/dev/null || true
  wp option update show_on_front posts --path="$WP" --allow-root
  wp option delete page_on_front --path="$WP" --allow-root 2>/dev/null || true
  wp rewrite flush --path="$WP" --allow-root || true
  wp cache flush --path="$WP" --allow-root 2>/dev/null || true
  ok=1
  echo "  ✓ wp-cli: тема + deactivate"
fi

if [ "$ok" -eq 0 ] && command -v php >/dev/null 2>&1; then
  php "$SCRIPT_DIR/wp-disable-builders.php" "$WP" && ok=1 && echo "  ✓ php helper"
fi

# PHP внутри php-контейнера
if [ "$ok" -eq 0 ] && docker ps --format '{{.Names}}' 2>/dev/null | grep -q ai-helper-php; then
  docker cp "$SCRIPT_DIR/wp-disable-builders.php" ai-helper-php:/tmp/wp-disable-builders.php
  docker exec ai-helper-php php /tmp/wp-disable-builders.php "$WP" && ok=1 && echo "  ✓ php в контейнере"
fi

if [ "$ok" -eq 0 ]; then
  echo ""
  echo "Авто-отключение не удалось. В админке WP вручную:"
  echo "  1) Плагины → Выключить: Elementor, Essential Addons"
  echo "  2) Внешний вид → Темы → 5MB2 Dark → Активировать"
  echo "  3) Настройки → Чтение → «На главной отображать: последние записи»"
fi

# 3) Кэш фронта (не сами плагины)
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true
rm -rf "$WP/wp-content/uploads/elementor/css" 2>/dev/null || true

echo ""
echo "============================================"
echo "  Elementor НЕ удалён — только выключен"
echo "  Оболочка сайта: тема 5mb2-dark"
echo "  http://5mb2.ru/  → Ctrl+F5"
echo "============================================"
