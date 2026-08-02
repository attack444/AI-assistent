# NeoBrain / 5MB2 — порядок, деньги, сервер, что не забыть

## 1. DNS сейчас (проверка)

Одинаковые NS (`ns1.reg.ru` / `ns2.reg.ru`) — ок. Но **записи A разные**:

| Имя | 5mb2.ru | neobrain.site |
|---|---|---|
| `@` (корень) | ❌ пусто у Google/Cloudflare и у ns1.reg.ru | ✅ 80.78.248.195 |
| `www` | ✅ 80.78.248.195 | ✅ 80.78.248.195 |

Это **не «ещё не обновилось»** для `5mb2.ru` `@`: у самого ns1.reg.ru ответа A нет.  
В панели reg.ru для **5mb2.ru** добавь: тип **A**, имя **@** (или пусто), значение **80.78.248.195**.

Поддомен `panel` **не обязателен**. Панель HTTPS: `https://neobrain.site/console/`.

---

## 2. HTTPS панели без отдельного домена

Let's Encrypt **не выдаёт сертификат на голый IP**. Варианты:

1. **Рекомендуем:** путь на домене витрины → `https://neobrain.site/console/` (сделано: `PANEL_BASE_PATH=/console` + nginx).
2. Поддомен `console` / `panel` — просто **создай A-запись** в reg.ru (это не «готовый продукт в списке», а обычный host).
3. Только IP + самоподписанный — браузеры будут ругаться, для клиентов плохо.

---

## 3. ЮKassa — сам себе тариф

Да: пользователь жмёт «Оплатить» → ЮKassa → webhook → план Starter/Pro сам.

Что нужно от тебя один раз:
1. Кабинет ЮKassa → shopId + секрет.
2. Панель → **Настройки** → вставить ключи (или `.env`).
3. В ЮKassa webhook: `https://neobrain.site/api/public/pay/webhook`.

Пока ключей нет — кнопка создаёт заявку «вручную»; после ключей — редирект на оплату.

---

## 4. Диски и приватная сеть — что можно

| Ресурс | Зачем |
|---|---|
| **Основной диск** | сайты, Docker, БД, бэкапы |
| **Второй диск / volume** | вынести MySQL, бэкапы ZIP, логи — меньше риск забить root |
| **Приватная сеть** | связать VPS между собой без интернета (БД на одном, app на другом); для одного сервера почти не нужна |
| **Снапшоты** | откат перед опасным деплоем |
| **Firewall** | наружу 80/443/22; MySQL/Redis только localhost или private |

**Как зарабатывать на этом стеке**
1. **NeoBrain SaaS** — Free → Starter/Pro (ЮKassa), деплой+чат+виджет.
2. **5MB2 услуги** — SEO-аудит/абонентка, заявки с сайта.
3. **White-label** — ставить NeoBrain клиентам на их VPS (разово + сопровождение).
4. **Хостинг «под ключ»** — 1–3 сайта + панель + SSL за фикс/мес.
5. **Агентство-мини** — DeepSeek правит сайты клиентов, ты продаёшь часы/пакеты.

Приоритет денег: (1) закрыть оплату NeoBrain + (2) лиды 5MB2 + (3) 2–3 платных пилота.

---

## 5. Где искать единомышленников

- Telegram: «индихакеры», «digital-агентства», «no-code / AI builders», чаты самозанятых IT  
- TenChat / VC.ru комментарии к AI-SaaS  
- Хабы Product Hunt / Indie Hackers (EN)  
- Локальные митапы SEO / веб в своём городе  
- Заказчики с Авито/FL как партнёры «ты SEO — я платформа»  
- Не искать «вдохновителей» бесконечно — один платящий клиент лучше десяти чатов

---

## 6. Поисковики и аналитика (делаем)

В панели **Настройки** вписываешь:
- Яндекс.Метрика ID  
- GA4 `G-…`  
- meta Google Search Console / Яндекс.Вебмастер  

Дальше коды сами попадут на NeoBrain и (через `/api/public/config`) на 5mb2.

Руками один раз:
1. [Google Search Console](https://search.google.com/search-console) → добавить `neobrain.site` и `5mb2.ru` → meta  
2. [Яндекс.Вебмастер](https://webmaster.yandex.ru) → то же  
3. Метрика / GA4 → счётчики → в Настройки  
4. Sitemap: `https://5mb2.ru/sitemap_index.xml` (Rank Math), для NeoBrain — простой `sitemap.xml` позже  
5. `robots.txt` уже есть у 5mb2; для NeoBrain добавить при необходимости  

---

## 7. Антиспам / боты

- Honeypot на формах ✅  
- **Cloudflare Turnstile** — ключи в Настройки → формы оплаты/feedback  
- Rate-limit публичного чата ✅  
- Wordfence на WP (логин) — уже может быть  
- Опционально: Cloudflare proxy (оранжевое облако) на DNS — WAF/бот-фильтр  

---

## 8. Что часто пропускают (чеклист «как часы»)

| # | Аспект | Статус |
|---|---|---|
| 1 | DNS A `@` для обоих доменов | neobrain ✅ / 5mb2 ❌ |
| 2 | Отдельный SSL на каждый домен | чинить vhost NeoBrain |
| 3 | HTTPS панели | `/console` |
| 4 | Бэкапы (сайты+БД) по cron | сделать |
| 5 | Мониторинг uptime | watchdog ✅ |
| 6 | Оферта + политика + cookies | нужно для оплаты |
| 7 | SMTP писем | настройки в панели |
| 8 | ЮKassa + webhook | самооплата |
| 9 | Аналитика + Вебмастер/GSC | настройки |
| 10 | Антибот Turnstile | настройки |
| 11 | Стейджинг перед правками | желательно |
| 12 | Лимиты диска / logrotate | проверить |
| 13 | 2FA панели / сильный PANEL_PASSWORD | обязательно |
| 14 | Юр. статус НПД на 5MB2 | реквизиты |
| 15 | Контент-план / SEO-страницы NeoBrain | рост |
| 16 | Поддержка (email/Telegram) на витрине | доверие |

---

## 9. Команды «навести порядок» на VPS

```bash
cd /opt/ai-helper
git fetch origin && git checkout cursor/neobrain-launch-17f9 && git pull

bash project/deploy/create-ai-site.sh
bash project/deploy/sync-5mb2-theme.sh

cd project/deploy
# панель по пути /console
echo 'PANEL_BASE_PATH=/console' >> ../.env
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

CERTBOT_EMAIL=you@mail.ru sudo bash /opt/ai-helper/project/deploy/fix-neobrain-vhost.sh
```

Потом: https://neobrain.site/ → витрина, https://neobrain.site/console/ → панель → **Настройки**.
