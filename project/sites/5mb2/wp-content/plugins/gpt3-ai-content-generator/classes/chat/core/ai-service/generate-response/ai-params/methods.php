<?php

namespace WPAICG\Chat\Core\AIService\GenerateResponse\AiParams;

use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\AIPKit_Providers; // For checking provider data existence if needed.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- apply-openai-stateful-conversation.php ---
/**
 * Applies OpenAI stateful conversation parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array &$messages_payload_ref Reference to the messages payload array (can be modified).
 * @param array $bot_settings Bot settings.
 * @param string|null $frontend_previous_openai_response_id Previous OpenAI response ID from frontend.
 * @param string|null $last_openai_response_id_from_history Last OpenAI response ID from history.
 * @return string|null The actual previous response ID used, or null.
 */
function apply_openai_stateful_conversation_logic(
    array &$final_ai_params,
    array &$messages_payload_ref,
    array $bot_settings,
    ?string $frontend_previous_openai_response_id,
    ?string $last_openai_response_id_from_history
): ?string {
    $actual_previous_response_id_to_use = null;
    $use_openai_conv_state = ($bot_settings['openai_conversation_state_enabled'] ?? '0') === '1';

    if ($use_openai_conv_state) {
        $final_ai_params['use_openai_conversation_state'] = true;
        if (!empty($frontend_previous_openai_response_id)) {
            $actual_previous_response_id_to_use = $frontend_previous_openai_response_id;
        } elseif (!empty($last_openai_response_id_from_history)) {
            $actual_previous_response_id_to_use = $last_openai_response_id_from_history;
        }

        if ($actual_previous_response_id_to_use !== null) {
            $final_ai_params['previous_response_id'] = $actual_previous_response_id_to_use;
            $latest_user_message_obj = end($messages_payload_ref);
            if ($latest_user_message_obj && ($latest_user_message_obj['role'] === 'user')) {
                $messages_payload_ref = [$latest_user_message_obj];
            }
        }
    }
    return $actual_previous_response_id_to_use;
}

// --- apply-openai-vector-tool-config.php ---
/**
 * Applies OpenAI Vector Store tool configuration to AI parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 */
function apply_openai_vector_tool_config_logic(&$final_ai_params, $bot_settings, $vector_store_ids_to_use_for_tool, $ai_service)
{
    // Vector store IDs should be prepared by the caller; just normalize here
    $vector_store_ids_to_use_for_tool = array_unique(array_filter($vector_store_ids_to_use_for_tool));
    $vector_top_k_openai = absint($bot_settings['vector_store_top_k'] ?? 3);
    $vector_top_k_openai = max(1, min($vector_top_k_openai, 20));

    if (($bot_settings['enable_vector_store'] ?? '0') === '1' &&
        ($bot_settings['vector_store_provider'] ?? '') === 'openai' &&
        !empty($vector_store_ids_to_use_for_tool)) {

        // Convert confidence threshold percentage (0-100) to OpenAI score threshold (0.0-1.0)
        // OpenAI expects ranking_options.score_threshold in the file_search tool for server-side filtering
        $confidence_threshold_percent = (int)($bot_settings['vector_store_confidence_threshold'] ?? 20);
        // Convert to 0.0-1.0 scale and round to fixed 6 decimals, with exact endpoints for 0 and 100
        if ($confidence_threshold_percent <= 0) {
            $openai_score_threshold = 0.0;
        } elseif ($confidence_threshold_percent >= 100) {
            $openai_score_threshold = 1.0;
        } else {
            $openai_score_threshold = round($confidence_threshold_percent / 100, 6);
        }

        $final_ai_params['vector_store_tool_config'] = [
            'type'             => 'file_search',
            'vector_store_ids' => $vector_store_ids_to_use_for_tool,
            'max_num_results'  => $vector_top_k_openai,
            'ranking_options'  => [
                'score_threshold' => $openai_score_threshold
            ]
        ];
    }
}

// --- apply-openai-web-search.php ---
/**
 * Applies OpenAI Web Search tool configuration to AI parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 * @param bool $frontend_openai_web_search_active Flag for OpenAI web search.
 */
