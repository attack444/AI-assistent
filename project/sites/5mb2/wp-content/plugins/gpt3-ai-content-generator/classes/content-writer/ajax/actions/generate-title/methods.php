<?php

namespace WPAICG\ContentWriter\Ajax\Actions\GenerateTitle;

use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Title_Action;
use WPAICG\AIPKit_Providers;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Prompts;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\smart_seo_keyword_resolution_response_fields_logic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates the input for the title generation AJAX action.
 *
 * @param AIPKit_Content_Writer_Generate_Title_Action $handler The handler instance.
 * @return array|WP_Error An array of validated parameters or a WP_Error on failure.
 */
function validate_and_normalize_input_logic(AIPKit_Content_Writer_Generate_Title_Action $handler)
{
    $permission_check = $handler->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
    if (is_wp_error($permission_check)) {
        return $permission_check;
    }

    if (!$handler->get_ai_caller()) {
        return new WP_Error('ai_caller_missing', __('AI processing component is unavailable.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions method.
    $settings = isset($_POST) ? wp_unslash($_POST) : [];
    $original_title = isset($settings['content_title']) ? sanitize_text_field(wp_unslash($settings['content_title'])) : '';

    if (empty($original_title)) {
        return new WP_Error('missing_original_title', __('Original title/topic is required to generate a new title.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // --- START: Parse title and keywords ---
    $topic = $original_title;
    $inline_keywords = '';
    if (strpos($original_title, '|') !== false) {
        $parts = explode('|', $original_title, 2);
        $topic = trim($parts[0]);
        $inline_keywords = isset($parts[1]) ? trim($parts[1]) : ''; // Only take the second part as keywords
    }
    // --- END: Parse ---

    $provider_raw = isset($settings['ai_provider']) && !empty($settings['ai_provider'])
                   ? sanitize_text_field($settings['ai_provider'])
                   : AIPKit_Providers::get_current_provider();

    $provider = AIPKit_Providers::normalize_provider_label($provider_raw);

    $model_data = AIPKit_Providers::get_provider_data($provider);
    $model = isset($settings['ai_model']) && !empty($settings['ai_model'])
             ? sanitize_text_field($settings['ai_model'])
             : ($model_data['model'] ?? '');

    if (empty($model)) {
        return new WP_Error('missing_model_title_gen', __('AI model selection is required for title generation.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // Sanitize other used fields that might be passed in
    $settings['ai_temperature'] = isset($settings['ai_temperature']) ? floatval($settings['ai_temperature']) : 1.0;
    $settings['custom_title_prompt'] = isset($settings['custom_title_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($settings['custom_title_prompt']) : '';
    $settings['rss_description'] = isset($settings['rss_description'])
        ? sanitize_textarea_field(wp_unslash($settings['rss_description']))
        : '';
    $settings['url_content_context'] = isset($settings['url_content_context'])
        ? sanitize_textarea_field(wp_unslash($settings['url_content_context']))
        : '';
    $settings['source_url'] = isset($settings['source_url'])
        ? esc_url_raw(wp_unslash($settings['source_url']))
        : '';


    // Return the full set of validated and normalized parameters
    $validated_params = $settings;
    $validated_params['content_title'] = $topic; // Use parsed topic
    $validated_params['inline_keywords'] = $inline_keywords; // Add parsed keywords
    $validated_params['provider'] = $provider;
    $validated_params['model'] = $model;

    return $validated_params;
}

/**
 * Builds the system instruction and user prompt for title generation.
 * UPDATED: Simplified to only use the custom title prompt, as guided mode is removed.
 *
 * @param array $validated_params The validated parameters from the request.
 * @return array An array containing 'system_instruction' and 'user_prompt'.
 */
function build_title_prompt_logic(array $validated_params): array
{
    $system_instruction = "You are an expert copywriter specializing in crafting engaging headlines.";

    // Use the custom prompt from settings, or the central default if empty.
    $user_prompt_template = $validated_params['custom_title_prompt'] ?? AIPKit_Content_Writer_Prompts::get_default_title_prompt();
    if (empty(trim($user_prompt_template))) {
        $user_prompt_template = AIPKit_Content_Writer_Prompts::get_default_title_prompt();
    }

    // Replace placeholders
    $final_title_for_prompt = $validated_params['content_title'] ?? '';
    $final_keywords_for_prompt = !empty($validated_params['inline_keywords']) ? $validated_params['inline_keywords'] : ($validated_params['content_keywords'] ?? '');

    $rss_description = $validated_params['rss_description'] ?? '';
    $url_content_context = $validated_params['url_content_context'] ?? '';
    $source_url = $validated_params['source_url'] ?? '';

    $user_prompt = str_replace('{topic}', $final_title_for_prompt, $user_prompt_template);
    $user_prompt = str_replace('{keywords}', $final_keywords_for_prompt, $user_prompt);
    $user_prompt = str_replace('{description}', $rss_description, $user_prompt);
    $user_prompt = str_replace('{url_content}', $url_content_context, $user_prompt);
    $user_prompt = str_replace('{source_url}', $source_url, $user_prompt);

    return [
        'user_prompt' => $user_prompt,
        'system_instruction' => $system_instruction,
    ];
}

/**
 * Prepares the final AI parameters by merging global settings with form-specific overrides for title generation.
 *
 * @param array $validated_params The validated settings from the request.
 * @return array The array of AI parameter overrides.
 */
function prepare_ai_params_logic(array $validated_params): array
{
    $ai_params_override = [];

    if (isset($validated_params['ai_temperature'])) {
        $ai_params_override['temperature'] = floatval($validated_params['ai_temperature']);
    }

    // Add provider-specific reasoning / think controls.
    if (($validated_params['provider'] ?? '') === 'OpenAI') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
            (string) ($validated_params['ai_model'] ?? ''),
            $validated_params['reasoning_effort'] ?? ''
        );
        if ($reasoning_effort !== '') {
            $ai_params_override['reasoning'] = ['effort' => $reasoning_effort];
        }
    } elseif (($validated_params['provider'] ?? '') === 'Ollama') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($validated_params['reasoning_effort'] ?? '');
        if ($reasoning_effort !== '' && $reasoning_effort !== 'none') {
            $ai_params_override['reasoning'] = ['effort' => $reasoning_effort];
        }
    }

    $ai_params_override['top_p'] = null;

    return $ai_params_override;
}

/**
 * Makes the call to the AI provider using the AI Caller.
 *
 * @param AIPKit_Content_Writer_Generate_Title_Action $handler The handler instance.
 * @param string $provider The AI provider.
 * @param string $model The AI model.
 * @param array $messages The message payload for the API.
 * @param array $ai_params_override AI parameters to override globals.
 * @param string $system_instruction The system instruction for the AI.
 * @param array $form_data The form data containing vector settings.
 * @return array|WP_Error The result from the AI Caller.
 */
function call_title_generator_logic(
    AIPKit_Content_Writer_Generate_Title_Action $handler,
    string $provider,
    string $model,
    array $messages,
    array $ai_params_override,
    string $system_instruction,
    array $form_data = []
) {
    $user_message = $messages[0]['content'] ?? '';
    [$system_instruction, $ai_params_override, $instruction_context] = $handler->prepare_content_writer_vector_context(
        $user_message,
        $provider,
        $system_instruction,
        $ai_params_override,
        $form_data
    );

    return $handler->get_ai_caller()->make_standard_call(
        $provider,
        $model,
        $messages,
        $ai_params_override,
        $system_instruction,
        $instruction_context
    );
}

/**
 * Handles the response from the AI call, cleaning it and sending a JSON response.
 * Also logs the request/response under the same conversation if conversation_uuid is provided.
 *
 * @param AIPKit_Content_Writer_Generate_Title_Action $handler The handler instance.
 * @param array|WP_Error $result The result from the AI Caller.
 * @param array $validated_params The validated request parameters.
 * @param array $prompts The prompts array with 'user_prompt' and 'system_instruction'.
 * @param array $ai_params_override The AI params used.
 * @return void
 */
function handle_title_response_logic(
    AIPKit_Content_Writer_Generate_Title_Action $handler,
    $result,
    array $validated_params,
    array $prompts,
    array $ai_params_override
): void
{
    if (is_wp_error($result)) {
        $handler->send_wp_error($result);
        return;
    }

    $generated_title = trim($result['content'] ?? '');

    // Clean up potential extra formatting from the AI
    if (preg_match('/^"(.*)"$/', $generated_title, $matches)) {
        $generated_title = $matches[1];
    }
    $generated_title = trim(str_replace(["\n", "\r"], ' ', $generated_title));
    $generated_title = preg_replace('/\s+/', ' ', $generated_title);
    $focus_keyword_for_title = '';
    foreach (['inline_keywords', 'content_keywords'] as $keyword_key) {
        if (!empty($validated_params[$keyword_key])) {
            $keyword_parts = array_map('trim', explode(',', (string) $validated_params[$keyword_key]));
            $focus_keyword_for_title = (string) ($keyword_parts[0] ?? '');
            break;
        }
    }
    if (class_exists(\WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::class)) {
        $generated_title = \WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::clean_title($generated_title, $focus_keyword_for_title);
    }

    if (empty($generated_title)) {
        $handler->send_wp_error(new WP_Error('title_gen_empty', __('AI did not return a valid title.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // Ensure logging under a conversation. Generate UUID if missing so first-run title is captured.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $conversation_uuid = isset($_POST['conversation_uuid']) ? sanitize_text_field(wp_unslash($_POST['conversation_uuid'])) : '';
    if ($handler->log_storage) {
        $conversation_uuid = $handler->ensure_content_writer_conversation_uuid($conversation_uuid);
        $provider = $validated_params['provider'] ?? '';
        $model = $validated_params['model'] ?? '';
        $base = $handler->build_content_writer_log_base($conversation_uuid, $provider, $model);
        // User intent log
        $handler->log_storage->log_message(array_merge($base, [
            'message_role' => 'user',
            'message_content' => 'Generate Title',
            'request_payload' => [
                'original_topic' => $validated_params['content_title'] ?? '',
                'inline_keywords' => $validated_params['inline_keywords'] ?? '',
                'custom_title_prompt' => $validated_params['custom_title_prompt'] ?? '',
            ],
        ]));
        // Bot response log (surface vector_search_scores top-level like SSE)
        $botLog = array_merge($base, [
            'message_role' => 'bot',
            'message_content' => $generated_title,
            'usage' => $result['usage'] ?? null,
            'request_payload' => [
                'provider' => $provider,
                'model' => $model,
                'payload_sent' => [
                    'messages' => [['role' => 'user', 'content' => $prompts['user_prompt'] ?? '']],
                    'ai_params' => $ai_params_override,
                    'system_instruction' => $prompts['system_instruction'] ?? '',
                ],
            ],
        ]);
        if (!empty($result['vector_search_scores'])) {
            $botLog['vector_search_scores'] = $result['vector_search_scores'];
        }
        $handler->log_storage->log_message($botLog);
    }

    $smart_seo_keyword_resolution = isset($validated_params['smart_seo_keyword_resolution']) && is_array($validated_params['smart_seo_keyword_resolution'])
        ? $validated_params['smart_seo_keyword_resolution']
        : [];

    wp_send_json_success(array_merge([
        'new_title' => $generated_title,
        'usage' => $result['usage'] ?? null,
        'conversation_uuid' => $conversation_uuid,
    ], smart_seo_keyword_resolution_response_fields_logic($smart_seo_keyword_resolution)));
}
