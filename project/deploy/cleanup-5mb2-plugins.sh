#!/bin/bash
# Удаляет лишние плагины на живом 5mb2 (AI + WPForms).
# CF7 + Flamingo остаются.
#   bash project/deploy/cleanup-5mb2-plugins.sh
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html" "$ROOT/www"; do
  if [ -d "$cand/wp-content/plugins" ]; then WP="$cand"; break; fi
done
if [ -z "$WP" ]; then
  WP=$(find "$ROOT" -maxdepth 3 -type d -path '*/wp-content/plugins' 2>/dev/null | head -1 | xargs -r dirname | xargs -r dirname || true)
fi
[ -n "$WP" ] && [ -d "$WP/wp-content/plugins" ] || { echo "[ERR] plugins не найдены"; exit 1; }

PLUGINS="$WP/wp-content/plugins"
REMOVE=(
  aibuddy-openai-chatgpt
  ai-engine
  alttext-ai
  chatbot
  gpt3-ai-content-generator
  wpforms-lite
)

echo "[>>] WP root: $WP"
for p in "${REMOVE[@]}"; do
  if [ -d "$PLUGINS/$p" ]; then
    rm -rf "$PLUGINS/$p"
    echo "  - удалён: $p"
  else
    echo "  · нет: $p"
  fi
done

# Сброс кэша плагина кэша, если есть
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true

echo "[OK] Остались формы: contact-form-7 (+ flamingo)."
echo "В админке WP: Плагины → убедись, что удалённые пропали; при необходимости деактивируй «пропавшие»."
ls "$PLUGINS"
