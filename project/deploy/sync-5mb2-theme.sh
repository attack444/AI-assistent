#!/usr/bin/env bash
# Синхронизирует тему 5mb2-dark на VPS и обновляет меню/страницы.
#   bash project/deploy/sync-5mb2-theme.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE="${SITES_ROOT:-/var/ai-helper/sites}/5mb2"
THEME_SRC="$ROOT/project/sites/5mb2/wp-content/themes/5mb2-dark"
THEME_DST="$SITE/wp-content/themes/5mb2-dark"

[ -d "$THEME_SRC" ] || { echo "[ERR] нет темы $THEME_SRC"; exit 1; }
[ -d "$SITE/wp-content" ] || { echo "[ERR] нет сайта $SITE"; exit 1; }

echo "==> rsync темы → $THEME_DST"
mkdir -p "$THEME_DST"
rsync -a --delete "$THEME_SRC/" "$THEME_DST/"
chown -R www-data:www-data "$THEME_DST" 2>/dev/null || true

MU_SRC="$ROOT/project/sites/5mb2/wp-content/mu-plugins"
MU_DST="$SITE/wp-content/mu-plugins"
if [ -d "$MU_SRC" ]; then
  echo "==> mu-plugins (health-guard)"
  mkdir -p "$MU_DST"
  rsync -a "$MU_SRC/" "$MU_DST/"
  chown -R www-data:www-data "$MU_DST" 2>/dev/null || true
fi

if [ -f "$ROOT/project/sites/5mb2/robots.txt" ]; then
  echo "==> robots.txt"
  cp "$ROOT/project/sites/5mb2/robots.txt" "$SITE/robots.txt"
  chown www-data:www-data "$SITE/robots.txt" 2>/dev/null || true
fi

echo "==> безопасный seed (без перезаписи контента страниц)"
php -r "
define('WP_USE_THEMES', false);
require '$SITE/wp-load.php';
if (function_exists('mb2_ensure_site_structure')) {
  mb2_ensure_site_structure();
  update_option('mb2_structure_ver', defined('MB2_THEME_VER') ? MB2_THEME_VER : '1.9.6', false);
  echo 'structure ok theme=' . get_stylesheet() . ' ver=' . (defined('MB2_THEME_VER') ? MB2_THEME_VER : '?') . PHP_EOL;
  echo 'services=' . (get_page_by_path('services') ? 'yes' : 'no') . PHP_EOL;
} else {
  echo \"functions not loaded — открой сайт один раз\n\";
}
" 2>/dev/null || echo "(php/wp-load пропуск — открой http://5mb2.ru/ )"

rm -rf "$SITE/wp-content/cache/"* 2>/dev/null || true
docker restart ai-helper-php >/dev/null 2>&1 || true

echo ""
echo "Готово. Ctrl+F5 → http://5mb2.ru/ и http://5mb2.ru/cabinet/"
