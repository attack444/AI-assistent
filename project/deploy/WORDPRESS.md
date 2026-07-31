# WordPress на AI Helper VPS

Сайт WordPress — это не просто HTML: нужны **файлы + PHP + MySQL**.

## Куда деваются файлы

| Где смотреть | Путь |
|---|---|
| На VPS (хост) | `/var/ai-helper/sites/mysite/` |
| В Docker (API) | `/opt/sites/mysite/` |
| Незавершённый ZIP | `/var/ai-helper/sites/.uploads/` |
| Публичный URL | `http://IP/sites/mysite/` |

В панели: **Сайты → «Найти файлы»**.

Если «0 файлов» — ZIP не доехал или не распаковался. Залей снова через мастер (chunked, до ~2 ГБ).

## Что выгрузить со старого хостинга

1. **Файлы** — ZIP `public_html` (или бэкап WordPress)
2. **База** — phpMyAdmin → Export → `.sql`

Только файлов мало: без БД WP покажет «ошибка установления соединения с базой».

## Перенос

### 1. Обнови сервер

```bash
bash /opt/ai-helper/project/deploy/update.sh
bash /opt/ai-helper/project/deploy/fix-sites-403.sh
```

В `.env` добавь (если нет):

```bash
MYSQL_ROOT_PASSWORD=strong_root_pass
MYSQL_DATABASE=wordpress
MYSQL_USER=wp
MYSQL_PASSWORD=strong_wp_pass
PANEL_PASSWORD=panel_password
```

> Пароли MySQL — **латиница/цифры**. Кириллица в `MYSQL_PASSWORD` давала ошибку `latin-1` при импорте SQL.

### 2. Залей ZIP в панели

`http://IP/sites` → мастер → имя `mysite` → ZIP → загрузка с прогрессом.

### 3–5. В панели: кнопка «Настроить WP»

1. **Сохранить wp-config** (DB_HOST=`mysql`, пароль из `.env`)
2. **Загрузить и импортировать SQL** (дамп со старого хостинга)
3. **Заменить URL** на `http://IP/sites/mysite`

Альтернатива SQL с VPS:

```bash
scp dump.sql root@IP:/tmp/dump.sql
bash /opt/ai-helper/project/deploy/import-wp-sql.sh /tmp/dump.sql
```

### 6. Права

```bash
bash /opt/ai-helper/project/deploy/fix-sites-403.sh
```

## Проверка сервисов

```bash
docker ps | grep -E 'php|mysql|web|app'
curl -I http://127.0.0.1/sites/mysite/
```

## Дальше

1. Домен → A-запись на IP → кнопка **Домен** в панели  
2. SSL: `certbot --nginx -d твой-домен.ru`  
3. Отключить старый хостинг после проверки
