#!/bin/bash
# Safe .env reader — only KEY=value lines, no full bash source
# (API keys like gsk_... / sk-... must not be executed as shell)
#
# Usage:  VAL=$(env_get MYSQL_PASSWORD)
#         env_get MYSQL_PASSWORD /path/to/.env

env_get() {
  local key="$1"
  local file="${2:-${ENV_FILE:-/opt/ai-helper/project/.env}}"
  [ -f "$file" ] || return 0
  # strip CR, comments, quotes; take first match
  local line
  line=$(grep -E "^[[:space:]]*${key}=" "$file" | tail -n1 | sed 's/\r$//' || true)
  [ -n "$line" ] || return 0
  local val="${line#*=}"
  # trim spaces
  val="${val#"${val%%[![:space:]]*}"}"
  val="${val%"${val##*[![:space:]]}"}"
  # strip surrounding quotes
  if [[ "$val" == \"*\" ]]; then val="${val:1:-1}"; fi
  if [[ "$val" == \'*\' ]]; then val="${val:1:-1}"; fi
  printf '%s' "$val"
}
