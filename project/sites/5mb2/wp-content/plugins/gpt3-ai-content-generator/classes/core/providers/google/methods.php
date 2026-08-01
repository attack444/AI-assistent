<?php
namespace WPAICG\Core\Providers\Google\Methods;

use WPAICG\AIPKit_Providers;
use WPAICG\AIPKit_Role_Manager;
use WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory;
use WP_Error;
use WPAICG\Core\Providers\GoogleProviderStrategy; 
use WPAICG\Core\Providers\Google\GoogleUrlBuilder; 
use WPAICG\Core\Providers\Google\GooglePayloadFormatter; 
use WPAICG\Core\Providers\Google\GoogleResponseParser;
use function WPAICG\Core\Providers\Shared\extract_sse_event_blocks;
use function WPAICG\Core\Providers\Shared\decode_data_only_sse_event_block;

if (!defined('ABSPATH')) {
    exit;
}

// --- _shared-format.php ---
/**
 * Shared formatting logic, previously a private static method in GooglePayloadFormatter.
 * UPDATED: Adds grounding tools to payload if active.
 * FIXED: Correctly maps internal 'bot' role to 'model' for Google API.
 * FIXED: Correctly assigns 'google_search' or 'google_search_retrieval' tool based on model family.
 * FIXED: Exclude messages with original role 'system' from Google's 'contents' array.
 *
 * @param string $instructions System instructions.
 * @param array  $history Conversation history (roles: 'user', 'model', or plugin's internal 'bot', 'system').
 * @param array  $ai_params AI parameters, can include 'google_search_grounding_active', 'google_grounding_mode', 'google_grounding_dynamic_threshold', 'model_id_for_grounding'.
 * @return array The formatted payload base.
 */
function _shared_format_logic(string $instructions, array $history, array $ai_params): array {
    $contents = [];

    foreach ($history as $msg) {
        // --- MODIFICATION START: Skip 'system' role messages from history for Google 'contents' ---
        if (isset($msg['role']) && $msg['role'] === 'system') {
            // These are internal logs (like trigger logs) or system messages
            // not meant for direct inclusion in the user/model turns for Google.
            // The main AI system instruction is handled separately.
            continue;
        }
        // --- MODIFICATION END ---

        $role = ($msg['role'] === 'bot' || $msg['role'] === 'assistant') ? 'model' : 'user';
        
        $content_parts = [];
        if (isset($msg['content']) && is_array($msg['content'])) { 
            foreach ($msg['content'] as $part) {
                if (isset($part['type'])) {
                    if ($part['type'] === 'text' || $part['type'] === 'input_text') {
                        $content_parts[] = ['text' => $part['text'] ?? ''];
                    } elseif (($part['type'] === 'image_url' || $part['type'] === 'input_image') && isset($part['image_url'])) {
                        if (is_string($part['image_url']) && strpos($part['image_url'], 'data:') === 0) {
                            list($type, $data) = explode(';', $part['image_url']);
                            list(, $data)      = explode(',', $data);
                            $mime_type = (string) substr($type, 5);
                            $content_parts[] = ['inline_data' => ['mime_type' => $mime_type, 'data' => $data]];
                        } else if (is_array($part['image_url']) && isset($part['image_url']['url']) && strpos($part['image_url']['url'], 'data:') === 0){
                            list($type, $data) = explode(';', $part['image_url']['url']);
                            list(, $data)      = explode(',', $data);
                            $mime_type = (string) substr($type, 5);
                            $content_parts[] = ['inline_data' => ['mime_type' => $mime_type, 'data' => $data]];
                        }
                    }
                }
            }
        } elseif (isset($msg['content']) && is_string($msg['content'])) {
            $content_parts[] = ['text' => trim($msg['content'])];
        }

        if (!empty($content_parts)) {
            // Ensure role is either 'user' or 'model' before adding to contents
            if ($role === 'user' || $role === 'model') {
                $contents[] = ['role' => $role, 'parts' => $content_parts];
            }
        }
    }

    if (!empty($ai_params['image_inputs']) && is_array($ai_params['image_inputs'])) {
        $image_parts = [];
        foreach ($ai_params['image_inputs'] as $image_input) {
            if (!is_array($image_input)) {
                continue;
            }

            $mime_type = isset($image_input['type']) ? sanitize_text_field((string) $image_input['type']) : '';
            $base64_data = isset($image_input['base64']) ? trim((string) $image_input['base64']) : '';
            if ($mime_type === '' || $base64_data === '') {
                continue;
            }

            $image_parts[] = [
                'inline_data' => [
                    'mime_type' => $mime_type,
                    'data' => $base64_data,
                ],
            ];
        }

        if (!empty($image_parts)) {
            $last_user_index = null;
            for ($i = count($contents) - 1; $i >= 0; $i--) {
                if (($contents[$i]['role'] ?? '') === 'user') {
                    $last_user_index = $i;
                    break;
                }
            }

            if ($last_user_index !== null) {
                $contents[$last_user_index]['parts'] = array_merge($contents[$last_user_index]['parts'], $image_parts);
            } else {
                $contents[] = ['role' => 'user', 'parts' => $image_parts];
            }
        }
    }

    $cleaned_contents = [];
    $last_role = null;
    foreach ($contents as $msg) {
        if ($msg['role'] !== $last_role) {
            $cleaned_contents[] = $msg;
            $last_role = $msg['role'];
        } else {
            $last_index = count($cleaned_contents) - 1;
            if ($last_index >= 0 && $cleaned_contents[$last_index]['role'] === $msg['role']) {
                $cleaned_contents[$last_index]['parts'] = array_merge($cleaned_contents[$last_index]['parts'], $msg['parts']);
            } else {
                $cleaned_contents[] = $msg;
                $last_role = $msg['role'];
            }
        }
    }

    $body_data = ['contents' => $cleaned_contents];

    if (!empty($instructions)) {
        $body_data['system_instruction'] = ['parts' => [['text' => $instructions]]];
    }

    $generationConfig = [];
    $param_map = [
        'temperature' => 'temperature',
        'max_completion_tokens' => 'maxOutputTokens',
        'top_p' => 'topP',
        'stop' => 'stopSequences',
    ];
    foreach ($param_map as $aipkit_key => $api_key) {
        if (isset($ai_params[$aipkit_key])) {
            $value = $ai_params[$aipkit_key];
            if ($api_key === 'temperature' || $api_key === 'topP') {
                $generationConfig[$api_key] = floatval($value);
            } elseif ($api_key === 'maxOutputTokens') {
                $generationConfig[$api_key] = absint($value);
            } elseif ($api_key === 'stopSequences' && !empty($value)) {
                $generationConfig[$api_key] = is_string($value) ? [$value] : (is_array($value) ? $value : null);
                if (empty($generationConfig[$api_key])) unset($generationConfig[$api_key]);
            }
        }
    }
    if (!empty($generationConfig)) $body_data['generationConfig'] = $generationConfig;

    if (isset($ai_params['safety_settings']) && is_array($ai_params['safety_settings'])) {
        $body_data['safetySettings'] = $ai_params['safety_settings'];
    }

    $google_search_grounding_active = $ai_params['frontend_google_search_grounding_active'] ?? false;
    $model_name_for_grounding = strtolower((string) ($ai_params['model_id_for_grounding'] ?? ''));

    if ($google_search_grounding_active) {
        $tools = [];
        // Gemini 1.5 Flash still uses the legacy retrieval tool; current Gemini families use google_search.
        if (strpos($model_name_for_grounding, 'gemini-1.5-flash') !== false) {
            $grounding_mode = $ai_params['google_grounding_mode'] ?? 'DEFAULT_MODE';
            if ($grounding_mode === 'MODE_DYNAMIC') {
                $dynamic_threshold = $ai_params['google_grounding_dynamic_threshold'] ?? 0.3;
                $tools[] = [
                    'google_search_retrieval' => [
                        'dynamic_retrieval_config' => [
                            'mode' => 'MODE_DYNAMIC',
                            'dynamic_threshold' => floatval($dynamic_threshold),
                        ]
                    ]
                ];
            } else { 
                $tools[] = ['google_search_retrieval' => new \stdClass()];
            }
        } elseif (strpos($model_name_for_grounding, 'gemini') !== false) {
            $tools[] = ['google_search' => new \stdClass()];
        }
        
        if (!empty($tools)) {
            $body_data['tools'] = $tools;
        }
    }
    return $body_data;
}

