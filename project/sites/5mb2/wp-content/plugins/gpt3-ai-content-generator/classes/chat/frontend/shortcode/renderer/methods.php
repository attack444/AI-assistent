<?php

namespace WPAICG\Chat\Frontend\Shortcode\RendererMethods;

use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\Chat\Utils\AIPKit_SVG_Icons;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- render_chatbot_html.php ---
/**
 * Logic for rendering the main chatbot HTML structure.
 *
 * @param \WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance The instance of the Renderer class.
 * @param int $bot_id
 * @param array $settings Bot Settings.
 * @param array $feature_flags Determined feature flags.
 * @param array $frontend_config Prepared frontend config data.
 * @return string Rendered HTML.
 */
function render_chatbot_html_logic(\WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance, int $bot_id, array $settings, array $feature_flags, array $frontend_config): string {
    ob_start();

    $json_encoded_data = wp_json_encode($frontend_config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $theme = $frontend_config['theme'];
    $voice_input_enabled_ui = $feature_flags['enable_voice_input_ui'] ?? false;
    $allow_openai_web_search_tool = $feature_flags['allowWebSearchTool'] ?? false;
    $allow_google_search_grounding = $feature_flags['allowGoogleSearchGrounding'] ?? false;

    if ($feature_flags['popup_enabled']) {
        // Call the render_popup_mode_html logic via the instance
        $rendererInstance->render_popup_mode_html_internal($bot_id, $theme, $json_encoded_data, $feature_flags, $frontend_config, $voice_input_enabled_ui, $allow_openai_web_search_tool, $allow_google_search_grounding);
    } else {
        // Call the render_inline_mode_html logic via the instance
        $rendererInstance->render_inline_mode_html_internal($bot_id, $theme, $json_encoded_data, $feature_flags, $frontend_config, $voice_input_enabled_ui, $allow_openai_web_search_tool, $allow_google_search_grounding);
    }

    return ob_get_clean();
}

// --- render_popup_mode_html.php ---
/**
 * Logic for rendering the Popup mode HTML.
 * UPDATED: Add data-custom-theme attribute and aipkit-theme-custom class.
 *
 * @param \WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance The instance of the Renderer class.
 * @param int $bot_id
 * @param string $theme
 * @param string $json_encoded_data
 * @param array $feature_flags
 * @param array $frontend_config
 * @param bool $voice_input_enabled_ui
 * @param bool $allow_openai_web_search_tool
 * @param bool $allow_google_search_grounding
 * @return void Echos HTML.
 */
function render_popup_mode_html_logic(
    \WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance,
    int $bot_id,
    string $theme, // This is the selected theme ('light', 'dark', 'custom')
    string $json_encoded_data,
    array $feature_flags,
    array $frontend_config,
    bool $voice_input_enabled_ui,
    bool $allow_openai_web_search_tool,
    bool $allow_google_search_grounding
) {
    $popup_position = $frontend_config['popupPosition'];
    $popup_icon_type = $frontend_config['popupIconType'] ?? BotSettingsManager::DEFAULT_POPUP_ICON_TYPE;
    $popup_icon_type = in_array($popup_icon_type, ['default', 'custom'], true) ? $popup_icon_type : BotSettingsManager::DEFAULT_POPUP_ICON_TYPE;
    $popup_icon_style = $frontend_config['popupIconStyle'] ?? 'circle';
    $popup_icon_style = in_array($popup_icon_style, ['circle', 'square', 'none'], true) ? $popup_icon_style : BotSettingsManager::DEFAULT_POPUP_ICON_STYLE;
    $popup_icon_value = $frontend_config['popupIconValue'] ?? BotSettingsManager::DEFAULT_POPUP_ICON_VALUE;
    $popup_icon_size  = (isset($frontend_config['popupIconSize']) && in_array($frontend_config['popupIconSize'], ['small','medium','large','xlarge'], true))
        ? $frontend_config['popupIconSize']
        : BotSettingsManager::DEFAULT_POPUP_ICON_SIZE;
    $icon_html = '';
    if ($popup_icon_type === 'custom' && !empty($popup_icon_value)) {
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Reason: The image source is correctly retrieved using a WordPress function (e.g., `wp_get_attachment_image_url`). The `<img>` tag is constructed manually to build a custom HTML structure with specific wrappers, classes, or attributes that are not achievable with the standard `wp_get_attachment_image()` function.
        $icon_html = '<img src="' . esc_url($popup_icon_value) . '" alt="' . esc_attr__('Open Chat', 'gpt3-ai-content-generator') . '" class="aipkit_popup_custom_icon" />';
    } else {
        switch ($popup_icon_value) {
            case 'spark':
                $icon_html = AIPKit_SVG_Icons::get_spark_svg();
                break;
            case 'openai':
                $icon_html = AIPKit_SVG_Icons::get_openai_svg();
                break;
            case 'plus': $icon_html = AIPKit_SVG_Icons::get_plus_svg();
                break;
            case 'question-mark': $icon_html = AIPKit_SVG_Icons::get_question_mark_svg();
                break;
            case 'chat-bubble': default: $icon_html = AIPKit_SVG_Icons::get_chat_bubble_svg();
                break;
        }
    }
    $voice_input_class = $voice_input_enabled_ui ? 'aipkit-voice-input-enabled' : '';
    $web_search_class = $allow_openai_web_search_tool ? 'aipkit-web-search-tool-allowed' : '';
    $google_grounding_class = $allow_google_search_grounding ? 'aipkit-google-search-grounding-allowed' : '';

    $custom_theme_class = '';
    $custom_theme_data_attr = '';
    $trigger_inline_style = '';
    $custom_theme_preset_key = isset($frontend_config['customThemePresetKey'])
        ? sanitize_key((string) $frontend_config['customThemePresetKey'])
        : '';
    $is_custom_theme_preset = ($theme === 'custom' && $custom_theme_preset_key !== '');
    if ($theme === 'custom' && !empty($frontend_config['customThemeSettings'])) {
        if (!$is_custom_theme_preset) {
            $custom_theme_class = 'aipkit-theme-custom';
        }
        $custom_theme_data_attr = 'data-custom-theme=\'' . esc_attr(wp_json_encode($frontend_config['customThemeSettings'])) . '\'';

        // Extract primary color for immediate trigger styling (prevents FOUC)
        $custom_settings = $frontend_config['customThemeSettings'];
        $primary_color = '';

        // Check primary_color first, then legacy accent_color
        if (!empty($custom_settings['primary_color'])) {
            $primary_color = $custom_settings['primary_color'];
        } elseif (!empty($custom_settings['accent_color'])) {
            $primary_color = $custom_settings['accent_color'];
        }

        // Build inline style for trigger to prevent flash of dark color
        if (!empty($primary_color)) {
            $trigger_inline_style = '--aipkit-chat-popup-trigger-bg-color:' . esc_attr($primary_color) . ';--aipkit-chat-send-button-bg-color:' . esc_attr($primary_color) . ';';
        }
    } elseif ($theme === 'custom' && !$is_custom_theme_preset) {
        $custom_theme_class = 'aipkit-theme-custom';
    }

    $popup_theme_marker_class = 'aipkit-popup-theme-' . sanitize_html_class(!empty($theme) ? $theme : 'light');
    $is_direct_voice_mode = !empty($frontend_config['directVoiceMode']);
    $popup_wrapper_classes = 'aipkit_popup_wrapper ' . $popup_theme_marker_class;
    if (!$is_direct_voice_mode) {
        $popup_wrapper_classes .= ' aipkit-popup-standard';
    }
    if (!empty($custom_theme_class)) {
        $popup_wrapper_classes .= ' ' . $custom_theme_class;
    }

    $has_main_footer_class = !empty($frontend_config['footerText']) ? 'aipkit-has-main-footer' : '';

    // Build wrapper style attribute
    $wrapper_style_attr = !empty($trigger_inline_style) ? 'style="' . esc_attr($trigger_inline_style) . '"' : '';
    ?>
    <div class="<?php echo esc_attr($popup_wrapper_classes); ?>" id="aipkit_popup_wrapper_<?php echo esc_attr($bot_id); ?>" data-config='<?php echo esc_attr($json_encoded_data); ?>' data-bot-id="<?php echo esc_attr($bot_id); ?>" data-icon-size="<?php echo esc_attr($popup_icon_size); ?>" <?php echo $wrapper_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <button type="button" class="aipkit_popup_trigger aipkit_popup_position-<?php echo esc_attr($popup_position); ?> aipkit_popup_trigger--size-<?php echo esc_attr($popup_icon_size); ?>" id="aipkit_popup_trigger_<?php echo esc_attr($bot_id); ?>" aria-label="<?php esc_attr_e('Open Chat', 'gpt3-ai-content-generator'); ?>" title="<?php esc_attr_e('Open Chat', 'gpt3-ai-content-generator'); ?>" aria-haspopup="dialog" aria-controls="aipkit_chat_container_<?php echo esc_attr($bot_id); ?>" aria-expanded="false" data-icon-style="<?php echo esc_attr($popup_icon_style); ?>" data-icon-type="<?php echo esc_attr($popup_icon_type); ?>" data-label-open="<?php esc_attr_e('Open Chat', 'gpt3-ai-content-generator'); ?>" data-label-close="<?php esc_attr_e('Close Chat', 'gpt3-ai-content-generator'); ?>">
            <span class="aipkit_popup_icon aipkit_popup_icon--open">
                <?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
            </span>
        </button>
        <?php
        $hint_enabled = !empty($frontend_config['popupLabelEnabled']) && !empty($frontend_config['popupLabelText']);
        if ($hint_enabled) {
            $dismissible = !empty($frontend_config['popupLabelDismissible']);
            // Plain text only
            $hint_text = wp_strip_all_tags((string)$frontend_config['popupLabelText']);
            $hint_size = isset($frontend_config['popupLabelSize']) && in_array($frontend_config['popupLabelSize'], ['small','medium','large','xlarge'], true)
                ? $frontend_config['popupLabelSize']
                : BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE;
            ?>
            <div
                class="aipkit_popup_hint aipkit_popup_position-<?php echo esc_attr($popup_position); ?> aipkit_popup_hint--size-<?php echo esc_attr($hint_size); ?>"
                id="aipkit_popup_hint_<?php echo esc_attr($bot_id); ?>"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                aria-hidden="true"
                inert
                hidden
                data-bot-id="<?php echo esc_attr($bot_id); ?>"
            >
                <span class="aipkit_popup_hint_text"><?php echo esc_html($hint_text); ?></span>
                <?php if ($dismissible): ?>
                    <button type="button" class="aipkit_popup_hint_close" aria-label="<?php echo esc_attr($frontend_config['text']['dismissHint'] ?? 'Dismiss'); ?>" aria-controls="aipkit_popup_hint_<?php echo esc_attr($bot_id); ?>" title="<?php echo esc_attr($frontend_config['text']['dismissHint'] ?? 'Dismiss'); ?>">&times;</button>
                <?php endif; ?>
            </div>
        <?php } // end hint_enabled ?>
        <div class="aipkit_chat_container aipkit_popup_content aipkit-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($custom_theme_class); ?> <?php echo esc_attr($has_main_footer_class); ?> aipkit_popup_position-<?php echo esc_attr($popup_position); ?> aipkit-sidebar-state-closed <?php echo esc_attr($voice_input_class); ?> <?php echo esc_attr($web_search_class); ?> <?php echo esc_attr($google_grounding_class); ?>" id="aipkit_chat_container_<?php echo esc_attr($bot_id); ?>" aria-hidden="true" inert <?php echo $custom_theme_data_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?> >
            <div class="aipkit_chat_main">
                <?php if ($feature_flags['show_header']): ?>
                    <?php $rendererInstance->render_header_html_internal($feature_flags, $frontend_config, true); ?>
                <?php endif; ?>
                <div class="aipkit_chat_messages"></div>
                <?php if ($feature_flags['starters_ui_enabled']): ?>
                    <div class="aipkit_conversation_starters"></div>
                <?php endif; ?>
                <?php $rendererInstance->render_input_area_html_internal($frontend_config, false, $feature_flags, $allow_openai_web_search_tool, $allow_google_search_grounding); ?>
                <?php $rendererInstance->render_footer_html_internal($frontend_config['footerText']); ?>
            </div>
        </div>
    </div>
    <?php
}

// --- render_inline_mode_html.php ---
/**
 * Logic for rendering the Inline mode HTML.
 * UPDATED: Add data-custom-theme attribute and aipkit-theme-custom class.
 *
 * @param \WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance The instance of the Renderer class.
 * @param int $bot_id
 * @param string $theme
 * @param string $json_encoded_data
 * @param array $feature_flags
 * @param array $frontend_config
 * @param bool $voice_input_enabled_ui
 * @param bool $allow_openai_web_search_tool
 * @param bool $allow_google_search_grounding
 * @return void Echos HTML.
 */
function render_inline_mode_html_logic(
    \WPAICG\Chat\Frontend\Shortcode\Renderer $rendererInstance,
    int $bot_id,
    string $theme, // This is the selected theme ('light', 'dark', 'custom')
    string $json_encoded_data,
    array $feature_flags,
    array $frontend_config,
    bool $voice_input_enabled_ui,
    bool $allow_openai_web_search_tool,
    bool $allow_google_search_grounding
) {
    $voice_input_class = $voice_input_enabled_ui ? 'aipkit-voice-input-enabled' : '';
    $web_search_class = $allow_openai_web_search_tool ? 'aipkit-web-search-tool-allowed' : '';
    $google_grounding_class = $allow_google_search_grounding ? 'aipkit-google-search-grounding-allowed' : '';

    $custom_theme_class = '';
    $custom_theme_data_attr = '';
    $custom_theme_preset_key = isset($frontend_config['customThemePresetKey'])
        ? sanitize_key((string) $frontend_config['customThemePresetKey'])
        : '';
    $is_custom_theme_preset = ($theme === 'custom' && $custom_theme_preset_key !== '');
    if ($theme === 'custom' && !empty($frontend_config['customThemeSettings'])) {
        if (!$is_custom_theme_preset) {
            $custom_theme_class = 'aipkit-theme-custom';
        }
        $custom_theme_data_attr = 'data-custom-theme=\'' . esc_attr(wp_json_encode($frontend_config['customThemeSettings'])) . '\'';
    } elseif ($theme === 'custom' && !$is_custom_theme_preset) { // Custom theme selected, but no settings (will fallback to light/base)
        $custom_theme_class = 'aipkit-theme-custom';
    }

    $has_main_footer_class = !empty($frontend_config['footerText']) ? 'aipkit-has-main-footer' : '';

    ?>
    <div class="aipkit_chat_container aipkit-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($custom_theme_class); ?> <?php echo esc_attr($has_main_footer_class); ?> aipkit-sidebar-state-closed <?php echo esc_attr($voice_input_class); ?> <?php echo esc_attr($web_search_class); ?> <?php echo esc_attr($google_grounding_class); ?>" id="aipkit_chat_container_<?php echo esc_attr($bot_id); ?>" data-bot-id="<?php echo esc_attr($bot_id); ?>" data-config='<?php echo esc_attr($json_encoded_data); ?>' <?php echo $custom_theme_data_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $custom_theme_data_attr is properly escaped ?> >
        <?php if ($feature_flags['sidebar_ui_enabled']): ?>
            <?php $rendererInstance->render_sidebar_html_internal($frontend_config); ?>
        <?php endif; ?>
        <div class="aipkit_chat_main">
             <?php if ($feature_flags['show_header']): ?>
                <?php $rendererInstance->render_header_html_internal($feature_flags, $frontend_config, false); ?>
             <?php endif; ?>
            <div class="aipkit_chat_messages"></div>
             <?php if ($feature_flags['starters_ui_enabled']): ?>
                <div class="aipkit_conversation_starters"></div>
             <?php endif; ?>
            <?php $rendererInstance->render_input_area_html_internal($frontend_config, true, $feature_flags, $allow_openai_web_search_tool, $allow_google_search_grounding); ?>
            <?php $rendererInstance->render_footer_html_internal($frontend_config['footerText']); ?>
        </div>
    </div>
    <?php
}

// --- render_header_html.php ---
/**
 * Logic for rendering the chat header HTML.
 *
 * @param array $feature_flags
 * @param array $frontend_config
 * @param bool $is_popup
 * @return void Echos HTML.
 */
function render_header_html_logic(array $feature_flags, array $frontend_config, bool $is_popup) {
    // SVG definitions
    $sidebar_toggle_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-menu-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6l16 0" /><path d="M4 12l16 0" /><path d="M4 18l16 0" /></svg>';
    $fullscreen_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-maximize"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 4l4 0l0 4" /><path d="M14 10l6 -6" /><path d="M8 20l-4 0l0 -4" /><path d="M4 20l6 -6" /><path d="M16 20l4 0l0 -4" /><path d="M14 14l6 6" /><path d="M8 4l-4 0l0 4" /><path d="M4 4l6 6" /></svg>';
    $download_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-download"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>';
    $close_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>';
    $header_avatar_type = isset($frontend_config['headerAvatarType']) ? (string) $frontend_config['headerAvatarType'] : '';
    $header_avatar_value = isset($frontend_config['headerAvatarValue']) ? (string) $frontend_config['headerAvatarValue'] : '';
    $header_avatar_url = '';
    $header_avatar_svg = '';
    $popup_icon_url = '';
    $popup_icon_svg = '';
    $resolve_icon_svg = function (string $icon_key): string {
        switch ($icon_key) {
            case 'spark':
                return AIPKit_SVG_Icons::get_spark_svg();
            case 'openai':
                return AIPKit_SVG_Icons::get_openai_svg();
            case 'plus':
                return AIPKit_SVG_Icons::get_plus_svg();
            case 'question-mark':
                return AIPKit_SVG_Icons::get_question_mark_svg();
            case 'chat-bubble':
            default:
                return AIPKit_SVG_Icons::get_chat_bubble_svg();
        }
    };
    $allowed_header_icons = ['chat-bubble', 'spark', 'openai', 'plus', 'question-mark'];
    if ($is_popup) {
        if ($header_avatar_type === 'custom') {
            if ($header_avatar_value !== '') {
                $header_avatar_url = $header_avatar_value;
            } else {
                $legacy_header_url = isset($frontend_config['headerAvatarUrl']) ? trim((string) $frontend_config['headerAvatarUrl']) : '';
                $header_avatar_url = $legacy_header_url;
            }
        } elseif ($header_avatar_type === 'default') {
            $icon_key = in_array($header_avatar_value, $allowed_header_icons, true) ? $header_avatar_value : 'chat-bubble';
            $header_avatar_svg = $resolve_icon_svg($icon_key);
        } else {
            $legacy_header_url = isset($frontend_config['headerAvatarUrl']) ? trim((string) $frontend_config['headerAvatarUrl']) : '';
            $header_avatar_url = $legacy_header_url;
        }

        if ($header_avatar_url === '' && $header_avatar_svg === '') {
            $popup_icon_type = isset($frontend_config['popupIconType']) ? (string) $frontend_config['popupIconType'] : '';
            $popup_icon_value = isset($frontend_config['popupIconValue']) ? (string) $frontend_config['popupIconValue'] : '';
            if ($popup_icon_type === 'custom' && $popup_icon_value !== '') {
                $popup_icon_url = $popup_icon_value;
            } elseif ($popup_icon_value !== '') {
                $popup_icon_svg = $resolve_icon_svg($popup_icon_value);
            }
        }
    }
    $header_name = isset($frontend_config['headerName']) ? trim((string) $frontend_config['headerName']) : '';
    if ($header_name === '') {
        $header_name = __('Chatbot', 'gpt3-ai-content-generator');
    }
    $header_online_text = isset($frontend_config['headerOnlineText']) ? trim((string) $frontend_config['headerOnlineText']) : '';
    if ($header_online_text === '') {
        $header_online_text = __('Online', 'gpt3-ai-content-generator');
    }
    $download_menu_id = function_exists('wp_unique_id')
        ? wp_unique_id('aipkit_download_menu_')
        : uniqid('aipkit_download_menu_', false);
    $fallback_avatar_svg = class_exists(AIPKit_SVG_Icons::class)
        ? AIPKit_SVG_Icons::get_chat_bubble_svg()
        : '';
    ?>
    <div class="aipkit_chat_header">
        <div class="aipkit_header_info">
            <?php if (!$is_popup && $feature_flags['sidebar_ui_enabled']): ?>
                <button type="button" class="aipkit_header_btn aipkit_sidebar_toggle_btn" title="<?php echo esc_attr($frontend_config['text']['sidebarToggle']); ?>" aria-label="<?php echo esc_attr($frontend_config['text']['sidebarToggle']); ?>" aria-expanded="false">
                    <?php echo $sidebar_toggle_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            <?php endif; ?>
            <?php if ($is_popup): ?>
                <div class="aipkit_header_identity">
                <div class="aipkit_header_avatar aipkit_header_icon">
                    <?php if (!empty($header_avatar_url)) : ?>
                        <img src="<?php echo esc_url($header_avatar_url); ?>" alt="<?php echo esc_attr($header_name); ?>" class="aipkit_header_avatar_img" />
                    <?php elseif (!empty($header_avatar_svg)) : ?>
                        <span class="aipkit_header_avatar_icon" aria-hidden="true">
                            <?php echo $header_avatar_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    <?php elseif (!empty($popup_icon_url)) : ?>
                        <img src="<?php echo esc_url($popup_icon_url); ?>" alt="<?php echo esc_attr($header_name); ?>" class="aipkit_header_avatar_img" />
                    <?php elseif (!empty($popup_icon_svg)) : ?>
                        <span class="aipkit_header_avatar_icon" aria-hidden="true">
                            <?php echo $popup_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    <?php elseif (!empty($fallback_avatar_svg)) : ?>
                        <span class="aipkit_header_avatar_icon" aria-hidden="true">
                            <?php echo $fallback_avatar_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    <?php endif; ?>
                </div>
                    <div class="aipkit_header_meta">
                        <div class="aipkit_header_name"><?php echo esc_html($header_name); ?></div>
                        <?php if (!empty($header_online_text)) : ?>
                            <div class="aipkit_header_status">
                                <span class="aipkit_header_status_dot" aria-hidden="true"></span>
                                <span class="aipkit_header_status_text"><?php echo esc_html($header_online_text); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($is_popup): ?>
            <div class="aipkit_header_drag_zone" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="aipkit_header_actions">
            <?php if ($feature_flags['enable_fullscreen']): ?>
                <button type="button" class="aipkit_header_btn aipkit_fullscreen_btn" title="<?php echo esc_attr($frontend_config['text']['fullscreen']); ?>" aria-label="<?php echo esc_attr($frontend_config['text']['fullscreen']); ?>" aria-expanded="false">
                    <?php echo $fullscreen_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            <?php endif; ?>
            <?php if ($feature_flags['enable_download']): ?>
                <div class="aipkit_download_wrapper">
                    <button type="button" class="aipkit_header_btn aipkit_download_btn" title="<?php echo esc_attr($frontend_config['text']['download']); ?>" aria-label="<?php echo esc_attr($frontend_config['text']['download']); ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr($download_menu_id); ?>">
                        <?php echo $download_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                    <?php if ($feature_flags['pdf_ui_enabled']): ?>
                        <div class="aipkit_download_menu" id="<?php echo esc_attr($download_menu_id); ?>" role="menu" aria-hidden="true">
                            <button type="button" class="aipkit_download_menu_item" role="menuitem" data-format="txt"><?php echo esc_html($frontend_config['text']['downloadTxt']); ?></button>
                            <button type="button" class="aipkit_download_menu_item" role="menuitem" data-format="pdf"><?php echo esc_html($frontend_config['text']['downloadPdf']); ?></button>
                        </div>
                    <?php elseif ($feature_flags['enable_download']): ?>
                        <div class="aipkit_download_menu" id="<?php echo esc_attr($download_menu_id); ?>" role="menu" aria-hidden="true">
                            <button type="button" class="aipkit_download_menu_item" role="menuitem" data-format="txt"><?php echo esc_html($frontend_config['text']['downloadTxt']); ?></button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($is_popup): ?>
                <button type="button" class="aipkit_header_btn aipkit_popup_close_btn" title="<?php echo esc_attr__('Close chat', 'gpt3-ai-content-generator'); ?>" aria-label="<?php echo esc_attr__('Close chat', 'gpt3-ai-content-generator'); ?>">
                    <?php echo $close_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// --- render_input_area_html.php ---
/**
 * Logic for rendering the chat input area HTML.
 *
 * @param array $frontend_config
 * @param bool $is_inline Whether the bot is in inline mode.
 * @param array $feature_flags Determined feature flags.
 * @param bool $allow_openai_web_search_tool Whether the OpenAI web search tool is allowed for this bot.
 * @param bool $allow_google_search_grounding Whether Google Search Grounding is allowed for this bot.
 * @return void Echos HTML.
 */
function render_input_area_html_logic(array $frontend_config, bool $is_inline = false, array $feature_flags = [], bool $allow_openai_web_search_tool = false, bool $allow_google_search_grounding = false) {
    // Autofocus is disabled for now as it can cause issues with focus management in some browsers.
    // $autofocus_attr = $is_inline ? 'autofocus' : '';
    $input_action_button_enabled = $feature_flags['input_action_button_enabled'] ?? false;
    $file_upload_ui_enabled = $feature_flags['file_upload_ui_enabled'] ?? false;
    $image_upload_ui_enabled = $feature_flags['image_upload_ui_enabled'] ?? false;
    $voice_input_enabled_ui = $feature_flags['enable_voice_input_ui'] ?? false;
    $realtime_voice_enabled_ui = $feature_flags['enable_realtime_voice_ui'] ?? false;
    $bot_id = $frontend_config['botId'] ?? 'default';

    // SVG definitions
    $attachment_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-paperclip"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 7l-6.5 6.5a1.5 1.5 0 0 0 3 3l6.5 -6.5a3 3 0 0 0 -6 -6l-6.5 6.5a4.5 4.5 0 0 0 9 9l6.5 -6.5" /></svg>';
    $image_upload_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo-up"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8h.01" /><path d="M12.5 21h-6.5a3 3 0 0 1 -3 -3v-12a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6.5" /><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l3.5 3.5" /><path d="M14 14l1 -1c.679 -.653 1.473 -.829 2.214 -.526" /><path d="M19 22v-6" /><path d="M22 19l-3 -3l-3 3" /></svg>';
    $file_upload_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-upload"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M12 11v6" /><path d="M9.5 13.5l2.5 -2.5l2.5 2.5" /></svg>';
    $send_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-up aipkit_send_icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M18 11l-6 -6" /><path d="M6 11l6 -6" /></svg>';
    $clear_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eraser aipkit_clear_icon" hidden><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19 20h-10.5l-4.21 -4.3a1 1 0 0 1 0 -1.41l10 -10a1 1 0 0 1 1.41 0l5 5a1 1 0 0 1 0 1.41l-9.2 9.3" /><path d="M18 13.3l-6.3 -6.3" /></svg>';
    $stop_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-player-stop aipkit_stop_icon" hidden><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="7" y="7" width="10" height="10" rx="2" /></svg>';
    $microphone_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-microphone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 2m0 3a3 3 0 0 1 3 -3h0a3 3 0 0 1 3 3v5a3 3 0 0 1 -3 3h0a3 3 0 0 1 -3 -3z" /><path d="M5 10a7 7 0 0 0 14 0" /><path d="M8 21l8 0" /><path d="M12 17l0 4" /></svg>';
    $volume_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-volume"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8a5 5 0 0 1 0 8" /><path d="M17.7 5a9 9 0 0 1 0 14" /><path d="M6 15h-2a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1h2l3.5 -4.5a.8 .8 0 0 1 1.5 .5v14a.8 .8 0 0 1 -1.5 .5l-3.5 -4.5" /></svg>';
    $world_www_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-world-www"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.5 7a9 9 0 0 0 -7.5 -4a8.991 8.991 0 0 0 -7.484 4" /><path d="M11.5 3a16.989 16.989 0 0 0 -1.826 4" /><path d="M12.5 3a16.989 16.989 0 0 1 1.828 4" /><path d="M19.5 17a9 9 0 0 1 -7.5 4a8.991 8.991 0 0 1 -7.484 -4" /><path d="M11.5 21a16.989 16.989 0 0 1 -1.826 -4" /><path d="M12.5 21a16.989 16.989 0 0 0 1.828 -4" /><path d="M2 10l1 4l1.5 -4l1.5 4l1 -4" /><path d="M17 10l1 4l1.5 -4l1.5 4l1 -4" /><path d="M9.5 10l1 4l1.5 -4l1.5 4l1 -4" /></svg>';

    $initial_icon_html = $attachment_svg;
    $initial_aria_label = __('Attach files or use tools', 'gpt3-ai-content-generator');
    $initial_has_popup = 'true';

    if ($file_upload_ui_enabled && !$image_upload_ui_enabled) {
        $initial_icon_html = $file_upload_svg;
        $initial_aria_label = __('Upload File (TXT, PDF)', 'gpt3-ai-content-generator');
        $initial_has_popup = 'false';
    } elseif (!$file_upload_ui_enabled && $image_upload_ui_enabled) {
        $initial_icon_html = $image_upload_svg;
        $initial_aria_label = __('Upload Image', 'gpt3-ai-content-generator');
        $initial_has_popup = 'false';
    }
    $input_action_menu_id = ($input_action_button_enabled && $file_upload_ui_enabled && $image_upload_ui_enabled)
        ? 'aipkit_input_action_menu_' . uniqid('', false)
        : '';

    ?>
    <div class="aipkit_chat_input">
        <div class="aipkit_chat_input_wrapper">
            <textarea
                id="aipkit_chat_input_field_<?php echo esc_attr($bot_id); ?>"
                name="aipkit_chat_message_<?php echo esc_attr($bot_id); ?>"
                class="aipkit_chat_input_field"
                placeholder="<?php echo esc_attr($frontend_config['text']['typeMessage']); ?>"
                aria-label="<?php esc_attr_e('Chat message input', 'gpt3-ai-content-generator'); ?>"
                rows="1"
            ></textarea>
             <div class="aipkit_chat_input_actions_bar">
                <div class="aipkit_chat_input_actions_left">
                    <button
                        type="button"
                        class="aipkit_input_action_btn aipkit_input_action_toggle"
                        aria-label="<?php echo esc_attr($initial_aria_label); ?>"
                        role="button"
                        <?php if ($initial_has_popup === 'true'): ?>
                            aria-haspopup="true"
                            aria-controls="<?php echo esc_attr($input_action_menu_id); ?>"
                            aria-expanded="false"
                        <?php endif; ?>
                        <?php if (!$input_action_button_enabled): ?>hidden<?php endif; ?>
                    >
                        <?php echo $initial_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                     <?php if ($allow_openai_web_search_tool): ?>
                     <button
                        type="button"
                        class="aipkit_input_action_btn aipkit_web_search_toggle"
                        aria-label="<?php echo esc_attr($frontend_config['text']['webSearchToggle'] ?? __('Toggle Web Search', 'gpt3-ai-content-generator')); ?>"
                        title="<?php echo esc_attr($frontend_config['text']['webSearchInactive'] ?? __('Web Search Inactive', 'gpt3-ai-content-generator')); ?>"
                        role="button"
                        aria-pressed="false"
                    >
                        <?php echo $world_www_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                    <?php endif; ?>
                    <?php if ($allow_google_search_grounding): ?>
                     <button
                        type="button"
                        class="aipkit_input_action_btn aipkit_google_search_grounding_toggle"
                        aria-label="<?php echo esc_attr($frontend_config['text']['googleSearchGroundingToggle'] ?? __('Toggle Google Search Grounding', 'gpt3-ai-content-generator')); ?>"
                        title="<?php echo esc_attr($frontend_config['text']['googleSearchGroundingInactive'] ?? __('Google Search Grounding Inactive', 'gpt3-ai-content-generator')); ?>"
                        role="button"
                        aria-pressed="false"
                    >
                        <?php echo $world_www_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="aipkit_chat_input_actions_right">
                    <button
                        class="aipkit_input_action_btn aipkit_realtime_voice_agent_btn"
                        aria-label="<?php esc_attr_e('Start Voice Conversation', 'gpt3-ai-content-generator'); ?>"
                        title="<?php esc_attr_e('Start Voice Conversation', 'gpt3-ai-content-generator'); ?>"
                        type="button"
                        <?php if (!$realtime_voice_enabled_ui): ?>hidden<?php endif; ?>
                    >
                        <?php echo $volume_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                    <button
                        class="aipkit_input_action_btn aipkit_voice_input_btn"
                        aria-label="<?php esc_attr_e('Voice input', 'gpt3-ai-content-generator'); ?>"
                        title="<?php esc_attr_e('Voice input', 'gpt3-ai-content-generator'); ?>"
                        type="button"
                        <?php if (!$voice_input_enabled_ui): ?>hidden<?php endif; ?>
                    >
                        <?php echo $microphone_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </button>
                    <button
                        class="aipkit_input_action_btn aipkit_chat_action_btn aipkit_send_btn"
                        aria-label="<?php echo esc_attr($frontend_config['text']['sendMessage']); ?>"
                        title="<?php echo esc_attr($frontend_config['text']['sendMessage']); ?>"
                        type="button"
                    >
                        <?php echo $send_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo $clear_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo $stop_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span class="aipkit_spinner" hidden></span>
                        <span class="aipkit_chat_action_timer" aria-hidden="true" hidden></span>
                    </button>
                </div>
            </div>
        </div>
        <?php if ($input_action_button_enabled && ($file_upload_ui_enabled && $image_upload_ui_enabled) ): ?>
            <div class="aipkit_input_action_menu" id="<?php echo esc_attr($input_action_menu_id); ?>" role="menu" aria-hidden="true" hidden>
                <?php if ($file_upload_ui_enabled): ?>
                    <button type="button" class="aipkit_input_action_menu_item" role="menuitem" aria-label="<?php esc_attr_e('Upload File (TXT, PDF)', 'gpt3-ai-content-generator'); ?>"><?php echo $file_upload_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
                <?php endif; ?>
                <?php if ($image_upload_ui_enabled): ?>
                    <button type="button" class="aipkit_input_action_menu_item" role="menuitem" aria-label="<?php esc_attr_e('Upload image', 'gpt3-ai-content-generator'); ?>"><?php echo $image_upload_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// --- render_footer_html.php ---
/**
 * Logic for rendering the chat footer HTML.
 *
 * @param string $footer_text
 * @return void Echos HTML.
 */
function render_footer_html_logic(string $footer_text) {
    if (!empty($footer_text)) {
        ?>
        <div class="aipkit_chat_footer"><?php echo wp_kses_post($footer_text); ?></div>
        <?php
    }
}

// --- render_sidebar_html.php ---
/**
 * Logic for rendering the conversation sidebar HTML.
 *
 * @param array $frontend_config
 * @return void Echos HTML.
 */
function render_sidebar_html_logic(array $frontend_config)
{
    $new_chat_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>';
    ?>
    <div class="aipkit_chat_sidebar" aria-hidden="true">
         <div class="aipkit_sidebar_header">
            <h4 class="aipkit_sidebar_title"><?php echo esc_html($frontend_config['text']['conversations']); ?></h4>
            <button type="button" class="aipkit_btn aipkit_btn-secondary aipkit_btn-small aipkit_sidebar_new_chat_btn" aria-label="<?php echo esc_attr($frontend_config['text']['newChat']); ?>">
                 <?php echo $new_chat_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html($frontend_config['text']['newChat']); ?>
            </button>
         </div>
         <div class="aipkit_sidebar_content" aria-live="polite">
         </div>
         <div class="aipkit_sidebar_footer">
         </div>
    </div>
    <?php
}

// --- createActionsContainerHTML.php ---
/**
 * Logic for creating the HTML for message action buttons.
 *
 * @param array $config
 * @return string HTML for the actions container.
 */
function createActionsContainerHTML_logic(array $config): string {
    // SVG definitions
    $play_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-player-play"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 4v16l13 -8z" /></svg>';
    $copy_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-copy"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" /><path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" /></svg>';
    $thumb_up_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-thumb-up"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 11v8a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1v-7a1 1 0 0 1 1 -1h3a4 4 0 0 0 4 -4v-1a2 2 0 0 1 4 0v5h3a2 2 0 0 1 2 2l-1 5a2 3 0 0 1 -2 2h-7a3 3 0 0 1 -3 -3" /></svg>';
    $thumb_down_svg = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-thumb-down"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 13v-8a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v7a1 1 0 0 0 1 1h3a4 4 0 0 1 4 4v1a2 2 0 0 0 4 0v-5h3a2 2 0 0 0 2 -2l-1 -5a2 3 0 0 0 -2 -2h-7a3 3 0 0 0 -3 3" /></svg>';

    $actionsHTML = '';
    $texts = $config['text'] ?? [];
    if ($config['ttsEnabled'] ?? false) {
        $playTitle = $texts['playActionLabel'] ?? 'Play audio';
        $actionsHTML .= sprintf(
             '<button type="button" class="aipkit_action_btn aipkit_play_btn" title="%1$s" aria-label="%1$s">' .
             '%2$s' .
             '</button>',
             esc_attr($playTitle),
             $play_svg
         );
    }
    if ($config['enableCopyButton'] ?? false) {
        $copyTitle = $texts['copyActionLabel'] ?? 'Copy response';
        $actionsHTML .= sprintf(
            '<button type="button" class="aipkit_action_btn aipkit_copy_btn" title="%1$s" aria-label="%1$s">%2$s</button>',
            esc_attr($copyTitle),
            $copy_svg
        );
    }
    if ($config['enableFeedback'] ?? false) {
        $likeTitle = $texts['feedbackLikeLabel'] ?? 'Like response';
        $dislikeTitle = $texts['feedbackDislikeLabel'] ?? 'Dislike response';
        $actionsHTML .= sprintf(
            '<button type="button" class="aipkit_action_btn aipkit_feedback_btn aipkit_thumb_up_btn" title="%1$s" aria-label="%1$s" data-feedback="up">%2$s</button>',
            esc_attr($likeTitle),
            $thumb_up_svg
        );
         $actionsHTML .= sprintf(
            '<button type="button" class="aipkit_action_btn aipkit_feedback_btn aipkit_thumb_down_btn" title="%1$s" aria-label="%1$s" data-feedback="down">%2$s</button>',
            esc_attr($dislikeTitle),
            $thumb_down_svg
        );
    }

    if ($actionsHTML) {
        return '<div class="aipkit_message_actions">' . $actionsHTML . '</div>';
    }
    return '';
}
