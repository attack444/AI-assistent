<?php

namespace WPAICG\Chat\Frontend\Shortcode\FeatureManagerMethods;

use WPAICG\AIPKit_Providers;
use WPAICG\aipkit_dashboard;
use WPAICG\Chat\Storage\BotSettingsManager;
use function WPAICG\Core\Providers\OpenRouter\Methods\resolve_model_capabilities_logic;

if (!defined('ABSPATH')) {
    exit;
}

// --- get-core-flag-values.php ---
/**
 * Retrieves core feature flag values directly from bot settings.
 * These are intermediate values used by other flag determination logic.
 *
 * @param array $settings Bot settings array.
 * @return array An array of core flag values.
 */
function get_core_flag_values_logic(array $settings): array {
    if (!class_exists(BotSettingsManager::class)) {
        // This is a critical dependency for defaults. If it's not loaded,
        // the behavior might be unexpected. Consider logging an error.
        // Provide hardcoded fallbacks if class is missing, though this indicates a deeper issue.
        $defaults = [
            'DEFAULT_ENABLE_FULLSCREEN' => '1',
            'DEFAULT_ENABLE_DOWNLOAD' => '0',
            'DEFAULT_ENABLE_CONVERSATION_STARTERS' => '1',
            'DEFAULT_ENABLE_CONVERSATION_SIDEBAR' => '0',
            'DEFAULT_TTS_ENABLED' => '0',
            'DEFAULT_ENABLE_VOICE_INPUT' => '0',
            'DEFAULT_ENABLE_FILE_UPLOAD' => '0',
            'DEFAULT_ENABLE_IMAGE_UPLOAD' => '0',
            'DEFAULT_OPENAI_WEB_SEARCH_ENABLED' => '0',
            'DEFAULT_CLAUDE_WEB_SEARCH_ENABLED' => '0',
            'DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED' => '0',
            'DEFAULT_XAI_WEB_SEARCH_ENABLED' => '0',
            'DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED' => '0',
        ];
    } else {
        $defaults = [
            'DEFAULT_ENABLE_FULLSCREEN' => BotSettingsManager::DEFAULT_ENABLE_FULLSCREEN,
            'DEFAULT_ENABLE_DOWNLOAD' => BotSettingsManager::DEFAULT_ENABLE_DOWNLOAD,
            'DEFAULT_ENABLE_CONVERSATION_STARTERS' => BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_STARTERS,
            'DEFAULT_ENABLE_CONVERSATION_SIDEBAR' => BotSettingsManager::DEFAULT_ENABLE_CONVERSATION_SIDEBAR,
            'DEFAULT_TTS_ENABLED' => BotSettingsManager::DEFAULT_TTS_ENABLED,
            'DEFAULT_ENABLE_VOICE_INPUT' => BotSettingsManager::DEFAULT_ENABLE_VOICE_INPUT,
            'DEFAULT_ENABLE_FILE_UPLOAD' => BotSettingsManager::DEFAULT_ENABLE_FILE_UPLOAD,
            'DEFAULT_ENABLE_IMAGE_UPLOAD' => BotSettingsManager::DEFAULT_ENABLE_IMAGE_UPLOAD,
            'DEFAULT_OPENAI_WEB_SEARCH_ENABLED' => BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_ENABLED,
            'DEFAULT_CLAUDE_WEB_SEARCH_ENABLED' => BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_ENABLED,
            'DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED' => BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED,
            'DEFAULT_XAI_WEB_SEARCH_ENABLED' => BotSettingsManager::DEFAULT_XAI_WEB_SEARCH_ENABLED,
            'DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED' => BotSettingsManager::DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED,
        ];
    }

    return [
        'provider' => isset($settings['provider']) ? sanitize_text_field((string) $settings['provider']) : 'OpenAI',
        'model' => isset($settings['model']) ? sanitize_text_field((string) $settings['model']) : '',
        'vector_store_provider' => isset($settings['vector_store_provider']) ? sanitize_key((string) $settings['vector_store_provider']) : 'openai',
        // Directly derived flags (boolean)
        'popup_enabled'      => ($settings['popup_enabled'] ?? '0') === '1',
        'enable_fullscreen'  => ($settings['enable_fullscreen'] ?? $defaults['DEFAULT_ENABLE_FULLSCREEN']) === '1',
        'enable_download'    => ($settings['enable_download'] ?? $defaults['DEFAULT_ENABLE_DOWNLOAD']) === '1',
        'enable_copy_button' => true,
        'enable_feedback'    => true,
        'enable_voice_input_ui' => ($settings['enable_voice_input'] ?? $defaults['DEFAULT_ENABLE_VOICE_INPUT']) === '1', // Direct UI flag

        // Intermediate setting values (to be combined with addon status)
        'enable_starters_setting' => ($settings['enable_conversation_starters'] ?? $defaults['DEFAULT_ENABLE_CONVERSATION_STARTERS']) === '1',
        'enable_sidebar_setting'  => ($settings['enable_conversation_sidebar'] ?? $defaults['DEFAULT_ENABLE_CONVERSATION_SIDEBAR']) === '1',
        'enable_tts_setting'      => ($settings['tts_enabled'] ?? $defaults['DEFAULT_TTS_ENABLED']) === '1',
        'enable_file_upload_setting'  => ($settings['enable_file_upload'] ?? $defaults['DEFAULT_ENABLE_FILE_UPLOAD']) === '1',
        'enable_image_upload_setting' => ($settings['enable_image_upload'] ?? $defaults['DEFAULT_ENABLE_IMAGE_UPLOAD']) === '1',
        'enable_realtime_voice_setting' => ($settings['enable_realtime_voice'] ?? '0') === '1',
        'allow_openai_web_search_tool_setting'  => ($settings['openai_web_search_enabled'] ?? $defaults['DEFAULT_OPENAI_WEB_SEARCH_ENABLED']) === '1',
        'allow_claude_web_search_tool_setting'  => ($settings['claude_web_search_enabled'] ?? $defaults['DEFAULT_CLAUDE_WEB_SEARCH_ENABLED']) === '1',
        'allow_openrouter_web_search_tool_setting'  => ($settings['openrouter_web_search_enabled'] ?? $defaults['DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED']) === '1',
        'allow_xai_web_search_tool_setting'  => ($settings['xai_web_search_enabled'] ?? $defaults['DEFAULT_XAI_WEB_SEARCH_ENABLED']) === '1',
        'allow_google_search_grounding_setting' => ($settings['google_search_grounding_enabled'] ?? $defaults['DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED']) === '1',
    ];
}

