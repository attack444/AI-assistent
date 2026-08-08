#!/bin/bash
# Чинит WordPress на 5mb2.ru: HTTP, siteurl/home, nginx, wordfence prepend.
# НЕ активирует тему и НЕ трогает плагины — это ты сделаешь вручную в админке.
#
#   bash project/deploy/fix-5mb2-wp.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_NAME="${SITE_NAME:-5mb2}"
DOMAIN="${DOMAIN:-5mb2.ru}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="${SITES_DIR}/${SITE_NAME}"
HOME_URL="http://${DOMAIN}"

[ -d "$ROOT" ] || { echo "[ERR] Нет $ROOT"; exit 1; }

WP=""
for cand in "$ROOT" "$ROOT/wordpress" "$ROOT/public_html"; do
  [ -f "$cand/wp-config.php" ] || [ -f "$cand/wp-config.php.bak-aihelper" ] || [ -d "$cand/wp-content" ] && WP="$cand" && break
done
[ -n "$WP" ] || WP="$ROOT"
[ -d "$WP/wp-content" ] || { echo "[ERR] wp-content не найден"; exit 1; }

echo "[>>] WP root: $WP"
echo "[>>] Домен:   $HOME_URL  (только HTTP — HTTPS пока сломан)"

# 1) Wordfence auto_prepend часто рвёт сайт
printf 'auto_prepend_file =\n' > "$WP/.user.ini" || true
printf 'auto_prepend_file =\n' > "$ROOT/.user.ini" || true
# убрать жёсткий WAF prepend из php.ini сайта если есть
find "$ROOT" -name 'php.ini' -o -name '.user.ini' 2>/dev/null | while read -r f; do
  sed -i 's/^[[:space:]]*auto_prepend_file.*/auto_prepend_file =/' "$f" 2>/dev/null || true
done

# 2) wp-config: не форсировать SSL
if [ -f "$WP/wp-config.php" ]; then
  # закомментировать FORCE_SSL_* если есть
  sed -i -E "s/^([[:space:]]*define[[:space:]]*\([[:space:]]*['\"]FORCE_SSL_ADMIN['\"].*)/\/\/ \\1/" "$WP/wp-config.php" 2>/dev/null || true
  sed -i -E "s/^([[:space:]]*define[[:space:]]*\([[:space:]]*['\"]FORCE_SSL_LOGIN['\"].*)/\/\/ \\1/" "$WP/wp-config.php" 2>/dev/null || true
  # WP_HOME / WP_SITEURL — http
  if grep -q "WP_HOME" "$WP/wp-config.php"; then
    sed -i -E "s|(define[[:space:]]*\([[:space:]]*['\"]WP_HOME['\"][[:space:]]*,[[:space:]]*)['\"][^'\"]*['\"]|\\1'${HOME_URL}'|" "$WP/wp-config.php" || true
  else
    # вставить перед "That's all"
    if grep -q "That's all" "$WP/wp-config.php"; then
      sed -i "/That's all/i\\
define('WP_HOME', '${HOME_URL}');\\
define('WP_SITEURL', '${HOME_URL}');\\
" "$WP/wp-config.php" || true
    fi
  fi
  if grep -q "WP_SITEURL" "$WP/wp-config.php"; then
    sed -i -E "s|(define[[:space:]]*\([[:space:]]*['\"]WP_SITEURL['\"][[:space:]]*,[[:space:]]*)['\"][^'\"]*['\"]|\\1'${HOME_URL}'|" "$WP/wp-config.php" || true
  fi
  echo "  ✓ wp-config: HOME/SITEURL → ${HOME_URL}, SSL force off"
fi

