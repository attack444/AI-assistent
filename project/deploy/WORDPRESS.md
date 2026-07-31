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
MYSQL_ROOT_PASSWORD=надежный_root
MYSQL_DATABASE=wordpress
MYSQL_USER=wp
MYSQL_PASSWORD=надежный_wp
PANEL_PASSWORD=пароль_панели
```

### 2. Залей ZIP в панели

`http://IP/sites` → мастер → имя `mysite` → ZIP → загрузка с прогрессом.

### 3. Импорт БД

```bash
# с ПК
scp dump.sql root@IP:/tmp/dump.sql

# на VPS
docker exec -i ai-helper-mysql mysql -uwp -p'надежный_wp' wordpress < /tmp/dump.sql
```

### 4. wp-config.php

В панели **Файлы** открой `/opt/sites/mysite/wp-config.php` (или на хосте):

```php
define('DB_NAME', 'wordpress');
define('DB_USER', 'wp');
define('DB_PASSWORD', 'надежный_wp');
define('DB_HOST', 'mysql');  // имя сервиса Docker, не localhost!
```

Если PHP на хосте через `127.0.0.1:9000`, а MySQL проброшен на 3306:

```php
define('DB_HOST', '127.0.0.1');
```

### 5. URL WordPress

В БД или через WP-CLI замени старый домен:

```sql
UPDATE wp_options SET option_value='http://IP/sites/mysite' WHERE option_name IN ('siteurl','home');
```

Или плагин Better Search Replace после входа.

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
