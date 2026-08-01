<?php
namespace WPAICG\Core\Providers\OpenRouter\Methods;

use WPAICG\Core\Providers\OpenRouterProviderStrategy;
use WPAICG\Core\Providers\OpenRouter\OpenRouterUrlBuilder; // For direct call
use WP_Error;
use WPAICG\Core\Providers\OpenRouter\OpenRouterPayloadFormatter; // For direct call
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser; // For direct call
use function WPAICG\Core\Providers\Shared\extract_sse_event_blocks;
use function WPAICG\Core\Providers\Shared\decode_event_type_sse_event_block;

if (!defined('ABSPATH')) {
    exit;
}

// --- _shared-format.php ---
/**
 * Attach image inputs to the latest user message in OpenRouter Responses input format.
 *
 * @param array $input_array Existing input message array.
 * @param array $image_inputs Image payload array from chat/frontend flow.
 * @return array
 */
function _openrouter_attach_image_inputs(array $input_array, array $image_inputs): array {
    if (empty($image_inputs)) {
        return $input_array;
    }

    $last_key = array_key_last($input_array);
    if ($last_key === null || !isset($input_array[$last_key]['role']) || $input_array[$last_key]['role'] !== 'user') {
        return $input_array;
    }

    $current_content = $input_array[$last_key]['content'] ?? '';
    $user_text = '';
    if (is_string($current_content)) {
        $user_text = $current_content;
    } elseif (is_array($current_content)) {
        foreach ($current_content as $part) {
            if (!is_array($part)) {
                continue;
            }
            $part_type = $part['type'] ?? '';
            if (($part_type === 'text' || $part_type === 'input_text') && isset($part['text']) && is_string($part['text'])) {
                $user_text = $part['text'];
                break;
            }
        }
    }

    $content_parts = [];
    $has_valid_image = false;
    if ($user_text !== '') {
        $content_parts[] = [
            'type' => 'input_text',
            'text' => $user_text,
        ];
    }

    foreach ($image_inputs as $image_input) {
        if (!is_array($image_input)) {
            continue;
        }
        $mime_type = isset($image_input['type']) ? sanitize_text_field((string) $image_input['type']) : '';
        $base64_data = isset($image_input['base64']) ? trim((string) $image_input['base64']) : '';
        if ($mime_type === '' || $base64_data === '') {
            continue;
        }

        $content_parts[] = [
            'type' => 'input_image',
            'image_url' => 'data:' . $mime_type . ';base64,' . $base64_data,
        ];
        $has_valid_image = true;
    }

    if ($has_valid_image) {
        if (empty($content_parts)) {
            $content_parts[] = ['type' => 'input_text', 'text' => ''];
        }
        $input_array[$last_key]['content'] = $content_parts;
    }

    return $input_array;
}

/**
 * Shared formatting logic for OpenRouter Responses API payloads.
 *
 * @param string $instructions System instructions.
 * @param array  $history Conversation history.
 * @param array  $ai_params AI parameters.
 * @param string $model Model name.
 * @return array The formatted payload base.
 */
function _shared_format_logic(string $instructions, array $history, array $ai_params, string $model): array {
    $input_array = [];
    if (!empty($instructions)) {
        $input_array[] = ['role' => 'system', 'content' => $instructions];
    }

    foreach ($history as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $raw_role = $msg['role'] ?? '';
        $role = ($raw_role === 'bot') ? 'assistant' : $raw_role;
        if (!in_array($role, ['system', 'user', 'assistant', 'developer'], true)) {
            continue;
        }
        if ($role === 'system' && !empty($instructions)) {
            continue;
        }

        $content = $msg['content'] ?? '';
        if (is_string($content)) {
            $content = trim($content);
            if ($content === '') {
                continue;
            }
        } elseif (is_array($content)) {
            if (empty($content)) {
                continue;
            }
        } else {
            continue;
        }

        $input_array[] = ['role' => $role, 'content' => $content];
    }

    if (!empty($ai_params['image_inputs']) && is_array($ai_params['image_inputs'])) {
        $input_array = _openrouter_attach_image_inputs($input_array, $ai_params['image_inputs']);
    }

    $body_data = [
        'model' => $model,
        'input' => $input_array,
    ];

    if (isset($ai_params['previous_response_id']) && is_string($ai_params['previous_response_id'])) {
        $previous_response_id = sanitize_text_field($ai_params['previous_response_id']);
        if ($previous_response_id !== '') {
            $body_data['previous_response_id'] = $previous_response_id;
        }
    }

    $param_map = [
        'temperature'           => 'temperature',
        'max_completion_tokens' => 'max_output_tokens',
        'top_p'                 => 'top_p',
        'top_k'                 => 'top_k',
        'presence_penalty'      => 'presence_penalty',
        'frequency_penalty'     => 'frequency_penalty',
        'top_logprobs'          => 'top_logprobs',
    ];

    foreach ($param_map as $aipkit_key => $api_key) {
        if (!isset($ai_params[$aipkit_key])) {
            continue;
        }

        $value = $ai_params[$aipkit_key];
        if ($api_key === 'max_output_tokens' || $api_key === 'top_logprobs') {
            $body_data[$api_key] = absint($value);
            continue;
        }

        $body_data[$api_key] = floatval($value);
    }

    if (isset($ai_params['reasoning']) && is_array($ai_params['reasoning'])) {
        $body_data['reasoning'] = $ai_params['reasoning'];
    }

    $capability_map = get_capability_map_logic();
    $model_supports_web_search = model_supports_web_search_plugin_logic($model);
    $bot_allows_web_search = !empty($ai_params['web_search_tool_config']['enabled']);
    $frontend_requests_web_search = !empty($ai_params['frontend_web_search_active']);
    if (
        !empty($capability_map['web_search_plugin']) &&
        $model_supports_web_search &&
        $bot_allows_web_search &&
        $frontend_requests_web_search
    ) {
        $web_plugin = array_merge(
            ['id' => 'web'],
            sanitize_web_search_config_logic(
                is_array($ai_params['web_search_tool_config'] ?? null)
                    ? $ai_params['web_search_tool_config']
                    : []
            )
        );
        $body_data['plugins'] = [$web_plugin];
    }

    return $body_data;
}

// --- build-api-url.php ---
/**
 * Logic for the build_api_url method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $operation ('chat', 'models', 'stream')
 * @param array  $params Required parameters (base_url, api_version).
 * @return string|WP_Error The full URL or WP_Error.
 */
function build_api_url_logic(OpenRouterProviderStrategy $strategyInstance, string $operation, array $params) {
    // Ensure OpenRouterUrlBuilder is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterUrlBuilder::class)) {
        $url_builder_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($url_builder_bootstrap)) {
            require_once $url_builder_bootstrap;
        } else {
            return new WP_Error('openrouter_url_builder_missing_logic', 'OpenRouter URL builder component is not available.');
        }
    }
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterUrlBuilder::build($operation, $params);
}

