#!/bin/bash
# Публичная витрина второго сайта (NeoBrain platform).
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/create-ai-site.sh | bash
set -euo pipefail

NAME="${SITE_NAME:-ai}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$NAME"
REPO="${REPO_DIR:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

mkdir -p "$ROOT"

SRC=""
if [ -f "$SCRIPT_DIR/../sites/ai/index.html" ]; then
  SRC="$SCRIPT_DIR/../sites/ai/index.html"
elif [ -n "$REPO" ] && [ -f "$REPO/project/sites/ai/index.html" ]; then
  SRC="$REPO/project/sites/ai/index.html"
elif [ -f /opt/ai-helper/project/sites/ai/index.html ]; then
  SRC=/opt/ai-helper/project/sites/ai/index.html
fi

DIR_SRC="$(dirname "$SRC" 2>/dev/null || true)"
if [ -n "$SRC" ]; then
  cp "$SRC" "$ROOT/index.html"
  if [ -f "${DIR_SRC}/widget.js" ]; then
    cp "${DIR_SRC}/widget.js" "$ROOT/widget.js"
  fi
  for f in robots.txt sitemap.xml; do
    if [ -f "${DIR_SRC}/${f}" ]; then
      cp "${DIR_SRC}/${f}" "$ROOT/${f}"
    fi
  done
  for page in contacts rekvizity; do
    if [ -f "${DIR_SRC}/${page}.html" ]; then
      cp -f "${DIR_SRC}/${page}.html" "$ROOT/${page}.html"
    fi
    if [ -d "${DIR_SRC}/${page}" ]; then
      mkdir -p "$ROOT/${page}"
      cp -f "${DIR_SRC}/${page}/"*.html "$ROOT/${page}/" 2>/dev/null || true
    fi
  done
  echo "[OK] Скопировал витрину из $SRC"
else
  # fallback: скачать с GitHub (ветка по умолчанию main; при необходимости подставь свою)
  BASE="https://raw.githubusercontent.com/attack444/AI-assistent/main/project/sites/ai"
  curl -fsSL "$BASE/index.html" -o "$ROOT/index.html"
  curl -fsSL "$BASE/widget.js" -o "$ROOT/widget.js" 2>/dev/null || true
  echo "[OK] Скачал витрину с GitHub"
fi

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chmod -R a+rX "$ROOT"
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo "============================================"
echo "  Витрина (IP): http://${IP}/sites/${NAME}/"
echo "  Домен: neobrain.site (enable-neobrain.sh)"
echo "============================================"
