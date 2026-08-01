# Ревью 5mb2 (кратко)

Стек: WordPress + **Astra** + **Elementor** + формы/SEO/кэш + **4 AI-плагина сразу**.

## Срочно (безопасность)

1. В git попал `wp-config.php.bak-aihelper` с паролем БД старого хостинга — **файл удалён из репо**.
2. На VPS удали бэкап: `rm -f /var/ai-helper/sites/5mb2/wp-config.php.bak-aihelper`
3. Если этот пароль ещё где-то используется — **смени пароль БД**.
4. Соли AUTH_* тоже были в файле — после смены пароля можно перегенерировать keys на [api.wordpress.org/secret-key](https://api.wordpress.org/secret-key/1.1/salt/).

## Производительности / порядок

| Наблюдение | Рекомендация |
|------------|--------------|
| AI-плагины и WPForms | **Удалены** из репо (и скрипт `deploy/cleanup-5mb2-plugins.sh` для VPS). Формы: Contact Form 7 + Flamingo. Чат — виджет AI Helper. |
| Тема Astra без child-theme | Кастом — через Astra/Elementor или завести `astra-child`, не править ядро темы. |
| Wordfence + `wordfence-waf.php` | На новом VPS проверь, что WAF не ломает сайт; auto_prepend чистили скриптами раньше. |
| `wp-fastest-cache` + Imagify | Норм; после деплоя очисти кэш. |
| CF7 + WPForms | Дубль форм — оставь одну систему. |
| Popup Maker + Essential Addons | Ок, но следи за весом страниц. |

## Контент / фичи под сайт

- Виджет AI Helper (наш): `install-5mb2-widget.sh` — гостевой чат без этих 4 плагинов.
- HTTPS для 5mb2.ru — отдельным шагом.
- Контент страниц в БД (не в git) — правки через админку WP или панель + DeepSeek.

## Что не ревьюилось как «ваш код»

Ядро `wp-admin` / `wp-includes` и чужие плагины — стандартные. Кастомной child-темы почти нет: сайт собран конструктором.
