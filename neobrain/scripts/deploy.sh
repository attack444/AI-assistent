#!/usr/bin/env bash
# =============================================================================
#  Ручной деплой на VPS из Termius (запасной вариант к CI/CD).
#  ЧТО ДЕЛАТЬ: подключись к серверу по SSH и запусти этот скрипт из папки
#  проекта. Он обновит код, пересоберёт образы и применит миграции.
#
#  ПРЕДПОЛАГАЕТСЯ: на сервере уже есть git-репозиторий и файл .env (секреты).
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/neobrain}"   # где лежит проект на сервере
cd "$APP_DIR"

echo ">>> 1. Забираю свежий код"
git pull --ff-only

echo ">>> 2. Пересобираю и поднимаю контейнеры"
docker compose pull || true          # если используешь готовые образы из registry
docker compose up -d --build

echo ">>> 3. Применяю миграции БД"
docker compose exec -T web npx prisma migrate deploy

echo ">>> 4. Проверяю здоровье web"
sleep 3
docker compose exec -T web wget -qO- http://127.0.0.1:8080/health || {
  echo "!!! web не отвечает — смотри логи: docker compose logs web"; exit 1;
}

echo ">>> Деплой завершён успешно ✅"