// --- build-sse-payload.php ---
/**
 * Logic for the build_sse_payload method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $messages Formatted messages/input/contents array.
 * @param string|array|null $system_instruction Formatted system instruction.
 * @param array $ai_params AI parameters.
 * @param string $model Target model/deployment.
 * @return array The formatted request body data for SSE.
 */
function build_sse_payload_logic(
    OpenRouterProviderStrategy $strategyInstance,
    array $messages,
    $system_instruction,
    array $ai_params,
    string $model
): array {
    // Ensure OpenRouterPayloadFormatter is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterPayloadFormatter::class)) {
        $formatter_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($formatter_bootstrap)) {
            require_once $formatter_bootstrap;
        } else {
            return []; // Or throw an error
        }
    }
    // The $system_instruction is now part of the messages array for the formatter
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterPayloadFormatter::format_sse($messages, $system_instruction, $ai_params, $model);
}

// --- build.php ---
/**
 * Logic for the build static method of OpenRouterUrlBuilder.
 *
 * @param string $operation ('chat', 'models', 'stream', 'embeddings', 'embedding_models')
 * @param array  $params Required parameters (base_url, api_version).
 * @return string|WP_Error The full URL or WP_Error.
 */
function build_logic_for_url_builder(string $operation, array $params) {
    $base_url = !empty($params['base_url']) ? rtrim($params['base_url'], '/') : '';
    $api_version = !empty($params['api_version']) ? $params['api_version'] : '';

    if (empty($base_url)) return new WP_Error("missing_base_url_OpenRouter_logic", __('OpenRouter Base URL is required.', 'gpt3-ai-content-generator'));

    $paths = [
        'responses'        => '/responses',
        'models'           => '/models',
        'embeddings'       => '/embeddings',
        'embedding_models' => '/embeddings/models',
    ];

    // OpenRouter runtime chat/stream now targets Responses API.
    $path_key = ($operation === 'chat' || $operation === 'stream') ? 'responses' : $operation;
    $path_segment = $paths[$path_key] ?? null;

    if ($path_segment === null) {
        // translators: %s is the operation name (e.g., "chat" or "stream")
        return new WP_Error('unsupported_operation_OpenRouter_logic', sprintf(__('Operation "%s" not supported for OpenRouter.', 'gpt3-ai-content-generator'), $operation));
    }

    $full_path = $path_segment;
    // Prepend version if it's provided and *not* already in the base URL
    if (!empty($api_version) && strpos($base_url, '/' . trim($api_version, '/')) === false) {
        $full_path = '/' . trim($api_version, '/') . $path_segment;
    }

    return $base_url . $full_path;
}

// --- capabilities.php ---
/**
 * Returns default OpenRouter provider capability map used for runtime guards.
 *
 * Important: this is provider-level default. Model-level resolution may narrow
 * capabilities when synced metadata explicitly indicates limitations.
 *
 * @return array<string, bool>
 */
function get_capability_map_logic(): array {
    return [
        'chat' => true,
        'stream' => true,
        'tools' => true,
        'web_search_plugin' => true,
        'image_input' => true,
        'image_output' => true,
        'image_generation' => true,
        'embeddings' => true,
    ];
}

/**
 * Normalizes a mixed list value into lowercase unique strings.
 *
 * @param mixed $value Raw list value.
 * @return array<int, string>
 */
function normalize_capability_list_logic($value): array {
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $entry) {
        if (!is_string($entry)) {
            continue;
        }
        $entry = strtolower(trim($entry));
        if ($entry !== '') {
            $normalized[] = $entry;
        }
    }

    return array_values(array_unique($normalized));
}

/**
 * Sanitizes capability payload from stored metadata.
 *
 * @param mixed $capabilities Raw stored capabilities.
 * @return array<string, bool>
 */
function sanitize_capabilities_payload_logic($capabilities): array {
    if (!is_array($capabilities)) {
        return [];
    }

    $allowed_keys = array_keys(get_capability_map_logic());
    $sanitized = [];
    foreach ($allowed_keys as $key) {
        if (!array_key_exists($key, $capabilities)) {
            continue;
        }
        $sanitized[$key] = (bool) $capabilities[$key];
    }

    return $sanitized;
}

/**
 * Resolves model-level capabilities from OpenRouter model metadata.
 *
 * @param array<string, mixed> $metadata Synchronized model metadata.
 * @return array<string, bool>
 */
