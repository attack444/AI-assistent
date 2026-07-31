#!/bin/bash
# Импорт SQL в MySQL контейнер
# Usage: $0 dump.sql [database]
set -euo pipefail
SQL_FILE="${1:?Usage: $0 dump.sql [database]}"
DB="${2:-wordpress}"
ENV_FILE="${ENV_FILE:-/opt/ai-helper/project/.env}"
REPO_DIR="${REPO_DIR:-/opt/ai-helper}"

if [ ! -f "$SQL_FILE" ]; then
  echo "[!!] Нет файла: $SQL_FILE"
  exit 1
fi

SIZE=$(stat -c%s "$SQL_FILE" 2>/dev/null || stat -f%z "$SQL_FILE")
echo "[>>] File: $SQL_FILE ($SIZE bytes)"
if [ "$SIZE" -lt 2048 ]; then
  echo "[!!] Файл слишком маленький — это не полный дамп сайта"
  exit 1
fi

# shellcheck source=env-get.sh
source "$REPO_DIR/project/deploy/env-get.sh"

USER="$(env_get MYSQL_USER || true)"; USER="${USER:-wp}"
PASS="$(env_get MYSQL_PASSWORD || true)"
ROOT_PASS="$(env_get MYSQL_ROOT_PASSWORD || true)"
if [ -n "${2:-}" ]; then
  DB="$2"
else
  _db="$(env_get MYSQL_DATABASE || true)"; DB="${_db:-wordpress}"
fi

if [ -z "$PASS" ]; then
  echo "[!!] MYSQL_PASSWORD пустой в $ENV_FILE"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx ai-helper-mysql; then
  echo "[!!] Контейнер ai-helper-mysql не запущен"
  exit 1
fi

TMP=$(mktemp /tmp/wp-import-XXXXXX.sql)
trap 'rm -f "$TMP"' EXIT

echo "[>>] Готовлю дамп (DEFINER/USE → $DB)…"
sed -E \
  -e 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`//Ig' \
  -e "s/DEFINER[ ]*=[ ]*'[^']+'@'[^']+'//Ig" \
  -e "s/USE[[:space:]]+\`[^\`]+\`/USE \`$DB\`/Ig" \
  -e "s/USE[[:space:]]+[a-zA-Z0-9_]+/USE \`$DB\`/Ig" \
  "$SQL_FILE" > "$TMP"

if head -c 4000 "$TMP" | grep -qi 'information_schema' \
  && ! head -c 200000 "$TMP" | grep -Eqi 'wp0w_|CREATE TABLE.*`wp_'; then
  echo "[!!] Похоже на дамп information_schema, не сайт"
  exit 1
fi

echo "[>>] Import → DB=$DB user=$USER"
if docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" --ssl-mode=DISABLED --default-character-set=utf8mb4 "$DB" < "$TMP"; then
  echo "[OK] Import via $USER"
elif [ -n "$ROOT_PASS" ] && docker exec -i ai-helper-mysql mysql -uroot -p"$ROOT_PASS" --ssl-mode=DISABLED --default-character-set=utf8mb4 "$DB" < "$TMP"; then
  echo "[OK] Import via root"
else
  echo "[!!] Import failed"
  exit 1
fi

COUNT=$(docker exec -i ai-helper-mysql mysql -N -u"$USER" -p"$PASS" --ssl-mode=DISABLED "$DB" \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" 2>/dev/null || echo 0)
echo "[OK] Tables in $DB: $COUNT"
if [ "${COUNT:-0}" -lt 5 ]; then
  echo "[!!] Мало таблиц — проверь что в дампе есть wp0w_options / wp_options"
  exit 1
fi
