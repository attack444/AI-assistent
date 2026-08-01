#!/bin/bash
# Чистка 5mb2 по шагам (можно запускать повторно — безопасно).
#   bash project/deploy/cleanup-5mb2-plugins.sh
#
# Удаляет: AI-плагины, WPForms, RSS, дефолтные темы twenty*
# Оставляет: Astra, CF7, Flamingo, Elementor, SEO, кэш, Wordfence…
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
THEMES="$WP/wp-content/themes"

REMOVE_PLUGINS=(
  aibuddy-openai-chatgpt
  ai-engine
  alttext-ai
  chatbot
  gpt3-ai-content-generator
  wpforms-lite
  wp-rss-aggregator
)

REMOVE_THEMES=(
  twentytwentythree
  twentytwentyfour
  twentytwentyfive
  twentytwentytwo
  twentytwentyone
  twentytwenty
)

echo "[>>] WP root: $WP"
echo "--- плагины ---"
for p in "${REMOVE_PLUGINS[@]}"; do
  if [ -d "$PLUGINS/$p" ]; then
    rm -rf "$PLUGINS/$p"
    echo "  - удалён плагин: $p"
  else
    echo "  · нет: $p"
  fi
done

echo "--- темы ---"
for t in "${REMOVE_THEMES[@]}"; do
  if [ -d "$THEMES/$t" ]; then
    rm -rf "$THEMES/$t"
    echo "  - удалена тема: $t"
  else
    echo "  · нет: $t"
  fi
done

# Сброс кэша
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true
rm -f "$ROOT/wp-config.php.bak-aihelper" 2>/dev/null || true

echo ""
echo "[OK] Тема: Astra. Формы: contact-form-7 + flamingo."
echo "Плагины сейчас:"
ls "$PLUGINS"
echo "Темы сейчас:"
ls "$THEMES"
echo ""
echo "Дальше (виджет чата): bash project/deploy/install-5mb2-widget.sh"