function resolve_model_capabilities_from_metadata_logic(array $metadata): array {
    $resolved = get_capability_map_logic();
    // Model-level image output should be opt-in from model metadata signals.
    $resolved['image_output'] = false;
    $resolved['image_generation'] = false;

    // If model already contains normalized capability payload, use it as base.
    $stored_capabilities = sanitize_capabilities_payload_logic($metadata['capabilities'] ?? null);
    if (!empty($stored_capabilities)) {
        $resolved = array_merge($resolved, $stored_capabilities);
    }

    $input_modalities = normalize_capability_list_logic(
        $metadata['input_modalities'] ?? ($metadata['architecture']['input_modalities'] ?? [])
    );
    if (!empty($input_modalities)) {
        $resolved['image_input'] = in_array('image', $input_modalities, true)
            || in_array('image_url', $input_modalities, true)
            || in_array('input_image', $input_modalities, true);
    }

    $output_modalities = normalize_capability_list_logic(
        $metadata['output_modalities'] ?? ($metadata['architecture']['output_modalities'] ?? [])
    );
    if (!empty($output_modalities)) {
        $supports_image_output = in_array('image', $output_modalities, true)
            || in_array('image_url', $output_modalities, true)
            || in_array('output_image', $output_modalities, true);
        $resolved['image_output'] = $supports_image_output;
        $resolved['image_generation'] = $supports_image_output;
    }

    $supported_features = normalize_capability_list_logic(
        $metadata['supported_features'] ?? ($metadata['supportedFeatures'] ?? [])
    );
    if (!empty($supported_features)) {
        $supports_tools_feature = in_array('tools', $supported_features, true)
            || in_array('tool_use', $supported_features, true)
            || in_array('function_calling', $supported_features, true)
            || in_array('function-calling', $supported_features, true)
            || in_array('plugins', $supported_features, true);

        $supports_web_feature = in_array('web_search', $supported_features, true)
            || in_array('web-search', $supported_features, true)
            || in_array('web', $supported_features, true)
            || $supports_tools_feature;

        $supports_image_generation_feature = in_array('image_generation', $supported_features, true)
            || in_array('image-generation', $supported_features, true);

        // Treat explicit supported_features payload as authoritative for these capabilities.
        $resolved['tools'] = $supports_tools_feature;
        $resolved['web_search_plugin'] = $supports_web_feature;
        $resolved['image_generation'] = $supports_image_generation_feature || $resolved['image_generation'];
        $resolved['image_output'] = $resolved['image_generation'];

        if (in_array('embeddings', $supported_features, true) || in_array('embedding', $supported_features, true)) {
            $resolved['embeddings'] = true;
        }
    }

    $supported_parameters = normalize_capability_list_logic(
        $metadata['supported_parameters']
            ?? ($metadata['supportedParameters']
                ?? ($metadata['supported_sampling_parameters'] ?? []))
    );
    if (!empty($supported_parameters)) {
        $supports_tools_param = in_array('plugins', $supported_parameters, true)
            || in_array('tools', $supported_parameters, true)
            || in_array('tool_choice', $supported_parameters, true)
            || in_array('parallel_tool_calls', $supported_parameters, true);

        if (empty($supported_features)) {
            // If we do not have explicit feature metadata, use parameter metadata directly.
            $resolved['tools'] = $supports_tools_param;
            $resolved['web_search_plugin'] = $supports_tools_param;
        } else {
            // If feature metadata exists, treat parameter metadata as supplementary.
            $resolved['tools'] = !empty($resolved['tools']) || $supports_tools_param;
            $resolved['web_search_plugin'] = !empty($resolved['web_search_plugin']) || $supports_tools_param;
        }
    }

    // Keep compatibility with OpenRouter pricing hints.
    if (array_key_exists('pricing_web_search', $metadata)) {
        $resolved['web_search_plugin'] = true;
    }

    // Resolve image output strictly:
    // - If explicit image metadata exists (output modalities or features), trust it.
    // - Otherwise, use conservative legacy heuristics (model id/name patterns + Auto Router).
    $supports_image_output_by_modality = in_array('image', $output_modalities, true)
        || in_array('image_url', $output_modalities, true)
        || in_array('output_image', $output_modalities, true);

    $supports_image_generation_by_feature = in_array('image_generation', $supported_features, true)
        || in_array('image-generation', $supported_features, true)
        || in_array('image_output', $supported_features, true)
        || in_array('image-output', $supported_features, true);

    $model_id_l = strtolower((string) ($metadata['id'] ?? ''));
    $model_name_l = strtolower((string) ($metadata['name'] ?? ''));
    $image_haystack = trim($model_id_l . ' ' . $model_name_l);
    $is_auto_router = $model_id_l === 'openrouter/auto'
        || $model_id_l === 'auto'
        || strpos($model_id_l, 'auto-router') !== false
        || strpos($model_name_l, 'auto router') !== false;

    $looks_like_image_model = strpos($image_haystack, 'image') !== false
        || strpos($image_haystack, 'gpt-image') !== false
        || strpos($image_haystack, 'flux') !== false
        || strpos($image_haystack, 'stable-diffusion') !== false
        || strpos($image_haystack, 'sdxl') !== false
        || strpos($image_haystack, 'riverflow') !== false
        || strpos($image_haystack, 'imagen') !== false
        || strpos($image_haystack, 'nano banana') !== false
        || strpos($image_haystack, 'nano-banana') !== false;

    $has_explicit_image_metadata = !empty($output_modalities) || !empty($supported_features);
    if ($is_auto_router) {
        $supports_image = true;
        $resolved['image_output'] = true;
        $resolved['image_generation'] = true;
    } elseif ($has_explicit_image_metadata) {
        $supports_image = $supports_image_output_by_modality || $supports_image_generation_by_feature;
        $resolved['image_output'] = $supports_image;
        $resolved['image_generation'] = $supports_image;
    } else {
        $supports_image = $looks_like_image_model;
        $resolved['image_output'] = $supports_image;
        $resolved['image_generation'] = $supports_image;
    }

    // Keep aliases aligned.
    if ($resolved['image_output'] !== $resolved['image_generation']) {
        $resolved['image_output'] = $resolved['image_generation'] || $resolved['image_output'];
        $resolved['image_generation'] = $resolved['image_output'];
    }

    return $resolved;
}

/**
 * Finds synchronized OpenRouter metadata for a specific model id.
 *
 * @param string $model_id OpenRouter model id.
 * @return array<string, mixed>|null
 */
function get_model_metadata_logic(string $model_id): ?array {
    $model_id = sanitize_text_field($model_id);
    if ($model_id === '') {
        return null;
    }

    $synced_models = get_option('aipkit_openrouter_model_list', []);
    if (!is_array($synced_models)) {
        return null;
    }

    foreach ($synced_models as $model) {
        if (!is_array($model)) {
            continue;
        }
        $candidate_id = isset($model['id']) ? sanitize_text_field((string) $model['id']) : '';
        if ($candidate_id !== '' && $candidate_id === $model_id) {
            return $model;
        }
    }

    return null;
}

/**
 * Resolves model-level capabilities for an OpenRouter model id.
 *
 * @param string $model_id OpenRouter model id.
 * @return array<string, bool>
 */
function resolve_model_capabilities_logic(string $model_id): array {
    $metadata = get_model_metadata_logic($model_id);
    if (!is_array($metadata)) {
        return get_capability_map_logic();
    }

    return resolve_model_capabilities_from_metadata_logic($metadata);
}

/**
 * Checks whether selected OpenRouter model appears to support web search plugin usage.
 *
 * Decision policy:
 * - If synced metadata explicitly declares support (`supported_features` / `supported_parameters`), trust it.
 * - If metadata exists and explicitly excludes tool/web capabilities, return false.
 * - If metadata is missing, return true to avoid breaking existing installs.
 *
 * @param string $model_id OpenRouter model id.
 * @return bool
 */
function model_supports_web_search_plugin_logic(string $model_id): bool {
    $resolved = resolve_model_capabilities_logic($model_id);
    return !empty($resolved['web_search_plugin']);
}

/**
 * Checks whether selected OpenRouter model appears to support image output.
 *
 * @param string $model_id OpenRouter model id.
 * @return bool
 */
function model_supports_image_output_logic(string $model_id): bool {
    $resolved = resolve_model_capabilities_logic($model_id);
    return !empty($resolved['image_output']);
}

/**
 * Returns normalized output modalities for a synced OpenRouter model.
 *
 * @param string $model_id OpenRouter model id.
 * @return array<int, string>
 */
function model_output_modalities_logic(string $model_id): array {
    $metadata = get_model_metadata_logic($model_id);
    if (!is_array($metadata)) {
        return [];
    }

    return normalize_capability_list_logic(
        $metadata['output_modalities'] ?? ($metadata['architecture']['output_modalities'] ?? [])
    );
}

/**
 * Checks whether selected OpenRouter model is a documented image_config-capable family.
 *
 * OpenRouter's model metadata does not consistently expose image_config in
 * supported_parameters, so keep this conservative and limited to documented
 * image model families instead of sending image_config to every image model.
 *
 * @param string $model_id OpenRouter model id.
 * @return bool
 */
function model_supports_image_config_logic(string $model_id): bool {
    $model_id = strtolower(sanitize_text_field($model_id));
    if ($model_id === '') {
        return false;
    }

    $metadata = get_model_metadata_logic($model_id);
    if (is_array($metadata)) {
        $supported_parameters = normalize_capability_list_logic(
            $metadata['supported_parameters']
                ?? ($metadata['supportedParameters']
                    ?? ($metadata['supported_sampling_parameters'] ?? []))
        );
        foreach ($supported_parameters as $parameter) {
            if (
                $parameter === 'image_config'
                || $parameter === 'image_config.aspect_ratio'
                || $parameter === 'image_config.image_size'
            ) {
                return true;
            }
        }
    }

    $is_google_image_model = strpos($model_id, 'google/gemini-') === 0
        && strpos($model_id, 'image') !== false;
    $is_flux_model = strpos($model_id, 'black-forest-labs/flux') === 0;
    $is_recraft_model = strpos($model_id, 'recraft/') === 0;
    $is_sourceful_model = strpos($model_id, 'sourceful/riverflow') === 0;

    return $is_google_image_model || $is_flux_model || $is_recraft_model || $is_sourceful_model;
}

