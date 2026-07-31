#!/bin/bash
# Обновление AI Helper на VPS + печать адресов панели
set -e

REPO_DIR="${REPO_DIR:-/opt/ai-helper}"
cd "$REPO_DIR"

echo "[>>] git pull..."
git pull origin main || git pull

cd "$REPO_DIR/project/deploy"
ENV_FILE="$REPO_DIR/project/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "[!!] Нет .env — копирую шаблон"
  cp .env.example "$ENV_FILE"
fi

# PANEL_PASSWORD
if ! grep -q '^PANEL_PASSWORD=.\+' "$ENV_FILE" 2>/dev/null; then
  echo ""
  echo "[!!] PANEL_PASSWORD не задан."
  if [ -t 0 ]; then
    read -r -p "Введи пароль панели (будет записан в .env): " PANEL_PW
    if [ -n "$PANEL_PW" ]; then
      if grep -q '^PANEL_PASSWORD=' "$ENV_FILE"; then
        sed -i "s|^PANEL_PASSWORD=.*|PANEL_PASSWORD=${PANEL_PW}|" "$ENV_FILE"
      else
        echo "PANEL_PASSWORD=${PANEL_PW}" >> "$ENV_FILE"
      fi
      echo "[OK] Пароль записан в $ENV_FILE"
    else
      echo "[!!] Пропущено. Задай позже: nano $ENV_FILE"
    fi
  else
    echo "[!!] Задай пароль: nano $ENV_FILE  →  PANEL_PASSWORD=..."
  fi
fi

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
echo "  ИНТЕРФЕЙС СЕРВЕРА:"
echo "  http://${IP}/"
echo "============================================"
echo "  Дальше: открой Сайты → «Перенос с хостинга»"
echo "  http://${IP}/sites"
echo "============================================"
echo "  Файлы:  http://${IP}/files"
echo "  Чат:    http://${IP}/chat"
echo "  Пароль: PANEL_PASSWORD в project/.env"
echo "============================================"
