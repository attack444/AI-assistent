#!/bin/bash
# Один шаг: обновить ветку → DeepSeek (если ключ есть) → витрина ai → виджет на 5mb2.
#
#   BRANCH=cursor/complete-ai-helper-17f9 \
#   DEEPSEEK_API_KEY=sk-... \
#   bash project/deploy/go-next.sh
#
# Без ключа — только деплой кода и виджет; ключ можно добавить позже enable-deepseek.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export BRANCH="${BRANCH:-cursor/complete-ai-helper-17f9}"
SITE_NAME="${SITE_NAME:-5mb2}"

echo "======== 1/4 bootstrap ($BRANCH) ========"
bash "$SCRIPT_DIR/bootstrap-update.sh"

if [ -n "${DEEPSEEK_API_KEY:-}" ]; then
  echo "======== 2/4 DeepSeek ========"
  bash "$SCRIPT_DIR/enable-deepseek.sh"
else
  echo "======== 2/4 DeepSeek — пропуск (нет DEEPSEEK_API_KEY) ========"
  echo "Потом: DEEPSEEK_API_KEY=sk-... bash $SCRIPT_DIR/enable-deepseek.sh"
fi

echo "======== 3/4 витрина ai ========"
bash "$SCRIPT_DIR/create-ai-site.sh" || true

echo "======== 4/4 виджет на ${SITE_NAME} ========"
SITE_NAME="$SITE_NAME" bash "$SCRIPT_DIR/install-5mb2-widget.sh"

IP=$(curl -s --max-time 3 ifconfig.me 2>/dev/null || echo "IP")
echo ""
echo "Готово."
echo "  Панель:  http://${IP}/"
echo "  Витрина: http://${IP}/sites/ai/"
echo "  Сайт:    http://${IP}/sites/${SITE_NAME}/  (+ кнопка Чат)"
echo "  В панели выбери сайт ${SITE_NAME} и попроси DeepSeek: «проверь сайт и что улучшить»"
