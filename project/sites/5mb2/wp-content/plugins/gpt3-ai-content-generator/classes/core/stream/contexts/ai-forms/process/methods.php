<?php

namespace WPAICG\Core\Stream\Contexts\AIForms\Process;

use WPAICG\Core\Stream\Contexts\AIForms\SSEAIFormsStreamContextHandler;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WP_Error;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\Core\AIPKit_Payload_Sanitizer;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\Providers\Google\GoogleSettingsHandler;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- validate-request.php ---
require_once WPAICG_PLUGIN_DIR . 'classes/ai-forms/core/pricing/fn-build-ai-form-pricing-check-context.php';

/**
 * Checks whether the saved AI Form contains at least one image-upload element.
 *
 * @param mixed $form_structure
 * @return bool
 */
function form_structure_has_image_upload_field($form_structure): bool
{
    if (!is_array($form_structure)) {
        return false;
    }

    foreach ($form_structure as $row) {
        if (!is_array($row) || empty($row['columns']) || !is_array($row['columns'])) {
            continue;
        }

        foreach ($row['columns'] as $column) {
            if (!is_array($column) || empty($column['elements']) || !is_array($column['elements'])) {
                continue;
            }

            foreach ($column['elements'] as $element) {
                if (is_array($element) && ($element['type'] ?? '') === 'image-upload') {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Validates the request and checks token limits for an AI Forms stream request.
 *
 * @param SSEAIFormsStreamContextHandler $handlerInstance The instance of the context handler.
 * @param array $cached_data Contains form data retrieved from the cache.
 * @param array $get_params  Original $_GET parameters from the SSE request.
 * @return array|WP_Error An array of validated parameters or a WP_Error on failure.
 */
function validate_request_logic(
    SSEAIFormsStreamContextHandler $handlerInstance,
    array $cached_data,
    array $get_params
) {
    // 1. Extract and Sanitize Parameters
    $user_id           = $cached_data['user_id'] ?? get_current_user_id();
    $form_id           = $cached_data['form_id'] ?? 0;
    $user_input_values = $cached_data['user_input_values'] ?? [];
    $image_inputs      = isset($cached_data['image_inputs']) && is_array($cached_data['image_inputs']) ? $cached_data['image_inputs'] : null;
    $conversation_uuid = $cached_data['conversation_uuid'] ?? wp_generate_uuid4();
    $session_id        = isset($get_params['session_id']) ? sanitize_text_field(wp_unslash($get_params['session_id'])) : '';

    // 2. Validate Essential Parameters
    if (empty($form_id)) {
        return new WP_Error('missing_form_id_ai_forms_logic', __('Form ID is missing for AI Forms stream.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (empty($user_input_values) && empty($image_inputs)) {
        return new WP_Error('missing_input_values_ai_forms_logic', __('User input values are missing for AI Forms stream.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    $submitted_fields = [];
    foreach ($user_input_values as $raw_key => $value) {
        $key_match = [];
        if (preg_match('/aipkit_form_field\[(.*?)\]/', (string) $raw_key, $key_match)) {
            $submitted_fields[$key_match[1]] = $value;
            continue;
        }

        $submitted_fields[$raw_key] = $value;
    }

    $form_config = $handlerInstance->get_ai_form_storage()->get_form_data($form_id);
    if (is_wp_error($form_config)) {
        return $form_config;
    }

    if (!empty($image_inputs)) {
        $is_pro = class_exists('\WPAICG\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
        if (!$is_pro) {
            return new WP_Error('image_upload_requires_pro_ai_forms_logic', __('Image upload is a paid AI Forms feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }

        if (!form_structure_has_image_upload_field($form_config['structure'] ?? [])) {
            return new WP_Error('image_upload_field_missing_ai_forms_logic', __('This AI Form is not configured to accept image uploads.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }
    }

    if (isset($submitted_fields['ai_provider']) && $submitted_fields['ai_provider'] !== '') {
        $form_config['ai_provider'] = sanitize_text_field((string) $submitted_fields['ai_provider']);
    }
    if (isset($submitted_fields['ai_model']) && $submitted_fields['ai_model'] !== '') {
        $form_config['ai_model'] = sanitize_text_field((string) $submitted_fields['ai_model']);
    }

    // 3. Perform Token Check
    $token_manager = $handlerInstance->get_token_manager();
    if (!$token_manager) {
        return new WP_Error('dependency_missing_token_manager', 'Token manager component is unavailable.', ['status' => 500]);
    }

    $context_id_for_tokens = !$user_id ? GuestTableConstants::AI_FORMS_GUEST_CONTEXT_ID : null;
    $usage_context = \WPAICG\AIForms\Core\Pricing\build_ai_form_pricing_check_context_logic(
        $form_id,
        $form_config,
        $submitted_fields,
        $image_inputs
    );
    $token_check_result = $token_manager->check_and_reset_tokens($user_id ?: null, $session_id, $context_id_for_tokens, 'ai_forms', $usage_context);

    if (is_wp_error($token_check_result)) {
        return $token_check_result;
    }

    // 4. Return sanitized and validated parameters
    return [
        'user_id'           => $user_id,
        'form_id'           => $form_id,
        'user_input_values' => $submitted_fields,
        'image_inputs'      => $image_inputs,
        'form_config'       => $form_config,
        'conversation_uuid' => $conversation_uuid,
        'session_id'        => $session_id,
        'client_ip'         => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
    ];
}

// --- build-prompt.php ---
/**
 * Builds the final prompt string from the template and submitted data.
 *
 * @param array $form_config The configuration of the form.
 * @param array $submitted_fields The sanitized submitted data.
 * @return string|WP_Error The final prompt string or WP_Error if template is missing.
 */
function build_prompt_logic(array $form_config, array $submitted_fields)
{
    $prompt_template = $form_config['prompt_template'] ?? '';
    $form_structure = $form_config['structure'] ?? [];

    if (empty($prompt_template)) {
        return new WP_Error('missing_template', __('Form prompt template is not configured.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }

    $final_prompt = $prompt_template;

    if (!empty($form_structure) && is_array($form_structure)) {
        foreach ($form_structure as $row) {
            if (empty($row['columns']) || !is_array($row['columns'])) {
                continue;
            }
            foreach ($row['columns'] as $column) {
                if (empty($column['elements']) || !is_array($column['elements'])) {
                    continue;
                }
                foreach ($column['elements'] as $element) {
                    $field_id = $element['fieldId'] ?? null;
                    if (!$field_id) { // Skip if element has no fieldId
                        continue;
                    }

                    $placeholder = '{' . $field_id . '}';
                    $value_to_substitute = resolve_submitted_field_value_logic($element, $submitted_fields);

                    // Replace placeholder in the prompt with the determined value.
                    // This will also replace with '' if the field wasn't submitted, effectively removing the placeholder.
                    $final_prompt = str_replace($placeholder, $value_to_substitute, $final_prompt);
                }
            }
        }
    }

    // Handle the legacy/simple case where a single 'user_input' is expected
    // This is less likely with the new structure but good for backward compatibility
    if (empty($form_structure) && isset($submitted_fields['user_input'])) {
        $final_prompt = str_replace('{user_input}', $submitted_fields['user_input'], $final_prompt);
    }

    return $final_prompt;
}

/**
 * Builds a moderation-safe text string from submitted field values only.
 *
 * @param array $form_config
 * @param array $submitted_fields
 * @return string
 */
function build_moderation_text_logic(array $form_config, array $submitted_fields): string
{
    $form_structure = $form_config['structure'] ?? [];
    $moderation_segments = [];

    if (!empty($form_structure) && is_array($form_structure)) {
        foreach ($form_structure as $row) {
            if (empty($row['columns']) || !is_array($row['columns'])) {
                continue;
            }
            foreach ($row['columns'] as $column) {
                if (empty($column['elements']) || !is_array($column['elements'])) {
                    continue;
                }
                foreach ($column['elements'] as $element) {
                    $field_id = $element['fieldId'] ?? null;
                    if (!$field_id) {
                        continue;
                    }

                    $resolved_value = trim(resolve_submitted_field_value_logic($element, $submitted_fields));
                    if ($resolved_value !== '') {
                        $moderation_segments[] = $resolved_value;
                    }
                }
            }
        }
    }

    if (empty($moderation_segments)) {
        collect_scalar_values_logic($submitted_fields, $moderation_segments);
    }

    return implode("\n", array_filter(array_map('trim', $moderation_segments), 'strlen'));
}

/**
 * Resolves one submitted field into the display string used in prompts and moderation.
 *
 * @param array $element
 * @param array $submitted_fields
 * @return string
 */
function resolve_submitted_field_value_logic(array $element, array $submitted_fields): string
{
    $field_id = $element['fieldId'] ?? null;
    if (!$field_id || !array_key_exists($field_id, $submitted_fields)) {
        return '';
    }

    $submitted_value = $submitted_fields[$field_id];
    $element_type = $element['type'] ?? 'text-input';

    switch ($element_type) {
        case 'select':
        case 'radio-button':
            $options = $element['options'] ?? [];
            foreach ($options as $option) {
                if (isset($option['value']) && $option['value'] == $submitted_value) {
                    return (string) ($option['text'] ?? $submitted_value);
                }
            }

            return is_scalar($submitted_value) ? (string) $submitted_value : '';

        case 'checkbox':
            $submitted_values_array = [];
            if (is_array($submitted_value)) {
                $submitted_values_array = $submitted_value;
            } elseif (is_string($submitted_value) && $submitted_value !== '') {
                $submitted_values_array = array_map('trim', explode(',', $submitted_value));
            }

            if (empty($submitted_values_array)) {
                return '';
            }

            $labels_to_substitute = [];
            $options = $element['options'] ?? [];
            foreach ($submitted_values_array as $value) {
                $resolved_label = is_scalar($value) ? (string) $value : '';
                foreach ($options as $option) {
                    if (isset($option['value']) && $option['value'] == $value) {
                        $resolved_label = (string) ($option['text'] ?? $value);
                        break;
                    }
                }
                if ($resolved_label !== '') {
                    $labels_to_substitute[] = $resolved_label;
                }
            }

            return implode(', ', $labels_to_substitute);

        default:
            if (is_array($submitted_value)) {
                $scalar_values = [];
                collect_scalar_values_logic($submitted_value, $scalar_values);
                return implode(', ', $scalar_values);
            }

            return is_scalar($submitted_value) ? (string) $submitted_value : '';
    }
}

/**
 * Recursively collects scalar values from nested arrays.
 *
 * @param mixed $value
 * @param array<int, string> $segments
 * @return void
 */
function collect_scalar_values_logic($value, array &$segments): void
{
    if (is_array($value)) {
        foreach ($value as $item) {
            collect_scalar_values_logic($item, $segments);
        }
        return;
    }

    if (is_scalar($value)) {
        $scalar_value = trim((string) $value);
        if ($scalar_value !== '') {
            $segments[] = $scalar_value;
        }
    }
}

// --- prepare-stream-data.php ---
/**
 * Normalizes submitted AI Form values for outbound webhook payloads.
 *
 * @param mixed $value
 * @return mixed
 */
function normalize_form_submission_value_for_event($value)
{
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $child_value) {
            $normalized_key = is_int($key) ? $key : sanitize_key((string) $key);
            if ($normalized_key === '') {
                continue;
            }
            $normalized[$normalized_key] = normalize_form_submission_value_for_event($child_value);
        }
        return AIPKit_Payload_Sanitizer::sanitize_payload_if_array($normalized);
    }

    if (is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
    }

    if (is_string($value)) {
        // Preserve the exact submitted string shape for outbound automations.
        return sanitize_textarea_field($value);
    }

    if (is_scalar($value)) {
        return sanitize_textarea_field((string) $value);
    }

    return '';
}

/**
 * Builds safe image metadata without base64 payloads for logs/events.
 *
 * @param mixed $image_inputs
 * @return array<int, array<string, mixed>>
 */
function summarize_ai_form_image_inputs_for_event($image_inputs): array
{
    if (!is_array($image_inputs)) {
        return [];
    }

    $summary = [];
    foreach ($image_inputs as $image_input) {
        if (!is_array($image_input)) {
            continue;
        }

        $summary[] = array_filter([
            'field_id' => isset($image_input['field_id']) ? sanitize_key((string) $image_input['field_id']) : '',
            'filename' => isset($image_input['filename']) ? sanitize_file_name((string) $image_input['filename']) : '',
            'mime_type' => isset($image_input['type']) ? sanitize_text_field((string) $image_input['type']) : '',
            'size' => isset($image_input['size']) ? absint($image_input['size']) : 0,
        ], static fn($value) => $value !== '' && $value !== 0);
    }

    return $summary;
}

/**
 * Builds AI Forms event metadata for later emission after the AI response is complete.
 *
 * @param array $validated_params
 * @param array $form_config
 * @param string $provider
 * @param string $model
 * @param int $submission_count
 * @return array<string, mixed>
 */
function build_form_submitted_event_meta_logic(
    array $validated_params,
    array $form_config,
    string $provider,
    string $model,
    int $submission_count
): array {
    $form_id = absint($validated_params['form_id'] ?? 0);
    $form_title = isset($form_config['title']) ? sanitize_text_field((string) $form_config['title']) : '';
    $submitted_inputs_raw = $validated_params['user_input_values'] ?? [];
    $submitted_inputs = is_array($submitted_inputs_raw)
        ? normalize_form_submission_value_for_event($submitted_inputs_raw)
        : [];
    $image_input_summary = summarize_ai_form_image_inputs_for_event($validated_params['image_inputs'] ?? null);

    $event_meta = [
        'form_id' => $form_id,
        'form_name' => $form_title !== '' ? $form_title : '',
        'submission_count' => $submission_count,
        'ai' => [
            'provider' => $provider,
            'model' => $model,
        ],
        'inputs' => is_array($submitted_inputs) ? $submitted_inputs : [],
    ];

    if (!empty($image_input_summary)) {
        $event_meta['media_inputs'] = [
            'image_count' => count($image_input_summary),
            'images' => $image_input_summary,
        ];
    }

    return $event_meta;
}

/**
 * Logs the user request and prepares the final data array for the SSE stream processor.
 *
 * @param SSEAIFormsStreamContextHandler $handlerInstance The instance of the context handler.
 * @param array $validated_params Validated request parameters.
 * @param array $form_config The configuration of the form.
 * @param string $final_user_prompt The final constructed user prompt.
 * @param string $system_instruction The system instruction, potentially with vector context.
 * @param array $vector_search_scores Array of captured vector search scores for logging.
 * @return array|WP_Error The structured data for the SSE processor, or a WP_Error on failure.
 */
function prepare_stream_data_logic(
    SSEAIFormsStreamContextHandler $handlerInstance,
    array $validated_params,
    array $form_config,
    string $final_user_prompt,
    string $system_instruction,
    array $vector_search_scores = []
) {
    $log_storage = $handlerInstance->get_log_storage();
    $user_id = $validated_params['user_id'];
    $user_wp_role = $user_id ? implode(', ', wp_get_current_user()->roles) : null;
    $provider = $form_config['ai_provider'];
    $model = $form_config['ai_model'];
    $image_inputs = isset($validated_params['image_inputs']) && is_array($validated_params['image_inputs'])
        ? $validated_params['image_inputs']
        : null;

    if (!empty($image_inputs)) {
        $providers_with_image_inputs = ['OpenAI', 'Google', 'Claude', 'OpenRouter', 'xAI', 'Ollama'];
        if (!in_array($provider, $providers_with_image_inputs, true)) {
            return new WP_Error(
                'provider_no_image_input_ai_forms_logic',
                sprintf(
                    /* translators: %s: provider name */
                    __('%s does not support image analysis in AI Forms.', 'gpt3-ai-content-generator'),
                    $provider
                ),
                ['status' => 400]
            );
        }

        /**
         * Allow provider-specific image capability validation for AI Forms stream requests.
         *
         * @param mixed $validation_error Existing validation error.
         * @param array $image_inputs Normalized image input payload.
         * @param string $provider Selected provider.
         * @param string $model Selected model.
         * @param array $form_config AI Form settings.
         * @param string $flow Request flow identifier.
         */
        $image_validation_error = apply_filters(
            'aipkit_chat_image_input_validation_error',
            null,
            $image_inputs,
            $provider,
            $model,
            $form_config,
            'ai_forms_stream'
        );
        if (is_wp_error($image_validation_error)) {
            return $image_validation_error;
        }
    }

    // 1. Log User Request
    $base_log_data = [
        'bot_id'            => null,
        'user_id'           => $user_id ?: null,
        'session_id'        => $user_id ? null : $validated_params['session_id'],
        'conversation_uuid' => $validated_params['conversation_uuid'],
        'module'            => 'ai_forms',
        'is_guest'          => ($user_id === 0),
        'role'              => $user_wp_role,
        'ip_address'        => $validated_params['client_ip'],
        'form_id'           => $validated_params['form_id'],
    ];
    $bot_message_id = 'aif-msg-' . uniqid('', true);
    $base_log_data['bot_message_id'] = $bot_message_id;

    $request_payload = [
        'form_id' => $validated_params['form_id'],
        'inputs' => $validated_params['user_input_values'],
        'constructed_prompt' => $final_user_prompt,
    ];
    if (!empty($image_inputs)) {
        $request_payload['image_inputs'] = $image_inputs;
    }
    $request_payload = AIPKit_Payload_Sanitizer::sanitize_payload_if_array($request_payload);

    $log_user_data = array_merge($base_log_data, [
        'message_role'       => 'user',
        'message_content'    => "AI Form Submission (ID: {$validated_params['form_id']}): " . ($form_config['title'] ?? 'Untitled'),
        'timestamp'          => time(),
        'request_payload'    => $request_payload,
    ]);
    $log_storage->log_message($log_user_data);

    $form_id = absint($validated_params['form_id']);
    if ($form_id > 0) {
        $count_meta_key = '_aipkit_ai_form_submission_count';
        $current_count = (int) get_post_meta($form_id, $count_meta_key, true);
        $submission_count = $current_count + 1;
        update_post_meta($form_id, $count_meta_key, $submission_count);
    } else {
        $submission_count = 0;
    }

    $base_log_data['aipkit_form_event_meta'] = build_form_submitted_event_meta_logic(
        $validated_params,
        $form_config,
        $provider,
        $model,
        $submission_count
    );

    // 2. Prepare AI and API Parameters
    $global_ai_params = AIPKIT_AI_Settings::get_ai_parameters();
    $ai_params_for_payload = $global_ai_params; // Start with all global defaults

    // Override with form-specific settings if they are numeric
    if (isset($form_config['temperature']) && is_numeric($form_config['temperature'])) {
        $ai_params_for_payload['temperature'] = floatval($form_config['temperature']);
    }
    if (isset($form_config['max_tokens']) && is_numeric($form_config['max_tokens'])) {
        $ai_params_for_payload['max_completion_tokens'] = absint($form_config['max_tokens']);
    }
    if (isset($form_config['top_p']) && is_numeric($form_config['top_p'])) {
        $ai_params_for_payload['top_p'] = floatval($form_config['top_p']);
    }
    if (isset($form_config['frequency_penalty']) && is_numeric($form_config['frequency_penalty'])) {
        $ai_params_for_payload['frequency_penalty'] = floatval($form_config['frequency_penalty']);
    }
    if (isset($form_config['presence_penalty']) && is_numeric($form_config['presence_penalty'])) {
        $ai_params_for_payload['presence_penalty'] = floatval($form_config['presence_penalty']);
    }
    if (!empty($image_inputs)) {
        $ai_params_for_payload['image_inputs'] = $image_inputs;
    }
    // Add provider-specific reasoning / think controls.
    if ($provider === 'OpenAI') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
            (string) $model,
            $form_config['reasoning_effort'] ?? ''
        );
        if ($reasoning_effort !== '') {
            $ai_params_for_payload['reasoning'] = ['effort' => $reasoning_effort];
        }
    } elseif ($provider === 'Ollama') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($form_config['reasoning_effort'] ?? '');
        if ($reasoning_effort !== '' && $reasoning_effort !== 'none') {
            $ai_params_for_payload['reasoning'] = ['effort' => $reasoning_effort];
        }
    }


    if ($provider === 'Google' && class_exists(GoogleSettingsHandler::class)) {
        $ai_params_for_payload['safety_settings'] = GoogleSettingsHandler::get_safety_settings();
    }
    $ai_params_for_payload['model_id_for_grounding'] = $model;

    // Vector Store Tool Config (OpenAI)
    $is_vector_enabled = ($form_config['enable_vector_store'] ?? '0') === '1';
    $is_openai_vector_provider = ($form_config['vector_store_provider'] ?? '') === 'openai';
    $has_vector_store_ids = !empty($form_config['openai_vector_store_ids']) && is_array($form_config['openai_vector_store_ids']);

    if ($provider === 'OpenAI' && $is_vector_enabled && $is_openai_vector_provider && $has_vector_store_ids) {
        $vector_top_k = isset($form_config['vector_store_top_k']) ? absint($form_config['vector_store_top_k']) : 3;
        $vector_top_k = max(1, min($vector_top_k, 20));

        // Get confidence threshold and convert to OpenAI score threshold
        $confidence_threshold_percent = (int)($form_config['vector_store_confidence_threshold'] ?? 20);
        $openai_score_threshold = round($confidence_threshold_percent / 100, 4); // Round to avoid precision issues

        $ai_params_for_payload['vector_store_tool_config'] = [
            'type'             => 'file_search',
            'vector_store_ids' => $form_config['openai_vector_store_ids'],
            'max_num_results'  => $vector_top_k,
            'ranking_options'  => [
                'score_threshold' => $openai_score_threshold
            ]
        ];
    }

    if ($provider === 'OpenAI' && ($form_config['openai_web_search_enabled'] ?? '0') === '1') {
        $ai_params_for_payload['web_search_tool_config'] = ['enabled' => true];
        // For AI Forms, web search is implicitly active if the form setting is enabled.
        $ai_params_for_payload['frontend_web_search_active'] = true;
    }
    if ($provider === 'Claude' && ($form_config['claude_web_search_enabled'] ?? '0') === '1') {
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

        $claude_max_uses = isset($form_config['claude_web_search_max_uses'])
            ? absint($form_config['claude_web_search_max_uses'])
            : 5;
        $web_search_config['max_uses'] = max(1, min($claude_max_uses, 20));

        $allowed_domains = $split_domains($form_config['claude_web_search_allowed_domains'] ?? '');
        $blocked_domains = $split_domains($form_config['claude_web_search_blocked_domains'] ?? '');
        if (!empty($allowed_domains)) {
            $web_search_config['allowed_domains'] = $allowed_domains;
        } elseif (!empty($blocked_domains)) {
            $web_search_config['blocked_domains'] = $blocked_domains;
        }

        if (($form_config['claude_web_search_loc_type'] ?? 'none') === 'approximate') {
            $claude_user_location = array_filter([
                'country' => $form_config['claude_web_search_loc_country'] ?? null,
                'city' => $form_config['claude_web_search_loc_city'] ?? null,
                'region' => $form_config['claude_web_search_loc_region'] ?? null,
                'timezone' => $form_config['claude_web_search_loc_timezone'] ?? null,
            ]);
            if (!empty($claude_user_location)) {
                $claude_user_location['type'] = 'approximate';
                $web_search_config['user_location'] = $claude_user_location;
            }
        }

        $cache_ttl = $form_config['claude_web_search_cache_ttl'] ?? 'none';
        if (in_array($cache_ttl, ['5m', '1h'], true)) {
            $web_search_config['cache_control'] = [
                'type' => 'ephemeral',
                'ttl' => $cache_ttl,
            ];
        }

        $ai_params_for_payload['web_search_tool_config'] = $web_search_config;
        // For AI Forms, web search is implicitly active if the form setting is enabled.
        $ai_params_for_payload['frontend_web_search_active'] = true;
    }
    if ($provider === 'OpenRouter' && ($form_config['openrouter_web_search_enabled'] ?? '0') === '1') {
        $web_search_config = ['enabled' => true];

        $openrouter_engine = isset($form_config['openrouter_web_search_engine'])
            ? sanitize_key((string) $form_config['openrouter_web_search_engine'])
            : 'auto';
        if (in_array($openrouter_engine, ['native', 'exa'], true)) {
            $web_search_config['engine'] = $openrouter_engine;
        }

        $openrouter_max_results = isset($form_config['openrouter_web_search_max_results'])
            ? absint($form_config['openrouter_web_search_max_results'])
            : 5;
        $web_search_config['max_results'] = max(1, min($openrouter_max_results, 10));

        $openrouter_search_prompt = isset($form_config['openrouter_web_search_search_prompt'])
            ? AIPKit_Prompt_Sanitizer::sanitize($form_config['openrouter_web_search_search_prompt'])
            : '';
        if ($openrouter_search_prompt !== '') {
            $web_search_config['search_prompt'] = $openrouter_search_prompt;
        }

        $ai_params_for_payload['web_search_tool_config'] = $web_search_config;
        $ai_params_for_payload['frontend_web_search_active'] = true;
    }
    if ($provider === 'xAI' && ($form_config['xai_web_search_enabled'] ?? '0') === '1') {
        $ai_params_for_payload['xai_web_search_tool_config'] = ['enabled' => true];
        $ai_params_for_payload['frontend_web_search_active'] = true;
    }
    if ($provider === 'Google' && ($form_config['google_search_grounding_enabled'] ?? '0') === '1') {
        // For AI Forms, grounding is implicitly active if the form setting is enabled.
        $ai_params_for_payload['frontend_google_search_grounding_active'] = true;
    }

        $provData = AIPKit_Providers::get_provider_data($provider);
    $api_params_for_stream = [
        'api_key' => $provData['api_key'] ?? '', 'base_url' => $provData['base_url'] ?? '', 'api_version' => $provData['api_version'] ?? '',
        'azure_endpoint' => ($provider === 'Azure') ? ($provData['endpoint'] ?? '') : '',
        'stream' => true,
    ];

    if (empty($api_params_for_stream['api_key']) && $provider !== 'Ollama') {
        /* translators: %s: The name of the AI provider (e.g., OpenAI, Google). */
        return new WP_Error('missing_api_key_ai_forms_logic', sprintf(__('API key missing for %s (AI Forms).', 'gpt3-ai-content-generator'), $provider), ['status' => 400]);
    }
    if ($provider === 'Azure' && empty($api_params_for_stream['azure_endpoint'])) {
        return new WP_Error('missing_azure_endpoint_ai_forms_logic', __('Azure endpoint is missing (AI Forms).', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // 3. Construct and return the final data array
    return [
        'provider'                      => $provider,
        'model'                         => $model,
        'user_message'                  => $final_user_prompt,
        'history'                       => [], // AI Forms do not have chat history
        'system_instruction_filtered'   => $system_instruction, // Pass the (potentially new) system instruction
        'api_params'                    => $api_params_for_stream,
        'ai_params'                     => $ai_params_for_payload,
        'conversation_uuid'             => $validated_params['conversation_uuid'],
        'base_log_data'                 => $base_log_data,
        'bot_message_id'                => $bot_message_id,
        'vector_search_scores'          => $vector_search_scores, // Include captured vector search scores
    ];
}
