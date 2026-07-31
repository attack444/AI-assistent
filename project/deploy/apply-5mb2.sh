#!/bin/bash
# Применение настроек 5mb2 на VPS (пароли НЕ хранятся в git — передай через env или site.env)
# Пример:
#   export MYSQL_PASSWORD='...' MYSQL_ROOT_PASSWORD='...' PANEL_PASSWORD='...' SSL_EMAIL='...'
#   bash /opt/ai-helper/project/deploy/apply-5mb2.sh
set -euo pipefail

REPO_DIR="${REPO_DIR:-/opt/ai-helper}"
DEPLOY="$REPO_DIR/project/deploy"
ENV_FILE="$REPO_DIR/project/.env"
SITE_ENV="$DEPLOY/site.env"

SITE_NAME="${SITE_NAME:-5mb2}"
DOMAIN="${DOMAIN:-5mb2.ru}"
OLD_URL="${OLD_URL:-https://5mb2.ru}"
NEW_URL="${NEW_URL:-https://5mb2.ru}"
ENABLE_SSL="${ENABLE_SSL:-1}"
SSL_EMAIL="${SSL_EMAIL:?Задай SSL_EMAIL=...}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:?Задай MYSQL_PASSWORD=...}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:?Задай MYSQL_ROOT_PASSWORD=...}"
PANEL_PASSWORD="${PANEL_PASSWORD:-$MYSQL_PASSWORD}"

echo "[>>] git update…"
bash "$DEPLOY/update.sh"

# Write project .env keys (keep other keys)
touch "$ENV_FILE"
set_kv() {
  local k="$1" v="$2"
  if grep -q "^${k}=" "$ENV_FILE"; then
    sed -i "s|^${k}=.*|${k}=${v}|" "$ENV_FILE"
  else
    echo "${k}=${v}" >> "$ENV_FILE"
  fi
}
set_kv PANEL_PASSWORD "$PANEL_PASSWORD"
set_kv MYSQL_DATABASE wordpress
set_kv MYSQL_USER wp
set_kv MYSQL_PASSWORD "$MYSQL_PASSWORD"
set_kv MYSQL_ROOT_PASSWORD "$MYSQL_ROOT_PASSWORD"

cat > "$SITE_ENV" <<EOF
SITE_NAME=${SITE_NAME}
DOMAIN=${DOMAIN}
OLD_URL=${OLD_URL}
NEW_URL=${NEW_URL}
SQL_FILE=
MYSQL_PASSWORD=${MYSQL_PASSWORD}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
ENABLE_SSL=${ENABLE_SSL}
SSL_EMAIL=${SSL_EMAIL}
EOF

echo "[>>] MySQL sync (без wipe, если root доступен)…"
bash "$DEPLOY/reset-mysql-password.sh" || {
  echo "[!!] Обычный reset не вышел — НЕ делаю --reinit автоматически (чтобы не стереть БД)."
  echo "    Если таблиц 0 и SQL ещё не залит — можно: bash $DEPLOY/reset-mysql-password.sh --reinit"
}

echo "[>>] Recreate app with new .env…"
cd "$DEPLOY"
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

sleep 3
API="http://127.0.0.1:8502"

echo "[>>] Flatten webroot 5mb2.ru → site root…"
curl -s -X POST "$API/sites/normalize" -H 'Content-Type: application/json' \
  -d "{\"name\":\"${SITE_NAME}\"}" || true
echo

# Wordfence auto_prepend from old hosting breaks PHP until cleared + php restart
if [ -f "/var/ai-helper/sites/${SITE_NAME}/.user.ini" ]; then
  echo "[>>] Clearing Wordfence auto_prepend in .user.ini"
  printf 'auto_prepend_file =\n' > "/var/ai-helper/sites/${SITE_NAME}/.user.ini"
fi
docker restart ai-helper-php >/dev/null 2>&1 || true
sleep 2

echo "[>>] wp-config + MySQL password…"
curl -s -X POST "$API/wp/config" -H 'Content-Type: application/json' \
  -d "{\"name\":\"${SITE_NAME}\",\"db_name\":\"wordpress\",\"db_user\":\"wp\",\"db_password\":\"${MYSQL_PASSWORD}\",\"db_host\":\"mysql\",\"root_password\":\"${MYSQL_ROOT_PASSWORD}\",\"table_prefix\":\"wp0w_\"}"
echo

echo "[>>] Domain + SSL…"
export CERTBOT_EMAIL="$SSL_EMAIL"
bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN" --ssl || \
  bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN"

bash "$DEPLOY/fix-sites-403.sh" || true

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || echo "80.78.248.195")
echo ""
echo "============================================"
echo "  Панель: http://${IP}/  (пароль PANEL_PASSWORD)"
echo "  Сайт:   http://${IP}/sites/${SITE_NAME}/"
echo "  Домен:  http://${DOMAIN}/ / https://${DOMAIN}/"
echo "============================================"
echo "  ВАЖНО: все залитые dump.sql были ПУСТЫЕ (~1KB)."
echo "  С старого хостинга выгрузи ПОЛНЫЙ дамп базы u3406909_default"
echo "  (phpMyAdmin → база → Export → SQL → данные+структура),"
echo "  залей в панели Шаг 2, потом URL:"
echo "  old=${OLD_URL}  new=${NEW_URL}"
echo "============================================"
