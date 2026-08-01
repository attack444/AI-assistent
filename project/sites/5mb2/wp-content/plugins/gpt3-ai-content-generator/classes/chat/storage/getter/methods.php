<?php

namespace WPAICG\Chat\Storage\GetterMethods;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;
use WP_Post;
use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- fn-validate-bot-post.php ---
/**
 * Validates the bot post ID, type, and status.
 *
 * @param int $bot_id The ID of the bot post.
 * @return WP_Post|WP_Error The WP_Post object on success, or WP_Error on failure.
 */
function validate_bot_post_logic(int $bot_id) {
    if (!class_exists(AdminSetup::class)) {
        $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
        if (file_exists($admin_setup_path)) {
            require_once $admin_setup_path;
        } else {
            return new WP_Error('dependency_missing_validator', __('AdminSetup class missing.', 'gpt3-ai-content-generator'));
        }
    }

    $post = get_post($bot_id);
    if (!$post) {
        return new WP_Error('post_not_found_validator', __('Chatbot post not found.', 'gpt3-ai-content-generator'));
    }

    if ($post->post_type !== AdminSetup::POST_TYPE) {
        return new WP_Error('invalid_post_type_validator', __('Invalid chatbot post type.', 'gpt3-ai-content-generator'));
    }

    if (!in_array($post->post_status, ['publish', 'draft'], true)) {
        return new WP_Error('invalid_post_status_validator', __('Chatbot post has an invalid status.', 'gpt3-ai-content-generator'));
    }
    return $post;
}

// --- fn-get-general-bot-settings.php ---
/**
 * Retrieves general bot settings like greeting and instructions.
 * MODIFIED: Explicitly adds 'bot_id' and 'name' to the settings array.
 *
 * @param int $bot_id The ID of the bot post.
 * @param string $bot_name The name of the bot.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of general settings.
 */
function get_general_bot_settings_logic(int $bot_id, string $bot_name, callable $get_meta_fn): array
{
    $settings = [];

    // Ensure BotSettingsManager is loaded for constants
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php'; // Path relative to getter directory
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['bot_id'] = $bot_id;
    $settings['name'] = $bot_name;

    $default_greeting = __('Hello there!', 'gpt3-ai-content-generator');
    $default_subgreeting = __('How can I help you today?', 'gpt3-ai-content-generator');
    $settings['greeting'] = $get_meta_fn('_aipkit_greeting_message', $default_greeting);
    $settings['subgreeting'] = $get_meta_fn('_aipkit_subgreeting_message', $default_subgreeting);

    $default_instructions = "You are a helpful AI Assistant. Please be friendly. Today's date is [date].";
    $settings['instructions'] = $get_meta_fn('_aipkit_instructions', $default_instructions);

    return $settings;
}

// --- fn-get-ai-configuration.php ---
/**
 * Retrieves AI configuration settings like provider, model, temperature, etc.
 *
 * @param int $bot_id The ID of the bot post.
 * @param string|null $current_provider_from_main_settings The current global provider.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of AI configuration settings.
 */
