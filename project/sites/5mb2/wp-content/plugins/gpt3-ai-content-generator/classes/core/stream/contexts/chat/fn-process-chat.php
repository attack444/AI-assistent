<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Core\Stream\Contexts\Chat;

use WP_Error;

require_once __DIR__ . '/process/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Main orchestrator function for processing a chat stream request.
*
* @param \WPAICG\Core\Stream\Contexts\Chat\SSEChatStreamContextHandler $handlerInstance The instance of the context handler.
* @param array $cached_data Contains 'user_message', 'image_inputs', and potentially 'client_user_message_id', active vector file context IDs, and 'active_claude_file_id'.
* @param array $get_params Original $_GET parameters.
* @return array|WP_Error Prepared data for SSEStreamProcessor or WP_Error.
*/
function process_chat_logic(
    SSEChatStreamContextHandler $handlerInstance,
    array $cached_data,
    array $get_params
) {
    // 1. Extract Parameters
    $params = Process\extract_request_params_logic($cached_data, $get_params);

    // 2. Validate Stream Requirements
    $validation_result = Process\validate_stream_requirements_logic(
        $params['bot_id'],
        $params['conversation_uuid'],
        $params['user_id'],
        $params['session_id'],
        $params['user_message_text'],
        $params['image_inputs']
    );
    if (is_wp_error($validation_result)) {
        return $validation_result;
    }

    $bot_storage = $handlerInstance->get_bot_storage();
    if (!$bot_storage) {
        return new WP_Error('dependency_missing_bot_storage_moderation', 'Bot storage is unavailable for moderation.', ['status' => 500]);
    }

    $bot_settings = $bot_storage->get_chatbot_settings($params['bot_id']);
    if (empty($bot_settings)) {
        return new WP_Error('settings_load_failure_moderation', __('Could not load chatbot configuration.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }

    $is_resume_after_form_submission = false;
    $form_resume_state = [];
    $form_resume_user_message_id = null;
    if (!empty($params['resume_after_form_submission'])) {
        $resume_token_result = validate_form_submission_resume_token_logic($params);
        if (is_wp_error($resume_token_result)) {
            return $resume_token_result;
        }
        $is_resume_after_form_submission = true;
        $form_resume_state = is_array($resume_token_result) ? $resume_token_result : [];

        $existing_history = $handlerInstance->get_log_storage()->get_conversation_thread_history(
            $params['user_id'] ? (int) $params['user_id'] : null,
            $params['user_id'] ? null : (string) $params['session_id'],
            (int) $params['bot_id'],
            (string) $params['conversation_uuid']
        );
        $params['user_message_text'] = '';
        if (is_array($existing_history)) {
            for ($index = count($existing_history) - 1; $index >= 0; $index--) {
                $history_item = $existing_history[$index] ?? [];
                if (!is_array($history_item) || sanitize_key((string) ($history_item['role'] ?? '')) !== 'user') {
                    continue;
                }
                $logged_user_message = isset($history_item['content']) ? trim((string) $history_item['content']) : '';
                if ($logged_user_message === '') {
                    continue;
                }
                $params['user_message_text'] = $logged_user_message;
                $form_resume_user_message_id = sanitize_key((string) ($history_item['message_id'] ?? ''));
                break;
            }
        }
        if ($params['user_message_text'] === '') {
            return new WP_Error('missing_form_resume_user_message', __('No captured user message was found for this form resume.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }

        if (isset($form_resume_state['form_submission_context']) && is_array($form_resume_state['form_submission_context'])) {
            $params['form_submission_context'] = $form_resume_state['form_submission_context'];
        }
    }

    // 3. Token Check
    $token_manager = $handlerInstance->get_token_manager();
    if (!$token_manager) {
        return new WP_Error('dependency_missing_token_manager', 'Token manager is unavailable.', ['status' => 500]);
    }
    $token_check_result = Process\run_token_check_logic(
        $token_manager,
        $params['user_id'],
        $params['session_id'],
        $params['bot_id'],
        $bot_settings,
        $params['user_message_text'],
        $params['image_inputs'],
        $handlerInstance->get_log_storage()
    );
    if (is_wp_error($token_check_result)) {
        return $token_check_result;
    }

    // 4. Content Moderation
    $moderation_result = Process\run_content_moderation_logic($params['user_message_text'], $params['client_ip'], $bot_settings, $handlerInstance->get_log_storage(), $params['bot_id'], $params['user_id'], $params['session_id']);
    if (is_wp_error($moderation_result)) {
        return $moderation_result;
    }

    // 5. Log User Message & Determine if New Session
    $base_log_data = [
        'bot_id' => $params['bot_id'], 'user_id' => $params['user_id'], 'session_id' => $params['session_id'],
        'conversation_uuid' => $params['conversation_uuid'], 'module' => 'chat', 'is_guest' => ($params['user_id'] === 0),
        'role' => ($params['user_id'] > 0 && class_exists('WP_User') && ($u = get_user_by('id', $params['user_id'])) && isset($u->roles) && is_array($u->roles)) ? implode(', ', $u->roles) : 'guest',
        'ip_address' => $params['client_ip'], 'form_id' => null,
        'user_message_id_from_client' => $params['client_user_message_id'],
    ];
    $bot_message_id_for_stream = 'aipkit-msg-' . uniqid('', true);
    $base_log_data['bot_message_id'] = $bot_message_id_for_stream;

    if ($is_resume_after_form_submission) {
        $user_log_result = [
            'log_id' => 0,
            'message_id' => $form_resume_user_message_id,
            'is_new_session' => false,
        ];
    } else {
        $user_log_result = Process\log_user_message_logic($handlerInstance->get_log_storage(), $base_log_data, $params['user_message_text'], $params['image_inputs'], time());
        if (is_wp_error($user_log_result)) {
            return $user_log_result;
        }
    }
    $is_new_session = $user_log_result['is_new_session'] ?? false;

    // 6. Trigger Processing
    $ai_service_for_triggers = $handlerInstance->get_ai_service_for_helper();
    if (!$ai_service_for_triggers) {
        return new WP_Error('dependency_missing_aiservice_triggers', 'AI Service component missing for triggers.', ['status' => 500]);
    }

    if (function_exists('\WPAICG\Chat\Core\AIService\determine_provider_model')) {
        $provider_model_info = \WPAICG\Chat\Core\AIService\determine_provider_model($ai_service_for_triggers, $bot_settings);
    } else {
        $provider_model_info = ['provider' => $bot_settings['provider'] ?? null, 'model' => $bot_settings['model'] ?? null];
    }

    if (!$is_resume_after_form_submission) {
        Process\emit_chatbot_user_events_logic(
            $handlerInstance->get_log_storage(),
            [
                'bot_id' => $params['bot_id'],
                'user_id' => $params['user_id'],
                'conversation_uuid' => $params['conversation_uuid'],
                'user_message_text' => $params['user_message_text'],
                'current_provider' => $provider_model_info['provider'] ?? null,
                'current_model_id' => $provider_model_info['model'] ?? null,
                'base_log_data' => $base_log_data,
            ],
            $user_log_result,
            $provider_model_info
        );
    }

    $trigger_context = [
        'bot_id' => $params['bot_id'], 'bot_settings' => $bot_settings, 'user_id' => $params['user_id'], 'session_id' => $params['session_id'],
        'client_ip' => $params['client_ip'], 'post_id' => $params['post_id'],
        'user_message_text' => $params['user_message_text'],
        'system_instruction_for_ai' => $bot_settings['instructions'] ?? '',
        'user_wp_role' => $base_log_data['role'],
        'current_provider' => $provider_model_info['provider'], 'current_model_id' => $provider_model_info['model'],
        'base_log_data' => $base_log_data, 'log_storage' => $handlerInstance->get_log_storage()
    ];

    if ($is_resume_after_form_submission) {
        $resume_instruction = build_form_submission_resume_instruction_logic(
            isset($params['form_submission_context']) && is_array($params['form_submission_context']) ? $params['form_submission_context'] : []
        );
        if ($resume_instruction !== '') {
            $trigger_context['system_instruction_for_ai'] = trim((string) $trigger_context['system_instruction_for_ai'] . "\n\n" . $resume_instruction);
        }
    }

    $initial_trigger_reply_data = null;

    if ($is_new_session) {
        $session_trigger_result = Process\process_session_start_trigger_logic($trigger_context);
        if (is_wp_error($session_trigger_result)) {
            return $session_trigger_result;
        }
        // Update context based on session trigger result for user_message triggers
        $trigger_context['system_instruction_for_ai'] = $session_trigger_result['modified_context_data']['system_instruction'] ?? $trigger_context['system_instruction_for_ai'];
        $trigger_context['final_user_message_for_ai'] = $session_trigger_result['modified_context_data']['user_message_text'] ?? $trigger_context['user_message_text'];
        $initial_trigger_reply_data = $session_trigger_result['message_to_user'] ? $session_trigger_result : null;
    } else {
        $trigger_context['final_user_message_for_ai'] = $trigger_context['user_message_text'];
        $trigger_context['final_system_instruction_for_ai'] = $trigger_context['system_instruction_for_ai'];
    }

    $history_for_triggers = $handlerInstance->get_log_storage()->get_conversation_thread_history($params['user_id'] ?: null, $params['session_id'], $params['bot_id'], $params['conversation_uuid']);
    if (count($history_for_triggers) > 0 && end($history_for_triggers)['role'] === 'user') {
        array_pop($history_for_triggers);
    }
    $max_msgs_for_history = isset($bot_settings['max_messages']) ? absint($bot_settings['max_messages']) : 15;
    if (count($history_for_triggers) > $max_msgs_for_history) {
        $history_for_triggers = array_slice($history_for_triggers, -$max_msgs_for_history);
    }
    $trigger_context['final_history_for_ai'] = $history_for_triggers;

    if ($is_resume_after_form_submission) {
        $user_message_trigger_result = [
            'status' => 'processed',
            'message_to_user' => null,
            'message_id' => null,
            'modified_context_data' => [
                'system_instruction' => $trigger_context['system_instruction_for_ai'],
                'user_message_text' => $trigger_context['final_user_message_for_ai'],
                'current_history' => $trigger_context['final_history_for_ai'],
            ],
            'stop_ai_processing' => false,
            'display_form_event_data' => null,
        ];
    } else {
        $user_message_trigger_result = Process\process_user_message_trigger_logic($trigger_context);
        if (is_wp_error($user_message_trigger_result)) {
            return $user_message_trigger_result;
        }
        if (!$initial_trigger_reply_data && $user_message_trigger_result['message_to_user']) { // Only overwrite if session didn't provide one
            $initial_trigger_reply_data = $user_message_trigger_result;
        }
    }

    // 7. Build AI Request Data
    $request_data_for_ai = Process\build_ai_request_data_for_stream_logic(
        $ai_service_for_triggers->get_ai_caller(),
        $ai_service_for_triggers->get_vector_store_manager(),
        $user_message_trigger_result['modified_context_data']['user_message_text'],
        $bot_settings,
        $provider_model_info['provider'],
        $provider_model_info['model'],
        $user_message_trigger_result['modified_context_data']['current_history'],
        $user_message_trigger_result['modified_context_data']['system_instruction'],
        $params['post_id'],
        $params['image_inputs'],
        $params['frontend_previous_openai_response_id'],
        $params['frontend_openai_web_search_active'],
        $params['frontend_google_search_grounding_active'],
        $params['active_openai_vs_id'],
        $params['active_pinecone_index_name'],
        $params['active_pinecone_namespace'],
        $params['active_qdrant_collection_name'],
        $params['active_qdrant_file_upload_context_id'],
        $params['active_chroma_collection_name'],
        $params['active_chroma_file_upload_context_id'],
        $params['active_claude_file_id']
    );
    if (is_wp_error($request_data_for_ai)) {
        return $request_data_for_ai;
    }

    // 8. Construct Final Input for SSEStreamProcessor
    return Process\construct_sse_processor_input_logic(
        $request_data_for_ai,
        $params['conversation_uuid'],
        $base_log_data,
        $bot_message_id_for_stream,
        $initial_trigger_reply_data
    );
}

/**
 * Builds a system instruction that lets the resumed stream continue after a required chat form.
 *
 * @param array<string, mixed> $form_submission_context
 * @return string
 */
function build_form_submission_resume_instruction_logic(array $form_submission_context): string
{
    $form_id = sanitize_text_field((string) ($form_submission_context['form_id'] ?? ''));
    $submitted_display = isset($form_submission_context['submitted_data_display']) && is_array($form_submission_context['submitted_data_display'])
        ? $form_submission_context['submitted_data_display']
        : (isset($form_submission_context['submitted_data']) && is_array($form_submission_context['submitted_data']) ? $form_submission_context['submitted_data'] : []);
    $submitted_labels = isset($form_submission_context['submitted_data_labels']) && is_array($form_submission_context['submitted_data_labels'])
        ? $form_submission_context['submitted_data_labels']
        : [];

    $lines = [];
    foreach ($submitted_display as $field_key => $value) {
        $label = isset($submitted_labels[$field_key]) && is_string($submitted_labels[$field_key]) && trim($submitted_labels[$field_key]) !== ''
            ? $submitted_labels[$field_key]
            : (string) $field_key;
        $display_value = stringify_form_submission_resume_value($value);
        if ($display_value === '') {
            continue;
        }
        $lines[] = '- ' . sanitize_text_field($label) . ': ' . sanitize_text_field($display_value);
    }

    $instruction = "The visitor submitted the requested chat form. Continue answering the visitor's previous question now. Do not ask for the same form again unless genuinely more information is required.";
    if ($form_id !== '') {
        $instruction .= "\nForm ID: " . $form_id;
    }
    if (!empty($lines)) {
        $instruction .= "\nSubmitted form data:\n" . implode("\n", $lines);
    }

    return $instruction;
}

/**
 * Verifies that the resume stream was initiated by a successful chat form submission.
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>|WP_Error
 */
function validate_form_submission_resume_token_logic(array $params)
{
    $token = sanitize_text_field((string) ($params['form_resume_token'] ?? ''));
    if ($token === '') {
        return new WP_Error('missing_form_resume_token', __('Form resume token is missing.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    $resume_state = get_transient('aipkit_chat_form_resume_' . sha1($token));
    delete_transient('aipkit_chat_form_resume_' . sha1($token));
    if (!is_array($resume_state)) {
        return new WP_Error('invalid_form_resume_token', __('Form resume token is invalid or expired.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    $form_id = sanitize_text_field((string) ($resume_state['form_id'] ?? ''));

    if (
        $form_id === ''
        ||
        absint($resume_state['bot_id'] ?? 0) !== absint($params['bot_id'] ?? 0)
        || sanitize_key((string) ($resume_state['conversation_uuid'] ?? '')) !== sanitize_key((string) ($params['conversation_uuid'] ?? ''))
    ) {
        return new WP_Error('invalid_form_resume_scope', __('Form resume token does not match this chat session.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    $user_id = absint($params['user_id'] ?? 0);
    if ($user_id > 0) {
        if (absint($resume_state['user_id'] ?? 0) !== $user_id) {
            return new WP_Error('invalid_form_resume_user', __('Form resume token does not match this user.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }
        return $resume_state;
    }

    $session_id = sanitize_text_field((string) ($params['session_id'] ?? ''));
    if ($session_id === '' || sanitize_text_field((string) ($resume_state['session_id'] ?? '')) !== $session_id) {
        return new WP_Error('invalid_form_resume_session', __('Form resume token does not match this guest session.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    return $resume_state;
}

/**
 * @param mixed $value
 * @return string
 */
function stringify_form_submission_resume_value($value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $item_value = stringify_form_submission_resume_value($item);
            if ($item_value !== '') {
                $parts[] = $item_value;
            }
        }
        return implode(', ', $parts);
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if ($value === null) {
        return '';
    }

    return (string) $value;
}
