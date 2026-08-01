<?php


namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles creation and setup of the default chatbot.
 */
class DefaultBotSetup
{
    private static function get_default_bot_name(): string
    {
        return __('Customer Support', 'gpt3-ai-content-generator');
    }

    /**
     * Ensures we have a default chatbot in place.
     * Checks if one exists; if not, creates it.
     * Only sets initial settings when creating or if marker is missing.
     */
    public static function ensure_default_chatbot()
    {
        // Ensure BotSettingsManager is loaded before potential use
        $settings_path = __DIR__ . '/class-aipkit_bot_settings_manager.php';
        if (!class_exists(BotSettingsManager::class)) {
            if (file_exists($settings_path)) {
                require_once $settings_path;
            } else {
                return;
            }
        }

        $existing = self::get_default_bot(); // This finds posts with the meta key _aipkit_default_bot = 1

        if (!$existing) {
            // No bot marked as default found, try to create one
            $result = self::create_default_bot(); // This also calls set_initial_bot_settings on creation
        } else {
            // Default bot exists. Check if it's marked correctly.
            $is_marked = get_post_meta($existing->ID, '_aipkit_default_bot', true);
            if ($is_marked !== '1') {
                update_post_meta($existing->ID, '_aipkit_default_bot', '1');
            }
            if (trim((string) $existing->post_title) === 'Default') {
                wp_update_post([
                    'ID' => $existing->ID,
                    'post_title' => self::get_default_bot_name(),
                ]);
            }
            self::ensure_default_bot_hint_settings((int) $existing->ID);
        }
    }

    /**
     * Ensures launcher hint defaults for existing default bots.
     */
    private static function ensure_default_bot_hint_settings(int $bot_id): void
    {
        if (!metadata_exists('post', $bot_id, '_aipkit_popup_label_enabled')) {
            update_post_meta($bot_id, '_aipkit_popup_label_enabled', BotSettingsManager::DEFAULT_POPUP_LABEL_ENABLED);
        }

        $stored_hint_text = get_post_meta($bot_id, '_aipkit_popup_label_text', true);
        if (!is_string($stored_hint_text) || trim($stored_hint_text) === '') {
            update_post_meta($bot_id, '_aipkit_popup_label_text', BotSettingsManager::DEFAULT_POPUP_LABEL_TEXT);
        }

        $allowed_hint_sizes = ['small', 'medium', 'large', 'xlarge'];
        $stored_hint_size = get_post_meta($bot_id, '_aipkit_popup_label_size', true);
        if (!is_string($stored_hint_size) || !in_array($stored_hint_size, $allowed_hint_sizes, true)) {
            update_post_meta($bot_id, '_aipkit_popup_label_size', BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE);
        }
    }

    /**
     * Checks if a default chatbot exists.
     */
    private static function get_default_bot(): ?\WP_Post
    {
        if (!class_exists('\\WPAICG\\Chat\\Admin\\AdminSetup')) {
            $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
            if (file_exists($admin_setup_path)) {
                require_once $admin_setup_path;
            } else {
                return null;
            }
        }

        $args = array(
            'post_type'      => AdminSetup::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reason: The meta/tax query is essential for the feature's functionality. Its performance impact is considered acceptable as the query is highly specific, paginated, cached, or runs in a non-critical admin/cron context.
            'meta_query' => array(
                array(
                    'key'   => '_aipkit_default_bot',
                    'value' => '1',
                    'compare' => '=',
                ),
            ),
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        $query = new \WP_Query($args);
        $posts = $query->get_posts();
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Gets the ID of the default chatbot.
     */
    public static function get_default_bot_id(): ?int
    {
        $default_bot = self::get_default_bot();
        return $default_bot ? $default_bot->ID : null;
    }

    /**
     * Creates the default chatbot.
     * Calls static BotSettingsManager::set_initial_bot_settings.
     * @return int|\WP_Error
     */
    private static function create_default_bot()
    {
        if (!class_exists('\\WPAICG\\Chat\\Admin\\AdminSetup')) {
            $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
            if (file_exists($admin_setup_path)) {
                require_once $admin_setup_path;
            } else {
                return new WP_Error('dependency_missing', 'AdminSetup class not found for default bot creation.');
            }
        }
        if (!class_exists(BotSettingsManager::class)) {
            return new WP_Error('dependency_missing', 'BotSettingsManager class not found for default bot creation.');
        }

        $botName = self::get_default_bot_name();

        $post_data = array(
            'post_title'  => $botName,
            'post_type'   => AdminSetup::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        );
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id) || $post_id === 0) {
            $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
            return new WP_Error('creation_failed', __('Error creating default chatbot post.', 'gpt3-ai-content-generator'));
        }

        update_post_meta($post_id, '_aipkit_default_bot', '1');
        BotSettingsManager::set_initial_bot_settings($post_id, $botName, 'inline');
        return $post_id;
    }

    /**
     * Resets a given chatbot's settings to the initial defaults.
     * Calls static BotSettingsManager::set_initial_bot_settings.
     * @return bool|\WP_Error
     */
    public static function reset_bot_settings($bot_id)
    {
        if (!class_exists('\\WPAICG\\Chat\\Admin\\AdminSetup')) {
            $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
            if (file_exists($admin_setup_path)) {
                require_once $admin_setup_path;
            } else {
                return new WP_Error('dependency_missing', 'AdminSetup class not found for bot reset.');
            }
        }
        $settings_path = __DIR__ . '/class-aipkit_bot_settings_manager.php';
        if (!class_exists(BotSettingsManager::class)) {
            if (file_exists($settings_path)) {
                require_once $settings_path;
            } else {
                return new WP_Error('dependency_missing', 'BotSettingsManager class not found for bot reset.');
            }
        }

        $bot_id = absint($bot_id);
        if (empty($bot_id) || get_post_type($bot_id) !== AdminSetup::POST_TYPE) {
            return new WP_Error('invalid_bot_id_reset', __('Invalid chatbot ID provided for reset.', 'gpt3-ai-content-generator'));
        }

        $bot_post = get_post($bot_id);
        if (!$bot_post) {
            return new WP_Error('bot_not_found_reset', __('Chatbot post not found for reset.', 'gpt3-ai-content-generator'));
        }

        $is_actually_default = (get_post_meta($bot_id, '_aipkit_default_bot', true) === '1');
        $was_site_wide = (get_post_meta($bot_id, '_aipkit_site_wide_enabled', true) === '1');

        // The built-in chatbot defaults to on-page; user-created bots default to popup.
        $default_deploy_mode = $is_actually_default ? 'inline' : 'popup';
        BotSettingsManager::set_initial_bot_settings($bot_id, $bot_post->post_title, $default_deploy_mode);

        if (!$is_actually_default) {
            delete_post_meta($bot_id, '_aipkit_default_bot');
        } else {
            // Ensure default marker remains if it is the default bot
            update_post_meta($bot_id, '_aipkit_default_bot', '1');
        }

        if ($was_site_wide) {
            if (!class_exists('\\WPAICG\\Chat\\Storage\\SiteWideBotManager')) {
                $site_wide_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_site_wide_bot_manager.php';
                if (file_exists($site_wide_path)) {
                    require_once $site_wide_path;
                }
            }
            if (class_exists('\\WPAICG\\Chat\\Storage\\SiteWideBotManager')) {
                $site_wide_manager = new SiteWideBotManager();
                $site_wide_manager->clear_site_wide_cache();
            }
        }
        return true;
    }

}