function get_ai_configuration_logic(int $bot_id, ?string $current_provider_from_main_settings, callable $get_meta_fn): array
{
    $settings = [];

    // Ensure dependencies are loaded for defaults
    if (!class_exists(AIPKit_Providers::class)) {
        $path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
    if (!class_exists(AIPKIT_AI_Settings::class)) {
        $path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_ai_settings.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $default_provider = $current_provider_from_main_settings ?: (class_exists(AIPKit_Providers::class) ? AIPKit_Providers::get_current_provider() : 'OpenAI');
    $settings['provider'] = $get_meta_fn('_aipkit_provider', $default_provider);
    if (class_exists(AIPKit_Providers::class)) {
        $settings['provider'] = AIPKit_Providers::normalize_main_provider(
            (string) $settings['provider'],
            (string) $default_provider
        );
    }
    $settings['model'] = $get_meta_fn('_aipkit_model'); // No default model here, depends on provider sync

    $global_ai_params = class_exists(AIPKIT_AI_Settings::class) ? AIPKIT_AI_Settings::get_ai_parameters() : [];
    $default_temp = BotSettingsManager::DEFAULT_TEMPERATURE;
    $default_max_tokens = BotSettingsManager::DEFAULT_MAX_COMPLETION_TOKENS;
    $default_max_messages = BotSettingsManager::DEFAULT_MAX_MESSAGES;

    $temp_val = $get_meta_fn('_aipkit_temperature', 'not_set');
    $settings['temperature'] = ($temp_val === 'not_set')
        ? floatval($global_ai_params['temperature'] ?? $default_temp)
        : floatval($temp_val);
    $settings['temperature'] = max(0.0, min($settings['temperature'], 2.0));

    $max_tokens_val = $get_meta_fn('_aipkit_max_completion_tokens', 'not_set');
    $settings['max_completion_tokens'] = ($max_tokens_val === 'not_set')
        ? absint($global_ai_params['max_completion_tokens'] ?? $default_max_tokens)
        : absint($max_tokens_val);
    $settings['max_completion_tokens'] = max(1, min($settings['max_completion_tokens'], 128000));

    $max_msgs_val = $get_meta_fn('_aipkit_max_messages', 'not_set');
    $settings['max_messages'] = ($max_msgs_val === 'not_set')
        ? $default_max_messages
        : absint($max_msgs_val);
    $settings['max_messages'] = max(1, min($settings['max_messages'], 1024));

    $settings['web_toggle_default_on'] = in_array(
        $get_meta_fn('_aipkit_web_toggle_default_on', BotSettingsManager::DEFAULT_WEB_TOGGLE_DEFAULT_ON),
        ['0', '1'],
        true
    ) ? $get_meta_fn('_aipkit_web_toggle_default_on', BotSettingsManager::DEFAULT_WEB_TOGGLE_DEFAULT_ON)
      : BotSettingsManager::DEFAULT_WEB_TOGGLE_DEFAULT_ON;
    $settings['show_sources'] = in_array(
        $get_meta_fn('_aipkit_show_sources', BotSettingsManager::DEFAULT_SHOW_SOURCES),
        ['0', '1'],
        true
    ) ? $get_meta_fn('_aipkit_show_sources', BotSettingsManager::DEFAULT_SHOW_SOURCES)
      : BotSettingsManager::DEFAULT_SHOW_SOURCES;
    $settings['sources_label'] = sanitize_text_field(
        (string) $get_meta_fn('_aipkit_sources_label', BotSettingsManager::DEFAULT_SOURCES_LABEL)
    );
    $settings['searching_web_text'] = sanitize_text_field(
        (string) $get_meta_fn('_aipkit_searching_web_text', BotSettingsManager::DEFAULT_SEARCHING_WEB_TEXT)
    );

    return $settings;
}

// --- fn-get-appearance-settings.php ---
/**
 * Retrieves appearance-related settings.
 * UPDATED: Includes custom theme settings.
 * ADDED: Logging and a defensive fix for bubble_border_radius.
 *
 * @param int $bot_id The ID of the bot post.
 * @param string $bot_name The name of the bot (for default popup icon value).
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of appearance settings.
 */
function get_appearance_settings_logic(int $bot_id, string $bot_name, callable $get_meta_fn): array
{
    $settings = [];
    $custom_theme_defaults = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
            $custom_theme_defaults = BotSettingsManager::get_custom_theme_defaults();
        } else {
            // Define minimal defaults if class is missing to avoid undefined index errors later
            $custom_theme_defaults = [
                'primary_color' => '#0F766E',
                'secondary_color' => '#ECFEFF',
                'auto_text_contrast' => '1',
                'font_family' => 'inherit',
                'bubble_border_radius' => 18,
                'container_border_radius' => 10,
                'container_max_width' => 896,
                'popup_width' => 450,
                'container_height' => 560,
                'container_min_height' => 320,
                'container_max_height' => 70,
                'popup_height' => 560,
                'popup_min_height' => 320,
                'popup_max_height' => 70,
            ];
        }
    } else {
        $custom_theme_defaults = BotSettingsManager::get_custom_theme_defaults();
    }


    $valid_themes = ['light', 'dark', 'custom', 'chatgpt'];
    $default_theme = 'dark';
    $settings['theme'] = in_array($get_meta_fn('_aipkit_theme', $default_theme), $valid_themes)
        ? $get_meta_fn('_aipkit_theme', $default_theme)
        : $default_theme;
    $raw_theme_preset_key = sanitize_key((string) $get_meta_fn('_aipkit_theme_preset_key', ''));
    $valid_theme_preset_keys = [];
    if (class_exists(BotSettingsManager::class)) {
        $custom_theme_presets = BotSettingsManager::get_custom_theme_presets();
        foreach ($custom_theme_presets as $preset) {
            if (!is_array($preset) || !isset($preset['key'])) {
                continue;
            }
            $preset_key = sanitize_key((string) $preset['key']);
            if ($preset_key !== '') {
                $valid_theme_preset_keys[$preset_key] = true;
            }
        }
    }
    $settings['theme_preset_key'] = (
        $settings['theme'] === 'custom' &&
        $raw_theme_preset_key !== '' &&
        isset($valid_theme_preset_keys[$raw_theme_preset_key])
    )
        ? $raw_theme_preset_key
        : '';
    $settings['footer_text'] = $get_meta_fn('_aipkit_footer_text');
    $settings['input_placeholder'] = $get_meta_fn('_aipkit_input_placeholder', __('Type your message...', 'gpt3-ai-content-generator'));
    $header_avatar_url = $get_meta_fn('_aipkit_header_avatar_url', BotSettingsManager::DEFAULT_HEADER_AVATAR_URL);
    $header_avatar_type = $get_meta_fn('_aipkit_header_avatar_type', BotSettingsManager::DEFAULT_HEADER_AVATAR_TYPE);
    $header_avatar_value = $get_meta_fn('_aipkit_header_avatar_value', BotSettingsManager::DEFAULT_HEADER_AVATAR_VALUE);
    if (!in_array($header_avatar_type, ['default', 'custom'], true)) {
        $header_avatar_type = $header_avatar_url !== '' ? 'custom' : BotSettingsManager::DEFAULT_HEADER_AVATAR_TYPE;
    }
    if ($header_avatar_type === 'custom') {
        if ($header_avatar_url === '' && !empty($header_avatar_value)) {
            $header_avatar_url = $header_avatar_value;
        }
        $header_avatar_value = $header_avatar_url;
    } else {
        $allowed_header_icons = ['chat-bubble', 'spark', 'openai', 'plus', 'question-mark'];
        if (!in_array($header_avatar_value, $allowed_header_icons, true)) {
            $header_avatar_value = BotSettingsManager::DEFAULT_HEADER_AVATAR_VALUE;
        }
        $header_avatar_url = '';
    }
    $settings['header_avatar_type'] = $header_avatar_type;
    $settings['header_avatar_value'] = $header_avatar_value;
    $settings['header_avatar_url'] = ($header_avatar_type === 'custom') ? $header_avatar_url : '';
    $settings['header_online_text'] = $get_meta_fn('_aipkit_header_online_text', __('Online', 'gpt3-ai-content-generator'));
    $settings['enable_fullscreen'] = in_array($get_meta_fn('_aipkit_enable_fullscreen', BotSettingsManager::DEFAULT_ENABLE_FULLSCREEN), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_fullscreen', BotSettingsManager::DEFAULT_ENABLE_FULLSCREEN)
        : BotSettingsManager::DEFAULT_ENABLE_FULLSCREEN;
    $settings['enable_download'] = in_array($get_meta_fn('_aipkit_enable_download', BotSettingsManager::DEFAULT_ENABLE_DOWNLOAD), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_download', BotSettingsManager::DEFAULT_ENABLE_DOWNLOAD)
        : BotSettingsManager::DEFAULT_ENABLE_DOWNLOAD;
    $settings['enable_copy_button'] = '1';
    $settings['enable_feedback'] = '1';
    $settings['enable_consent_compliance'] = in_array($get_meta_fn('_aipkit_enable_consent_compliance', BotSettingsManager::DEFAULT_ENABLE_CONSENT_COMPLIANCE), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_consent_compliance', BotSettingsManager::DEFAULT_ENABLE_CONSENT_COMPLIANCE)
        : BotSettingsManager::DEFAULT_ENABLE_CONSENT_COMPLIANCE;
    $settings['consent_title'] = $get_meta_fn(
        '_aipkit_consent_title',
        __('Consent Required', 'gpt3-ai-content-generator')
    );
    $settings['consent_message'] = $get_meta_fn(
        '_aipkit_consent_message',
        __('Before starting the conversation, please agree to our Terms of Service and Privacy Policy.', 'gpt3-ai-content-generator')
    );
    $settings['consent_button'] = $get_meta_fn(
        '_aipkit_consent_button',
        __('I Agree', 'gpt3-ai-content-generator')
    );
    $settings['enable_conversation_sidebar'] = in_array($get_meta_fn('_aipkit_enable_conversation_sidebar', BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_SIDEBAR), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_conversation_sidebar', BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_SIDEBAR)
        : BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_SIDEBAR;

    // Typing indicator customization
    $settings['custom_typing_text'] = $get_meta_fn('_aipkit_custom_typing_text', BotSettingsManager::DEFAULT_CUSTOM_TYPING_TEXT);
    $settings['retrieving_context_text'] = $get_meta_fn('_aipkit_retrieving_context_text', BotSettingsManager::DEFAULT_RETRIEVING_CONTEXT_TEXT);

    // Popup settings
    $settings['popup_enabled'] = in_array($get_meta_fn('_aipkit_popup_enabled', '0'), ['0','1'])
        ? $get_meta_fn('_aipkit_popup_enabled', '0')
        : '0';
    $settings['popup_position'] = in_array($get_meta_fn('_aipkit_popup_position', 'bottom-right'), ['bottom-right','bottom-left','top-right','top-left'])
        ? $get_meta_fn('_aipkit_popup_position', 'bottom-right')
        : 'bottom-right';
    $settings['popup_delay'] = absint($get_meta_fn('_aipkit_popup_delay', BotSettingsManager::DEFAULT_POPUP_DELAY));
    $settings['site_wide_enabled'] = in_array($get_meta_fn('_aipkit_site_wide_enabled', '0'), ['0','1'])
        ? $get_meta_fn('_aipkit_site_wide_enabled', '0')
        : '0';
    $allowed_icon_sizes = ['small','medium','large','xlarge'];
    $icon_size_meta = $get_meta_fn('_aipkit_popup_icon_size', BotSettingsManager::DEFAULT_POPUP_ICON_SIZE);
    $settings['popup_icon_size'] = in_array($icon_size_meta, $allowed_icon_sizes, true) ? $icon_size_meta : BotSettingsManager::DEFAULT_POPUP_ICON_SIZE;
    $settings['popup_icon_style'] = $get_meta_fn('_aipkit_popup_icon_style', BotSettingsManager::DEFAULT_POPUP_ICON_STYLE);
    if (!in_array($settings['popup_icon_style'], ['circle', 'square', 'none'])) {
        $settings['popup_icon_style'] = BotSettingsManager::DEFAULT_POPUP_ICON_STYLE;
    }
    $settings['popup_icon_type'] = $get_meta_fn('_aipkit_popup_icon_type', BotSettingsManager::DEFAULT_POPUP_ICON_TYPE);
    $settings['popup_icon_value'] = $get_meta_fn('_aipkit_popup_icon_value', BotSettingsManager::DEFAULT_POPUP_ICON_VALUE);
    if (!in_array($settings['popup_icon_type'], ['default', 'custom'])) {
        $settings['popup_icon_type'] = BotSettingsManager::DEFAULT_POPUP_ICON_TYPE;
    }
    if ($settings['popup_icon_type'] === 'default' && !in_array($settings['popup_icon_value'], ['chat-bubble', 'spark', 'openai', 'plus', 'question-mark'])) {
        $settings['popup_icon_value'] = BotSettingsManager::DEFAULT_POPUP_ICON_VALUE;
    }
    if ($settings['popup_icon_type'] === 'custom' && empty($settings['popup_icon_value'])) {
        $settings['popup_icon_value'] = '';
    }

    $settings['popup_label_enabled'] = in_array($get_meta_fn('_aipkit_popup_label_enabled', BotSettingsManager::DEFAULT_POPUP_LABEL_ENABLED), ['0','1'])
        ? $get_meta_fn('_aipkit_popup_label_enabled', BotSettingsManager::DEFAULT_POPUP_LABEL_ENABLED)
        : BotSettingsManager::DEFAULT_POPUP_LABEL_ENABLED;
    $popup_label_text = trim((string) $get_meta_fn('_aipkit_popup_label_text', BotSettingsManager::DEFAULT_POPUP_LABEL_TEXT));
    if ($popup_label_text === '') {
        $popup_label_text = BotSettingsManager::DEFAULT_POPUP_LABEL_TEXT;
    }
    $settings['popup_label_text'] = $popup_label_text;
    $allowed_modes = ['always','on_delay','until_open','until_dismissed'];
    $raw_label_mode = $get_meta_fn('_aipkit_popup_label_mode', BotSettingsManager::DEFAULT_POPUP_LABEL_MODE);
    $legacy_mode_map = [
        'delay_once' => 'on_delay',
        'delay_always' => 'on_delay',
        'immediate_once' => 'always',
        'immediate_always' => 'always',
        'manual' => 'until_dismissed',
    ];
    $legacy_frequency_map = [
        'delay_once' => 'once_per_visitor',
        'delay_always' => 'always',
        'immediate_once' => 'once_per_visitor',
        'immediate_always' => 'always',
    ];
    $label_mode = $legacy_mode_map[$raw_label_mode] ?? $raw_label_mode;
    $settings['popup_label_mode'] = in_array($label_mode, $allowed_modes, true) ? $label_mode : BotSettingsManager::DEFAULT_POPUP_LABEL_MODE;
    $settings['popup_label_delay_seconds'] = absint($get_meta_fn('_aipkit_popup_label_delay_seconds', BotSettingsManager::DEFAULT_POPUP_LABEL_DELAY_SECONDS));
    $settings['popup_label_auto_hide_seconds'] = absint($get_meta_fn('_aipkit_popup_label_auto_hide_seconds', BotSettingsManager::DEFAULT_POPUP_LABEL_AUTO_HIDE_SECONDS));
    $settings['popup_label_dismissible'] = in_array($get_meta_fn('_aipkit_popup_label_dismissible', BotSettingsManager::DEFAULT_POPUP_LABEL_DISMISSIBLE), ['0','1'])
        ? $get_meta_fn('_aipkit_popup_label_dismissible', BotSettingsManager::DEFAULT_POPUP_LABEL_DISMISSIBLE)
        : BotSettingsManager::DEFAULT_POPUP_LABEL_DISMISSIBLE;
    $allowed_freq = ['always','once_per_session','once_per_visitor'];
    $label_freq = $get_meta_fn('_aipkit_popup_label_frequency', BotSettingsManager::DEFAULT_POPUP_LABEL_FREQUENCY);
    if (!in_array($label_freq, $allowed_freq, true) && isset($legacy_frequency_map[$raw_label_mode])) {
        $label_freq = $legacy_frequency_map[$raw_label_mode];
    }
    $settings['popup_label_frequency'] = in_array($label_freq, $allowed_freq, true) ? $label_freq : BotSettingsManager::DEFAULT_POPUP_LABEL_FREQUENCY;
    $settings['popup_label_show_on_mobile'] = in_array($get_meta_fn('_aipkit_popup_label_show_on_mobile', BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_MOBILE), ['0','1'])
        ? $get_meta_fn('_aipkit_popup_label_show_on_mobile', BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_MOBILE)
        : BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_MOBILE;
    $settings['popup_label_show_on_desktop'] = in_array($get_meta_fn('_aipkit_popup_label_show_on_desktop', BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_DESKTOP), ['0','1'])
        ? $get_meta_fn('_aipkit_popup_label_show_on_desktop', BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_DESKTOP)
        : BotSettingsManager::DEFAULT_POPUP_LABEL_SHOW_ON_DESKTOP;
    $settings['popup_label_version'] = $get_meta_fn('_aipkit_popup_label_version', BotSettingsManager::DEFAULT_POPUP_LABEL_VERSION);
    $allowed_sizes = ['small','medium','large','xlarge'];
    $label_size = $get_meta_fn('_aipkit_popup_label_size', BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE);
    $settings['popup_label_size'] = in_array($label_size, $allowed_sizes, true) ? $label_size : BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE;

    // --- Retrieve Custom Theme Settings ---
    $custom_theme_settings_retrieved = [];
    foreach (array_keys($custom_theme_defaults) as $key) {
        if (strpos($key, '_placeholder') !== false) {
            continue;
        }
        $meta_key_name = '_aipkit_cts_' . $key;
        $value_from_meta = $get_meta_fn($meta_key_name);

        if ($value_from_meta === '' || $value_from_meta === null) {
            $custom_theme_settings_retrieved[$key] = $custom_theme_defaults[$key];
        } else {
            // Specific handling for numeric dimension settings
            if (in_array($key, [
                'bubble_border_radius', 'container_border_radius', 'container_max_width', 'popup_width',
                'container_height', 'container_min_height',
                'popup_height', 'popup_min_height'
            ])) {
                $custom_theme_settings_retrieved[$key] = is_numeric($value_from_meta) ? max(0, absint($value_from_meta)) : $custom_theme_defaults[$key];
            } elseif (in_array($key, ['container_max_height', 'popup_max_height'])) {
                $custom_theme_settings_retrieved[$key] = is_numeric($value_from_meta) ? max(1, min(absint($value_from_meta), 100)) : $custom_theme_defaults[$key];
            } elseif ($key === 'auto_text_contrast') {
                $custom_theme_settings_retrieved[$key] = in_array((string)$value_from_meta, ['0', '1'], true)
                    ? (string)$value_from_meta
                    : ($custom_theme_defaults[$key] ?? '1');
            } else {
                $custom_theme_settings_retrieved[$key] = $value_from_meta;
            }
        }
    }
    $settings['custom_theme_settings'] = $custom_theme_settings_retrieved;
    // --- END Retrieve Custom Theme Settings ---

    return $settings;
}

// --- fn-get-conversation-starters.php ---
/**
 * Retrieves conversation starters settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of conversation starters settings.
 */
function get_conversation_starters_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $default_enable_starters = BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_STARTERS;
    $settings['enable_conversation_starters'] = in_array($get_meta_fn('_aipkit_enable_conversation_starters', $default_enable_starters), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_conversation_starters', $default_enable_starters)
        : $default_enable_starters;

    $default_starters_json = method_exists(BotSettingsManager::class, 'get_default_conversation_starters_json')
        ? BotSettingsManager::get_default_conversation_starters_json()
        : '[]';
    $starters_json = $get_meta_fn('_aipkit_conversation_starters', $default_starters_json);
    $starters_array = json_decode($starters_json, true);
    $settings['conversation_starters'] = is_array($starters_array) ? $starters_array : [];

    return $settings;
}

// --- fn-get-contextual-settings.php ---
/**
 * Retrieves contextual settings like content_aware, file_upload, image_upload,
 * image_triggers, and chat_image_model_id.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of contextual settings.
 */
function get_contextual_settings_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['content_aware_enabled'] = in_array($get_meta_fn('_aipkit_content_aware_enabled', BotSettingsManager::DEFAULT_CONTENT_AWARE_ENABLED), ['0','1'])
        ? $get_meta_fn('_aipkit_content_aware_enabled', BotSettingsManager::DEFAULT_CONTENT_AWARE_ENABLED)
        : BotSettingsManager::DEFAULT_CONTENT_AWARE_ENABLED;

    $settings['enable_file_upload'] = in_array($get_meta_fn('_aipkit_enable_file_upload', BotSettingsManager::DEFAULT_ENABLE_FILE_UPLOAD), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_file_upload', BotSettingsManager::DEFAULT_ENABLE_FILE_UPLOAD)
        : BotSettingsManager::DEFAULT_ENABLE_FILE_UPLOAD;

    $settings['enable_image_upload'] = in_array($get_meta_fn('_aipkit_enable_image_upload', BotSettingsManager::DEFAULT_ENABLE_IMAGE_UPLOAD), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_image_upload', BotSettingsManager::DEFAULT_ENABLE_IMAGE_UPLOAD)
        : BotSettingsManager::DEFAULT_ENABLE_IMAGE_UPLOAD;

    $settings['image_triggers'] = $get_meta_fn('_aipkit_image_triggers', BotSettingsManager::DEFAULT_IMAGE_TRIGGERS);
    if (empty($settings['image_triggers'])) {
        $settings['image_triggers'] = BotSettingsManager::DEFAULT_IMAGE_TRIGGERS;
    }

    $settings['chat_image_model_id'] = $get_meta_fn('_aipkit_chat_image_model_id', BotSettingsManager::DEFAULT_CHAT_IMAGE_MODEL_ID);
    $settings['enable_image_generation'] = in_array($get_meta_fn('_aipkit_enable_image_generation', BotSettingsManager::DEFAULT_ENABLE_IMAGE_GENERATION), ['0','1'], true)
        ? $get_meta_fn('_aipkit_enable_image_generation', BotSettingsManager::DEFAULT_ENABLE_IMAGE_GENERATION)
        : BotSettingsManager::DEFAULT_ENABLE_IMAGE_GENERATION;
    // Build valid image models dynamically
    $valid_image_models = class_exists('\\WPAICG\\AIPKit_Providers')
        ? \WPAICG\AIPKit_Providers::get_openai_image_model_ids()
        : [BotSettingsManager::DEFAULT_CHAT_IMAGE_MODEL_ID];
    if (class_exists('\\WPAICG\\AIPKit_Providers')) {
        // Add Google image models
        $google_models = \WPAICG\AIPKit_Providers::get_google_image_models();
        if (!empty($google_models)) {
            $valid_image_models = array_merge($valid_image_models, wp_list_pluck($google_models, 'id'));
        }
    }

    // Add Azure image models to validation list
    if (class_exists('\WPAICG\AIPKit_Providers')) {
        $azure_models = \WPAICG\AIPKit_Providers::get_azure_image_models();
        if (!empty($azure_models)) {
            $azure_model_ids = wp_list_pluck($azure_models, 'id');
            $valid_image_models = array_merge($valid_image_models, $azure_model_ids);
        }
    }

    // Add Replicate models to validation list when available
    if (class_exists('\WPAICG\AIPKit_Providers')) {
        $replicate_models = \WPAICG\AIPKit_Providers::get_replicate_models();
        if (!empty($replicate_models)) {
            $replicate_model_ids = wp_list_pluck($replicate_models, 'id');
            $valid_image_models = array_merge($valid_image_models, $replicate_model_ids);
        }
    }

    // Add OpenRouter image models to validation list when available
    if (class_exists('\WPAICG\AIPKit_Providers')) {
        $openrouter_image_models = \WPAICG\AIPKit_Providers::get_openrouter_image_models();
        if (!empty($openrouter_image_models)) {
            $openrouter_model_ids = wp_list_pluck($openrouter_image_models, 'id');
            $valid_image_models = array_merge($valid_image_models, $openrouter_model_ids);
        }
    }

    // Add xAI image models to validation list when available
    if (class_exists('\WPAICG\AIPKit_Providers')) {
        $xai_image_models = \WPAICG\AIPKit_Providers::get_xai_image_models();
        if (!empty($xai_image_models)) {
            $xai_model_ids = wp_list_pluck($xai_image_models, 'id');
            $valid_image_models = array_merge($valid_image_models, $xai_model_ids);
        }
    }

    if (!in_array($settings['chat_image_model_id'], $valid_image_models)) {
        $settings['chat_image_model_id'] = BotSettingsManager::DEFAULT_CHAT_IMAGE_MODEL_ID;
    }

    return $settings;
}

// --- fn-get-vector-store-config.php ---
/**
 * Retrieves vector store configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of vector store settings.
 */
function get_vector_store_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['enable_vector_store'] = in_array($get_meta_fn('_aipkit_enable_vector_store', BotSettingsManager::DEFAULT_ENABLE_VECTOR_STORE), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_vector_store', BotSettingsManager::DEFAULT_ENABLE_VECTOR_STORE)
        : BotSettingsManager::DEFAULT_ENABLE_VECTOR_STORE;

    $settings['vector_store_provider'] = $get_meta_fn('_aipkit_vector_store_provider', BotSettingsManager::DEFAULT_VECTOR_STORE_PROVIDER);
    if (!in_array($settings['vector_store_provider'], ['openai', 'pinecone', 'qdrant', 'chroma', 'claude_files'], true)) {
        $settings['vector_store_provider'] = BotSettingsManager::DEFAULT_VECTOR_STORE_PROVIDER;
    }
    $chat_provider = sanitize_text_field((string) $get_meta_fn('_aipkit_provider', 'OpenAI'));
    if (strtolower($chat_provider) !== 'claude' && $settings['vector_store_provider'] === 'claude_files') {
        $settings['vector_store_provider'] = BotSettingsManager::DEFAULT_VECTOR_STORE_PROVIDER;
    }

    $openai_vs_ids_json = $get_meta_fn('_aipkit_openai_vector_store_ids', '[]');
    $openai_vs_ids_array = json_decode($openai_vs_ids_json, true);
    if (!is_array($openai_vs_ids_array)) {
        $openai_vs_ids_array = [];
    }
    $settings['openai_vector_store_ids'] = $openai_vs_ids_array;

    // Delete old singular OpenAI store ID meta if it exists
    if (get_post_meta($bot_id, '_aipkit_openai_vector_store_id', true) !== false) {
        delete_post_meta($bot_id, '_aipkit_openai_vector_store_id');
    }

    $settings['pinecone_index_name'] = $get_meta_fn('_aipkit_pinecone_index_name', BotSettingsManager::DEFAULT_PINECONE_INDEX_NAME);
    $settings['qdrant_collection_name'] = $get_meta_fn('_aipkit_qdrant_collection_name', BotSettingsManager::DEFAULT_QDRANT_COLLECTION_NAME);
    $qdrant_names_json = $get_meta_fn('_aipkit_qdrant_collection_names', '[]');
    $qdrant_names_array = json_decode($qdrant_names_json, true);
    if (!is_array($qdrant_names_array)) { $qdrant_names_array = []; }
    if (empty($qdrant_names_array) && !empty($settings['qdrant_collection_name'])) {
        $qdrant_names_array = [$settings['qdrant_collection_name']];
    }
    $settings['qdrant_collection_names'] = $qdrant_names_array;

    $settings['chroma_collection_name'] = $get_meta_fn('_aipkit_chroma_collection_name', '');
    $chroma_names_json = $get_meta_fn('_aipkit_chroma_collection_names', '[]');
    $chroma_names_array = json_decode($chroma_names_json, true);
    if (!is_array($chroma_names_array)) { $chroma_names_array = []; }
    if (empty($chroma_names_array) && !empty($settings['chroma_collection_name'])) {
        $chroma_names_array = [$settings['chroma_collection_name']];
    }
    $settings['chroma_collection_names'] = $chroma_names_array;

    $allowed_embedding_provider_keys = AIPKit_Providers::get_embedding_provider_keys('chat_vector_store_getter');

    $settings['vector_embedding_provider'] = $get_meta_fn('_aipkit_vector_embedding_provider', BotSettingsManager::DEFAULT_VECTOR_EMBEDDING_PROVIDER);
    if (!in_array($settings['vector_embedding_provider'], $allowed_embedding_provider_keys, true)) {
        $settings['vector_embedding_provider'] = BotSettingsManager::DEFAULT_VECTOR_EMBEDDING_PROVIDER;
    }
    $settings['vector_embedding_model'] = $get_meta_fn('_aipkit_vector_embedding_model', BotSettingsManager::DEFAULT_VECTOR_EMBEDDING_MODEL);

    $top_k_val = $get_meta_fn('_aipkit_vector_store_top_k', BotSettingsManager::DEFAULT_VECTOR_STORE_TOP_K);
    $settings['vector_store_top_k'] = max(1, min(absint($top_k_val), 20));

    $threshold_val = $get_meta_fn('_aipkit_vector_store_confidence_threshold', BotSettingsManager::DEFAULT_VECTOR_STORE_CONFIDENCE_THRESHOLD);
    $settings['vector_store_confidence_threshold'] = max(0, min(absint($threshold_val), 100));
    // END NEW

    return $settings;
}

// --- fn-get-tts-config.php ---
/**
 * Retrieves Text-to-Speech (TTS) configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of TTS settings.
 */
function get_tts_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['tts_enabled'] = in_array($get_meta_fn('_aipkit_tts_enabled', BotSettingsManager::DEFAULT_TTS_ENABLED), ['0','1'])
        ? $get_meta_fn('_aipkit_tts_enabled', BotSettingsManager::DEFAULT_TTS_ENABLED)
        : BotSettingsManager::DEFAULT_TTS_ENABLED;

    $settings['tts_provider'] = $get_meta_fn('_aipkit_tts_provider', BotSettingsManager::DEFAULT_TTS_PROVIDER);
    if (!in_array($settings['tts_provider'], ['Google', 'OpenAI', 'ElevenLabs'])) {
        $settings['tts_provider'] = BotSettingsManager::DEFAULT_TTS_PROVIDER;
    }

    $settings['tts_google_voice_id'] = $get_meta_fn('_aipkit_tts_google_voice_id', '');
    $settings['tts_openai_voice_id'] = $get_meta_fn('_aipkit_tts_openai_voice_id', 'alloy');
    $settings['tts_openai_model_id'] = $get_meta_fn('_aipkit_tts_openai_model_id', BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID);
    $settings['tts_elevenlabs_voice_id'] = $get_meta_fn('_aipkit_tts_elevenlabs_voice_id', '');
    $settings['tts_elevenlabs_model_id'] = $get_meta_fn('_aipkit_tts_elevenlabs_model_id', BotSettingsManager::DEFAULT_TTS_ELEVENLABS_MODEL_ID);

    $settings['tts_voice_id'] = ''; // Determine combined voice ID based on provider
    switch ($settings['tts_provider']) {
        case 'Google': $settings['tts_voice_id'] = $settings['tts_google_voice_id'];
            break;
        case 'OpenAI': $settings['tts_voice_id'] = $settings['tts_openai_voice_id'];
            break;
        case 'ElevenLabs': $settings['tts_voice_id'] = $settings['tts_elevenlabs_voice_id'];
            break;
    }

    $settings['tts_auto_play'] = in_array($get_meta_fn('_aipkit_tts_auto_play', BotSettingsManager::DEFAULT_TTS_AUTO_PLAY), ['0','1'])
        ? $get_meta_fn('_aipkit_tts_auto_play', BotSettingsManager::DEFAULT_TTS_AUTO_PLAY)
        : BotSettingsManager::DEFAULT_TTS_AUTO_PLAY;

    return $settings;
}

// --- fn-get-stt-config.php ---
/**
 * Retrieves Speech-to-Text (STT) configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of STT settings.
 */
function get_stt_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['enable_voice_input'] = in_array($get_meta_fn('_aipkit_enable_voice_input', BotSettingsManager::DEFAULT_ENABLE_VOICE_INPUT), ['0','1'])
        ? $get_meta_fn('_aipkit_enable_voice_input', BotSettingsManager::DEFAULT_ENABLE_VOICE_INPUT)
        : BotSettingsManager::DEFAULT_ENABLE_VOICE_INPUT;

    $settings['stt_provider'] = $get_meta_fn('_aipkit_stt_provider', BotSettingsManager::DEFAULT_STT_PROVIDER);
    if (!in_array($settings['stt_provider'], ['OpenAI', 'Azure'])) { // Add other valid providers as needed
        $settings['stt_provider'] = BotSettingsManager::DEFAULT_STT_PROVIDER;
    }

    $settings['stt_openai_model_id'] = $get_meta_fn('_aipkit_stt_openai_model_id', BotSettingsManager::DEFAULT_STT_OPENAI_MODEL_ID);
    $settings['stt_azure_model_id'] = $get_meta_fn('_aipkit_stt_azure_model_id', BotSettingsManager::DEFAULT_STT_AZURE_MODEL_ID);

    return $settings;
}

// --- fn-get-token-management-config.php ---
/**
 * Retrieves token management configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of token management settings.
 */
function get_token_management_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['token_limit_mode'] = $get_meta_fn('_aipkit_token_limit_mode', BotSettingsManager::DEFAULT_TOKEN_LIMIT_MODE);
    if (!in_array($settings['token_limit_mode'], ['general', 'role_based'])) {
        $settings['token_limit_mode'] = BotSettingsManager::DEFAULT_TOKEN_LIMIT_MODE;
    }

    $guest_limit_raw = $get_meta_fn('_aipkit_token_guest_limit', BotSettingsManager::DEFAULT_TOKEN_GUEST_LIMIT);
    $settings['token_guest_limit'] = ($guest_limit_raw === '') ? null : (($guest_limit_raw === '0') ? 0 : absint($guest_limit_raw));

    $user_limit_raw = $get_meta_fn('_aipkit_token_user_limit', BotSettingsManager::DEFAULT_TOKEN_USER_LIMIT);
    $settings['token_user_limit'] = ($user_limit_raw === '') ? null : (($user_limit_raw === '0') ? 0 : absint($user_limit_raw));

    $role_limits_json = $get_meta_fn('_aipkit_token_role_limits', '[]');
    $decoded_roles = json_decode($role_limits_json, true);
    $settings['token_role_limits'] = is_array($decoded_roles) ? $decoded_roles : [];

    $settings['token_reset_period'] = $get_meta_fn('_aipkit_token_reset_period', BotSettingsManager::DEFAULT_TOKEN_RESET_PERIOD);
    if (!in_array($settings['token_reset_period'], ['never', 'daily', 'weekly', 'monthly'])) {
        $settings['token_reset_period'] = BotSettingsManager::DEFAULT_TOKEN_RESET_PERIOD;
    }

    $default_limit_message = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_default_token_limit_message()
        : __('You have reached your quota for this period.', 'gpt3-ai-content-generator');
    $settings['token_limit_message'] = $get_meta_fn('_aipkit_token_limit_message', $default_limit_message);
    if (empty($settings['token_limit_message'])) {
        $settings['token_limit_message'] = $default_limit_message;
    }

    $valid_action_types = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_token_limit_action_types()
        : ['none', 'dashboard_usage', 'dashboard_credits', 'dashboard_purchases', 'buy_credits', 'custom_url'];
    $default_action_settings = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_default_token_limit_action_settings()
        : [
            'primary_type' => 'none',
            'primary_label' => '',
            'primary_url' => '',
            'secondary_type' => 'none',
            'secondary_label' => '',
            'secondary_url' => '',
        ];

    $settings['token_limit_primary_action_type'] = (string) $get_meta_fn(
        '_aipkit_token_limit_primary_action_type',
        $default_action_settings['primary_type']
    );
    if (!in_array($settings['token_limit_primary_action_type'], $valid_action_types, true)) {
        $settings['token_limit_primary_action_type'] = $default_action_settings['primary_type'];
    }
    $settings['token_limit_primary_action_label'] = trim((string) $get_meta_fn(
        '_aipkit_token_limit_primary_action_label',
        $default_action_settings['primary_label']
    ));
    if ($settings['token_limit_primary_action_type'] === 'none') {
        $settings['token_limit_primary_action_label'] = '';
    } elseif ($settings['token_limit_primary_action_label'] === '') {
        $settings['token_limit_primary_action_label'] = class_exists(BotSettingsManager::class)
            ? BotSettingsManager::get_token_limit_action_default_label($settings['token_limit_primary_action_type'])
            : $default_action_settings['primary_label'];
    }
    $settings['token_limit_primary_action_url'] = esc_url_raw((string) $get_meta_fn(
        '_aipkit_token_limit_primary_action_url',
        $default_action_settings['primary_url']
    ));

    $settings['token_limit_secondary_action_type'] = (string) $get_meta_fn(
        '_aipkit_token_limit_secondary_action_type',
        $default_action_settings['secondary_type']
    );
    if (!in_array($settings['token_limit_secondary_action_type'], $valid_action_types, true)) {
        $settings['token_limit_secondary_action_type'] = $default_action_settings['secondary_type'];
    }
    $settings['token_limit_secondary_action_label'] = trim((string) $get_meta_fn(
        '_aipkit_token_limit_secondary_action_label',
        $default_action_settings['secondary_label']
    ));
    if ($settings['token_limit_secondary_action_type'] === 'none') {
        $settings['token_limit_secondary_action_label'] = '';
    } elseif ($settings['token_limit_secondary_action_label'] === '') {
        $settings['token_limit_secondary_action_label'] = class_exists(BotSettingsManager::class)
            ? BotSettingsManager::get_token_limit_action_default_label($settings['token_limit_secondary_action_type'])
            : $default_action_settings['secondary_label'];
    }
    $settings['token_limit_secondary_action_url'] = esc_url_raw((string) $get_meta_fn(
        '_aipkit_token_limit_secondary_action_url',
        $default_action_settings['secondary_url']
    ));

    return $settings;
}

// --- fn-get-openai-specific-config.php ---
/**
 * Retrieves OpenAI-specific configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of OpenAI-specific settings.
 */
function get_openai_specific_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['openai_conversation_state_enabled'] = in_array(
        $get_meta_fn('_aipkit_openai_conversation_state_enabled', BotSettingsManager::DEFAULT_OPENAI_CONVERSATION_STATE_ENABLED),
        ['0', '1']
    ) ? $get_meta_fn('_aipkit_openai_conversation_state_enabled', BotSettingsManager::DEFAULT_OPENAI_CONVERSATION_STATE_ENABLED)
      : BotSettingsManager::DEFAULT_OPENAI_CONVERSATION_STATE_ENABLED;

    // OpenAI Web Search Settings
    $settings['openai_web_search_enabled'] = in_array(
        $get_meta_fn('_aipkit_openai_web_search_enabled', BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_ENABLED),
        ['0', '1']
    ) ? $get_meta_fn('_aipkit_openai_web_search_enabled', BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_ENABLED)
      : BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_ENABLED;

    $settings['openai_web_search_context_size'] = $get_meta_fn('_aipkit_openai_web_search_context_size', BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE);
    if (!in_array($settings['openai_web_search_context_size'], ['low', 'medium', 'high'])) {
        $settings['openai_web_search_context_size'] = BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE;
    }

    $settings['openai_web_search_loc_type'] = $get_meta_fn('_aipkit_openai_web_search_loc_type', BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_LOC_TYPE);
    if (!in_array($settings['openai_web_search_loc_type'], ['none', 'approximate'])) {
        $settings['openai_web_search_loc_type'] = BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_LOC_TYPE;
    }

    $settings['openai_web_search_loc_country'] = $get_meta_fn('_aipkit_openai_web_search_loc_country', '');
    $settings['openai_web_search_loc_city'] = $get_meta_fn('_aipkit_openai_web_search_loc_city', '');
    $settings['openai_web_search_loc_region'] = $get_meta_fn('_aipkit_openai_web_search_loc_region', '');
    $settings['openai_web_search_loc_timezone'] = $get_meta_fn('_aipkit_openai_web_search_loc_timezone', '');
    
    // Reasoning Effort Setting
    $default_reasoning_effort = defined('WPAICG\Chat\Storage\BotSettingsManager::DEFAULT_REASONING_EFFORT') ? BotSettingsManager::DEFAULT_REASONING_EFFORT : 'none';
    $settings['reasoning_effort'] = $get_meta_fn('_aipkit_reasoning_effort', $default_reasoning_effort);
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($settings['reasoning_effort']);
    $settings['reasoning_effort'] = $reasoning_effort !== '' ? $reasoning_effort : $default_reasoning_effort;


    return $settings;
}

// --- fn-get-claude-specific-config.php ---
/**
 * Retrieves Claude-specific configuration settings (Web Search).
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of Claude-specific settings.
 */
function get_claude_specific_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['claude_web_search_enabled'] = in_array(
        $get_meta_fn('_aipkit_claude_web_search_enabled', BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_ENABLED),
        ['0', '1'],
        true
    ) ? $get_meta_fn('_aipkit_claude_web_search_enabled', BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_ENABLED)
      : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_ENABLED;

    $raw_max_uses = $get_meta_fn('_aipkit_claude_web_search_max_uses', (string) BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES);
    $max_uses = is_numeric($raw_max_uses) ? absint($raw_max_uses) : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES;
    $settings['claude_web_search_max_uses'] = max(1, min($max_uses, 20));

    $settings['claude_web_search_loc_type'] = $get_meta_fn('_aipkit_claude_web_search_loc_type', BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_LOC_TYPE);
    if (!in_array($settings['claude_web_search_loc_type'], ['none', 'approximate'], true)) {
        $settings['claude_web_search_loc_type'] = BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_LOC_TYPE;
    }

    $settings['claude_web_search_loc_country'] = $get_meta_fn('_aipkit_claude_web_search_loc_country', '');
    $settings['claude_web_search_loc_city'] = $get_meta_fn('_aipkit_claude_web_search_loc_city', '');
    $settings['claude_web_search_loc_region'] = $get_meta_fn('_aipkit_claude_web_search_loc_region', '');
    $settings['claude_web_search_loc_timezone'] = $get_meta_fn('_aipkit_claude_web_search_loc_timezone', '');

    $settings['claude_web_search_allowed_domains'] = $get_meta_fn('_aipkit_claude_web_search_allowed_domains', '');
    $settings['claude_web_search_blocked_domains'] = $get_meta_fn('_aipkit_claude_web_search_blocked_domains', '');
    if (!empty($settings['claude_web_search_allowed_domains']) && !empty($settings['claude_web_search_blocked_domains'])) {
        $settings['claude_web_search_blocked_domains'] = '';
    }

    $settings['claude_web_search_cache_ttl'] = $get_meta_fn('_aipkit_claude_web_search_cache_ttl', BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL);
    if (!in_array($settings['claude_web_search_cache_ttl'], ['none', '5m', '1h'], true)) {
        $settings['claude_web_search_cache_ttl'] = BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL;
    }

    return $settings;
}

