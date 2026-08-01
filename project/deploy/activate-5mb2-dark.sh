#!/bin/bash
# Ставит тёмную тему 5mb2-dark, кабинет, чинит гостевой чат (rebuild app).
#   bash project/deploy/activate-5mb2-dark.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$SCRIPT_DIR/../.." && pwd)"
SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
THEME_SRC="$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark"

echo "[>>] 1/4 тема → $ROOT"
WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -d "$cand/wp-content/themes" ] && WP="$cand" && break
done
[ -n "$WP" ] || WP=$(find "$ROOT" -maxdepth 3 -type d -path '*/wp-content/themes' 2>/dev/null | head -1 | xargs -r dirname | xargs -r dirname || true)
[ -d "$WP/wp-content/themes" ] || { echo "[ERR] WP themes не найдены"; exit 1; }

mkdir -p "$WP/wp-content/themes/5mb2-dark"
rsync -a --delete "$THEME_SRC/" "$WP/wp-content/themes/5mb2-dark/"
chown -R www-data:www-data "$WP/wp-content/themes/5mb2-dark" 2>/dev/null || true

# Activate via WP-CLI if present, else wp-config option update with php
activate() {
  if command -v wp >/dev/null 2>&1; then
    wp theme activate 5mb2-dark --path="$WP" --allow-root
    wp rewrite flush --path="$WP" --allow-root || true
    return
  fi
  if docker ps --format '{{.Names}}' 2>/dev/null | grep -q php; then
    docker exec ai-helper-php bash -lc "command -v wp >/dev/null && wp theme activate 5mb2-dark --path=/var/ai-helper/sites/${SITE_NAME} --allow-root" 2>/dev/null || true
  fi
  # Fallback: set stylesheet/template in DB via mysql if credentials exist
  ENV="$SCRIPT_DIR/../.env"
  if [ -f "$ENV" ]; then
    # shellcheck disable=SC1090
    set +u
    DB_NAME=$(grep -E '^MYSQL_DATABASE=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
    DB_USER=$(grep -E '^MYSQL_USER=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
    DB_PASS=$(grep -E '^MYSQL_PASSWORD=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
    set -u
    if [ -n "${DB_NAME:-}" ] && command -v mysql >/dev/null 2>&1; then
      PREFIX=$(docker exec ai-helper-mysql mysql -N -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT option_value FROM wp0w_options WHERE option_name='template' LIMIT 1;" 2>/dev/null || echo "")
      # try common prefixes
      for pref in wp0w_ wp_; do
        docker exec ai-helper-mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
          "UPDATE ${pref}options SET option_value='5mb2-dark' WHERE option_name IN ('template','stylesheet');" 2>/dev/null && break || true
      done
    fi
  fi
}
activate || true

echo "[>>] 2/5 убрать Elementor / Astra (одна оболочка — тема)"
bash "$SCRIPT_DIR/purge-elementor-5mb2.sh" || true

echo "[>>] 3/5 виджет + nginx"
bash "$SCRIPT_DIR/install-5mb2-widget.sh" || true

echo "[>>] 4/5 PUBLIC_WIDGET_GUEST + rebuild API"
ENVF="$SCRIPT_DIR/../.env"
touch "$ENVF"
grep -q '^PUBLIC_WIDGET_GUEST=' "$ENVF" 2>/dev/null || echo 'PUBLIC_WIDGET_GUEST=1' >> "$ENVF"
sed -i 's/^PUBLIC_WIDGET_GUEST=.*/PUBLIC_WIDGET_GUEST=1/' "$ENVF" 2>/dev/null || true
cd "$SCRIPT_DIR"
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d --force-recreate app

echo "[>>] 5/5 кэш"
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true

echo "============================================"
echo "  Одна оболочка: тема 5mb2-dark (без Elementor)"
echo "  Кабинет: http://5mb2.ru/cabinet/"
echo "  Гостевой чат: /api/status → widget_guest:true, version 2.9.0"
echo "  Ctrl+F5 на http://5mb2.ru/"
echo "============================================"
