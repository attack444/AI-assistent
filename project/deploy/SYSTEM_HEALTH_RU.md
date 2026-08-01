# Здоровье системы: 5mb2 + AI Helper + панель

## Зачем

Пока правим сайты и WP, возможны сбои. Watchdog каждые 2 минуты проверяет:

1. **API панели** (порт 8502) — приоритет  
2. **DeepSeek** (ключ в `/status`) — приоритет для ремонта  
3. **Панель Next** (порт 3000) — приоритет  
4. **https://5mb2.ru/**, кабинет, `?mb2_health=1`  
5. **/sites/ai/**

При сбое:

- пишет инцидент в `~/.ai-helper/system_incidents.jsonl` и в «Обратная связь»  
- безопасный restart: `ai-helper-app`, `ai-helper-web`, `ai-helper-php`  
- опционально зовёт **DeepSeek** (не бесплатную 1.5b) разобрать и починить tools’ами  

В панели: раздел **«Здоровье»**.

## Установка на VPS

```bash
cd /opt/ai-helper
git pull origin cursor/complete-ai-helper-17f9   # или main после merge

# тема + mu-plugin health
bash project/deploy/sync-5mb2-theme.sh
bash project/deploy/create-ai-site.sh

cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

# cron watchdog
sudo bash project/deploy/install-system-watchdog.sh
```

Проверка вручную:

```bash
bash project/deploy/system-watchdog.sh
bash project/deploy/system-watchdog.sh --ask-deepseek
curl -sS http://127.0.0.1:8502/status | head -c 400; echo
```

## Почему «сайт лёг» после правок в WP

Тема до 1.9.6 на **каждом** фронтовом запросе при смене версии гоняла `mb2_ensure_site_structure()` и **перезаписывала** страницы/`Rank Math`. После сохранения SEO в админке сайт мог уходить в таймаут.

С 1.9.6:

- seed только в `admin_init` / деплой-скрипте  
- существующие страницы и SEO meta **не затираются**  
- mu-plugin `mb2-health-guard` отдаёт `/?mb2_health=1` и пишет фаталы в `wp-content/mb2-fatal.log`

## Переменные

| Переменная | Смысл |
|---|---|
| `WATCHDOG_BASE_URL` | База для 5mb2/ai (часто `https://127.0.0.1`) |
| `WATCHDOG_HOST_HEADER` | `5mb2.ru` |
| `WATCHDOG_ALLOW_RESTART` | `1` — docker restart |
| `WATCHDOG_ASK_DEEPSEEK` | `1` — разрешить AI-ремонт |
| `LLM_PREFER_FREE=0` | в cron ремонта — DeepSeek в приоритете |

Публичный виджет может по-прежнему использовать free LLM; ремонт инфраструктуры — через DeepSeek.
