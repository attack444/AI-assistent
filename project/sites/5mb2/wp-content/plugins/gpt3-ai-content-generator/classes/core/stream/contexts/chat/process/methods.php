<?php

namespace WPAICG\Core\Stream\Contexts\Chat\Process;

use WPAICG\Chat\Admin\AdminSetup;
use WP_Error;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\AIPKit_Content_Moderator;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage;
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager;
use WPAICG\Core\AIPKit_Payload_Sanitizer;
use WPAICG\Core\AIPKit_Event_Webhooks;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Core\AIPKit_Instruction_Manager;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\Providers\Google\GoogleSettingsHandler;
use WPAICG\Core\Providers\OpenAI\OpenAIStatefulConversationHelper;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\Chat\Storage\BotSettingsManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- extract-request-params.php ---
/**
 * Extracts and sanitizes request parameters for chat stream processing.
 *
 * @param array $cached_data Data retrieved from the SSE cache.
 * @param array $get_params Original $_GET parameters from the SSE request.
 * @return array An associative array of extracted and sanitized parameters.
 */
function extract_request_params_logic(array $cached_data, array $get_params): array
{
    return [
        'user_id'            => get_current_user_id(),
        'bot_id'             => isset($get_params['bot_id']) ? absint($get_params['bot_id']) : 0,
        'session_id'         => isset($get_params['session_id']) ? sanitize_text_field(wp_unslash($get_params['session_id'])) : '',
        'conversation_uuid'  => isset($get_params['conversation_uuid']) ? sanitize_key($get_params['conversation_uuid']) : '',
        'post_id'            => isset($get_params['post_id']) ? absint($get_params['post_id']) : 0,
        'user_message_text'  => $cached_data['user_message'] ?? '',
        'image_inputs'       => $cached_data['image_inputs'] ?? null,
        'client_ip'          => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
        'frontend_previous_openai_response_id' => isset($get_params['previous_openai_response_id']) ? sanitize_text_field($get_params['previous_openai_response_id']) : null,
        'frontend_openai_web_search_active' => (isset($get_params['frontend_web_search_active']) && $get_params['frontend_web_search_active'] === 'true'),
        'frontend_google_search_grounding_active' => (isset($get_params['frontend_google_search_grounding_active']) && $get_params['frontend_google_search_grounding_active'] === 'true'),
        'client_user_message_id' => $cached_data['client_user_message_id'] ?? null,
        'resume_after_form_submission' => !empty($cached_data['resume_after_form_submission']),
        'form_submission_context' => isset($cached_data['form_submission_context']) && is_array($cached_data['form_submission_context']) ? $cached_data['form_submission_context'] : [],
        'form_resume_token' => isset($cached_data['form_resume_token']) ? sanitize_text_field((string) $cached_data['form_resume_token']) : '',
        'active_openai_vs_id' => $cached_data['active_openai_vs_id'] ?? ($get_params['active_openai_vs_id'] ?? null),
        'active_pinecone_index_name' => $cached_data['active_pinecone_index_name'] ?? ($get_params['active_pinecone_index_name'] ?? null),
        'active_pinecone_namespace' => $cached_data['active_pinecone_namespace'] ?? ($get_params['active_pinecone_namespace'] ?? null),
        'active_qdrant_collection_name' => $cached_data['active_qdrant_collection_name'] ?? ($get_params['active_qdrant_collection_name'] ?? null),
        'active_qdrant_file_upload_context_id' => $cached_data['active_qdrant_file_upload_context_id'] ?? ($get_params['active_qdrant_file_upload_context_id'] ?? null),
        'active_chroma_collection_name' => $cached_data['active_chroma_collection_name'] ?? ($get_params['active_chroma_collection_name'] ?? null),
        'active_chroma_file_upload_context_id' => $cached_data['active_chroma_file_upload_context_id'] ?? ($get_params['active_chroma_file_upload_context_id'] ?? null),
        'active_claude_file_id' => isset($cached_data['active_claude_file_id'])
            ? sanitize_text_field((string) $cached_data['active_claude_file_id'])
            : (isset($get_params['active_claude_file_id']) ? sanitize_text_field(wp_unslash($get_params['active_claude_file_id'])) : null),
    ];
}

// --- validate-stream-requirements.php ---
/**
 * Validates the essential requirements for a chat stream request.
 *
 * @param int         $bot_id            The ID of the chatbot.
 * @param string      $conversation_uuid The UUID of the conversation.
 * @param int|null    $user_id           The ID of the logged-in user, or null for guests.
 * @param string|null $session_id        The session ID for guests.
 * @param string      $user_message_text The user's text message.
 * @param array|null  $image_inputs      Processed image input data.
 * @return true|WP_Error True if validation passes, WP_Error otherwise.
 */
