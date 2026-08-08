# Перенос сайта с хостинга на VPS (AI Helper)

Сейчас у тебя обычный shared-хостинг + VPS. Цель: сайт крутится на VPS (дешевле), а панель AI Helper показывает файлы как в ISPmanager / cPanel.

## Что уже есть на VPS

| Что | Где |
|---|---|
| Панель (файлы / сайты / чат) | `http://IP/` (Next.js :3000 через Nginx) |
| API | `http://IP/api/` |
| Файлы сайтов | `/var/ai-helper/sites/<имя>/` |
| Публичный URL сайта | `http://IP/sites/<имя>/` |
| Старый Streamlit | `http://IP/legacy/` |

## Шаг 1. Скачай сайт с текущего хостинга

Любой из вариантов:

1. **Панель хостинга** → Файловый менеджер → выдели `public_html` / `www` → скачать ZIP  
2. **FTP/SFTP** (FileZilla): скачай папку сайта целиком, потом упакуй в ZIP  
3. **Бэкап** в панели хостинга → скачай архив

Нужны как минимум HTML/CSS/JS/картинки. Если сайт на PHP + MySQL — см. раздел «PHP/БД» ниже.

## Шаг 2. Создай сайт в панели AI Helper

1. Открой `http://ТВОЙ_IP/` → **Сайты**
2. Имя, например `mysite` (латиница)
3. Домен — пока можно оставить пустым
4. **Создать**

Появится папка `/var/ai-helper/sites/mysite/` и заглушка `index.html`.

## Шаг 3. Залей файлы

**Через панель (проще):**

1. На карточке сайта нажми **ZIP**
2. Выбери архив с хостинга
3. Открой `http://IP/sites/mysite/`

**Через файловый менеджер:**

1. **Файлы** → зайди в `/opt/sites/mysite` (в Docker это `/var/ai-helper/sites/mysite` на хосте)
2. Удали заглушку при необходимости
3. Загрузи файлы по одному или через ZIP на странице Сайты

**Через SCP с Windows (PowerShell):**

```powershell
scp -r C:\path\to\site\* root@ТВОЙ_IP:/var/ai-helper/sites/mysite/
```

## Шаг 4. Проверь

- Открой `http://IP/sites/mysite/`
- Если белая страница — в **Файлы** проверь, что есть `index.html` в корне сайта (не во вложенной папке `public_html/`)
- Панель при ZIP сама пытается «развернуть» одну верхнюю папку из архива

## Шаг 5. Домен (когда будешь готов)

1. В DNS у регистратора: A-запись `@` и `www` → IP VPS  
2. В панели при создании сайта укажи домен — сгенерируется `nginx.vhost.conf`  
3. На сервере:

```bash
# если конфиг лежит в папке сайта
sudo cp /var/ai-helper/sites/mysite/nginx.vhost.conf /etc/nginx/sites-available/mysite
sudo ln -sf /etc/nginx/sites-available/mysite /etc/nginx/sites-enabled/mysite
sudo nginx -t && sudo systemctl reload nginx

# SSL
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d твой-домен.ru -d www.твой-домен.ru
```

## PHP / база данных

Статика (HTML/JS) — работает сразу.

**PHP:** на VPS нужен `php-fpm` + правки Nginx (`fastcgi_pass`). Напиши — добавим шаблон.

**MySQL:**

1. Экспорт БД на старом хостинге (phpMyAdmin → Export)  
2. На VPS уже есть Postgres в Docker; для MySQL можно поднять отдельно:

```bash
docker run -d --name site-mysql \
  -e MYSQL_ROOT_PASSWORD=... \
  -e MYSQL_DATABASE=site \
  -v /var/ai-helper/mysql:/var/lib/mysql \
  -p 3306:3306 mysql:8
```

3. Импорт: `mysql -h 127.0.0.1 -u root -p site < dump.sql`  
4. В конфиге сайта поменяй `localhost` / логин / пароль БД на новые

## Обновление AI Helper на сервере

```bash
cd /opt/ai-helper && git pull
cd project/deploy
docker compose -f docker-compose.prod.yml up -d --build
sudo cp ../deploy/nginx.conf /etc/nginx/sites-available/ai-helper
sudo nginx -t && sudo systemctl reload nginx
```

После обновления панель будет на порту **80** (и `:3000`), API на `/api/`.
