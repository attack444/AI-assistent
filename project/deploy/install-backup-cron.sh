#!/usr/bin/env bash
# Ежедневный бэкап сайтов + MySQL на второй диск (если смонтирован) или /var/backups/ai-helper.
#
#   sudo bash /opt/ai-helper/project/deploy/install-backup-cron.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_SH="${SCRIPT_DIR}/backup-daily.sh"
DEST="${BACKUP_DIR:-}"

if [ -z "$DEST" ]; then
  if [ -d /mnt/data ] && touch /mnt/data/.w 2>/dev/null; then
    rm -f /mnt/data/.w
    DEST=/mnt/data/ai-helper-backups
  elif [ -d /mnt/volume ] && touch /mnt/volume/.w 2>/dev/null; then
    rm -f /mnt/volume/.w
    DEST=/mnt/volume/ai-helper-backups
  else
    DEST=/var/backups/ai-helper
  fi
fi

mkdir -p "$DEST"
chmod +x "$BACKUP_SH"

if command -v crontab >/dev/null 2>&1; then
  TMP=$(mktemp)
  crontab -l 2>/dev/null | grep -v 'backup-daily.sh' >"$TMP" || true
  echo "25 3 * * * BACKUP_DIR=${DEST} bash ${BACKUP_SH} >> /var/log/ai-helper-backup.log 2>&1" >>"$TMP"
  crontab "$TMP"
  rm -f "$TMP"
  echo "[OK] cron: ежедневно 03:25 → ${DEST}"
else
  echo "[!!] crontab нет — добавь вручную:"
  echo "25 3 * * * BACKUP_DIR=${DEST} bash ${BACKUP_SH} >> /var/log/ai-helper-backup.log 2>&1"
fi

echo "Пробный прогон:"
echo "  sudo BACKUP_DIR=${DEST} bash ${BACKUP_SH}"
