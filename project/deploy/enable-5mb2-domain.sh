#!/bin/bash
# Включить 5mb2.ru. По умолчанию HTTP-only (без битого HTTPS).
# SSL: передай --ssl ТОЛЬКО если сертификат уже есть на этом VPS
#   или готов выпускать Let's Encrypt после рабочего HTTP.
#
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/enable-5mb2-domain.sh | bash
#   bash enable-5mb2-domain.sh --ssl
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
DOMAIN="${DOMAIN:-5mb2.ru}"
SSL_EMAIL="${SSL_EMAIL:-slavasundukov887@gmail.com}"
WANT_SSL=0
for a in "$@"; do [[ "$a" == "--ssl" ]] && WANT_SSL=1; done

ROOT="${SITES_DIR:-/var/ai-helper/sites}/$SITE_NAME"
VHOST_SRC="$ROOT/nginx.vhost.conf"
CONF_DST="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"

echo "[>>] Site root: $ROOT"
[ -d "$ROOT" ] || { echo "[!!] Нет $ROOT"; exit 1; }
[ -f "$ROOT/index.php" ] || [ -f "$ROOT/wp-config.php" ] || { echo "[!!] Нет WordPress в $ROOT"; exit 1; }

printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
docker restart ai-helper-php >/dev/null 2>&1 || true

echo "[>>] HTTP vhost (без редиректа на https)…"
cat > "$VHOST_SRC" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${ROOT};
    index index.php index.html index.htm;
    client_max_body_size 200M;
    location / { try_files \$uri \$uri/ /index.php?\$args; }
    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_read_timeout 300s;
    }
    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff2?)\$ {
        expires 7d; access_log off; try_files \$uri =404;
    }
}
EOF

cp "$VHOST_SRC" "$CONF_DST"
ln -sf "$CONF_DST" "/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf"
chmod 755 /var/ai-helper /var/ai-helper/sites "$ROOT" 2>/dev/null || true
find "$ROOT" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$ROOT" -type f -exec chmod 644 {} \; 2>/dev/null || true
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

nginx -t
systemctl reload nginx
echo "[OK] http://${DOMAIN}/"

if [ "$WANT_SSL" -eq 1 ]; then
  if ! command -v certbot >/dev/null 2>&1; then
    apt-get update -q
    apt-get install -y -q certbot python3-certbot-nginx
  fi
  echo "[>>] SSL Let's Encrypt…"
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
    --email "$SSL_EMAIL" --redirect \
    || echo "[!!] SSL не выписался — сайт остаётся на http:// (это нормально)"
else
  echo "[info] SSL пропущен (по умолчанию). Сертификат с reg.ru ≠ на VPS."
  echo "       Когда HTTP ок — либо --ssl, либо свои .pem в nginx."
fi

echo ""
echo "[info] Репозиторий AI Helper:"
find /root /opt /home /var -name 'docker-compose.prod.yml' 2>/dev/null | head -5 || echo "  (не найден — панель уже в Docker)"

echo ""
echo "============================================"
echo "  Проверь: http://${DOMAIN}/"
echo "  Панель:  http://$(curl -s --max-time 3 ifconfig.me)/"
echo "============================================"
