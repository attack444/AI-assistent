<?php

namespace WPAICG\Chat\Storage\SiteWide;

use WPAICG\Chat\Storage\SiteWideBotManager; // To access methods
use WPAICG\Chat\Admin\AdminSetup;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for the ensure_site_wide_uniqueness method of SiteWideBotManager.
 *
 * @param int $target_bot_id The ID of the bot being potentially enabled.
 * @param bool $is_enabling Whether the target bot is being enabled for site-wide use.
 * @param SiteWideBotManager $site_wide_manager_instance Instance of SiteWideBotManager to call its methods.
 * @return bool True if the cache should be cleared after the operation.
 */
function ensure_site_wide_uniqueness_logic(int $target_bot_id, bool $is_enabling, SiteWideBotManager $site_wide_manager_instance): bool {
    $clear_cache = false;

    if ($is_enabling) {
        if (!class_exists(AdminSetup::class)) {
            $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
            if (file_exists($admin_setup_path)) {
                require_once $admin_setup_path;
            }
        }

        if (class_exists(AdminSetup::class)) {
            // Disable every other popup bot marked as site-wide.
            $conflicting_query = new \WP_Query([
                'post_type'              => AdminSetup::POST_TYPE,
                'post_status'            => ['publish', 'draft'],
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Targeted query over chatbot posts for uniqueness enforcement.
                'meta_query'             => [
                    'relation' => 'AND',
                    ['key' => '_aipkit_site_wide_enabled', 'value' => '1', 'compare' => '='],
                    ['key' => '_aipkit_popup_enabled', 'value' => '1', 'compare' => '='],
                ],
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);
            $conflicting_ids = $conflicting_query->get_posts();
            foreach ($conflicting_ids as $conflicting_id) {
                $conflicting_id = absint($conflicting_id);
                if ($conflicting_id > 0 && $conflicting_id !== $target_bot_id) {
                    update_post_meta($conflicting_id, '_aipkit_site_wide_enabled', '0');
                    $clear_cache = true;
                }
            }
        }

        // Enabling site-wide on target always requires cache refresh.
        $clear_cache = true;
    } else {
        // If target was site-wide, cache must be refreshed.
        $was_site_wide = get_post_meta($target_bot_id, '_aipkit_site_wide_enabled', true) === '1';
        if ($was_site_wide) {
            $clear_cache = true;
        }
    }
    return $clear_cache;
}
