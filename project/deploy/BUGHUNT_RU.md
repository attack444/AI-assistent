# Багхант — что нашли и что сделать на VPS

## Уже чинили раньше
1. Петля `/console` ↔ `/console/`
2. 401 → витрина `/login` вместо `/console/login/`
3. `PANEL_PASSWORD` затирался пустым `${PANEL_PASSWORD:-}` в compose

## Волна 2.16 — кабинет, пароли, OAuth, интеграции

1. Пароли кабинета только пользовательские (регистрация + смена в UI); OAuth-only может задать пароль без «текущего».
2. Панель: нет тихой автогенерации `PANEL_PASSWORD` (нужен `.env` / `reset-panel-password.sh`; `ALLOW_AUTO_PANEL_PASSWORD=1` только явно).
3. Google / GitHub OAuth: start/callback + поля в Настройках; callback кладёт токен в `localStorage`, не в query.
4. SMTP mailer: письмо после регистрации; флаги `smtp_ready` / oauth в `/public/config`.
5. Оплата: email только из сессии; Turnstile на register.
6. Документы: `PROFESSIONAL_CHECKS_RU.md`, `COMPETITIVE_GAP_RU.md`.

## Новая волна (2.15)

| # | Баг | Риск |
|---|---|---|
| 1 | **Порты 3000/8502/3306/9000/11434 открыты в интернет** (на VPS ещё старый compose) | CRITICAL |
| 2 | MySQL пароли из `${:-default}`, не из `project/.env` | HIGH |
| 3 | Главная `/console/` дергает `/sites` → 401 → выкид на login | HIGH |
| 4 | Watchdog с хоста видит IP `172.x`, не localhost → 401 cron | HIGH |
| 5 | Next rewrite `/api` под basePath `/console/api` | HIGH |
| 6 | `panel.*` проксировал на `/` без basePath | MEDIUM |
| 7 | AuthGate любой сетевой сбой = «выйти» | MEDIUM |

## Команды на VPS (обязательно)

```bash
cd /opt/ai-helper && git pull origin cursor/neobrain-launch-17f9
sudo bash project/deploy/harden-vps.sh
sudo bash project/deploy/reset-panel-password.sh   # если не помнишь пароль
cd project/deploy
docker compose --env-file ../.env -f docker-compose.prod.yml build app web
docker compose --env-file ../.env -f docker-compose.prod.yml up -d --force-recreate
SKIP_CERTBOT=1 sudo bash fix-neobrain-vhost.sh
```

Проверка портов с ноутбука (должны быть closed):

```bash
nc -vz 80.78.248.195 8502
nc -vz 80.78.248.195 3000
nc -vz 80.78.248.195 3306
```
