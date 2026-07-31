#!/bin/bash
# Финальный запуск 5mb2.ru на VPS (домен + SSL + чистка Wordfence)
# На сервере:
#   bash /opt/ai-helper/project/deploy/go-live-5mb2.sh
set -euo pipefail

REPO="${REPO_DIR:-/opt/ai-helper}"
DEPLOY="$REPO/project/deploy"
SITE_NAME="${SITE_NAME:-5mb2}"
DOMAIN="${DOMAIN:-5mb2.ru}"
SSL_EMAIL="${SSL_EMAIL:-slavasundukov887@gmail.com}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
ROOT="$SITES_DIR/$SITE_NAME"

echo "============================================"
echo "  go-live: $SITE_NAME → https://$DOMAIN"
echo "============================================"

echo "[>>] update…"
bash "$DEPLOY/update.sh"

if [ ! -d "$ROOT" ] || [ ! -f "$ROOT/wp-config.php" ]; then
  echo "[!!] Нет WordPress в $ROOT"
  exit 1
fi

# Wordfence auto_prepend со старого хостинга
printf 'auto_prepend_file =\n' > "$ROOT/.user.ini"
find "$ROOT" -name '.user.ini' -type f 2>/dev/null | while read -r f; do
  if grep -q 'auto_prepend_file' "$f" 2>/dev/null; then
    printf 'auto_prepend_file =\n' > "$f"
  fi
done
docker restart ai-helper-php >/dev/null 2>&1 || true

echo "[>>] Domain + SSL…"
export CERTBOT_EMAIL="$SSL_EMAIL"
bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN" --ssl || \
  bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN"

bash "$DEPLOY/fix-sites-403.sh" 2>/dev/null || true

# siteurl уже должен быть https://5mb2.ru после reimport
IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || echo "80.78.248.195")

echo ""
echo "============================================"
echo "  Готово (проверь в браузере):"
echo "  https://${DOMAIN}/"
echo "  http://${DOMAIN}/"
echo "  Панель: http://${IP}/"
echo "  IP-путь: http://${IP}/sites/${SITE_NAME}/index.php"
echo "============================================"
echo "  Если https не выписался — DNS уже на $IP,"
echo "  подожди 1–2 мин и снова:"
echo "  CERTBOT_EMAIL=$SSL_EMAIL bash $DEPLOY/setup-domain.sh $SITE_NAME $DOMAIN --ssl"
echo "============================================"
