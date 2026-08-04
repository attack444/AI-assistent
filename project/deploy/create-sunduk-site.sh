#!/usr/bin/env bash
# Создаёт/обновляет превью студии SUNDUK:
#   https://neobrain.site/sites/sunduk/
# Полный многостраничный сайт (игры, обзоры, новости, PWA).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$ROOT/project/sites/sunduk"
DEST="${SITES_ROOT:-/var/ai-helper/sites}/sunduk"
WEB_USER="${WEB_USER:-www-data}"

if [[ ! -d "$SRC" ]]; then
  echo "ERROR: source not found: $SRC" >&2
  exit 1
fi

mkdir -p "$DEST"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete \
    --exclude '.DS_Store' \
    "$SRC/" "$DEST/"
else
  find "$DEST" -mindepth 1 -delete 2>/dev/null || true
  cp -a "$SRC/." "$DEST/"
fi

chown -R "$WEB_USER:$WEB_USER" "$DEST" 2>/dev/null || true
find "$DEST" -type d -exec chmod 755 {} +
find "$DEST" -type f -exec chmod 644 {} +

echo "OK: SUNDUK studio published -> $DEST"
echo "Preview: https://neobrain.site/sites/sunduk/"
echo "Pages: / /play/ /games/ /reviews/ /news/ /about/ /contact/ /press/"
echo "Next (after SEO migrate off 5mb2): bash project/deploy/enable-sunduk.sh"
