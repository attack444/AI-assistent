#!/usr/bin/env bash
# Чинит панель на https://neobrain.site/console/ (редирект-петля / 404).
# 1) Пересобирает Next с basePath=/console
# 2) Обновляет nginx vhost NeoBrain под /console/
#
#   cd /opt/ai-helper/project/deploy
#   sudo bash fix-panel-console.sh
set -euo pipefail

DOMAIN="${NEOBRAIN_DOMAIN:-neobrain.site}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"
COMPOSE="${SCRIPT_DIR}/docker-compose.prod.yml"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] Нужен root: sudo bash $0"; exit 1; }

touch "$ENV_FILE"
if grep -q '^PANEL_BASE_PATH=' "$ENV_FILE" 2>/dev/null; then
  sed -i 's|^PANEL_BASE_PATH=.*|PANEL_BASE_PATH=/console|' "$ENV_FILE"
else
  echo 'PANEL_BASE_PATH=/console' >> "$ENV_FILE"
fi

echo "==> Rebuild web with PANEL_BASE_PATH=/console (build-arg)"
cd "$SCRIPT_DIR"
ENV_FILE="${SCRIPT_DIR}/../.env"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE" build \
  --build-arg PANEL_BASE_PATH=/console \
  web
docker compose --env-file "$ENV_FILE" -f "$COMPOSE" up -d --force-recreate web

echo "==> Wait Next"
for i in $(seq 1 30); do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 2 http://127.0.0.1:3000/console/ || true)
  if [ "$code" = "200" ]; then
    echo "[OK] Next отдаёт /console/ → $code"
    break
  fi
  sleep 1
done

# Обновить nginx кусок панели (через fix-neobrain-vhost, без повторного certbot если уже есть)
export NEOBRAIN_DOMAIN="$DOMAIN"
export SKIP_CERTBOT="${SKIP_CERTBOT:-1}"
bash "$SCRIPT_DIR/fix-neobrain-vhost.sh"

echo ""
echo "Проверка:"
echo "  curl -sI https://${DOMAIN}/console/ | head"
echo "  curl -sk https://${DOMAIN}/console/ | grep -o 'NeoBrain' | head -1"
code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 "https://${DOMAIN}/console/" || echo ERR)
echo "  HTTPS /console/ → HTTP $code (ожидаем 200)"
