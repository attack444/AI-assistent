<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Chat\Frontend\Shortcode;

use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\Chat\Utils\AIPKit_SVG_Icons;
use WPAICG\Chat\Frontend\Assets\AssetsRequireFlags;

// Require renderer logic files.
require_once __DIR__ . '/renderer/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the HTML rendering for the Chatbot Shortcode.
 * Delegates logic to namespaced functions.
 */
class Renderer {

    public function __construct() {
        // Load SVG Icons utility if not already loaded
        if (!class_exists(AIPKit_SVG_Icons::class)) {
            $svg_util_path = WPAICG_PLUGIN_DIR . 'classes/chat/utils/class-aipkit-svg-icons.php';
            if (file_exists($svg_util_path)) {
                require_once $svg_util_path;
            }
        }
    }

    /**
     * Generates the final HTML output for the chatbot.
     *
     * @param int $bot_id
     * @param array $settings Bot Settings.
     * @param array $feature_flags Determined feature flags.
     * @param array $frontend_config Prepared frontend config data.
     * @return string Rendered HTML.
     */
    public function render_chatbot_html(int $bot_id, array $settings, array $feature_flags, array $frontend_config): string {
        // Ensure AssetsRequireFlags class is loaded before calling its static method
        if (!class_exists(AssetsRequireFlags::class)) {
            $flags_path = WPAICG_PLUGIN_DIR . 'classes/chat/frontend/assets/classes.php';
            if (file_exists($flags_path)) {
                require_once $flags_path;
            }
        }
        if (class_exists(AssetsRequireFlags::class)) {
            AssetsRequireFlags::set_flags(
                $feature_flags['pdf_ui_enabled'],
                $feature_flags['enable_copy_button'],
                $feature_flags['starters_ui_enabled'],
                $feature_flags['sidebar_ui_enabled'],
                $feature_flags['feedback_ui_enabled'],
                $feature_flags['tts_ui_enabled'],
                $feature_flags['enable_voice_input_ui'],
                true, // Assume image generation command is always potentially available
                $feature_flags['image_upload_ui_enabled'],
                $feature_flags['file_upload_ui_enabled'],
                $feature_flags['enable_realtime_voice_ui']
            );
        }

        return RendererMethods\render_chatbot_html_logic($this, $bot_id, $settings, $feature_flags, $frontend_config);
    }

    /**
     * Renders the HTML structure for the Popup mode.
     * Internal method to be called by namespaced logic.
     */
    public function render_popup_mode_html_internal(int $bot_id, string $theme, string $json_encoded_data, array $feature_flags, array $frontend_config, bool $voice_input_enabled_ui, bool $allow_openai_web_search_tool, bool $allow_google_search_grounding) {
        RendererMethods\render_popup_mode_html_logic($this, $bot_id, $theme, $json_encoded_data, $feature_flags, $frontend_config, $voice_input_enabled_ui, $allow_openai_web_search_tool, $allow_google_search_grounding);
    }

    /**
     * Renders the HTML structure for the Inline mode.
     * Internal method to be called by namespaced logic.
     */
    public function render_inline_mode_html_internal(int $bot_id, string $theme, string $json_encoded_data, array $feature_flags, array $frontend_config, bool $voice_input_enabled_ui, bool $allow_openai_web_search_tool, bool $allow_google_search_grounding) {
        RendererMethods\render_inline_mode_html_logic($this, $bot_id, $theme, $json_encoded_data, $feature_flags, $frontend_config, $voice_input_enabled_ui, $allow_openai_web_search_tool, $allow_google_search_grounding);
    }

    /**
     * Renders the chat header HTML.
     * Internal method to be called by namespaced logic.
     */
    public function render_header_html_internal(array $feature_flags, array $frontend_config, bool $is_popup) {
        RendererMethods\render_header_html_logic($feature_flags, $frontend_config, $is_popup);
    }

    /**
     * Renders the chat input area HTML.
     * Internal method to be called by namespaced logic.
     */
    public function render_input_area_html_internal(array $frontend_config, bool $is_inline = false, array $feature_flags = [], bool $allow_openai_web_search_tool = false, bool $allow_google_search_grounding = false) {
        RendererMethods\render_input_area_html_logic($frontend_config, $is_inline, $feature_flags, $allow_openai_web_search_tool, $allow_google_search_grounding);
    }

    /**
     * Renders the optional chat footer HTML.
     * Internal method to be called by namespaced logic.
     */
    public function render_footer_html_internal(string $footer_text) {
        RendererMethods\render_footer_html_logic($footer_text);
    }

    /**
     * Renders the conversation sidebar HTML.
     * Internal method to be called by namespaced logic.
     */
    public function render_sidebar_html_internal(array $frontend_config) {
        RendererMethods\render_sidebar_html_logic($frontend_config);
    }

    /**
     * Creates the HTML for message action buttons.
     * This method can remain public if directly used by other classes, or be made internal.
     */
    public function createActionsContainerHTML(array $config): string {
        return RendererMethods\createActionsContainerHTML_logic($config);
    }
}