// --- ajax-sync-tts-voices.php ---
/**
 * Logic for the ajax_sync_google_tts_voices static method of GoogleSettingsHandler.
 *
 * @param string $option_name The name of the WordPress option to store synced voices.
 */
function ajax_sync_google_tts_voices_logic(string $option_name) {
    if (!\WPAICG\AIPKit_Role_Manager::user_can_access_module('settings')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'gpt3-ai-content-generator')], 403);
        return;
    }
    if (!check_ajax_referer('aipkit_nonce', '_ajax_nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
         wp_send_json_error(['message' => __('Provider configuration missing.', 'gpt3-ai-content-generator')], 500);
         return;
    }
    $google_data = \WPAICG\AIPKit_Providers::get_provider_data('Google');
    $api_key = $google_data['api_key'] ?? null;
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('Google API key is required to sync voices.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    if (!class_exists(\WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory::class)) {
        wp_send_json_error(['message' => __('TTS components missing.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    $strategy = \WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory::get_strategy('Google');
    if (is_wp_error($strategy)) {
        wp_send_json_error(['message' => $strategy->get_error_message()], 500);
        return;
    }

    $voices = $strategy->get_voices(['api_key' => $api_key]);
    if (is_wp_error($voices)) {
         $error_data = $voices->get_error_data();
         $status_code = isset($error_data['status']) ? (int)$error_data['status'] : 500;
         wp_send_json_error(['message' => $voices->get_error_message()], $status_code);
        return;
    }

    update_option($option_name, $voices, 'no');

    wp_send_json_success([
        'message' => __('Sync ok.', 'gpt3-ai-content-generator'),
        'voices'  => $voices
    ]);
}

// --- build-api-url.php ---
/**
 * Logic for the build_api_url method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $operation ('chat', 'models', 'stream', 'embedContent', 'batchEmbedContents')
 * @param array  $params Required parameters (api_key, base_url, api_version, model) and optional (pageSize, pageToken).
 * @return string|WP_Error The full URL or WP_Error.
 */
function build_api_url_logic(GoogleProviderStrategy $strategyInstance, string $operation, array $params) {
    if (!class_exists(\WPAICG\Core\Providers\Google\GoogleUrlBuilder::class)) {
        $url_builder_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($url_builder_bootstrap)) {
            require_once $url_builder_bootstrap;
        } else {
            return new WP_Error('google_url_builder_missing_logic', 'Google URL builder component is not available.');
        }
    }
    return \WPAICG\Core\Providers\Google\GoogleUrlBuilder::build($operation, $params);
}

// --- build-sse-payload.php ---
/**
 * Logic for the build_sse_payload method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $messages Formatted messages/input/contents array.
 * @param string|array|null $system_instruction Formatted system instruction.
 * @param array $ai_params AI parameters.
 * @param string $model Target model.
 * @return array The formatted request body data for SSE.
 */
function build_sse_payload_logic(
    GoogleProviderStrategy $strategyInstance,
    array $messages,
    $system_instruction,
    array $ai_params,
    string $model
): array {
    if (!class_exists(\WPAICG\Core\Providers\Google\GooglePayloadFormatter::class)) {
        $formatter_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($formatter_bootstrap)) {
            require_once $formatter_bootstrap;
        } else {
            return []; 
        }
    }
    return \WPAICG\Core\Providers\Google\GooglePayloadFormatter::format_sse($messages, $system_instruction, $ai_params, $model);
}

// --- build.php ---
/**
 * Logic for the build static method of GoogleUrlBuilder.
 *
 * @param string $operation ('chat', 'models', 'stream', 'embedContent', 'batchEmbedContents')
 * @param array  $params Required parameters (base_url, api_version, api_key, model) and optional (pageSize, pageToken).
 * @return string|WP_Error The full URL or WP_Error.
 */
function build_logic_for_url_builder(string $operation, array $params) {
    $base_url = !empty($params['base_url']) ? rtrim($params['base_url'], '/') : '';
    $api_version = !empty($params['api_version']) ? $params['api_version'] : '';
    $api_key = !empty($params['api_key']) ? $params['api_key'] : '';
    $model_id = !empty($params['model']) ? $params['model'] : '';

    if (empty($base_url)) return new WP_Error("missing_base_url_Google_logic", __('Google Base URL is required.', 'gpt3-ai-content-generator'));
    if (empty($api_version)) return new WP_Error("missing_api_version_Google_logic", __('Google API Version is required.', 'gpt3-ai-content-generator'));
    if (empty($api_key)) return new WP_Error('missing_google_api_key_for_url_logic', __('Google API key is required for URL construction.', 'gpt3-ai-content-generator'));

    $paths = [
        'models'       => '/models',
        'chat'         => '/models/{model}:generateContent',
        'stream'       => '/models/{model}:streamGenerateContent',
        'embedContent' => '/models/{model}:embedContent',
        'batchEmbedContents' => '/models/{model}:batchEmbedContents',
    ];

    $path_key = ($operation === 'stream') ? 'stream' :
                (($operation === 'chat') ? 'chat' :
                (($operation === 'embedContent' || $operation === 'batchEmbedContents') ? $operation : 'models'));
    $path_segment = $paths[$path_key] ?? null;

    if ($path_segment === null) {
        /* translators: %s: The name of the API operation (e.g., 'chat'). */
        return new WP_Error('unsupported_operation_Google_logic', sprintf(__('Operation "%s" not supported for Google.', 'gpt3-ai-content-generator'), $operation));
    }

    $full_path = '/' . trim($api_version, '/') . $path_segment;

    if ($operation === 'chat' || $operation === 'stream' || $operation === 'embedContent' || $operation === 'batchEmbedContents') {
        /* translators: %s: The name of the API endpoint path. */
        if (empty($model_id)) return new WP_Error('missing_google_model_logic', sprintf(__('Google model ID is required for the "%s" endpoint path.', 'gpt3-ai-content-generator'), $operation));
        
        // The endpoint path already includes '/models/{model}',
        // so the placeholder should receive the raw model id WITHOUT the 'models/' prefix.
        $model_id_for_path = (strpos($model_id, 'models/') === 0)
            ? (string) substr($model_id, 7)
            : $model_id;
        $full_path = str_replace('{model}', urlencode($model_id_for_path), $full_path);
    }

    $url_with_key = $base_url . $full_path . '?key=' . urlencode($api_key);

    if ($operation === 'stream') {
        $url_with_key = add_query_arg('alt', 'sse', $url_with_key);
    }

    if ($operation === 'models') {
        if (!empty($params['pageSize'])) $url_with_key = add_query_arg('pageSize', absint($params['pageSize']), $url_with_key);
        if (!empty($params['pageToken'])) $url_with_key = add_query_arg('pageToken', urlencode($params['pageToken']), $url_with_key);
    }

    return $url_with_key;
}

// --- check-and-init-safety-settings.php ---
/**
 * Logic for the check_and_init_safety_settings static method of GoogleSettingsHandler.
 *
 * @param array $default_safety_settings The default safety settings array.
 */
function check_and_init_safety_settings_logic(array $default_safety_settings) {
    $opts = get_option('aipkit_options', array());
    $changed = false;

    if (!isset($opts['providers']) || !is_array($opts['providers'])) {
        $opts['providers'] = array();
        $changed = true;
    }
    if (!isset($opts['providers']['Google']) || !is_array($opts['providers']['Google'])) {
        $opts['providers']['Google'] = array();
        $changed = true;
    }

    if (!isset($opts['providers']['Google']['safety_settings'])
        || !is_array($opts['providers']['Google']['safety_settings'])
        || empty($opts['providers']['Google']['safety_settings'])) {
        $opts['providers']['Google']['safety_settings'] = $default_safety_settings;
        $changed = true;
    } else {
        $current_categories = array_column($opts['providers']['Google']['safety_settings'], 'category');
        foreach ($default_safety_settings as $default_setting_item) { // Renamed loop variable
            if (!in_array($default_setting_item['category'], $current_categories, true)) {
                $opts['providers']['Google']['safety_settings'][] = $default_setting_item;
                $changed = true;
            }
        }
    }

    if ($changed) {
        update_option('aipkit_options', $opts, 'no');
    }
}

// --- extract-candidate-text.php ---
/**
 * Extracts and concatenates all text parts from a Gemini candidate payload.
 *
 * @param array<string, mixed> $candidate
 * @return string|null
 */
function extract_candidate_text_logic_for_response_parser(array $candidate): ?string {
    if (!isset($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
        return null;
    }

    $text = '';
    $has_text = false;

    foreach ($candidate['content']['parts'] as $part) {
        if (!is_array($part) || !isset($part['text'])) {
            continue;
        }

        $text .= (string) $part['text'];
        $has_text = true;
    }

    if (!$has_text) {
        return null;
    }

    return $text;
}

// --- extract-citations.php ---
/**
 * Convert Gemini grounding metadata into a stable citation list for the shared chat UI.
 *
 * @param array<string, mixed> $grounding_metadata
 * @return array<int, array<string, mixed>>
 */
function extract_google_citations_from_grounding_metadata_logic_for_response_parser(array $grounding_metadata): array {
    $grounding_chunks = isset($grounding_metadata['groundingChunks']) && is_array($grounding_metadata['groundingChunks'])
        ? $grounding_metadata['groundingChunks']
        : [];

    if (empty($grounding_chunks)) {
        return [];
    }

    $support_texts_by_chunk = [];
    $grounding_supports = isset($grounding_metadata['groundingSupports']) && is_array($grounding_metadata['groundingSupports'])
        ? $grounding_metadata['groundingSupports']
        : [];

    foreach ($grounding_supports as $support) {
        if (!is_array($support)) {
            continue;
        }

        $segment = isset($support['segment']) && is_array($support['segment']) ? $support['segment'] : [];
        $segment_text = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
        $chunk_indices = isset($support['groundingChunkIndices']) && is_array($support['groundingChunkIndices'])
            ? $support['groundingChunkIndices']
            : [];

        if ($segment_text === '' || empty($chunk_indices)) {
            continue;
        }

        foreach ($chunk_indices as $chunk_index) {
            if (!is_numeric($chunk_index)) {
                continue;
            }
            $chunk_index = (int) $chunk_index;
            if (!isset($support_texts_by_chunk[$chunk_index])) {
                $support_texts_by_chunk[$chunk_index] = [];
            }
            $support_texts_by_chunk[$chunk_index][] = $segment_text;
        }
    }

    $citations = [];
    foreach ($grounding_chunks as $chunk_index => $chunk) {
        if (!is_array($chunk)) {
            continue;
        }

        $normalized = normalize_google_grounding_chunk_logic_for_response_parser(
            $chunk,
            isset($support_texts_by_chunk[$chunk_index]) && is_array($support_texts_by_chunk[$chunk_index])
                ? $support_texts_by_chunk[$chunk_index]
                : [],
            is_numeric($chunk_index) ? (int) $chunk_index : null
        );

        if ($normalized !== null) {
            $citations[] = $normalized;
        }
    }

    return dedupe_google_citations_logic_for_response_parser($citations);
}

/**
 * Normalize one Gemini grounding chunk into the shared citation shape.
 *
 * @param array<string, mixed> $chunk
 * @param array<int, string> $support_texts
 * @param int|null $chunk_index
 * @return array<string, mixed>|null
 */
function normalize_google_grounding_chunk_logic_for_response_parser(array $chunk, array $support_texts = [], ?int $chunk_index = null): ?array {
    $source = extract_google_grounding_source_logic_for_response_parser($chunk);
    $title = isset($source['title']) && is_string($source['title']) ? trim($source['title']) : '';
    $url = isset($source['url']) && is_string($source['url']) ? trim($source['url']) : '';

    $unique_support_texts = [];
    foreach ($support_texts as $support_text) {
        if (!is_string($support_text)) {
            continue;
        }

        $support_text = trim($support_text);
        if ($support_text === '' || in_array($support_text, $unique_support_texts, true)) {
            continue;
        }

        $unique_support_texts[] = $support_text;
    }

    $cited_text = trim(implode(' ', $unique_support_texts));

    if ($title === '' && $url === '' && $cited_text === '') {
        return null;
    }

    $normalized = [
        'type' => 'url_citation',
    ];

    if ($url !== '') {
        $normalized['url'] = $url;
    }
    if ($title !== '') {
        $normalized['title'] = $title;
        $normalized['source_title'] = $title;
    }
    if ($cited_text !== '') {
        $normalized['cited_text'] = $cited_text;
    }
    if ($chunk_index !== null) {
        $normalized['document_index'] = $chunk_index;
    }

    return $normalized;
}

/**
 * Extract the best available title/url pair from a grounding chunk.
 *
 * @param array<string, mixed> $chunk
 * @return array<string, string>
 */
function extract_google_grounding_source_logic_for_response_parser(array $chunk): array {
    $candidates = [];

    foreach (['web', 'retrievedContext', 'source', 'document'] as $key) {
        if (isset($chunk[$key]) && is_array($chunk[$key])) {
            $candidates[] = $chunk[$key];
        }
    }

    $candidates[] = $chunk;

    foreach ($candidates as $candidate) {
        $url = '';
        foreach (['uri', 'url', 'link'] as $field) {
            if (isset($candidate[$field]) && is_string($candidate[$field]) && trim($candidate[$field]) !== '') {
                $url = trim($candidate[$field]);
                break;
            }
        }

        $title = '';
        foreach (['title', 'source_title', 'website_title', 'name'] as $field) {
            if (isset($candidate[$field]) && is_string($candidate[$field]) && trim($candidate[$field]) !== '') {
                $title = trim($candidate[$field]);
                break;
            }
        }

        if ($url !== '' || $title !== '') {
            return [
                'url' => $url,
                'title' => $title,
            ];
        }
    }

    return [
        'url' => '',
        'title' => '',
    ];
}

/**
 * Deduplicate citations while preserving order.
 *
 * @param array<int, array<string, mixed>> $citations
 * @return array<int, array<string, mixed>>
 */
function dedupe_google_citations_logic_for_response_parser(array $citations): array {
    $deduped = [];
    $seen = [];

    foreach ($citations as $citation) {
        if (!is_array($citation) || empty($citation)) {
            continue;
        }

        $encoded = wp_json_encode($citation);
        if (!is_string($encoded) || isset($seen[$encoded])) {
            continue;
        }

        $seen[$encoded] = true;
        $deduped[] = $citation;
    }

    return $deduped;
}

// --- format-chat-payload.php ---
/**
 * Logic for the format_chat_payload method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $user_message The user's message (already included in history for Google).
 * @param string $instructions System instructions.
 * @param array  $history Conversation history.
 * @param array  $ai_params AI parameters.
 * @param string $model Model ID.
 * @return array The formatted request body data.
 */
function format_chat_payload_logic(
    GoogleProviderStrategy $strategyInstance,
    string $user_message,
    string $instructions,
    array $history,
    array $ai_params,
    string $model
): array {
    if (!class_exists(\WPAICG\Core\Providers\Google\GooglePayloadFormatter::class)) {
        $formatter_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($formatter_bootstrap)) {
            require_once $formatter_bootstrap;
        } else {
            return []; 
        }
    }
    $final_history = $history;
    if(!empty($user_message)){
        $last_msg = end($final_history);
        if(!$last_msg || $last_msg['role'] !== 'user' || $last_msg['content'] !== $user_message){
             $final_history[] = ['role' => 'user', 'content' => $user_message];
        }
    }
    return \WPAICG\Core\Providers\Google\GooglePayloadFormatter::format_chat($instructions, $final_history, $ai_params, $model);
}

// --- format-chat.php ---
/**
 * Logic for the format_chat static method of GooglePayloadFormatter.
 *
 * @param string $instructions System instructions.
 * @param array  $history Conversation history.
 * @param array  $ai_params AI parameters.
 * @param string $model Model ID (for grounding tool determination).
 * @return array The formatted payload.
 */
function format_chat_logic_for_payload_formatter(string $instructions, array $history, array $ai_params, string $model): array {
    $ai_params['model_id_for_grounding'] = $model;
    return _shared_format_logic($instructions, $history, $ai_params);
}

// --- format-embeddings.php ---
/**
 * Logic for the format_embeddings static method of GooglePayloadFormatter.
 *
 * @param string|array $input The input text or array of texts.
 * @param array  $options Embedding options including 'model', 'taskType', 'outputDimensionality'.
 * @return array The formatted request body data.
 */
function format_embeddings_logic_for_payload_formatter($input, array $options): array {
    $texts_to_embed = [];
    if (is_array($input)) {
        foreach ($input as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $text = trim((string) $item);
            if ($text !== '') {
                $texts_to_embed[] = $text;
            }
        }
    } elseif (is_scalar($input)) {
        $text = trim((string) $input);
        if ($text !== '') {
            $texts_to_embed[] = $text;
        }
    }

    // Keep one empty part to preserve previous behavior for edge-case empty input.
    if (empty($texts_to_embed)) {
        $texts_to_embed[] = '';
    }

    $parts = [];
    foreach ($texts_to_embed as $text) {
        $parts[] = ['text' => $text];
    }

    // Google Embeddings expects the model name in the form "models/<model-id>" in the request body
    $model_for_body = isset($options['model']) ? (string) $options['model'] : '';
    if ($model_for_body !== '' && strpos($model_for_body, 'models/') !== 0) {
        $model_for_body = 'models/' . $model_for_body;
    }

    $request_options = [];

    if (isset($options['taskType']) && is_string($options['taskType'])) {
        $request_options['taskType'] = $options['taskType'];
    }
    if (isset($options['title']) && is_string($options['title'])) {
        $request_options['title'] = $options['title'];
    }
    if (isset($options['outputDimensionality']) && is_int($options['outputDimensionality'])) {
        $request_options['outputDimensionality'] = $options['outputDimensionality'];
    }

    if (count($texts_to_embed) > 1) {
        $requests = [];
        foreach ($texts_to_embed as $text) {
            $requests[] = array_merge([
                'model' => $model_for_body,
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ], $request_options);
        }

        return ['requests' => $requests];
    }

    return array_merge([
        'model' => $model_for_body,
        'content' => [
            'parts' => $parts
        ]
    ], $request_options);
}

// --- format-google-model-list.php ---
/**
 * Logic for the format_google_model_list private method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class (unused in static context but kept for consistency).
 * @param array $raw_models The array of raw model data from the API.
 * @return array Formatted list [['id' => ..., 'name' => ...]].
 */
function format_google_model_list_logic(GoogleProviderStrategy $strategyInstance, array $raw_models): array {
    
    $formatted = [];
    foreach ($raw_models as $model) {
        if (!is_array($model)) continue;
        $mId = $model['name'] ?? null;
        if (!empty($mId)) {
            $cleanId = (strpos($mId, 'models/') === 0) ? (string) substr($mId, 7) : $mId;
            $supportedMethods = $model['supportedGenerationMethods'] ?? [];
            
            $formatted[] = [
                'id'       => $cleanId,
                'name'     => $model['displayName'] ?? $cleanId,
                'version'  => $model['version'] ?? '',
                'supportedGenerationMethods' => $supportedMethods,
            ];
        }
    }
    return $formatted;
}

// --- format-sse.php ---
/**
 * Logic for the format_sse static method of GooglePayloadFormatter.
 *
 * @param array  $messages Formatted messages array (user/model).
 * @param string $system_instruction System instructions.
 * @param array  $ai_params AI parameters.
 * @param string $model Model ID (for grounding tool determination).
 * @return array The formatted SSE payload.
 */
function format_sse_logic_for_payload_formatter(array $messages, string $system_instruction, array $ai_params, string $model): array {
    $history = array_map(function($msg) { 
        if ($msg['role'] === 'assistant') $msg['role'] = 'model';
        return $msg;
    }, $messages);
    $ai_params['model_id_for_grounding'] = $model;
    $payload = _shared_format_logic($system_instruction, $history, $ai_params);
    return $payload;
}

// --- generate-embeddings.php ---
/**
 * Logic for the generate_embeddings method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string|array $input The input text or array of texts.
 * @param array $api_params Provider-specific API connection parameters.
 * @param array $options Embedding options (model, taskType, outputDimensionality).
 * @return array|WP_Error An array of embedding vectors or WP_Error on failure.
 */
function generate_embeddings_logic(
    GoogleProviderStrategy $strategyInstance,
    $input,
    array $api_params,
    array $options = []
) {
    if (!class_exists(GoogleUrlBuilder::class) || !class_exists(GooglePayloadFormatter::class) || !class_exists(GoogleResponseParser::class)) {
        return new WP_Error('google_embedding_dependency_missing_logic', __('Google embedding components are missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }

    $model_id = $options['model'] ?? '';
    if (empty($model_id)) {
        return new WP_Error('missing_google_embedding_model_logic', __('Google embedding model ID is required.', 'gpt3-ai-content-generator'));
    }

    $input_count = 0;
    if (is_array($input)) {
        foreach ($input as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $input_count++;
            }
        }
    } elseif (is_scalar($input) && trim((string) $input) !== '') {
        $input_count = 1;
    }
    $operation = $input_count > 1 ? 'batchEmbedContents' : 'embedContent';

    $url_params = array_merge($api_params, ['model' => $model_id]);
    $url = GoogleUrlBuilder::build($operation, $url_params);
    if (is_wp_error($url)) {
        return $url;
    }

    $headers = $strategyInstance->get_api_headers($api_params['api_key'], $operation);
    $request_options = $strategyInstance->get_request_options($operation);
    $payload = GooglePayloadFormatter::format_embeddings($input, $options);
    $request_body_json = wp_json_encode($payload);

    $response = wp_remote_post($url, array_merge($request_options, ['headers' => $headers, 'body' => $request_body_json]));

    if (is_wp_error($response)) {
        return new WP_Error('google_embedding_http_error_logic', __('HTTP error during embedding generation.', 'gpt3-ai-content-generator'));
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded_response = $strategyInstance->decode_json($body, 'Google Embeddings'); 

    if ($status_code !== 200 || is_wp_error($decoded_response)) {
        $error_msg = is_wp_error($decoded_response)
                    ? $decoded_response->get_error_message()
                    : GoogleResponseParser::parse_error($body, $status_code);
        $error_data = $strategyInstance->build_http_error_data_with_retry_after($response, $status_code);
        /* translators: %1$d: HTTP status code, %2$s: API error message. */
        return new WP_Error('google_embedding_api_error_logic', sprintf(__('Google Embeddings API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, esc_html($error_msg)), $error_data);
    }
    return GoogleResponseParser::parse_embeddings($decoded_response);
}

// --- get-api-headers.php ---
/**
 * Logic for the get_api_headers method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $api_key The API key (not directly used by Google headers, but part of interface).
 * @param string $operation The specific operation being performed.
 * @return array Key-value array of headers.
 */
function get_api_headers_logic(GoogleProviderStrategy $strategyInstance, string $api_key, string $operation): array {
    $headers = ['Content-Type' => 'application/json'];
    if ($operation === 'stream') { 
        $headers['Accept'] = 'text/event-stream';
        $headers['Cache-Control'] = 'no-cache';
    }
    return $headers;
}

// --- get-models.php ---
/**
 * Logic for the get_models method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $api_params Connection parameters (api_key, base_url, etc.).
 * @return array|WP_Error Formatted list [['id' => ..., 'name' => ...]] or WP_Error.
 */
function get_models_logic(GoogleProviderStrategy $strategyInstance, array $api_params) {
    if (!class_exists(GoogleUrlBuilder::class) || !class_exists(GoogleResponseParser::class)) {
        return new WP_Error('google_dependency_missing_models_logic', __('Google components for model listing are missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }

    $all_results = [];
    $next_page_token = null;
    $page_size = 100;

    do {
        $params_for_page = $api_params;
        $params_for_page['pageSize'] = $page_size;
        if ($next_page_token) $params_for_page['pageToken'] = $next_page_token;

        $url = GoogleUrlBuilder::build('models', $params_for_page);
        if (is_wp_error($url)) return $url;

        $headers = $strategyInstance->get_api_headers($api_params['api_key'], 'models');
        $options = $strategyInstance->get_request_options('models');
        $options['method'] = 'GET';

        $response = wp_remote_get($url, array_merge($options, ['headers' => $headers]));
        if (is_wp_error($response)) return $response;

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_msg = GoogleResponseParser::parse_error($body, $status_code);
            return new WP_Error('api_error_google_models_logic', sprintf('Google API Error (HTTP %d): %s', $status_code, esc_html($error_msg)));
        }

        $decoded = $strategyInstance->decode_json($body, 'Google Models');
        if (is_wp_error($decoded)) return $decoded;

        $raw_models = $decoded['models'] ?? [];
        $formatted_page = format_google_model_list_logic($strategyInstance, $raw_models); // Call the namespaced function
        $all_results = array_merge($all_results, $formatted_page);

        $next_page_token = $decoded['nextPageToken'] ?? null;

    } while (!empty($next_page_token));

    usort($all_results, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
    return $all_results;
}

// --- get-safety-settings.php ---
/**
 * Logic for the get_safety_settings static method of GoogleSettingsHandler.
 *
 * @param array $default_safety_settings The default safety settings array.
 * @return array The safety settings.
 */
function get_safety_settings_logic(array $default_safety_settings): array {
    check_and_init_safety_settings_logic($default_safety_settings); 
    $opts = get_option('aipkit_options', array());
    return $opts['providers']['Google']['safety_settings'] ?? $default_safety_settings;
}

// --- get-synced-tts-voices.php ---
/**
 * Logic for the get_synced_google_tts_voices static method of GoogleSettingsHandler.
 *
 * @param string $option_name The name of the WordPress option storing the voices.
 * @return array List of voice data arrays.
 */
function get_synced_google_tts_voices_logic(string $option_name): array {
    return get_option($option_name, []);
}

// --- map-sse-event.php ---
/**
 * Maps a normalized Google SSE event into an internal typed event.
 *
 * @param array<string, mixed> $decoded_event
 * @return array<string, mixed>
 */
function map_sse_event_logic_for_response_parser(array $decoded_event): array {
    $kind = isset($decoded_event['kind']) && is_string($decoded_event['kind']) ? $decoded_event['kind'] : 'payload';
    $payload = isset($decoded_event['payload']) && is_array($decoded_event['payload']) ? $decoded_event['payload'] : [];

    if ($kind === 'done') {
        return ['kind' => 'done'];
    }

    if (isset($payload['error']['message'])) {
        return [
            'kind' => 'error',
            'message' => parse_error_logic_for_response_parser($payload, 500),
        ];
    }

    $candidate = $payload['candidates'][0] ?? null;
    if (!is_array($candidate)) {
        $candidate = [];
    }

    $delta_text = extract_candidate_text_logic_for_response_parser($candidate);
    if ($delta_text === '') {
        $delta_text = null;
    }

    $usage = extract_sse_usage_logic_for_response_parser($payload);
    $grounding_metadata = extract_sse_grounding_metadata_logic_for_response_parser($candidate);
    $citations = is_array($grounding_metadata)
        ? extract_google_citations_from_grounding_metadata_logic_for_response_parser($grounding_metadata)
        : [];
    $notice_text = build_sse_notice_text_logic_for_response_parser($payload, $candidate);
    $is_warning = is_sse_warning_logic_for_response_parser($payload, $candidate);

    if ($delta_text === null && $usage === null && $grounding_metadata === null && empty($citations) && $notice_text === null) {
        return ['kind' => 'skip'];
    }

    return [
        'kind' => 'chunk',
        'delta_text' => $delta_text,
        'usage' => $usage,
        'grounding_metadata' => $grounding_metadata,
        'citations' => $citations,
        'notice_text' => $notice_text,
        'is_warning' => $is_warning,
    ];
}

/**
 * Extracts token usage from a Google SSE payload.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null
 */
function extract_sse_usage_logic_for_response_parser(array $payload): ?array {
    if (!isset($payload['usageMetadata']) || !is_array($payload['usageMetadata'])) {
        return null;
    }

    $usage = $payload['usageMetadata'];

    return [
        'input_tokens' => $usage['promptTokenCount'] ?? 0,
        'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
        'total_tokens' => $usage['totalTokenCount'] ?? 0,
        'provider_raw' => $usage,
    ];
}

/**
 * Extracts grounding metadata from a Google candidate payload.
 *
 * @param array<string, mixed> $candidate
 * @return array<string, mixed>|null
 */
function extract_sse_grounding_metadata_logic_for_response_parser(array $candidate): ?array {
    if (isset($candidate['groundingMetadata']) && is_array($candidate['groundingMetadata'])) {
        return $candidate['groundingMetadata'];
    }

    return null;
}

/**
 * Builds any user-visible notice text from prompt feedback or finish reasons.
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $candidate
 * @return string|null
 */
function build_sse_notice_text_logic_for_response_parser(array $payload, array $candidate): ?string {
    $notice_text = '';

    if (!empty($payload['promptFeedback']['blockReason'])) {
        $notice_text .= sprintf(' (%s: %s)', __('Warning', 'gpt3-ai-content-generator'), $payload['promptFeedback']['blockReason']);
    }

    if (isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
        $reason = (string) $candidate['finishReason'];
        if ($reason === 'SAFETY') {
            $notice_text .= sprintf(' (%s: %s)', __('Warning', 'gpt3-ai-content-generator'), $candidate['safetyRatings'][0]['category'] ?? $reason);
        } else {
            $notice_text .= sprintf(' (%s: %s)', __('Note', 'gpt3-ai-content-generator'), $reason);
        }
    }

    if ($notice_text === '') {
        return null;
    }

    return $notice_text;
}

/**
 * Determines whether the current Google SSE payload should be treated as a warning.
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $candidate
 * @return bool
 */
function is_sse_warning_logic_for_response_parser(array $payload, array $candidate): bool {
    if (!empty($payload['promptFeedback']['blockReason'])) {
        return true;
    }

    return isset($candidate['finishReason']) && $candidate['finishReason'] === 'SAFETY';
}

// --- parse-chat-response.php ---
/**
 * Logic for the parse_chat_response method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $decoded_response The decoded JSON response body.
 * @param array $request_data The original request data sent (unused here).
 * @return array|WP_Error ['content' => string, 'usage' => array|null] or WP_Error.
 */
function parse_chat_response_logic(
    GoogleProviderStrategy $strategyInstance,
    array $decoded_response,
    array $request_data
) {
    if (!class_exists(\WPAICG\Core\Providers\Google\GoogleResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return new WP_Error('google_response_parser_missing_logic', 'Google response parser component is not available.');
        }
    }
    return \WPAICG\Core\Providers\Google\GoogleResponseParser::parse_chat($decoded_response);
}

// --- parse-chat.php ---
/**
 * Logic for the parse_chat static method of GoogleResponseParser.
 * UPDATED: Extracts groundingMetadata.
 *
 * @param array $decoded_response The decoded JSON response.
 * @return array|WP_Error ['content' => string, 'usage' => array|null, 'grounding_metadata' => array|null] or WP_Error.
 */
function parse_chat_logic_for_response_parser(array $decoded_response)
{
    $content = null;
    $usage = null;
    $grounding_metadata = null;
    $citations = [];

    if (!empty($decoded_response['promptFeedback']['blockReason'])) {
        $block_reason = $decoded_response['promptFeedback']['blockReason'];
        $safety_ratings = $decoded_response['promptFeedback']['safetyRatings'] ?? [];
        $details = array_map(fn ($r) => ($r['category'] ?? 'Unknown') . ': ' . ($r['probability'] ?? 'N/A'), $safety_ratings);
        /* translators: %1$s: The reason the request was blocked (e.g., SAFETY). %2$s: A comma-separated list of details. */
        $error_message = sprintf(__('Request blocked by Google due to: %1$s. Details: %2$s', 'gpt3-ai-content-generator'), $block_reason, implode(', ', $details));
        return new WP_Error('google_content_blocked_logic', $error_message);
    }

    if (isset($decoded_response['candidates'][0]['finishReason']) && $decoded_response['candidates'][0]['finishReason'] === 'SAFETY') {
        $reason = $decoded_response['promptFeedback']['blockReason'] ?? $decoded_response['candidates'][0]['safetyRatings'][0]['category'] ?? 'safety settings';
        /* translators: %s: The reason the response was filtered (e.g., 'SAFETY'). */
        return new WP_Error('google_content_filtered_logic', sprintf(__('Response filtered by Google due to: %s.', 'gpt3-ai-content-generator'), $reason));
    }

    if (isset($decoded_response['candidates'][0]) && is_array($decoded_response['candidates'][0])) {
        $candidate_text = extract_candidate_text_logic_for_response_parser($decoded_response['candidates'][0]);
        if ($candidate_text !== null) {
            $content = trim($candidate_text);
        }
    }

    if (isset($decoded_response['candidates'][0]['groundingMetadata'])) {
        $grounding_metadata = $decoded_response['candidates'][0]['groundingMetadata'];
        if (is_array($grounding_metadata)) {
            $citations = extract_google_citations_from_grounding_metadata_logic_for_response_parser($grounding_metadata);
        }
    }

    if (isset($decoded_response['usageMetadata']) && is_array($decoded_response['usageMetadata'])) {
        $usage = [
            'input_tokens'  => $decoded_response['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $decoded_response['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens'  => $decoded_response['usageMetadata']['totalTokenCount'] ?? 0,
            'provider_raw' => $decoded_response['usageMetadata'],
        ];
    }

    if ($content === null && isset($decoded_response['candidates'][0]['finishReason']) && $decoded_response['candidates'][0]['finishReason'] !== 'STOP') {
        /* translators: %s: The reason the AI stopped generating content (e.g., 'MAX_TOKENS'). */
        return new WP_Error('google_no_content_logic', sprintf(__('No content returned from Google. Finish reason: %s', 'gpt3-ai-content-generator'), $decoded_response['candidates'][0]['finishReason']));
    } elseif ($content === null) {
        return new WP_Error('invalid_response_structure_google_logic', __('Unexpected response structure from Google API.', 'gpt3-ai-content-generator'));
    }

    $return_data = ['content' => $content, 'usage' => $usage];
    if ($grounding_metadata !== null) {
        $return_data['grounding_metadata'] = $grounding_metadata;
    }
    if (!empty($citations)) {
        $return_data['citations'] = $citations;
    }
    return $return_data;
}

// --- parse-embeddings.php ---
/**
 * Logic for the parse_embeddings static method of GoogleResponseParser.
 *
 * @param array $decoded_response The decoded JSON response body.
 * @return array|WP_Error ['embeddings' => array, 'usage' => array|null] or WP_Error.
 */
function parse_embeddings_logic_for_response_parser(array $decoded_response) {
    $embeddings = [];
    if (isset($decoded_response['embedding']['values']) && is_array($decoded_response['embedding']['values'])) { 
        $embeddings[] = $decoded_response['embedding']['values']; 
    } elseif (isset($decoded_response['embeddings']) && is_array($decoded_response['embeddings'])) {
        foreach ($decoded_response['embeddings'] as $emb_item) {
            if (isset($emb_item['values']) && is_array($emb_item['values'])) { 
                $embeddings[] = $emb_item['values']; 
            } elseif (isset($emb_item['embedding']) && is_array($emb_item['embedding'])) { 
                $embeddings[] = $emb_item['embedding'];
            }
        }
    }

    if (empty($embeddings)) {
        return new WP_Error('google_embedding_no_data_logic', __('No embedding data found in Google response.', 'gpt3-ai-content-generator'));
    }
    return ['embeddings' => $embeddings, 'usage' => null];
}

// --- parse-error-response.php ---
/**
 * Logic for the parse_error_response method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param mixed $response_body The raw or decoded error response body.
 * @param int $status_code The HTTP status code.
 * @return string A user-friendly error message.
 */
function parse_error_response_logic(
    GoogleProviderStrategy $strategyInstance,
    $response_body,
    int $status_code
): string {
    if (!class_exists(\WPAICG\Core\Providers\Google\GoogleResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return "Google response parser component is not available."; 
        }
    }
    return \WPAICG\Core\Providers\Google\GoogleResponseParser::parse_error($response_body, $status_code);
}

// --- parse-error.php ---
/**
 * Logic for the parse_error static method of GoogleResponseParser.
 *
 * @param mixed $response_body The raw or decoded error response body.
 * @param int $status_code The HTTP status code.
 * @return string A user-friendly error message.
 */
function parse_error_logic_for_response_parser($response_body, int $status_code): string {
    $message = __('An unknown API error occurred.', 'gpt3-ai-content-generator');
    $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

    if (is_array($decoded) && !empty($decoded['error']['message'])) {
        $message = $decoded['error']['message'];
        if (!empty($decoded['error']['details'][0]['message'])) {
            $message .= " (" . $decoded['error']['details'][0]['message'] . ")";
        }
    } elseif (is_string($response_body)) {
         $message = substr($response_body, 0, 200);
    }

    return trim($message);
}

// --- parse-sse-chunk.php ---
/**
 * Logic for the parse_sse_chunk method of GoogleProviderStrategy.
 *
 * @param GoogleProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $sse_chunk The raw chunk received from the stream.
 * @param string &$current_buffer The reference to the incomplete buffer for this provider.
 * @return array Result containing delta, usage, flags.
 */
function parse_sse_chunk_logic(
    GoogleProviderStrategy $strategyInstance,
    string $sse_chunk,
    string &$current_buffer
): array {
    if (!class_exists(\WPAICG\Core\Providers\Google\GoogleResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return ['delta' => null, 'usage' => null, 'is_error' => true, 'is_warning' => false, 'is_done' => true];
        }
    }
    return \WPAICG\Core\Providers\Google\GoogleResponseParser::parse_sse_chunk($sse_chunk, $current_buffer);
}

// --- parse-sse.php ---
require_once __DIR__ . '/../shared/extract-sse-event-blocks.php';
require_once __DIR__ . '/../shared/decode-sse-event-block.php';

/**
 * Logic for the parse_sse_chunk static method of GoogleResponseParser.
 * UPDATED: Include groundingMetadata in the result.
 *
 * @param string $sse_chunk The raw chunk received.
 * @param string &$current_buffer Reference to the incomplete buffer.
 * @return array Result containing delta, usage, flags, and grounding_metadata.
 */
function parse_sse_chunk_logic_for_response_parser(string $sse_chunk, string &$current_buffer): array {
    $current_buffer .= $sse_chunk;
    $result = ['delta' => null, 'usage' => null, 'is_error' => false, 'is_warning' => false, 'is_done' => false, 'grounding_metadata' => null, 'citations' => null];

    foreach (extract_sse_event_blocks($current_buffer) as $event_block) {
        $decoded_event = decode_data_only_sse_event_block($event_block);
        if ($decoded_event === null) {
            continue;
        }

        $mapped_event = map_sse_event_logic_for_response_parser($decoded_event);
        if (reduce_sse_event_logic_for_response_parser($mapped_event, $result)) {
            return $result;
        }
    }

    return $result;
}

// --- reduce-sse-event.php ---
/**
 * Applies an internal typed Google SSE event to the flattened parse result expected by the stream processor.
 *
 * @param array<string, mixed> $mapped_event
 * @param array<string, mixed> $result
 * @return bool True when parsing should stop immediately.
 */
function reduce_sse_event_logic_for_response_parser(array $mapped_event, array &$result): bool {
    $kind = isset($mapped_event['kind']) && is_string($mapped_event['kind']) ? $mapped_event['kind'] : 'skip';

    switch ($kind) {
        case 'chunk':
            if (isset($mapped_event['usage']) && is_array($mapped_event['usage'])) {
                $result['usage'] = $mapped_event['usage'];
            }

            if (isset($mapped_event['grounding_metadata']) && is_array($mapped_event['grounding_metadata'])) {
                $result['grounding_metadata'] = $mapped_event['grounding_metadata'];
            }

            if (isset($mapped_event['citations']) && is_array($mapped_event['citations']) && !empty($mapped_event['citations'])) {
                $existing_citations = isset($result['citations']) && is_array($result['citations'])
                    ? $result['citations']
                    : [];
                $result['citations'] = merge_google_sse_citations_logic_for_response_parser($existing_citations, $mapped_event['citations']);
            }

            if (!empty($mapped_event['delta_text']) && is_string($mapped_event['delta_text'])) {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $mapped_event['delta_text'];
            }

            if (!empty($mapped_event['notice_text']) && is_string($mapped_event['notice_text'])) {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $mapped_event['notice_text'];
            }

            if (!empty($mapped_event['is_warning'])) {
                $result['is_warning'] = true;
            }
            return false;

        case 'error':
            $message = isset($mapped_event['message']) ? (string) $mapped_event['message'] : '';
            $result['delta'] = $message;
            $result['is_error'] = true;
            return true;

        case 'done':
            $result['is_done'] = true;
            return false;

        default:
            return false;
    }
}

/**
 * Merge citations while preserving order and removing duplicates.
 *
 * @param array<int, array<string, mixed>> $existing
 * @param array<int, array<string, mixed>> $incoming
 * @return array<int, array<string, mixed>>
 */
function merge_google_sse_citations_logic_for_response_parser(array $existing, array $incoming): array {
    return dedupe_google_citations_logic_for_response_parser(array_merge($existing, $incoming));
}

// --- save-safety-settings.php ---
/**
 * Logic for the save_safety_settings static method of GoogleSettingsHandler.
 *
 * @param array $post_data The unslashed $_POST data containing safety settings.
 * @param array $default_safety_settings The default safety settings array.
 * @return bool True if the option was updated, false otherwise.
 */
function save_safety_settings_logic(array $post_data, array $default_safety_settings): bool {
    $opts = get_option('aipkit_options', array());
    check_and_init_safety_settings_logic($default_safety_settings); 
    $opts = get_option('aipkit_options', array()); 

    $current_settings = $opts['providers']['Google']['safety_settings'] ?? $default_safety_settings;
    $updated_settings = [];
    $valid_thresholds = array('BLOCK_NONE', 'BLOCK_LOW_AND_ABOVE', 'BLOCK_MEDIUM_AND_ABOVE', 'BLOCK_ONLY_HIGH');

    foreach ($default_safety_settings as $default_setting_item) {
         $category = $default_setting_item['category'];
         $short_category = strtolower(str_replace('HARM_CATEGORY_', '', $category));
         $threshold_key = 'safety_' . $short_category;
         $new_threshold = $default_setting_item['threshold'];

         if (isset($post_data[$threshold_key])) {
             $posted_threshold = sanitize_text_field($post_data[$threshold_key]);
             if (in_array($posted_threshold, $valid_thresholds, true)) {
                 $new_threshold = $posted_threshold;
             }
         } else {
             foreach ($current_settings as $current_setting_item) {
                 if (isset($current_setting_item['category']) && $current_setting_item['category'] === $category && isset($current_setting_item['threshold'])) {
                     $new_threshold = $current_setting_item['threshold'];
                     break;
                 }
             }
         }
         $updated_settings[] = ['category' => $category, 'threshold' => $new_threshold];
    }

    if ($opts['providers']['Google']['safety_settings'] !== $updated_settings) {
        $opts['providers']['Google']['safety_settings'] = $updated_settings;
        return update_option('aipkit_options', $opts, 'no');
    }
    return false;
}
