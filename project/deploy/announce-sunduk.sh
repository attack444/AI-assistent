#!/usr/bin/env bash
# Публикует SUNDUK под NeoBrain и вешает анонс на главную:
#   https://neobrain.site/sunduk/
#   https://neobrain.site/  ← полоска «Запуск студии»
#
# На VPS:
#   cd /opt/ai-helper && git pull origin cursor/sunduk-studio-17f9
#   bash project/deploy/announce-sunduk.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITES="${SITES_ROOT:-/var/ai-helper/sites}"
AI_ROOT="$SITES/ai"
INDEX="$AI_ROOT/index.html"
WEB_USER="${WEB_USER:-www-data}"

echo "==> SUNDUK announce"

bash "$ROOT/project/deploy/create-sunduk-site.sh"

if [[ ! -f "$INDEX" ]]; then
  echo "[!!] Нет $INDEX — анонс на главной NeoBrain пропущен (студия уже в $SITES/ai/sunduk/)"
  exit 0
fi

NEOBRAIN_INDEX="$INDEX" python3 - <<'PY'
from pathlib import Path
import os, re

index = Path(os.environ["NEOBRAIN_INDEX"])
text = index.read_text(encoding="utf-8")

block = """<!-- SUNDUK-ANNOUNCE -->
<style>
  .sunduk-announce{
    position:sticky;top:0;z-index:80;
    display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;
    padding:10px 16px;
    background:linear-gradient(90deg,#071018,#0e1a24 45%,#12261f);
    color:#eef3f7;font:600 14px/1.35 Manrope,system-ui,sans-serif;
    border-bottom:1px solid rgba(198,241,53,.35);
    text-decoration:none;
  }
  .sunduk-announce b{color:#c6f135;font-family:Unbounded,Manrope,sans-serif;letter-spacing:.04em}
  .sunduk-announce span{opacity:.88}
  .sunduk-announce em{
    font-style:normal;background:#c6f135;color:#071018;
    padding:4px 10px;border-radius:8px;font-size:12px;font-weight:800;
  }
  .sunduk-announce:hover em{filter:brightness(1.05)}
</style>
<a class="sunduk-announce" href="/sunduk/">
  <b>SUNDUK</b>
  <span>Запуск студии мобильных игр — каталог, обзоры и демо Chest Dash</span>
  <em>Смотреть →</em>
</a>
<!-- /SUNDUK-ANNOUNCE -->
"""

# idempotent replace
text = re.sub(
    r"<!-- SUNDUK-ANNOUNCE -->.*?<!-- /SUNDUK-ANNOUNCE -->\s*",
    "",
    text,
    flags=re.S,
)
if "<body" in text:
    text = re.sub(r"(<body[^>]*>)", r"\1\n" + block, text, count=1, flags=re.I)
else:
    text = block + text
index.write_text(text, encoding="utf-8")
print("[OK] banner →", index)
PY

chown "$WEB_USER:$WEB_USER" "$INDEX" 2>/dev/null || true
chmod 644 "$INDEX"

echo ""
echo "Готово — открывайте:"
echo "  https://neobrain.site/          (анонс на главной)"
echo "  https://neobrain.site/sunduk/   (студия)"
echo "  https://neobrain.site/sunduk/play/"
