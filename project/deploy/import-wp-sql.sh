#!/bin/bash
# Быстрый импорт SQL в MySQL контейнер (если панель недоступна)
set -e
SQL_FILE="${1:?Usage: $0 dump.sql [database]}"
DB="${2:-wordpress}"
ENV_FILE="${ENV_FILE:-/opt/ai-helper/project/.env}"

if [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  set -a; source "$ENV_FILE"; set +a
fi

USER="${MYSQL_USER:-wp}"
PASS="${MYSQL_PASSWORD:-}"
echo "[>>] Import $SQL_FILE → $DB (user=$USER)"
docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" "$DB" < "$SQL_FILE"
echo "[OK] Done"
