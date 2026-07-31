#!/bin/bash
# Срочно: 5mb2.ru по HTTP без сломанного HTTPS-редиректа.
# (443 на VPS сейчас рвёт TLS — из-за редиректа сайт «не открывается»)
#
# Под root:
#   curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/fix-5mb2-http.sh | bash
set -euo pipefail

SITE_NAME="${SITE_NAME:-5mb2}"
DOMAIN="${DOMAIN:-5mb2.ru}"
ROOT="${SITES_DIR:-/var/ai-helper/sites}/$SITE_NAME"
CONF="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"
ENABLED="/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf"

[ -d "$ROOT" ] || { echo "[!!] Нет $ROOT"; exit 1; }

# Убрать битые ssl-серверы certbot для этого домена (если есть)
for f in /etc/nginx/sites-enabled/*5mb2* /etc/nginx/sites-enabled/*${DOMAIN}* \
         /etc/nginx/sites-available/*5mb2* /etc/nginx/sites-available/*${DOMAIN}*; do
  [ -e "$f" ] || continue
  # не трогаем основной ai-helper default — только доменные
  case "$f" in
    *ai-helper) ;;
    *) echo "[>>] disable broken ssl conf: $f"; rm -f "$f" 2>/dev/null || true ;;
  esac
done

# Wordfence
printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
docker restart ai-helper-php >/dev/null 2>&1 || true

echo "[>>] Пишу HTTP-only vhost (без редиректа на https)…"
cat > "$CONF" <<EOF
# AI Helper — ${SITE_NAME} → ${DOMAIN} (HTTP; SSL добавим своим сертификатом)
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${ROOT};
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

ln -sf "$CONF" "$ENABLED"
cp "$CONF" "$ROOT/nginx.vhost.conf"

chmod 755 /var/ai-helper /var/ai-helper/sites "$ROOT" 2>/dev/null || true
find "$ROOT" -maxdepth 2 -type d -exec chmod 755 {} \; 2>/dev/null || true
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

nginx -t
systemctl reload nginx

echo "$DOMAIN" > "$ROOT/.ai-helper-domain"

# Где лежит git-репо (для панели)
echo ""
echo "[info] Поиск репозитория AI Helper…"
find /root /opt /home /var -name 'docker-compose.prod.yml' 2>/dev/null | head -8 || true

IP=$(curl -s --max-time 3 ifconfig.me || echo "80.78.248.195")
echo ""
echo "============================================"
echo "  Открой:  http://${DOMAIN}/"
echo "  Панель:  http://${IP}/"
echo "  IP:      http://${IP}/sites/${SITE_NAME}/index.php"
echo "============================================"
echo "  HTTPS пока НЕ включаем — на 443 TLS ломается."
echo "  Когда будут файлы сертификата с reg.ru (или Let's Encrypt):"
echo "  положим их и включим listen 443 — отдельно."
echo "============================================"