/**
 * Checks whether selected OpenRouter model appears to support image editing.
 * Edit support requires both image input and image output capabilities.
 *
 * @param string $model_id OpenRouter model id.
 * @return bool
 */
function model_supports_image_editing_logic(string $model_id): bool {
    $resolved = resolve_model_capabilities_logic($model_id);
    return !empty($resolved['image_input']) && !empty($resolved['image_output']);
}

/**
 * Sanitizes web search plugin configuration for OpenRouter Responses payloads.
 *
 * @param array $raw_config Raw web search tool config.
 * @return array Sanitized web plugin config fields.
 */
function sanitize_web_search_config_logic(array $raw_config): array {
    $sanitized = [];

    if (isset($raw_config['max_results'])) {
        $max_results = absint($raw_config['max_results']);
        if ($max_results > 0) {
            $sanitized['max_results'] = max(1, min($max_results, 10));
        }
    }

    if (!empty($raw_config['search_prompt'])) {
        $search_prompt = AIPKit_Prompt_Sanitizer::sanitize($raw_config['search_prompt']);
        if ($search_prompt !== '') {
            $sanitized['search_prompt'] = $search_prompt;
        }
    }

    if (!empty($raw_config['engine'])) {
        $engine = sanitize_key((string) $raw_config['engine']);
        if (in_array($engine, ['native', 'exa'], true)) {
            $sanitized['engine'] = $engine;
        }
    }

    return $sanitized;
}

// --- format-chat-payload.php ---
/**
 * Logic for the format_chat_payload method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $user_message The user's message (already included in history for OpenRouter).
 * @param string $instructions System instructions.
 * @param array  $history Conversation history.
 * @param array  $ai_params AI parameters (temperature, max_tokens, etc.).
 * @param string $model Model name (required by OpenRouter in payload).
 * @return array The formatted request body data.
 */
function format_chat_payload_logic(
    OpenRouterProviderStrategy $strategyInstance,
    string $user_message,
    string $instructions,
    array $history,
    array $ai_params,
    string $model
): array {
    // Ensure OpenRouterPayloadFormatter is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterPayloadFormatter::class)) {
        $formatter_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($formatter_bootstrap)) {
            require_once $formatter_bootstrap;
        } else {
            return []; // Or throw an error
        }
    }
    // The latest user_message should be part of the history for format_chat
    $final_history = $history;
    if (!empty($user_message)) {
        $last_msg = end($final_history);
        if (!$last_msg || $last_msg['role'] !== 'user' || $last_msg['content'] !== $user_message) {
            $final_history[] = ['role' => 'user', 'content' => $user_message];
        }
    }
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterPayloadFormatter::format_chat($instructions, $final_history, $ai_params, $model);
}

// --- format-chat.php ---
/**
 * Logic for the format_chat static method of OpenRouterPayloadFormatter.
 *
 * @param string $instructions System instructions.
 * @param array  $history Conversation history.
 * @param array  $ai_params AI parameters (temperature, max_tokens, etc.).
 * @param string $model Model name.
 * @return array The formatted payload.
 */
function format_chat_logic_for_payload_formatter(string $instructions, array $history, array $ai_params, string $model): array {
    return _shared_format_logic($instructions, $history, $ai_params, $model);
}

// --- format-sse.php ---
/**
 * Logic for the format_sse static method of OpenRouterPayloadFormatter.
 *
 * @param array  $messages Formatted messages array (user/assistant).
 * @param string $system_instruction System instructions.
 * @param array  $ai_params AI parameters.
 * @param string $model Model name.
 * @return array The formatted SSE payload.
 */
function format_sse_logic_for_payload_formatter(array $messages, string $system_instruction, array $ai_params, string $model): array {
    // The history array already contains user/assistant messages; system instructions are merged by shared formatter.
    $payload = _shared_format_logic($system_instruction, $messages, $ai_params, $model);
    $payload['stream'] = true;
    return $payload;
}

// --- generate-embeddings.php ---
/**
 * Logic for the generate_embeddings method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string|array $input The input text or array of texts.
 * @param array $api_params Provider-specific API connection parameters.
 * @param array $options Embedding options (model, dimensions, encoding_format, etc.).
 * @return array|WP_Error ['embeddings' => array, 'usage' => array|null] or WP_Error.
 */
function generate_embeddings_logic(
    OpenRouterProviderStrategy $strategyInstance,
    $input,
    array $api_params,
    array $options = []
) {
    $model = isset($options['model']) ? sanitize_text_field((string) $options['model']) : '';
    if ($model === '') {
        return new WP_Error(
            'missing_openrouter_embedding_model_logic',
            __('OpenRouter embedding model ID is required.', 'gpt3-ai-content-generator')
        );
    }

    $url = $strategyInstance->build_api_url('embeddings', $api_params);
    if (is_wp_error($url)) {
        return $url;
    }

    $headers = $strategyInstance->get_api_headers($api_params['api_key'] ?? '', 'embeddings');
    $request_options = $strategyInstance->get_request_options('embeddings');

    $payload = [
        'model' => $model,
        'input' => $input,
    ];

    if (isset($options['dimensions']) && absint($options['dimensions']) > 0) {
        $payload['dimensions'] = absint($options['dimensions']);
    }

    if (isset($options['encoding_format'])) {
        $encoding_format = sanitize_key((string) $options['encoding_format']);
        if (in_array($encoding_format, ['float', 'base64'], true)) {
            $payload['encoding_format'] = $encoding_format;
        }
    }

    $request_body_json = wp_json_encode($payload);
    $response = wp_remote_post(
        $url,
        array_merge(
            $request_options,
            [
                'headers' => $headers,
                'body'    => $request_body_json,
            ]
        )
    );

    if (is_wp_error($response)) {
        return new WP_Error(
            'openrouter_embedding_http_error_logic',
            __('HTTP error during OpenRouter embedding generation.', 'gpt3-ai-content-generator')
        );
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded_response = $strategyInstance->decode_json($body, 'OpenRouter Embeddings');

    if ($status_code !== 200 || is_wp_error($decoded_response)) {
        $error_msg = is_wp_error($decoded_response)
            ? $decoded_response->get_error_message()
            : $strategyInstance->parse_error_response($body, $status_code);
        $error_data = $strategyInstance->build_http_error_data_with_retry_after($response, $status_code);
        /* translators: %1$d: HTTP status code, %2$s: API error message. */
        return new WP_Error(
            'openrouter_embedding_api_error_logic',
            sprintf(
                /* translators: 1: HTTP status code, 2: API error message. */
                __('OpenRouter Embeddings API Error (%1$d): %2$s', 'gpt3-ai-content-generator'),
                $status_code,
                esc_html($error_msg)
            ),
            $error_data
        );
    }

    $embeddings = [];
    if (isset($decoded_response['data']) && is_array($decoded_response['data'])) {
        foreach ($decoded_response['data'] as $item) {
            if (isset($item['embedding']) && is_array($item['embedding'])) {
                $embeddings[] = $item['embedding'];
            }
        }
    }

    if (empty($embeddings)) {
        return new WP_Error(
            'openrouter_embedding_no_data_logic',
            __('No embedding data found in OpenRouter response.', 'gpt3-ai-content-generator')
        );
    }

    $usage = null;
    if (isset($decoded_response['usage']) && is_array($decoded_response['usage'])) {
        $usage = [
            'input_tokens'  => $decoded_response['usage']['input_tokens'] ?? $decoded_response['usage']['prompt_tokens'] ?? 0,
            'total_tokens'  => $decoded_response['usage']['total_tokens'] ?? 0,
            'provider_raw'  => $decoded_response['usage'],
        ];
    }

    return [
        'embeddings' => $embeddings,
        'usage' => $usage,
    ];
}

// --- get-api-headers.php ---
/**
 * Logic for the get_api_headers method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $api_key The API key for the provider.
 * @param string $operation The specific operation being performed.
 * @return array Key-value array of headers.
 */
function get_api_headers_logic(OpenRouterProviderStrategy $strategyInstance, string $api_key, string $operation): array {
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
        'HTTP-Referer' => get_bloginfo('url'),
        'X-Title' => 'AIPKit',
    ];
    if ($operation === 'stream') {
        $headers['Accept'] = 'text/event-stream';
        $headers['Cache-Control'] = 'no-cache';
    }
    return $headers;
}

