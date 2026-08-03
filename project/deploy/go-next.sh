#!/bin/bash
# Обновить ветку → HTTP/WP fix → DeepSeek (опц.) → витрина ai → виджет.
#
#   BRANCH=cursor/complete-ai-helper-17f9 \
#   DEEPSEEK_API_KEY=sk-... \
#   bash project/deploy/go-next.sh
#
# После скрипта вручную в http://5mb2.ru/wp-admin/ :
#   1) выключить Elementor + Essential Addons
#   2) активировать тему 5MB2 Dark
#   3) Чтение → на главной «последние записи»
# Или: bash project/deploy/activate-5mb2-dark.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export BRANCH="${BRANCH:-cursor/complete-ai-helper-17f9}"
SITE_NAME="${SITE_NAME:-5mb2}"

echo "======== 1/5 bootstrap ($BRANCH) ========"
bash "$SCRIPT_DIR/bootstrap-update.sh"

echo "======== 2/5 WordPress HTTP + URLs (без активации темы) ========"
bash "$SCRIPT_DIR/fix-5mb2-wp.sh" || true

if [ -n "${DEEPSEEK_API_KEY:-}" ]; then
  echo "======== 3/5 DeepSeek ========"
  bash "$SCRIPT_DIR/enable-deepseek.sh"
else
  echo "======== 3/5 DeepSeek — пропуск (нет DEEPSEEK_API_KEY) ========"
  echo "Потом: DEEPSEEK_API_KEY=sk-... bash $SCRIPT_DIR/enable-deepseek.sh"
fi

echo "======== 4/5 витрина ai ========"
bash "$SCRIPT_DIR/create-ai-site.sh" || true

echo "======== 5/5 виджет на ${SITE_NAME} ========"
SITE_NAME="$SITE_NAME" bash "$SCRIPT_DIR/install-5mb2-widget.sh"

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo ""
echo "Готово."
echo "  Админка 5mb2 (HTTP): http://5mb2.ru/wp-admin/"
echo "  Панель:  http://${IP}/"
echo "  Витрина: http://${IP}/sites/ai/"
echo "  Дальше: выключи Elementor вручную → тема 5MB2 Dark"
echo "  (или: bash $SCRIPT_DIR/activate-5mb2-dark.sh)"
