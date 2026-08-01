<?php

namespace WPAICG\Chat\Frontend\Shortcode\ConfiguratorMethods;

use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\AIPKit_Providers;
use WPAICG\Lib\Addons\AIPKit_Consent_Compliance; // For consent required check
use WPAICG\aipkit_dashboard; // For addon/plan status checks

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- get-conversation-starters.php ---
/**
 * Prepares the conversation starters array.
 *
 * @param array $settings Bot settings.
 * @param bool $starters_ui_enabled Flag indicating if starters UI is enabled.
 * @return array The array of conversation starter strings.
 */
function get_conversation_starters_logic(array $settings, bool $starters_ui_enabled): array {
    $starters_array = [];
    if ($starters_ui_enabled) {
        $starters_raw = $settings['conversation_starters'] ?? [];
        if (!empty($starters_raw) && is_array($starters_raw)) { // Check if it's already an array
            $starters_array = $starters_raw;
        } elseif (!empty($starters_raw) && is_string($starters_raw)) { // Handle JSON string if somehow passed
            $decoded_starters = json_decode($starters_raw, true);
            if (is_array($decoded_starters)) {
                $starters_array = $decoded_starters;
            }
        }

        if (empty($starters_array)) {
            // Fallback to default starters if the setting is empty or invalid
            $starters_array = method_exists(BotSettingsManager::class, 'get_default_conversation_starters')
                ? BotSettingsManager::get_default_conversation_starters()
                : [
                    __('What can you do?', 'gpt3-ai-content-generator'),
                    __('Tell me a fun fact', 'gpt3-ai-content-generator'),
                ];
        }
    }
    return $starters_array;
}

// --- get-consent-settings.php ---
/**
 * Prepares the consent-related text fields.
 *
 * @param array $settings Bot settings.
 * @return array An array containing consent_title, consent_message, and consent_button texts.
 */
function get_consent_settings_logic(array $settings): array {
    $default_title = __('Consent Required', 'gpt3-ai-content-generator');
    $default_message = __('Before starting the conversation, please agree to our Terms of Service and Privacy Policy.', 'gpt3-ai-content-generator');
    $default_button = __('I Agree', 'gpt3-ai-content-generator');

    $consent_title = $settings['consent_title'] ?? '';
    $consent_message = $settings['consent_message'] ?? '';
    $consent_button = $settings['consent_button'] ?? '';

    return [
        'consent_title' => $consent_title !== '' ? $consent_title : $default_title,
        'consent_message' => $consent_message !== '' ? $consent_message : $default_message,
        'consent_button' => $consent_button !== '' ? $consent_button : $default_button,
    ];
}

// --- get-tts-settings.php ---
/**
 * Prepares TTS (Text-to-Speech) related settings.
 *
 * @param array $settings Bot settings.
 * @return array An array containing tts_provider and tts_voice_id.
 */
function get_tts_settings_logic(array $settings): array {
    if (!class_exists(BotSettingsManager::class)) {
        return [
            'tts_provider' => 'Google',
            'tts_voice_id' => '',
            'tts_auto_play' => false,
            'tts_openai_model_id' => 'tts-1', // Default if BotSettingsManager constants not available
            'tts_elevenlabs_model_id' => '',  // Default if BotSettingsManager constants not available
        ];
    }
    $tts_provider = $settings['tts_provider'] ?? BotSettingsManager::DEFAULT_TTS_PROVIDER;
    $tts_voice_id = $settings['tts_voice_id'] ?? ''; // This should be the combined one after bot settings are fetched
    $tts_auto_play = ($settings['tts_auto_play'] ?? BotSettingsManager::DEFAULT_TTS_AUTO_PLAY) === '1';
    $tts_openai_model_id = $settings['tts_openai_model_id'] ?? BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID;
    $tts_elevenlabs_model_id = $settings['tts_elevenlabs_model_id'] ?? BotSettingsManager::DEFAULT_TTS_ELEVENLABS_MODEL_ID;

    return [
        'tts_provider' => $tts_provider,
        'tts_voice_id' => $tts_voice_id,
        'tts_auto_play' => $tts_auto_play,
        'tts_openai_model_id' => $tts_openai_model_id,
        'tts_elevenlabs_model_id' => $tts_elevenlabs_model_id,
    ];
}

