<?php
/**
 * CLI: отключить Elementor/EA и включить тему 5mb2-dark (без удаления файлов).
 *   php wp-disable-builders.php /var/ai-helper/sites/5mb2
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
$wp = $argv[1] ?? '/var/ai-helper/sites/5mb2';
$wp = rtrim($wp, '/');
if (!is_file($wp . '/wp-load.php')) {
    fwrite(STDERR, "wp-load.php not found in $wp\n");
    exit(1);
}

define('WP_USE_THEMES', false);
require $wp . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

switch_theme('5mb2-dark');
echo "theme: 5mb2-dark\n";

$active = (array) get_option('active_plugins', []);
$kept = [];
foreach ($active as $plugin) {
    $low = strtolower($plugin);
    if (strpos($low, 'elementor') !== false || strpos($low, 'essential-addons') !== false) {
        echo "deactivate: $plugin\n";
        continue;
    }
    $kept[] = $plugin;
}
update_option('active_plugins', array_values($kept));

update_option('show_on_front', 'posts');
delete_option('page_on_front');
echo "front: posts (theme front-page.php)\n";
echo "ok\n";