// --- get-embedding-models.php ---
/**
 * Logic for fetching OpenRouter embedding models from /embeddings/models.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The OpenRouter strategy instance.
 * @param array $api_params Connection parameters (api_key, base_url, api_version).
 * @return array|WP_Error Formatted list [['id' => ..., 'name' => ...]] or WP_Error.
 */
function get_embedding_models_logic(OpenRouterProviderStrategy $strategyInstance, array $api_params) {
    $url = $strategyInstance->build_api_url('embedding_models', $api_params);
    if (is_wp_error($url)) {
        return $url;
    }

    $headers = $strategyInstance->get_api_headers($api_params['api_key'] ?? '', 'embedding_models');
    $options = $strategyInstance->get_request_options('embedding_models');
    $options['method'] = 'GET';

    $response = wp_remote_get($url, array_merge($options, ['headers' => $headers]));
    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($status_code !== 200) {
        $error_msg = $strategyInstance->parse_error_response($body, $status_code);
        /* translators: %1$d: HTTP status code, %2$s: API error message. */
        return new WP_Error(
            'api_error_openrouter_embedding_models_logic',
            sprintf(
                /* translators: 1: HTTP status code, 2: API error message. */
                __('OpenRouter Embedding Models API Error (HTTP %1$d): %2$s', 'gpt3-ai-content-generator'),
                $status_code,
                esc_html($error_msg)
            )
        );
    }

    $decoded = $strategyInstance->decode_json($body, 'OpenRouter Embedding Models');
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    $raw_models = $decoded['data'] ?? [];
    if (!is_array($raw_models)) {
        return [];
    }

    $formatted = [];
    foreach ($raw_models as $model) {
        if (is_string($model)) {
            $id = sanitize_text_field($model);
            if ($id === '') {
                continue;
            }
            $formatted[] = ['id' => $id, 'name' => $id];
            continue;
        }

        if (!is_array($model)) {
            continue;
        }

        $id = isset($model['id']) ? sanitize_text_field((string) $model['id']) : '';
        if ($id === '') {
            continue;
        }
        $name = isset($model['name']) ? sanitize_text_field((string) $model['name']) : $id;
        $formatted[] = ['id' => $id, 'name' => $name];
    }

    usort(
        $formatted,
        static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
    );

    return $formatted;
}

/**
 * Logic for the get_models method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $api_params Connection parameters (api_key, base_url, etc.).
 * @return array|WP_Error Formatted list [['id' => ..., 'name' => ...]] or WP_Error.
 */
function get_models_logic(OpenRouterProviderStrategy $strategyInstance, array $api_params) {
    $url = $strategyInstance->build_api_url('models', $api_params);
    if (is_wp_error($url)) {
        return $url;
    }

    $headers = $strategyInstance->get_api_headers($api_params['api_key'] ?? '', 'models');
    $options = $strategyInstance->get_request_options('models');
    $options['method'] = 'GET';

    $response = wp_remote_get($url, array_merge($options, ['headers' => $headers]));
    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        $error_msg = $strategyInstance->parse_error_response($body, $status_code);
        return new WP_Error('api_error_openrouter_models_logic', sprintf('OpenRouter API Error (HTTP %d): %s', $status_code, esc_html($error_msg)));
    }

    // decode_json is public in BaseProviderStrategy
    $decoded = $strategyInstance->decode_json($body, 'OpenRouter Models');
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    $raw_models = $decoded['data'] ?? [];
    if (!is_array($raw_models)) {
        return [];
    }

    return format_models_logic($raw_models);
}

/**
 * Logic for syncing OpenRouter image-output models.
 *
 * OpenRouter's general /models response does not currently include every
 * image-only model family, so image model lists use the output_modalities
 * filtered endpoint.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $api_params Connection parameters (api_key, base_url, etc.).
 * @return array|WP_Error
 */
function get_image_models_logic(OpenRouterProviderStrategy $strategyInstance, array $api_params) {
    $url = $strategyInstance->build_api_url('models', $api_params);
    if (is_wp_error($url)) {
        return $url;
    }

    $url .= (strpos($url, '?') !== false ? '&' : '?') . 'output_modalities=image';

    $headers = $strategyInstance->get_api_headers($api_params['api_key'] ?? '', 'models');
    $options = $strategyInstance->get_request_options('models');
    $options['method'] = 'GET';

    $response = wp_remote_get($url, array_merge($options, ['headers' => $headers]));
    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        $error_msg = $strategyInstance->parse_error_response($body, $status_code);
        return new WP_Error(
            'api_error_openrouter_image_models_logic',
            sprintf('OpenRouter Image Models API Error (HTTP %d): %s', $status_code, esc_html($error_msg)),
            ['status' => $status_code]
        );
    }

    $decoded = $strategyInstance->decode_json($body, 'OpenRouter Image Models');
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    $raw_models = $decoded['data'] ?? [];
    if (!is_array($raw_models)) {
        return [];
    }

    return format_models_logic($raw_models);
}

/**
 * @param array<int, mixed> $raw_models
 * @return array<int, array<string, mixed>>
 */
