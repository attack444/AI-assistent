# Финальный аудит: безопасность, что работает, что убрать, панель, трафик

Дата проверки агентом: 2026-08-02 (после рабочего `/console`).

## 1. Работает как задумано

| Узел | Статус |
|---|---|
| 5mb2.ru HTTPS + WordPress | ✅ |
| neobrain.site витрина + SSL CN=neobrain.site | ✅ |
| Панель `https://neobrain.site/console/` | ✅ |
| API `/api/status` 2.14+ | ✅ |
| robots/sitemap оба сайта | ✅ |
| Яндекс verification в public/config | ✅ (уже вписан meta) |
| Unit tests | ✅ |

## 2. Безопасность — что было критично

**Найдено в живой проверке:** `auth_required: false` — API (`/fs/list`, `/system/settings`, …) открыт через `https://neobrain.site/api/` без пароля.

**Исправлено в коде 2.14:**
- автогенерация `PANEL_PASSWORD`, если пуст (`ALLOW_OPEN_PANEL=1` только для отладки);
- порты Docker → `127.0.0.1` (не 0.0.0.0);
- Streamlit выключен по умолчанию;
- Postgres/Redis — profile `extra` (не стартуют);
- webhook ЮKassa проверяет платёж через API GET;
- `upload_id` только hex;
- пароль MySQL не отдаётся в `/wp/status`;
- скрипт `harden-vps.sh` (UFW 22/80/443 + пароль в `.env`).

**Сделай на VPS сейчас:**

```bash
cd /opt/ai-helper && git pull origin cursor/neobrain-launch-17f9
sudo bash project/deploy/harden-vps.sh
cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web php
```

Потом войди в панель с новым паролем из вывода скрипта / `.env`.

**Ещё руками (среднее):**
- сменить дефолтные `MYSQL_*` пароли;
- Cloudflare Turnstile в Настройки;
- в nginx 5mb2: при желании `deny` на `xmlrpc.php`;
- Wordfence / лимит логина WP.

## 3. После GSC / Вебмастер / Метрики — финальный чеклист

1. Панель → Настройки: Метрика, GA4, meta GSC + Яндекс (Яндекс уже частично есть).
2. Вебмастер + GSC: оба домена, sitemap  
   - `https://5mb2.ru/sitemap_index.xml`  
   - `https://neobrain.site/sitemap.xml`
3. Запросить индексирование главных URL.
4. Панель → SEO → «Проверить сейчас» — зелёные robots/sitemap/title.
5. Turnstile + ЮKassa + webhook.
6. `install-seo-cron.sh` + `install-backup-cron.sh` + watchdog.
7. Оферта / политика на NeoBrain перед платными тарифами.

## 4. Что убрать / не раздувать

| Убрать или забыть | Почему |
|---|---|
| Streamlit `:8501` | Заменён панелью `/console` |
| `panel.neobrain.site` как обязательный | Не нужен |
| Postgres + Redis в проде | Не используются API |
| Куча старых `enable-https-5mb2` / `go-live` дублей | Оставить 1 путь: sync theme + repair |
| Документы «панель на IP / Streamlit first» | Актуальны `NEXT_STEPS_SIMPLE_RU.md` + этот файл |

**Не удалять из git сразу** — помечены / отключены. Чистку WP-core из репозитория — отдельной задачей.

## 5. Панель: упрощать или расширять

**Сначала упростить/защитить** (сделано частично): один вход `/console`, пароль обязателен, меньше сервисов.

**Потом расширять точечно:**
1. Overview: красный баннер «нет Turnstile / нет ЮKassa / слабый пароль».
2. SEO (есть) + пакет трафика (есть в разделе SEO).
3. Клиенты/тарифы: список public users + план.
4. Не плодить 10 пунктов меню.

## 6. Трафик — план (без вредного автоспама)

Подробно: `GROWTH_TRAFFIC_RU.md` + панель → SEO → блок «Трафик и размещение».

Идея: **полуавтомат** — готовые тексты и чеклист каналов; публикация руками. Массовые боты по каталогам — нет.
