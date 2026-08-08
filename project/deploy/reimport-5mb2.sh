#!/bin/bash
# Переимпорт большого дампа 5mb2 через mysql в Docker
set -euo pipefail
REPO="${REPO_DIR:-/opt/ai-helper}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$REPO/project/.env"
DB=wordpress

# Не source весь .env — GROQ gsk_... / OpenAI sk-... ломают bash ("command not found: gsk_...")
ENV_GET="$SCRIPT_DIR/env-get.sh"
[ -f "$ENV_GET" ] || ENV_GET="$REPO/project/deploy/env-get.sh"
if [ ! -f "$ENV_GET" ]; then
  echo "[!!] Нет env-get.sh — сначала: bash $REPO/project/deploy/update.sh"
  exit 1
fi
# shellcheck source=env-get.sh
source "$ENV_GET"

USER="$(env_get MYSQL_USER || true)"; USER="${USER:-wp}"
PASS="$(env_get MYSQL_PASSWORD || true)"
ROOT_PASS="$(env_get MYSQL_ROOT_PASSWORD || true)"
DB="$(env_get MYSQL_DATABASE || true)"; DB="${DB:-wordpress}"

if [ -z "$PASS" ] || [ -z "$ROOT_PASS" ]; then
  echo "[!!] В $ENV_FILE нужны MYSQL_PASSWORD и MYSQL_ROOT_PASSWORD"
  exit 1
fi

DUMP="${1:-}"
if [ -z "$DUMP" ]; then
  DUMP=$(find /var/ai-helper/sites/.uploads -type f -name '*.sql' -size +10M -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1{print $2}')
fi
if [ -z "${DUMP:-}" ] || [ ! -f "$DUMP" ]; then
  echo "[!!] Нет дампа >10MB. Укажи: $0 /path/dump.sql"
  exit 1
fi
echo "[>>] DUMP=$DUMP ($(stat -c%s "$DUMP") bytes)"
echo "[>>] MySQL user=$USER db=$DB (пароли из .env, API-ключи не трогаем)"

echo "[>>] Очищаю таблицы $DB…"
docker exec -i ai-helper-mysql mysql -uroot -p"$ROOT_PASS" --ssl-mode=DISABLED -e "
SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE \`$DB\`;
SET @t = NULL;
SELECT GROUP_CONCAT(CONCAT('\`', table_name, '\`')) INTO @t FROM information_schema.tables WHERE table_schema='$DB';
SET @sql = IF(@t IS NULL, 'SELECT 1', CONCAT('DROP TABLE IF EXISTS ', @t));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET FOREIGN_KEY_CHECKS=1;
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$USER'@'%';
FLUSH PRIVILEGES;
"

TMP=$(mktemp /tmp/wpimp-XXXX.sql)
trap 'rm -f "$TMP"' EXIT
echo "[>>] Чищу DEFINER / USE…"
sed -E \
  -e 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`//Ig' \
  -e "s/DEFINER[ ]*=[ ]*'[^']+'@'[^']+'//Ig" \
  -e "s/USE[[:space:]]+\`[^\`]+\`/USE \`$DB\`/Ig" \
  -e "s/USE[[:space:]]+[a-zA-Z0-9_]+/USE \`$DB\`/Ig" \
  "$DUMP" > "$TMP"

echo "[>>] Import (это может занять несколько минут)…"
docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" --ssl-mode=DISABLED --default-character-set=utf8mb4 "$DB" < "$TMP"

COUNT=$(docker exec -i ai-helper-mysql mysql -N -u"$USER" -p"$PASS" --ssl-mode=DISABLED "$DB" \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" 2>/dev/null || echo 0)
echo "[OK] Tables: $COUNT"

SITEURL=$(docker exec -i ai-helper-mysql mysql -N -u"$USER" -p"$PASS" --ssl-mode=DISABLED "$DB" \
  -e "SELECT option_value FROM wp0w_options WHERE option_name='siteurl' LIMIT 1;" 2>/dev/null || true)
echo "[info] siteurl=$SITEURL"

docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" --ssl-mode=DISABLED "$DB" -e "
UPDATE wp0w_options SET option_value='https://5mb2.ru' WHERE option_name IN ('siteurl','home');
" 2>/dev/null || true

printf 'auto_prepend_file =\n' > /var/ai-helper/sites/5mb2/.user.ini || true
docker restart ai-helper-php >/dev/null 2>&1 || true

echo "============================================"
echo "  Готово. Tables=$COUNT"
echo "  http://$(curl -s --max-time 3 ifconfig.me)/sites/5mb2/"
echo "  https://5mb2.ru/"
echo "============================================"
