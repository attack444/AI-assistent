#!/bin/bash
# Найти или клонировать репозиторий и обновить панель (без жёсткого /opt/ai-helper).
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/bootstrap-update.sh | bash
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/attack444/AI-assistent.git}"
DEFAULT_DIR="/opt/ai-helper"

find_repo() {
  local f
  f=$(find /root /opt /home /var -name 'docker-compose.prod.yml' 2>/dev/null | head -1 || true)
  if [ -n "$f" ]; then
    # .../project/deploy/docker-compose.prod.yml → repo root
    cd "$(dirname "$f")/../.." && pwd
    return 0
  fi
  return 1
}

if [ -n "${REPO_DIR:-}" ] && [ -d "$REPO_DIR/.git" ]; then
  REPO="$REPO_DIR"
elif REPO=$(find_repo); then
  echo "[OK] Репозиторий: $REPO"
else
  echo "[>>] Репозитория нет — клонирую в $DEFAULT_DIR"
  mkdir -p "$(dirname "$DEFAULT_DIR")"
  if [ -d "$DEFAULT_DIR/.git" ]; then
    REPO="$DEFAULT_DIR"
  else
    git clone "$REPO_URL" "$DEFAULT_DIR"
    REPO="$DEFAULT_DIR"
  fi
fi

cd "$REPO"
echo "[>>] git pull…"
git fetch origin main || true
git checkout main 2>/dev/null || true
git pull origin main || git pull

if [ -x "$REPO/project/deploy/update.sh" ]; then
  bash "$REPO/project/deploy/update.sh"
else
  cd "$REPO/project/deploy"
  docker compose -f docker-compose.prod.yml build app web
  docker compose -f docker-compose.prod.yml up -d --force-recreate app web
fi

echo ""
echo "============================================"
echo "  REPO=$REPO"
echo "  Панель: http://$(curl -s --max-time 3 ifconfig.me)/"
echo "  API version: $(curl -s --max-time 5 http://127.0.0.1:8502/status | python3 -c 'import sys,json;print(json.load(sys.stdin).get("version","?"))' 2>/dev/null || echo '?')"
echo "============================================"
