#!/usr/bin/env bash
# Публикует бизнес-хаб NeoBrain (AI + SEO) на VPS.
#   cd /opt/ai-helper
#   git fetch origin && git checkout -B cursor/neobrain-hub-17f9 origin/cursor/neobrain-hub-17f9
#   bash project/deploy/publish-neobrain-hub.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

echo "==> NeoBrain hub publish"
bash "$ROOT/project/deploy/create-ai-site.sh"

# Студия (если есть в репо) — превью под /sunduk/
if [[ -d "$ROOT/project/sites/sunduk" ]]; then
  if [[ -f "$ROOT/project/deploy/announce-sunduk.sh" ]]; then
    bash "$ROOT/project/deploy/announce-sunduk.sh" || bash "$ROOT/project/deploy/create-sunduk-site.sh" || true
  elif [[ -f "$ROOT/project/deploy/create-sunduk-site.sh" ]]; then
    bash "$ROOT/project/deploy/create-sunduk-site.sh" || true
  fi
fi

echo ""
echo "Проверьте:"
echo "  https://neobrain.site/"
echo "  https://neobrain.site/seo/"
echo "  https://neobrain.site/sunduk/"
echo ""
echo "ЮKassa: если оплата падает — перевыпустите live_ ключ и вставьте в"
echo "  https://neobrain.site/console/settings/  → Сохранить и проверить"
echo "См. project/deploy/YOOKASSA_SETUP_RU.md и DOMAIN_HUB_RU.md"
