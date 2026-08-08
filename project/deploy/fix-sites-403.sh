#!/bin/bash
# Исправление 403 на /sites/... — права для nginx (www-data)
set -e

SITES="${SITES_DIR:-/var/ai-helper/sites}"
BASE="$(dirname "$SITES")"

echo "[>>] Права на $BASE и $SITES"

mkdir -p "$SITES"

# nginx должен пройти по пути и читать файлы
chmod 755 "$BASE" 2>/dev/null || true
chmod 755 "$SITES"

# Все каталоги — исполняемые для других, файлы — читаемые
find "$SITES" -type d -exec chmod 755 {} \;
find "$SITES" -type f -exec chmod 644 {} \;

# Владелец: www-data если есть, иначе оставить + a+rX
if id www-data &>/dev/null; then
  chown -R www-data:www-data "$SITES" 2>/dev/null || chown -R root:www-data "$SITES"
  # Docker пишет от root — группа www-data + g+rwX удобнее для обновлений
  chmod -R g+rwX "$SITES" 2>/dev/null || true
else
  chmod -R a+rX "$SITES"
fi

# Показать что лежит
echo "[OK] Содержимое:"
ls -la "$SITES" || true
for d in "$SITES"/*/; do
  [ -d "$d" ] || continue
  echo "--- $d"
  ls -la "$d" | head -20
  if [ ! -f "${d}index.html" ] && [ ! -f "${d}index.htm" ] && [ ! -f "${d}index.php" ]; then
    echo "[!!] Нет index.html в $d — будет 403/404. Проверь Файлы в панели."
  fi
done

if command -v nginx &>/dev/null; then
  nginx -t && systemctl reload nginx && echo "[OK] Nginx reload"
fi

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
echo ""
echo "Проверь: http://${IP}/sites/mysite/"
echo "Если 404 — нет index.html. Если снова 403 — пришли: ls -la $SITES/mysite"