function validate_stream_requirements_logic(
    int $bot_id,
    string $conversation_uuid,
    ?int $user_id,
    ?string $session_id,
    string $user_message_text,
    ?array $image_inputs
) {
    // Ensure AdminSetup is loaded for POST_TYPE constant
    if (!class_exists(AdminSetup::class)) {
        $admin_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php';
        if (file_exists($admin_setup_path)) {
            require_once $admin_setup_path;
        } else {
            return new WP_Error(
                'dependency_missing_validator_logic',
                __('Chat system component (AdminSetup) missing.', 'gpt3-ai-content-generator'),
                ['status' => 500, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'load_admin_setup']
            );
        }
    }

    if (empty($bot_id) || get_post_type($bot_id) !== AdminSetup::POST_TYPE || get_post_status($bot_id) !== 'publish') {
        return new WP_Error('invalid_bot_id_stream_req', __('Invalid chatbot specified.', 'gpt3-ai-content-generator'), ['status' => 400, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'validate_bot_id']);
    }
    if (empty($conversation_uuid)) {
        return new WP_Error('missing_conversation_uuid_stream_req', __('Conversation ID is missing.', 'gpt3-ai-content-generator'), ['status' => 400, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'validate_conv_uuid']);
    }
    if (!$user_id && empty($session_id)) {
        return new WP_Error('missing_session_id_stream_req', __('Session ID is missing for guest.', 'gpt3-ai-content-generator'), ['status' => 400, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'validate_session_id']);
    }
    if (empty($user_message_text) && empty($image_inputs)) {
        return new WP_Error('empty_content_stream_req', __('Message or image cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'validate_content']);
    }
    return true;
}

// --- run-token-check.php ---

require_once WPAICG_PLUGIN_DIR . 'classes/chat/core/pricing/fn-build-chat-pricing-check-context.php';

/**
 * Performs token limit checks for a chat stream request.
 * Dispatches a 'system_error_occurred' trigger if the token check fails.
 *
 * @param AIPKit_Token_Manager $token_manager Instance of the token manager.
 * @param int|null    $user_id          User ID, or null for guests.
 * @param string|null $session_id       Session ID for guests.
 * @param int         $bot_id           Bot ID for the current chat context.
 * @param array<string, mixed> $bot_settings Bot configuration used to estimate priced usage.
 * @param string $user_message_text Current user message for conservative token estimation.
 * @param array<int, mixed>|null $image_inputs Optional image inputs for multimodal estimation.
 * @param LogStorage|null $log_storage    Instance of LogStorage (for trigger manager).
 * @return true|WP_Error True if token check passes or not applicable, WP_Error if limit exceeded.
 */
function run_token_check_logic(
    AIPKit_Token_Manager $token_manager,
    ?int $user_id,
    ?string $session_id,
    int $bot_id,
    array $bot_settings,
    string $user_message_text,
    ?array $image_inputs,
    ?LogStorage $log_storage
) {
    $usage_context = \WPAICG\Chat\Core\Pricing\build_chat_pricing_check_context_logic(
        $bot_id,
        $bot_settings,
        $user_message_text,
        $image_inputs
    );
    $token_check_result = $token_manager->check_and_reset_tokens($user_id ?: null, $session_id, $bot_id, 'chat', $usage_context);

    if (is_wp_error($token_check_result)) {
        $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
        $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
        $triggers_enabled = false;
        if (class_exists('\WPAICG\aipkit_dashboard')) {
            $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
        }

        if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
            // Only proceed if log storage is available for the trigger manager
            if ($log_storage) {
                $error_data = $token_check_result->get_error_data() ?: [];
                $error_event_context = [
                    'error_code'    => $token_check_result->get_error_code(),
                    'error_message' => $token_check_result->get_error_message(),
                    'bot_id'        => $bot_id,
                    'user_id'       => $user_id ?: null,
                    'session_id'    => $session_id,
                    'module'        => 'chat_stream_context',
                    'operation'     => 'token_check',
                    'status_code'   => is_array($error_data) && isset($error_data['status']) ? (int)$error_data['status'] : 429,
                ];
                try {
                    $trigger_storage = new $trigger_storage_class();
                    $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage);
                    $trigger_manager->process_event($bot_id, 'system_error_occurred', $error_event_context);
                } catch (\Exception $e) {
                    // Exception is caught and ignored to prevent fatal errors.
                }
            }
        }
        // Return the original error from token manager, including status code
        $token_error_data = $token_check_result->get_error_data();
        if (!is_array($token_error_data)) {
            $token_error_data = [];
        }
        if (!isset($token_error_data['status'])) {
            $token_error_data['status'] = 429;
        }
        return new WP_Error($token_check_result->get_error_code(), $token_check_result->get_error_message(), $token_error_data);
    }
    return true; // Token check passed
}

// --- run-content-moderation.php ---
/**
 * Performs content moderation checks (global blocklists and OpenAI moderation).
 * Dispatches a 'system_error_occurred' trigger if moderation fails.
 *
 * @param string $user_message_text The user's text message.
 * @param string|null $client_ip The client's IP address.
 * @param array $bot_settings Settings for the current bot.
 * @param LogStorage|null $log_storage Instance of LogStorage for triggers.
 * @param int $bot_id The ID of the current bot.
 * @param int|null $user_id The ID of the user, or null for guests.
 * @param string|null $session_id The session ID for guests.
 * @return true|WP_Error True if moderation passes, WP_Error otherwise.
 */
function run_content_moderation_logic(
    string $user_message_text,
    ?string $client_ip,
    array $bot_settings,
    ?LogStorage $log_storage,
    int $bot_id,
    ?int $user_id,
    ?string $session_id
) {

    if (!class_exists(AIPKit_Content_Moderator::class)) {
        return true; // Fail open if moderator class is missing.
    }

    $moderation_context = [
        'client_ip' => $client_ip,
        'bot_settings' => $bot_settings,
        'module' => 'chat',
    ];
    $moderation_check = AIPKit_Content_Moderator::check_content($user_message_text, $moderation_context);

    if (is_wp_error($moderation_check)) {
        $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
        $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
        $triggers_enabled = false;
        if (class_exists('\WPAICG\aipkit_dashboard')) {
            $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
        }

        if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
            // Only proceed if log storage is available for the trigger manager
            if ($log_storage) {
                $error_data = $moderation_check->get_error_data() ?: [];
                $error_event_context = [
                    'error_code'    => $moderation_check->get_error_code(),
                    'error_message' => $moderation_check->get_error_message(),
                    'bot_id'        => $bot_id,
                    'user_id'       => $user_id ?: null,
                    'session_id'    => $session_id,
                    'module'        => 'chat_content_moderation',
                    'operation'     => 'check_user_message',
                    'status_code'   => is_array($error_data) && isset($error_data['status']) ? (int)$error_data['status'] : 400,
                ];
                try {
                    $trigger_storage = new $trigger_storage_class();
                    $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage);
                    $trigger_manager->process_event($bot_id, 'system_error_occurred', $error_event_context);
                } catch (\Exception $e) {
                     // Exception is caught and ignored to prevent fatal errors.
                }
            }
        }
        // Return the original WP_Error, ensuring status code is preserved
        $status_code_from_error = is_array($moderation_check->get_error_data()) && isset($moderation_check->get_error_data()['status'])
                                  ? (int)$moderation_check->get_error_data()['status']
                                  : 400;
        return new WP_Error($moderation_check->get_error_code(), $moderation_check->get_error_message(), ['status' => $status_code_from_error]);
    }
    return true; // Moderation passed
}

