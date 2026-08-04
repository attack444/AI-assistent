#!/usr/bin/env bash
# Вешает студию SUNDUK на домен (по умолчанию 5mb2.ru).
# ВАЖНО: запускать только когда SEO-контент 5MB2 уже перенесён на NeoBrain,
# иначе WordPress на 5mb2.ru будет перекрыт.
#
#   SUNDUK_DOMAIN=5mb2.ru sudo bash project/deploy/enable-sunduk.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOMAIN="${SUNDUK_DOMAIN:-5mb2.ru}"
SITE_NAME="${SITE_NAME:-sunduk}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
SITE_ROOT="$SITES_DIR/$SITE_NAME"
EMAIL="${CERTBOT_EMAIL:-hello@${DOMAIN}}"
CONF="/etc/nginx/sites-available/sunduk-studio.conf"
IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "==> SUNDUK → https://${DOMAIN}/  (webroot ${SITE_ROOT})"
echo "    IP: ${IP}"
echo ""
echo "[!!] Это заменит текущий vhost ${DOMAIN}, если он указывает на WordPress."
echo "     Продолжайте только после переноса SEO. Ctrl+C чтобы отменить."
sleep 4

bash "$SCRIPT_DIR/create-sunduk-site.sh"

A_MAIN=$(dig +short @8.8.8.8 "$DOMAIN" A 2>/dev/null | head -1 || true)
if [ -z "$A_MAIN" ]; then
  echo "[ERR] Нет A у $DOMAIN"
  exit 1
fi

cat > "$CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${SITE_ROOT};
    index index.html;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff2)\$ {
        expires 7d;
        add_header Cache-Control "public";
        try_files \$uri =404;
    }
}
EOF

ln -sfn "$CONF" /etc/nginx/sites-enabled/sunduk-studio.conf
nginx -t
systemctl reload nginx

if [ "${SKIP_CERTBOT:-0}" != "1" ]; then
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect || {
    echo "[!!] Certbot не прошёл — HTTP уже отдаёт студию. Повторите certbot позже."
  }
fi

echo "[OK] https://${DOMAIN}/ → SUNDUK"
