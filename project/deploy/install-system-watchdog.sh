#!/usr/bin/env bash
# Ставит cron watchdog каждые 2 минуты + один прогон сейчас.
#   sudo bash project/deploy/install-system-watchdog.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WATCH="$SCRIPT_DIR/system-watchdog.sh"
chmod +x "$WATCH" "$SCRIPT_DIR/install-system-watchdog.sh" 2>/dev/null || true

LOG_DIR="${WATCHDOG_LOG_DIR:-/var/log/ai-helper}"
mkdir -p "$LOG_DIR" 2>/dev/null || LOG_DIR="/tmp"
LOG="$LOG_DIR/watchdog.log"

# check + safe docker restart; при сбое — DeepSeek (cooldown внутри system_health)
LINE="*/2 * * * * WATCHDOG_BASE_URL=https://127.0.0.1 WATCHDOG_HOST_HEADER=5mb2.ru WATCHDOG_ASK_ON_FAIL=1 LLM_PREFER_FREE=0 bash $WATCH >>$LOG 2>&1"

if command -v crontab >/dev/null 2>&1; then
  TMP="$(mktemp)"
  crontab -l 2>/dev/null | grep -v 'system-watchdog.sh' >"$TMP" || true
  echo "$LINE" >>"$TMP"
  crontab "$TMP"
  rm -f "$TMP"
  echo "[OK] cron: каждые 2 мин (safe fix + DeepSeek при сбое, cooldown 8 мин)"
else
  echo "[!!] crontab нет — добавь вручную:"
  echo "$LINE"
fi

echo "[>>] Первый прогон…"
bash "$WATCH" || true
echo "[OK] лог: $LOG"
echo "Панель → раздел «Здоровье»"
