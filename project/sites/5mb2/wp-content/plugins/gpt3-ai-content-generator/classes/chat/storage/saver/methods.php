<?php

namespace WPAICG\Chat\Storage\SaverMethods;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\aipkit_dashboard;
use WPAICG\AIPKit_Providers;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\Chat\Storage\SiteWideBotManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- validate-bot-post-logic.php ---
/**
 * Validates the bot post ID, type, and status.
 *
 * @param int $botId The ID of the bot post.
 * @return \WP_Post|WP_Error The WP_Post object on success, or WP_Error on failure.
 */
function validate_bot_post_logic(int $botId) {
    if (!class_exists(AdminSetup::class)) {
        $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
        if (file_exists($admin_setup_path)) {
            require_once $admin_setup_path;
        } else {
            return new WP_Error('dependency_missing_validator', __('AdminSetup class missing.', 'gpt3-ai-content-generator'));
        }
    }

    $post = get_post($botId);
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

// --- sanitize-settings-logic.php ---
/**
 * Sanitizes the raw bot settings array.
 * UPDATED: Includes sanitization for new custom theme settings.
 * UPDATED: Validates new theme names.
 * FIXED: Ensures color fields default to valid hex codes from $custom_theme_defaults if input is invalid/empty.
 * ADDED: Handles 'triggers_json' from form.
 *
 * @param array $raw_settings The raw settings array from $_POST or similar.
 * @param int $bot_id The ID of the bot (for context, e.g., default values).
 * @return array The sanitized settings array.
 */
function sanitize_settings_logic(array $raw_settings, int $bot_id): array
{
    $sanitized = [];
    $custom_theme_defaults = [];

    if (!class_exists(BotSettingsManager::class)) {
        // Fallback defaults if class is missing
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
    } else {
        $custom_theme_defaults = BotSettingsManager::get_custom_theme_defaults();
    }

    $sanitized['greeting'] = isset($raw_settings['greeting']) ? sanitize_textarea_field($raw_settings['greeting']) : '';
    $sanitized['subgreeting'] = isset($raw_settings['subgreeting']) ? sanitize_textarea_field($raw_settings['subgreeting']) : '';
    $sanitized['provider'] = isset($raw_settings['provider']) ? sanitize_text_field($raw_settings['provider']) : '';
    $valid_themes = ['light', 'dark', 'custom', 'chatgpt'];
    $sanitized['theme'] = isset($raw_settings['theme']) && in_array($raw_settings['theme'], $valid_themes, true)
        ? sanitize_text_field($raw_settings['theme'])
        : 'dark';
    $raw_theme_preset_key = isset($raw_settings['theme_preset_key'])
        ? sanitize_key((string) $raw_settings['theme_preset_key'])
        : '';
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
    $sanitized['theme_preset_key'] = (
        $sanitized['theme'] === 'custom' &&
        $raw_theme_preset_key !== '' &&
        isset($valid_theme_preset_keys[$raw_theme_preset_key])
    )
        ? $raw_theme_preset_key
        : '';
    $sanitized['instructions'] = isset($raw_settings['instructions']) ? AIPKit_Prompt_Sanitizer::sanitize($raw_settings['instructions']) : '';
    $sanitized['popup_enabled'] = (isset($raw_settings['popup_enabled']) && $raw_settings['popup_enabled'] === '1') ? '1' : '0';
    $sanitized['popup_position'] = isset($raw_settings['popup_position']) ? sanitize_key($raw_settings['popup_position']) : 'bottom-right';
    $sanitized['popup_delay'] = isset($raw_settings['popup_delay']) ? absint($raw_settings['popup_delay']) : BotSettingsManager::DEFAULT_POPUP_DELAY;
    $sanitized['site_wide_enabled'] = (isset($raw_settings['site_wide_enabled']) && $raw_settings['site_wide_enabled'] === '1') ? '1' : '0';
    if ($sanitized['popup_enabled'] !== '1') {
        $sanitized['site_wide_enabled'] = '0';
    }
    $raw_deploy_mode = isset($raw_settings['deploy_mode']) ? sanitize_key((string) $raw_settings['deploy_mode']) : '';
    $sanitized['deploy_mode'] = in_array($raw_deploy_mode, ['inline', 'popup', 'external'], true)
        ? $raw_deploy_mode
        : (($sanitized['popup_enabled'] === '1') ? 'popup' : 'inline');
    $sanitized['popup_icon_style'] = isset($raw_settings['popup_icon_style']) && in_array($raw_settings['popup_icon_style'], ['circle', 'square', 'none']) ? sanitize_key($raw_settings['popup_icon_style']) : BotSettingsManager::DEFAULT_POPUP_ICON_STYLE;
    $allowed_icon_sizes = ['small','medium','large','xlarge'];
    $sanitized['popup_icon_size'] = isset($raw_settings['popup_icon_size']) && in_array($raw_settings['popup_icon_size'], $allowed_icon_sizes, true)
        ? sanitize_key($raw_settings['popup_icon_size'])
        : (defined('WPAICG\\Chat\\Storage\\BotSettingsManager::DEFAULT_POPUP_ICON_SIZE') ? BotSettingsManager::DEFAULT_POPUP_ICON_SIZE : 'medium');
    $sanitized['popup_icon_type'] = isset($raw_settings['popup_icon_type']) && in_array($raw_settings['popup_icon_type'], ['default', 'custom']) ? $raw_settings['popup_icon_type'] : BotSettingsManager::DEFAULT_POPUP_ICON_TYPE;
    $sanitized['popup_icon_value'] = '';
    if ($sanitized['popup_icon_type'] === 'default') {
        $default_icon_key = isset($raw_settings['popup_icon_default']) && in_array($raw_settings['popup_icon_default'], ['chat-bubble', 'spark', 'openai', 'plus', 'question-mark']) ? $raw_settings['popup_icon_default'] : BotSettingsManager::DEFAULT_POPUP_ICON_VALUE;
        $sanitized['popup_icon_value'] = $default_icon_key;
    } elseif ($sanitized['popup_icon_type'] === 'custom') {
        $sanitized['popup_icon_value'] = isset($raw_settings['popup_icon_custom_url']) ? esc_url_raw(trim($raw_settings['popup_icon_custom_url'])) : '';
    }
    $sanitized['footer_text'] = isset($raw_settings['footer_text']) ? wp_kses_post($raw_settings['footer_text']) : '';
    $allowed_header_icons = ['chat-bubble', 'spark', 'openai', 'plus', 'question-mark'];
    $header_avatar_type = isset($raw_settings['header_avatar_type']) && in_array($raw_settings['header_avatar_type'], ['default', 'custom'], true)
        ? sanitize_key($raw_settings['header_avatar_type'])
        : BotSettingsManager::DEFAULT_HEADER_AVATAR_TYPE;
    if (!isset($raw_settings['header_avatar_type']) && !empty($raw_settings['header_avatar_url'])) {
        $header_avatar_type = 'custom';
    }
    $header_avatar_value = '';
    $header_avatar_url = '';
    if ($header_avatar_type === 'custom') {
        $header_avatar_url = isset($raw_settings['header_avatar_url'])
            ? esc_url_raw(trim((string)$raw_settings['header_avatar_url']))
            : BotSettingsManager::DEFAULT_HEADER_AVATAR_URL;
        $header_avatar_value = $header_avatar_url;
    } else {
        $default_avatar_key = isset($raw_settings['header_avatar_default']) && in_array($raw_settings['header_avatar_default'], $allowed_header_icons, true)
            ? sanitize_key($raw_settings['header_avatar_default'])
            : BotSettingsManager::DEFAULT_HEADER_AVATAR_VALUE;
        $header_avatar_value = $default_avatar_key;
    }
    $sanitized['header_avatar_type'] = $header_avatar_type;
    $sanitized['header_avatar_value'] = $header_avatar_value;
    $sanitized['header_avatar_url'] = $header_avatar_url;
    $sanitized['header_online_text'] = isset($raw_settings['header_online_text'])
        ? sanitize_text_field($raw_settings['header_online_text'])
        : __('Online', 'gpt3-ai-content-generator');
    $sanitized['enable_fullscreen'] = (isset($raw_settings['enable_fullscreen']) && $raw_settings['enable_fullscreen'] === '1') ? '1' : '0';
    $sanitized['enable_download'] = (isset($raw_settings['enable_download']) && $raw_settings['enable_download'] === '1') ? '1' : '0';
    $sanitized['enable_copy_button'] = '1';
    $sanitized['enable_feedback'] = '1';
    $sanitized['enable_consent_compliance'] = (isset($raw_settings['enable_consent_compliance']) && $raw_settings['enable_consent_compliance'] === '1') ? '1' : '0';
    $sanitized['consent_title'] = isset($raw_settings['consent_title']) ? sanitize_text_field($raw_settings['consent_title']) : '';
    $sanitized['consent_message'] = isset($raw_settings['consent_message']) ? wp_kses_post($raw_settings['consent_message']) : '';
    $sanitized['consent_button'] = isset($raw_settings['consent_button']) ? sanitize_text_field($raw_settings['consent_button']) : '';
    $sanitized['enable_conversation_sidebar'] = (isset($raw_settings['enable_conversation_sidebar']) && $raw_settings['enable_conversation_sidebar'] === '1') ? '1' : '0';
    // Typing indicator customization
    $sanitized['custom_typing_text'] = isset($raw_settings['custom_typing_text']) ? sanitize_text_field($raw_settings['custom_typing_text']) : '';
    $sanitized['retrieving_context_text'] = isset($raw_settings['retrieving_context_text'])
        ? sanitize_text_field((string) $raw_settings['retrieving_context_text'])
        : BotSettingsManager::DEFAULT_RETRIEVING_CONTEXT_TEXT;
    $sanitized['input_placeholder'] = isset($raw_settings['input_placeholder']) ? sanitize_text_field($raw_settings['input_placeholder']) : __('Type your message...', 'gpt3-ai-content-generator');
    $sanitized['temperature'] = isset($raw_settings['temperature']) ? floatval($raw_settings['temperature']) : BotSettingsManager::DEFAULT_TEMPERATURE;
    $sanitized['max_completion_tokens'] = isset($raw_settings['max_completion_tokens']) ? absint($raw_settings['max_completion_tokens']) : BotSettingsManager::DEFAULT_MAX_COMPLETION_TOKENS;
    $sanitized['max_messages'] = isset($raw_settings['max_messages']) ? absint($raw_settings['max_messages']) : BotSettingsManager::DEFAULT_MAX_MESSAGES;
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($raw_settings['reasoning_effort'] ?? '');
    $sanitized['reasoning_effort'] = $reasoning_effort !== '' ? $reasoning_effort : BotSettingsManager::DEFAULT_REASONING_EFFORT;
    $sanitized['enable_conversation_starters'] = (isset($raw_settings['enable_conversation_starters']) && $raw_settings['enable_conversation_starters'] === '1') ? '1' : '0';
    $starters_raw = isset($raw_settings['conversation_starters']) ? $raw_settings['conversation_starters'] : ''; // Textarea value
    $starters_array = [];
    if (!empty($starters_raw)) {
        $lines = explode("\n", $starters_raw);
        foreach ($lines as $line) {
            $trimmed_line = trim($line);
            if (!empty($trimmed_line)) {
                $starters_array[] = $trimmed_line;
            }
        }
        $starters_array = array_slice($starters_array, 0, 6);
    }
    $sanitized['conversation_starters'] = wp_json_encode($starters_array, JSON_UNESCAPED_UNICODE);
    $sanitized['content_aware_enabled'] = (isset($raw_settings['content_aware_enabled']) && $raw_settings['content_aware_enabled'] === '1') ? '1' : '0';
    $sanitized['openai_conversation_state_enabled'] = (isset($raw_settings['openai_conversation_state_enabled']) && $raw_settings['openai_conversation_state_enabled'] === '1') ? '1' : '0';
    $sanitized['token_limit_mode'] = isset($raw_settings['token_limit_mode']) && in_array($raw_settings['token_limit_mode'], ['general', 'role_based']) ? $raw_settings['token_limit_mode'] : BotSettingsManager::DEFAULT_TOKEN_LIMIT_MODE;
    $raw_guest_limit = isset($raw_settings['token_guest_limit']) ? trim($raw_settings['token_guest_limit']) : '';
    $sanitized['token_guest_limit'] = ($raw_guest_limit === '0' || (ctype_digit($raw_guest_limit) && $raw_guest_limit > 0)) ? (string)absint($raw_guest_limit) : ''; // Store as string or empty
    $raw_user_limit = isset($raw_settings['token_user_limit']) ? trim($raw_settings['token_user_limit']) : '';
    $sanitized['token_user_limit'] = ($raw_user_limit === '0' || (ctype_digit($raw_user_limit) && $raw_user_limit > 0)) ? (string)absint($raw_user_limit) : '';
    $role_limits_to_save = [];
    if (isset($raw_settings['token_role_limits']) && is_array($raw_settings['token_role_limits'])) {
        $editable_roles = get_editable_roles();
        foreach ($editable_roles as $role_slug => $role_info) {
            if (isset($raw_settings['token_role_limits'][$role_slug])) {
                $raw_limit = trim($raw_settings['token_role_limits'][$role_slug]);
                if ($raw_limit === '0' || (ctype_digit($raw_limit) && $raw_limit > 0)) {
                    $role_limits_to_save[$role_slug] = (string)absint($raw_limit);
                } else {
                    $role_limits_to_save[$role_slug] = '';
                }
            }
        }
    }
    $sanitized['token_role_limits'] = wp_json_encode($role_limits_to_save, JSON_UNESCAPED_UNICODE);
    $sanitized['token_reset_period'] = isset($raw_settings['token_reset_period']) && in_array($raw_settings['token_reset_period'], ['never', 'daily', 'weekly', 'monthly']) ? sanitize_key($raw_settings['token_reset_period']) : BotSettingsManager::DEFAULT_TOKEN_RESET_PERIOD;
    $sanitized['token_limit_message'] = isset($raw_settings['token_limit_message']) ? sanitize_text_field($raw_settings['token_limit_message']) : '';
    $allowed_token_limit_action_types = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_token_limit_action_types()
        : ['none', 'dashboard_usage', 'dashboard_credits', 'dashboard_purchases', 'buy_credits', 'custom_url'];
    $default_token_limit_actions = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_default_token_limit_action_settings()
        : [
            'primary_type' => 'none',
            'primary_label' => '',
            'primary_url' => '',
            'secondary_type' => 'none',
            'secondary_label' => '',
            'secondary_url' => '',
        ];
    $sanitized['token_limit_primary_action_type'] = isset($raw_settings['token_limit_primary_action_type']) && in_array($raw_settings['token_limit_primary_action_type'], $allowed_token_limit_action_types, true)
        ? sanitize_key($raw_settings['token_limit_primary_action_type'])
        : $default_token_limit_actions['primary_type'];
    $sanitized['token_limit_primary_action_label'] = isset($raw_settings['token_limit_primary_action_label'])
        ? sanitize_text_field((string) $raw_settings['token_limit_primary_action_label'])
        : $default_token_limit_actions['primary_label'];
    if ($sanitized['token_limit_primary_action_type'] === 'none') {
        $sanitized['token_limit_primary_action_label'] = '';
    } elseif ($sanitized['token_limit_primary_action_label'] === '') {
        $sanitized['token_limit_primary_action_label'] = class_exists(BotSettingsManager::class)
            ? BotSettingsManager::get_token_limit_action_default_label($sanitized['token_limit_primary_action_type'])
            : $default_token_limit_actions['primary_label'];
    }
    $sanitized['token_limit_primary_action_url'] = isset($raw_settings['token_limit_primary_action_url'])
        ? esc_url_raw(trim((string) $raw_settings['token_limit_primary_action_url']))
        : $default_token_limit_actions['primary_url'];
    $sanitized['token_limit_secondary_action_type'] = isset($raw_settings['token_limit_secondary_action_type']) && in_array($raw_settings['token_limit_secondary_action_type'], $allowed_token_limit_action_types, true)
        ? sanitize_key($raw_settings['token_limit_secondary_action_type'])
        : $default_token_limit_actions['secondary_type'];
    $sanitized['token_limit_secondary_action_label'] = isset($raw_settings['token_limit_secondary_action_label'])
        ? sanitize_text_field((string) $raw_settings['token_limit_secondary_action_label'])
        : $default_token_limit_actions['secondary_label'];
    if ($sanitized['token_limit_secondary_action_type'] === 'none') {
        $sanitized['token_limit_secondary_action_label'] = '';
    } elseif ($sanitized['token_limit_secondary_action_label'] === '') {
        $sanitized['token_limit_secondary_action_label'] = class_exists(BotSettingsManager::class)
            ? BotSettingsManager::get_token_limit_action_default_label($sanitized['token_limit_secondary_action_type'])
            : $default_token_limit_actions['secondary_label'];
    }
    $sanitized['token_limit_secondary_action_url'] = isset($raw_settings['token_limit_secondary_action_url'])
        ? esc_url_raw(trim((string) $raw_settings['token_limit_secondary_action_url']))
        : $default_token_limit_actions['secondary_url'];
    $sanitized['model'] = isset($raw_settings['model']) ? sanitize_text_field($raw_settings['model']) : '';
    $sanitized['tts_enabled'] = (isset($raw_settings['tts_enabled']) && $raw_settings['tts_enabled'] === '1') ? '1' : '0';
    $sanitized['tts_provider'] = isset($raw_settings['tts_provider']) ? sanitize_text_field($raw_settings['tts_provider']) : BotSettingsManager::DEFAULT_TTS_PROVIDER;
    if (!in_array($sanitized['tts_provider'], ['Google', 'OpenAI', 'ElevenLabs'])) {
        $sanitized['tts_provider'] = BotSettingsManager::DEFAULT_TTS_PROVIDER;
    }
    $sanitized['tts_google_voice_id'] = isset($raw_settings['tts_google_voice_id']) ? sanitize_text_field($raw_settings['tts_google_voice_id']) : '';
    $sanitized['tts_openai_voice_id'] = isset($raw_settings['tts_openai_voice_id']) ? sanitize_text_field($raw_settings['tts_openai_voice_id']) : 'alloy';
    $sanitized['tts_openai_model_id'] = isset($raw_settings['tts_openai_model_id']) ? sanitize_text_field($raw_settings['tts_openai_model_id']) : BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID;
    $sanitized['tts_elevenlabs_voice_id'] = isset($raw_settings['tts_elevenlabs_voice_id']) ? sanitize_text_field($raw_settings['tts_elevenlabs_voice_id']) : '';
    $sanitized['tts_elevenlabs_model_id'] = isset($raw_settings['tts_elevenlabs_model_id']) ? sanitize_text_field($raw_settings['tts_elevenlabs_model_id']) : '';
    $sanitized['tts_auto_play'] = (isset($raw_settings['tts_auto_play']) && $raw_settings['tts_auto_play'] === '1') ? '1' : '0';
    $sanitized['enable_voice_input'] = (isset($raw_settings['enable_voice_input']) && $raw_settings['enable_voice_input'] === '1') ? '1' : '0';
    $sanitized['stt_provider'] = isset($raw_settings['stt_provider']) ? sanitize_text_field($raw_settings['stt_provider']) : BotSettingsManager::DEFAULT_STT_PROVIDER;
    if (!in_array($sanitized['stt_provider'], ['OpenAI', 'Azure'])) {
        $sanitized['stt_provider'] = BotSettingsManager::DEFAULT_STT_PROVIDER;
    }
    $sanitized['stt_openai_model_id'] = isset($raw_settings['stt_openai_model_id']) ? sanitize_text_field($raw_settings['stt_openai_model_id']) : BotSettingsManager::DEFAULT_STT_OPENAI_MODEL_ID;
    $sanitized['stt_azure_model_id'] = isset($raw_settings['stt_azure_model_id']) ? sanitize_text_field($raw_settings['stt_azure_model_id']) : BotSettingsManager::DEFAULT_STT_AZURE_MODEL_ID;
    $raw_image_triggers = isset($raw_settings['image_triggers']) ? sanitize_text_field($raw_settings['image_triggers']) : BotSettingsManager::DEFAULT_IMAGE_TRIGGERS;
    $triggers_array = array_map('trim', explode(',', $raw_image_triggers));
    $triggers_array = array_filter($triggers_array, function ($trigger) { return !empty($trigger) && preg_match('/^\/[a-zA-Z0-9_]+$/', $trigger); });
    $sanitized['image_triggers'] = !empty($triggers_array) ? implode(',', $triggers_array) : BotSettingsManager::DEFAULT_IMAGE_TRIGGERS;
    $sanitized['chat_image_model_id'] = isset($raw_settings['chat_image_model_id']) ? sanitize_text_field($raw_settings['chat_image_model_id']) : BotSettingsManager::DEFAULT_CHAT_IMAGE_MODEL_ID;
    $sanitized['enable_image_generation'] = (isset($raw_settings['enable_image_generation']) && $raw_settings['enable_image_generation'] === '1') ? '1' : '0';
    $sanitized['enable_file_upload'] = (isset($raw_settings['enable_file_upload']) && $raw_settings['enable_file_upload'] === '1') ? '1' : '0';
    $sanitized['enable_image_upload'] = (isset($raw_settings['enable_image_upload']) && $raw_settings['enable_image_upload'] === '1') ? '1' : '0';
    $sanitized['enable_vector_store'] = (isset($raw_settings['enable_vector_store']) && $raw_settings['enable_vector_store'] === '1') ? '1' : '0';
    $vector_store_provider_input = isset($raw_settings['vector_store_provider'])
        ? sanitize_key((string) $raw_settings['vector_store_provider'])
        : '';
    $allowed_vector_store_providers = ['openai', 'pinecone', 'qdrant', 'chroma'];
    if (strtolower((string) ($sanitized['provider'] ?? '')) === 'claude') {
        $allowed_vector_store_providers[] = 'claude_files';
    }
    $sanitized['vector_store_provider'] = in_array($vector_store_provider_input, $allowed_vector_store_providers, true)
        ? $vector_store_provider_input
        : BotSettingsManager::DEFAULT_VECTOR_STORE_PROVIDER;
    $openai_vs_ids_raw = isset($raw_settings['openai_vector_store_ids']) && is_array($raw_settings['openai_vector_store_ids']) ? $raw_settings['openai_vector_store_ids'] : [];
    $openai_vs_ids_to_save = [];
    foreach ($openai_vs_ids_raw as $vs_id) {
        $sanitized_id = sanitize_text_field(trim($vs_id));
        if (!empty($sanitized_id) && strpos($sanitized_id, 'vs_') === 0) {
            $openai_vs_ids_to_save[] = $sanitized_id;
        }
    }
    $sanitized['openai_vector_store_ids'] = wp_json_encode(array_values(array_unique($openai_vs_ids_to_save)));
    $sanitized['pinecone_index_name'] = ($sanitized['vector_store_provider'] === 'pinecone' && isset($raw_settings['pinecone_index_name'])) ? sanitize_text_field($raw_settings['pinecone_index_name']) : '';
    // Qdrant: accept multiple collections; also maintain legacy single for compatibility
    $qdrant_names_raw = [];
    if ($sanitized['vector_store_provider'] === 'qdrant') {
        if (isset($raw_settings['qdrant_collection_names'])) {
            $qdrant_names_raw = is_array($raw_settings['qdrant_collection_names']) ? $raw_settings['qdrant_collection_names'] : [];
        } elseif (isset($raw_settings['qdrant_collection_names']) && is_string($raw_settings['qdrant_collection_names'])) {
            // If sent as JSON string for any reason
            $decoded = json_decode($raw_settings['qdrant_collection_names'], true);
            if (is_array($decoded)) { $qdrant_names_raw = $decoded; }
        }
        // Fallback to single field
        if (empty($qdrant_names_raw) && isset($raw_settings['qdrant_collection_name'])) {
            $single = sanitize_text_field($raw_settings['qdrant_collection_name']);
            if (!empty($single)) { $qdrant_names_raw = [$single]; }
        }
    }
    $qdrant_names_clean = [];
    foreach ($qdrant_names_raw as $name) {
        $sn = sanitize_text_field(trim((string)$name));
        if ($sn !== '') { $qdrant_names_clean[] = $sn; }
    }
    $qdrant_names_clean = array_values(array_unique($qdrant_names_clean));
    $sanitized['qdrant_collection_names'] = wp_json_encode($qdrant_names_clean);
    $sanitized['qdrant_collection_name'] = $qdrant_names_clean[0] ?? '';
    $chroma_names_raw = [];
    if ($sanitized['vector_store_provider'] === 'chroma') {
        if (isset($raw_settings['chroma_collection_names']) && is_array($raw_settings['chroma_collection_names'])) {
            $chroma_names_raw = $raw_settings['chroma_collection_names'];
        } elseif (isset($raw_settings['chroma_collection_names']) && is_string($raw_settings['chroma_collection_names'])) {
            $decoded = json_decode($raw_settings['chroma_collection_names'], true);
            if (is_array($decoded)) { $chroma_names_raw = $decoded; }
        }
        if (empty($chroma_names_raw) && isset($raw_settings['chroma_collection_name'])) {
            $single = sanitize_text_field($raw_settings['chroma_collection_name']);
            if (!empty($single)) { $chroma_names_raw = [$single]; }
        }
    }
    $chroma_names_clean = [];
    foreach ($chroma_names_raw as $name) {
        $sn = sanitize_text_field(trim((string)$name));
        if ($sn !== '') { $chroma_names_clean[] = $sn; }
    }
    $chroma_names_clean = array_values(array_unique($chroma_names_clean));
    $sanitized['chroma_collection_names'] = wp_json_encode($chroma_names_clean);
    $sanitized['chroma_collection_name'] = $chroma_names_clean[0] ?? '';
    $allowed_embedding_providers = AIPKit_Providers::get_embedding_provider_keys('chat_settings_sanitize');
    $uses_custom_embedding_provider = in_array($sanitized['vector_store_provider'], ['pinecone', 'qdrant', 'chroma'], true);
    $sanitized['vector_embedding_provider'] = ($uses_custom_embedding_provider && isset($raw_settings['vector_embedding_provider']))
        ? sanitize_key($raw_settings['vector_embedding_provider'])
        : BotSettingsManager::DEFAULT_VECTOR_EMBEDDING_PROVIDER;
    if (!in_array($sanitized['vector_embedding_provider'], $allowed_embedding_providers, true)) {
        $sanitized['vector_embedding_provider'] = BotSettingsManager::DEFAULT_VECTOR_EMBEDDING_PROVIDER;
    }
    $sanitized['vector_embedding_model'] = ($uses_custom_embedding_provider && isset($raw_settings['vector_embedding_model']))
        ? sanitize_text_field($raw_settings['vector_embedding_model'])
        : '';
    // Backward-compatibility: accept combined "provider::model" values from older UI payloads.
    if (strpos($sanitized['vector_embedding_model'], '::') !== false) {
        [$model_provider, $model_id] = array_pad(explode('::', $sanitized['vector_embedding_model'], 2), 2, '');
        $model_provider = sanitize_key((string) $model_provider);
        $model_id = sanitize_text_field((string) $model_id);
        if ($model_id !== '' && in_array($model_provider, $allowed_embedding_providers, true)) {
            $sanitized['vector_embedding_provider'] = $model_provider;
            $sanitized['vector_embedding_model'] = $model_id;
        }
    }
    $raw_top_k = isset($raw_settings['vector_store_top_k']) ? absint($raw_settings['vector_store_top_k']) : BotSettingsManager::DEFAULT_VECTOR_STORE_TOP_K;
    $sanitized['vector_store_top_k'] = max(1, min($raw_top_k, 20));
    $raw_threshold = isset($raw_settings['vector_store_confidence_threshold']) ? absint($raw_settings['vector_store_confidence_threshold']) : BotSettingsManager::DEFAULT_VECTOR_STORE_CONFIDENCE_THRESHOLD;
    $sanitized['vector_store_confidence_threshold'] = max(0, min($raw_threshold, 100));
    // END NEW
    $sanitized['openai_web_search_enabled'] = (isset($raw_settings['openai_web_search_enabled']) && $raw_settings['openai_web_search_enabled'] === '1') ? '1' : '0';
    $sanitized['openai_web_search_context_size'] = isset($raw_settings['openai_web_search_context_size']) && in_array($raw_settings['openai_web_search_context_size'], ['low', 'medium', 'high']) ? $raw_settings['openai_web_search_context_size'] : BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE;
    $sanitized['openai_web_search_loc_type'] = isset($raw_settings['openai_web_search_loc_type']) && in_array($raw_settings['openai_web_search_loc_type'], ['none', 'approximate']) ? $raw_settings['openai_web_search_loc_type'] : BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_LOC_TYPE;
    $sanitized['openai_web_search_loc_country'] = isset($raw_settings['openai_web_search_loc_country']) ? sanitize_text_field($raw_settings['openai_web_search_loc_country']) : '';
    $sanitized['openai_web_search_loc_city'] = isset($raw_settings['openai_web_search_loc_city']) ? sanitize_text_field($raw_settings['openai_web_search_loc_city']) : '';
    $sanitized['openai_web_search_loc_region'] = isset($raw_settings['openai_web_search_loc_region']) ? sanitize_text_field($raw_settings['openai_web_search_loc_region']) : '';
    $sanitized['openai_web_search_loc_timezone'] = isset($raw_settings['openai_web_search_loc_timezone']) ? sanitize_text_field($raw_settings['openai_web_search_loc_timezone']) : '';
    $sanitized['claude_web_search_enabled'] = (isset($raw_settings['claude_web_search_enabled']) && $raw_settings['claude_web_search_enabled'] === '1') ? '1' : '0';
    $raw_claude_max_uses = isset($raw_settings['claude_web_search_max_uses']) ? absint($raw_settings['claude_web_search_max_uses']) : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES;
    $sanitized['claude_web_search_max_uses'] = max(1, min($raw_claude_max_uses, 20));
    $sanitized['claude_web_search_loc_type'] = isset($raw_settings['claude_web_search_loc_type']) && in_array($raw_settings['claude_web_search_loc_type'], ['none', 'approximate'], true)
        ? $raw_settings['claude_web_search_loc_type']
        : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_LOC_TYPE;
    $sanitized['claude_web_search_loc_country'] = isset($raw_settings['claude_web_search_loc_country']) ? sanitize_text_field($raw_settings['claude_web_search_loc_country']) : '';
    $sanitized['claude_web_search_loc_city'] = isset($raw_settings['claude_web_search_loc_city']) ? sanitize_text_field($raw_settings['claude_web_search_loc_city']) : '';
    $sanitized['claude_web_search_loc_region'] = isset($raw_settings['claude_web_search_loc_region']) ? sanitize_text_field($raw_settings['claude_web_search_loc_region']) : '';
    $sanitized['claude_web_search_loc_timezone'] = isset($raw_settings['claude_web_search_loc_timezone']) ? sanitize_text_field($raw_settings['claude_web_search_loc_timezone']) : '';
    $normalize_domains = static function ($domains_raw): string {
        if (!is_string($domains_raw)) {
            return '';
        }
        $domains = preg_split('/[\r\n,]+/', $domains_raw);
        if (!is_array($domains)) {
            return '';
        }
        $clean = [];
        foreach ($domains as $domain) {
            $value = strtolower(trim((string) $domain));
            if ($value === '') {
                continue;
            }
            $value = preg_replace('/^https?:\/\//', '', $value);
            $value = trim((string) $value, " \t\n\r\0\x0B/");
            if ($value === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $value)) {
                continue;
            }
            $clean[] = $value;
        }
        return implode(',', array_values(array_unique($clean)));
    };
    $sanitized['claude_web_search_allowed_domains'] = $normalize_domains($raw_settings['claude_web_search_allowed_domains'] ?? '');
    $sanitized['claude_web_search_blocked_domains'] = $normalize_domains($raw_settings['claude_web_search_blocked_domains'] ?? '');
    if ($sanitized['claude_web_search_allowed_domains'] !== '') {
        $sanitized['claude_web_search_blocked_domains'] = '';
    }
    $sanitized['claude_web_search_cache_ttl'] = isset($raw_settings['claude_web_search_cache_ttl']) && in_array($raw_settings['claude_web_search_cache_ttl'], ['none', '5m', '1h'], true)
        ? $raw_settings['claude_web_search_cache_ttl']
        : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL;
    $sanitized['openrouter_web_search_enabled'] = (isset($raw_settings['openrouter_web_search_enabled']) && $raw_settings['openrouter_web_search_enabled'] === '1') ? '1' : '0';
    $sanitized['openrouter_web_search_engine'] = isset($raw_settings['openrouter_web_search_engine']) && in_array($raw_settings['openrouter_web_search_engine'], ['auto', 'native', 'exa'], true)
        ? $raw_settings['openrouter_web_search_engine']
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE;
    $raw_openrouter_max_results = isset($raw_settings['openrouter_web_search_max_results'])
        ? absint($raw_settings['openrouter_web_search_max_results'])
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS;
    $sanitized['openrouter_web_search_max_results'] = max(1, min($raw_openrouter_max_results, 10));
    $sanitized['openrouter_web_search_search_prompt'] = isset($raw_settings['openrouter_web_search_search_prompt'])
        ? AIPKit_Prompt_Sanitizer::sanitize($raw_settings['openrouter_web_search_search_prompt'])
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_SEARCH_PROMPT;
    $sanitized['xai_web_search_enabled'] = (isset($raw_settings['xai_web_search_enabled']) && $raw_settings['xai_web_search_enabled'] === '1') ? '1' : '0';
    $sanitized['google_search_grounding_enabled'] = (isset($raw_settings['google_search_grounding_enabled']) && $raw_settings['google_search_grounding_enabled'] === '1') ? '1' : '0';
    $sanitized['google_grounding_mode'] = isset($raw_settings['google_grounding_mode']) && in_array($raw_settings['google_grounding_mode'], ['DEFAULT_MODE', 'MODE_DYNAMIC']) ? $raw_settings['google_grounding_mode'] : BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
    $raw_google_threshold = isset($raw_settings['google_grounding_dynamic_threshold']) ? floatval($raw_settings['google_grounding_dynamic_threshold']) : BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;
    $sanitized['google_grounding_dynamic_threshold'] = max(0.0, min($raw_google_threshold, 1.0));
    $sanitized['web_toggle_default_on'] = (isset($raw_settings['web_toggle_default_on']) && $raw_settings['web_toggle_default_on'] === '1') ? '1' : '0';
    $sanitized['show_sources'] = isset($raw_settings['show_sources'])
        ? (($raw_settings['show_sources'] === '1') ? '1' : '0')
        : BotSettingsManager::DEFAULT_SHOW_SOURCES;
    $sanitized['sources_label'] = isset($raw_settings['sources_label'])
        ? sanitize_text_field((string) $raw_settings['sources_label'])
        : BotSettingsManager::DEFAULT_SOURCES_LABEL;
    $sanitized['searching_web_text'] = isset($raw_settings['searching_web_text'])
        ? sanitize_text_field((string) $raw_settings['searching_web_text'])
        : BotSettingsManager::DEFAULT_SEARCHING_WEB_TEXT;

    // --- Sanitize Realtime Voice Agent settings ---
    $sanitized['enable_realtime_voice'] = (isset($raw_settings['enable_realtime_voice']) && $raw_settings['enable_realtime_voice'] === '1') ? '1' : '0';
    $sanitized['direct_voice_mode'] = (isset($raw_settings['direct_voice_mode']) && $raw_settings['direct_voice_mode'] === '1') ? '1' : '0';
    $sanitized['realtime_model'] = isset($raw_settings['realtime_model']) && in_array($raw_settings['realtime_model'], ['gpt-4o-realtime-preview', 'gpt-4o-mini-realtime']) ? $raw_settings['realtime_model'] : 'gpt-4o-realtime-preview';
    $sanitized['realtime_voice'] = isset($raw_settings['realtime_voice']) && in_array($raw_settings['realtime_voice'], ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'onyx', 'nova', 'shimmer', 'verse']) ? $raw_settings['realtime_voice'] : 'alloy';
    $sanitized['turn_detection'] = isset($raw_settings['turn_detection']) && in_array($raw_settings['turn_detection'], ['none', 'server_vad', 'semantic_vad']) ? $raw_settings['turn_detection'] : 'server_vad';
    $sanitized['speed'] = isset($raw_settings['speed']) ? max(0.25, min(1.5, floatval($raw_settings['speed']))) : 1.0;
    $valid_audio_formats = ['pcm16', 'g711_ulaw', 'g711_alaw'];
    $sanitized['input_audio_format'] = isset($raw_settings['input_audio_format']) && in_array($raw_settings['input_audio_format'], $valid_audio_formats) ? $raw_settings['input_audio_format'] : 'pcm16';
    $sanitized['output_audio_format'] = isset($raw_settings['output_audio_format']) && in_array($raw_settings['output_audio_format'], $valid_audio_formats) ? $raw_settings['output_audio_format'] : 'pcm16';
    $sanitized['input_audio_noise_reduction'] = (isset($raw_settings['input_audio_noise_reduction']) && $raw_settings['input_audio_noise_reduction'] === '1') ? '1' : '0';
    // --- END Sanitize Realtime Voice Agent settings ---

    $sanitized['popup_label_enabled'] = (isset($raw_settings['popup_label_enabled']) && $raw_settings['popup_label_enabled'] === '1') ? '1' : '0';
    $raw_popup_label_mode = isset($raw_settings['popup_label_mode']) ? sanitize_key((string) $raw_settings['popup_label_mode']) : '';
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
    $normalized_popup_label_mode = $legacy_mode_map[$raw_popup_label_mode] ?? $raw_popup_label_mode;
    $allowed_modes = ['always','on_delay','until_open','until_dismissed'];
    $sanitized['popup_label_mode'] = in_array($normalized_popup_label_mode, $allowed_modes, true)
        ? $normalized_popup_label_mode
        : 'on_delay';
    $sanitized['popup_label_text'] = isset($raw_settings['popup_label_text']) ? sanitize_text_field($raw_settings['popup_label_text']) : '';
    $sanitized['popup_label_delay_seconds'] = isset($raw_settings['popup_label_delay_seconds']) ? max(0, absint($raw_settings['popup_label_delay_seconds'])) : BotSettingsManager::DEFAULT_POPUP_LABEL_DELAY_SECONDS;
    $sanitized['popup_label_auto_hide_seconds'] = isset($raw_settings['popup_label_auto_hide_seconds']) ? max(0, absint($raw_settings['popup_label_auto_hide_seconds'])) : 0;
    $sanitized['popup_label_dismissible'] = (isset($raw_settings['popup_label_dismissible']) && $raw_settings['popup_label_dismissible'] === '1') ? '1' : '0';
    $allowed_freq = ['always','once_per_session','once_per_visitor'];
    $raw_popup_label_frequency = isset($raw_settings['popup_label_frequency']) ? sanitize_key((string) $raw_settings['popup_label_frequency']) : '';
    if ($raw_popup_label_frequency === '' && isset($legacy_frequency_map[$raw_popup_label_mode])) {
        $raw_popup_label_frequency = $legacy_frequency_map[$raw_popup_label_mode];
    }
    $sanitized['popup_label_frequency'] = in_array($raw_popup_label_frequency, $allowed_freq, true)
        ? $raw_popup_label_frequency
        : 'once_per_visitor';
    $sanitized['popup_label_show_on_mobile'] = (isset($raw_settings['popup_label_show_on_mobile']) && $raw_settings['popup_label_show_on_mobile'] === '1') ? '1' : '0';
    $sanitized['popup_label_show_on_desktop'] = (isset($raw_settings['popup_label_show_on_desktop']) && $raw_settings['popup_label_show_on_desktop'] === '1') ? '1' : '0';
    $sanitized['popup_label_version'] = isset($raw_settings['popup_label_version']) ? sanitize_text_field($raw_settings['popup_label_version']) : '';
    $allowed_sizes = ['small','medium','large','xlarge'];
    $sanitized['popup_label_size'] = isset($raw_settings['popup_label_size']) && in_array($raw_settings['popup_label_size'], $allowed_sizes, true)
        ? $raw_settings['popup_label_size']
        : BotSettingsManager::DEFAULT_POPUP_LABEL_SIZE;

    $raw_domains = isset($raw_settings['embed_allowed_domains']) ? trim($raw_settings['embed_allowed_domains']) : '';
    $domains_array = preg_split('/[\s,]+/', $raw_domains, -1, PREG_SPLIT_NO_EMPTY);
    $sanitized_domains = [];
    foreach ($domains_array as $domain) {
        $sanitized_url = esc_url_raw(trim($domain));
        if (!empty($sanitized_url)) {
            $sanitized_domains[] = rtrim($sanitized_url, '/');
        }
    }
    $sanitized['embed_allowed_domains'] = implode("\n", array_unique($sanitized_domains));

    // Sanitize Custom Theme Settings
    $custom_theme_settings_raw = $raw_settings['custom_theme_settings'] ?? [];
    $custom_theme_settings_sanitized = [];

    foreach (array_keys($custom_theme_defaults) as $key) {
        if (strpos($key, '_placeholder') !== false) {
            continue;
        }

        $value = $custom_theme_settings_raw[$key] ?? '';
        if (substr_compare($key, '_color', -strlen('_color')) === 0) {
            if (preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value) || preg_match('/^rgba?\((\d{1,3}%?,\s*){2,3}\d{1,3}%?(,\s*(0|1|0?\.\d+))?\)$/', $value)) {
                $custom_theme_settings_sanitized[$key] = $value;
            } else {
                $custom_theme_settings_sanitized[$key] = $custom_theme_defaults[$key] ?? '#FFFFFF';
            }
        } elseif ($key === 'font_family') {
            $allowed_fonts = [
                '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                'Arial, Helvetica, sans-serif', 'Verdana, Geneva, sans-serif', 'Tahoma, Geneva, sans-serif',
                '"Trebuchet MS", Helvetica, sans-serif', '"Times New Roman", Times, serif', 'Georgia, serif',
                'Garamond, serif', '"Courier New", Courier, monospace', '"Brush Script MT", cursive', 'inherit'
            ];
            $custom_theme_settings_sanitized[$key] = in_array($value, $allowed_fonts, true) ? $value : ($custom_theme_defaults['font_family'] ?? 'inherit');
        } elseif ($key === 'auto_text_contrast') {
            $custom_theme_settings_sanitized[$key] = ($value === '0' || $value === 0) ? '0' : '1';
        } elseif ($key === 'bubble_border_radius' ||
                   $key === 'container_border_radius' ||
                   $key === 'container_max_width' ||
                   $key === 'popup_width' ||
                   $key === 'container_height' ||
                   $key === 'container_min_height' ||
                   $key === 'popup_height' ||
                   $key === 'popup_min_height'
        ) {
            $custom_theme_settings_sanitized[$key] = ($value === '' || !is_numeric($value)) ? '' : (string)max(0, absint($value));
        } elseif ($key === 'container_max_height' || $key === 'popup_max_height') {
            $custom_theme_settings_sanitized[$key] = ($value === '' || !is_numeric($value)) ? '' : (string)max(1, min(absint($value), 100));
        } else {
            $custom_theme_settings_sanitized[$key] = sanitize_text_field($value);
        }
    }
    $sanitized['custom_theme_settings'] = $custom_theme_settings_sanitized;

    $sanitized['triggers_json'] = isset($raw_settings['triggers_json']) ? trim(wp_unslash($raw_settings['triggers_json'])) : '[]';

    return $sanitized;
}

