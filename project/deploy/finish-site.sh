#!/bin/bash
# Полная добивка сайта на VPS по site.env
# 1) Заполни site.env (см. site.env.example)
# 2) bash /opt/ai-helper/project/deploy/finish-site.sh
set -euo pipefail

REPO_DIR="${REPO_DIR:-/opt/ai-helper}"
DEPLOY="$REPO_DIR/project/deploy"
ENV_FILE="$REPO_DIR/project/.env"
SITE_ENV="${SITE_ENV:-$DEPLOY/site.env}"

if [ ! -f "$SITE_ENV" ]; then
  echo "[!!] Нет $SITE_ENV"
  echo "    cp $DEPLOY/site.env.example $SITE_ENV && nano $SITE_ENV"
  exit 1
fi

# shellcheck disable=SC1090
set -a
source <(sed 's/\r$//' "$SITE_ENV")
set +a

SITE_NAME="${SITE_NAME:?Задай SITE_NAME в site.env}"
DOMAIN="${DOMAIN:?Задай DOMAIN в site.env}"
OLD_URL="${OLD_URL:-}"
NEW_URL="${NEW_URL:-https://${DOMAIN}}"
SQL_FILE="${SQL_FILE:-}"
ENABLE_SSL="${ENABLE_SSL:-0}"
SSL_EMAIL="${SSL_EMAIL:-}"

DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN%%/*}"

SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
SITE_ROOT="$SITES_DIR/$SITE_NAME"
IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "============================================"
echo "  finish-site"
echo "  SITE_NAME=$SITE_NAME"
echo "  DOMAIN=$DOMAIN"
echo "  OLD_URL=${OLD_URL:-"(из БД после импорта)"}"
echo "  NEW_URL=$NEW_URL"
echo "  IP=$IP"
echo "============================================"

# ── 0. Update code ───────────────────────────────────────────
if [ -x "$DEPLOY/update.sh" ]; then
  echo "[>>] update.sh…"
  bash "$DEPLOY/update.sh" || true
fi

# ── 1. MySQL passwords (ASCII) ───────────────────────────────
if [ ! -f "$ENV_FILE" ]; then
  cp "$DEPLOY/.env.example" "$ENV_FILE"
fi

ensure_env_kv() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

if [ -n "${MYSQL_PASSWORD:-}" ]; then
  ensure_env_kv MYSQL_PASSWORD "$MYSQL_PASSWORD"
fi
if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
  ensure_env_kv MYSQL_ROOT_PASSWORD "$MYSQL_ROOT_PASSWORD"
fi
ensure_env_kv MYSQL_DATABASE "${MYSQL_DATABASE:-wordpress}"
ensure_env_kv MYSQL_USER "${MYSQL_USER:-wp}"

# Sync MySQL user/password from .env. Never auto --reinit: that wipes the
# volume and can destroy WordPress data (and unrelated mysql_* volumes).
# Same policy as apply-5mb2.sh.
echo "[>>] MySQL sync (без wipe тома)…"
if ! bash "$DEPLOY/reset-mysql-password.sh"; then
  echo "[!!] Обычный reset не вышел — НЕ делаю --reinit автоматически (чтобы не стереть БД)."
  echo "    Если база пустая / том битый и SQL ещё не залит — вручную:"
  echo "      bash $DEPLOY/reset-mysql-password.sh --reinit"
  exit 1
fi

# Reload only MySQL vars (не source весь .env — gsk_/sk- ключи ломают bash)
# shellcheck source=env-get.sh
source "$DEPLOY/env-get.sh"
WP_PASS="$(env_get MYSQL_PASSWORD || true)"
MYSQL_PASSWORD="$WP_PASS"
MYSQL_ROOT_PASSWORD="$(env_get MYSQL_ROOT_PASSWORD || true)"
MYSQL_USER="$(env_get MYSQL_USER || true)"; MYSQL_USER="${MYSQL_USER:-wp}"
MYSQL_DATABASE="$(env_get MYSQL_DATABASE || true)"; MYSQL_DATABASE="${MYSQL_DATABASE:-wordpress}"

# ── 2. Site folder ───────────────────────────────────────────
if [ ! -d "$SITE_ROOT" ]; then
  echo "[!!] Нет $SITE_ROOT"
  echo "    Создай сайт в панели http://$IP/sites имя=$SITE_NAME и залей ZIP,"
  echo "    либо: mkdir -p $SITE_ROOT"
  exit 1
fi

# ── 3. wp-config ─────────────────────────────────────────────
WP_CONFIG=""
for c in "$SITE_ROOT/wp-config.php" "$SITE_ROOT/wordpress/wp-config.php" "$SITE_ROOT/public_html/wp-config.php"; do
  if [ -f "$c" ]; then WP_CONFIG="$c"; break; fi
done
if [ -z "$WP_CONFIG" ]; then
  WP_CONFIG=$(find "$SITE_ROOT" -maxdepth 3 -name wp-config.php 2>/dev/null | head -1 || true)
fi

if [ -n "$WP_CONFIG" ] && [ -n "$WP_PASS" ]; then
  echo "[>>] Патч wp-config: $WP_CONFIG"
  python3 - <<PY
from pathlib import Path
import sys
sys.path.insert(0, "$REPO_DIR/project")
# Prefer running inside container if host has no pymysql — patch via simple regex here
import re
p = Path("$WP_CONFIG")
text = p.read_text(encoding="utf-8", errors="ignore")
bak = p.with_suffix(".php.bak-finish")
if not bak.exists():
    bak.write_text(text, encoding="utf-8")

def repl(key, val, t):
    safe = val.replace("\\\\", "\\\\\\\\").replace("'", "\\\\'")
    pat = re.compile(rf"(define\\s*\\(\\s*['\"]{re.escape(key)}['\"]\\s*,\\s*)(['\"].*?['\"]|[^,]+?)(\\s*\\))", re.I|re.S)
    if pat.search(t):
        return pat.sub(rf"\\1'{safe}'\\3", t, count=1), True
    return t + f"\\ndefine('{key}', '{safe}');\\n", True

for k, v in [
    ("DB_NAME", "${MYSQL_DATABASE:-wordpress}"),
    ("DB_USER", "${MYSQL_USER:-wp}"),
    ("DB_PASSWORD", "$WP_PASS"),
    ("DB_HOST", "mysql"),
]:
    text, _ = repl(k, v, text)
p.write_text(text, encoding="utf-8")
print("[OK] wp-config updated")
PY
else
  echo "[!!] wp-config не найден или нет MYSQL_PASSWORD — сделай «Сохранить wp-config» в панели"
fi

# Also patch via API container (more reliable if python on host lacks modules)
if docker ps --format '{{.Names}}' | grep -qx ai-helper-app; then
  docker exec -i ai-helper-app python - <<PY || true
import os, sys
sys.path.insert(0, "/app")
from pathlib import Path
import wp_tools as w
root = Path("/opt/sites/$SITE_NAME")
if not root.is_dir():
    root = Path("/var/ai-helper/sites/$SITE_NAME")
if root.is_dir():
    r = w.patch_wp_config(
        root,
        db_name=os.environ.get("MYSQL_DATABASE", "wordpress"),
        db_user=os.environ.get("MYSQL_USER", "wp"),
        db_password=os.environ.get("MYSQL_PASSWORD", ""),
        db_host="mysql",
    )
    print("[OK] container wp-config", r.get("changed"), r.get("path"))
    print("[mysql]", w.ensure_mysql_user(force=True))
else:
    print("[!!] site root missing in container", root)
PY
fi

# ── 4. Import SQL if provided ────────────────────────────────
if [ -n "$SQL_FILE" ]; then
  if [ ! -f "$SQL_FILE" ]; then
    echo "[!!] SQL_FILE не найден: $SQL_FILE"
    exit 1
  fi
  echo "[>>] Импорт $SQL_FILE…"
  bash "$DEPLOY/import-wp-sql.sh" "$SQL_FILE" "${MYSQL_DATABASE:-wordpress}"
fi

# ── 5. Replace URLs in DB ────────────────────────────────────
replace_urls() {
  local old="$1" new="$2"
  [ -z "$old" ] || [ -z "$new" ] && return 0
  echo "[>>] URL replace: $old → $new"
  docker exec -i ai-helper-app python - <<PY
import os, sys
sys.path.insert(0, "/app")
import wp_tools as w
old = """$old""".strip().rstrip("/")
new = """$new""".strip().rstrip("/")
# Auto old from DB if placeholder
if not old or old in ("AUTO", "auto", "(из БД)"):
    u = w.get_site_urls("wp_")
    old = (u.get("urls") or {}).get("siteurl") or (u.get("urls") or {}).get("home") or ""
    print("[info] old from DB:", old)
if not old:
    print("[!!] Неизвестен OLD_URL — пропусти или задай в site.env")
else:
    print(w.replace_site_url(old, new))
    # also try www / non-www twin
    if old.startswith("https://www."):
        twin = "https://" + old[len("https://www."):]
        print("twin", w.replace_site_url(twin, new))
    elif old.startswith("https://"):
        twin = "https://www." + old[len("https://"):]
        print("twin", w.replace_site_url(twin, new))
    if old.startswith("http://") and not old.startswith("http://www."):
        print("https twin", w.replace_site_url("https://" + old[len("http://"):], new))
PY
}

if [ -n "$OLD_URL" ] && [ -n "$NEW_URL" ]; then
  replace_urls "$OLD_URL" "$NEW_URL"
elif [ -n "$NEW_URL" ]; then
  replace_urls "AUTO" "$NEW_URL"
fi

# ── 6. Permissions ───────────────────────────────────────────
bash "$DEPLOY/fix-sites-403.sh" || true

# ── 7. Domain + SSL ──────────────────────────────────────────
if [ "$ENABLE_SSL" = "1" ]; then
  if [ -n "$SSL_EMAIL" ] && [ "$SSL_EMAIL" != "you@example.com" ]; then
    export CERTBOT_EMAIL="$SSL_EMAIL"
  fi
  bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN" --ssl || \
    bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN"
else
  bash "$DEPLOY/setup-domain.sh" "$SITE_NAME" "$DOMAIN"
fi

# ── 8. Firewall basics (if ufw present) ──────────────────────
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || true
  ufw allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  echo "[OK] ufw: 22/80/443 allowed (enable вручную: ufw enable)"
fi

# ── 9. Summary ───────────────────────────────────────────────
echo ""
echo "============================================"
echo "  ГОТОВО / ПРОВЕРЬ:"
echo "  Панель:  http://${IP}/"
echo "  Сайт IP: http://${IP}/sites/${SITE_NAME}/"
echo "  Домен:   http://${DOMAIN}/  и  https://${DOMAIN}/"
echo "  OLD→NEW: ${OLD_URL:-AUTO} → ${NEW_URL}"
echo "============================================"
echo "  DNS A @ и www → ${IP}"
echo "  nslookup ${DOMAIN}"
echo "============================================"
if [ -z "$SQL_FILE" ]; then
  echo "  SQL не импортирован скриптом — залей в панели или:"
  echo "  scp \"C:\\path\\backup.sql\" root@${IP}:/tmp/dump.sql"
  echo "  bash $DEPLOY/import-wp-sql.sh /tmp/dump.sql"
  echo "  SQL_FILE=/tmp/dump.sql bash $0"
fi
echo "============================================"