// --- fn-get-openrouter-specific-config.php ---
/**
 * Retrieves OpenRouter-specific configuration settings (Web Search).
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of OpenRouter-specific settings.
 */
function get_openrouter_specific_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['openrouter_web_search_enabled'] = in_array(
        $get_meta_fn('_aipkit_openrouter_web_search_enabled', BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED),
        ['0', '1'],
        true
    ) ? $get_meta_fn('_aipkit_openrouter_web_search_enabled', BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED)
      : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED;

    $engine = $get_meta_fn('_aipkit_openrouter_web_search_engine', BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE);
    $settings['openrouter_web_search_engine'] = in_array($engine, ['auto', 'native', 'exa'], true)
        ? $engine
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE;

    $raw_max_results = $get_meta_fn(
        '_aipkit_openrouter_web_search_max_results',
        (string) BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS
    );
    $max_results = is_numeric($raw_max_results) ? absint($raw_max_results) : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS;
    $settings['openrouter_web_search_max_results'] = max(1, min($max_results, 10));

    $settings['openrouter_web_search_search_prompt'] = $get_meta_fn(
        '_aipkit_openrouter_web_search_search_prompt',
        BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_SEARCH_PROMPT
    );

    return $settings;
}

// --- fn-get-xai-specific-config.php ---
/**
 * Retrieves xAI-specific chatbot settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of xAI-specific settings.
 */
