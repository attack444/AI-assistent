#!/bin/bash
# ============================================================
#  AI Helper — Автоматическая установка на Ubuntu 22.04 VPS
#  Запуск: bash setup.sh
# ============================================================
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${GREEN}[OK]${NC} $1"; }
info() { echo -e "${CYAN}[>>]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }
err()  { echo -e "${RED}[ERR]${NC} $1"; exit 1; }

echo -e "${CYAN}"
echo "╔══════════════════════════════════════════════╗"
echo "║        AI Helper — Установка на VPS          ║"
echo "╚══════════════════════════════════════════════╝"
echo -e "${NC}"

# ── Проверка Ubuntu ──────────────────────────────────────────
[[ "$(lsb_release -si 2>/dev/null)" == "Ubuntu" ]] || err "Скрипт только для Ubuntu"
info "Ubuntu $(lsb_release -sr)"

# ── Обновление системы ───────────────────────────────────────
info "Обновляю систему..."
apt-get update -q && apt-get upgrade -y -q
log "Система обновлена"

# ── Базовые пакеты ───────────────────────────────────────────
info "Устанавливаю базовые пакеты..."
apt-get install -y -q \
    git curl wget unzip nano htop \
    ca-certificates gnupg lsb-release \
    ufw fail2ban
log "Базовые пакеты установлены"

# ── Docker ───────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
    info "Устанавливаю Docker..."
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker.gpg] \
        https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-compose-plugin
    systemctl enable docker
    log "Docker установлен: $(docker --version)"
else
    log "Docker уже установлен: $(docker --version)"
fi

# ── Node.js 20 ───────────────────────────────────────────────
if ! command -v node &>/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 18 ]]; then
    info "Устанавливаю Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y -q nodejs
    log "Node.js установлен: $(node -v)"
else
    log "Node.js уже установлен: $(node -v)"
fi

# ── Firewall ─────────────────────────────────────────────────
info "Настраиваю файрвол..."
ufw allow ssh
ufw allow http
ufw allow https
# 8501/8502/3000/9000/3306 слушают только 127.0.0.1 (docker) — наружу не открываем
ufw --force enable
log "Файрвол настроен"

# ── Папка сайтов ─────────────────────────────────────────────
mkdir -p /var/ai-helper/sites
chmod 755 /var/ai-helper/sites
log "Папка сайтов: /var/ai-helper/sites"

# ── Клонирование репозитория ─────────────────────────────────
REPO_DIR="/opt/ai-helper"
if [ -d "$REPO_DIR" ]; then
    info "Обновляю репозиторий..."
    cd "$REPO_DIR" && git pull
else
    info "Клонирую репозиторий..."
    git clone https://github.com/attack444/AI-assistent "$REPO_DIR"
fi
cd "$REPO_DIR/project"
log "Репозиторий: $REPO_DIR"

# ── .env файл ────────────────────────────────────────────────
ENV_FILE="$REPO_DIR/project/.env"
if [ ! -f "$ENV_FILE" ]; then
    info "Создаю .env из шаблона..."
    cp "$REPO_DIR/project/deploy/.env.example" "$ENV_FILE"
fi

# Сгенерировать секреты, если остались плейсхолдеры / пустые значения
_set_env() {
    local key="$1" val="$2"
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
    else
        echo "${key}=${val}" >> "$ENV_FILE"
    fi
}
if ! grep -qE '^PANEL_PASSWORD=.+' "$ENV_FILE" \
   || grep -qE '^PANEL_PASSWORD=(change_me_panel_password)?$' "$ENV_FILE"; then
    PANEL_GEN="$(openssl rand -hex 16)"
    _set_env PANEL_PASSWORD "$PANEL_GEN"
    warn "Сгенерирован PANEL_PASSWORD (сохрани): $PANEL_GEN"
fi
if ! grep -qE '^SECRET_KEY=.+' "$ENV_FILE" \
   || grep -qE '^SECRET_KEY=(change_me_to_random_string_min_32_chars|dev-insecure-change-me)?$' "$ENV_FILE"; then
    _set_env SECRET_KEY "$(openssl rand -hex 32)"
    log "Сгенерирован SECRET_KEY"
fi
if ! grep -qE '^MYSQL_ROOT_PASSWORD=.+' "$ENV_FILE" \
   || grep -qE '^MYSQL_ROOT_PASSWORD=(root_change_me)?$' "$ENV_FILE"; then
    _set_env MYSQL_ROOT_PASSWORD "$(openssl rand -hex 16)"
    log "Сгенерирован MYSQL_ROOT_PASSWORD"
fi
if ! grep -qE '^MYSQL_PASSWORD=.+' "$ENV_FILE" \
   || grep -qE '^MYSQL_PASSWORD=(wp_change_me)?$' "$ENV_FILE"; then
    _set_env MYSQL_PASSWORD "$(openssl rand -hex 16)"
    log "Сгенерирован MYSQL_PASSWORD"
fi
if ! grep -qE '^POSTGRES_PASSWORD=.+' "$ENV_FILE" \
   || grep -qE '^POSTGRES_PASSWORD=(change_me_postgres_123|changeme123)?$' "$ENV_FILE"; then
    _set_env POSTGRES_PASSWORD "$(openssl rand -hex 16)"
    log "Сгенерирован POSTGRES_PASSWORD"
fi

# Compose host interpolation читает deploy/.env — симлинк на project/.env
ln -sfn "$ENV_FILE" "$REPO_DIR/project/deploy/.env"

# ── Docker Compose запуск ────────────────────────────────────
info "Запускаю сервисы через Docker Compose..."
cd "$REPO_DIR/project/deploy"
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml up -d --build
log "Сервисы запущены"

# ── Nginx ────────────────────────────────────────────────────
if ! command -v nginx &>/dev/null; then
    info "Устанавливаю Nginx..."
    apt-get install -y -q nginx
fi
cp "$REPO_DIR/project/deploy/nginx.conf" /etc/nginx/sites-available/ai-helper
ln -sf /etc/nginx/sites-available/ai-helper /etc/nginx/sites-enabled/ai-helper
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
log "Nginx настроен"

# ── Итог ─────────────────────────────────────────────────────
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════╗"
echo -e "║              Установка завершена!            ║"
echo -e "╠══════════════════════════════════════════════╣"
echo -e "║  Панель:  http://${SERVER_IP}/                "
echo -e "║  Файлы:   http://${SERVER_IP}/files           "
echo -e "║  Сайты:   http://${SERVER_IP}/sites           "
echo -e "║  Чат:     http://${SERVER_IP}/chat            "
echo -e "║  API:     http://${SERVER_IP}/api/status      "
echo -e "║  Legacy:  http://${SERVER_IP}/legacy/         "
echo -e "╠══════════════════════════════════════════════╣"
echo -e "║  Это и есть интерфейс сервера: http://IP/    ║"
echo -e "║  См. deploy/ACCESS.md                        ║"
echo -e "║  1. PANEL_PASSWORD в .env                    ║"
echo -e "║  2. bash deploy/update.sh                    ║"
echo -e "║  3. Перенеси сайт: MIGRATE_SITE.md           ║"
echo -e "╚══════════════════════════════════════════════╝${NC}"
