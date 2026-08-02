#!/bin/bash
# Обновление AI Helper на VPS + печать адресов панели
set -e

REPO_DIR="${REPO_DIR:-/opt/ai-helper}"
cd "$REPO_DIR"

echo "[>>] git pull..."
git fetch origin main || true
git pull origin main || git pull

REV=$(git rev-parse --short HEAD 2>/dev/null || echo unknown)
echo "[OK] commit: $REV"

cd "$REPO_DIR/project/deploy"
ENV_FILE="$REPO_DIR/project/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "[!!] Нет .env — копирую шаблон"
  cp .env.example "$ENV_FILE"
fi

# Секреты: пустые / change_me_* → криптостойкие значения
bash "$REPO_DIR/project/deploy/ensure-secrets.sh" "$ENV_FILE"
ln -sfn "$ENV_FILE" "$REPO_DIR/project/deploy/.env"

# Heal bare API keys: a lone "gsk_..." / "sk-..." line → bash "command not found"
# (часто ключ вставили без GROQ_API_KEY= / OPENAI_API_KEY=)
if grep -qE '^[[:space:]]*gsk_[A-Za-z0-9]' "$ENV_FILE" 2>/dev/null; then
  echo "[>>] Чиню голый gsk_... в .env (строка без GROQ_API_KEY=)…"
  if grep -qE '^[[:space:]]*GROQ_API_KEY=' "$ENV_FILE"; then
    sed -i -E 's/^[[:space:]]*(gsk_[A-Za-z0-9_-]+)/# (moved) \1/' "$ENV_FILE"
  else
    sed -i -E '0,/^[[:space:]]*(gsk_[A-Za-z0-9_-]+)/s//GROQ_API_KEY=\1/' "$ENV_FILE"
  fi
fi
if grep -qE '^[[:space:]]*sk-[A-Za-z0-9]' "$ENV_FILE" 2>/dev/null; then
  echo "[>>] Чиню голый sk-... в .env…"
  if grep -qE '^[[:space:]]*(OPENAI_API_KEY|DEEPSEEK_API_KEY)=' "$ENV_FILE"; then
    sed -i -E 's/^[[:space:]]*(sk-[A-Za-z0-9_-]+)/# (moved) \1/' "$ENV_FILE"
  else
    sed -i -E '0,/^[[:space:]]*(sk-[A-Za-z0-9_-]+)/s//DEEPSEEK_API_KEY=\1/' "$ENV_FILE"
  fi
fi

# Warn about Cyrillic MySQL passwords (pymysql latin-1 crash)
if grep -E '^MYSQL_(PASSWORD|ROOT_PASSWORD)=.*[^ -~]' "$ENV_FILE" >/dev/null 2>&1; then
  echo ""
  echo "[!!] В .env кириллица в MYSQL_PASSWORD / MYSQL_ROOT_PASSWORD."
  echo "    Раньше из-за этого был latin-1 crash. Код теперь терпит UTF-8,"
  echo "    но лучше сменить пароли на латиницу (и в MySQL тоже)."
  echo ""
fi

mkdir -p /var/ai-helper/sites

echo "[>>] Docker rebuild (app + web)..."
# Force recreate so api.py / Next panel definitely pick up the new commit
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml build app web
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml up -d --force-recreate app web
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml up -d --build

# Права: иначе nginx даёт 403 на /sites/...
if [ -x "$REPO_DIR/project/deploy/fix-sites-403.sh" ]; then
  bash "$REPO_DIR/project/deploy/fix-sites-403.sh" || true
else
  chmod 755 /var/ai-helper /var/ai-helper/sites 2>/dev/null || true
  find /var/ai-helper/sites -type d -exec chmod 755 {} \; 2>/dev/null || true
  find /var/ai-helper/sites -type f -exec chmod 644 {} \; 2>/dev/null || true
fi

if command -v nginx &>/dev/null; then
  cp nginx.conf /etc/nginx/sites-available/ai-helper
  ln -sf /etc/nginx/sites-available/ai-helper /etc/nginx/sites-enabled/ai-helper
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx
  echo "[OK] Nginx обновлён"
fi

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo ""
echo "============================================"
echo "  ИНТЕРФЕЙС СЕРВЕРА:"
echo "  http://${IP}/"
echo "  commit: ${REV}"
echo "============================================"
echo "  Сайт:   http://${IP}/sites/mysite/"
echo "  Если 403: bash project/deploy/fix-sites-403.sh"
echo "============================================"
echo "  Файлы:  http://${IP}/files"
echo "  Сайты:  http://${IP}/sites"
echo "  Чат:    http://${IP}/chat"
echo "============================================"
