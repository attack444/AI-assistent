#!/bin/bash
# Публичная витрина NeoBrain (хаб: AI + SEO + …).
#   bash project/deploy/create-ai-site.sh
#   # или: bash project/deploy/publish-neobrain-hub.sh
set -euo pipefail

NAME="${SITE_NAME:-ai}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$NAME"
REPO="${REPO_DIR:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_USER="${WEB_USER:-www-data}"

mkdir -p "$ROOT"

DIR_SRC=""
if [ -f "$SCRIPT_DIR/../sites/ai/index.html" ]; then
  DIR_SRC="$SCRIPT_DIR/../sites/ai"
elif [ -n "$REPO" ] && [ -f "$REPO/project/sites/ai/index.html" ]; then
  DIR_SRC="$REPO/project/sites/ai"
elif [ -f /opt/ai-helper/project/sites/ai/index.html ]; then
  DIR_SRC=/opt/ai-helper/project/sites/ai
fi

if [ -n "$DIR_SRC" ]; then
  if command -v rsync >/dev/null 2>&1; then
    # Не трогаем /sunduk/ — его кладёт create-sunduk / announce-sunduk
    rsync -a --delete \
      --exclude '.DS_Store' \
      --exclude 'sunduk/' \
      "$DIR_SRC/" "$ROOT/"
  else
    cp -f "$DIR_SRC/index.html" "$ROOT/index.html"
    [ -f "$DIR_SRC/widget.js" ] && cp -f "$DIR_SRC/widget.js" "$ROOT/widget.js"
    for f in robots.txt sitemap.xml; do
      [ -f "$DIR_SRC/$f" ] && cp -f "$DIR_SRC/$f" "$ROOT/$f"
    done
    for page in contacts rekvizity seo; do
      if [ -d "$DIR_SRC/$page" ]; then
        mkdir -p "$ROOT/$page"
        cp -f "$DIR_SRC/$page/"*.html "$ROOT/$page/" 2>/dev/null || true
      fi
      if [ -f "$DIR_SRC/${page}.html" ]; then
        cp -f "$DIR_SRC/${page}.html" "$ROOT/${page}.html"
      fi
    done
  fi
  echo "[OK] Хаб NeoBrain из $DIR_SRC → $ROOT"
else
  BASE="https://raw.githubusercontent.com/attack444/AI-assistent/main/project/sites/ai"
  curl -fsSL "$BASE/index.html" -o "$ROOT/index.html"
  curl -fsSL "$BASE/widget.js" -o "$ROOT/widget.js" 2>/dev/null || true
  echo "[OK] Скачал витрину с GitHub (fallback)"
fi

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chown -R "$WEB_USER:$WEB_USER" "$ROOT" 2>/dev/null || true
chmod -R a+rX "$ROOT"

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo "============================================"
echo "  Хаб:     https://neobrain.site/"
echo "  SEO:     https://neobrain.site/seo/"
echo "  Игры:    https://neobrain.site/sunduk/"
echo "  IP path: http://${IP}/sites/${NAME}/"
echo "============================================"
