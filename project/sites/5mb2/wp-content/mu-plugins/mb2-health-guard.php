<?php
/**
 * Plugin Name: 5MB2 Health Guard
 * Description: Лёгкий health-check и лог фаталов — не ломает сайт при ошибках темы.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /?mb2_health=1 — JSON для watchdog (без темы).
 */
add_action('plugins_loaded', static function () {
    if (!isset($_GET['mb2_health'])) {
        return;
    }
    if ((string) $_GET['mb2_health'] !== '1') {
        return;
    }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
    $payload = [
        'ok'       => true,
        'service'  => '5mb2',
        'wordpress'=> true,
        'theme'    => $theme ? $theme->get_stylesheet() : '',
        'theme_ver'=> $theme ? (string) $theme->get('Version') : '',
        'php'      => PHP_VERSION,
        'time'     => gmdate('c'),
    ];
    $log = WP_CONTENT_DIR . '/mb2-fatal.log';
    if (is_readable($log)) {
        $tail = @file($log, FILE_IGNORE_NEW_LINES);
        if (is_array($tail) && $tail) {
            $payload['last_fatal'] = array_slice($tail, -3);
        }
    }
    echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}, 0);

/** Пишем фаталы в wp-content/mb2-fatal.log (для панели/watchdog). */
register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) $err['type'], $fatal, true)) {
        return;
    }
    $line = sprintf(
        "[%s] %s in %s:%d\n",
        gmdate('c'),
        $err['message'] ?? '',
        $err['file'] ?? '',
        (int) ($err['line'] ?? 0)
    );
    $path = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : __DIR__ . '/..') . '/mb2-fatal.log';
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
});
