#!/usr/bin/env bash
# Публикует студию SUNDUK в два места:
#   /var/ai-helper/sites/sunduk/     → http://IP/sites/sunduk/
#   /var/ai-helper/sites/ai/sunduk/  → https://neobrain.site/sunduk/
# (корень neobrain.site = sites/ai)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$ROOT/project/sites/sunduk"
SITES="${SITES_ROOT:-/var/ai-helper/sites}"
DEST_HOST="$SITES/sunduk"
DEST_NEO="$SITES/ai/sunduk"
WEB_USER="${WEB_USER:-www-data}"

if [[ ! -d "$SRC" ]]; then
  echo "ERROR: source not found: $SRC" >&2
  exit 1
fi

publish() {
  local dest="$1"
  mkdir -p "$dest"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete --exclude '.DS_Store' "$SRC/" "$dest/"
  else
    find "$dest" -mindepth 1 -delete 2>/dev/null || true
    cp -a "$SRC/." "$dest/"
  fi
  chown -R "$WEB_USER:$WEB_USER" "$dest" 2>/dev/null || true
  find "$dest" -type d -exec chmod 755 {} +
  find "$dest" -type f -exec chmod 644 {} +
  echo "OK: $dest"
}

publish "$DEST_HOST"
if [[ -d "$SITES/ai" ]]; then
  publish "$DEST_NEO"
else
  echo "[!!] Нет $SITES/ai — пропуск превью на neobrain.site/sunduk/"
fi

echo "Preview: https://neobrain.site/sunduk/"
echo "Also:    /sites/sunduk/ на IP (default nginx)"
echo "Pages: / /play/ /games/ /reviews/ /news/ /about/ /contact/ /press/"
