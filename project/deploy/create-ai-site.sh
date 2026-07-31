#!/bin/bash
# Публичная витрина второго сайта (AI Helper platform).
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

if [ -n "$SRC" ]; then
  cp "$SRC" "$ROOT/index.html"
  echo "[OK] Скопировал витрину из $SRC"
else
  # fallback: скачать с GitHub
  curl -fsSL "https://raw.githubusercontent.com/attack444/AI-assistent/main/project/sites/ai/index.html" \
    -o "$ROOT/index.html"
  echo "[OK] Скачал витрину с GitHub"
fi

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
chmod -R a+rX "$ROOT"
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo "============================================"
echo "  Витрина: http://${IP}/sites/${NAME}/"
echo "  Домен подключим позже"
echo "============================================"
