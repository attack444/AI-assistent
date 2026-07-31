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
ufw allow 8501   # Streamlit (legacy)
ufw allow 8502   # AI Helper API
ufw allow 3000   # Next.js panel (также через Nginx :80)
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
if [ ! -f "$REPO_DIR/project/.env" ]; then
    info "Создаю .env из шаблона..."
    cp "$REPO_DIR/project/deploy/.env.example" "$REPO_DIR/project/.env"
    warn "Отредактируй $REPO_DIR/project/.env — добавь API ключи!"
fi

# ── Docker Compose запуск ────────────────────────────────────
info "Запускаю сервисы через Docker Compose..."
cd "$REPO_DIR/project/deploy"
docker compose -f docker-compose.prod.yml up -d --build
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
echo -e "║  API:     http://${SERVER_IP}/api/status      "
echo -e "║  Legacy:  http://${SERVER_IP}/legacy/         "
echo -e "╠══════════════════════════════════════════════╣"
echo -e "║  Следующие шаги:                             ║"
echo -e "║  1. Отредактируй .env (DeepSeek ключ)        ║"
echo -e "║  2. docker compose up -d --build             ║"
echo -e "║  3. Перенеси сайт: deploy/MIGRATE_SITE.md    ║"
echo -e "╚══════════════════════════════════════════════╝${NC}"
