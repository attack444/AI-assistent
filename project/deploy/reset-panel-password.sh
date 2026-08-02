#!/usr/bin/env bash
# Сброс пароля панели NeoBrain → записывает в project/.env и перезапускает app.
#
#   sudo bash /opt/ai-helper/project/deploy/reset-panel-password.sh
#   sudo bash .../reset-panel-password.sh 'мойПароль'
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"
COMPOSE="${SCRIPT_DIR}/docker-compose.prod.yml"
NEW_PW="${1:-}"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] sudo bash $0"; exit 1; }

if [ -z "$NEW_PW" ]; then
  NEW_PW=$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
fi

touch "$ENV_FILE"
if grep -q '^PANEL_PASSWORD=' "$ENV_FILE" 2>/dev/null; then
  # не используем sed с спецсимволами пароля — переписываем файл
  TMP=$(mktemp)
  grep -v '^PANEL_PASSWORD=' "$ENV_FILE" >"$TMP" || true
  echo "PANEL_PASSWORD=${NEW_PW}" >>"$TMP"
  mv "$TMP" "$ENV_FILE"
else
  echo "PANEL_PASSWORD=${NEW_PW}" >>"$ENV_FILE"
fi

# убрать автосген внутри volume, чтобы не путать
docker exec ai-helper-app rm -f /root/.ai-helper/generated_panel_password.txt 2>/dev/null || true

cd "$SCRIPT_DIR"
docker compose -f "$COMPOSE" up -d --force-recreate app

echo ""
echo "============================================"
echo "  Новый пароль панели:"
echo "  ${NEW_PW}"
echo "============================================"
echo "Вход: https://neobrain.site/console/login/"
echo "Проверка длины в контейнере:"
docker exec ai-helper-app printenv PANEL_PASSWORD | wc -c
docker logs ai-helper-app 2>&1 | grep -E 'PANEL_PASSWORD|SECURITY' | tail -5 || true