function format_models_logic(array $raw_models): array {
    $formatted = [];
    foreach ($raw_models as $model) {
        if (!is_array($model)) {
            continue;
        }

        $id = isset($model['id']) ? sanitize_text_field((string) $model['id']) : '';
        if ($id === '') {
            continue;
        }

        $name = isset($model['name']) ? sanitize_text_field((string) $model['name']) : $id;
        $item = [
            'id'   => $id,
            'name' => $name,
            'status'  => isset($model['status']) ? sanitize_text_field((string) $model['status']) : null,
            'version' => isset($model['version']) ? sanitize_text_field((string) $model['version']) : null,
        ];

        $supported_parameters = [];
        $raw_supported_parameters = $model['supported_parameters']
            ?? $model['supportedParameters']
            ?? $model['supported_sampling_parameters']
            ?? [];
        if (is_array($raw_supported_parameters)) {
            foreach ($raw_supported_parameters as $param) {
                if (!is_string($param)) {
                    continue;
                }
                $param = strtolower(trim($param));
                if ($param !== '') {
                    $supported_parameters[] = $param;
                }
            }
            $supported_parameters = array_values(array_unique($supported_parameters));
        }
        if (!empty($supported_parameters)) {
            $item['supported_parameters'] = $supported_parameters;
        }

        $input_modalities = [];
        $raw_input_modalities = [];
        if (isset($model['input_modalities']) && is_array($model['input_modalities'])) {
            $raw_input_modalities = $model['input_modalities'];
        } elseif (isset($model['architecture']['input_modalities']) && is_array($model['architecture']['input_modalities'])) {
            $raw_input_modalities = $model['architecture']['input_modalities'];
        }
        foreach ($raw_input_modalities as $modality) {
            if (!is_string($modality)) {
                continue;
            }
            $modality = strtolower(trim($modality));
            if ($modality !== '') {
                $input_modalities[] = $modality;
            }
        }
        $input_modalities = array_values(array_unique($input_modalities));
        if (!empty($input_modalities)) {
            $item['input_modalities'] = $input_modalities;
        }

        $output_modalities = [];
        $raw_output_modalities = [];
        if (isset($model['output_modalities']) && is_array($model['output_modalities'])) {
            $raw_output_modalities = $model['output_modalities'];
        } elseif (isset($model['architecture']['output_modalities']) && is_array($model['architecture']['output_modalities'])) {
            $raw_output_modalities = $model['architecture']['output_modalities'];
        }
        foreach ($raw_output_modalities as $modality) {
            if (!is_string($modality)) {
                continue;
            }
            $modality = strtolower(trim($modality));
            if ($modality !== '') {
                $output_modalities[] = $modality;
            }
        }
        $output_modalities = array_values(array_unique($output_modalities));
        if (!empty($output_modalities)) {
            $item['output_modalities'] = $output_modalities;
        }

        $supported_features = [];
        $raw_supported_features = $model['supported_features'] ?? $model['supportedFeatures'] ?? [];
        if (is_array($raw_supported_features)) {
            foreach ($raw_supported_features as $feature) {
                if (!is_string($feature)) {
                    continue;
                }
                $feature = strtolower(trim($feature));
                if ($feature !== '') {
                    $supported_features[] = $feature;
                }
            }
            $supported_features = array_values(array_unique($supported_features));
        }
        if (!empty($supported_features)) {
            $item['supported_features'] = $supported_features;
        }

        // Keep a tiny pricing hint for runtime feature guards.
        if (isset($model['pricing']) && is_array($model['pricing']) && isset($model['pricing']['web_search'])) {
            $item['pricing_web_search'] = sanitize_text_field((string) $model['pricing']['web_search']);
        }

        // Centralized normalized capability contract consumed across modules.
        $item['capabilities'] = resolve_model_capabilities_from_metadata_logic($item);

        $formatted[] = $item;
    }

    usort(
        $formatted,
        static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
    );

    return $formatted;
}

// --- map-sse-event.php ---
/**
 * Maps a normalized OpenRouter SSE event into an internal typed event.
 *
 * @param array<string, mixed> $decoded_event
 * @return array<string, mixed>
 */
function map_sse_event_logic_for_response_parser(array $decoded_event): array {
    $event_type = isset($decoded_event['event']) && is_string($decoded_event['event']) ? $decoded_event['event'] : 'message';
    $payload = isset($decoded_event['payload']) && is_array($decoded_event['payload']) ? $decoded_event['payload'] : [];

    if ($event_type === '[DONE]') {
        return [
            'kind' => 'done',
            'event' => $event_type,
        ];
    }

    if (in_array($event_type, ['ping', 'keepalive'], true)) {
        return [
            'kind' => 'skip',
            'event' => $event_type,
        ];
    }

    if ($event_type === 'error' || isset($payload['error'])) {
        return [
            'kind' => 'error',
            'event' => $event_type,
            'message' => parse_error_logic_for_response_parser($payload, 500),
        ];
    }

    switch ($event_type) {
        case 'response.created':
        case 'response.in_progress':
        case 'response.queued':
        case 'response.web_search_call.in_progress':
        case 'response.web_search_call.searching':
        case 'response.web_search_call.completed':
        case 'response.file_search_call.in_progress':
        case 'response.file_search_call.searching':
        case 'response.file_search_call.completed':
        case 'response.image_generation_call.in_progress':
        case 'response.image_generation_call.generating':
        case 'response.image_generation_call.completed':
        case 'response.output_item.added':
        case 'response.output_item.done':
        case 'response.content_part.added':
        case 'response.output_text.done':
        case 'response.reasoning.delta':
        case 'response.reasoning.done':
        case 'response.function_call_arguments.delta':
        case 'response.function_call_arguments.done':
        case 'tool.preliminary_result':
        case 'tool.result':
            return [
                'kind' => 'status',
                'event' => $event_type,
                'status' => build_sse_status_logic_for_response_parser($event_type, $payload),
            ];

        case 'response.output_text.delta':
        case 'response.content_part.delta':
            $delta_text = isset($payload['delta']) ? (string) $payload['delta'] : '';
            if ($delta_text === '') {
                return [
                    'kind' => 'skip',
                    'event' => $event_type,
                ];
            }

            return [
                'kind' => 'delta',
                'event' => $event_type,
                'text' => $delta_text,
            ];

        case 'response.refusal.delta':
            $refusal_text = isset($payload['delta']) ? (string) $payload['delta'] : '';
            if ($refusal_text === '') {
                return [
                    'kind' => 'skip',
                    'event' => $event_type,
                ];
            }

            return [
                'kind' => 'warning',
                'event' => $event_type,
                'text' => sprintf(' (%s: %s)', __('Refusal', 'gpt3-ai-content-generator'), $refusal_text),
            ];

        case 'response.done':
        case 'response.completed':
        case 'response.incomplete':
            $warning_text = null;
            if ($event_type === 'response.incomplete') {
                $reason = $payload['response']['incomplete_details']['reason'] ?? 'unknown';
                $warning_text = sprintf(' (%s: %s)', __('Incomplete', 'gpt3-ai-content-generator'), $reason);
            }

            return [
                'kind' => 'completion',
                'event' => $event_type,
                'usage' => extract_sse_usage_logic_for_response_parser($payload),
                'status' => build_sse_status_logic_for_response_parser($event_type, $payload),
                'warning_text' => $warning_text,
            ];

        case 'response.failed':
            $error_message = $payload['response']['error']['message'] ?? __('Response failed', 'gpt3-ai-content-generator');

            return [
                'kind' => 'error',
                'event' => $event_type,
                'message' => sprintf(' (%s: %s)', __('Error', 'gpt3-ai-content-generator'), $error_message),
            ];

        default:
            // Backward-compat fallback for chat-completions stream shape.
            return map_chat_completions_fallback_sse_event_logic_for_response_parser($payload, $event_type);
    }
}

