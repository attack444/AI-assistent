<?php

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BotLifecycleManager
{
    private $default_setup;
    private $site_wide_manager;

    public function __construct()
    {
        // Instantiate dependencies if the classes exist.
        if (class_exists(DefaultBotSetup::class)) {
            $this->default_setup = new DefaultBotSetup();
        }
        if (class_exists(SiteWideBotManager::class)) {
            $this->site_wide_manager = new SiteWideBotManager();
        }
    }

    /**
     * Creates a new chatbot post and sets its initial settings.
     * REMOVED: Duplicate name check. Bots can now have the same name.
     *
     * @param string $botName The desired name for the new bot.
     * @return array|WP_Error ['bot_id' => int, 'bot_name' => string, 'bot_settings' => array] on success, WP_Error on failure.
     */
    public function create_bot(string $botName)
    {
        if (empty($botName)) {
            return new WP_Error('empty_name', __('Chatbot name cannot be empty.', 'gpt3-ai-content-generator'));
        }
        // Ensure BotSettingsManager is loaded before calling its static method
        $settings_path = __DIR__ . '/class-aipkit_bot_settings_manager.php';
        if (!class_exists(BotSettingsManager::class)) {
            if (file_exists($settings_path)) {
                require_once $settings_path;
            } else {
                return new WP_Error('dependency_missing', 'BotSettingsManager class not found for bot creation.');
            }
        }

        $post_data = array(
            'post_title'  => $botName,
            'post_type'   => AdminSetup::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        );

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id) || $post_id === 0) {
            $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
            return new WP_Error('creation_failed', __('Error creating chatbot post.', 'gpt3-ai-content-generator'));
        }

        // Call the static method from BotSettingsManager to set defaults
        BotSettingsManager::set_initial_bot_settings($post_id, $botName);

        // Fetch the settings AFTER setting them
        // Need an instance to call the non-static get_chatbot_settings
        $settings_manager_instance = new BotSettingsManager();
        $saved_settings = $settings_manager_instance->get_chatbot_settings($post_id);
        return ['bot_id' => $post_id, 'bot_name' => $botName, 'bot_settings' => $saved_settings];
    }

    /**
     * Deletes (trashes) a chatbot post.
     *
     * @param int $botId The ID of the bot to delete.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_bot(int $botId)
    {
        // Ensure AdminSetup class is loaded for POST_TYPE constant
        if (!class_exists(AdminSetup::class)) {
            return new WP_Error('dependency_missing', 'AdminSetup class not available.');
        }

        if (empty($botId) || get_post_type($botId) !== AdminSetup::POST_TYPE) {
            return new WP_Error('invalid_bot_id_delete', __('Invalid chatbot ID provided for deletion.', 'gpt3-ai-content-generator'));
        }
        if (!$this->site_wide_manager) {
            return new WP_Error('missing_dependency', __('SiteWideBotManager not initialized for deletion.', 'gpt3-ai-content-generator'));
        }

        // Check if it's the default bot using the static method
        $default_bot_id = DefaultBotSetup::get_default_bot_id();
        if ($botId === $default_bot_id) {
            return new WP_Error('cannot_delete_default', __('The default chatbot cannot be deleted.', 'gpt3-ai-content-generator'));
        }

        $was_site_wide = (get_post_meta($botId, '_aipkit_site_wide_enabled', true) === '1');
        $deleted = wp_delete_post($botId, false); // Move to trash

        if (!$deleted) {
            return new WP_Error('delete_failed', __('Failed to delete chatbot.', 'gpt3-ai-content-generator'));
        }

        if ($was_site_wide) {
            $this->site_wide_manager->clear_site_wide_cache();
        }

        return true;
    }
}
