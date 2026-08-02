#!/bin/bash
# Генерирует/заменяет небезопасные секреты в project/.env
# Использование: bash ensure-secrets.sh /path/to/project/.env
set -euo pipefail

ENV_FILE="${1:?Usage: $0 <path-to-.env>}"

if [ ! -f "$ENV_FILE" ]; then
  echo "[ERR] Нет файла: $ENV_FILE" >&2
  exit 1
fi

_rand() {
  # openssl предпочтительнее; fallback на /dev/urandom
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -hex 24
  else
    head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n'
  fi
}

_get_kv() {
  local key="$1"
  grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true
}

_set_kv() {
  local key="$1"
  local val="$2"
  if grep -qE "^${key}=" "$ENV_FILE" 2>/dev/null; then
    # не используем | в пароле; hex-only
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

_is_insecure() {
  local key="$1"
  local val
  val="$(_get_kv "$key")"
  case "$val" in
    ""|change_me*|changeme*|root_change_me|wp_change_me|change_me_panel_password|change_me_to_random_string_min_32_chars|change_me_postgres_123|password|admin|secret|dev-insecure-change-me)
      return 0
      ;;
  esac
  if [ "${#val}" -lt 12 ]; then
    return 0
  fi
  return 1
}

CHANGED=0
print_panel=""

for key in PANEL_PASSWORD SECRET_KEY MYSQL_ROOT_PASSWORD MYSQL_PASSWORD POSTGRES_PASSWORD; do
  if _is_insecure "$key"; then
    new="$(_rand)"
    _set_kv "$key" "$new"
    CHANGED=1
    if [ "$key" = "PANEL_PASSWORD" ]; then
      print_panel="$new"
    fi
    echo "[OK] Сгенерирован ${key}"
  fi
done

# Prod: не оставлять открытую панель
if grep -qE '^ALLOW_OPEN_PANEL=' "$ENV_FILE" 2>/dev/null; then
  sed -i 's|^ALLOW_OPEN_PANEL=.*|ALLOW_OPEN_PANEL=0|' "$ENV_FILE"
else
  echo "ALLOW_OPEN_PANEL=0" >> "$ENV_FILE"
fi

if [ "$CHANGED" -eq 1 ]; then
  echo "[OK] Секреты обновлены в $ENV_FILE"
  if [ -n "$print_panel" ]; then
    echo ""
    echo "╔══════════════════════════════════════════════════╗"
    echo "║  PANEL_PASSWORD (сохрани — больше не покажется): ║"
    echo "║  ${print_panel}"
    echo "╚══════════════════════════════════════════════════╝"
    echo ""
  fi
else
  echo "[OK] Секреты уже выглядят заданными"
fi
