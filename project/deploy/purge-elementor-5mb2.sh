#!/bin/bash
# Убирает Elementor/Astra «сборную солянку» — остаётся только тема 5mb2-dark.
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

PLUGINS="$WP/wp-content/plugins"
THEMES="$WP/wp-content/themes"

echo "[>>] WP: $WP"

# 1) Синхронизировать тему из репо
if [ -d "$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark" ]; then
  mkdir -p "$THEMES/5mb2-dark"
  rsync -a --delete "$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark/" "$THEMES/5mb2-dark/"
  echo "  ✓ тема 5mb2-dark обновлена"
fi

# 2) Удалить конструкторы / старую тему
REMOVE_PLUGINS=(
  elementor
  elementor-pro
  essential-addons-for-elementor-lite
  essential-addons-for-elementor
  header-footer-elementor
  elemntor
)
REMOVE_THEMES=(astra astra-child hello-elementor)

for p in "${REMOVE_PLUGINS[@]}"; do
  if [ -d "$PLUGINS/$p" ]; then
    rm -rf "$PLUGINS/$p"
    echo "  - плагин удалён: $p"
  fi
done
for t in "${REMOVE_THEMES[@]}"; do
  if [ -d "$THEMES/$t" ]; then
    rm -rf "$THEMES/$t"
    echo "  - тема удалена: $t"
  fi
done

# 3) Кэш Elementor / WP Fastest Cache
rm -rf "$WP/wp-content/uploads/elementor" 2>/dev/null || true
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true
rm -rf "$WP/wp-content/uploads/wp-fastest-cache-temp" 2>/dev/null || true
find "$WP/wp-content/uploads" -maxdepth 2 -type d -name 'elementor*' -exec rm -rf {} + 2>/dev/null || true

# 4) Активировать 5mb2-dark + главная = записи (front-page.php темы)
activate_theme() {
  if command -v wp >/dev/null 2>&1; then
    wp theme activate 5mb2-dark --path="$WP" --allow-root
    wp plugin deactivate elementor essential-addons-for-elementor-lite elementor-pro 2>/dev/null || true
    wp option update show_on_front posts --path="$WP" --allow-root
    wp option delete page_on_front --path="$WP" --allow-root 2>/dev/null || true
    wp rewrite flush --path="$WP" --allow-root || true
    wp cache flush --path="$WP" --allow-root 2>/dev/null || true
    return 0
  fi
  # MySQL fallback
  ENV="$SCRIPT_DIR/../.env"
  [ -f "$ENV" ] || return 0
  DB_NAME=$(grep -E '^MYSQL_DATABASE=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  DB_USER=$(grep -E '^MYSQL_USER=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  DB_PASS=$(grep -E '^MYSQL_PASSWORD=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  [ -n "${DB_NAME:-}" ] || return 0
  for pref in wp0w_ wp_; do
    docker exec ai-helper-mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
      UPDATE ${pref}options SET option_value='5mb2-dark' WHERE option_name IN ('template','stylesheet');
      UPDATE ${pref}options SET option_value='posts' WHERE option_name='show_on_front';
      DELETE FROM ${pref}options WHERE option_name='page_on_front';
      DELETE FROM ${pref}options WHERE option_name LIKE '%elementor%css%';
    " 2>/dev/null && echo "  ✓ БД: тема + главная (префикс ${pref})" && break || true
  done
}
activate_theme || true

chown -R www-data:www-data "$THEMES/5mb2-dark" 2>/dev/null || true

echo ""
echo "============================================"
echo "  Осталась одна оболочка: тема 5mb2-dark"
echo "  Elementor / Astra сняты"
echo "  Открой http://5mb2.ru/  →  Ctrl+F5"
echo "  Плагины:"
ls "$PLUGINS"
echo "  Темы:"
ls "$THEMES"
echo "============================================"
