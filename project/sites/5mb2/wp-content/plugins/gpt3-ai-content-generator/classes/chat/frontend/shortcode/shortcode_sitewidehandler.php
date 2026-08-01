<?php

namespace WPAICG\Chat\Frontend\Shortcode;

use WPAICG\Chat\Storage\SiteWideBotManager;
use WPAICG\Chat\Frontend\Shortcode; // Reference the main shortcode class

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles site-wide injection logic for the Chatbot Shortcode.
 */
class SiteWideHandler {

    private $site_wide_manager;
    private $shortcode_instance; // Reference to the main Shortcode class instance
    private static $site_wide_bot_id_cache = null; // Cache for site-wide bot ID

    public function __construct(Shortcode $shortcode_instance) {
        $this->site_wide_manager = new SiteWideBotManager();
        $this->shortcode_instance = $shortcode_instance; // Store reference
    }

    /**
     * Inject the site-wide chatbot if configured.
     * Hooked into `wp_footer`.
     */
    public function inject_site_wide_chatbot() {
        if (is_admin() || wp_doing_ajax()) return;

        // Use the manager to get the site-wide bot ID, utilize its caching
        if (self::$site_wide_bot_id_cache === null) { // Check static cache first
            self::$site_wide_bot_id_cache = $this->site_wide_manager->get_site_wide_bot_id();
        }
        $bot_id_to_inject = self::$site_wide_bot_id_cache;

        // Check if the shortcode instance exists and the bot ID is valid
        if ($this->shortcode_instance && $bot_id_to_inject) {
            // Check if this bot has *already* been rendered by the main shortcode class
            // Access the static property via the instance reference
            if (!isset($this->shortcode_instance::$rendered_bot_ids[$bot_id_to_inject])) {
                // Render the shortcode using the main public method from the Shortcode instance
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is sanitized internally and safe to render
                echo $this->shortcode_instance->render_chatbot_shortcode(['id' => $bot_id_to_inject]);
            }
        }
    }
}