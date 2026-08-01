#!/bin/bash
# Ставит чат-виджет AI Helper на WordPress-сайт 5mb2 (mu-plugin).
#   bash project/deploy/install-5mb2-widget.sh
#   SITE_NAME=mysite bash project/deploy/install-5mb2-widget.sh
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_AI="${SCRIPT_DIR}/../sites/ai"
REPO_KIT="${SCRIPT_DIR}/../sites/kits"

if [ ! -d "$ROOT" ]; then
  echo "[ERR] Нет сайта: $ROOT"
  exit 1
fi

# 1) Обновить витрину ai (нужен widget.js)
if [ -x "$SCRIPT_DIR/create-ai-site.sh" ]; then
  bash "$SCRIPT_DIR/create-ai-site.sh" || true
fi
if [ -f "$REPO_AI/widget.js" ]; then
  mkdir -p "${SITES_DIR}/ai"
  cp "$REPO_AI/widget.js" "${SITES_DIR}/ai/widget.js"
  cp "$REPO_AI/index.html" "${SITES_DIR}/ai/index.html" 2>/dev/null || true
fi

# 2) Найти WordPress и поставить mu-plugin
WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html" "$ROOT/www" "$ROOT/httpdocs"; do
  if [ -f "$cand/wp-config.php" ] || [ -d "$cand/wp-content" ]; then
    WP="$cand"
    break
  fi
done

if [ -z "$WP" ]; then
  # глубокий поиск (не дальше 3 уровней)
  WP=$(find "$ROOT" -maxdepth 3 -type f -name wp-config.php 2>/dev/null | head -1 | xargs -r dirname || true)
fi

if [ -z "$WP" ] || [ ! -d "$WP" ]; then
  echo "[WARN] WordPress не найден в $ROOT — кладу HTML-сниппет в $ROOT/ai-helper-widget.html"
  cp "$REPO_KIT/wp-chat-widget.html" "$ROOT/ai-helper-widget.html"
  echo "Вставь содержимое вручную в footer темы."
  exit 0
fi

MU="$WP/wp-content/mu-plugins"
mkdir -p "$MU"
cp "$REPO_KIT/ai-helper-chat-widget.php" "$MU/ai-helper-chat-widget.php"

# Подставить имя сайта в mu-plugin
sed -i "s/'5mb2'/'${SITE_NAME}'/g" "$MU/ai-helper-chat-widget.php" 2>/dev/null || true

chown -R www-data:www-data "$MU" 2>/dev/null || true
chmod -R a+rX "${SITES_DIR}/ai" "$MU" 2>/dev/null || true

# Сброс кэша object/cache если есть
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo "============================================"
echo "  Виджет на сайте: ${SITE_NAME}"
echo "  mu-plugin: $MU/ai-helper-chat-widget.php"
echo "  JS: http://${IP}/sites/ai/widget.js"
echo "  Сайт: http://${IP}/sites/${SITE_NAME}/"
echo "  (если домен) https://5mb2.ru/"
echo "============================================"
echo "Открой сайт → кнопка «Чат» справа внизу."