# 3) options в БД (home/siteurl)
ENV="$SCRIPT_DIR/../.env"
fix_db() {
  [ -f "$ENV" ] || return 0
  DB_NAME=$(grep -E '^MYSQL_DATABASE=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  DB_USER=$(grep -E '^MYSQL_USER=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  DB_PASS=$(grep -E '^MYSQL_PASSWORD=' "$ENV" | tail -1 | cut -d= -f2- | tr -d '"' || true)
  [ -n "${DB_NAME:-}" ] || return 0
  for pref in wp0w_ wp_; do
    docker exec ai-helper-mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
      UPDATE ${pref}options SET option_value='${HOME_URL}' WHERE option_name IN ('home','siteurl');
    " 2>/dev/null && echo "  ✓ БД home/siteurl (${pref})" && break || true
  done
}
if command -v wp >/dev/null 2>&1; then
  wp option update home "$HOME_URL" --path="$WP" --allow-root 2>/dev/null || true
  wp option update siteurl "$HOME_URL" --path="$WP" --allow-root 2>/dev/null || true
  echo "  ✓ wp-cli home/siteurl"
else
  fix_db || true
fi

# 4) Положить тему на диск (не активировать)
THEME_SRC="$SCRIPT_DIR/../sites/5mb2/wp-content/themes/5mb2-dark"
if [ -d "$THEME_SRC" ]; then
  mkdir -p "$WP/wp-content/themes/5mb2-dark"
  rsync -a --delete "$THEME_SRC/" "$WP/wp-content/themes/5mb2-dark/"
  chown -R www-data:www-data "$WP/wp-content/themes/5mb2-dark" 2>/dev/null || true
  echo "  ✓ тема 5mb2-dark лежит в wp-content/themes/ (ещё не активна)"
fi

# 5) nginx HTTP + /api (без HTTPS-редиректа)
CONF="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"
ENABLED="/etc/nginx/sites-enabled/ai-helper-${SITE_NAME}.conf"
# убрать только ЧУЖИЕ/битые ssl-конфиги домена (не трогаем рабочий Let's Encrypt)
LE_CERT="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
for f in /etc/nginx/sites-enabled/*5mb2* /etc/nginx/sites-enabled/*${DOMAIN}* \
         /etc/nginx/sites-available/*5mb2* /etc/nginx/sites-available/*${DOMAIN}*; do
  [ -e "$f" ] || continue
  case "$f" in
    *ai-helper-${SITE_NAME}.conf) continue ;;
  esac
  if grep -q 'listen 443\|ssl_certificate' "$f" 2>/dev/null; then
    if [ -f "$LE_CERT" ] && grep -q "letsencrypt/live/${DOMAIN}" "$f" 2>/dev/null; then
      echo "  · оставляю валидный SSL: $f"
      continue
    fi
    echo "  · отключаю битый SSL-конфиг: $f"
    rm -f "$f" 2>/dev/null || true
  fi
done

# если уже есть LE-сертификат — не затираем HTTPS vhost целиком (только чиним WP/HTTP пути ниже)
SKIP_NGINX_REWRITE=0
if [ -f "$LE_CERT" ] && [ -f "/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf" ] \
   && grep -q "letsencrypt/live/${DOMAIN}" "/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf" 2>/dev/null; then
  SKIP_NGINX_REWRITE=1
  echo "  · nginx HTTPS уже с Let's Encrypt — конфиг не перезаписываю"
fi

if [ "$SKIP_NGINX_REWRITE" -eq 0 ]; then
cat > "$CONF" <<EOF
# 5mb2.ru — HTTP (HTTPS через enable-https-5mb2.sh). WordPress + /api.
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

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
        fastcgi_param HTTPS off;
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
fi

# 6) кэш + php
rm -rf "$WP/wp-content/cache/"* 2>/dev/null || true
docker restart ai-helper-php >/dev/null 2>&1 || true

if [ "$(id -u)" -eq 0 ]; then
  nginx -t && systemctl reload nginx
  echo "  ✓ nginx reload"
else
  echo "  → нужно: sudo nginx -t && sudo systemctl reload nginx"
fi

echo ""
echo "============================================"
echo "  Админка (только HTTP!):"
echo "    ${HOME_URL}/wp-admin/"
echo "    ${HOME_URL}/wp-login.php"
echo ""
echo "  Дальше вручную:"
echo "    1) Плагины → Выключить Elementor + Essential Addons"
echo "    2) Внешний вид → Темы → 5MB2 Dark → Активировать"
echo "    3) Настройки → Чтение → «последние записи» на главной"
echo "    4) Ctrl+F5 на ${HOME_URL}/"
echo "============================================"
