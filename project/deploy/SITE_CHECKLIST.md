# Что написать агенту / заполнить на сервере

Скопируй блок, замени значения, пришли в чат **или** сохрани на VPS как `site.env`.

```
SITE_NAME=          # имя папки сайта в панели, латиница (например mysite)
DOMAIN=5mb2.ru
OLD_URL=https://5mb2.ru
NEW_URL=https://5mb2.ru
SQL_FILE=           # если дамп уже на VPS: /tmp/dump.sql  (иначе пусто — лей в панели)
MYSQL_PASSWORD=     # латиница, например WpPass123
MYSQL_ROOT_PASSWORD= # латиница, например RootPass123
ENABLE_SSL=1
SSL_EMAIL=          # твой email для Let's Encrypt
VPS_IP=             # IP сервера
PANEL_PASSWORD=     # пароль входа в панель (если ещё не задан)
```

Дополнительно полезно:
- точное имя сайта в панели (как в `/sites/...`)
- лежит ли ZIP/файлы уже на VPS
- смотрит ли DNS `5mb2.ru` уже на VPS или ещё на старый хостинг

## Одна команда на VPS после заполнения

```bash
bash /opt/ai-helper/project/deploy/update.sh
cp /opt/ai-helper/project/deploy/site.env.example /opt/ai-helper/project/deploy/site.env
nano /opt/ai-helper/project/deploy/site.env
bash /opt/ai-helper/project/deploy/finish-site.sh
```

`finish-site.sh` делает: MySQL пароли, wp-config, импорт SQL (если путь указан), замену URL, права, nginx-домен, SSL, ufw 80/443.