// --- handle-site-wide-logic.php ---
/**
 * Handles site-wide bot uniqueness and reports whether cache invalidation is needed.
 *
 * @param SiteWideBotManager $site_wide_manager The SiteWideBotManager instance.
 * @param int $botId The ID of the bot being saved.
 * @param string $site_wide_enabled_flag '0' or '1' indicating if site-wide is enabled for this bot.
 * @return bool Whether the site-wide cache should be cleared after meta is saved.
 */
function handle_site_wide_logic(SiteWideBotManager $site_wide_manager, int $botId, string $site_wide_enabled_flag): bool {
    return $site_wide_manager->ensure_site_wide_uniqueness($botId, $site_wide_enabled_flag === '1');
}

// --- save-meta-fields-logic.php ---
/**
 * Saves the sanitized bot settings as post meta fields.
 * UPDATED: Saves new custom theme settings.
 * NEW: Saves triggers JSON using AIPKit_Trigger_Storage::META_KEY.
 *
 * @param int $botId The ID of the bot post.
 * @param array $sanitized_settings The array of sanitized settings.
 * @return bool|WP_Error Returns true on success, or WP_Error if JSON for triggers is invalid.
 */
function save_meta_fields_logic(int $botId, array $sanitized_settings)
{
    update_post_meta($botId, '_aipkit_greeting_message', $sanitized_settings['greeting']);
    update_post_meta($botId, '_aipkit_subgreeting_message', $sanitized_settings['subgreeting']);
    update_post_meta($botId, '_aipkit_provider', $sanitized_settings['provider']);
    update_post_meta($botId, '_aipkit_theme', $sanitized_settings['theme']);
    if (
        ($sanitized_settings['theme'] ?? '') === 'custom' &&
        !empty($sanitized_settings['theme_preset_key'])
    ) {
        update_post_meta($botId, '_aipkit_theme_preset_key', $sanitized_settings['theme_preset_key']);
    } else {
        delete_post_meta($botId, '_aipkit_theme_preset_key');
    }
    update_post_meta($botId, '_aipkit_instructions', $sanitized_settings['instructions']);
    update_post_meta($botId, '_aipkit_deploy_mode', $sanitized_settings['deploy_mode']);
    update_post_meta($botId, '_aipkit_popup_enabled', $sanitized_settings['popup_enabled']);
    update_post_meta($botId, '_aipkit_popup_position', $sanitized_settings['popup_position']);
    update_post_meta($botId, '_aipkit_popup_delay', $sanitized_settings['popup_delay']);
    update_post_meta($botId, '_aipkit_site_wide_enabled', $sanitized_settings['site_wide_enabled']);
    update_post_meta($botId, '_aipkit_popup_icon_size', $sanitized_settings['popup_icon_size']);
    update_post_meta($botId, '_aipkit_popup_icon_type', $sanitized_settings['popup_icon_type']);
    update_post_meta($botId, '_aipkit_popup_icon_style', $sanitized_settings['popup_icon_style']);
    update_post_meta($botId, '_aipkit_popup_icon_value', $sanitized_settings['popup_icon_value']);
    update_post_meta($botId, '_aipkit_footer_text', $sanitized_settings['footer_text']);
    update_post_meta($botId, '_aipkit_header_avatar_type', $sanitized_settings['header_avatar_type']);
    update_post_meta($botId, '_aipkit_header_avatar_value', $sanitized_settings['header_avatar_value']);
    update_post_meta($botId, '_aipkit_header_avatar_url', $sanitized_settings['header_avatar_url']);
    update_post_meta($botId, '_aipkit_header_online_text', $sanitized_settings['header_online_text']);
    update_post_meta($botId, '_aipkit_enable_fullscreen', $sanitized_settings['enable_fullscreen']);
    update_post_meta($botId, '_aipkit_enable_download', $sanitized_settings['enable_download']);
    update_post_meta($botId, '_aipkit_enable_copy_button', $sanitized_settings['enable_copy_button']);
    update_post_meta($botId, '_aipkit_enable_feedback', $sanitized_settings['enable_feedback']);
    update_post_meta($botId, '_aipkit_enable_consent_compliance', $sanitized_settings['enable_consent_compliance']);
    update_post_meta($botId, '_aipkit_consent_title', $sanitized_settings['consent_title']);
    update_post_meta($botId, '_aipkit_consent_message', $sanitized_settings['consent_message']);
    update_post_meta($botId, '_aipkit_consent_button', $sanitized_settings['consent_button']);
    update_post_meta($botId, '_aipkit_enable_conversation_sidebar', $sanitized_settings['enable_conversation_sidebar']);
    update_post_meta($botId, '_aipkit_custom_typing_text', $sanitized_settings['custom_typing_text']);
    update_post_meta($botId, '_aipkit_retrieving_context_text', $sanitized_settings['retrieving_context_text']);
    update_post_meta($botId, '_aipkit_input_placeholder', $sanitized_settings['input_placeholder']);
    update_post_meta($botId, '_aipkit_temperature', (string)$sanitized_settings['temperature']);
    update_post_meta($botId, '_aipkit_max_completion_tokens', $sanitized_settings['max_completion_tokens']);
    update_post_meta($botId, '_aipkit_max_messages', $sanitized_settings['max_messages']);
    update_post_meta($botId, '_aipkit_reasoning_effort', $sanitized_settings['reasoning_effort']);
    update_post_meta($botId, '_aipkit_enable_conversation_starters', $sanitized_settings['enable_conversation_starters']);
    update_post_meta($botId, '_aipkit_conversation_starters', $sanitized_settings['conversation_starters']); // Already JSON
    update_post_meta($botId, '_aipkit_content_aware_enabled', $sanitized_settings['content_aware_enabled']);
    update_post_meta($botId, '_aipkit_openai_conversation_state_enabled', $sanitized_settings['openai_conversation_state_enabled']);
    update_post_meta($botId, '_aipkit_token_limit_mode', $sanitized_settings['token_limit_mode']);
    delete_post_meta($botId, '_aipkit_token_pricing_mode');
    if ($sanitized_settings['token_guest_limit'] === '') {
        delete_post_meta($botId, '_aipkit_token_guest_limit');
    } else {
        update_post_meta($botId, '_aipkit_token_guest_limit', $sanitized_settings['token_guest_limit']);
    }
    if ($sanitized_settings['token_user_limit'] === '') {
        delete_post_meta($botId, '_aipkit_token_user_limit');
    } else {
        update_post_meta($botId, '_aipkit_token_user_limit', $sanitized_settings['token_user_limit']);
    }
    if (empty(json_decode($sanitized_settings['token_role_limits'], true))) {
        delete_post_meta($botId, '_aipkit_token_role_limits');
    } else {
        update_post_meta($botId, '_aipkit_token_role_limits', $sanitized_settings['token_role_limits']);
    }
    update_post_meta($botId, '_aipkit_token_reset_period', $sanitized_settings['token_reset_period']);
    if (empty($sanitized_settings['token_limit_message'])) {
        delete_post_meta($botId, '_aipkit_token_limit_message');
    } else {
        update_post_meta($botId, '_aipkit_token_limit_message', $sanitized_settings['token_limit_message']);
    }
    update_post_meta($botId, '_aipkit_token_limit_primary_action_type', $sanitized_settings['token_limit_primary_action_type']);
    if (empty($sanitized_settings['token_limit_primary_action_label'])) {
        delete_post_meta($botId, '_aipkit_token_limit_primary_action_label');
    } else {
        update_post_meta($botId, '_aipkit_token_limit_primary_action_label', $sanitized_settings['token_limit_primary_action_label']);
    }
    if (empty($sanitized_settings['token_limit_primary_action_url'])) {
        delete_post_meta($botId, '_aipkit_token_limit_primary_action_url');
    } else {
        update_post_meta($botId, '_aipkit_token_limit_primary_action_url', $sanitized_settings['token_limit_primary_action_url']);
    }
    update_post_meta($botId, '_aipkit_token_limit_secondary_action_type', $sanitized_settings['token_limit_secondary_action_type']);
    if (empty($sanitized_settings['token_limit_secondary_action_label'])) {
        delete_post_meta($botId, '_aipkit_token_limit_secondary_action_label');
    } else {
        update_post_meta($botId, '_aipkit_token_limit_secondary_action_label', $sanitized_settings['token_limit_secondary_action_label']);
    }
    if (empty($sanitized_settings['token_limit_secondary_action_url'])) {
        delete_post_meta($botId, '_aipkit_token_limit_secondary_action_url');
    } else {
        update_post_meta($botId, '_aipkit_token_limit_secondary_action_url', $sanitized_settings['token_limit_secondary_action_url']);
    }
    if (!empty($sanitized_settings['model'])) {
        update_post_meta($botId, '_aipkit_model', $sanitized_settings['model']);
    } else {
        delete_post_meta($botId, '_aipkit_model');
    }
    delete_post_meta($botId, '_aipkit_azure_endpoint');
    delete_post_meta($botId, '_aipkit_azure_deployment');
    update_post_meta($botId, '_aipkit_tts_enabled', $sanitized_settings['tts_enabled']);
    update_post_meta($botId, '_aipkit_tts_provider', $sanitized_settings['tts_provider']);
    update_post_meta($botId, '_aipkit_tts_google_voice_id', $sanitized_settings['tts_google_voice_id']);
    update_post_meta($botId, '_aipkit_tts_openai_voice_id', $sanitized_settings['tts_openai_voice_id']);
    update_post_meta($botId, '_aipkit_tts_openai_model_id', $sanitized_settings['tts_openai_model_id']);
    update_post_meta($botId, '_aipkit_tts_elevenlabs_voice_id', $sanitized_settings['tts_elevenlabs_voice_id']);
    update_post_meta($botId, '_aipkit_tts_elevenlabs_model_id', $sanitized_settings['tts_elevenlabs_model_id']);
    update_post_meta($botId, '_aipkit_tts_auto_play', $sanitized_settings['tts_auto_play']);
    update_post_meta($botId, '_aipkit_enable_voice_input', $sanitized_settings['enable_voice_input']);
    update_post_meta($botId, '_aipkit_stt_provider', $sanitized_settings['stt_provider']);
    update_post_meta($botId, '_aipkit_stt_openai_model_id', $sanitized_settings['stt_openai_model_id']);
    update_post_meta($botId, '_aipkit_stt_azure_model_id', $sanitized_settings['stt_azure_model_id']);
    update_post_meta($botId, '_aipkit_image_triggers', $sanitized_settings['image_triggers']);
    update_post_meta($botId, '_aipkit_chat_image_model_id', $sanitized_settings['chat_image_model_id']);
    update_post_meta($botId, '_aipkit_enable_image_generation', $sanitized_settings['enable_image_generation']);
    update_post_meta($botId, '_aipkit_enable_file_upload', $sanitized_settings['enable_file_upload']);
    update_post_meta($botId, '_aipkit_enable_image_upload', $sanitized_settings['enable_image_upload']);
    update_post_meta($botId, '_aipkit_enable_vector_store', $sanitized_settings['enable_vector_store']);
    update_post_meta($botId, '_aipkit_vector_store_provider', $sanitized_settings['vector_store_provider']);
    if ($sanitized_settings['vector_store_provider'] === 'openai') {
        update_post_meta($botId, '_aipkit_openai_vector_store_ids', $sanitized_settings['openai_vector_store_ids']);
        delete_post_meta($botId, '_aipkit_pinecone_index_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_names');
        delete_post_meta($botId, '_aipkit_chroma_collection_name');
        delete_post_meta($botId, '_aipkit_chroma_collection_names');
        delete_post_meta($botId, '_aipkit_vector_embedding_provider');
        delete_post_meta($botId, '_aipkit_vector_embedding_model');
    } elseif ($sanitized_settings['vector_store_provider'] === 'pinecone') {
        update_post_meta($botId, '_aipkit_pinecone_index_name', $sanitized_settings['pinecone_index_name']);
        update_post_meta($botId, '_aipkit_vector_embedding_provider', $sanitized_settings['vector_embedding_provider']);
        update_post_meta($botId, '_aipkit_vector_embedding_model', $sanitized_settings['vector_embedding_model']);
        delete_post_meta($botId, '_aipkit_openai_vector_store_ids');
        delete_post_meta($botId, '_aipkit_qdrant_collection_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_names');
        delete_post_meta($botId, '_aipkit_chroma_collection_name');
        delete_post_meta($botId, '_aipkit_chroma_collection_names');
    } elseif ($sanitized_settings['vector_store_provider'] === 'qdrant') {
        update_post_meta($botId, '_aipkit_qdrant_collection_name', $sanitized_settings['qdrant_collection_name']);
        update_post_meta($botId, '_aipkit_qdrant_collection_names', $sanitized_settings['qdrant_collection_names']);
        update_post_meta($botId, '_aipkit_vector_embedding_provider', $sanitized_settings['vector_embedding_provider']);
        update_post_meta($botId, '_aipkit_vector_embedding_model', $sanitized_settings['vector_embedding_model']);
        delete_post_meta($botId, '_aipkit_openai_vector_store_ids');
        delete_post_meta($botId, '_aipkit_pinecone_index_name');
        delete_post_meta($botId, '_aipkit_chroma_collection_name');
        delete_post_meta($botId, '_aipkit_chroma_collection_names');
    } elseif ($sanitized_settings['vector_store_provider'] === 'chroma') {
        update_post_meta($botId, '_aipkit_chroma_collection_name', $sanitized_settings['chroma_collection_name']);
        update_post_meta($botId, '_aipkit_chroma_collection_names', $sanitized_settings['chroma_collection_names']);
        update_post_meta($botId, '_aipkit_vector_embedding_provider', $sanitized_settings['vector_embedding_provider']);
        update_post_meta($botId, '_aipkit_vector_embedding_model', $sanitized_settings['vector_embedding_model']);
        delete_post_meta($botId, '_aipkit_openai_vector_store_ids');
        delete_post_meta($botId, '_aipkit_pinecone_index_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_names');
    } else {
        delete_post_meta($botId, '_aipkit_openai_vector_store_ids');
        delete_post_meta($botId, '_aipkit_pinecone_index_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_name');
        delete_post_meta($botId, '_aipkit_qdrant_collection_names');
        delete_post_meta($botId, '_aipkit_chroma_collection_name');
        delete_post_meta($botId, '_aipkit_chroma_collection_names');
        delete_post_meta($botId, '_aipkit_vector_embedding_provider');
        delete_post_meta($botId, '_aipkit_vector_embedding_model');
    }
    update_post_meta($botId, '_aipkit_vector_store_top_k', $sanitized_settings['vector_store_top_k']);
    update_post_meta($botId, '_aipkit_vector_store_confidence_threshold', $sanitized_settings['vector_store_confidence_threshold']); // NEW
    update_post_meta($botId, '_aipkit_openai_web_search_enabled', $sanitized_settings['openai_web_search_enabled']);
    if ($sanitized_settings['openai_web_search_enabled'] === '1') {
        update_post_meta($botId, '_aipkit_openai_web_search_context_size', $sanitized_settings['openai_web_search_context_size']);
        update_post_meta($botId, '_aipkit_openai_web_search_loc_type', $sanitized_settings['openai_web_search_loc_type']);
        if ($sanitized_settings['openai_web_search_loc_type'] === 'approximate') {
            update_post_meta($botId, '_aipkit_openai_web_search_loc_country', $sanitized_settings['openai_web_search_loc_country']);
            update_post_meta($botId, '_aipkit_openai_web_search_loc_city', $sanitized_settings['openai_web_search_loc_city']);
            update_post_meta($botId, '_aipkit_openai_web_search_loc_region', $sanitized_settings['openai_web_search_loc_region']);
            update_post_meta($botId, '_aipkit_openai_web_search_loc_timezone', $sanitized_settings['openai_web_search_loc_timezone']);
        } else {
            delete_post_meta($botId, '_aipkit_openai_web_search_loc_country');
            delete_post_meta($botId, '_aipkit_openai_web_search_loc_city');
            delete_post_meta($botId, '_aipkit_openai_web_search_loc_region');
            delete_post_meta($botId, '_aipkit_openai_web_search_loc_timezone');
        }
    } else {
        delete_post_meta($botId, '_aipkit_openai_web_search_context_size');
        delete_post_meta($botId, '_aipkit_openai_web_search_loc_type');
        delete_post_meta($botId, '_aipkit_openai_web_search_loc_country');
        delete_post_meta($botId, '_aipkit_openai_web_search_loc_city');
        delete_post_meta($botId, '_aipkit_openai_web_search_loc_region');
        delete_post_meta($botId, '_aipkit_openai_web_search_loc_timezone');
    }
    update_post_meta($botId, '_aipkit_claude_web_search_enabled', $sanitized_settings['claude_web_search_enabled']);
    if ($sanitized_settings['claude_web_search_enabled'] === '1') {
        update_post_meta($botId, '_aipkit_claude_web_search_max_uses', (string) $sanitized_settings['claude_web_search_max_uses']);
        update_post_meta($botId, '_aipkit_claude_web_search_loc_type', $sanitized_settings['claude_web_search_loc_type']);
        if ($sanitized_settings['claude_web_search_loc_type'] === 'approximate') {
            update_post_meta($botId, '_aipkit_claude_web_search_loc_country', $sanitized_settings['claude_web_search_loc_country']);
            update_post_meta($botId, '_aipkit_claude_web_search_loc_city', $sanitized_settings['claude_web_search_loc_city']);
            update_post_meta($botId, '_aipkit_claude_web_search_loc_region', $sanitized_settings['claude_web_search_loc_region']);
            update_post_meta($botId, '_aipkit_claude_web_search_loc_timezone', $sanitized_settings['claude_web_search_loc_timezone']);
        } else {
            delete_post_meta($botId, '_aipkit_claude_web_search_loc_country');
            delete_post_meta($botId, '_aipkit_claude_web_search_loc_city');
            delete_post_meta($botId, '_aipkit_claude_web_search_loc_region');
            delete_post_meta($botId, '_aipkit_claude_web_search_loc_timezone');
        }
        if (!empty($sanitized_settings['claude_web_search_allowed_domains'])) {
            update_post_meta($botId, '_aipkit_claude_web_search_allowed_domains', $sanitized_settings['claude_web_search_allowed_domains']);
            delete_post_meta($botId, '_aipkit_claude_web_search_blocked_domains');
        } elseif (!empty($sanitized_settings['claude_web_search_blocked_domains'])) {
            update_post_meta($botId, '_aipkit_claude_web_search_blocked_domains', $sanitized_settings['claude_web_search_blocked_domains']);
            delete_post_meta($botId, '_aipkit_claude_web_search_allowed_domains');
        } else {
            delete_post_meta($botId, '_aipkit_claude_web_search_allowed_domains');
            delete_post_meta($botId, '_aipkit_claude_web_search_blocked_domains');
        }
        update_post_meta($botId, '_aipkit_claude_web_search_cache_ttl', $sanitized_settings['claude_web_search_cache_ttl']);
    } else {
        delete_post_meta($botId, '_aipkit_claude_web_search_max_uses');
        delete_post_meta($botId, '_aipkit_claude_web_search_loc_type');
        delete_post_meta($botId, '_aipkit_claude_web_search_loc_country');
        delete_post_meta($botId, '_aipkit_claude_web_search_loc_city');
        delete_post_meta($botId, '_aipkit_claude_web_search_loc_region');
        delete_post_meta($botId, '_aipkit_claude_web_search_loc_timezone');
        delete_post_meta($botId, '_aipkit_claude_web_search_allowed_domains');
        delete_post_meta($botId, '_aipkit_claude_web_search_blocked_domains');
        delete_post_meta($botId, '_aipkit_claude_web_search_cache_ttl');
    }
    update_post_meta($botId, '_aipkit_openrouter_web_search_enabled', $sanitized_settings['openrouter_web_search_enabled']);
    if ($sanitized_settings['openrouter_web_search_enabled'] === '1') {
        update_post_meta($botId, '_aipkit_openrouter_web_search_engine', $sanitized_settings['openrouter_web_search_engine']);
        update_post_meta($botId, '_aipkit_openrouter_web_search_max_results', (string) $sanitized_settings['openrouter_web_search_max_results']);
        update_post_meta($botId, '_aipkit_openrouter_web_search_search_prompt', $sanitized_settings['openrouter_web_search_search_prompt']);
    } else {
        delete_post_meta($botId, '_aipkit_openrouter_web_search_engine');
        delete_post_meta($botId, '_aipkit_openrouter_web_search_max_results');
        delete_post_meta($botId, '_aipkit_openrouter_web_search_search_prompt');
    }
    update_post_meta($botId, '_aipkit_xai_web_search_enabled', $sanitized_settings['xai_web_search_enabled']);
    update_post_meta($botId, '_aipkit_google_search_grounding_enabled', $sanitized_settings['google_search_grounding_enabled']);
    if ($sanitized_settings['google_search_grounding_enabled'] === '1') {
        update_post_meta($botId, '_aipkit_google_grounding_mode', $sanitized_settings['google_grounding_mode']);
        if ($sanitized_settings['google_grounding_mode'] === 'MODE_DYNAMIC') {
            update_post_meta($botId, '_aipkit_google_grounding_dynamic_threshold', (string)$sanitized_settings['google_grounding_dynamic_threshold']);
        } else {
            delete_post_meta($botId, '_aipkit_google_grounding_dynamic_threshold');
        }
    } else {
        delete_post_meta($botId, '_aipkit_google_grounding_mode');
        delete_post_meta($botId, '_aipkit_google_grounding_dynamic_threshold');
    }
    update_post_meta($botId, '_aipkit_web_toggle_default_on', $sanitized_settings['web_toggle_default_on']);
    update_post_meta($botId, '_aipkit_show_sources', $sanitized_settings['show_sources']);
    update_post_meta($botId, '_aipkit_sources_label', $sanitized_settings['sources_label']);
    update_post_meta($botId, '_aipkit_searching_web_text', $sanitized_settings['searching_web_text']);

    if (isset($sanitized_settings['custom_theme_settings']) && is_array($sanitized_settings['custom_theme_settings'])) {
        foreach ($sanitized_settings['custom_theme_settings'] as $key => $value) {
            update_post_meta($botId, '_aipkit_cts_' . $key, $value);
        }
        if (class_exists(\WPAICG\Chat\Storage\BotSettingsManager::class)) {
            \WPAICG\Chat\Storage\BotSettingsManager::cleanup_custom_theme_meta($botId);
        }
    }

    // --- Save Realtime Voice Agent settings ---
    update_post_meta($botId, '_aipkit_enable_realtime_voice', $sanitized_settings['enable_realtime_voice']);
    update_post_meta($botId, '_aipkit_direct_voice_mode', $sanitized_settings['direct_voice_mode']);
    update_post_meta($botId, '_aipkit_realtime_model', $sanitized_settings['realtime_model']);
    update_post_meta($botId, '_aipkit_realtime_voice', $sanitized_settings['realtime_voice']);
    update_post_meta($botId, '_aipkit_turn_detection', $sanitized_settings['turn_detection']);
    update_post_meta($botId, '_aipkit_speed', (string)$sanitized_settings['speed']);
    update_post_meta($botId, '_aipkit_input_audio_format', $sanitized_settings['input_audio_format']);
    update_post_meta($botId, '_aipkit_output_audio_format', $sanitized_settings['output_audio_format']);
    update_post_meta($botId, '_aipkit_input_audio_noise_reduction', $sanitized_settings['input_audio_noise_reduction']);
    // --- END ---

    update_post_meta($botId, '_aipkit_popup_label_enabled', $sanitized_settings['popup_label_enabled']);
    update_post_meta($botId, '_aipkit_popup_label_text', $sanitized_settings['popup_label_text']);
    update_post_meta($botId, '_aipkit_popup_label_mode', $sanitized_settings['popup_label_mode']);
    update_post_meta($botId, '_aipkit_popup_label_delay_seconds', $sanitized_settings['popup_label_delay_seconds']);
    update_post_meta($botId, '_aipkit_popup_label_auto_hide_seconds', $sanitized_settings['popup_label_auto_hide_seconds']);
    update_post_meta($botId, '_aipkit_popup_label_dismissible', $sanitized_settings['popup_label_dismissible']);
    update_post_meta($botId, '_aipkit_popup_label_frequency', $sanitized_settings['popup_label_frequency']);
    update_post_meta($botId, '_aipkit_popup_label_show_on_mobile', $sanitized_settings['popup_label_show_on_mobile']);
    update_post_meta($botId, '_aipkit_popup_label_show_on_desktop', $sanitized_settings['popup_label_show_on_desktop']);
    update_post_meta($botId, '_aipkit_popup_label_version', $sanitized_settings['popup_label_version']);
    update_post_meta($botId, '_aipkit_popup_label_size', $sanitized_settings['popup_label_size']);

    update_post_meta($botId, '_aipkit_embed_allowed_domains', $sanitized_settings['embed_allowed_domains']);

    $trigger_meta_key = '_aipkit_chatbot_triggers'; // Fallback key
    $trigger_storage_class_name = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';

    if (class_exists($trigger_storage_class_name)) {
        $trigger_meta_key = $trigger_storage_class_name::META_KEY;
    }

    $triggers_json_string = $sanitized_settings['triggers_json'] ?? '[]';
    $decoded_triggers_for_validation = json_decode($triggers_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_trigger_json_meta', __('Invalid JSON format for triggers. Triggers were not saved.', 'gpt3-ai-content-generator'));
    }
    if (!is_array($decoded_triggers_for_validation)) {
        $triggers_json_string = '[]';
    } else {
        // Normalize stored JSON to ensure nested strings (e.g. body_template) are properly escaped.
        $triggers_json_string = wp_json_encode(
            $decoded_triggers_for_validation,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    $trigger_validator_class = '\\WPAICG\\Lib\\Chat\\Triggers\\Validation\\AIPKit_Trigger_Validator';
    if (!class_exists($trigger_validator_class) && defined('WPAICG_LIB_DIR')) {
        $validator_path = WPAICG_LIB_DIR . 'chat/triggers/validation/class-aipkit-trigger-validator.php';
        if (file_exists($validator_path)) {
            require_once $validator_path;
        }
    }
    if (class_exists($trigger_validator_class)) {
        $validation_result = $trigger_validator_class::validate_triggers_array($decoded_triggers_for_validation);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
    }
    update_post_meta($botId, $trigger_meta_key, $triggers_json_string);

    return true;
}

// --- handle-openai-specific-settings-logic.php ---
/**
 * Handles OpenAI-specific settings, such as ensuring global 'store_conversation' is true
 * if the bot's conversation state is enabled.
 *
 * @param int $botId The ID of the bot.
 * @param array $sanitized_settings The array of sanitized settings for the bot.
 * @return void
 */
function handle_openai_specific_settings_logic(int $botId, array $sanitized_settings): void
{
    if (isset($sanitized_settings['provider']) && $sanitized_settings['provider'] === 'OpenAI' &&
        isset($sanitized_settings['openai_conversation_state_enabled']) && $sanitized_settings['openai_conversation_state_enabled'] === '1') {
        if (class_exists(\WPAICG\AIPKit_Providers::class)) {
            $openai_global_settings = AIPKit_Providers::get_provider_data('OpenAI');
            if (($openai_global_settings['store_conversation'] ?? '0') !== '1') {
                $openai_global_settings['store_conversation'] = '1';
                AIPKit_Providers::save_provider_data('OpenAI', $openai_global_settings);
            }
        }
    }
}

// --- save-bot-settings-logic.php ---
// Ensure the new method logic files are loaded

/**
 * Orchestrates the saving of bot settings.
 * MODIFIED: Now handles WP_Error from save_meta_fields_logic and clears site-wide cache
 * only after the updated popup/site-wide meta has been persisted.
 *
 * @param int $botId The chatbot post ID.
 * @param array $raw_settings The raw settings array from the form (e.g., $_POST).
 * @param SiteWideBotManager $site_wide_manager The SiteWideBotManager instance.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function save_bot_settings_logic(int $botId, array $raw_settings, SiteWideBotManager $site_wide_manager) {
    // 1. Validate Bot Post
    $validation_result = validate_bot_post_logic($botId);
    if (is_wp_error($validation_result)) {
        return $validation_result;
    }

    // 2. Sanitize Settings
    $sanitized_settings = sanitize_settings_logic($raw_settings, $botId);

    // 3. Handle Site-Wide Logic (before saving meta, so uniqueness can be enforced first)
    $should_clear_site_wide_cache = handle_site_wide_logic(
        $site_wide_manager,
        $botId,
        $sanitized_settings['site_wide_enabled']
    );

    // 4. Save Meta Fields
    // This function can now return WP_Error
    $meta_save_result = save_meta_fields_logic($botId, $sanitized_settings);
    if (is_wp_error($meta_save_result)) {
        return $meta_save_result; // Propagate WP_Error if JSON validation failed for triggers
    }

    // 5. Handle OpenAI Specific Settings (like forcing global store_conversation)
    handle_openai_specific_settings_logic($botId, $sanitized_settings);

    if ($should_clear_site_wide_cache) {
        $site_wide_manager->clear_site_wide_cache();
    }

    // Action hook after settings are saved
    do_action('aipkit_after_bot_settings_saved', $botId, $sanitized_settings);

    return true;
}
