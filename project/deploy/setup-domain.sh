#!/bin/bash
# Привязка домена к сайту на VPS (+ опционально Let's Encrypt SSL)
# Пример: bash setup-domain.sh mysite 5mb2.ru --ssl
set -e

SITE_NAME="${1:?Usage: $0 <site_name> <domain> [--ssl]}"
DOMAIN="${2:?Usage: $0 <site_name> <domain> [--ssl]}"
WANT_SSL=0
[[ "${3:-}" == "--ssl" ]] && WANT_SSL=1

DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN%%/*}"
DOMAIN="$(echo "$DOMAIN" | tr '[:upper:]' '[:lower:]')"

SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
SITE_ROOT="$SITES_DIR/$SITE_NAME"
REPO="${REPO_DIR:-/opt/ai-helper}"

if [ ! -d "$SITE_ROOT" ]; then
  echo "[ERR] Нет папки сайта: $SITE_ROOT"
  echo "Имя сайта в панели должно совпадать (например mysite)."
  exit 1
fi

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "[>>] Сайт: $SITE_NAME"
echo "[>>] Домен: $DOMAIN (www.$DOMAIN)"
echo "[>>] Root: $SITE_ROOT"
echo "[>>] IP сервера: $IP"
echo ""
echo "DNS у регистратора должен быть:"
echo "  A    @     $IP"
echo "  A    www   $IP"
echo ""

# Write domain marker
echo "$DOMAIN" > "$SITE_ROOT/.ai-helper-domain"

CONF_SRC="$SITE_ROOT/nginx.vhost.conf"
CONF_DST="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"

# Generate / refresh vhost (WordPress-friendly)
cat > "$CONF_SRC" <<EOF
# AI Helper site: ${SITE_NAME} → ${DOMAIN}
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${SITE_ROOT};
    index index.php index.html index.htm;
    client_max_body_size 200M;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_read_timeout 300s;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff2?)\$ {
        expires 7d;
        access_log off;
        try_files \$uri =404;
    }

    location ~* /(?:uploads|files)/.*\\.php\$ {
        deny all;
    }
}
EOF

cp "$CONF_SRC" "$CONF_DST"
ln -sf "$CONF_DST" "/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf"

# Permissions
chmod 755 /var/ai-helper "$SITES_DIR" 2>/dev/null || true
find "$SITE_ROOT" -type d -exec chmod 755 {} \;
find "$SITE_ROOT" -type f -exec chmod 644 {} \;
if id www-data &>/dev/null; then
  chown -R www-data:www-data "$SITE_ROOT" 2>/dev/null || true
fi

nginx -t
systemctl reload nginx
echo "[OK] HTTP: http://${DOMAIN}/"

if [ "$WANT_SSL" -eq 1 ]; then
  if ! command -v certbot &>/dev/null; then
    apt-get update -q
    apt-get install -y -q certbot python3-certbot-nginx
  fi
  echo "[>>] Выпускаю SSL (Let's Encrypt)…"
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
    --register-unsafely-without-email --redirect || \
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --agree-tos --redirect
  echo "[OK] HTTPS: https://${DOMAIN}/"
fi

echo ""
echo "============================================"
echo "  Сайт:    https://${DOMAIN}/  (после --ssl)"
echo "  Панель:  http://${IP}/"
echo "============================================"
echo "  Позже в WP (импорт БД) замени URL на:"
echo "  https://${DOMAIN}"
echo "============================================"