/**
 * Builds the status payload that gets forwarded to the public stream formatter.
 *
 * @param string $type
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function build_sse_status_logic_for_response_parser(string $type, array $payload): array {
    $status = ['type' => $type];

    if (isset($payload['response']['status'])) {
        $status['status'] = $payload['response']['status'];
    }
    if (isset($payload['response']['id'])) {
        $status['response_id'] = $payload['response']['id'];
    } elseif (isset($payload['response_id'])) {
        $status['response_id'] = $payload['response_id'];
    }
    if (isset($payload['item_id'])) {
        $status['item_id'] = $payload['item_id'];
    }
    if (isset($payload['output_index'])) {
        $status['output_index'] = $payload['output_index'];
    }
    if (isset($payload['call_id'])) {
        $status['call_id'] = $payload['call_id'];
    }
    if (isset($payload['item']['id'])) {
        $status['item_id'] = $payload['item']['id'];
    }
    if (isset($payload['item']['type'])) {
        $status['item_type'] = $payload['item']['type'];
    }
    if (isset($payload['item']['status'])) {
        $status['item_status'] = $payload['item']['status'];
    }
    if (isset($payload['item']['name'])) {
        $status['name'] = $payload['item']['name'];
    }

    return $status;
}

/**
 * Extracts usage information from a completion payload using the existing public parse shape.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null
 */
function extract_sse_usage_logic_for_response_parser(array $payload): ?array {
    if (isset($payload['response']['usage']) && is_array($payload['response']['usage'])) {
        $usage = $payload['response']['usage'];
    } elseif (isset($payload['usage']) && is_array($payload['usage'])) {
        $usage = $payload['usage'];
    } else {
        return null;
    }

    return [
        'input_tokens' => $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0,
        'output_tokens' => $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0,
        'total_tokens' => $usage['total_tokens'] ?? 0,
        'provider_raw' => $usage,
    ];
}

/**
 * Handles legacy chat-completions-shaped fallback stream payloads.
 *
 * @param array<string, mixed> $payload
 * @param string $event_type
 * @return array<string, mixed>
 */
function map_chat_completions_fallback_sse_event_logic_for_response_parser(array $payload, string $event_type): array {
    $usage = extract_sse_usage_logic_for_response_parser($payload);
    $delta_text = null;
    if (isset($payload['choices'][0]['delta']['content'])) {
        $delta_text = (string) $payload['choices'][0]['delta']['content'];
        if ($delta_text === '') {
            $delta_text = null;
        }
    }

    $warning_text = null;
    if (isset($payload['choices'][0]['finish_reason']) && $payload['choices'][0]['finish_reason'] === 'content_filter') {
        $warning_text = sprintf(' (%s)', __('Warning: Content Filtered', 'gpt3-ai-content-generator'));
    }

    if ($delta_text === null && $usage === null && $warning_text === null) {
        return [
            'kind' => 'skip',
            'event' => $event_type,
        ];
    }

    return [
        'kind' => 'legacy_chunk',
        'event' => $event_type,
        'usage' => $usage,
        'text' => $delta_text,
        'warning_text' => $warning_text,
    ];
}

// --- parse-chat-response.php ---
/**
 * Logic for the parse_chat_response method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param array $decoded_response The decoded JSON response body.
 * @param array $request_data The original request data sent (unused here).
 * @return array|WP_Error ['content' => string, 'usage' => array|null] or WP_Error.
 */
function parse_chat_response_logic(
    OpenRouterProviderStrategy $strategyInstance,
    array $decoded_response,
    array $request_data
) {
    // Ensure OpenRouterResponseParser is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return new WP_Error('openrouter_response_parser_missing_logic', 'OpenRouter response parser component is not available.');
        }
    }
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::parse_chat($decoded_response);
}

// --- parse-chat.php ---
/**
 * Logic for the parse_chat static method of OpenRouterResponseParser.
 *
 * @param array $decoded_response The decoded JSON response.
 * @return array|WP_Error ['content' => string, 'usage' => array|null] or WP_Error.
 */
function parse_chat_logic_for_response_parser(array $decoded_response) {
    $content = null;
    $usage = null;

    if (isset($decoded_response['status']) && $decoded_response['status'] === 'failed') {
        $failed_message = parse_error_logic_for_response_parser($decoded_response, 500);
        return new WP_Error('openrouter_failed_response_logic', $failed_message);
    }

    if (isset($decoded_response['output_text']) && is_string($decoded_response['output_text'])) {
        $output_text = trim($decoded_response['output_text']);
        if ($output_text !== '') {
            $content = $output_text;
        }
    }

    if ($content === null && isset($decoded_response['output']) && is_array($decoded_response['output'])) {
        $parts = [];
        foreach ($decoded_response['output'] as $output_item) {
            if (!is_array($output_item) || ($output_item['type'] ?? '') !== 'message') {
                continue;
            }
            if (empty($output_item['content']) || !is_array($output_item['content'])) {
                continue;
            }
            foreach ($output_item['content'] as $content_item) {
                if (!is_array($content_item)) {
                    continue;
                }
                $part_type = $content_item['type'] ?? '';
                if (($part_type === 'output_text' || $part_type === 'text') && isset($content_item['text']) && is_string($content_item['text'])) {
                    $parts[] = $content_item['text'];
                }
            }
        }
        if (!empty($parts)) {
            $joined = trim(implode('', $parts));
            if ($joined !== '') {
                $content = $joined;
            }
        }
    }

    // Backward-compat fallback for chat-completions shaped payloads.
    if ($content === null) {
        if (isset($decoded_response['choices'][0]['message']['content']) && is_string($decoded_response['choices'][0]['message']['content'])) {
            $content = trim($decoded_response['choices'][0]['message']['content']);
        } elseif (isset($decoded_response['choices'][0]['delta']['content']) && is_string($decoded_response['choices'][0]['delta']['content'])) {
            $content = trim($decoded_response['choices'][0]['delta']['content']);
        } elseif (isset($decoded_response['choices'][0]['text']) && is_string($decoded_response['choices'][0]['text'])) {
            $content = trim($decoded_response['choices'][0]['text']);
        }
    }

    if (isset($decoded_response['status']) && $decoded_response['status'] === 'incomplete') {
        $reason = $decoded_response['incomplete_details']['reason'] ?? 'unknown';
        if ($content !== null && $content !== '') {
            $content .= sprintf(' (%s: %s)', __('Incomplete', 'gpt3-ai-content-generator'), $reason);
        } else {
            /* translators: %s: The reason why the OpenRouter response was incomplete. */
            return new WP_Error('openrouter_incomplete_response_logic', sprintf(__('Response incomplete due to: %s', 'gpt3-ai-content-generator'), $reason));
        }
    }

    $usage_source = null;
    if (isset($decoded_response['usage']) && is_array($decoded_response['usage'])) {
        $usage_source = $decoded_response['usage'];
    } elseif (isset($decoded_response['response']['usage']) && is_array($decoded_response['response']['usage'])) {
        $usage_source = $decoded_response['response']['usage'];
    }
    if ($usage_source !== null) {
        $usage = [
            'input_tokens'  => $usage_source['input_tokens'] ?? $usage_source['prompt_tokens'] ?? 0,
            'output_tokens' => $usage_source['output_tokens'] ?? $usage_source['completion_tokens'] ?? 0,
            'total_tokens'  => $usage_source['total_tokens'] ?? 0,
            'provider_raw'  => $usage_source,
        ];
    }

    if ($content === null) {
        if (isset($decoded_response['choices'][0]['finish_reason']) && $decoded_response['choices'][0]['finish_reason'] === 'content_filter') {
            return new WP_Error('content_filter_logic', __('Response blocked due to content filtering.', 'gpt3-ai-content-generator'));
        }
        return new WP_Error('invalid_response_structure_openrouter_logic', __('Unexpected response structure from OpenRouter API.', 'gpt3-ai-content-generator'));
    }

    return ['content' => $content, 'usage' => $usage];
}