function get_xai_specific_config_logic(int $bot_id, callable $get_meta_fn): array
{
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $enabled = $get_meta_fn('_aipkit_xai_web_search_enabled', BotSettingsManager::DEFAULT_XAI_WEB_SEARCH_ENABLED);

    return [
        'xai_web_search_enabled' => in_array($enabled, ['0', '1'], true)
            ? $enabled
            : BotSettingsManager::DEFAULT_XAI_WEB_SEARCH_ENABLED,
    ];
}

// --- fn-get-google-specific-config.php ---
/**
 * Retrieves Google-specific configuration settings (Search Grounding).
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of Google-specific settings.
 */
function get_google_specific_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }

    $settings['google_search_grounding_enabled'] = in_array(
        $get_meta_fn('_aipkit_google_search_grounding_enabled', BotSettingsManager::DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED),
        ['0', '1']
    ) ? $get_meta_fn('_aipkit_google_search_grounding_enabled', BotSettingsManager::DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED)
      : BotSettingsManager::DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED;

    $settings['google_grounding_mode'] = $get_meta_fn('_aipkit_google_grounding_mode', BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE);
    if (!in_array($settings['google_grounding_mode'], ['DEFAULT_MODE', 'MODE_DYNAMIC'])) {
        $settings['google_grounding_mode'] = BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
    }

    $raw_threshold = $get_meta_fn('_aipkit_google_grounding_dynamic_threshold');
    if ($raw_threshold === '' || !is_numeric($raw_threshold)) {
        $settings['google_grounding_dynamic_threshold'] = BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;
    } else {
        $settings['google_grounding_dynamic_threshold'] = floatval($raw_threshold);
    }
    $settings['google_grounding_dynamic_threshold'] = max(0.0, min($settings['google_grounding_dynamic_threshold'], 1.0));

    return $settings;
}

