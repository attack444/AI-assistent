<?php

namespace WPAICG\ContentWriter\Ajax\Actions\Shared;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config;
use WPAICG\AIPKit_Providers;
use WP_Error;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_System_Instruction_Builder;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_User_Prompt_Builder;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\Lib\ContentWriter\AIPKit_Google_Sheets_Parser;
use WPAICG\Lib\Utils\AIPKit_Google_Credentials_Handler;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_Smart_SEO_Keyphrase_Usage;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver;
use WPAICG\Core\AIPKit_AI_Caller;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates the input for content generation AJAX actions.
 * UPDATED: Simplified to remove guided mode fields.
 *
 * @param AIPKit_Content_Writer_Base_Ajax_Action $handler The handler instance.
 * @param array $settings The raw POST data.
 * @return array|WP_Error An array of validated parameters or a WP_Error on failure.
 */
function validate_and_normalize_input_logic(AIPKit_Content_Writer_Base_Ajax_Action $handler, array $settings)
{
    $permission_check = $handler->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
    if (is_wp_error($permission_check)) {
        return $permission_check;
    }

    if (class_exists(AIPKit_Content_Writer_SEO_Config::class)) {
        $seo_permission_check = AIPKit_Content_Writer_SEO_Config::require_pro_for_improvement($settings);
        if (is_wp_error($seo_permission_check)) {
            return $seo_permission_check;
        }
        $settings = AIPKit_Content_Writer_SEO_Config::normalize($settings);
    }

    $content_title_raw = isset($settings['content_title']) ? sanitize_text_field(wp_unslash($settings['content_title'])) : '';
    if (empty($content_title_raw)) {
        return new WP_Error('missing_title', __('Content title/topic is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // --- START: Parse title and keywords ---
    $topic = $content_title_raw;
    $inline_keywords = '';
    if (strpos($content_title_raw, '|') !== false) {
        $parts = explode('|', $content_title_raw, 2);
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
        return new WP_Error('missing_model', __('AI model selection is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    $content_length = isset($settings['content_length'])
        ? sanitize_key($settings['content_length'])
        : '';
    if (!in_array($content_length, ['short', 'medium', 'long'], true)) {
        $content_length = 'medium';
    }

    $rss_description = isset($settings['rss_description'])
        ? sanitize_textarea_field(wp_unslash($settings['rss_description']))
        : '';
    $url_content_context = isset($settings['url_content_context'])
        ? sanitize_textarea_field(wp_unslash($settings['url_content_context']))
        : '';
    $source_url = isset($settings['source_url'])
        ? esc_url_raw(wp_unslash($settings['source_url']))
        : '';

    $validated_params = $settings;
    $validated_params['content_title'] = $topic; // Use parsed topic
    $validated_params['inline_keywords'] = $inline_keywords; // Add parsed keywords
    $validated_params['provider'] = $provider;
    $validated_params['model'] = $model;
    $validated_params['content_length'] = $content_length;
    $validated_params['rss_description'] = $rss_description;
    $validated_params['url_content_context'] = $url_content_context;
    $validated_params['source_url'] = $source_url;

    return $validated_params;
}

/**
 * Builds the system instruction and user prompt for the Content Writer.
 * UPDATED: Simplified to remove guided mode logic. Replaces placeholders in custom prompt.
 *
 * @param array $validated_params The validated settings from the request.
 * @return array|WP_Error An array containing 'system_instruction' and 'user_prompt' or WP_Error.
 */
function build_prompts_logic(array $validated_params)
{
    if (!class_exists(AIPKit_Content_Writer_System_Instruction_Builder::class) || !class_exists(AIPKit_Content_Writer_User_Prompt_Builder::class)) {
        return new WP_Error('dependency_missing', 'Content writer prompt builders are unavailable.');
    }

    // 1. Build the system instruction. This function is now very simple.
    $system_instruction = AIPKit_Content_Writer_System_Instruction_Builder::build($validated_params);

    // 2. Build the user prompt *template*. This will now only return the custom prompt.
    $user_prompt_template = AIPKit_Content_Writer_User_Prompt_Builder::build($validated_params);
    if (empty($user_prompt_template)) {
        return new WP_Error('missing_prompt', 'Content Prompt cannot be empty.', ['status' => 400]);
    }

    // 3. Replace placeholders in the final prompt template.
    // The `content_title` in $validated_params has already been parsed.
    $final_title_for_prompt = $validated_params['content_title'] ?? 'AI Generated Content';
    // Prioritize inline keywords, fall back to global, then to empty.
    $final_keywords = !empty($validated_params['inline_keywords']) ? $validated_params['inline_keywords'] : ($validated_params['content_keywords'] ?? '');
    $rss_description = $validated_params['rss_description'] ?? '';
    $url_content_context = $validated_params['url_content_context'] ?? '';
    $source_url = $validated_params['source_url'] ?? '';

    $user_prompt = str_replace('{topic}', $final_title_for_prompt, $user_prompt_template);
    $user_prompt = str_replace('{keywords}', $final_keywords, $user_prompt);
    $user_prompt = str_replace('{description}', $rss_description, $user_prompt);
    $user_prompt = str_replace('{url_content}', $url_content_context, $user_prompt);
    $user_prompt = str_replace('{source_url}', $source_url, $user_prompt);

    return [
        'system_instruction' => $system_instruction,
        'user_prompt' => $user_prompt,
    ];
}

/**
 * Prepares an array of AI parameter overrides from the submitted settings.
 * This does NOT merge with global defaults; it only prepares the override values.
 *
 * @param array $settings The validated settings from the request.
 * @return array The array of AI parameter overrides.
 */
function prepare_ai_params_logic(array $settings): array
{
    $ai_params_override = [];

    if (isset($settings['ai_temperature'])) {
        $ai_params_override['temperature'] = floatval($settings['ai_temperature']);
    }

    $max_completion_tokens = null;
    if (isset($settings['max_completion_tokens']) && is_numeric($settings['max_completion_tokens'])) {
        $max_completion_tokens = absint($settings['max_completion_tokens']);
    } elseif (isset($settings['max_tokens']) && is_numeric($settings['max_tokens'])) {
        $max_completion_tokens = absint($settings['max_tokens']);
    } else {
        $content_length = isset($settings['content_length'])
            ? sanitize_key($settings['content_length'])
            : '';
        $length_map = [
            'short' => 2000,
            'medium' => 4000,
            'long' => 6000,
        ];
        if (isset($length_map[$content_length])) {
            $max_completion_tokens = $length_map[$content_length];
        }
    }
    if ($max_completion_tokens) {
        $ai_params_override['max_completion_tokens'] = $max_completion_tokens;
    }
    // Add provider-specific reasoning / think controls.
    if (($settings['provider'] ?? '') === 'OpenAI') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
            (string) ($settings['ai_model'] ?? ''),
            $settings['reasoning_effort'] ?? ''
        );
        if ($reasoning_effort !== '') {
            $ai_params_override['reasoning'] = ['effort' => $reasoning_effort];
        }
    } elseif (($settings['provider'] ?? '') === 'Ollama') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($settings['reasoning_effort'] ?? '');
        if ($reasoning_effort !== '' && $reasoning_effort !== 'none') {
            $ai_params_override['reasoning'] = ['effort' => $reasoning_effort];
        }
    }

    $ai_params_override['top_p'] = null;


    return $ai_params_override;
}

/**
 * Logs the initial user request for content generation (both standard and stream).
 *
 * @param AIPKit_Content_Writer_Base_Ajax_Action $handler The handler instance.
 * @param array $request_data The validated and normalized request parameters.
 * @param string $request_type A string indicating the request type (e.g., 'Stream Init', 'AJAX').
 * @return void
 */
function log_initial_request_logic(AIPKit_Content_Writer_Base_Ajax_Action $handler, array $request_data, string $request_type): void
{
    if (!$handler->log_storage) {
        return;
    }

    $initial_request_details_for_log = [
        'title'              => $request_data['content_title'] ?? '',
        'keywords'           => $request_data['content_keywords'] ?? null,
    ];

    // Reuse provided conversation_uuid when available so all steps belong to one session
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $conversation_uuid = $handler->ensure_content_writer_conversation_uuid(
        isset($request_data['conversation_uuid']) && !empty($request_data['conversation_uuid'])
            ? sanitize_text_field($request_data['conversation_uuid'])
            : ''
    );

    $handler->log_storage->log_message(array_merge($handler->build_content_writer_log_base(
        $conversation_uuid,
        (string) ($request_data['provider'] ?? ''),
        (string) ($request_data['model'] ?? '')
    ), [
        'message_role'      => 'user',
        'message_content'   => "Content Writer Request ({$request_type}): " . esc_html($request_data['content_title']),
        'request_payload'   => $initial_request_details_for_log
    ]));
}

/**
 * Updates the Google Sheets status column for Content Writer gsheets mode.
 *
 * @param array  $settings The current action payload or sanitized settings.
 * @param string $status_prefix Status prefix such as "Queued on" or "Processed on".
 * @return bool|WP_Error True on success or no-op, WP_Error on failure.
 */
function maybe_update_gsheets_row_status_logic(array $settings, string $status_prefix)
{
    $generation_mode = isset($settings['cw_generation_mode'])
        ? sanitize_key((string) $settings['cw_generation_mode'])
        : '';

    if ($generation_mode !== 'gsheets') {
        return true;
    }

    $sheet_id = isset($settings['gsheets_sheet_id'])
        ? sanitize_text_field((string) $settings['gsheets_sheet_id'])
        : '';
    $row_index = isset($settings['gsheets_row_index'])
        ? absint($settings['gsheets_row_index'])
        : 0;

    if ($sheet_id === '' || $row_index <= 0) {
        return new WP_Error(
            'missing_gsheets_status_context',
            __('Google Sheets status update is missing the sheet ID or row index.', 'gpt3-ai-content-generator')
        );
    }

    if (!class_exists(AIPKit_Google_Credentials_Handler::class)) {
        return new WP_Error(
            'gsheets_credentials_handler_missing',
            __('Google Sheets credentials handler is unavailable.', 'gpt3-ai-content-generator')
        );
    }

    $credentials = AIPKit_Google_Credentials_Handler::process_credentials($settings['gsheets_credentials'] ?? null);
    if (!is_array($credentials) || empty($credentials['private_key']) || empty($credentials['client_email'])) {
        return new WP_Error(
            'invalid_gsheets_status_credentials',
            __('Google Sheets status update is missing valid service account credentials.', 'gpt3-ai-content-generator')
        );
    }

    if (!class_exists(AIPKit_Google_Sheets_Parser::class)) {
        return new WP_Error(
            'gsheets_parser_missing',
            __('Google Sheets parser component is missing.', 'gpt3-ai-content-generator')
        );
    }

    try {
        $sheets_parser = new AIPKit_Google_Sheets_Parser($credentials);
        $status_text = trim($status_prefix) . ' ' . current_time('mysql');

        return $sheets_parser->update_row_status($sheet_id, $row_index, $status_text);
    } catch (\Exception $e) {
        return new WP_Error(
            'gsheets_status_update_exception',
            sprintf(
                /* translators: %s: Exception message. */
                __('Failed to update Google Sheets status: %s', 'gpt3-ai-content-generator'),
                $e->getMessage()
            )
        );
    }
}

/**
 * Resolves duplicate or unsuitable Smart SEO focus keyphrases before dependent generation steps.
 */
function resolve_smart_seo_keywords_logic(array $validated_params, ?AIPKit_AI_Caller $ai_caller, array $context = []): array
{
    $source = '';
    $keywords = '';

    if (!empty($validated_params['inline_keywords'])) {
        $source = 'inline';
        $keywords = (string) $validated_params['inline_keywords'];
    } elseif (!empty($validated_params['content_keywords'])) {
        $source = 'global';
        $keywords = (string) $validated_params['content_keywords'];
    }

    if ($source === '' || trim($keywords) === '' || !$ai_caller) {
        return [
            'params' => $validated_params,
            'resolution' => [],
        ];
    }

    load_smart_seo_keyword_resolver_logic();
    if (
        !class_exists(AIPKit_Content_Writer_SEO_Config::class)
        || !AIPKit_Content_Writer_SEO_Config::is_enabled($validated_params)
        || !class_exists(AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver::class)
    ) {
        return [
            'params' => $validated_params,
            'resolution' => [],
        ];
    }

    $resolver = new AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver();
    $resolver_context = array_merge([
        'ai_provider' => $validated_params['provider'] ?? $validated_params['ai_provider'] ?? '',
        'ai_model' => $validated_params['model'] ?? $validated_params['ai_model'] ?? '',
        'topic' => $validated_params['content_title'] ?? '',
        'title' => $validated_params['content_title'] ?? '',
        'keywords' => $keywords,
    ], $context);

    $result = $resolver->maybe_resolve_keywords($keywords, $validated_params, $ai_caller, $resolver_context);
    if (empty($result['changed'])) {
        return [
            'params' => $validated_params,
            'resolution' => $result,
        ];
    }

    $resolved_keywords = sanitize_text_field((string) ($result['keywords'] ?? $keywords));
    if ($source === 'inline') {
        $validated_params['inline_keywords'] = $resolved_keywords;
    } else {
        $validated_params['content_keywords'] = $resolved_keywords;
    }

    $result['source'] = $source;
    $result['resolved_content_title'] = build_resolved_content_title_logic(
        (string) ($validated_params['content_title'] ?? ''),
        $resolved_keywords,
        $source
    );

    return [
        'params' => $validated_params,
        'resolution' => $result,
    ];
}

function load_smart_seo_keyword_resolver_logic(): void
{
    if (
        class_exists(AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver::class)
        && class_exists(AIPKit_Content_Writer_Smart_SEO_Keyphrase_Usage::class)
    ) {
        return;
    }

    if (!defined('WPAICG_LIB_DIR')) {
        return;
    }

    $loader_class = '\\WPAICG\\Lib\\DependencyLoaders\\Smart_SEO_Dependencies_Loader';
    $loader_path = WPAICG_LIB_DIR . 'dependency-loaders/class-smart-seo-dependencies-loader.php';
    if (!class_exists($loader_class) && file_exists($loader_path)) {
        require_once $loader_path;
    }

    if (class_exists($loader_class)) {
        $loader_class::load();
    }
}

function build_resolved_content_title_logic(string $topic, string $resolved_keywords, string $source): string
{
    $topic = trim($topic);
    $resolved_keywords = trim($resolved_keywords);

    if ($source !== 'inline' || $resolved_keywords === '') {
        return $topic;
    }

    return $topic !== '' ? $topic . ' | ' . $resolved_keywords : $resolved_keywords;
}

function smart_seo_keyword_resolution_response_fields_logic(array $resolution): array
{
    if (empty($resolution['changed'])) {
        return [];
    }

    return [
        'resolved_focus_keyword' => sanitize_text_field((string) ($resolution['keyword'] ?? '')),
        'resolved_keywords' => sanitize_text_field((string) ($resolution['keywords'] ?? '')),
        'resolved_keyword_source' => sanitize_key((string) ($resolution['source'] ?? '')),
        'resolved_content_title' => sanitize_text_field((string) ($resolution['resolved_content_title'] ?? '')),
        'resolved_keyword_original' => sanitize_text_field((string) ($resolution['original_keyword'] ?? '')),
        'resolved_keyword_used_count' => absint($resolution['used_count'] ?? 0),
        'resolved_keyword_reason' => sanitize_key((string) ($resolution['reason'] ?? '')),
    ];
}

/**
 * @param mixed $primary_usage
 * @param mixed $secondary_usage
 * @return mixed
 */
function merge_smart_seo_usage_logic($primary_usage, $secondary_usage)
{
    if (!is_array($secondary_usage)) {
        return $primary_usage;
    }

    if (!is_array($primary_usage)) {
        return $secondary_usage;
    }

    $merged = $primary_usage;
    $merged['input_tokens'] = (int) ($primary_usage['input_tokens'] ?? 0) + (int) ($secondary_usage['input_tokens'] ?? 0);
    $merged['output_tokens'] = (int) ($primary_usage['output_tokens'] ?? 0) + (int) ($secondary_usage['output_tokens'] ?? 0);
    $merged['total_tokens'] = (int) ($primary_usage['total_tokens'] ?? 0) + (int) ($secondary_usage['total_tokens'] ?? 0);

    if (isset($secondary_usage['provider_raw'])) {
        $primary_provider_raw = isset($primary_usage['provider_raw']) ? $primary_usage['provider_raw'] : [];
        $merged['provider_raw'] = [$primary_provider_raw, $secondary_usage['provider_raw']];
    }

    return $merged;
}