// --- get-grounding-flags.php ---
/**
 * Prepares Google Search Grounding related flags and settings.
 *
 * @param array $settings Bot settings.
 * @param array $feature_flags Determined feature flags.
 * @return array An array containing allowGoogleSearchGrounding, googleGroundingMode, and googleGroundingDynamicThreshold.
 */
function get_google_grounding_settings_logic(array $settings, array $feature_flags): array {
    if (!class_exists(BotSettingsManager::class)) {
        return [
            'allowGoogleSearchGrounding' => false,
            'googleGroundingMode' => 'DEFAULT_MODE',
            'googleGroundingDynamicThreshold' => 0.3,
        ];
    }

    $allow_google_search_grounding = $feature_flags['allowGoogleSearchGrounding'] ?? false;
    $google_grounding_mode = BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
    $google_grounding_dynamic_threshold = BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;

    if ($allow_google_search_grounding) {
        $google_grounding_mode = $settings['google_grounding_mode'] ?? BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
        if ($google_grounding_mode === 'MODE_DYNAMIC') {
            $google_grounding_dynamic_threshold = isset($settings['google_grounding_dynamic_threshold'])
                                                 ? floatval($settings['google_grounding_dynamic_threshold'])
                                                 : BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;
        }
    }

    return [
        'allowGoogleSearchGrounding' => $allow_google_search_grounding,
        'googleGroundingMode' => $google_grounding_mode,
        'googleGroundingDynamicThreshold' => $google_grounding_dynamic_threshold,
    ];
}

// --- get-text-labels.php ---
/**
 * Prepares the `text` array for localization in JavaScript.
 *
 * @param array $settings Bot settings.
 * @param array $consent_texts Prepared consent texts.
 * @return array The array of text labels.
 */
function get_text_labels_logic(array $settings, array $consent_texts): array {
    $custom_sources_label = isset($settings['sources_label'])
        ? sanitize_text_field((string) $settings['sources_label'])
        : '';
    $custom_searching_web_text = isset($settings['searching_web_text'])
        ? sanitize_text_field((string) $settings['searching_web_text'])
        : '';
    $custom_retrieving_context_text = isset($settings['retrieving_context_text'])
        ? sanitize_text_field((string) $settings['retrieving_context_text'])
        : '';

    return [
        'sendMessage' => __('Send Message', 'gpt3-ai-content-generator'),
        'sending' => __('Sending...', 'gpt3-ai-content-generator'),
        'stopResponse' => __('Stop Response', 'gpt3-ai-content-generator'),
        'typeMessage' => $settings['input_placeholder'] ?? __('Type your message...', 'gpt3-ai-content-generator'),
        'thinking' => __('Thinking', 'gpt3-ai-content-generator'),
        'streaming' => __('Streaming...', 'gpt3-ai-content-generator'),
        'statusThinking' => __('Thinking...', 'gpt3-ai-content-generator'),
        'statusProcessing' => __('Processing...', 'gpt3-ai-content-generator'),
        'statusSearchingWeb' => $custom_searching_web_text !== '' ? $custom_searching_web_text : __('Searching web...', 'gpt3-ai-content-generator'),
        'statusRetrievingContext' => $custom_retrieving_context_text,
        'statusCallingTool' => __('Calling tool...', 'gpt3-ai-content-generator'),
        'errorPrefix' => __('Error:', 'gpt3-ai-content-generator'),
        'userPrefix' => __('User', 'gpt3-ai-content-generator'),
        'sources' => $custom_sources_label !== '' ? $custom_sources_label : __('Sources', 'gpt3-ai-content-generator'),
        'source' => $custom_sources_label !== '' ? $custom_sources_label : __('Source', 'gpt3-ai-content-generator'),
        'clearChat' => __('Clear Chat', 'gpt3-ai-content-generator'),
        'fullscreen' => __('Fullscreen', 'gpt3-ai-content-generator'),
        'exitFullscreen' => __('Exit Fullscreen', 'gpt3-ai-content-generator'),
        'download' => __('Download Transcript', 'gpt3-ai-content-generator'),
        'downloadTxt' => __('Download TXT', 'gpt3-ai-content-generator'),
        'downloadPdf' => __('Download PDF', 'gpt3-ai-content-generator'),
        'downloadEmpty' => __('Nothing to download.', 'gpt3-ai-content-generator'),
        'pdfError' => __('Could not open the print window. Please allow popups and try again.', 'gpt3-ai-content-generator'),
        'streamError' => __('Stream error. Please try again.', 'gpt3-ai-content-generator'),
        'connError' => __('Connection error. Please try again.', 'gpt3-ai-content-generator'),
        'initialGreeting' => $settings['greeting'] ?? __('Hello there!', 'gpt3-ai-content-generator'),
        'initialSubgreeting' => $settings['subgreeting'] ?? __('How can I help you today?', 'gpt3-ai-content-generator'),
        'sidebarToggle' => __('Toggle Conversation Sidebar', 'gpt3-ai-content-generator'),
        'newChat' => __('New Chat', 'gpt3-ai-content-generator'),
        'conversations' => __('Conversations', 'gpt3-ai-content-generator'),
        'historyGuests' => __('History unavailable for guests.', 'gpt3-ai-content-generator'),
        'historyEmpty' => __('No past conversations.', 'gpt3-ai-content-generator'),
        'feedbackLikeLabel' => __('Like response', 'gpt3-ai-content-generator'),
        'feedbackDislikeLabel' => __('Dislike response', 'gpt3-ai-content-generator'),
        'feedbackSubmitted' => __('Feedback submitted', 'gpt3-ai-content-generator'),
        'copyActionLabel' => __('Copy response', 'gpt3-ai-content-generator'),
        'copyCodeLabel' => __('Copy code', 'gpt3-ai-content-generator'),
        'consentTitle' => $consent_texts['consent_title'],
        'consentMessage' => $consent_texts['consent_message'],
        'consentButton' => $consent_texts['consent_button'],
        'playActionLabel' => __('Play audio', 'gpt3-ai-content-generator'),
        'imageCommandEmptyPrompt' => __('Please provide a description after the image command (e.g., /image a cat playing with a ball).', 'gpt3-ai-content-generator'),
        'pauseActionLabel' => __('Pause audio', 'gpt3-ai-content-generator'),
        'webSearchToggle' => __('Toggle Web Search', 'gpt3-ai-content-generator'),
        'webSearchActive' => __('Web Search Active', 'gpt3-ai-content-generator'),
        'webSearchInactive' => __('Web Search Inactive', 'gpt3-ai-content-generator'),
        'googleSearchGroundingToggle' => __('Toggle Google Search Grounding', 'gpt3-ai-content-generator'),
        'googleSearchGroundingActive' => __('Google Search Grounding Active', 'gpt3-ai-content-generator'),
        'googleSearchGroundingInactive' => __('Google Search Grounding Inactive', 'gpt3-ai-content-generator'),
        // Popup hint related
        'dismissHint' => __('Dismiss', 'gpt3-ai-content-generator'),
    ];
}

