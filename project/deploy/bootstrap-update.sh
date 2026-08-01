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

BRANCH="${BRANCH:-main}"

cd "$REPO"
echo "[>>] git pull ($BRANCH)…"
git fetch origin "$BRANCH" || git fetch origin || true
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH" 2>/dev/null || true
git pull origin "$BRANCH" || git pull || true

if [ -x "$REPO/project/deploy/update.sh" ]; then
  bash "$REPO/project/deploy/update.sh"
else
  cd "$REPO/project/deploy"
  docker compose -f docker-compose.prod.yml build app web
  docker compose -f docker-compose.prod.yml up -d --force-recreate app web
fi

# Обновить публичную витрину ai (index + widget), не трогая 5mb2
if [ -x "$REPO/project/deploy/create-ai-site.sh" ]; then
  echo "[>>] Обновляю /sites/ai …"
  bash "$REPO/project/deploy/create-ai-site.sh" || true
fi

echo ""
echo "============================================"
echo "  REPO=$REPO  BRANCH=$BRANCH"
echo "  Панель: http://$(curl -s --max-time 3 ifconfig.me)/"
echo "  Витрина: http://$(curl -s --max-time 3 ifconfig.me)/sites/ai/"
echo "  API version: $(curl -s --max-time 5 http://127.0.0.1:8502/status | python3 -c 'import sys,json;print(json.load(sys.stdin).get("version","?"))' 2>/dev/null || echo '?')"
echo "============================================"
