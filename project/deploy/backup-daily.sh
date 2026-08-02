#!/usr/bin/env bash
# Бэкап сайтов + Docker MySQL (если контейнер есть). Хранит последние 7 копий.
set -euo pipefail

DEST="${BACKUP_DIR:-/var/backups/ai-helper}"
SITES="${SITES_DIR:-/var/ai-helper/sites}"
STAMP=$(date +%Y%m%d-%H%M)
KEEP="${BACKUP_KEEP:-7}"
mkdir -p "$DEST/$STAMP"

echo "[backup] $STAMP → $DEST/$STAMP"

if [ -d "$SITES" ]; then
  tar -czf "$DEST/$STAMP/sites.tgz" -C "$(dirname "$SITES")" "$(basename "$SITES")" 2>/dev/null \
    || tar -czf "$DEST/$STAMP/sites.tgz" -C "$SITES" .
  echo "[OK] sites"
fi

# MySQL из типичного compose-контейнера
MYSQL_C=$(docker ps --format '{{.Names}}' 2>/dev/null | grep -E 'mysql|mariadb' | head -1 || true)
if [ -n "$MYSQL_C" ]; then
  ROOT_PW="${MYSQL_ROOT_PASSWORD:-}"
  if [ -z "$ROOT_PW" ] && [ -f /opt/ai-helper/project/.env ]; then
    # shellcheck disable=SC1091
    set -a; . /opt/ai-helper/project/.env; set +a
    ROOT_PW="${MYSQL_ROOT_PASSWORD:-}"
  fi
  if [ -n "$ROOT_PW" ]; then
    docker exec "$MYSQL_C" mysqldump -uroot -p"$ROOT_PW" --all-databases --single-transaction \
      > "$DEST/$STAMP/mysql-all.sql" 2>/dev/null \
      && gzip -f "$DEST/$STAMP/mysql-all.sql" \
      && echo "[OK] mysql" \
      || echo "[!!] mysqldump failed"
  else
    echo "[!!] MYSQL_ROOT_PASSWORD не задан — пропуск БД"
  fi
else
  echo "[skip] mysql container"
fi

# ротация
mapfile -t OLD < <(ls -1dt "$DEST"/20* 2>/dev/null | tail -n +$((KEEP + 1)) || true)
for d in "${OLD[@]:-}"; do
  [ -n "$d" ] && rm -rf "$d" && echo "[rm] $d"
done

du -sh "$DEST/$STAMP" || true
echo "[done]"
