#!/usr/bin/env bash
# Включает рабочий HTTPS для 5mb2.ru (Let's Encrypt) и переводит WP на https://
#
# Сейчас: порт 443 на VPS принимает TCP, но рвёт TLS (нет нормального сертификата).
# AAAA (IPv6) у 5mb2.ru часто смотрит на СТАРЫЙ хостинг — лучше убрать AAAA у reg.ru
# или поставить AAAA на IPv6 этого VPS.
#
#   bash project/deploy/enable-https-5mb2.sh
#   SSL_EMAIL=you@gmail.com bash project/deploy/enable-https-5mb2.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOMAIN="${DOMAIN:-5mb2.ru}"
SITE_NAME="${SITE_NAME:-5mb2}"
SSL_EMAIL="${SSL_EMAIL:-${CERTBOT_EMAIL:-slavasundukov887@gmail.com}}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
HOME_HTTPS="https://${DOMAIN}"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] Запусти от root (sudo)"; exit 1; }
[ -d "$ROOT" ] || { echo "[ERR] Нет $ROOT"; exit 1; }

echo "======== 0) DNS ========"
VPS_IP=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
DNS_A=$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk '{print $1; exit}' || true)
DNS_AAAA=$(getent ahostsv6 "$DOMAIN" 2>/dev/null | awk '{print $1; exit}' || true)
echo "  VPS IPv4:  ${VPS_IP}"
echo "  DNS A:     ${DNS_A:-?}"
echo "  DNS AAAA:  ${DNS_AAAA:-(нет)}"
if [ -n "${DNS_A:-}" ] && [ -n "${VPS_IP:-}" ] && [ "$DNS_A" != "$VPS_IP" ]; then
  echo "[!!] A-запись не на этот VPS. Certbot не выпустит сертификат."
  echo "     У регистратора: A @ и www → ${VPS_IP}"
  exit 1
fi
if [ -n "${DNS_AAAA:-}" ]; then
  echo "[!!] Есть AAAA. Часть клиентов пойдёт на IPv6."
  echo "     Если IPv6 не этого VPS — удали AAAA у регистратора (reg.ru)."
fi

echo "======== 1) HTTP база ========"
bash "$SCRIPT_DIR/fix-5mb2-wp.sh"

# firewall
if command -v ufw >/dev/null 2>&1; then
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  echo "  ✓ ufw 80/443"
fi

code=$(curl -4 -sS -o /dev/null -w '%{http_code}' --max-time 20 "http://${DOMAIN}/" || echo 000)
if [ "$code" != "200" ] && [ "$code" != "301" ] && [ "$code" != "302" ]; then
  echo "[ERR] http://${DOMAIN}/ → $code — сначала почини HTTP"
  exit 1
fi
echo "  ✓ HTTP $code"

echo "======== 2) Certbot ========"
if ! command -v certbot >/dev/null 2>&1; then
  apt-get update -q
  apt-get install -y -q certbot python3-certbot-nginx
fi

# убрать битые 443-конфиги без валидных pem (кроме нашего vhost — certbot его дополнит)
CONF="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"
for f in /etc/nginx/sites-enabled/*; do
  [ -e "$f" ] || continue
  case "$f" in
    *ai-helper-${SITE_NAME}.conf) continue ;;
    *default*) continue ;;
  esac
  if grep -qE 'listen[[:space:]]+443|ssl_certificate' "$f" 2>/dev/null; then
    echo "  · отключаю чужой SSL vhost: $f"
    rm -f "$f"
  fi
done

# если уже есть сертификат — просто подключить
CERT_LIVE="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
if [ -f "$CERT_LIVE" ]; then
  echo "  · сертификат уже есть — certbot --nginx (reinstall/redirect)"
  certbot install --nginx -d "$DOMAIN" -d "www.$DOMAIN" --redirect --non-interactive 2>/dev/null \
    || certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
         --email "$SSL_EMAIL" --redirect --reinstall
else
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
    --email "$SSL_EMAIL" --redirect
fi

nginx -t
systemctl reload nginx

echo "======== 3) Проверка HTTPS ========"
sleep 2
hcode=$(curl -4 -sS -o /dev/null -w '%{http_code}' --max-time 25 "https://${DOMAIN}/" || echo 000)
echo "  https://${DOMAIN}/ → $hcode"
if [ "$hcode" = "000" ]; then
  echo "[ERR] HTTPS всё ещё не отвечает."
  echo "  Проверь: ss -lntp | grep ':443'"
  echo "  и AAAA у регистратора."
  exit 1
fi

echo "======== 4) WordPress URL → https ========"
WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-load.php" ] && WP="$cand" && break
done
[ -n "$WP" ] || WP="$ROOT"

if [ -f "$WP/wp-config.php" ]; then
  # раскомментировать / выставить WP_HOME / WP_SITEURL на https
  if grep -q "WP_HOME" "$WP/wp-config.php"; then
    sed -i -E "s|(define[[:space:]]*\([[:space:]]*['\"]WP_HOME['\"][[:space:]]*,[[:space:]]*)['\"][^'\"]*['\"]|\\1'${HOME_HTTPS}'|" "$WP/wp-config.php" || true
  fi
  if grep -q "WP_SITEURL" "$WP/wp-config.php"; then
    sed -i -E "s|(define[[:space:]]*\([[:space:]]*['\"]WP_SITEURL['\"][[:space:]]*,[[:space:]]*)['\"][^'\"]*['\"]|\\1'${HOME_HTTPS}'|" "$WP/wp-config.php" || true
  fi
fi

if command -v wp >/dev/null 2>&1; then
  wp option update home "$HOME_HTTPS" --path="$WP" --allow-root 2>/dev/null || true
  wp option update siteurl "$HOME_HTTPS" --path="$WP" --allow-root 2>/dev/null || true
else
  php -r "
  define('WP_USE_THEMES', false);
  require '${WP}/wp-load.php';
  update_option('home', '${HOME_HTTPS}');
  update_option('siteurl', '${HOME_HTTPS}');
  echo 'home=' . get_option('home') . PHP_EOL;
  " 2>/dev/null || true
fi

# API replace-url если панель жива
API="http://127.0.0.1:8502"
PW=$(docker exec ai-helper-app printenv PANEL_PASSWORD 2>/dev/null || true)
if [ -n "${PW:-}" ] && curl -sS --max-time 3 "$API/status" >/dev/null 2>&1; then
  TOK=$(curl -sS -X POST "$API/auth/login" -H 'Content-Type: application/json' \
    -d "{\"password\":\"${PW}\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("token",""))' 2>/dev/null || true)
  if [ -n "${TOK:-}" ]; then
    curl -sS -X POST "$API/wp/replace-url" -H "Authorization: Bearer $TOK" -H 'Content-Type: application/json' \
      -d "{\"name\":\"${SITE_NAME}\",\"old_url\":\"http://${DOMAIN}\",\"new_url\":\"${HOME_HTTPS}\"}" >/dev/null || true
    echo "  ✓ replace-url через API"
  fi
fi

docker restart ai-helper-php >/dev/null 2>&1 || true

echo ""
echo "============================================"
echo "  Сайт:    ${HOME_HTTPS}/"
echo "  Админка: ${HOME_HTTPS}/wp-admin/"
echo ""
echo "  Пароль админки (если забыл) — без почты:"
echo "    bash ${SCRIPT_DIR}/reset-wp-admin-password.sh"
echo "============================================"
