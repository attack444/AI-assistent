<?php
// UPDATED FILE - Use BotStorage facade

namespace WPAICG\Chat\Frontend\Shortcode;

use WPAICG\Chat\Storage\BotStorage; // Use the Facade
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles data fetching logic for the Chatbot Shortcode.
 * Uses the BotStorage facade.
 */
class DataProvider {

    /**
     * Fetches the WP_Post object and settings for a given bot ID.
     *
     * @param int $bot_id The chatbot post ID.
     * @return array|WP_Error Array containing 'post' and 'settings' on success, WP_Error on failure.
     */
    public static function get_bot_data(int $bot_id) {
        $bot_post = get_post($bot_id);
        if (!$bot_post) {
            return new WP_Error('fetch_error', sprintf('[AIPKit Chatbot Error: Could not fetch post data for ID: %d]', $bot_id));
        }

        // Ensure BotStorage facade is available
        if (!class_exists(\WPAICG\Chat\Storage\BotStorage::class)) {
            return new \WP_Error('internal_error', 'Cannot load bot data.');
        }
        // Instantiate BotStorage facade locally
        $bot_storage = new BotStorage();
        $bot_settings = $bot_storage->get_chatbot_settings($bot_id);

        return ['post' => $bot_post, 'settings' => $bot_settings];
    }
}