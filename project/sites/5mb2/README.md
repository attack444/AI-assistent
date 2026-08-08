# 5mb2 — исходники для ревью

Положи сюда файлы сайта с VPS (тема, плагины, PHP), **без** секретов.

## Что загрузить (нужно)

- `wp-content/themes/` — активная тема
- `wp-content/plugins/` — кастомные / важные плагины (можно без тяжёлых)
- корневые `*.php` если правили (`index.php` не обязателен)
- кастомный CSS/JS темы

## Что НЕ загружать

- `wp-config.php` (пароли БД)
- дампы `.sql`
- `wp-content/uploads/` (фото — тяжело и не для ревью кода)
- кэш, логи, бэкапы

## Как с VPS в репо

```bash
# на VPS, из корня WP (подставь путь)
SITE=/var/ai-helper/sites/5mb2
# если WP глубже:
# SITE=$(find /var/ai-helper/sites/5mb2 -name wp-config.php | head -1 | xargs dirname)

cd /opt/ai-helper
git fetch origin cursor/complete-ai-helper-17f9
git checkout cursor/complete-ai-helper-17f9
git pull origin cursor/complete-ai-helper-17f9

mkdir -p project/sites/5mb2
rsync -a --delete \
  --exclude 'wp-config.php' \
  --exclude 'wp-content/uploads' \
  --exclude 'wp-content/cache' \
  --exclude 'wp-content/upgrade' \
  --exclude 'wp-content/wflogs' \
  --exclude '*.sql' \
  "$SITE/" project/sites/5mb2/

git add project/sites/5mb2
git commit -m "content: 5mb2 site sources for review"
git push origin cursor/complete-ai-helper-17f9
```

Либо залей ZIP темы/плагинов через GitHub / Cursor — я разберу и проверю.