// --- fn-get-trigger-config.php ---
/**
 * Retrieves trigger configuration for a bot.
 * Returns the raw JSON string from post meta, or an empty array string '[]' if not set or invalid.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta (used to get the trigger meta key).
 * @return array Associative array containing 'triggers_json'.
 */
function get_trigger_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    $trigger_meta_key = '_aipkit_chatbot_triggers'; // Fallback key
    $trigger_storage_class_name = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage'; // New Pro location/namespace

    if (class_exists($trigger_storage_class_name)) {
        $trigger_meta_key = $trigger_storage_class_name::META_KEY;
    }

    // Use the passed $get_meta_fn to retrieve the value for the determined meta key
    // Default to an empty JSON array string if the meta key is not found or is empty.
    $triggers_json_string = $get_meta_fn($trigger_meta_key, '[]');

    // Ensure $triggers_json_string is a string before trying to decode
    if (!is_string($triggers_json_string) || trim($triggers_json_string) === '') {
        $triggers_json_string = '[]';
    }

    $decoded = json_decode($triggers_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        // Attempt a targeted repair for unescaped quotes in body_template (common cause of invalid JSON).
        $repaired = preg_replace_callback(
            '/("body_template"\s*:\s*")(.+?)(")\s*,\s*"timeout_seconds"/s',
            static function (array $matches): string {
                $value = $matches[2];
                $value = str_replace('\\', '\\\\', $value);
                $value = str_replace('"', '\\"', $value);
                return $matches[1] . $value . $matches[3] . ', "timeout_seconds"';
            },
            $triggers_json_string,
            -1,
            $repair_count
        );
        if (!empty($repair_count)) {
            $decoded = json_decode($repaired, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $triggers_json_string = $repaired;
            }
        }
    }

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $settings['triggers_json'] = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $settings['triggers_json'] = '[]';
    }
    return $settings;
}

