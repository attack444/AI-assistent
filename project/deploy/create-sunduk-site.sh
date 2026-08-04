#!/usr/bin/env bash
# Витрина игровой студии SUNDUK → /var/ai-helper/sites/sunduk
#   bash project/deploy/create-sunduk-site.sh
set -euo pipefail

NAME="${SITE_NAME:-sunduk}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$NAME"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_SRC="$SCRIPT_DIR/../sites/sunduk"

mkdir -p "$ROOT"

if [ ! -f "$REPO_SRC/index.html" ]; then
  echo "[ERR] Нет $REPO_SRC/index.html — git pull ветку со студией"
  exit 1
fi

cp -f "$REPO_SRC/index.html" "$ROOT/index.html"
for f in robots.txt sitemap.xml; do
  if [ -f "$REPO_SRC/$f" ]; then
    cp -f "$REPO_SRC/$f" "$ROOT/$f"
  fi
done

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chmod -R a+rX "$ROOT"
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo "============================================"
echo "  SUNDUK preview: http://${IP}/sites/${NAME}/"
echo "  или: https://neobrain.site/sites/${NAME}/"
echo "  На 5mb2.ru — после переноса SEO: enable-sunduk.sh"
echo "============================================"
