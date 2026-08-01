<?php


namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Facade class for managing Chatbot posts and settings.
 * Delegates operations to BotLifecycleManager and BotSettingsManager.
 * Retains get_chatbots and helper methods for default/site-wide bots.
 * ADDED: New method get_chatbots_with_settings() for optimized data fetching.
 */
class BotStorage
{
    private $lifecycle_manager;
    private $settings_manager;
    private $default_setup; // Keep for ensure_default_chatbot()
    private $site_wide_manager; // Keep for get_site_wide_bot_id()

    public function __construct()
    {
        // Ensure new classes are loaded before instantiating
        $lifecycle_path = __DIR__ . '/class-aipkit_bot_lifecycle_manager.php';
        $settings_path = __DIR__ . '/class-aipkit_bot_settings_manager.php';
        $default_setup_path = __DIR__ . '/class-aipkit_default_bot_setup.php';
        $site_wide_path = __DIR__ . '/class-aipkit_site_wide_bot_manager.php';
        $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php'; // Need AdminSetup path
        $settings_getter_path = __DIR__ . '/class-aipkit-bot-settings-getter.php'; // ADDED: Ensure getter is loaded

        // Load dependencies if they exist and are not already declared
        if (file_exists($admin_setup_path) && !class_exists(AdminSetup::class)) {
            require_once $admin_setup_path;
        }
        if (file_exists($default_setup_path) && !class_exists(DefaultBotSetup::class)) {
            require_once $default_setup_path;
        }
        if (file_exists($site_wide_path) && !class_exists(SiteWideBotManager::class)) {
            require_once $site_wide_path;
        }
        if (file_exists($lifecycle_path) && !class_exists(BotLifecycleManager::class)) {
            require_once $lifecycle_path;
        }
        if (file_exists($settings_path) && !class_exists(BotSettingsManager::class)) {
            require_once $settings_path;
        }
        if (file_exists($settings_getter_path) && !class_exists(AIPKit_Bot_Settings_Getter::class)) {
            require_once $settings_getter_path;
        }


        // Instantiate dependencies needed by this facade or its children
        if (class_exists(DefaultBotSetup::class)) {
            $this->default_setup = new DefaultBotSetup();
        }
        if (class_exists(SiteWideBotManager::class)) {
            $this->site_wide_manager = new SiteWideBotManager();
        }

        if (class_exists(BotLifecycleManager::class)) {
            $this->lifecycle_manager = new BotLifecycleManager();
        }
        if (class_exists(BotSettingsManager::class)) {
            $this->settings_manager = new BotSettingsManager();
        }
    }

    /**
     * Retrieve all published chatbots.
     * (Kept in Facade for now)
     *
     * @param bool $prefetch_meta Whether to prefetch post meta for all bots.
     * @return array Array of WP_Post objects.
     */
    public function get_chatbots(bool $prefetch_meta = true): array
    {
        if (!class_exists(AdminSetup::class)) {
            return [];
        }
        $args = array(
            'post_type'      => AdminSetup::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'update_post_meta_cache' => $prefetch_meta,
            'update_post_term_cache' => false,
        );
        $query = new \WP_Query($args);
        return $query->get_posts();
    }

    /**
     * NEW: Retrieve all published chatbots along with their settings, optimized.
     *
     * @return array Array of ['post' => WP_Post, 'settings' => array].
     */
    public function get_chatbots_with_settings(): array
    {
        $bot_posts = $this->get_chatbots(); // This WP_Query should cache meta
        if (empty($bot_posts)) {
            return [];
        }

        $bots_with_settings = [];
        foreach ($bot_posts as $bot_post) {
            // Fetch all meta for the current bot. This should hit the cache.
            $all_meta_for_bot = get_post_meta($bot_post->ID);
            $prefetched_meta = [];
            if (is_array($all_meta_for_bot)) {
                foreach ($all_meta_for_bot as $meta_key => $meta_values) {
                    // Store the single value, similar to get_post_meta($id, $key, true)
                    $prefetched_meta[$meta_key] = isset($meta_values[0]) ? $meta_values[0] : null;
                }
            }

            // Ensure AIPKit_Bot_Settings_Getter is loaded and class exists
            if (!class_exists(AIPKit_Bot_Settings_Getter::class)) {
                continue; // Skip this bot if getter isn't available
            }

            $settings = AIPKit_Bot_Settings_Getter::get($bot_post->ID, $prefetched_meta);
            if (!is_wp_error($settings)) {
                $bots_with_settings[] = [
                    'post' => $bot_post,
                    'settings' => $settings,
                ];
            }
        }
        return $bots_with_settings;
    }


    /**
     * Facade method to create a bot. Delegates to BotLifecycleManager.
     *
     * @param string $botName The desired name for the new bot.
     * @return array|WP_Error ['bot_id' => int, 'bot_name' => string, 'bot_settings' => array] on success, WP_Error on failure.
     */
    public function create_bot(string $botName)
    {
        if (!$this->lifecycle_manager) {
            return new WP_Error('init_error', 'Lifecycle Manager not available.');
        }
        return $this->lifecycle_manager->create_bot($botName);
    }

    /**
     * Facade method to save bot settings. Delegates to BotSettingsManager.
     *
     * @param int $botId The chatbot post ID.
     * @param array $settings The settings array from the form.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function save_bot_settings(int $botId, array $settings)
    {
        if (!$this->settings_manager) {
            return new WP_Error('init_error', 'Settings Manager not available.');
        }
        return $this->settings_manager->save_bot_settings($botId, $settings);
    }

    /**
     * Facade method to delete a bot. Delegates to BotLifecycleManager.
     *
     * @param int $botId The ID of the bot to delete.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_bot(int $botId)
    {
        if (!$this->lifecycle_manager) {
            return new WP_Error('init_error', 'Lifecycle Manager not available.');
        }
        return $this->lifecycle_manager->delete_bot($botId);
    }

    /**
     * Facade method to get bot settings. Delegates to BotSettingsManager.
     *
     * @param int $bot_id The chatbot post ID.
     * @return array An associative array of settings.
     */
    public function get_chatbot_settings(int $bot_id): array
    {
        if (!$this->settings_manager) {
            return [];
        } // Return empty if manager failed
        $settings = $this->settings_manager->get_chatbot_settings($bot_id);

        if (is_wp_error($settings) || !is_array($settings)) {
            return [];
        }

        return $settings;
    }

    /**
     * Facade method to get the site-wide bot ID. Delegates to SiteWideBotManager.
     *
     * @param bool $force_refresh Set to true to bypass cache.
     * @return int|null The ID of the site-wide bot, or null if none is set.
     */
    public function get_site_wide_bot_id(bool $force_refresh = false): ?int
    {
        if (!$this->site_wide_manager) {
            return null;
        }
        return $this->site_wide_manager->get_site_wide_bot_id($force_refresh);
    }

    /**
     * Facade method to ensure the default chatbot exists. Delegates to DefaultBotSetup.
     */
    public function ensure_default_chatbot(): void
    {
        if (!$this->default_setup) {
            return;
        }
        // DefaultBotSetup::ensure_default_chatbot() is static now
        DefaultBotSetup::ensure_default_chatbot();
    }
}
