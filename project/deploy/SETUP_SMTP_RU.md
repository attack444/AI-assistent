# SMTP для 5mb2.ru — без путаницы с SSL сайта

## Важно

| Что | Нужно? |
|---|---|
| HTTPS-сертификат сайта (`https://5mb2.ru`) | Уже стоит — для **браузера** |
| Отдельный SSL «для SMTP на VPS» | **Не нужен**, если шлём через Яндекс/Mail |
| Шифрование SMTP (SSL/TLS к `smtp.yandex.ru`) | Да — его даёт **Яндекс**, не твой nginx |

Сайт уже по HTTPS. Письма сайт будет отправлять **наружу** на SMTP Яндекса по защищённому каналу.

Рекомендуемый ящик: `hello@5mb2.ru` (или любой на домене) через **Яндекс 360 для домена** / Почта для домена.

---

## Шаг 1. Почта на домене (Яндекс)

**Сейчас у 5mb2.ru снаружи (проверка Aug 2026):**
- MX всё ещё на **старый хостинг**: `mx1/mx2.hosting.reg.ru`
- SPF тоже reg.ru (`ip4:31.31.197.13`)
- AAAA на IPv6 старого сервера ещё висит

Пока MX/SPF на reg.ru, ящик `hello@5mb2.ru` через Яндекс часто **не авторизуется** или письма уходят «криво».  
Либо подключаешь домен к Яндекс 360 и меняешь MX, либо для SMTP временно используешь обычный `@yandex.ru` / `@ya.ru`.

1. Зайди: https://connect.yandex.ru/ (Яндекс 360 / Почта для домена)  
2. Подключи домен `5mb2.ru`  
3. В DNS reg.ru **замени** (не дублируй со старыми mx.hosting.reg.ru):
   - **MX** → то, что даст Яндекс (обычно `mx.yandex.net` приоритет 10)
   - удали старые `mx1.hosting.reg.ru` / `mx2.hosting.reg.ru`
   - **TXT** SPF от Яндекса (замени старый SPF reg.ru)
   - DKIM (TXT), если Яндекс покажет  
4. Создай ящик `hello@5mb2.ru`  
5. Пароль приложения: https://id.yandex.ru → Безопасность → Пароли приложений → «Почта»

Старый хостинг reg.ru для почты больше не нужен.

---

## Шаг 2. Подключить WordPress (на VPS)

### Вариант А — скрипт (быстрее)

```bash
cd /opt/ai-helper
git pull origin cursor/complete-ai-helper-17f9

SMTP_USER='hello@5mb2.ru' \
SMTP_PASS='пароль-приложения-яндекс' \
SMTP_FROM_NAME='5MB2 Digital' \
bash project/deploy/setup-smtp-5mb2.sh
```

Скрипт пропишет константы WP Mail SMTP в `wp-config.php`, активирует плагин и отправит тест на admin email.

### Вариант B — руками в админке

1. https://5mb2.ru/wp-admin/ → плагины → **WP Mail SMTP** включён  
2. WP Mail SMTP → Settings → **Other SMTP**:

| Поле | Значение |
|---|---|
| From Email | `hello@5mb2.ru` |
| From Name | `5MB2 Digital` |
| Host | `smtp.yandex.ru` |
| Encryption | **TLS** |
| Port | **587** |
| Auth | On |
| Username | `hello@5mb2.ru` |
| Password | пароль приложения |

(Альтернатива Яндекса: SSL + порт 465 — тоже работает; TLS/587 предпочтительнее.)

3. Send a Test Email → себе на почту  
4. Настройки → Общие → E-mail администратора = ящик, куда падают **заявки**

---

## Шаг 3. Проверка

- Форма на сайте → письмо админу + запись в **Заявки 5MB2**  
- «Забыли пароль» на `/wp-login.php` → письмо приходит  

## Если тест падает

На VPS:
```bash
bash project/deploy/diagnose-smtp-5mb2.sh
```

Частые причины:

| Симптом | Что сделать |
|---|---|
| Could not connect / Connection timed out | Хостинг режет порт → в плагине **SSL + 465** вместо TLS/587 |
| Authentication failed / 535 | Пароль **приложения** Яндекса, не обычный |
| From address rejected | From Email = тот же, что Username |
| FAIL из Docker php | Исходящие 465/587 с контейнера — см. вывод diagnose |

Текст ошибки: WP Mail SMTP → **Email Test** (или Tools) — скопируй целиком.
