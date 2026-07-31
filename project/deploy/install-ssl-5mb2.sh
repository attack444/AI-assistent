#!/bin/bash
# HTTPS для 5mb2.ru — только после рабочего HTTP.
# Под root:
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/install-ssl-5mb2.sh | bash
set -euo pipefail

DOMAIN="${DOMAIN:-5mb2.ru}"
SSL_EMAIL="${SSL_EMAIL:-slavasundukov887@gmail.com}"
SITE_NAME="${SITE_NAME:-5mb2}"

echo "[>>] Проверка HTTP…"
code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "http://${DOMAIN}/" || echo 000)
if [ "$code" != "200" ] && [ "$code" != "301" ] && [ "$code" != "302" ]; then
  echo "[!!] http://${DOMAIN}/ отвечает $code — сначала:"
  echo "    curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/fix-5mb2-http.sh | bash"
  exit 1
fi
echo "[OK] HTTP $code"

# Убрать битый listen 443, если есть, но оставить HTTP vhost
if [ ! -f "/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf" ] && [ ! -f "/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf" ]; then
  echo "[>>] Нет vhost — ставлю HTTP…"
  curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/fix-5mb2-http.sh | bash
fi

if ! command -v certbot >/dev/null 2>&1; then
  apt-get update -q
  apt-get install -y -q certbot python3-certbot-nginx
fi

echo "[>>] Let's Encrypt для ${DOMAIN}…"
certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
  --email "$SSL_EMAIL" --redirect

nginx -t && systemctl reload nginx

echo "[>>] Проверка HTTPS…"
sleep 2
hcode=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://${DOMAIN}/" || echo 000)
echo "[info] https://${DOMAIN}/ → $hcode"

# Вернуть WP URL на https через API панели (если доступна)
API="http://127.0.0.1:8502"
if curl -sS --max-time 3 "$API/status" >/dev/null 2>&1; then
  ENV_FILE=""
  for c in /opt/ai-helper/project/.env /root/AI-assistent/project/.env /root/ai-helper/project/.env; do
    [ -f "$c" ] && ENV_FILE="$c" && break
  done
  # PANEL_PASSWORD из docker env
  PW=$(docker exec ai-helper-app printenv PANEL_PASSWORD 2>/dev/null || true)
  if [ -z "$PW" ] && [ -n "$ENV_FILE" ]; then
    PW=$(grep -E '^PANEL_PASSWORD=' "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
  fi
  if [ -n "$PW" ]; then
    TOK=$(curl -sS -X POST "$API/auth/login" -H 'Content-Type: application/json' \
      -d "{\"password\":\"$PW\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("token",""))' 2>/dev/null || true)
    if [ -n "$TOK" ]; then
      echo "[>>] WP siteurl → https://${DOMAIN}"
      curl -sS -X POST "$API/wp/replace-url" -H "Authorization: Bearer $TOK" -H 'Content-Type: application/json' \
        -d "{\"name\":\"${SITE_NAME}\",\"old_url\":\"http://${DOMAIN}\",\"new_url\":\"https://${DOMAIN}\"}" || true
      echo
    fi
  else
    echo "[!!] Не смог сменить siteurl через API — в панели: WP → заменить URL http→https"
  fi
fi

echo ""
echo "============================================"
echo "  https://${DOMAIN}/"
echo "  Если 000/ошибка — проверь AAAA (IPv6) у reg.ru:"
echo "  лишний AAAA на старый хостинг мешает. Оставь A → этот VPS."
echo "============================================"
