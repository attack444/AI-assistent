#!/usr/bin/env bash
# Чинит NeoBrain vhost: отдельный SSL + панель на /console/
#
#   sudo bash /opt/ai-helper/project/deploy/fix-neobrain-vhost.sh
#   SKIP_CERTBOT=1 sudo bash ...   # только nginx
set -euo pipefail

DOMAIN="${NEOBRAIN_DOMAIN:-neobrain.site}"
PANEL_DOMAIN="${NEOBRAIN_PANEL_DOMAIN:-panel.${DOMAIN}}"
SITE_NAME="${SITE_NAME:-ai}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
WEBROOT="${SITES_DIR}/${SITE_NAME}"
EMAIL="${CERTBOT_EMAIL:-admin@${DOMAIN}}"
CONF="/etc/nginx/sites-available/neobrain-site.conf"
PANEL_CONF="/etc/nginx/sites-available/neobrain-panel.conf"
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
SKIP_CERTBOT="${SKIP_CERTBOT:-0}"
TMP_PANEL=$(mktemp)

cleanup() { rm -f "$TMP_PANEL"; }
trap cleanup EXIT

[ "$(id -u)" -eq 0 ] || { echo "[ERR] Нужен root: sudo bash $0"; exit 1; }
[ -d "$WEBROOT" ] || { echo "[ERR] Нет $WEBROOT — сначала: bash create-ai-site.sh"; exit 1; }

echo "$DOMAIN" > "$WEBROOT/.ai-helper-domain"
rm -f /etc/nginx/sites-enabled/ai-helper-ai.conf 2>/dev/null || true

PANEL_CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 http://127.0.0.1:3000/console/ || true)
ROOT_CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 http://127.0.0.1:3000/overview || true)
if [ "$PANEL_CODE" = "200" ]; then
  PANEL_MODE="basepath"
  echo "[OK] Next basePath=/console"
elif [ "$ROOT_CODE" = "200" ]; then
  PANEL_MODE="strip"
  echo "[!!] Next без basePath — strip-режим (потом fix-panel-console.sh)"
else
  PANEL_MODE="basepath"
  echo "[!!] Next молчит — пишем basepath nginx"
fi

HAS_CERT=0
if [ -f "${CERT_DIR}/fullchain.pem" ] && [ -f "${CERT_DIR}/privkey.pem" ]; then
  HAS_CERT=1
fi

if [ "$PANEL_MODE" = "basepath" ]; then
  cat > "$TMP_PANEL" <<'LOC'
    location = /console {
        return 302 /console/;
    }
    location /console/ {
        proxy_pass         http://127.0.0.1:3000/console/;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   Upgrade           $http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
LOC
else
  cat > "$TMP_PANEL" <<'LOC'
    location = /console {
        return 302 /console/;
    }
    location /console/ {
        proxy_pass         http://127.0.0.1:3000/;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   Upgrade           $http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
    location /_next/ {
        proxy_pass         http://127.0.0.1:3000/_next/;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_read_timeout 600s;
    }
    location ~ ^/(login|overview|settings|seo|health|feedback|sites|files|chat)(/|$) {
        proxy_pass         http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   Upgrade           $http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
LOC
fi

emit_inner() {
  cat <<EOF
    server_name ${DOMAIN} www.${DOMAIN};
    root ${WEBROOT};
    index index.html index.htm;
    client_max_body_size 200M;

    location /api/ {
        proxy_pass         http://127.0.0.1:8502/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Connection        "";
        proxy_read_timeout 600s;
        proxy_buffering    off;
        chunked_transfer_encoding on;
    }

EOF
  cat "$TMP_PANEL"
  cat <<EOF

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
EOF
}

{
  echo "# NeoBrain → ${DOMAIN} (panel mode=${PANEL_MODE})"
  if [ "$HAS_CERT" -eq 1 ]; then
    cat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
EOF
    emit_inner
    cat <<EOF
    ssl_certificate     ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}
EOF
  else
    cat <<EOF
server {
    listen 80;
    listen [::]:80;
EOF
    emit_inner
    echo "}"
  fi
} > "$CONF"

ln -sf "$CONF" /etc/nginx/sites-enabled/neobrain-site.conf

PANEL_A=$(dig +short @8.8.8.8 "$PANEL_DOMAIN" A 2>/dev/null | head -1 || true)
if [ -n "$PANEL_A" ]; then
  cat > "$PANEL_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${PANEL_DOMAIN};
    client_max_body_size 200M;
    location /api/ {
        proxy_pass         http://127.0.0.1:8502/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 600s;
        proxy_buffering    off;
    }
    location = / {
        return 302 /console/;
    }
    location /console/ {
        proxy_pass         http://127.0.0.1:3000/console/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Upgrade           \$http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
    location /api/ {
        proxy_pass         http://127.0.0.1:8502/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 600s;
        proxy_buffering    off;
    }
}
EOF
  ln -sf "$PANEL_CONF" /etc/nginx/sites-enabled/neobrain-panel.conf
else
  echo "[INFO] panel.* DNS нет — панель на /console/"
  rm -f /etc/nginx/sites-enabled/neobrain-panel.conf 2>/dev/null || true
fi

for f in /etc/nginx/sites-enabled/*; do
  [ -e "$f" ] || continue
  base=$(basename "$f")
  case "$base" in
    neobrain-site.conf|neobrain-panel.conf) continue ;;
  esac
  if grep -qE "server_name[[:space:]]+.*${DOMAIN}" "$f" 2>/dev/null; then
    echo "[!!] конфликт server_name ${DOMAIN} в $f — отключаю"
    rm -f "$f"
  fi
done

nginx -t
systemctl reload nginx
echo "[OK] nginx reload (cert=$HAS_CERT mode=$PANEL_MODE)"

if [ "$SKIP_CERTBOT" != "1" ] && [ "$HAS_CERT" -eq 0 ]; then
  if ! command -v certbot &>/dev/null; then
    apt-get update -q
    apt-get install -y -q certbot python3-certbot-nginx
  fi
  echo "==> SSL ${DOMAIN}"
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
    --email "$EMAIL" --redirect \
    || certbot certonly --webroot -w "$WEBROOT" -d "$DOMAIN" -d "www.$DOMAIN" \
         --non-interactive --agree-tos --email "$EMAIL"
  SKIP_CERTBOT=1 bash "$0"
  exit 0
fi

echo ""
echo "============================================"
echo "  Сайт:   https://${DOMAIN}/"
echo "  Панель: https://${DOMAIN}/console/   (${PANEL_MODE})"
echo "============================================"
if [ "$PANEL_MODE" = "strip" ]; then
  echo "[NEXT] sudo bash $(dirname "$0")/fix-panel-console.sh"
fi
echo "  curl -skI https://${DOMAIN}/console/ | head -n 8"
