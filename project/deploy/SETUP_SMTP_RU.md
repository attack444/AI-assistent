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

1. Зайди: https://connect.yandex.ru/ (Яндекс 360 / Почта для домена)  
2. Подключи домен `5mb2.ru`  
3. В DNS reg.ru добавь записи, которые покажет Яндекс (обычно):
   - **MX** → серверы Яндекса  
   - **TXT** SPF (типа `v=spf1 redirect=_spf.yandex.net`)  
   - иногда DKIM (TXT)  
4. Создай ящик `hello@5mb2.ru` (или `info@…`)  
5. Пароль приложения: https://id.yandex.ru → Безопасность → Пароли приложений → «Почта»

Старый хостинг reg.ru для почты больше не обязателен — MX переводишь на Яндекс.

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
| Encryption | SSL |
| Port | `465` |
| Auth | On |
| Username | `hello@5mb2.ru` |
| Password | пароль приложения |

3. Send a Test Email → себе на почту  
4. Настройки → Общие → E-mail администратора = ящик, куда падают **заявки**

---

## Шаг 3. Проверка

- Форма на сайте → письмо админу + запись в **Заявки 5MB2**  
- «Забыли пароль» на `/wp-login.php` → письмо приходит  

Если тест не уходит: пароль приложения (не обычный), порт 465/SSL, From = тот же ящик, что User.