// --- build-config-array.php ---
/**
 * Main orchestrator function to build the frontend configuration array.
 * This replaces the body of the original Configurator::prepare_config().
 * UPDATED: Include custom theme settings.
 * ADDED: Logging and a defensive fix for bubble_border_radius in custom theme settings.
 * ADDED: fileUploadEnabledUI flag.
 * MODIFIED: Added vectorStoreProvider to the returned config.
 *
 * @param int $bot_id
 * @param \WP_Post $bot_post
 * @param array $settings Bot settings.
 * @param array $feature_flags Determined feature flags.
 * @return array Frontend configuration data.
 */
function build_config_array_logic(int $bot_id, \WP_Post $bot_post, array $settings, array $feature_flags): array
{
    // Ensure dependencies are loaded
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        }
    }
    if (!class_exists(AIPKit_Providers::class)) {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($providers_path)) {
            require_once $providers_path;
        }
    }
    if (!class_exists(AIPKit_Consent_Compliance::class)) {
        $consent_path = WPAICG_LIB_DIR . 'addons/class-aipkit-consent-compliance.php';
        if (file_exists($consent_path)) {
            require_once $consent_path;
        }
    }
    if (!class_exists(aipkit_dashboard::class)) {
        $dashboard_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_dashboard.php';
        if (file_exists($dashboard_path)) {
            require_once $dashboard_path;
        }
    }

    $starters_array = get_conversation_starters_logic($settings, $feature_flags['starters_ui_enabled']);
    $consent_texts = get_consent_settings_logic($settings);
    $tts_settings = get_tts_settings_logic($settings);
    $google_grounding_settings = get_google_grounding_settings_logic($settings, $feature_flags);

    $nonce = wp_create_nonce('aipkit_frontend_chat_nonce');

    $consent_required = false;
    if (class_exists(AIPKit_Consent_Compliance::class)) {
        $consent_required = AIPKit_Consent_Compliance::is_required();
    }
    $consent_toggle_enabled = ($settings['enable_consent_compliance'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_ENABLE_CONSENT_COMPLIANCE : '0')) === '1';

    $current_post_id = 0;
    if (is_singular()) {
        $current_post_id = get_the_ID();
    }

    $image_triggers = $settings['image_triggers'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_IMAGE_TRIGGERS : '/image');
    if (empty($image_triggers)) {
        $image_triggers = (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_IMAGE_TRIGGERS : '/image');
    }

    $enable_openai_conv_state = ($settings['openai_conversation_state_enabled'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_OPENAI_CONVERSATION_STATE_ENABLED : '0')) === '1';
    $allow_openai_web_search_tool = $feature_flags['allowWebSearchTool'] ?? false;

    $text_labels = get_text_labels_logic($settings, $consent_texts);

    // --- Add custom theme settings to frontend config ---
    $custom_theme_settings_for_js = [];
    $custom_theme_preset_key = isset($settings['theme_preset_key'])
        ? sanitize_key((string) $settings['theme_preset_key'])
        : '';
    if (($settings['theme'] ?? 'light') === 'custom') {
        $raw_custom_theme_settings = isset($settings['custom_theme_settings']) && is_array($settings['custom_theme_settings'])
            ? $settings['custom_theme_settings']
            : [];
        $preset_map = [];
        if (class_exists(BotSettingsManager::class)) {
            $custom_theme_presets = BotSettingsManager::get_custom_theme_presets();
            foreach ($custom_theme_presets as $preset) {
                if (!is_array($preset)) {
                    continue;
                }
                $preset_key = isset($preset['key']) ? sanitize_key((string) $preset['key']) : '';
                if ($preset_key === '') {
                    continue;
                }
                $preset_map[$preset_key] = [
                    'primary' => isset($preset['primary']) ? strtolower(trim((string) $preset['primary'])) : '',
                    'secondary' => isset($preset['secondary']) ? strtolower(trim((string) $preset['secondary'])) : '',
                ];
            }
        }

        // Backward compatibility: old bots may not have an explicit preset key saved.
        if (
            $custom_theme_preset_key === '' &&
            !empty($preset_map)
        ) {
            $saved_custom_primary = isset($raw_custom_theme_settings['primary_color'])
                ? strtolower(trim((string) $raw_custom_theme_settings['primary_color']))
                : '';
            $saved_custom_secondary = isset($raw_custom_theme_settings['secondary_color'])
                ? strtolower(trim((string) $raw_custom_theme_settings['secondary_color']))
                : '';
            if ($saved_custom_primary !== '' && $saved_custom_secondary !== '') {
                foreach ($preset_map as $preset_key => $preset_colors) {
                    if (
                        $preset_colors['primary'] !== '' &&
                        $preset_colors['secondary'] !== '' &&
                        $saved_custom_primary === $preset_colors['primary'] &&
                        $saved_custom_secondary === $preset_colors['secondary']
                    ) {
                        $custom_theme_preset_key = $preset_key;
                        break;
                    }
                }
            }
        }

        if ($custom_theme_preset_key !== '' && !isset($preset_map[$custom_theme_preset_key])) {
            $custom_theme_preset_key = '';
        }

        $custom_theme_settings_for_js = $settings['custom_theme_settings'] ?? [];
        $custom_theme_settings_for_js = array_filter($custom_theme_settings_for_js, function ($value) {
            return $value !== '' && $value !== null;
        });

        $custom_theme_defaults = class_exists(BotSettingsManager::class)
            ? BotSettingsManager::get_custom_theme_defaults()
            : [];
        $token_keys = [
            'primary_color', 'secondary_color', 'auto_text_contrast',
            'font_family', 'bubble_border_radius', 'container_border_radius',
            'container_max_width', 'popup_width', 'container_height', 'container_min_height',
            'container_max_height', 'popup_height', 'popup_min_height', 'popup_max_height'
        ];
        $numeric_keys = [
            'bubble_border_radius', 'container_border_radius', 'container_max_width', 'popup_width',
            'container_height', 'container_min_height', 'container_max_height',
            'popup_height', 'popup_min_height', 'popup_max_height'
        ];
        $token_key_map = array_fill_keys($token_keys, true);
        $has_tokens = false;
        foreach ($token_keys as $token_key) {
            if (array_key_exists($token_key, $custom_theme_settings_for_js)) {
                $has_tokens = true;
                break;
            }
        }
        if ($has_tokens && !empty($custom_theme_defaults)) {
            foreach ($custom_theme_settings_for_js as $key => $value) {
                if (isset($token_key_map[$key])) {
                    continue;
                }
                if (!array_key_exists($key, $custom_theme_defaults)) {
                    continue;
                }
                $default_value = $custom_theme_defaults[$key];
                if (in_array($key, $numeric_keys, true)) {
                    if (is_numeric($value) && is_numeric($default_value) && (int)$value === (int)$default_value) {
                        unset($custom_theme_settings_for_js[$key]);
                    }
                } elseif (is_string($value) && is_string($default_value)) {
                    if (strtolower($value) === strtolower($default_value)) {
                        unset($custom_theme_settings_for_js[$key]);
                    }
                } elseif ($value === $default_value) {
                    unset($custom_theme_settings_for_js[$key]);
                }
            }
        }
    }
    // --- END ---

    $direct_voice_mode_flag = ($feature_flags['popup_enabled'] ?? false) &&
                              ($feature_flags['enable_realtime_voice_ui'] ?? false) &&
                              (($settings['direct_voice_mode'] ?? '0') === '1');

    return [
        'botId' => $bot_id,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => $nonce,
        'postId' => $current_post_id,
        'theme' => $settings['theme'] ?? 'light',
        'popupEnabled' => $feature_flags['popup_enabled'],
        'popupPosition' => $settings['popup_position'] ?? 'bottom-right',
        'popupDelay' => absint($settings['popup_delay'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_POPUP_DELAY : 1)),
        'popupIconType' => $settings['popup_icon_type'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_POPUP_ICON_TYPE : 'default'),
        'popupIconStyle' => $settings['popup_icon_style'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_POPUP_ICON_STYLE : 'circle'),
        'popupIconValue' => $settings['popup_icon_value'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_POPUP_ICON_VALUE : 'chat-bubble'),
        'popupIconSize' => (function() use ($settings) {
            $val = $settings['popup_icon_size'] ?? 'medium';
            return in_array($val, ['small','medium','large','xlarge'], true) ? $val : 'medium';
        })(),
        'customThemePresetKey' => $custom_theme_preset_key,
        'popupLabelEnabled' => ($settings['popup_label_enabled'] ?? '0') === '1',
        'popupLabelText' => (function() use ($settings) {
            $fallback = class_exists(BotSettingsManager::class)
                ? BotSettingsManager::DEFAULT_POPUP_LABEL_TEXT
                : 'Need help? Ask me!';
            $text = isset($settings['popup_label_text'])
                ? wp_strip_all_tags((string) $settings['popup_label_text'])
                : '';
            $text = trim($text);
            return $text !== '' ? $text : $fallback;
        })(),
        // Modes: 'always', 'on_delay', 'until_open', 'until_dismissed'
        'popupLabelMode' => in_array(($settings['popup_label_mode'] ?? 'on_delay'), ['always','on_delay','until_open','until_dismissed'], true) ? $settings['popup_label_mode'] : 'on_delay',
        'popupLabelDelaySeconds' => max(0, absint($settings['popup_label_delay_seconds'] ?? BotSettingsManager::DEFAULT_POPUP_LABEL_DELAY_SECONDS)),
        // 0 = never auto-hide
        'popupLabelAutoHideSeconds' => max(0, absint($settings['popup_label_auto_hide_seconds'] ?? 0)),
        'popupLabelDismissible' => ($settings['popup_label_dismissible'] ?? '1') === '1',
        // Frequency: 'always', 'once_per_session', 'once_per_visitor'
        'popupLabelFrequency' => in_array(($settings['popup_label_frequency'] ?? 'once_per_visitor'), ['always','once_per_session','once_per_visitor'], true) ? $settings['popup_label_frequency'] : 'once_per_visitor',
        'popupLabelShowOnMobile' => ($settings['popup_label_show_on_mobile'] ?? '1') === '1',
        'popupLabelShowOnDesktop' => ($settings['popup_label_show_on_desktop'] ?? '1') === '1',
        // Bump this (any string) to re-show hints for everyone
        'popupLabelVersion' => isset($settings['popup_label_version']) ? (string)$settings['popup_label_version'] : '',
        'popupLabelSize' => (function() use ($settings) {
            $fallback = class_exists(BotSettingsManager::class)
                ? BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE
                : 'large';
            $value = $settings['popup_label_size'] ?? $fallback;
            return in_array($value, ['small','medium','large','xlarge'], true) ? $value : $fallback;
        })(),
        'footerText' => $settings['footer_text'] ?? '',
        'headerAvatarType' => $settings['header_avatar_type'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_HEADER_AVATAR_TYPE : 'default'),
        'headerAvatarValue' => $settings['header_avatar_value'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_HEADER_AVATAR_VALUE : 'chat-bubble'),
        'headerAvatarUrl' => (($settings['header_avatar_type'] ?? '') === 'custom')
            ? ($settings['header_avatar_value'] ?? ($settings['header_avatar_url'] ?? ''))
            : '',
        'headerOnlineText' => $settings['header_online_text'] ?? '',
        'enableFullscreen' => $feature_flags['enable_fullscreen'],
        'enableDownload' => $feature_flags['enable_download'],
        'enableCopyButton' => $feature_flags['enable_copy_button'],
        'enableFeedback' => $feature_flags['feedback_ui_enabled'],
        'enableSidebar' => $feature_flags['sidebar_ui_enabled'],
        'pdfDownloadActive' => $feature_flags['pdf_ui_enabled'],
        'headerName' => $bot_post->post_title ?: '',
        'enableStarters' => $feature_flags['starters_ui_enabled'],
        'starters' => $starters_array,
        'requireConsentCompliance' => $consent_required && $consent_toggle_enabled,
        'ttsEnabled' => $feature_flags['tts_ui_enabled'],
        'ttsAutoPlay' => $tts_settings['tts_auto_play'],
        'ttsProvider' => $tts_settings['tts_provider'],
        'ttsVoiceId' => $tts_settings['tts_voice_id'],
        'ttsOpenAIModelId' => $tts_settings['tts_openai_model_id'],
        'ttsElevenLabsModelId' => $tts_settings['tts_elevenlabs_model_id'],
        'enableVoiceInputUI' => $feature_flags['enable_voice_input_ui'] ?? false,
        'enableRealtimeVoiceUI' => $feature_flags['enable_realtime_voice_ui'] ?? false,
        'directVoiceMode' => $direct_voice_mode_flag,
        'realtimeModel' => $settings['realtime_model'] ?? 'gpt-4o-realtime-preview',
        'sttProvider' => $settings['stt_provider'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_STT_PROVIDER : 'OpenAI'),
        'imageTriggers' => $image_triggers,
        'fileUploadEnabledUI' => $feature_flags['file_upload_ui_enabled'] ?? false,
        'imageUploadEnabledUI' => $feature_flags['image_upload_ui_enabled'] ?? false,
        'inputActionButtonEnabled' => $feature_flags['input_action_button_enabled'] ?? false,
        'provider' => $settings['provider'] ?? 'OpenAI', // This is the Main AI provider for the bot
        // This line ensures vectorStoreProvider (e.g., 'openai', 'pinecone', 'qdrant', 'chroma', 'claude_files') is passed to JS.
        'vectorStoreProvider' => $settings['vector_store_provider'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_VECTOR_STORE_PROVIDER : 'openai'),
        'enableOpenAIConversationState' => $enable_openai_conv_state,
        'allowWebSearchTool' => $allow_openai_web_search_tool,
        'webToggleDefaultOn' => ($settings['web_toggle_default_on'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_WEB_TOGGLE_DEFAULT_ON : '0')) === '1',
        'showSources' => ($settings['show_sources'] ?? (class_exists(BotSettingsManager::class) ? BotSettingsManager::DEFAULT_SHOW_SOURCES : '1')) === '1',
        'allowGoogleSearchGrounding' => $google_grounding_settings['allowGoogleSearchGrounding'],
        'googleGroundingMode' => $google_grounding_settings['googleGroundingMode'],
        'googleGroundingDynamicThreshold' => $google_grounding_settings['googleGroundingDynamicThreshold'],
        'customThemeSettings' => $custom_theme_settings_for_js,
        'text' => $text_labels,
        'customTypingText' => (function() use ($settings, $text_labels) {
            $txt = isset($settings['custom_typing_text']) ? trim((string)$settings['custom_typing_text']) : '';
            // If empty, frontend shows dots; no auto-fallback
            return $txt;
        })(),
    ];
}
