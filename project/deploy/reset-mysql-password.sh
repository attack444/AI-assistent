#!/bin/bash
# Сброс пароля MySQL user wp (и при необходимости root) по значениям из .env
# Нужен при ошибке: 1045 Access denied for user 'wp'@'...' (using password: YES)
set -e

ENV_FILE="${ENV_FILE:-/opt/ai-helper/project/.env}"
if [ ! -f "$ENV_FILE" ]; then
  echo "[!!] Нет $ENV_FILE"
  exit 1
fi

# shellcheck disable=SC1090
set -a
# strip CR from Windows-edited .env
source <(sed 's/\r$//' "$ENV_FILE")
set +a

DB="${MYSQL_DATABASE:-wordpress}"
USER="${MYSQL_USER:-wp}"
PASS="${MYSQL_PASSWORD:-}"
ROOT_PASS="${MYSQL_ROOT_PASSWORD:-}"

if [ -z "$PASS" ]; then
  echo "[!!] MYSQL_PASSWORD пустой в $ENV_FILE"
  echo "    Задай латиницей, например: MYSQL_PASSWORD=wp_change_me"
  exit 1
fi

if [ -z "$ROOT_PASS" ]; then
  echo "[!!] MYSQL_ROOT_PASSWORD пустой в $ENV_FILE"
  exit 1
fi

# Cyrillic in password works via SQL, but prefer ASCII in .env for shells/scp
if printf '%s' "$PASS" | grep -q '[^ -~]'; then
  echo "[!!] MYSQL_PASSWORD содержит кириллицу — лучше латиница."
  echo "    Сейчас всё равно попробую сбросить по .env…"
fi

echo "[>>] Проверяю контейнер ai-helper-mysql..."
if ! docker ps --format '{{.Names}}' | grep -qx 'ai-helper-mysql'; then
  echo "[!!] Контейнер ai-helper-mysql не запущен"
  echo "    cd /opt/ai-helper/project/deploy && docker compose -f docker-compose.prod.yml up -d mysql"
  exit 1
fi

# Escape single quotes for SQL: ' → ''
sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}
PASS_SQL=$(sql_escape "$PASS")
USER_SQL=$(sql_escape "$USER")
DB_SQL=$(sql_escape "$DB")

echo "[>>] ALTER USER '$USER'@'%' … (пароль из .env)"
# Try root with .env password; if that fails, try common leftovers
run_root() {
  docker exec -i ai-helper-mysql mysql -uroot -p"$1" -e "$2" 2>/dev/null
}

SQL="
CREATE DATABASE IF NOT EXISTS \`$DB_SQL\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$USER_SQL'@'%' IDENTIFIED BY '$PASS_SQL';
ALTER USER '$USER_SQL'@'%' IDENTIFIED BY '$PASS_SQL';
CREATE USER IF NOT EXISTS '$USER_SQL'@'localhost' IDENTIFIED BY '$PASS_SQL';
ALTER USER '$USER_SQL'@'localhost' IDENTIFIED BY '$PASS_SQL';
GRANT ALL PRIVILEGES ON \`$DB_SQL\`.* TO '$USER_SQL'@'%';
GRANT ALL PRIVILEGES ON \`$DB_SQL\`.* TO '$USER_SQL'@'localhost';
FLUSH PRIVILEGES;
"

ok=0
for candidate in "$ROOT_PASS" "root_change_me" "смени_root_пароль"; do
  if [ -z "$candidate" ]; then
    continue
  fi
  if run_root "$candidate" "$SQL"; then
    echo "[OK] Пароль пользователя $USER обновлён через root"
    ok=1
    # If root from .env didn't work but a fallback did, sync root too
    if [ "$candidate" != "$ROOT_PASS" ]; then
      ROOT_SQL=$(sql_escape "$ROOT_PASS")
      run_root "$candidate" "ALTER USER 'root'@'%' IDENTIFIED BY '$ROOT_SQL'; ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_SQL'; FLUSH PRIVILEGES;" \
        && echo "[OK] MYSQL_ROOT_PASSWORD тоже синхронизирован с .env" \
        || echo "[!!] root из .env не совпал со старым — пароль wp уже сброшен, root оставь как был или поправь .env"
    fi
    break
  fi
done

if [ "$ok" -ne 1 ]; then
  echo "[!!] Не удалось войти root-ом. Попробуй вручную:"
  echo "  docker exec -it ai-helper-mysql mysql -uroot -p"
  echo "  Затем:"
  echo "  ALTER USER '$USER'@'%' IDENTIFIED BY 'ТВОЙ_ПАРОЛЬ_ИЗ_ENV';"
  echo "  FLUSH PRIVILEGES;"
  exit 1
fi

echo "[>>] Проверка входа $USER…"
if docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" -e "SELECT 1 AS ok;" "$DB" >/dev/null; then
  echo "[OK] $USER / $DB — вход работает"
else
  echo "[!!] Вход $USER всё ещё не работает — смотри пароль в .env и перезапусти app:"
  echo "  cd /opt/ai-helper/project/deploy && docker compose -f docker-compose.prod.yml up -d --force-recreate app"
  exit 1
fi

echo "[>>] Перезапуск API (подхватит MYSQL_PASSWORD)…"
cd /opt/ai-helper/project/deploy 2>/dev/null && \
  docker compose -f docker-compose.prod.yml up -d --force-recreate app || \
  docker restart ai-helper-app || true

echo ""
echo "Готово. В панели: «Проверить MySQL», потом импорт SQL."
echo "В wp-config DB_PASSWORD должен быть тот же, что MYSQL_PASSWORD в .env."
