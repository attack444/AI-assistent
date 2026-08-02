# NeoBrain — запуск на neobrain.site

## DNS (уже ок у тебя)

| Запись | Значение |
|---|---|
| NS | `ns1.reg.ru` / `ns2.reg.ru` |
| A `@` | `80.78.248.195` |
| A `www` | `80.78.248.195` |
| **A `panel`** | `80.78.248.195` ← **добавь**, иначе HTTPS панели не выпустится |

Проверка: `dig +short neobrain.site A` → IP VPS.

## Одна команда на VPS

```bash
cd /opt/ai-helper
git pull origin cursor/neobrain-launch-17f9

# ребрендинг + код
bash project/deploy/create-ai-site.sh
cd project/deploy
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d --force-recreate app web

# домен сайта + SSL + nginx панели
CERTBOT_EMAIL=ТВОЙ@EMAIL.ru sudo bash project/deploy/enable-neobrain.sh
```

Итог:
- **https://neobrain.site/** — витрина NeoBrain  
- **https://panel.neobrain.site/** — панель владельца (после A `panel`)  
- `/sites/ai/` на IP — запасной путь  

## Что уже есть (можно «мягко» запускать)

| Компонент | Статус |
|---|---|
| VPS + Docker + DeepSeek | готово |
| Витрина + регистрация + чат + деплой ZIP | готово |
| Тарифы Free / Starter / Pro (лимиты) | готово |
| Панель: сайты, файлы, чат, обзор, DNS, watchdog | готово |
| Домен neobrain.site → VPS | DNS ок |
| HTTPS сайта / панели | скрипт `enable-neobrain.sh` |
| Ребрендинг AI Helper → NeoBrain | в этой ветке |

## Что ещё нужно до «полного» платного запуска

| Пункт | Зачем |
|---|---|
| **A `panel` → IP** | HTTPS панели |
| **ЮKassa** `YOOKASSA_SHOP_ID` + `YOOKASSA_SECRET_KEY` в `.env` | автооплата Starter/Pro |
| Webhook ЮKassa → `https://neobrain.site/api/public/pay/webhook` | автоактивация плана |
| **OWNER_EMAIL** в `.env` | владелец без лимитов |
| **Оферта / политика** на neobrain.site (страницы или PDF) | юр. для оплаты |
| **SMTP** (письма регистрации/чеки) | доверие, восстановление |
| 5mb2: DNS A на VPS (если ещё hosting.reg.ru) | отдельно от NeoBrain |
| Контент/SEO продвижение NeoBrain | трафик |

Без ЮKassa можно стартовать **вручную**: пользователь пишет → ты `set-plan` в панели/API.

```bash
curl -s -X POST https://panel.neobrain.site/api/public/admin/set-plan \
  -H "Authorization: Bearer PANEL_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@mail.ru","plan":"starter"}'
```

## ЮKassa — подключение

1. Кабинет [yookassa.ru](https://yookassa.ru) → shopId + секретный ключ.  
2. В `/opt/ai-helper/project/.env`:
```env
YOOKASSA_SHOP_ID=...
YOOKASSA_SECRET_KEY=...
PUBLIC_SITE_URL=https://neobrain.site
NEOBRAIN_DOMAIN=neobrain.site
NEOBRAIN_PANEL_DOMAIN=panel.neobrain.site
OWNER_EMAIL=твой@email.ru
```
3. `docker compose … up -d --force-recreate app`  
4. В ЮKassa укажи HTTP-уведомления: `https://neobrain.site/api/public/pay/webhook`  
   (прокси `/api` должен идти на сайт или отдельный location — на vhost сайта добавь proxy `/api` как на панели, см. ниже)

Если на `neobrain.site` нет `/api`, добавь в nginx vhost сайта location `/api/` → `8502` (как в `enable-neobrain.sh` для panel) или принимай webhook на `https://panel.neobrain.site/api/public/pay/webhook`.

API:
- `GET /public/pay/status` — настроена ли ЮKassa  
- `POST /public/pay/create` `{"email","plan":"starter"|"pro"}`  
- `POST /public/pay/webhook` — от ЮKassa  

Пока ключей нет — `create` пишет заявку в лог и отвечает «оплата вручную».

## Можно ли уже продвигать?

**Да — soft launch:** регистрация, Free, демо деплоя, ручная активация Starter/Pro, сбор обратной связи.  
**Полный платный автозапуск** — после ЮKassa + оферта + SMTP + panel HTTPS.

Продвижение: не жди идеала. Закрой DNS panel → SSL → витрина → 5–10 тестовых юзеров → ЮKassa.

## Проверка после деплоя

```bash
curl -sI https://neobrain.site/ | head -5
curl -sS https://neobrain.site/ | grep -o 'NeoBrain' | head -3
curl -sS http://127.0.0.1:8502/status | head -c 300; echo
curl -sS http://127.0.0.1:8502/public/pay/status
bash project/deploy/system-watchdog.sh
```