// --- fn-get-voice-agent-config.php ---
/**
 * Retrieves Realtime Voice Agent configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of voice agent settings.
 */
function get_voice_agent_config_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    // Ensure BotSettingsManager is loaded for constants, or provide fallbacks.
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = dirname(__DIR__) . '/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }
    
    $default_enable_realtime = BotSettingsManager::DEFAULT_ENABLE_REALTIME_VOICE ?? '0';
    $default_direct_voice_mode = BotSettingsManager::DEFAULT_DIRECT_VOICE_MODE ?? '0';
    $default_realtime_model = BotSettingsManager::DEFAULT_REALTIME_MODEL ?? 'gpt-4o-realtime-preview';
    $default_realtime_voice = BotSettingsManager::DEFAULT_REALTIME_VOICE ?? 'alloy';
    $default_turn_detection = BotSettingsManager::DEFAULT_TURN_DETECTION ?? 'server_vad';
    $default_speed = BotSettingsManager::DEFAULT_SPEED ?? 1.0;
    $default_input_audio_format = BotSettingsManager::DEFAULT_INPUT_AUDIO_FORMAT ?? 'pcm16';
    $default_output_audio_format = BotSettingsManager::DEFAULT_OUTPUT_AUDIO_FORMAT ?? 'pcm16';
    $default_noise_reduction = BotSettingsManager::DEFAULT_INPUT_AUDIO_NOISE_REDUCTION ?? '1';

    $settings['enable_realtime_voice'] = $get_meta_fn('_aipkit_enable_realtime_voice', $default_enable_realtime);
    $settings['direct_voice_mode'] = $get_meta_fn('_aipkit_direct_voice_mode', $default_direct_voice_mode);
    $settings['realtime_model'] = $get_meta_fn('_aipkit_realtime_model', $default_realtime_model);
    $settings['realtime_voice'] = $get_meta_fn('_aipkit_realtime_voice', $default_realtime_voice);
    $settings['turn_detection'] = $get_meta_fn('_aipkit_turn_detection', $default_turn_detection);
    $settings['speed'] = floatval($get_meta_fn('_aipkit_speed', $default_speed));
    $settings['input_audio_format'] = $get_meta_fn('_aipkit_input_audio_format', $default_input_audio_format);
    $settings['output_audio_format'] = $get_meta_fn('_aipkit_output_audio_format', $default_output_audio_format);
    $settings['input_audio_noise_reduction'] = $get_meta_fn('_aipkit_input_audio_noise_reduction', $default_noise_reduction);
    
    // Validate values to be safe
    $valid_audio_formats = ['pcm16', 'g711_ulaw', 'g711_alaw'];
    if (!in_array($settings['input_audio_format'], $valid_audio_formats, true)) {
        $settings['input_audio_format'] = $default_input_audio_format;
    }
    if (!in_array($settings['output_audio_format'], $valid_audio_formats, true)) {
        $settings['output_audio_format'] = $default_output_audio_format;
    }
    if (!in_array($settings['realtime_model'], ['gpt-4o-realtime-preview', 'gpt-4o-mini-realtime'])) {
        $settings['realtime_model'] = $default_realtime_model;
    }
    if (!in_array($settings['realtime_voice'], ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'onyx', 'nova', 'shimmer', 'verse'])) {
        $settings['realtime_voice'] = $default_realtime_voice;
    }
    if (!in_array($settings['turn_detection'], ['none', 'server_vad', 'semantic_vad'])) {
        $settings['turn_detection'] = $default_turn_detection;
    }
    $settings['speed'] = max(0.25, min(1.5, $settings['speed']));

    return $settings;
}

// --- fn-get-embed-settings.php ---
/**
 * Retrieves Embed configuration settings.
 *
 * @param int $bot_id The ID of the bot post.
 * @param callable $get_meta_fn A function to retrieve post meta.
 * @return array Associative array of embed settings.
 */
function get_embed_settings_logic(int $bot_id, callable $get_meta_fn): array
{
    $settings = [];

    $deploy_mode = sanitize_key((string) $get_meta_fn('_aipkit_deploy_mode', ''));
    $settings['deploy_mode'] = in_array($deploy_mode, ['inline', 'popup', 'external'], true)
        ? $deploy_mode
        : '';

    // Get the allowed domains, default to an empty string if not set.
    $settings['embed_allowed_domains'] = $get_meta_fn('_aipkit_embed_allowed_domains', '');

    return $settings;
}