// --- get-ui-flags.php ---
/**
 * Determines UI-related feature flags based on intermediate core settings and plan status.
 *
 * @param array $core_flags An array of intermediate flags from get_core_flag_values_logic.
 *                          Expected keys: 'enable_starters_setting', 'enable_sidebar_setting',
 *                                         'popup_enabled', 'enable_download', 'enable_tts_setting'.
 * @param bool $is_pro_plan Whether the current user is on a Pro plan.
 * @return array An array of UI feature flags:
 *               'starters_ui_enabled', 'sidebar_ui_enabled', 'pdf_ui_enabled', 'tts_ui_enabled'.
 */
function get_ui_flags_logic(array $core_flags, bool $is_pro_plan): array {
    $ui_flags = [];

    $ui_flags['starters_ui_enabled'] = ($core_flags['enable_starters_setting'] ?? false);

    $ui_flags['sidebar_ui_enabled']  = ($core_flags['enable_sidebar_setting'] ?? false) &&
                                      !($core_flags['popup_enabled'] ?? false); // Sidebar disabled in popup mode

    $ui_flags['pdf_ui_enabled']      = ($core_flags['enable_download'] ?? false) && $is_pro_plan;

    $ui_flags['tts_ui_enabled']      = ($core_flags['enable_tts_setting'] ?? false);

    // Note: 'feedback_ui_enabled' and 'enable_voice_input_ui' are taken directly
    // from $core_flags in the main orchestrator.

    return $ui_flags;
}

// --- get-upload-flags.php ---
/**
 * Determines file/image upload related feature flags.
 *
 * @param array $core_flags An array of intermediate flags from get_core_flag_values_logic.
 *                          Expected keys: 'provider', 'enable_file_upload_setting', 'enable_image_upload_setting'.
 * @return array An array of upload feature flags:
 *               'file_upload_ui_enabled', 'image_upload_ui_enabled', 'input_action_button_enabled'.
 */
