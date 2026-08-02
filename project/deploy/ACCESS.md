# Где интерфейс сервера

Подставь вместо `IP` адрес своего VPS (тот же, что был у Streamlit).

## Главное

| Что | Адрес |
|---|---|
| **Панель (интерфейс сервера)** | **`http://IP/`** |
| Файлы | `http://IP/files` |
| Сайты | `http://IP/sites` |
| Чат | `http://IP/chat` |
| Твой сайт после деплоя | `http://IP/sites/ИМЯ/` |

Пример: если IP `95.163.xxx.xxx`, открываешь в браузере:

```text
http://95.163.xxx.xxx/
```

Это и есть интерфейс «как на хостинге».

## Важно

Пока на VPS **не сделаешь** `git pull` + пересборку Docker — по `http://IP/` ещё старый Streamlit или пусто.  
После обновления Nginx отдаёт **Next.js-панель** на порту **80**.

Команда обновления:

```bash
bash /opt/ai-helper/project/deploy/update.sh
```

Или вручную — см. ниже в `update.sh`.

## Перезапуск API (нет systemd-юнита `ai-helper-api`)

API крутится в Docker (`app` в `docker-compose.prod.yml`), не как `systemctl` сервис.

```bash
cd /opt/ai-helper/project/deploy
docker compose -f docker-compose.prod.yml up -d --force-recreate app
# или всё сразу:
bash /opt/ai-helper/project/deploy/update.sh
```

Проверка: `curl -sS http://127.0.0.1:8502/status | head -c 200`

## Другие адреса

| Что | Адрес |
|---|---|
| API статус | `http://IP/api/status` |
| API локально (не снаружи) | `http://127.0.0.1:8502/status` |
| Панель локально (не снаружи) | `http://127.0.0.1:3000` |
| Здоровье (панель) | `http://IP/health` |
| Watchdog | `bash project/deploy/system-watchdog.sh` (см. `SYSTEM_HEALTH_RU.md`) |

Публичные превью `/sites/p…/` отдают CSP `sandbox` (без `allow-same-origin`), чтобы JS деплоя не читал токен панели.  
Панель (файлы/чат/управление) — по паролю `PANEL_PASSWORD` из `.env` (пустой пароль = закрыто).  
Порты `8501` / `8502` / `3000` / `9000` / `3306` слушают только `127.0.0.1`. Legacy Streamlit по умолчанию выключен (`ENABLE_STREAMLIT=0`); `/legacy/` отдаёт 404.
