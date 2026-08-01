<?php

namespace WPAICG\Chat\Frontend;

// Use statements for the new helper classes
use WPAICG\Chat\Frontend\Shortcode\Validator;
use WPAICG\Chat\Frontend\Shortcode\DataProvider;
use WPAICG\Chat\Frontend\Shortcode\FeatureManager;
use WPAICG\Chat\Frontend\Shortcode\Configurator;
use WPAICG\Chat\Frontend\Shortcode\Renderer;
use WPAICG\Chat\Frontend\Shortcode\SiteWideHandler;
use WPAICG\Chat\Frontend\Assets\AssetsEnqueuer; // Import the enqueuer class
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Orchestrates rendering the [aipkit_chatbot] shortcode.
 * Uses helper classes for validation, data fetching, config, features, and rendering.
 * Also handles site-wide injection via a dedicated handler.
 */
class Shortcode {

    /**
     * Tracks IDs rendered on the current page to prevent duplicates.
     * Made public static so SiteWideHandler can access it via the instance.
     * @var array
     */
    public static $rendered_bot_ids = [];

    private $renderer; // Instance of the renderer class
    private $site_wide_handler; // Instance of the site-wide handler

    public function __construct() {
        // Instantiate classes that need state or complex dependencies
        $this->renderer = new Renderer();
        // Pass the current instance ($this) to the SiteWideHandler
        $this->site_wide_handler = new SiteWideHandler($this);
        $this->register_hooks();
    }

    /**
     * Registers WordPress hooks.
     */
    private function register_hooks() {
        // Use the instance method from the handler for the footer hook
        add_action('wp_footer', [$this->site_wide_handler, 'inject_site_wide_chatbot'], 10);
    }

    /**
     * Main entry point for rendering the chatbot shortcode.
     * Orchestrates validation, data fetching, configuration, and HTML generation.
     *
     * @param array $atts Shortcode attributes.
     * @return string The rendered HTML or an error message/empty string.
     */
    public function render_chatbot_shortcode($atts) {
        // 1. Validate Attributes
        $validation_result = Validator::validate_atts($atts, self::$rendered_bot_ids);
        if (is_wp_error($validation_result)) {
            return $this->handle_render_error($validation_result);
        }
        $bot_id = $validation_result; // Validated Bot ID

        // 2. Get Bot Data
        $bot_data = DataProvider::get_bot_data($bot_id);
        if (is_wp_error($bot_data)) {
            return $this->handle_render_error($bot_data, $bot_id);
        }
        $bot_post = $bot_data['post'];
        $bot_settings = $bot_data['settings'];

        // 3. Determine Feature Flags
        $feature_flags = FeatureManager::determine_flags($bot_settings);

        // 4. Prepare Frontend Config
        $frontend_config = Configurator::prepare_config($bot_id, $bot_post, $bot_settings, $feature_flags);

        // 5. Signal Assets Needed AND Force Enqueue
        Assets::require_assets(
            $feature_flags['pdf_ui_enabled'],
            $feature_flags['enable_copy_button'],
            $feature_flags['starters_ui_enabled'],
            $feature_flags['sidebar_ui_enabled'],
            $feature_flags['feedback_ui_enabled'],
            $feature_flags['tts_ui_enabled'],
            $feature_flags['enable_voice_input_ui'],
            true,
            $feature_flags['image_upload_ui_enabled'],
            $feature_flags['file_upload_ui_enabled'],
            $feature_flags['enable_realtime_voice_ui']
        );

        // --- THE FIX: Manually trigger the enqueuer logic ---
        // This ensures assets are loaded even if the `wp_enqueue_scripts` hook has already run.
        if (class_exists(AssetsEnqueuer::class)) {
            (new AssetsEnqueuer())->process_assets();
        }
        // --- END FIX ---

        // 6. Mark as rendered *before* generating HTML
        self::$rendered_bot_ids[$bot_id] = true;

        // 7. Generate the HTML using the Renderer instance
        return $this->renderer->render_chatbot_html($bot_id, $bot_settings, $feature_flags, $frontend_config);
    }

    /**
     * Handles rendering errors, showing messages to admins only.
     * Kept in the main class as it relates to the overall shortcode result.
     *
     * @param WP_Error $error The error object.
     * @param int|null $bot_id Optional bot ID for context.
     * @return string HTML error message or empty string.
     */
    private function handle_render_error(WP_Error $error, $bot_id = null) {
        if (\WPAICG\AIPKit_Role_Manager::user_can_view_admin_notices()) {
            $message = $error->get_error_message();
            $code = $error->get_error_code();
            if ($code === 'already_rendered') {
                 return '<p style="color: orange; font-style: italic; margin: 1em 0;">' . esc_html($message) . '</p>';
            } else {
                 return '<p style="color: red; font-style: italic; margin: 1em 0;">' . esc_html($message) . '</p>';
            }
        }
        // For non-admins, mark as rendered if it was an 'already_rendered' error
        if ($bot_id && $error->get_error_code() === 'already_rendered') {
            self::$rendered_bot_ids[$bot_id] = true;
        }
        return ''; // Silently fail for regular users on other errors
    }
}
