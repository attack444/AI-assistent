#!/usr/bin/env bash
# Чинит ситуацию, когда https://neobrain.site отдаёт 5MB2 / чужой сертификат.
# Пишет отдельные nginx vhost для NeoBrain (сайт ai) и выпускает SSL.
#
#   sudo bash /opt/ai-helper/project/deploy/fix-neobrain-vhost.sh
set -euo pipefail

DOMAIN="${NEOBRAIN_DOMAIN:-neobrain.site}"
PANEL_DOMAIN="${NEOBRAIN_PANEL_DOMAIN:-panel.${DOMAIN}}"
SITE_NAME="${SITE_NAME:-ai}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
WEBROOT="${SITES_DIR}/${SITE_NAME}"
EMAIL="${CERTBOT_EMAIL:-admin@${DOMAIN}}"
CONF="/etc/nginx/sites-available/neobrain-site.conf"
PANEL_CONF="/etc/nginx/sites-available/neobrain-panel.conf"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] Нужен root: sudo bash $0"; exit 1; }
[ -d "$WEBROOT" ] || { echo "[ERR] Нет $WEBROOT — сначала: bash create-ai-site.sh"; exit 1; }

echo "$DOMAIN" > "$WEBROOT/.ai-helper-domain"

# Убрать старый catch-all конфликт: ai-helper-ai на чужом SSL не используем как default
rm -f /etc/nginx/sites-enabled/ai-helper-ai.conf 2>/dev/null || true

cat > "$CONF" <<EOF
# NeoBrain public site → ${DOMAIN}
server {
    listen 80;
    listen [::]:80;
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

    # HTTPS панели БЕЗ отдельного DNS — путь на том же домене
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
    location = /console {
        return 302 /console/;
    }

    location / {
        try_files \$uri \$uri/ /index.html;
    }
}
EOF
ln -sf "$CONF" /etc/nginx/sites-enabled/neobrain-site.conf

# Опциональный поддомен panel.* — только если DNS есть
PANEL_A=$(dig +short @8.8.8.8 "$PANEL_DOMAIN" A 2>/dev/null | head -1 || true)
if [ -n "$PANEL_A" ]; then
cat > "$PANEL_CONF" <<EOF
# NeoBrain panel → ${PANEL_DOMAIN}
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
        proxy_set_header   Connection        "";
        proxy_read_timeout 600s;
        proxy_buffering    off;
    }

    location / {
        proxy_pass         http://127.0.0.1:3000/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Upgrade           \$http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
}
EOF
ln -sf "$PANEL_CONF" /etc/nginx/sites-enabled/neobrain-panel.conf
else
  echo "[INFO] Поддомен ${PANEL_DOMAIN} не нужен: панель на https://${DOMAIN}/console/"
  rm -f /etc/nginx/sites-enabled/neobrain-panel.conf 2>/dev/null || true
fi

nginx -t
systemctl reload nginx
echo "[OK] HTTP: http://${DOMAIN}/  (должен быть NeoBrain, не 5MB2)"

if ! command -v certbot &>/dev/null; then
  apt-get update -q
  apt-get install -y -q certbot python3-certbot-nginx
fi

echo "==> SSL ${DOMAIN}"
certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos \
  --email "$EMAIL" --redirect \
  || certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --agree-tos --email "$EMAIL" --redirect

if [ -n "${PANEL_A:-}" ]; then
  echo "==> SSL ${PANEL_DOMAIN}"
  certbot --nginx -d "$PANEL_DOMAIN" --non-interactive --agree-tos \
    --email "$EMAIL" --redirect \
    || certbot --nginx -d "$PANEL_DOMAIN" --agree-tos --email "$EMAIL" --redirect
fi

nginx -t
systemctl reload nginx

echo ""
echo "============================================"
echo "  Сайт:   https://${DOMAIN}/"
echo "  Панель: https://${DOMAIN}/console/   ← без отдельного DNS"
echo "  Сертификат должен быть CN=${DOMAIN} (не 5mb2.ru)"
echo "============================================"
echo "Проверка:"
echo "  curl -sk https://${DOMAIN}/ | grep -o NeoBrain | head -1"
echo "  echo | openssl s_client -connect ${DOMAIN}:443 -servername ${DOMAIN} 2>/dev/null | openssl x509 -noout -subject"
