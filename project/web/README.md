# AI Helper Panel

Next.js панель сервера: чат, файловый менеджер, сайты.

## Локально

```bash
cd project/web
npm install
npm run dev
```

Открой http://localhost:3000  
API должен быть на :8502 (`python api.py` из `project/`).

## Production

Собирается сервисом `web` в `deploy/docker-compose.prod.yml`.
Nginx проксирует `/` → :3000, `/api/` → :8502, `/sites/` → `/var/ai-helper/sites/`.
