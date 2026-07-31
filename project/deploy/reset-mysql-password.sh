#!/bin/bash
# Сброс пароля MySQL user wp под .env
# Usage:
#   bash reset-mysql-password.sh           # ALTER USER через root
#   bash reset-mysql-password.sh --reinit  # УДАЛИТ том mysql и создаст заново (пустая БД)
set -e

REINIT=0
if [ "${1:-}" = "--reinit" ] || [ "${1:-}" = "-f" ]; then
  REINIT=1
fi

ENV_FILE="${ENV_FILE:-/opt/ai-helper/project/.env}"
COMPOSE_DIR="${COMPOSE_DIR:-/opt/ai-helper/project/deploy}"
COMPOSE_FILE="$COMPOSE_DIR/docker-compose.prod.yml"

if [ ! -f "$ENV_FILE" ]; then
  echo "[!!] Нет $ENV_FILE"
  exit 1
fi

# shellcheck disable=SC1090
set -a
source <(sed 's/\r$//' "$ENV_FILE")
set +a

DB="${MYSQL_DATABASE:-wordpress}"
USER="${MYSQL_USER:-wp}"
PASS="${MYSQL_PASSWORD:-}"
ROOT_PASS="${MYSQL_ROOT_PASSWORD:-}"

if [ -z "$PASS" ] || [ -z "$ROOT_PASS" ]; then
  echo "[!!] Задай MYSQL_PASSWORD и MYSQL_ROOT_PASSWORD в $ENV_FILE (латиница!)"
  echo "    Пример:"
  echo "    MYSQL_ROOT_PASSWORD=RootPass123"
  echo "    MYSQL_PASSWORD=WpPass123"
  exit 1
fi

if printf '%s' "$PASS$ROOT_PASS" | grep -q '[^ -~]'; then
  echo "[!!] В паролях кириллица. Меняю .env на латиницу автоматически…"
  NEW_ROOT="RootPass$(date +%s | tail -c 5)"
  NEW_WP="WpPass$(date +%s | tail -c 5)"
  sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=${NEW_ROOT}|" "$ENV_FILE"
  sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=${NEW_WP}|" "$ENV_FILE"
  if ! grep -q '^MYSQL_ROOT_PASSWORD=' "$ENV_FILE"; then
    echo "MYSQL_ROOT_PASSWORD=${NEW_ROOT}" >> "$ENV_FILE"
  fi
  if ! grep -q '^MYSQL_PASSWORD=' "$ENV_FILE"; then
    echo "MYSQL_PASSWORD=${NEW_WP}" >> "$ENV_FILE"
  fi
  ROOT_PASS="$NEW_ROOT"
  PASS="$NEW_WP"
  echo "[OK] Новые пароли записаны в .env:"
  echo "    MYSQL_ROOT_PASSWORD=$ROOT_PASS"
  echo "    MYSQL_PASSWORD=$PASS"
  REINIT=1
fi

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

if [ "$REINIT" -eq 1 ]; then
  echo "[!!] REINIT: удаляю том MySQL (база будет пустой — потом импорт SQL)."
  cd "$COMPOSE_DIR"
  docker compose -f docker-compose.prod.yml stop mysql app || true
  docker compose -f docker-compose.prod.yml rm -f mysql || true
  # volume name varies — wipe all project mysql volumes
  for vol in $(docker volume ls -q | grep -E 'mysql_data|deploy_mysql' || true); do
    echo "[>>] docker volume rm $vol"
    docker volume rm "$vol" 2>/dev/null || true
  done
  docker compose -f docker-compose.prod.yml up -d mysql
  echo "[>>] Жду готовности MySQL…"
  for i in $(seq 1 60); do
    if docker exec ai-helper-mysql mysqladmin ping -h127.0.0.1 -uroot -p"$ROOT_PASS" --silent 2>/dev/null; then
      echo "[OK] MySQL готов"
      break
    fi
    sleep 2
    if [ "$i" -eq 60 ]; then
      echo "[!!] MySQL не поднялся за 120с"
      docker logs ai-helper-mysql --tail 40 || true
      exit 1
    fi
  done
fi

echo "[>>] Синхронизирую пользователя $USER…"
PASS_SQL=$(sql_escape "$PASS")
USER_SQL=$(sql_escape "$USER")
DB_SQL=$(sql_escape "$DB")
ROOT_SQL=$(sql_escape "$ROOT_PASS")

SQL="
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$USER_SQL'@'%' IDENTIFIED BY '$PASS_SQL';
ALTER USER '$USER_SQL'@'%' IDENTIFIED BY '$PASS_SQL';
CREATE USER IF NOT EXISTS '$USER_SQL'@'localhost' IDENTIFIED BY '$PASS_SQL';
ALTER USER '$USER_SQL'@'localhost' IDENTIFIED BY '$PASS_SQL';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$USER_SQL'@'%';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$USER_SQL'@'localhost';
ALTER USER 'root'@'%' IDENTIFIED BY '$ROOT_SQL';
ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_SQL';
FLUSH PRIVILEGES;
"

ok=0
for candidate in "$ROOT_PASS" "root_change_me" "смени_root_пароль" "strong_root_pass" "root"; do
  if [ -z "$candidate" ]; then continue; fi
  if docker exec -i ai-helper-mysql mysql -uroot -p"$candidate" -e "$SQL" 2>/dev/null; then
    echo "[OK] Пароли синхронизированы (root candidate matched)"
    ok=1
    break
  fi
done

if [ "$ok" -ne 1 ]; then
  echo "[!!] root не подошёл. Принудительный reinit:"
  echo "    bash $0 --reinit"
  exit 1
fi

echo "[>>] Проверка $USER…"
if docker exec -i ai-helper-mysql mysql -u"$USER" -p"$PASS" -e "SELECT 1 AS ok;" "$DB" >/dev/null; then
  echo "[OK] $USER / $DB — вход работает"
else
  echo "[!!] Вход $USER не работает"
  exit 1
fi

echo "[>>] Перезапуск app с новым .env…"
cd "$COMPOSE_DIR"
docker compose -f docker-compose.prod.yml up -d --force-recreate app

echo ""
echo "============================================"
echo "  MySQL OK"
echo "  MYSQL_PASSWORD=$PASS"
echo "  В панели: Настроить WP → пароль тот же →"
echo "  Сохранить wp-config → Проверить MySQL → импорт SQL"
echo "============================================"
