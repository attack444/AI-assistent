# Что делать дальше — простыми словами

У тебя один сервер и два сайта. Картина такая:

1. **5mb2.ru** — сайт агентства (WordPress).  
2. **neobrain.site** — витрина продукта + панель управления.  
3. **Панель** открывается как `https://neobrain.site/console/` (отдельный домен для панели не нужен).

---

## Сейчас сломано / не доделано (проверка)

| Проблема | Простыми словами | Что сделать |
|---|---|---|
| У 5mb2.ru нет A-записи `@` | Корень сайта не указывает на сервер (www есть, «голое» имя — нет) | В reg.ru → 5mb2.ru → добавить **A**, имя **@**, IP **80.78.248.195** |
| neobrain.site показывает 5MB2 | На сервере ещё не включили отдельный «адрес» для NeoBrain | Команды ниже на VPS |
| Нет ключей в панели | Оплата, метрика, антибот сами не появятся | Панель → **Настройки** |

NS `ns1.reg.ru` / `ns2.reg.ru` у обоих доменов — это нормально. Проблема не в NS, а в записи **A @** у 5mb2.

---

## Твой порядок действий (делай сверху вниз)

### Шаг 1 — DNS (5 минут, в браузере)
reg.ru → домен **5mb2.ru** → зона DNS →  
**A** / хост **@** / значение **80.78.248.195** → сохранить.

### Шаг 2 — Сервер (один раз по SSH)

```bash
cd /opt/ai-helper
git fetch origin && git checkout cursor/neobrain-launch-17f9 && git pull

bash project/deploy/create-ai-site.sh
bash project/deploy/sync-5mb2-theme.sh

cd project/deploy
grep -q PANEL_BASE_PATH ../.env || echo 'PANEL_BASE_PATH=/console' >> ../.env
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

CERTBOT_EMAIL=твой@mail.ru sudo bash /opt/ai-helper/project/deploy/fix-neobrain-vhost.sh
sudo bash /opt/ai-helper/project/deploy/install-system-watchdog.sh
sudo bash /opt/ai-helper/project/deploy/install-backup-cron.sh
sudo bash /opt/ai-helper/project/deploy/install-seo-cron.sh
```

### Шаг 3 — Проверка глазами
- `https://neobrain.site/` — сайт **NeoBrain**, не 5MB2  
- `https://neobrain.site/console/` — твоя панель  
- `https://5mb2.ru/` — сайт агентства (после Шага 1)

### Шаг 4 — Панель → Настройки
Впиши (что есть под рукой):
- ЮKassa shopId + секрет → люди сами включают тариф  
- Яндекс.Метрика ID  
- Google Analytics `G-…` (по желанию)  
- meta-коды Google Search Console и Яндекс.Вебмастер  
- Cloudflare Turnstile (антибот)

Webhook ЮKassa: `https://neobrain.site/api/public/pay/webhook`

### Шаг 5 — Панель → SEO
- Жми **«Проверить сейчас»** — увидишь, что зелёное/красное  
- Жми **«Собрать черновики новостей»** — в WordPress появятся черновики  
- Раз в день заходи в WP → правь → **публикуй** (автоматом наружу не выкладываем)

### Шаг 6 — Поисковики (один раз)
1. [Яндекс.Вебмастер](https://webmaster.yandex.ru) — добавить оба сайта, вставить meta в Настройки, указать sitemap  
2. [Google Search Console](https://search.google.com/search-console) — то же  
   - 5mb2: `https://5mb2.ru/sitemap_index.xml`  
   - NeoBrain: `https://neobrain.site/sitemap.xml`

---

## Что уже автоматическое

- Мониторинг сайтов + безопасный ремонт (watchdog)  
- Бэкапы раз в сутки  
- Черновики SEO-новостей по cron / кнопке  
- Самооплата тарифов (после ключей ЮKassa)  
- Счётчики аналитики с панели на оба сайта  
- Антибот форм (после Turnstile)

## Что остаётся руками (и так правильно)

- DNS в reg.ru  
- Один деплой на VPS  
- Ключи в Настройках  
- Публикация статей  
- Ответы клиентам / оферта / реквизиты НПД

---

Подробности: `OPS_ROADMAP_RU.md`. Панель SEO: `/console/seo`.