function get_upload_flags_logic(array $core_flags): array {
    $upload_flags = [];
    $is_pro = false;
    // Ensure aipkit_dashboard class is loaded before calling its static methods
    if (!class_exists(aipkit_dashboard::class)) {
        $dashboard_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_dashboard.php';
        if (file_exists($dashboard_path)) {
            require_once $dashboard_path;
        }
    }

    if (class_exists(aipkit_dashboard::class)) {
        $is_pro = aipkit_dashboard::is_pro_plan();
    }

    $provider = isset($core_flags['provider']) ? sanitize_text_field((string) $core_flags['provider']) : 'OpenAI';
    $model = isset($core_flags['model']) ? sanitize_text_field((string) $core_flags['model']) : '';
    $vector_store_provider = isset($core_flags['vector_store_provider']) ? sanitize_key((string) $core_flags['vector_store_provider']) : 'openai';
    $default_image_upload_supported_providers = ['OpenAI', 'Claude', 'OpenRouter', 'xAI'];
    $image_upload_supported_providers = apply_filters(
        'aipkit_chat_image_upload_supported_providers',
        $default_image_upload_supported_providers,
        $core_flags,
        $is_pro
    );
    if (!is_array($image_upload_supported_providers)) {
        $image_upload_supported_providers = $default_image_upload_supported_providers;
    } else {
        $image_upload_supported_providers = array_values(array_filter(array_map(
            static fn($item) => is_string($item) ? sanitize_text_field($item) : '',
            $image_upload_supported_providers
        )));
        if (empty($image_upload_supported_providers)) {
            $image_upload_supported_providers = $default_image_upload_supported_providers;
        }
    }
    $is_image_upload_supported_provider = in_array($provider, $image_upload_supported_providers, true);
    $claude_files_compatible = !($vector_store_provider === 'claude_files' && $provider !== 'Claude');

    if ($provider === 'OpenRouter' && $is_image_upload_supported_provider && $model !== '') {
        $resolver_fn = 'WPAICG\\Core\\Providers\\OpenRouter\\Methods\\resolve_model_capabilities_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }
        if (function_exists($resolver_fn)) {
            $capabilities = resolve_model_capabilities_logic($model);
            $is_image_upload_supported_provider = !empty($capabilities['image_input']);
        }
    }
    if ($provider === 'xAI' && $is_image_upload_supported_provider && $model !== '') {
        if (!class_exists(AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            }
        }
        if (class_exists(AIPKit_Providers::class)) {
            $is_image_upload_supported_provider = AIPKit_Providers::xai_model_supports_image_input($model);
        }
    }

    /**
     * Final gate for image-upload availability by provider/model on frontend.
     *
     * Allows paid integrations to enable/disable support without adding
     * provider-specific logic in core frontend classes.
     *
     * @param bool  $is_image_upload_supported_provider Current support decision.
     * @param array $context Gate context (provider, model, core_flags, is_pro_plan).
     */
    $is_image_upload_supported_provider = (bool) apply_filters(
        'aipkit_chat_image_upload_model_supported',
        $is_image_upload_supported_provider,
        [
            'provider' => $provider,
            'model' => $model,
            'core_flags' => $core_flags,
            'is_pro_plan' => $is_pro,
            'vector_store_provider' => $vector_store_provider,
        ]
    );

    // File upload UI is enabled if the setting is on AND it's a Pro feature
    $upload_flags['file_upload_ui_enabled'] = ($core_flags['enable_file_upload_setting'] ?? false) && $is_pro && $claude_files_compatible;
    // Image upload UI is enabled only for providers with image-analysis support.
    $upload_flags['image_upload_ui_enabled'] = ($core_flags['enable_image_upload_setting'] ?? false) && $is_image_upload_supported_provider;

    $upload_flags['input_action_button_enabled'] = $upload_flags['file_upload_ui_enabled'] ||
                                                 $upload_flags['image_upload_ui_enabled'];

    return $upload_flags;
}

// --- get-web-search-flag.php ---
/**
 * Determines the 'allowWebSearchTool' feature flag.
 *
 * @param array $settings Bot settings array (needs 'provider').
 * @param bool $allow_openai_web_search_tool_setting Intermediate OpenAI flag value from core flags.
 * @param bool $allow_claude_web_search_tool_setting Intermediate Claude flag value from core flags.
 * @param bool $allow_openrouter_web_search_tool_setting Intermediate OpenRouter flag value from core flags.
 * @param bool $allow_xai_web_search_tool_setting Intermediate xAI flag value from core flags.
 * @return array An array containing the 'allowWebSearchTool' flag.
 */