function apply_openai_web_search_logic(
    array &$final_ai_params,
    array $bot_settings,
    bool $frontend_openai_web_search_active
): void {
    // Ensure BotSettingsManager constants are available
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        } else {
            return;
        }
    }

    $bot_allows_openai_web_search = (isset($bot_settings['openai_web_search_enabled']) && $bot_settings['openai_web_search_enabled'] === '1');

    if ($bot_allows_openai_web_search) {
        $final_ai_params['web_search_tool_config'] = [
            'enabled' => true,
            'search_context_size' => $bot_settings['openai_web_search_context_size'] ?? BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE,
        ];
        if (($bot_settings['openai_web_search_loc_type'] ?? 'none') === 'approximate') {
            $user_location = array_filter([
                'country' => $bot_settings['openai_web_search_loc_country'] ?? null,
                'city' => $bot_settings['openai_web_search_loc_city'] ?? null,
                'region' => $bot_settings['openai_web_search_loc_region'] ?? null,
                'timezone' => $bot_settings['openai_web_search_loc_timezone'] ?? null
            ]);
            if (!empty($user_location)) {
                $final_ai_params['web_search_tool_config']['user_location'] = $user_location;
            }
        }
        $final_ai_params['frontend_web_search_active'] = $frontend_openai_web_search_active;
    }
}

// --- apply-claude-web-search.php ---
/**
 * Applies Claude Web Search tool configuration to AI parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 * @param bool $frontend_web_search_active Flag for frontend web search toggle.
 */
function apply_claude_web_search_logic(
    array &$final_ai_params,
    array $bot_settings,
    bool $frontend_web_search_active
): void {
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        } else {
            return;
        }
    }

    $bot_allows_claude_web_search = (isset($bot_settings['claude_web_search_enabled']) && $bot_settings['claude_web_search_enabled'] === '1');
    if (!$bot_allows_claude_web_search) {
        return;
    }

    $split_domains = static function ($domains_raw): array {
        if (!is_string($domains_raw) || trim($domains_raw) === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,]+/', $domains_raw);
        if (!is_array($parts)) {
            return [];
        }
        $domains = array_values(array_filter(array_map(static function ($part) {
            $domain = strtolower(trim((string) $part));
            if ($domain === '') {
                return '';
            }
            $domain = preg_replace('/^https?:\/\//', '', $domain);
            $domain = trim((string) $domain, " \t\n\r\0\x0B/");
            if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
                return '';
            }
            return $domain;
        }, $parts)));
        return array_values(array_unique($domains));
    };

    $web_search_config = [
        'enabled' => true,
        'type' => 'web_search_20250305',
    ];

    $max_uses = isset($bot_settings['claude_web_search_max_uses'])
        ? absint($bot_settings['claude_web_search_max_uses'])
        : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES;
    $web_search_config['max_uses'] = max(1, min($max_uses, 20));

    $allowed_domains = $split_domains($bot_settings['claude_web_search_allowed_domains'] ?? '');
    $blocked_domains = $split_domains($bot_settings['claude_web_search_blocked_domains'] ?? '');
    if (!empty($allowed_domains)) {
        $web_search_config['allowed_domains'] = $allowed_domains;
    } elseif (!empty($blocked_domains)) {
        $web_search_config['blocked_domains'] = $blocked_domains;
    }

    if (($bot_settings['claude_web_search_loc_type'] ?? 'none') === 'approximate') {
        $user_location = array_filter([
            'country' => $bot_settings['claude_web_search_loc_country'] ?? null,
            'city' => $bot_settings['claude_web_search_loc_city'] ?? null,
            'region' => $bot_settings['claude_web_search_loc_region'] ?? null,
            'timezone' => $bot_settings['claude_web_search_loc_timezone'] ?? null,
        ]);
        if (!empty($user_location)) {
            $user_location['type'] = 'approximate';
            $web_search_config['user_location'] = $user_location;
        }
    }

    $cache_ttl = $bot_settings['claude_web_search_cache_ttl'] ?? BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL;
    if (in_array($cache_ttl, ['5m', '1h'], true)) {
        $web_search_config['cache_control'] = [
            'type' => 'ephemeral',
            'ttl' => $cache_ttl,
        ];
    }

    $final_ai_params['web_search_tool_config'] = $web_search_config;
    $final_ai_params['frontend_web_search_active'] = $frontend_web_search_active;
}

// --- apply-openrouter-web-search.php ---
/**
 * Applies OpenRouter web search plugin configuration to AI parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 * @param bool $frontend_web_search_active Flag for frontend web search toggle.
 */
