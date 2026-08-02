#!/usr/bin/env bash
# NeoBrain: домен neobrain.site → сайт ai + SSL; panel.neobrain.site → панель + SSL.
#
# Перед запуском DNS:
#   A  @                 → IP VPS
#   A  www               → IP VPS
#   A  panel             → IP VPS   (обязательно для HTTPS панели)
#
#   sudo bash project/deploy/enable-neobrain.sh
#   CERTBOT_EMAIL=you@mail.ru sudo bash project/deploy/enable-neobrain.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOMAIN="${NEOBRAIN_DOMAIN:-neobrain.site}"
PANEL_DOMAIN="${NEOBRAIN_PANEL_DOMAIN:-panel.${DOMAIN}}"
SITE_NAME="${SITE_NAME:-ai}"
SITES_DIR="${SITES_DIR:-/var/ai-helper/sites}"
SITE_ROOT="$SITES_DIR/$SITE_NAME"
EMAIL="${CERTBOT_EMAIL:-admin@${DOMAIN}}"
IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "==> NeoBrain go-live"
echo "    site:   https://${DOMAIN}/  (папка ${SITE_NAME})"
echo "    panel:  https://${PANEL_DOMAIN}/"
echo "    IP:     ${IP}"
echo ""

# 0) витрина
bash "$SCRIPT_DIR/create-ai-site.sh"

# 1) DNS sanity
echo "==> DNS check"
for d in "$DOMAIN" "www.$DOMAIN" "$PANEL_DOMAIN"; do
  got=$(dig +short @8.8.8.8 "$d" A 2>/dev/null | head -1 || true)
  echo "    $d → ${got:-нет A}"
done
A_MAIN=$(dig +short @8.8.8.8 "$DOMAIN" A 2>/dev/null | head -1 || true)
if [ -z "$A_MAIN" ]; then
  echo "[ERR] Нет A у $DOMAIN. Настрой DNS и подожди пропагацию."
  exit 1
fi
PANEL_A=$(dig +short @8.8.8.8 "$PANEL_DOMAIN" A 2>/dev/null | head -1 || true)
if [ -z "$PANEL_A" ]; then
  echo "[!!] Нет A у $PANEL_DOMAIN — добавь запись: A panel → $IP"
  echo "     Сайт подниму сейчас; панель HTTPS — после DNS (перезапусти скрипт)."
fi

# 2) домен сайта + SSL
echo "==> сайт ${SITE_NAME} → ${DOMAIN}"
bash "$SCRIPT_DIR/setup-domain.sh" "$SITE_NAME" "$DOMAIN" --ssl

# 2b) на vhost сайта — /api → AI API (виджет + ЮKassa webhook)
SITE_NGX="/etc/nginx/sites-available/ai-helper-${SITE_NAME}.conf"
if [ -f "$SITE_NGX" ] && ! grep -q 'location /api/' "$SITE_NGX" 2>/dev/null; then
  echo "==> добавляю /api proxy в vhost ${DOMAIN}"
  python3 - <<'PY'
from pathlib import Path
p = Path("/etc/nginx/sites-available/ai-helper-ai.conf")
# try common names
cands = list(Path("/etc/nginx/sites-available").glob("ai-helper-*.conf"))
snippet = """
    location /api/ {
        proxy_pass         http://127.0.0.1:8502/;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   Connection        "";
        proxy_read_timeout 600s;
        proxy_buffering    off;
        chunked_transfer_encoding on;
    }
"""
for conf in cands:
    text = conf.read_text(encoding="utf-8", errors="ignore")
    if "location /api/" in text:
        continue
    if "neobrain-panel" in conf.name:
        continue
    # insert after server_name line block's first location or after client_max
    if "client_max_body_size" in text:
        text = text.replace(
            "client_max_body_size 200M;",
            "client_max_body_size 200M;\n" + snippet,
            1,
        )
    else:
        text = text.replace("server {", "server {" + snippet, 1)
    conf.write_text(text, encoding="utf-8")
    print("[OK] /api →", conf)
PY
  nginx -t && systemctl reload nginx || true
fi

# 3) nginx панель
PANEL_CONF="/etc/nginx/sites-available/neobrain-panel.conf"
cat > "$PANEL_CONF" <<EOF
# NeoBrain owner panel → ${PANEL_DOMAIN}
server {
    listen 80;
    listen [::]:80;
    server_name ${PANEL_DOMAIN};

    client_max_body_size 200M;

    location /api/ {
        proxy_pass         http://127.0.0.1:8502/;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Connection        "";
        proxy_read_timeout 600s;
        proxy_buffering    off;
        chunked_transfer_encoding on;
    }

    location / {
        proxy_pass         http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Upgrade           \$http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout 600s;
    }
}
EOF
ln -sf "$PANEL_CONF" /etc/nginx/sites-enabled/neobrain-panel.conf
nginx -t
systemctl reload nginx
echo "[OK] HTTP panel: http://${PANEL_DOMAIN}/"

if [ -n "$PANEL_A" ]; then
  if ! command -v certbot &>/dev/null; then
    apt-get update -q
    apt-get install -y -q certbot python3-certbot-nginx
  fi
  echo "==> SSL для панели ${PANEL_DOMAIN}"
  certbot --nginx -d "$PANEL_DOMAIN" --non-interactive --agree-tos \
    --email "$EMAIL" --redirect \
    || certbot --nginx -d "$PANEL_DOMAIN" --agree-tos --email "$EMAIL" --redirect \
    || certbot --nginx -d "$PANEL_DOMAIN" --non-interactive --agree-tos \
      --register-unsafely-without-email --redirect
  echo "[OK] HTTPS panel: https://${PANEL_DOMAIN}/"
else
  echo "[SKIP] SSL панели — нет DNS A для ${PANEL_DOMAIN}"
fi

# 4) .env маркеры
ENV_FILE="${ENV_FILE:-$REPO_DIR/project/.env}"
touch "$ENV_FILE"
python3 - <<PY
from pathlib import Path
p = Path("$ENV_FILE")
text = p.read_text(encoding="utf-8") if p.exists() else ""
keys = {
    "NEOBRAIN_DOMAIN": "$DOMAIN",
    "NEOBRAIN_PANEL_DOMAIN": "$PANEL_DOMAIN",
    "VPS_PUBLIC_IP": "$IP",
    "PUBLIC_SITE_URL": "https://$DOMAIN",
    "BRAND_NAME": "NeoBrain",
}
lines = text.splitlines()
out, seen = [], set()
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
print("[OK] .env обновлён:", p)
PY

echo "$DOMAIN" > "$SITE_ROOT/.ai-helper-domain"
echo "NeoBrain" > "$SITE_ROOT/.neobrain-brand" 2>/dev/null || true

echo ""
echo "============================================"
echo "  NeoBrain сайт:  https://${DOMAIN}/"
echo "  Панель:         https://${PANEL_DOMAIN}/  (если DNS panel готов)"
echo "  Старый путь:    https://${IP}/sites/ai/ (можно оставить)"
echo "============================================"
echo "Дальше:"
echo "  1) A panel → ${IP} (если ещё нет)"
echo "  2) git pull + rebuild app/web (ребрендинг)"
echo "  3) OWNER_EMAIL / ЮKassa — см. LAUNCH_NEOBRAIN_RU.md"
echo "============================================"
