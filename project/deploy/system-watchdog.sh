#!/usr/bin/env bash
# Watchdog: 5mb2 + NeoBrain + панель/API/DeepSeek.
#   bash project/deploy/system-watchdog.sh
#   bash project/deploy/system-watchdog.sh --ask-deepseek
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="${PROJECT_DIR}/.env"

export WATCHDOG_BASE_URL="${WATCHDOG_BASE_URL:-https://127.0.0.1}"
export WATCHDOG_HOST_HEADER="${WATCHDOG_HOST_HEADER:-5mb2.ru}"
export WATCHDOG_API_URL="${WATCHDOG_API_URL:-http://127.0.0.1:8502}"
export WATCHDOG_PANEL_URL="${WATCHDOG_PANEL_URL:-http://127.0.0.1:3000/console/}"
export WATCHDOG_ALLOW_RESTART="${WATCHDOG_ALLOW_RESTART:-1}"
export WATCHDOG_ASK_DEEPSEEK="${WATCHDOG_ASK_DEEPSEEK:-1}"
export LLM_PREFER_FREE=0

ASK=0
for a in "$@"; do
  case "$a" in
    --ask-deepseek|--ask-ai) ASK=1 ;;
  esac
done

# Токен для Docker-bridge (API видит 172.x, не 127.0.0.1)
WD_TOKEN=""
if [ -f "$ENV_FILE" ]; then
  WD_TOKEN=$(grep -E '^WATCHDOG_TOKEN=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)
fi

AUTH_H=()
if [ -n "$WD_TOKEN" ]; then
  AUTH_H=(-H "X-Watchdog-Token: ${WD_TOKEN}")
fi

if curl -sf --max-time 3 "$WATCHDOG_API_URL/status" >/dev/null 2>&1; then
  if [[ "$ASK" == "1" ]]; then
    BODY=$(printf '{"remediate":true,"ask_deepseek":true,"base":"%s","host":"%s"}' \
      "$WATCHDOG_BASE_URL" "$WATCHDOG_HOST_HEADER")
  else
    BODY=$(printf '{"remediate":true,"ask_deepseek":false,"base":"%s","host":"%s"}' \
      "$WATCHDOG_BASE_URL" "$WATCHDOG_HOST_HEADER")
  fi
  RESP="$(curl -sk --max-time 180 -X POST "$WATCHDOG_API_URL/system/watchdog" \
    -H 'Content-Type: application/json' \
    "${AUTH_H[@]}" \
    -d "$BODY" || true)"
  if [[ -n "$RESP" ]]; then
    echo "$RESP" | python3 -c '
import sys, json
try:
    d = json.load(sys.stdin)
except Exception as e:
    print("watchdog parse error", e)
    sys.exit(2)
if d.get("error") and d.get("auth_required"):
    print("watchdog AUTH FAIL — задай WATCHDOG_TOKEN в project/.env и recreate app")
    sys.exit(2)
failed = d.get("failed") or []
print("watchdog", "OK" if d.get("ok") else "FAIL", "| failed=", ",".join(failed) or "-")
if d.get("actions"):
    print("actions:", json.dumps(d.get("actions"), ensure_ascii=False)[:500])
sys.exit(0 if d.get("ok") else 1)
'
    exit $?
  fi
fi

ARGS=(--remediate --json --base "$WATCHDOG_BASE_URL" --host "$WATCHDOG_HOST_HEADER")
if [[ "$ASK" == "1" ]]; then
  ARGS+=(--ask-deepseek)
fi
cd "$PROJECT_DIR"
python3 "$PROJECT_DIR/system_health.py" "${ARGS[@]}"
