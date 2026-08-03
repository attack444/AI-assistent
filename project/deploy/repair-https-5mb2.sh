#!/usr/bin/env bash
# Чинит HTTPS, когда сертификат УЖЕ есть, но сайт по https не открывается.
#
# Типичная причина на этом VPS:
#   - файлы Let's Encrypt лежат в /etc/letsencrypt/live/5mb2.ru/
#   - а nginx-vhost после fix-5mb2-wp.sh остался ТОЛЬКО на :80
#   - порт 443 что-то принимает, но TLS handshake = Connection reset
#
#   sudo bash project/deploy/repair-https-5mb2.sh
set -euo pipefail

DOMAIN="${DOMAIN:-5mb2.ru}"
SITE_NAME="${SITE_NAME:-5mb2}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
CONF="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"
ENABLED="/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf"
HOME_HTTPS="https://${DOMAIN}"

[ "$(id -u)" -eq 0 ] || { echo "[ERR] Нужен root: sudo bash $0"; exit 1; }
[ -d "$ROOT" ] || { echo "[ERR] Нет $ROOT"; exit 1; }

echo "======== Диагностика ========"
echo "— кто слушает 443:"
ss -lntp 2>/dev/null | grep -E ':443\b' || echo "  (никто / пусто)"
echo "— сертификаты Let's Encrypt:"
ls -la /etc/letsencrypt/live/ 2>/dev/null || echo "  (нет /etc/letsencrypt/live)"
echo "— nginx ssl в enabled:"
grep -RInE 'listen[[:space:]]+443|ssl_certificate' /etc/nginx/sites-enabled/ 2>/dev/null | head -40 || echo "  (нет listen 443 в sites-enabled)"

# Найти pem для домена (live или archive)
CERT=""
KEY=""
for d in \
  "/etc/letsencrypt/live/${DOMAIN}" \
  "/etc/letsencrypt/live/www.${DOMAIN}" \
  $(ls -d /etc/letsencrypt/live/*5mb2* 2>/dev/null || true)
do
  [ -d "$d" ] || continue
  if [ -f "$d/fullchain.pem" ] && [ -f "$d/privkey.pem" ]; then
    CERT="$d/fullchain.pem"
    KEY="$d/privkey.pem"
    echo "  ✓ найдены: $CERT"
    break
  fi
done

# иногда сертификат лежит как custom path
if [ -z "$CERT" ]; then
  for cand in \
    "/etc/ssl/certs/${DOMAIN}.pem" \
    "/etc/nginx/ssl/${DOMAIN}/fullchain.pem" \
    "/etc/nginx/ssl/${DOMAIN}.crt"
  do
    [ -f "$cand" ] || continue
    CERT="$cand"
    KEY="${cand/fullchain.pem/privkey.pem}"
    KEY="${KEY%.crt}.key"
    [ -f "$KEY" ] || KEY="/etc/nginx/ssl/${DOMAIN}/privkey.pem"
    [ -f "$KEY" ] || KEY="/etc/nginx/ssl/${DOMAIN}.key"
    if [ -f "$KEY" ]; then
      echo "  ✓ кастомный cert: $CERT"
      break
    fi
    CERT=""
  done
fi

if [ -z "$CERT" ] || [ ! -f "$KEY" ]; then
  echo ""
  echo "[!!] На ЭТОМ VPS файлов сертификата не видно."
  echo "    Часто сертификат «есть на домене» = был на СТАРОМ хостинге или"
  echo "    куплен у регистратора, но сюда не скопирован."
  echo ""
  echo "Выпусти/привяжи заново:"
  echo "  bash $(dirname "$0")/enable-https-5mb2.sh"
  echo ""
  echo "Или положи pem сюда и запусти скрипт снова:"
  echo "  /etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
  echo "  /etc/letsencrypt/live/${DOMAIN}/privkey.pem"
  exit 1
fi

# показать срок действия
if command -v openssl >/dev/null 2>&1; then
  echo "— срок сертификата:"
  openssl x509 -in "$CERT" -noout -subject -dates 2>/dev/null || true
fi

echo "======== Пишем nginx HTTP+HTTPS ========"
# убрать чужие битые 443 для этого домена
for f in /etc/nginx/sites-enabled/*; do
  [ -e "$f" ] || continue
  case "$f" in
    *ai-helper-${SITE_NAME}.conf) continue ;;
  esac
  if grep -qE "server_name.*${DOMAIN}|listen[[:space:]]+443" "$f" 2>/dev/null; then
    if grep -qE "ssl_certificate" "$f" 2>/dev/null && ! grep -qF "$CERT" "$f" 2>/dev/null; then
      echo "  · отключаю конфликтующий vhost: $f"
      rm -f "$f"
    fi
  fi
done

cat > "$CONF" <<EOF
# 5mb2.ru — HTTP → HTTPS + WordPress + /api
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    location /.well-known/acme-challenge/ {
        root ${ROOT};
        allow all;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${DOMAIN} www.${DOMAIN};

    ssl_certificate     ${CERT};
    ssl_certificate_key ${KEY};
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;
    ssl_protocols TLSv1.2 TLSv1.3;

    root ${ROOT};
    index index.php index.html index.htm;
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
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTPS on;
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
cp "$CONF" "$ROOT/nginx.vhost.conf" 2>/dev/null || true

if command -v ufw >/dev/null 2>&1; then
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
fi

echo "======== nginx -t && reload ========"
nginx -t
systemctl reload nginx

# WP URL → https
WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-load.php" ] && WP="$cand" && break
done
[ -n "$WP" ] || WP="$ROOT"

if [ -f "$WP/wp-config.php" ]; then
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
  echo get_option('home'), PHP_EOL;
  " 2>/dev/null || true
fi

docker restart ai-helper-php >/dev/null 2>&1 || true
sleep 1

echo "======== Проверка ========"
hcode=$(curl -4 -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://${DOMAIN}/" || echo 000)
echo "  https://${DOMAIN}/ → $hcode"
if [ "$hcode" = "000" ]; then
  echo ""
  echo "[!!] Всё ещё 000. Смотри:"
  echo "  ss -lntp | grep 443"
  echo "  nginx -T 2>/dev/null | grep -A2 'listen 443'"
  echo "  journalctl -u nginx -n 40 --no-pager"
  echo ""
  echo "Частая вторая причина: AAAA (IPv6) на СТАРЫЙ хостинг."
  echo "У регистратора удали AAAA для ${DOMAIN}, оставь только A → этот VPS."
  exit 1
fi

echo ""
echo "OK: ${HOME_HTTPS}/"
echo "Админка: ${HOME_HTTPS}/wp-admin/"
