#!/usr/bin/env bash
# Cron: 2 раза в день SEO-новости → черновики WordPress (не публикует).
#
#   sudo bash /opt/ai-helper/project/deploy/install-seo-cron.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="${SCRIPT_DIR}/seo-news-drafts.php"
SITES="${SITES_DIR:-/var/ai-helper/sites}"
OPS_COPY="${SITES}/_ops/seo-news-drafts.php"

mkdir -p "${SITES}/_ops"
cp -f "$PHP_SCRIPT" "$OPS_COPY"
chmod +x "$PHP_SCRIPT" 2>/dev/null || true

# Предпочитаем host php; иначе docker exec в php-контейнер
RUNNER="/usr/local/bin/ai-helper-seo-news.sh"
cat > "$RUNNER" <<EOF
#!/usr/bin/env bash
set -euo pipefail
export SITES_ROOT="${SITES}"
export SEO_NEWS_MAX="\${SEO_NEWS_MAX:-3}"
if command -v php >/dev/null 2>&1 && [ -f "${PHP_SCRIPT}" ]; then
  exec php "${PHP_SCRIPT}"
fi
if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx ai-helper-php; then
  exec docker exec -e SITES_ROOT="${SITES}" -e SEO_NEWS_MAX="\${SEO_NEWS_MAX:-3}" ai-helper-php php "${OPS_COPY}"
fi
echo "[ERR] нет php и нет контейнера ai-helper-php" >&2
exit 1
EOF
chmod +x "$RUNNER"

if command -v crontab >/dev/null 2>&1; then
  TMP=$(mktemp)
  crontab -l 2>/dev/null | grep -v 'ai-helper-seo-news\|seo-news-drafts' >"$TMP" || true
  echo "20 8,18 * * * ${RUNNER} >> /var/log/ai-helper-seo-news.log 2>&1" >>"$TMP"
  crontab "$TMP"
  rm -f "$TMP"
  echo "[OK] cron: 08:20 и 18:20 → черновики SEO"
else
  echo "[!!] crontab нет. Добавь:"
  echo "20 8,18 * * * ${RUNNER} >> /var/log/ai-helper-seo-news.log 2>&1"
fi

echo "Пробный прогон: DRY_RUN=1 ${RUNNER}"
echo "Боевой:        ${RUNNER}"
