<?php

namespace WPAICG\Chat\Frontend\Shortcode;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles validation logic for the Chatbot Shortcode.
 */
class Validator {

    /**
     * Validates shortcode attributes, checks for duplicates, and verifies the bot ID.
     *
     * @param array $atts Raw shortcode attributes.
     * @param array $rendered_bot_ids Reference to the array tracking rendered bot IDs.
     * @return int|WP_Error Valid bot ID on success, WP_Error on failure.
     */
    public static function validate_atts(array $atts, array &$rendered_bot_ids) {
        $atts = shortcode_atts(['id' => 0], $atts, 'aipkit_chatbot');
        $bot_id = absint($atts['id']);

        if (empty($bot_id)) {
            return new WP_Error('invalid_id', sprintf('[AIPKit Chatbot Error: Invalid Chatbot ID: %s]', esc_html($atts['id'])));
        }

        $bot_post = get_post($bot_id);
        if (!$bot_post || $bot_post->post_type !== AdminSetup::POST_TYPE || !in_array($bot_post->post_status, ['publish', 'draft'])) {
            return new WP_Error('not_found', sprintf('[AIPKit Chatbot Error: Invalid or non-existent Chatbot ID: %d]', $bot_id));
        }

        return $bot_id;
    }
}