// --- log-user-message.php ---
/**
* Logs the initial user message for a chat stream.
*
* @param LogStorage $log_storage Instance of LogStorage.
* @param array $base_log_data Base log data (bot_id, user_id, session_id, conversation_uuid, module, is_guest, role, ip_address, bot_message_id FOR BOT, user_message_id_from_client FOR USER).
* @param string $user_message_text The user's text message.
* @param array|null $image_inputs Processed image input data.
* @param int $request_timestamp The timestamp of the request.
* @return array|WP_Error Result from LogStorage::log_message or WP_Error on failure.
*/
function log_user_message_logic(
    LogStorage $log_storage,
    array $base_log_data,
    string $user_message_text,
    ?array $image_inputs,
    int $request_timestamp
) {
    $log_user_data = [
        'bot_id'            => $base_log_data['bot_id'],
        'user_id'           => $base_log_data['user_id'],
        'session_id'        => $base_log_data['session_id'],
        'conversation_uuid' => $base_log_data['conversation_uuid'],
        'module'            => $base_log_data['module'],
        'is_guest'          => $base_log_data['is_guest'],
        'role'              => $base_log_data['role'],
        'ip_address'        => $base_log_data['ip_address'] ?? null,
        'ip_anonymize'      => $base_log_data['ip_anonymize'] ?? false,
        'message_role'      => 'user',
        'message_content'   => $user_message_text,
        'timestamp'         => $request_timestamp,
        'message_id'        => $base_log_data['user_message_id_from_client'] ?? null,
    ];
    unset($log_user_data['bot_message_id'], $log_user_data['user_message_id_from_client']);


    if (!empty($image_inputs)) {
        $response_data_for_log = AIPKit_Payload_Sanitizer::sanitize_payload_if_array([
            'type' => 'user_image_upload',
            'images' => $image_inputs,
        ]);
        $log_user_data['response_data'] = $response_data_for_log;
    }

    $user_log_result = $log_storage->log_message($log_user_data);

    if ($user_log_result === false) {
        return new WP_Error('user_log_failed_logic', __('Failed to log user message.', 'gpt3-ai-content-generator'), ['status' => 500]);
    }
    return $user_log_result; // Contains ['log_id', 'message_id', 'is_new_session']
}

// --- emit-chatbot-events.php ---
/**
 * Emits the canonical chatbot user-side events after the user message is stored.
 *
 * @param LogStorage $log_storage
 * @param array<string, mixed> $context
 * @param array<string, mixed> $user_log_result
 * @param array<string, mixed> $provider_model_info
 * @return void
 */
function emit_chatbot_user_events_logic(
    LogStorage $log_storage,
    array $context,
    array $user_log_result,
    array $provider_model_info = []
): void {
    if (!class_exists(AIPKit_Event_Webhooks::class)) {
        return;
    }

    $log_id = absint($user_log_result['log_id'] ?? 0);
    $message_id = sanitize_key((string) ($user_log_result['message_id'] ?? ''));
    $conversation_uuid = sanitize_key((string) ($context['conversation_uuid'] ?? ($context['base_log_data']['conversation_uuid'] ?? '')));
    $bot_id = absint($context['bot_id'] ?? 0);
    $user_id = absint($context['user_id'] ?? 0);
    $actor_type = $user_id > 0 ? 'user' : 'guest';

    if ($log_id <= 0 || $message_id === '' || $conversation_uuid === '' || $bot_id <= 0) {
        return;
    }

    $conversation_log = $log_storage->get_log_by_id($log_id);
    $bot_name = sanitize_text_field((string) ($conversation_log['bot_name'] ?? get_the_title($bot_id)));
    $message_count = absint($conversation_log['message_count'] ?? 1);
    $message_text = sanitize_textarea_field((string) ($context['user_message_text'] ?? ''));
    $provider = sanitize_text_field((string) ($provider_model_info['provider'] ?? ($context['current_provider'] ?? '')));
    $model = sanitize_text_field((string) ($provider_model_info['model'] ?? ($context['current_model_id'] ?? '')));

    $payload = [
        'bot' => [
            'id' => $bot_id,
            'name' => $bot_name,
        ],
        'conversation' => [
            'id' => $conversation_uuid,
            'message_count' => $message_count,
        ],
        'actor' => [
            'type' => $actor_type,
        ],
        'message' => [
            'id' => $message_id,
            'text' => $message_text,
        ],
        'ai' => [
            'provider' => $provider,
            'model' => $model,
        ],
    ];

    if ($user_id > 0) {
        $payload['actor']['user_id'] = $user_id;
    }

    if (!empty($user_log_result['is_new_session'])) {
        AIPKit_Event_Webhooks::emit(
            'chatbot.session_started',
            $payload,
            [
                'module' => 'chatbot',
                'origin' => 'frontend_session_started',
                'resource' => [
                    'type' => 'conversation',
                    'id' => $conversation_uuid,
                    'label' => $bot_name !== ''
                        ? sprintf(
                            /* translators: %s: chatbot name */
                            __('Conversation started for %s', 'gpt3-ai-content-generator'),
                            $bot_name
                        )
                        : __('Chat session started', 'gpt3-ai-content-generator'),
                ],
                'meta' => [
                    'bot_id' => $bot_id,
                    'conversation_uuid' => $conversation_uuid,
                    'message_id' => $message_id,
                    'message_count' => $message_count,
                    'is_guest' => $user_id > 0 ? 0 : 1,
                ],
                'idempotency_key' => sha1(implode('|', [
                    'chatbot.session_started',
                    (string) $bot_id,
                    $conversation_uuid,
                    $message_id,
                    $user_id > 0 ? (string) $user_id : 'guest',
                ])),
            ]
        );
    }

    AIPKit_Event_Webhooks::emit(
        'chatbot.user_message_submitted',
        $payload,
        [
            'module' => 'chatbot',
            'origin' => 'frontend_user_message',
            'resource' => [
                'type' => 'conversation_message',
                'id' => $message_id,
                'label' => $bot_name !== ''
                    ? sprintf(
                        /* translators: %s: chatbot name */
                        __('User message to %s', 'gpt3-ai-content-generator'),
                        $bot_name
                    )
                    : __('Chat user message', 'gpt3-ai-content-generator'),
            ],
            'meta' => [
                'bot_id' => $bot_id,
                'conversation_uuid' => $conversation_uuid,
                'message_id' => $message_id,
                'message_count' => $message_count,
                'is_guest' => $user_id > 0 ? 0 : 1,
            ],
            'idempotency_key' => sha1(implode('|', [
                'chatbot.user_message_submitted',
                (string) $bot_id,
                $conversation_uuid,
                $message_id,
                $user_id > 0 ? (string) $user_id : 'guest',
            ])),
        ]
    );
}

