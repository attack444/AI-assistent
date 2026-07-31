#!/bin/bash
# Обновление AI Helper на VPS + печать адресов панели
set -e

REPO_DIR="${REPO_DIR:-/opt/ai-helper}"
cd "$REPO_DIR"

echo "[>>] git pull..."
git pull

cd "$REPO_DIR/project/deploy"

if [ ! -f "$REPO_DIR/project/.env" ]; then
  echo "[!!] Нет .env — копирую шаблон"
  cp .env.example "$REPO_DIR/project/.env"
  echo "[!!] Заполни PANEL_PASSWORD и DEEPSEEK_API_KEY в $REPO_DIR/project/.env"
fi

# Создать папку сайтов
mkdir -p /var/ai-helper/sites

echo "[>>] Docker rebuild..."
docker compose -f docker-compose.prod.yml up -d --build

if command -v nginx &>/dev/null; then
  cp nginx.conf /etc/nginx/sites-available/ai-helper
  ln -sf /etc/nginx/sites-available/ai-helper /etc/nginx/sites-enabled/ai-helper
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx
  echo "[OK] Nginx обновлён"
fi

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo ""
echo "============================================"
echo "  ИНТЕРФЕЙС СЕРВЕРА (открой в браузере):"
echo "  http://${IP}/"
echo "============================================"
echo "  Файлы:  http://${IP}/files"
echo "  Сайты:  http://${IP}/sites"
echo "  Чат:    http://${IP}/chat"
echo "  API:    http://${IP}/api/status"
echo "============================================"
echo "  Пароль панели: PANEL_PASSWORD в project/.env"
echo "  Перенос сайта: deploy/MIGRATE_SITE.md"
echo "============================================"