function get_web_search_flag_logic(
    array $settings,
    bool $allow_openai_web_search_tool_setting,
    bool $allow_claude_web_search_tool_setting,
    bool $allow_openrouter_web_search_tool_setting,
    bool $allow_xai_web_search_tool_setting
): array {
    $provider = $settings['provider'] ?? 'OpenAI';
    $allow_web_search_tool = false;
    if ($provider === 'OpenAI') {
        $allow_web_search_tool = $allow_openai_web_search_tool_setting;
    } elseif ($provider === 'Claude') {
        $allow_web_search_tool = $allow_claude_web_search_tool_setting;
    } elseif ($provider === 'OpenRouter') {
        $allow_web_search_tool = $allow_openrouter_web_search_tool_setting;
        $model = isset($settings['model']) ? sanitize_text_field((string) $settings['model']) : '';
        if ($allow_web_search_tool && $model !== '') {
            $resolver_fn = 'WPAICG\\Core\\Providers\\OpenRouter\\Methods\\resolve_model_capabilities_logic';
            if (!function_exists($resolver_fn)) {
                $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
                if (file_exists($capability_file)) {
                    require_once $capability_file;
                }
            }
            if (function_exists($resolver_fn)) {
                $capabilities = resolve_model_capabilities_logic($model);
                $allow_web_search_tool = !empty($capabilities['web_search_plugin']);
            }
        }
    } elseif ($provider === 'xAI') {
        $allow_web_search_tool = $allow_xai_web_search_tool_setting;
    }

    return [
        'allowWebSearchTool' => $allow_web_search_tool,
    ];
}

// --- get-google-grounding-flags.php ---
/**
 * Determines Google Search Grounding related feature flags.
 *
 * @param array $settings Bot settings array (needs 'provider', 'google_grounding_mode',
 *                        'google_grounding_dynamic_threshold').
 * @param bool $allow_google_search_grounding_setting Intermediate flag value from core flags.
 * @return array An array containing Google Search Grounding flags:
 *               'allowGoogleSearchGrounding', 'googleGroundingMode', 'googleGroundingDynamicThreshold'.
 */
function get_google_grounding_flags_logic(array $settings, bool $allow_google_search_grounding_setting): array {
    $grounding_flags = [];

    if (!class_exists(BotSettingsManager::class)) {
        // Fallback if BotSettingsManager is not available for defaults
        $defaults = [
            'DEFAULT_GOOGLE_GROUNDING_MODE' => 'DEFAULT_MODE',
            'DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD' => 0.3,
        ];
    } else {
        $defaults = [
            'DEFAULT_GOOGLE_GROUNDING_MODE' => BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE,
            'DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD' => BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD,
        ];
    }


    $grounding_flags['allowGoogleSearchGrounding'] = ($settings['provider'] ?? 'OpenAI') === 'Google' &&
                                                   $allow_google_search_grounding_setting;

    if ($grounding_flags['allowGoogleSearchGrounding']) {
        $grounding_flags['googleGroundingMode'] = $settings['google_grounding_mode'] ?? $defaults['DEFAULT_GOOGLE_GROUNDING_MODE'];
        if ($grounding_flags['googleGroundingMode'] === 'MODE_DYNAMIC') {
            $grounding_flags['googleGroundingDynamicThreshold'] = isset($settings['google_grounding_dynamic_threshold'])
                                                                  ? floatval($settings['google_grounding_dynamic_threshold'])
                                                                  : $defaults['DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD'];
        } else {
            // Set a default or null if mode is not dynamic, to ensure the key exists if expected.
            $grounding_flags['googleGroundingDynamicThreshold'] = null;
        }
    } else {
        // Ensure keys exist even if grounding is not allowed, with default/null values.
        $grounding_flags['googleGroundingMode'] = null;
        $grounding_flags['googleGroundingDynamicThreshold'] = null;
    }

    return $grounding_flags;
}

// --- get-realtime-voice-flag.php ---
/**
 * Determines the 'enable_realtime_voice_ui' feature flag.
 *
 * @param array $core_flags An array of intermediate flags from get_core_flag_values_logic.
 * @return array An array containing the 'enable_realtime_voice_ui' flag.
 */
function get_realtime_voice_flag_logic(array $core_flags): array
{
    $is_pro = false;

    if (class_exists(aipkit_dashboard::class)) {
        $is_pro = aipkit_dashboard::is_pro_plan();
    }

    return [
        'enable_realtime_voice_ui' => ($core_flags['enable_realtime_voice_setting'] ?? false) && $is_pro,
    ];
}

// --- compute-derived-flags.php ---
/**
 * Computes derived feature flags based on already determined flags.
 *
 * @param array $current_flags An array of already computed flags.
 *                             Expected keys: 'popup_enabled', 'enable_fullscreen',
 *                                            'enable_download', 'sidebar_ui_enabled'.
 * @return array An array containing derived flags, e.g., 'show_header'.
 */
function compute_derived_flags_logic(array $current_flags): array {
    $derived_flags = [];

    $derived_flags['show_header'] = ($current_flags['popup_enabled'] ?? false) ||
                                  ($current_flags['enable_fullscreen'] ?? false) ||
                                  ($current_flags['enable_download'] ?? false) ||
                                  ($current_flags['sidebar_ui_enabled'] ?? false);
    return $derived_flags;
}