// --- trigger-session-start.php ---
// Dependencies like Trigger_Storage, Trigger_Manager, process_chat_triggers
// are checked for existence within this logic file before use.

/**
 * Handles the 'session_started' trigger event processing.
 *
 * @param array $context An associative array containing all necessary parameters:
 *    'user_id', 'session_id', 'bot_id', 'bot_settings', 'client_ip', 'post_id',
 *    'user_message_text' (first user message), 'system_instruction_for_ai' (current system instruction),
 *    'user_wp_role', 'current_provider', 'current_model_id', 'base_log_data', 'log_storage'.
 * @return array|WP_Error The result of trigger processing, which could be a WP_Error
 *                        if the trigger blocks or sends a direct reply, or an array
 *                        with potentially modified context data.
 *                        Structure of array: ['status', 'message_to_user', 'message_id', 'modified_context_data', 'stop_ai_processing', 'display_form_event_data']
 */
function process_session_start_trigger_logic(array $context)
{
    $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
    $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
    $trigger_function_name = '\WPAICG\Lib\Chat\Triggers\process_chat_triggers';
    $triggers_enabled = false;

    if (class_exists('\WPAICG\aipkit_dashboard')) {
        $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
    }

    $neutral_result = [
        'status' => 'processed',
        'message_to_user' => null,
        'message_id' => null,
        'modified_context_data' => [
            'system_instruction' => $context['system_instruction_for_ai'],
            'user_message_text' => $context['user_message_text'],
            'current_history' => [], // History is empty for session_started
        ],
        'stop_ai_processing' => false,
        'display_form_event_data' => null,
    ];

    if (!$triggers_enabled || !class_exists($trigger_storage_class) || !class_exists($trigger_manager_class) || !function_exists($trigger_function_name)) {
        return $neutral_result; // No triggers to run or components missing
    }

    $session_trigger_context_data = [
        'event_type'        => 'session_started',
        'user_id'           => $context['user_id'],
        'session_id'        => $context['session_id'],
        'bot_id'            => $context['bot_id'],
        'bot_settings'      => $context['bot_settings'],
        'client_ip'         => $context['client_ip'],
        'post_id'           => $context['post_id'],
        'user_message_text' => $context['user_message_text'], // First user message
        'current_history'   => [], // History is empty for session_started event itself
        'message_count'     => 0,
        'system_instruction' => $context['system_instruction_for_ai'],
        'user_roles'        => $context['user_wp_role'] ? array_map('trim', explode(',', $context['user_wp_role'])) : ['guest'],
        'current_provider'  => $context['current_provider'],
        'current_model_id'  => $context['current_model_id'],
        'base_log_data'     => $context['base_log_data'],
        'log_storage'       => $context['log_storage']
    ];

    // Call the global trigger processing function
    $session_trigger_result = $trigger_function_name($session_trigger_context_data);

    // If trigger blocks or sends a direct reply, return that result as WP_Error
    if ($session_trigger_result['status'] === 'blocked') {
        $block_message_id = $session_trigger_result['message_id'] ?? ('trigger-session-block-' . uniqid());
        return new WP_Error('trigger_blocked', $session_trigger_result['message_to_user'] ?? __('Session blocked by system.', 'gpt3-ai-content-generator'), ['status' => 400, 'is_trigger_response' => true, 'message_id' => $block_message_id]);
    }
    if (isset($session_trigger_result['display_form_event_data']) && is_array($session_trigger_result['display_form_event_data'])) {
        return new WP_Error('trigger_display_form', __('Form display requested by trigger.', 'gpt3-ai-content-generator'), ['status' => 200, 'display_form_event_data' => $session_trigger_result['display_form_event_data']]);
    }
    if (isset($session_trigger_result['message_to_user']) && ($session_trigger_result['stop_ai_processing'] ?? false)) {
        $trigger_reply_message_id_session = $session_trigger_result['message_id'] ?? ('trigger-session-reply-' . uniqid());
        return new WP_Error('trigger_direct_reply', $session_trigger_result['message_to_user'], ['status' => 200, 'is_trigger_response' => true, 'message_id' => $trigger_reply_message_id_session]);
    }

    // Return the full result from trigger processing, including modified context
    return $session_trigger_result;
}

// --- trigger-user-message.php ---
/**
 * Processes 'user_message_received' triggers.
 *
 * @param array $context Context data including bot_id, bot_settings, user_id, session_id, etc.
 *                       Also includes `user_message_text`, `final_history_for_ai`,
 *                       and `system_instruction_for_ai` which may have been modified
 *                       by `session_started` triggers.
 * @return array|WP_Error Result of trigger processing or WP_Error if blocked/direct reply.
 */
