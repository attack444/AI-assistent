#!/bin/bash
# Ставит чат-виджет на 5mb2 + чинит nginx домена (JS локально + /api proxy).
#   bash project/deploy/install-5mb2-widget.sh
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

# 1) Витрина ai (на всякий случай)
if [ -x "$SCRIPT_DIR/create-ai-site.sh" ]; then
  bash "$SCRIPT_DIR/create-ai-site.sh" || true
fi
if [ -f "$REPO_AI/widget.js" ]; then
  mkdir -p "${SITES_DIR}/ai"
  cp "$REPO_AI/widget.js" "${SITES_DIR}/ai/widget.js"
  cp "$REPO_AI/index.html" "${SITES_DIR}/ai/index.html" 2>/dev/null || true
fi

# 2) WordPress root
WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html" "$ROOT/www" "$ROOT/httpdocs"; do
  if [ -f "$cand/wp-config.php" ] || [ -d "$cand/wp-content" ]; then
    WP="$cand"
    break
  fi
done
if [ -z "$WP" ]; then
  WP=$(find "$ROOT" -maxdepth 3 -type f -name wp-config.php 2>/dev/null | head -1 | xargs -r dirname || true)
fi
if [ -z "$WP" ] || [ ! -d "$WP" ]; then
  echo "[ERR] WordPress не найден в $ROOT"
  exit 1
fi

MU="$WP/wp-content/mu-plugins"
mkdir -p "$MU"

# 3) mu-plugin + локальный JS (главный фикс для 5mb2.ru)
cp "$REPO_KIT/ai-helper-chat-widget.php" "$MU/ai-helper-chat-widget.php"
if [ -f "$REPO_AI/widget.js" ]; then
  cp "$REPO_AI/widget.js" "$MU/ai-helper-widget.js"
elif [ -f "${SITES_DIR}/ai/widget.js" ]; then
  cp "${SITES_DIR}/ai/widget.js" "$MU/ai-helper-widget.js"
else
  echo "[ERR] Нет widget.js"
  exit 1
fi

chown -R www-data:www-data "$MU" 2>/dev/null || true
chmod -R a+rX "$MU" 2>/dev/null || true

# 4) nginx vhost: /api proxy
VHOST_SRC="$ROOT/nginx.vhost.conf"
if [ -f "$SCRIPT_DIR/../sites/5mb2/nginx.vhost.conf" ]; then
  cp "$SCRIPT_DIR/../sites/5mb2/nginx.vhost.conf" "$VHOST_SRC"
fi

apply_nginx() {
  local dest=""
  if [ -d /etc/nginx/sites-available ]; then
    dest=/etc/nginx/sites-available/5mb2.ru
    cp "$VHOST_SRC" "$dest"
    ln -sfn "$dest" /etc/nginx/sites-enabled/5mb2.ru 2>/dev/null || true
  elif [ -d /etc/nginx/conf.d ]; then
    dest=/etc/nginx/conf.d/5mb2.ru.conf
    cp "$VHOST_SRC" "$dest"
  else
    echo "[WARN] nginx conf dir не найден — скопируй $VHOST_SRC вручную"
    return 1
  fi
  if nginx -t 2>/dev/null; then
    systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || nginx -s reload 2>/dev/null || true
    echo "[OK] nginx обновлён: $dest"
  else
    echo "[WARN] nginx -t failed — проверь конфиг вручную"
    nginx -t || true
  fi
}

if [ "$(id -u)" -eq 0 ] && [ -f "$VHOST_SRC" ]; then
  apply_nginx || true
else
  echo "[>>] Для nginx нужен root. Выполни:"
  echo "  sudo cp $VHOST_SRC /etc/nginx/sites-available/5mb2.ru"
  echo "  sudo ln -sfn /etc/nginx/sites-available/5mb2.ru /etc/nginx/sites-enabled/5mb2.ru"
  echo "  sudo nginx -t && sudo systemctl reload nginx"
fi

# 5) Сброс кэша WP
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true

echo "============================================"
echo "  mu-plugin: $MU/ai-helper-chat-widget.php"
echo "  JS local:  $MU/ai-helper-widget.js"
echo "  Проверь:   http://5mb2.ru/wp-content/mu-plugins/ai-helper-widget.js"
echo "  API:       http://5mb2.ru/api/status"
echo "============================================"
echo "Открой http://5mb2.ru/ → кнопка «Чат» справа внизу (Ctrl+F5)."
