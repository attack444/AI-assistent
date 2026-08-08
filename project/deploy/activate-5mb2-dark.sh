#!/usr/bin/env bash
# Активирует тему 5MB2 Dark на VPS.
# Плагины Elementor отключайте вручную в админке ДО запуска (или с флагом --disable-builders).
# Использование: bash project/deploy/activate-5mb2-dark.sh [--disable-builders]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE="${SITES_ROOT:-/var/ai-helper/sites}/5mb2"
THEME_SRC="$ROOT/project/sites/5mb2/wp-content/themes/5mb2-dark"
THEME_DST="$SITE/wp-content/themes/5mb2-dark"
DISABLE_BUILDERS=0
[[ "${1:-}" == "--disable-builders" ]] && DISABLE_BUILDERS=1

if [[ ! -d "$SITE" ]]; then
  echo "Сайт не найден: $SITE"
  exit 1
fi
if [[ ! -d "$THEME_SRC" ]]; then
  echo "Тема не найдена в репо: $THEME_SRC"
  exit 1
fi

echo "==> Синхронизация темы 5mb2-dark"
mkdir -p "$THEME_DST"
rsync -a --delete \
  --exclude '.git' \
  "$THEME_SRC/" "$THEME_DST/"

echo "==> Права"
chown -R www-data:www-data "$THEME_DST" 2>/dev/null || true
find "$THEME_DST" -type d -exec chmod 755 {} \;
find "$THEME_DST" -type f -exec chmod 644 {} \;

if [[ "$DISABLE_BUILDERS" -eq 1 ]]; then
  echo "==> Отключение Elementor/EAEL через опции WP"
  php -r "
  define('ABSPATH', '$SITE/');
  define('WPINC', 'wp-includes');
  require '$SITE/wp-load.php';
  \$drop = [
    'elementor/elementor.php',
    'essential-addons-for-elementor-lite/essential_adons_elementor.php',
    'essential-addons-for-elementor-lite/essential_addons_elementor.php',
  ];
  \$active = get_option('active_plugins', []);
  if (!is_array(\$active)) { \$active = []; }
  \$active = array_values(array_filter(\$active, function (\$p) use (\$drop) {
    return !in_array(\$p, \$drop, true);
  }));
  update_option('active_plugins', \$active);
  echo 'builders off' . PHP_EOL;
  " 2>/dev/null || echo "(пропуск — отключите плагины вручную в админке)"
fi

echo "==> Активация темы + главная «последние записи»"
php -r "
define('ABSPATH', '$SITE/');
define('WPINC', 'wp-includes');
require '$SITE/wp-load.php';
switch_theme('5mb2-dark');
update_option('show_on_front', 'posts');
delete_option('page_on_front');
delete_option('elementor_active_kit');
echo 'theme=' . get_stylesheet() . PHP_EOL;
echo 'show_on_front=' . get_option('show_on_front') . PHP_EOL;
" 2>/dev/null || {
  echo "PHP/wp-load не сработал. Активируйте тему вручную:"
  echo "  Внешний вид → Темы → 5MB2 Dark → Активировать"
  echo "  Настройки → Чтение → На главной: последние записи"
}

# Очистка кэша Wordfence / object cache если есть
rm -f "$SITE/wp-content/cache/"* 2>/dev/null || true
rm -rf "$SITE/wp-content/cache/wp-rocket" 2>/dev/null || true

echo ""
echo "Готово. Откройте http://5mb2.ru/ (не https) и Ctrl+F5."
echo "Админка: http://5mb2.ru/wp-admin/"
