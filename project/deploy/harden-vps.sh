#!/usr/bin/env bash
# Закрывает лишние порты UFW, поднимает compose только на 127.0.0.1,
# напоминает про PANEL_PASSWORD.
#
#   sudo bash /opt/ai-helper/project/deploy/harden-vps.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] sudo bash $0"; exit 1; }

touch "$ENV_FILE"
if ! grep -q '^PANEL_PASSWORD=.\+' "$ENV_FILE" 2>/dev/null; then
  PW=$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
  if grep -q '^PANEL_PASSWORD=' "$ENV_FILE"; then
    sed -i "s|^PANEL_PASSWORD=.*|PANEL_PASSWORD=${PW}|" "$ENV_FILE"
  else
    echo "PANEL_PASSWORD=${PW}" >> "$ENV_FILE"
  fi
  echo "[SECURITY] Записан PANEL_PASSWORD в .env"
  echo "           Пароль панели: ${PW}"
  echo "           Сохрани его — вход: https://neobrain.site/console/login/"
fi

if ! grep -q '^SECRET_KEY=.\+' "$ENV_FILE" || grep -q 'dev-insecure-change-me' "$ENV_FILE"; then
  SK=$(openssl rand -hex 32)
  if grep -q '^SECRET_KEY=' "$ENV_FILE"; then
    sed -i "s|^SECRET_KEY=.*|SECRET_KEY=${SK}|" "$ENV_FILE"
  else
    echo "SECRET_KEY=${SK}" >> "$ENV_FILE"
  fi
  echo "[SECURITY] Обновлён SECRET_KEY"
fi

grep -q '^PANEL_BASE_PATH=' "$ENV_FILE" || echo 'PANEL_BASE_PATH=/console' >> "$ENV_FILE"
grep -q '^ENABLE_STREAMLIT=' "$ENV_FILE" || echo 'ENABLE_STREAMLIT=0' >> "$ENV_FILE"
grep -q '^ALLOW_OPEN_PANEL=' "$ENV_FILE" || echo 'ALLOW_OPEN_PANEL=0' >> "$ENV_FILE"

if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH || ufw allow 22/tcp || true
  ufw allow 80/tcp || true
  ufw allow 443/tcp || true
  # закрыть прямые docker-порты если когда-то открывали
  for p in 3000 8501 8502 3306 9000 11434; do
    ufw delete allow ${p}/tcp 2>/dev/null || true
  done
  ufw --force enable || true
  echo "[OK] UFW: 22/80/443"
  ufw status || true
fi

cd "$SCRIPT_DIR"
docker compose -f docker-compose.prod.yml up -d --force-recreate app web php mysql ollama 2>/dev/null \
  || docker compose -f docker-compose.prod.yml up -d --force-recreate

echo ""
echo "Проверка: порты не должны слушать 0.0.0.0:8502/3000/3306"
ss -lntp | grep -E ':(3000|8501|8502|3306|9000|11434)\s' || echo "(ss пусто или ок)"
echo "API через nginx: curl -sk https://neobrain.site/api/status | head -c 120"
echo "Панель: https://neobrain.site/console/login/"
