#!/bin/bash
# Прописывает DEEPSEEK_API_KEY в .env и перезапускает app.
#   DEEPSEEK_API_KEY=sk-... bash project/deploy/enable-deepseek.sh
# или интерактивно:
#   bash project/deploy/enable-deepseek.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/../.env}"

KEY="${DEEPSEEK_API_KEY:-}"
if [ -z "$KEY" ]; then
  echo -n "Вставь DeepSeek API key (sk-...): "
  read -r KEY
fi
KEY="$(echo "$KEY" | tr -d '[:space:]')"
if [[ ! "$KEY" =~ ^sk- ]]; then
  echo "[ERR] Ключ должен начинаться с sk-"
  exit 1
fi

touch "$ENV_FILE"
# upsert DEEPSEEK_* and prefer-free chat layer
python3 - <<PY
from pathlib import Path
p = Path("$ENV_FILE")
text = p.read_text(encoding="utf-8") if p.exists() else ""
lines = text.splitlines()
keys = {
    "DEEPSEEK_API_KEY": "$KEY",
    "DEEPSEEK_MODEL": "deepseek-chat",
    "LLM_PREFER_FREE": "1",
    "PUBLIC_WIDGET_GUEST": "1",
}
out = []
seen = set()
for line in lines:
    if not line.strip() or line.lstrip().startswith("#") or "=" not in line:
        out.append(line)
        continue
    k = line.split("=", 1)[0].strip()
    if k in keys:
        out.append(f"{k}={keys[k]}")
        seen.add(k)
    else:
        out.append(line)
for k, v in keys.items():
    if k not in seen:
        out.append(f"{k}={v}")
p.write_text("\n".join(out).rstrip() + "\n", encoding="utf-8")
print("[OK] записано в", p)
PY

cd "$SCRIPT_DIR"
if [ -f docker-compose.prod.yml ]; then
  docker compose -f docker-compose.prod.yml up -d --force-recreate app
  echo "[OK] контейнер app пересоздан"
fi

sleep 2
curl -s --max-time 5 http://127.0.0.1:8502/status | python3 -c '
import sys,json
d=json.load(sys.stdin)
print("deepseek:", d.get("deepseek"), "| free:", d.get("free_model"), "| ver:", d.get("version"))
' 2>/dev/null || echo "Проверь /status вручную"