function apply_openrouter_web_search_logic(
    array &$final_ai_params,
    array $bot_settings,
    bool $frontend_web_search_active
): void {
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        } else {
            return;
        }
    }

    $bot_allows_openrouter_web_search = (isset($bot_settings['openrouter_web_search_enabled']) && $bot_settings['openrouter_web_search_enabled'] === '1');
    if (!$bot_allows_openrouter_web_search) {
        return;
    }

    $web_search_config = [
        'enabled' => true,
    ];

    $engine = isset($bot_settings['openrouter_web_search_engine']) ? sanitize_key((string) $bot_settings['openrouter_web_search_engine']) : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE;
    if (in_array($engine, ['native', 'exa'], true)) {
        $web_search_config['engine'] = $engine;
    }

    $max_results = isset($bot_settings['openrouter_web_search_max_results'])
        ? absint($bot_settings['openrouter_web_search_max_results'])
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS;
    $web_search_config['max_results'] = max(1, min($max_results, 10));

    $search_prompt = isset($bot_settings['openrouter_web_search_search_prompt'])
        ? AIPKit_Prompt_Sanitizer::sanitize($bot_settings['openrouter_web_search_search_prompt'])
        : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_SEARCH_PROMPT;
    if ($search_prompt !== '') {
        $web_search_config['search_prompt'] = $search_prompt;
    }

    $final_ai_params['web_search_tool_config'] = $web_search_config;
    $final_ai_params['frontend_web_search_active'] = $frontend_web_search_active;
}

// --- apply-xai-web-search.php ---
/**
 * Applies xAI Responses API web search parameters.
 *
 * @param array<string, mixed> $final_ai_params
 * @param array<string, mixed> $bot_settings
 * @param bool $frontend_web_search_active
 */
function apply_xai_web_search_logic(array &$final_ai_params, array $bot_settings, bool $frontend_web_search_active): void
{
    if (($bot_settings['xai_web_search_enabled'] ?? '0') !== '1') {
        return;
    }

    $final_ai_params['xai_web_search_tool_config'] = ['enabled' => true];
    $final_ai_params['frontend_web_search_active'] = $frontend_web_search_active;
}

// --- apply-google-search-grounding.php ---
/**
 * Applies Google Search Grounding parameters.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 * @param bool $frontend_google_search_grounding_active Flag for Google Search Grounding.
 */
function apply_google_search_grounding_logic(
    array &$final_ai_params,
    array $bot_settings,
    bool $frontend_google_search_grounding_active
): void {
    // Ensure BotSettingsManager constants are available
    if (!class_exists(BotSettingsManager::class)) {
        $bsm_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($bsm_path)) {
            require_once $bsm_path;
        } else {
            return;
        }
    }

    $bot_allows_google_grounding = (isset($bot_settings['google_search_grounding_enabled']) && $bot_settings['google_search_grounding_enabled'] === '1');

    if ($bot_allows_google_grounding) {
        $final_ai_params['google_grounding_mode'] = $bot_settings['google_grounding_mode'] ?? BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
        if ($final_ai_params['google_grounding_mode'] === 'MODE_DYNAMIC') {
            $final_ai_params['google_grounding_dynamic_threshold'] = isset($bot_settings['google_grounding_dynamic_threshold']) ? floatval($bot_settings['google_grounding_dynamic_threshold']) : BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;
        }
        $final_ai_params['frontend_google_search_grounding_active'] = $frontend_google_search_grounding_active;
    }
}

// --- apply-openai-reasoning.php ---
/**
 * Applies OpenAI Reasoning parameters if the model is compatible.
 *
 * @param array &$final_ai_params Reference to the final AI parameters array to be modified.
 * @param array $bot_settings Bot settings.
 * @param string $model The selected AI model name.
 */
function apply_openai_reasoning_logic(
    array &$final_ai_params,
    array $bot_settings,
    string $model
): void {
    $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
        (string) $model,
        $bot_settings['reasoning_effort'] ?? ''
    );

    if ($reasoning_effort !== '') {
        $final_ai_params['reasoning'] = ['effort' => $reasoning_effort];
    }
}

// --- apply-ollama-thinking.php ---
/**
 * Apply Ollama thinking controls using the existing reasoning setting.
 *
 * The provider strategy maps this normalized effort into Ollama's `think`
 * payload shape, including GPT-OSS level-based values.
 *
 * @param array<string, mixed> $final_ai_params
 * @param array<string, mixed> $bot_settings
 * @return void
 */
function apply_ollama_thinking_logic(array &$final_ai_params, array $bot_settings): void
{
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($bot_settings['reasoning_effort'] ?? '');
    if ($reasoning_effort === '' || $reasoning_effort === 'none') {
        return;
    }

    $final_ai_params['reasoning'] = ['effort' => $reasoning_effort];
}