function process_user_message_trigger_logic(array $context)
{
    $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
    $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
    $trigger_function_name = '\WPAICG\Lib\Chat\Triggers\process_chat_triggers'; // This function is in lib/chat/triggers/trigger_handler.php
    $triggers_enabled = false;

    if (class_exists('\WPAICG\aipkit_dashboard')) {
        $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
    }

    // Prepare a neutral result that reflects the input context if no triggers modify it.
    $neutral_result = [
        'status' => 'processed',
        'message_to_user' => null,
        'message_id' => null,
        'modified_context_data' => [
            // These are the keys expected by the AI request builder later
            'system_instruction' => $context['system_instruction_for_ai'],
            'user_message_text'  => $context['user_message_text'],
            'current_history'    => $context['final_history_for_ai'], // Use final_history_for_ai from input context
        ],
        'stop_ai_processing' => false,
        'display_form_event_data' => null,
    ];

    if (!$triggers_enabled || !class_exists($trigger_storage_class) || !class_exists($trigger_manager_class) || !function_exists($trigger_function_name)) {
        return $neutral_result; // No triggers to run or components missing
    }

    // Data to pass to the global trigger processor `process_chat_triggers`
    // This uses specific keys that `process_chat_triggers` expects for 'user_message_received'
    $message_count_for_trigger = count($context['final_history_for_ai']);
    $user_message_trigger_context_data = [
        'event_type'        => 'user_message_received',
        'user_id'           => $context['user_id'],
        'session_id'        => $context['session_id'],
        'bot_id'            => $context['bot_id'],
        'bot_settings'      => $context['bot_settings'],
        'user_message_text' => $context['user_message_text'],
        'current_history'   => $context['final_history_for_ai'], // Use final_history_for_ai
        'client_ip'         => $context['client_ip'],
        'post_id'           => $context['post_id'],
        'message_count'     => $message_count_for_trigger, // Calculated count
        'system_instruction'=> $context['system_instruction_for_ai'],
        'user_roles'        => $context['user_wp_role'] ? array_map('trim', explode(',', $context['user_wp_role'])) : ['guest'],
        'current_provider'  => $context['current_provider'],
        'current_model_id'  => $context['current_model_id'],
        'base_log_data'     => $context['base_log_data'],
        'log_storage'       => $context['log_storage']
    ];

    // $trigger_function_name is \WPAICG\Lib\Chat\Triggers\process_chat_triggers
    $user_message_trigger_result = $trigger_function_name($user_message_trigger_context_data);

    // Handle blocking actions or direct replies from triggers
    if ($user_message_trigger_result['status'] === 'blocked') {
        $block_message_id = $user_message_trigger_result['message_id'] ?? ('trigger-block-' . uniqid());
        return new WP_Error('trigger_blocked', $user_message_trigger_result['message_to_user'] ?? __('Message blocked.', 'gpt3-ai-content-generator'), ['status' => 400, 'is_trigger_response' => true, 'message_id' => $block_message_id]);
    }
    if (isset($user_message_trigger_result['display_form_event_data']) && is_array($user_message_trigger_result['display_form_event_data'])) {
        return new WP_Error('trigger_display_form', __('Form display requested by trigger.', 'gpt3-ai-content-generator'), ['status' => 200, 'display_form_event_data' => $user_message_trigger_result['display_form_event_data']]);
    }
    if (isset($user_message_trigger_result['message_to_user']) && ($user_message_trigger_result['stop_ai_processing'] ?? false)) {
        $trigger_reply_message_id_user = $user_message_trigger_result['message_id'] ?? ('trigger-reply-' . uniqid());
        return new WP_Error('trigger_direct_reply', $user_message_trigger_result['message_to_user'], ['status' => 200, 'is_trigger_response' => true, 'message_id' => $trigger_reply_message_id_user]);
    }

    // If not blocked or direct replied, update the neutral_result with the outcome
    $neutral_result['status'] = $user_message_trigger_result['status']; // Should still be 'processed'
    $neutral_result['message_to_user'] = $user_message_trigger_result['message_to_user'] ?? $neutral_result['message_to_user'];
    $neutral_result['message_id'] = $user_message_trigger_result['message_id'] ?? $neutral_result['message_id'];
    $neutral_result['stop_ai_processing'] = $user_message_trigger_result['stop_ai_processing'] ?? $neutral_result['stop_ai_processing'];
    $neutral_result['display_form_event_data'] = $user_message_trigger_result['display_form_event_data'] ?? $neutral_result['display_form_event_data'];

    // Apply modifications from triggers to the context that will be passed to the AI
    // The keys in $user_message_trigger_result['modified_context_data'] are 'system_instruction', 'user_message_text', 'current_history'
    $neutral_result['modified_context_data']['system_instruction'] = $user_message_trigger_result['modified_context_data']['system_instruction'] ?? $context['system_instruction_for_ai'];
    $neutral_result['modified_context_data']['user_message_text']  = $user_message_trigger_result['modified_context_data']['user_message_text'] ?? $context['user_message_text'];
    $neutral_result['modified_context_data']['current_history']    = $user_message_trigger_result['modified_context_data']['current_history'] ?? $context['final_history_for_ai'];

    return $neutral_result;
}

// --- build-ai-request-data-for-stream.php ---


/**
 * Builds all necessary data for the AI stream request.
 *
 * @param AIPKit_AI_Caller $ai_caller Instance of AI Caller.
 * @param AIPKit_Vector_Store_Manager $vector_store_manager Instance of Vector Store Manager.
 * @param string $final_user_message_for_ai The user's message after trigger processing.
 * @param array $bot_settings Bot settings.
 * @param string $main_provider_for_ai The main AI provider for the chat.
 * @param string $model_id_for_ai The selected AI model.
 * @param array $final_history_for_ai Conversation history after trigger processing.
 * @param string $system_instruction_after_triggers System instruction after trigger processing.
 * @param int $post_id Current post ID.
 * @param array|null $image_inputs Processed image inputs.
 * @param string|null $frontend_previous_openai_response_id Previous OpenAI response ID.
 * @param bool $frontend_openai_web_search_active Flag for OpenAI web search.
 * @param bool $frontend_google_search_grounding_active Flag for Google Search Grounding.
 * @param string|null $frontend_active_openai_vs_id Active OpenAI Vector Store ID.
 * @param string|null $frontend_active_pinecone_index_name Active Pinecone index name.
 * @param string|null $frontend_active_pinecone_namespace Active Pinecone namespace.
 * @param string|null $frontend_active_qdrant_collection_name Active Qdrant collection name.
 * @param string|null $frontend_active_qdrant_file_upload_context_id Active Qdrant file context ID.
 * @param string|null $frontend_active_chroma_collection_name Active Chroma collection name.
 * @param string|null $frontend_active_chroma_file_upload_context_id Active Chroma file context ID.
 * @param string|null $frontend_active_claude_file_id Active Claude file ID.
 * @return array|WP_Error Prepared data array or WP_Error.
 */
