#!/bin/bash
# Бесплатная локальная модель (Ollama + qwen2.5:1.5b) на VPS.
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/install-free-llm.sh | bash
set -euo pipefail

REPO="${REPO_DIR:-}"
MODEL="${FREE_LLM_MODEL:-qwen2.5:1.5b}"
COMPOSE_DIR=""

find_compose() {
  local f
  f=$(find /root /opt /home /var -name 'docker-compose.prod.yml' 2>/dev/null | head -1 || true)
  if [ -n "$f" ]; then
    echo "$(cd "$(dirname "$f")" && pwd)"
    return 0
  fi
  return 1
}

if [ -n "${REPO:-}" ] && [ -f "$REPO/project/deploy/docker-compose.prod.yml" ]; then
  COMPOSE_DIR="$REPO/project/deploy"
elif COMPOSE_DIR=$(find_compose); then
  :
elif [ -f /opt/ai-helper/project/deploy/docker-compose.prod.yml ]; then
  COMPOSE_DIR=/opt/ai-helper/project/deploy
else
  echo "[!!] Не найден docker-compose.prod.yml — сначала bootstrap-update.sh"
  exit 1
fi

ENV_FILE="$(cd "$COMPOSE_DIR/.." && pwd)/.env"
touch "$ENV_FILE"

set_kv() {
  local k="$1" v="$2"
  if grep -q "^${k}=" "$ENV_FILE"; then
    sed -i "s|^${k}=.*|${k}=${v}|" "$ENV_FILE"
  else
    echo "${k}=${v}" >> "$ENV_FILE"
  fi
}

echo "[>>] Compose: $COMPOSE_DIR"
echo "[>>] Модель: $MODEL (бесплатно, на сервере)"

set_kv OLLAMA_HOST "http://ollama:11434"
set_kv FREE_LLM_MODEL "$MODEL"
set_kv LLM_PREFER_FREE "1"
set_kv FAST_LLM_MODEL "$MODEL"
# Не затираем DeepSeek — остаётся платным fallback

cd "$COMPOSE_DIR"
echo "[>>] Поднимаю Ollama…"
docker compose -f docker-compose.prod.yml up -d ollama

echo "[>>] Жду Ollama…"
for i in $(seq 1 60); do
  if docker exec ai-helper-ollama ollama list >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "[>>] Качаю $MODEL (это может занять несколько минут)…"
docker exec ai-helper-ollama ollama pull "$MODEL"

echo "[>>] Пересоздаю app с OLLAMA_HOST…"
docker compose -f docker-compose.prod.yml up -d --force-recreate app

sleep 3
echo "[>>] Проверка…"
docker exec ai-helper-ollama ollama list || true
curl -sS --max-time 5 http://127.0.0.1:8502/status | python3 -c 'import sys,json;d=json.load(sys.stdin);print("api",d.get("version"),"ollama",d.get("ollama"),"free",d.get("free_llm"),d.get("free_model"))' 2>/dev/null || true

echo ""
echo "============================================"
echo "  Бесплатная модель: $MODEL"
echo "  Приоритет: Ollama → DeepSeek (если ключ есть)"
echo "  Панель + витрина ai используют её автоматически"
echo "============================================"
echo "  Если мало RAM — поставь легче:"
echo "  FREE_LLM_MODEL=qwen2.5:0.5b bash $0"
echo "============================================"
