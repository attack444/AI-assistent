<?php

namespace WPAICG\Chat\Storage\SiteWide;

use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Chat\Storage\SiteWideBotManager; // To access constants

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for the get_site_wide_bot_id method of SiteWideBotManager.
 *
 * @param bool $force_refresh Set to true to bypass cache and query DB directly.
 * @return int|null The ID of the site-wide bot, or null if none is set.
 */
function get_site_wide_bot_id_logic(bool $force_refresh = false): ?int {
    $cache_key = SiteWideBotManager::SITE_WIDE_BOT_CACHE_KEY;
    $bot_id = null;

    if (!$force_refresh) {
        $bot_id = wp_cache_get($cache_key, 'aipkit');
        if ($bot_id !== false) {
            return ($bot_id === 'none') ? null : (int) $bot_id;
        }

        $bot_id = get_transient($cache_key);
        if ($bot_id !== false) {
            wp_cache_set($cache_key, $bot_id, 'aipkit', MINUTE_IN_SECONDS);
            return ($bot_id === 'none') ? null : (int) $bot_id;
        }
    }

    // Query the Database
    // Ensure AdminSetup is available for POST_TYPE
    if (!class_exists(AdminSetup::class)) {
        $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
        if (file_exists($admin_setup_path)) {
            require_once $admin_setup_path;
        } else {
            return null; // Cannot proceed without post type
        }
    }

    $args = array(
        'post_type'      => AdminSetup::POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reason: The meta/tax query is essential for the feature's functionality. Its performance impact is considered acceptable as the query is highly specific, paginated, cached, or runs in a non-critical admin/cron context.
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => '_aipkit_site_wide_enabled', 'value' => '1', 'compare' => '='),
            array('key' => '_aipkit_popup_enabled', 'value' => '1', 'compare' => '=') // Must be popup too
        ),
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );
    $query = new \WP_Query($args);
    $found_ids = $query->get_posts();

    $bot_id = !empty($found_ids) ? (int) $found_ids[0] : null;
    $cache_value = ($bot_id === null) ? 'none' : $bot_id;

    wp_cache_set($cache_key, $cache_value, 'aipkit', MINUTE_IN_SECONDS);
    set_transient($cache_key, $cache_value, MINUTE_IN_SECONDS * 5);

    return $bot_id;
}