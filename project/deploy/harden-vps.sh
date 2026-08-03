#!/usr/bin/env bash
# Закрывает порты, поднимает compose на 127.0.0.1, пароль панели + WATCHDOG_TOKEN.
#
#   sudo bash /opt/ai-helper/project/deploy/harden-vps.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"
COMPOSE=(docker compose --env-file "$ENV_FILE" -f "${SCRIPT_DIR}/docker-compose.prod.yml")

[ "$(id -u)" -eq 0 ] || { echo "[ERR] sudo bash $0"; exit 1; }

touch "$ENV_FILE"
set_kv() {
  local k="$1" v="$2"
  if grep -q "^${k}=" "$ENV_FILE" 2>/dev/null; then
    TMP=$(mktemp)
    grep -v "^${k}=" "$ENV_FILE" >"$TMP" || true
    echo "${k}=${v}" >>"$TMP"
    mv "$TMP" "$ENV_FILE"
  else
    echo "${k}=${v}" >>"$ENV_FILE"
  fi
}

# Пароль панели задаёт владелец — не генерируем «тихий» пароль.
if ! grep -q '^PANEL_PASSWORD=.\+' "$ENV_FILE" 2>/dev/null; then
  if [ -n "${PANEL_PASSWORD_INIT:-}" ]; then
    set_kv PANEL_PASSWORD "$PANEL_PASSWORD_INIT"
    echo "[SECURITY] PANEL_PASSWORD задан из PANEL_PASSWORD_INIT"
  elif grep -q '^ALLOW_AUTO_PANEL_PASSWORD=1' "$ENV_FILE" 2>/dev/null; then
    PW=$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
    set_kv PANEL_PASSWORD "$PW"
    echo "[SECURITY] PANEL_PASSWORD авто (ALLOW_AUTO_PANEL_PASSWORD=1): ${PW}"
  else
    echo "[ERR] PANEL_PASSWORD не задан."
    echo "      Задай свой пароль и повтори:"
    echo "      sudo bash ${SCRIPT_DIR}/reset-panel-password.sh 'твой_пароль'"
    echo "      или: PANEL_PASSWORD_INIT='твой_пароль' sudo -E bash $0"
    exit 1
  fi
fi
# Явно выключаем автогенерацию в приложении
grep -q '^ALLOW_AUTO_PANEL_PASSWORD=' "$ENV_FILE" || echo 'ALLOW_AUTO_PANEL_PASSWORD=0' >> "$ENV_FILE"

if ! grep -q '^SECRET_KEY=.\+' "$ENV_FILE" || grep -q 'dev-insecure-change-me' "$ENV_FILE"; then
  set_kv SECRET_KEY "$(openssl rand -hex 32)"
  echo "[SECURITY] SECRET_KEY обновлён"
fi

if ! grep -q '^WATCHDOG_TOKEN=.\+' "$ENV_FILE" 2>/dev/null; then
  set_kv WATCHDOG_TOKEN "$(openssl rand -hex 24)"
  echo "[SECURITY] WATCHDOG_TOKEN задан (для cron)"
fi

grep -q '^PANEL_BASE_PATH=' "$ENV_FILE" || echo 'PANEL_BASE_PATH=/console' >> "$ENV_FILE"
grep -q '^ENABLE_STREAMLIT=' "$ENV_FILE" || echo 'ENABLE_STREAMLIT=0' >> "$ENV_FILE"
grep -q '^ALLOW_OPEN_PANEL=' "$ENV_FILE" || echo 'ALLOW_OPEN_PANEL=0' >> "$ENV_FILE"
grep -q '^MYSQL_ROOT_PASSWORD=.\+' "$ENV_FILE" || set_kv MYSQL_ROOT_PASSWORD "root_change_me"
grep -q '^MYSQL_PASSWORD=.\+' "$ENV_FILE" || set_kv MYSQL_PASSWORD "wp_change_me"
grep -q '^MYSQL_DATABASE=' "$ENV_FILE" || echo 'MYSQL_DATABASE=wordpress' >> "$ENV_FILE"
grep -q '^MYSQL_USER=' "$ENV_FILE" || echo 'MYSQL_USER=wp' >> "$ENV_FILE"

if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH || ufw allow 22/tcp || true
  ufw allow 80/tcp || true
  ufw allow 443/tcp || true
  for p in 3000 8501 8502 3306 9000 11434; do
    ufw delete allow ${p}/tcp 2>/dev/null || true
    ufw deny ${p}/tcp 2>/dev/null || true
  done
  ufw --force enable || true
  echo "[OK] UFW: allow 22/80/443, deny docker ports"
fi

cd "$SCRIPT_DIR"
"${COMPOSE[@]}" up -d --force-recreate app web php mysql ollama

echo ""
echo "=== Проверка bind (должно быть 127.0.0.1, не 0.0.0.0) ==="
ss -lntp | grep -E ':(3000|8501|8502|3306|9000|11434)\s' || echo "(нет слушателей — странно)"
echo ""
echo "Снаружи эти порты должны быть ЗАКРЫТЫ. Если ss показывает 0.0.0.0 — compose не тот."
echo "Панель: https://neobrain.site/console/login/"
echo "Пароль: grep PANEL_PASSWORD $ENV_FILE"