function build_ai_request_data_for_stream_logic(
    AIPKit_AI_Caller $ai_caller,
    AIPKit_Vector_Store_Manager $vector_store_manager,
    string $final_user_message_for_ai,
    array $bot_settings,
    string $main_provider_for_ai,
    string $model_id_for_ai,
    array $final_history_for_ai,
    string $system_instruction_after_triggers,
    int $post_id,
    ?array $image_inputs,
    ?string $frontend_previous_openai_response_id,
    bool $frontend_openai_web_search_active,
    bool $frontend_google_search_grounding_active,
    ?string $frontend_active_openai_vs_id,
    ?string $frontend_active_pinecone_index_name,
    ?string $frontend_active_pinecone_namespace,
    ?string $frontend_active_qdrant_collection_name,
    ?string $frontend_active_qdrant_file_upload_context_id,
    ?string $frontend_active_chroma_collection_name,
    ?string $frontend_active_chroma_file_upload_context_id,
    ?string $frontend_active_claude_file_id
) {

    // Ensure dependencies for sub-logics are loaded
    if (!class_exists(AIPKit_Instruction_Manager::class)) {
        return new WP_Error('dependency_missing_instr_mgr', 'Instruction Manager component is missing.');
    }
    if (!class_exists(AIPKit_Providers::class) || !class_exists(AIPKIT_AI_Settings::class)) {
        return new WP_Error('dependency_missing_global_settings', 'Global settings components (Providers/AI_Settings) are missing.');
    }
    if ($main_provider_for_ai === 'Google' && !class_exists(GoogleSettingsHandler::class)) {
        return new WP_Error('dependency_missing_google_handler', 'Google Settings Handler component is missing.');
    }
    if ($main_provider_for_ai === 'OpenAI' && !class_exists(OpenAIStatefulConversationHelper::class)) {
        return new WP_Error('dependency_missing_openai_helper', 'OpenAI Stateful Helper component is missing.');
    }

    if (!empty($image_inputs)) {
        /**
         * Allow provider-specific image capability validation for chat stream requests.
         *
         * Return a WP_Error to block request when selected provider/model
         * cannot accept image inputs.
         *
         * @param mixed $validation_error Existing validation error (null by default).
         * @param array $image_inputs Normalized image input payload.
         * @param string $provider Selected provider.
         * @param string $model Selected model.
         * @param array $bot_settings Bot settings.
         * @param string $flow Request flow identifier.
         */
        $image_validation_error = apply_filters(
            'aipkit_chat_image_input_validation_error',
            null,
            $image_inputs,
            $main_provider_for_ai,
            $model_id_for_ai,
            $bot_settings,
            'stream'
        );
        if (is_wp_error($image_validation_error)) {
            return $image_validation_error;
        }
    }

    $all_formatted_results_for_instruction = "";
    $vector_search_scores = []; // Initialize array to capture vector search scores
    if (function_exists('\WPAICG\Core\Stream\Vector\build_vector_search_context_logic')) {
        $all_formatted_results_for_instruction = \WPAICG\Core\Stream\Vector\build_vector_search_context_logic(
            $ai_caller,
            $vector_store_manager,
            $final_user_message_for_ai,
            $bot_settings,
            $main_provider_for_ai,
            $frontend_active_openai_vs_id,
            $frontend_active_pinecone_index_name,
            $frontend_active_pinecone_namespace,
            $frontend_active_qdrant_collection_name,
            $frontend_active_qdrant_file_upload_context_id,
            $frontend_active_chroma_collection_name,
            $frontend_active_chroma_file_upload_context_id,
            $vector_search_scores // Pass reference to capture scores
        );
    }

    $instruction_context = [
        'base_instructions' => $system_instruction_after_triggers,
        'bot_settings' => $bot_settings,
        'post_id' => $post_id
    ];
    if (!empty($all_formatted_results_for_instruction)) {
        $instruction_context['vector_search_results'] = trim($all_formatted_results_for_instruction);
    }
    $instructions_built = AIPKit_Instruction_Manager::build_instructions($instruction_context);
    $instructions_filtered_for_api = apply_filters('aipkit_system_instruction', $instructions_built, $main_provider_for_ai, $model_id_for_ai, $final_user_message_for_ai, $final_history_for_ai, $bot_settings, $bot_settings['session_id'] ?? null);

    $global_ai_params = AIPKIT_AI_Settings::get_ai_parameters();
    $ai_params_for_payload = [
        'temperature' => isset($bot_settings['temperature']) ? floatval($bot_settings['temperature']) : floatval($global_ai_params['temperature'] ?? 1.0),
        'max_completion_tokens' => isset($bot_settings['max_completion_tokens']) ? absint($bot_settings['max_completion_tokens']) : absint($global_ai_params['max_completion_tokens'] ?? 4000),
    ];
    $global_only_keys = ['top_p', 'stop'];
    foreach ($global_only_keys as $k) {
        if (isset($global_ai_params[$k])) {
            $ai_params_for_payload[$k] = $global_ai_params[$k];
        }
    }
    if (!empty($image_inputs)) {
        $ai_params_for_payload['image_inputs'] = $image_inputs;
    }

    // Make a mutable copy of history for potential modification by stateful helper
    $history_for_stateful_check = $final_history_for_ai;

    if ($main_provider_for_ai === 'Google' && class_exists(GoogleSettingsHandler::class)) {
        $ai_params_for_payload['safety_settings'] = GoogleSettingsHandler::get_safety_settings();
    }
    if ($main_provider_for_ai === 'OpenAI' && class_exists(OpenAIStatefulConversationHelper::class)) {
        $stateful_result = OpenAIStatefulConversationHelper::prepare_parameters_and_history(
            $ai_params_for_payload, // Passed by value, modified array returned
            $history_for_stateful_check, // Passed by value, modified array returned
            $bot_settings,
            $frontend_previous_openai_response_id
        );
        $ai_params_for_payload = $stateful_result['ai_params'];
        $history_for_stateful_check = $stateful_result['history']; // Update history if stateful logic modified it
    }

    if ($main_provider_for_ai === 'OpenAI') {
        $vector_store_ids_to_use = $bot_settings['openai_vector_store_ids'] ?? [];
        if ($frontend_active_openai_vs_id && !in_array($frontend_active_openai_vs_id, $vector_store_ids_to_use, true)) {
            $vector_store_ids_to_use[] = $frontend_active_openai_vs_id;
        }
        $vector_store_ids_to_use = array_unique(array_filter($vector_store_ids_to_use));
        $vector_top_k_openai = absint($bot_settings['vector_store_top_k'] ?? 3);
        $vector_top_k_openai = max(1, min($vector_top_k_openai, 20));
        if (($bot_settings['enable_vector_store'] ?? '0') === '1' && ($bot_settings['vector_store_provider'] ?? '') === 'openai' && !empty($vector_store_ids_to_use)) {
            // Get confidence threshold and convert to OpenAI score threshold
            $confidence_threshold_percent = (int)($bot_settings['vector_store_confidence_threshold'] ?? 20);
            $openai_score_threshold = round($confidence_threshold_percent / 100, 4); // Round to avoid precision issues
            
            $ai_params_for_payload['vector_store_tool_config'] = [
                'type' => 'file_search', 
                'vector_store_ids' => $vector_store_ids_to_use, 
                'max_num_results' => $vector_top_k_openai,
                'ranking_options' => [
                    'score_threshold' => $openai_score_threshold
                ]
            ];
        }
        if (($bot_settings['openai_web_search_enabled'] ?? '0') === '1') {
            $ai_params_for_payload['web_search_tool_config'] = ['enabled' => true, 'search_context_size' => $bot_settings['openai_web_search_context_size'] ?? BotSettingsManager::DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE];
            if (($bot_settings['openai_web_search_loc_type'] ?? 'none') === 'approximate') {
                $user_loc = array_filter(['country' => $bot_settings['openai_web_search_loc_country'] ?? null, 'city' => $bot_settings['openai_web_search_loc_city'] ?? null, 'region' => $bot_settings['openai_web_search_loc_region'] ?? null, 'timezone' => $bot_settings['openai_web_search_loc_timezone'] ?? null]);
                if (!empty($user_loc)) {
                    $ai_params_for_payload['web_search_tool_config']['user_location'] = $user_loc;
                }
            }
            $ai_params_for_payload['frontend_web_search_active'] = $frontend_openai_web_search_active;
        }
        $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
            (string) $model_id_for_ai,
            $bot_settings['reasoning_effort'] ?? ''
        );
        if ($reasoning_effort !== '') {
            $ai_params_for_payload['reasoning'] = ['effort' => $reasoning_effort];
        }
    } elseif ($main_provider_for_ai === 'Claude') {
        if (($bot_settings['claude_web_search_enabled'] ?? '0') === '1') {
            $web_search_config = [
                'enabled' => true,
                'type' => 'web_search_20250305',
            ];

            $claude_max_uses = isset($bot_settings['claude_web_search_max_uses'])
                ? absint($bot_settings['claude_web_search_max_uses'])
                : BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES;
            $claude_max_uses = max(1, min($claude_max_uses, 20));
            $web_search_config['max_uses'] = $claude_max_uses;

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

            $allowed_domains = $split_domains($bot_settings['claude_web_search_allowed_domains'] ?? '');
            $blocked_domains = $split_domains($bot_settings['claude_web_search_blocked_domains'] ?? '');
            if (!empty($allowed_domains)) {
                $web_search_config['allowed_domains'] = $allowed_domains;
            } elseif (!empty($blocked_domains)) {
                $web_search_config['blocked_domains'] = $blocked_domains;
            }

            if (($bot_settings['claude_web_search_loc_type'] ?? 'none') === 'approximate') {
                $claude_user_location = array_filter([
                    'country' => $bot_settings['claude_web_search_loc_country'] ?? null,
                    'city' => $bot_settings['claude_web_search_loc_city'] ?? null,
                    'region' => $bot_settings['claude_web_search_loc_region'] ?? null,
                    'timezone' => $bot_settings['claude_web_search_loc_timezone'] ?? null,
                ]);
                if (!empty($claude_user_location)) {
                    $claude_user_location['type'] = 'approximate';
                    $web_search_config['user_location'] = $claude_user_location;
                }
            }

            $cache_ttl = $bot_settings['claude_web_search_cache_ttl'] ?? BotSettingsManager::DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL;
            if (in_array($cache_ttl, ['5m', '1h'], true)) {
                $web_search_config['cache_control'] = [
                    'type' => 'ephemeral',
                    'ttl' => $cache_ttl,
                ];
            }

            $ai_params_for_payload['web_search_tool_config'] = $web_search_config;
            $ai_params_for_payload['frontend_web_search_active'] = $frontend_openai_web_search_active;
        }
        $vector_store_provider = sanitize_key((string) ($bot_settings['vector_store_provider'] ?? ''));
        $claude_file_id = sanitize_text_field((string) $frontend_active_claude_file_id);
        if (
            $vector_store_provider === 'claude_files' &&
            $claude_file_id !== '' &&
            preg_match('/^file_[a-zA-Z0-9_-]+$/', $claude_file_id)
        ) {
            $ai_params_for_payload['claude_file_ids'] = [$claude_file_id];
        }
    } elseif ($main_provider_for_ai === 'OpenRouter') {
        if (($bot_settings['openrouter_web_search_enabled'] ?? '0') === '1') {
            $web_search_config = ['enabled' => true];

            $openrouter_engine = isset($bot_settings['openrouter_web_search_engine'])
                ? sanitize_key((string) $bot_settings['openrouter_web_search_engine'])
                : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE;
            if (in_array($openrouter_engine, ['native', 'exa'], true)) {
                $web_search_config['engine'] = $openrouter_engine;
            }

            $openrouter_max_results = isset($bot_settings['openrouter_web_search_max_results'])
                ? absint($bot_settings['openrouter_web_search_max_results'])
                : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS;
            $web_search_config['max_results'] = max(1, min($openrouter_max_results, 10));

            $openrouter_search_prompt = isset($bot_settings['openrouter_web_search_search_prompt'])
                ? AIPKit_Prompt_Sanitizer::sanitize($bot_settings['openrouter_web_search_search_prompt'])
                : BotSettingsManager::DEFAULT_OPENROUTER_WEB_SEARCH_SEARCH_PROMPT;
            if ($openrouter_search_prompt !== '') {
                $web_search_config['search_prompt'] = $openrouter_search_prompt;
            }

            $ai_params_for_payload['web_search_tool_config'] = $web_search_config;
            $ai_params_for_payload['frontend_web_search_active'] = $frontend_openai_web_search_active;
        }
    } elseif ($main_provider_for_ai === 'xAI') {
        if (($bot_settings['xai_web_search_enabled'] ?? '0') === '1') {
            $ai_params_for_payload['xai_web_search_tool_config'] = ['enabled' => true];
            $ai_params_for_payload['frontend_web_search_active'] = $frontend_openai_web_search_active;
        }
    } elseif ($main_provider_for_ai === 'Google') {
        if (($bot_settings['google_search_grounding_enabled'] ?? '0') === '1') {
            $ai_params_for_payload['google_grounding_mode'] = $bot_settings['google_grounding_mode'] ?? BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_MODE;
            if ($ai_params_for_payload['google_grounding_mode'] === 'MODE_DYNAMIC') {
                $ai_params_for_payload['google_grounding_dynamic_threshold'] = isset($bot_settings['google_grounding_dynamic_threshold']) ? floatval($bot_settings['google_grounding_dynamic_threshold']) : BotSettingsManager::DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD;
            }
            $ai_params_for_payload['frontend_google_search_grounding_active'] = $frontend_google_search_grounding_active;
        }
        $ai_params_for_payload['model_id_for_grounding'] = $model_id_for_ai;
    } elseif ($main_provider_for_ai === 'Ollama') {
        $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($bot_settings['reasoning_effort'] ?? '');
        if ($reasoning_effort !== '' && $reasoning_effort !== 'none') {
            $ai_params_for_payload['reasoning'] = ['effort' => $reasoning_effort];
        }
    }

    $provData = AIPKit_Providers::get_provider_data($main_provider_for_ai);
    $api_params_for_stream = [
        'api_key' => $provData['api_key'] ?? '', 'base_url' => $provData['base_url'] ?? '', 'api_version' => $provData['api_version'] ?? '',
        'azure_endpoint' => ($main_provider_for_ai === 'Azure') ? ($provData['endpoint'] ?? '') : '',
        'azure_inference_version' => ($main_provider_for_ai === 'Azure') ? ($provData['api_version_inference'] ?? '2025-01-01-preview') : '',
        'azure_authoring_version' => ($main_provider_for_ai === 'Azure') ? ($provData['api_version_authoring'] ?? '2023-03-15-preview') : '',
    ];
    // Ollama doesn't require an API key, so skip validation for it
    if ($main_provider_for_ai !== 'Ollama' && empty($api_params_for_stream['api_key'])) {
        /* translators: %s: The name of the AI provider (e.g., OpenAI, Google). */
        return new WP_Error('missing_api_key', sprintf(__('API key missing for %s.', 'gpt3-ai-content-generator'), $main_provider_for_ai), ['status' => 400]);
    }
    if ($main_provider_for_ai === 'Azure' && empty($api_params_for_stream['azure_endpoint'])) {
        return new WP_Error('missing_azure_endpoint', __('Azure endpoint is missing.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // The history for the API call should include the current user message
    $final_history_for_api_call = $history_for_stateful_check;
    if (!empty($final_user_message_for_ai)) {
        $final_history_for_api_call[] = ['role' => 'user', 'content' => $final_user_message_for_ai];
    }

    return [
        'provider'                      => $main_provider_for_ai,
        'model'                         => $model_id_for_ai,
        'user_message'                  => $final_user_message_for_ai, // Still needed for some strategy formatters
        'history'                       => $final_history_for_api_call,
        'system_instruction_filtered'   => $instructions_filtered_for_api,
        'api_params'                    => $api_params_for_stream,
        'ai_params'                     => $ai_params_for_payload,
        'vector_search_scores'          => $vector_search_scores, // Include captured vector search scores
    ];
}

// --- construct-sse-processor-input.php ---
/**
 * Assembles the final array expected by SSEStreamProcessor::start_stream.
 *
 * @param array $request_data_for_ai Data prepared by build_ai_request_data_for_stream_logic.
 * @param string $conversation_uuid The UUID of the conversation.
 * @param array $base_log_data Base data for logging.
 * @param string $bot_message_id The generated ID for the bot's message.
 * @param array|null $initial_trigger_reply_data Optional data for an initial reply from triggers.
 * @return array The final input array for SSEStreamProcessor.
 */
function construct_sse_processor_input_logic(
    array $request_data_for_ai,
    string $conversation_uuid,
    array $base_log_data,
    string $bot_message_id,
    ?array $initial_trigger_reply_data
): array {
    $return_data = [
        'provider'                      => $request_data_for_ai['provider'],
        'model'                         => $request_data_for_ai['model'],
        'user_message'                  => $request_data_for_ai['user_message'],
        'history'                       => $request_data_for_ai['history'],
        'system_instruction_filtered'   => $request_data_for_ai['system_instruction_filtered'],
        'api_params'                    => $request_data_for_ai['api_params'],
        'ai_params'                     => $request_data_for_ai['ai_params'],
        'vector_search_scores'          => $request_data_for_ai['vector_search_scores'] ?? [], // Include vector search scores
        'conversation_uuid'             => $conversation_uuid,
        'base_log_data'                 => $base_log_data, // This already includes bot_message_id for the upcoming bot reply
        'bot_message_id'                => $bot_message_id, // Explicitly pass it as well
    ];
    
    if ($initial_trigger_reply_data) {
        $return_data['initial_trigger_reply_data'] = $initial_trigger_reply_data;
    }
    return $return_data;
}
