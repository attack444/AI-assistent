# Как перезапустить API AI Helper

Ошибка `Unit ai-helper-api.service not found` — нормально: **такого systemd-сервиса нет**.

API = контейнер Docker `app` (порт 8502).

## Быстро

```bash
cd /opt/ai-helper
git pull origin cursor/complete-ai-helper-17f9

# тема 5mb2
bash project/deploy/sync-5mb2-theme.sh

# витрина AI Helper
bash project/deploy/create-ai-site.sh

# API + панель (Next)
cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web
```

Или одной командой: `bash /opt/ai-helper/project/deploy/update.sh`

## Проверка

```bash
curl -sS http://127.0.0.1:8502/status
curl -sS http://127.0.0.1:8502/feedback -H "Authorization: Bearer ТОКЕН"   # inbox панели
```

Обратная связь в панели: `http://IP/feedback`  
На сайтах форма внизу страницы `#feedback`.