// --- parse-error-response.php ---
/**
 * Logic for the parse_error_response method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param mixed $response_body The raw or decoded error response body.
 * @param int $status_code The HTTP status code.
 * @return string A user-friendly error message.
 */
function parse_error_response_logic(
    OpenRouterProviderStrategy $strategyInstance,
    $response_body,
    int $status_code
): string {
    // Ensure OpenRouterResponseParser is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return "OpenRouter response parser component is not available."; // Fallback error
        }
    }
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::parse_error($response_body, $status_code);
}

// --- parse-error.php ---
/**
 * Logic for the parse_error static method of OpenRouterResponseParser.
 *
 * @param mixed $response_body The raw or decoded error response body.
 * @param int $status_code The HTTP status code.
 * @return string A user-friendly error message.
 */
function parse_error_logic_for_response_parser($response_body, int $status_code): string {
    $message = __('An unknown API error occurred.', 'gpt3-ai-content-generator');
    $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

    if (is_array($decoded)) {
        // Check common error structures
        if (!empty($decoded['error']['message'])) {
            $message = $decoded['error']['message'];
            if (!empty($decoded['error']['code'])) { $message .= ' (Code: ' . $decoded['error']['code'] . ')'; }
        } elseif (!empty($decoded['detail'])) { // Sometimes uses 'detail'
            $message = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
        } elseif (!empty($decoded['message'])) { // Top-level message fallback
            $message = $decoded['message'];
        }
    } elseif (is_string($response_body)) {
         $message = substr($response_body, 0, 200); // Raw snippet
    }

    return trim($message);
}

// --- parse-sse-chunk.php ---
/**
 * Logic for the parse_sse_chunk method of OpenRouterProviderStrategy.
 *
 * @param OpenRouterProviderStrategy $strategyInstance The instance of the strategy class.
 * @param string $sse_chunk The raw chunk received from the stream.
 * @param string &$current_buffer The reference to the incomplete buffer for this provider.
 * @return array Result containing delta, usage, flags.
 */
function parse_sse_chunk_logic(
    OpenRouterProviderStrategy $strategyInstance,
    string $sse_chunk,
    string &$current_buffer
): array {
    // Ensure OpenRouterResponseParser is available
    if (!class_exists(\WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::class)) {
        $parser_bootstrap = dirname(__FILE__) . '/bootstrap-provider-strategy.php';
        if (file_exists($parser_bootstrap)) {
            require_once $parser_bootstrap;
        } else {
            return ['delta' => null, 'usage' => null, 'is_error' => true, 'is_warning' => false, 'is_done' => true];
        }
    }
    return \WPAICG\Core\Providers\OpenRouter\OpenRouterResponseParser::parse_sse_chunk($sse_chunk, $current_buffer);
}

// --- parse-sse.php ---
require_once __DIR__ . '/../shared/extract-sse-event-blocks.php';
require_once __DIR__ . '/../shared/decode-sse-event-block.php';

/**
 * Logic for the parse_sse_chunk static method of OpenRouterResponseParser.
 *
 * @param string $sse_chunk The raw chunk received.
 * @param string &$current_buffer Reference to the incomplete buffer.
 * @return array Result containing delta, usage, flags.
 */
function parse_sse_chunk_logic_for_response_parser(string $sse_chunk, string &$current_buffer): array {
    $current_buffer .= $sse_chunk;
    $result = [
        'delta' => null,
        'usage' => null,
        'is_error' => false,
        'is_warning' => false,
        'is_done' => false,
        'status' => null,
    ];

    foreach (extract_sse_event_blocks($current_buffer) as $event_block) {
        $decoded_event = decode_event_type_sse_event_block($event_block);
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
 * Applies an internal typed event to the flattened parse result expected by the stream processor.
 *
 * @param array<string, mixed> $mapped_event
 * @param array<string, mixed> $result
 * @return bool True when parsing should stop immediately.
 */
function reduce_sse_event_logic_for_response_parser(array $mapped_event, array &$result): bool {
    $kind = isset($mapped_event['kind']) && is_string($mapped_event['kind']) ? $mapped_event['kind'] : 'skip';

    switch ($kind) {
        case 'status':
            if (isset($mapped_event['status']) && is_array($mapped_event['status'])) {
                $result['status'] = $mapped_event['status'];
            }
            return false;

        case 'delta':
            $text = isset($mapped_event['text']) ? (string) $mapped_event['text'] : '';
            if ($text !== '') {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $text;
            }
            return false;

        case 'warning':
            $text = isset($mapped_event['text']) ? (string) $mapped_event['text'] : '';
            if ($text !== '') {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $text;
                $result['is_warning'] = true;
            }
            return false;

        case 'completion':
            $result['is_done'] = true;
            if (isset($mapped_event['usage']) && is_array($mapped_event['usage'])) {
                $result['usage'] = $mapped_event['usage'];
            }
            if (isset($mapped_event['status']) && is_array($mapped_event['status'])) {
                $result['status'] = $mapped_event['status'];
            }
            if (!empty($mapped_event['warning_text']) && is_string($mapped_event['warning_text'])) {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $mapped_event['warning_text'];
                $result['is_warning'] = true;
            }
            return false;

        case 'legacy_chunk':
            if (isset($mapped_event['usage']) && is_array($mapped_event['usage'])) {
                $result['usage'] = $mapped_event['usage'];
            }
            if (!empty($mapped_event['text']) && is_string($mapped_event['text'])) {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $mapped_event['text'];
            }
            if (!empty($mapped_event['warning_text']) && is_string($mapped_event['warning_text'])) {
                if ($result['delta'] === null) {
                    $result['delta'] = '';
                }
                $result['delta'] .= $mapped_event['warning_text'];
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
