<?php

namespace WPAICG\Core\Stream\Handler\Ajax;

use WPAICG\Chat\Core\Validation\ChatImageInputValidator;
use WP_Error;
use WPAICG\Core\Stream\Handler\SSEHandler;
use WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache;
use WPAICG\Utils\AIPKit_CORS_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- fn-ajax-cache-sse-message.php ---


/**
* AJAX handler for caching the user message or context data before starting the SSE stream.
*
* @param \WPAICG\Core\Stream\Handler\SSEHandler $handlerInstance The instance of the SSEHandler class.
* @return void Sends JSON response.
*/
function ajax_cache_sse_message_logic(\WPAICG\Core\Stream\Handler\SSEHandler $handlerInstance): void
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Frontend nonce is verified below before processing request data.
    $post_data = wp_unslash($_POST);

    // --- Handle preflight OPTIONS request ---
    AIPKit_CORS_Manager::handle_preflight_request();

    // --- CORS Check ---
    $bot_id = 0;
    if (isset($post_data['bot_id'])) {
        $bot_id = absint($post_data['bot_id']);
    }

    if ($bot_id > 0) {
        // Check if this is a cross-origin request (actual embed usage)
        $is_cross_origin = false;
        if (isset($_SERVER['HTTP_ORIGIN']) && !empty($_SERVER['HTTP_ORIGIN'])) {
            $origin = esc_url_raw(wp_unslash((string) $_SERVER['HTTP_ORIGIN']));
            $site_url = get_site_url();
            $site_parsed = wp_parse_url($site_url);
            $origin_parsed = wp_parse_url($origin);

            // Check if origin is different from site domain
            if ($origin_parsed && $site_parsed && 
                ($origin_parsed['host'] !== $site_parsed['host'] || 
                 ($origin_parsed['scheme'] ?? 'http') !== ($site_parsed['scheme'] ?? 'http'))) {
                $is_cross_origin = true;
            }
        }

        if ($is_cross_origin) {
            // This is a cross-origin request, check embed feature availability
            if (class_exists('\WPAICG\aipkit_dashboard') && 
                \WPAICG\aipkit_dashboard::is_pro_plan()) {

                $origin_allowed = AIPKit_CORS_Manager::check_and_set_cors_headers($bot_id);
                if (!$origin_allowed) {
                    wp_send_json_error([
                        'message' => __('This domain is not permitted to access the chatbot.', 'gpt3-ai-content-generator'),
                        'code'    => 'cors_denied'
                    ], 403);
                    return;
                }
            } else {
                // Embed feature not available but this is a cross-origin request
                wp_send_json_error([
                    'message' => __('Embed feature is not available with your current plan.', 'gpt3-ai-content-generator'),
                    'code'    => 'embed_not_available'
                ], 403);
                return;
            }
        }
        // For same-origin requests, no additional CORS checks needed
    }

    // --- MODIFICATION: Improved Nonce Check and Error Reporting ---
    if (!isset($post_data['_ajax_nonce']) || !wp_verify_nonce(sanitize_key((string) $post_data['_ajax_nonce']), 'aipkit_frontend_chat_nonce')) {
        $error_data_for_response = [
            'message' => __('Your session has expired or the request is invalid. Please refresh the page and try again.', 'gpt3-ai-content-generator'),
            'code'    => 'nonce_failure_cache_sse' // Specific code for nonce failure
        ];
        wp_send_json_error($error_data_for_response, 403);
        return;
    }

    $raw_user_message = isset($post_data['message']) ? (string) $post_data['message'] : '';
    
    // Custom sanitization for code content - preserve code structure while ensuring security
    $user_message = wp_check_invalid_utf8($raw_user_message);
    $user_message = str_replace(chr(0), '', $user_message); // Remove null bytes
    
    $image_inputs_json = isset($post_data['image_inputs']) ? (string) $post_data['image_inputs'] : null;
    $client_user_message_id = isset($post_data['user_client_message_id']) ? sanitize_key((string) $post_data['user_client_message_id']) : null;
    $active_openai_vs_id = isset($post_data['active_openai_vs_id']) ? sanitize_text_field((string) $post_data['active_openai_vs_id']) : null;
    $active_pinecone_index_name = isset($post_data['active_pinecone_index_name']) ? sanitize_text_field((string) $post_data['active_pinecone_index_name']) : null;
    $active_pinecone_namespace = isset($post_data['active_pinecone_namespace']) ? sanitize_text_field((string) $post_data['active_pinecone_namespace']) : null;
    $active_qdrant_collection_name = isset($post_data['active_qdrant_collection_name']) ? sanitize_text_field((string) $post_data['active_qdrant_collection_name']) : null;
    $active_qdrant_file_upload_context_id = isset($post_data['active_qdrant_file_upload_context_id']) ? sanitize_text_field((string) $post_data['active_qdrant_file_upload_context_id']) : null;
    $active_chroma_collection_name = isset($post_data['active_chroma_collection_name']) ? sanitize_text_field((string) $post_data['active_chroma_collection_name']) : null;
    $active_chroma_file_upload_context_id = isset($post_data['active_chroma_file_upload_context_id']) ? sanitize_text_field((string) $post_data['active_chroma_file_upload_context_id']) : null;
    $active_claude_file_id = isset($post_data['active_claude_file_id']) ? sanitize_text_field((string) $post_data['active_claude_file_id']) : null;
    $resume_after_form_submission = isset($post_data['resume_after_form_submission']) && (string) $post_data['resume_after_form_submission'] === '1';
    $form_resume_token = isset($post_data['form_resume_token']) ? sanitize_text_field((string) $post_data['form_resume_token']) : '';
    $form_submission_context = [];

    if ($resume_after_form_submission && isset($post_data['form_submission_context']) && is_string($post_data['form_submission_context'])) {
        $form_submission_context_raw = wp_kses_post($post_data['form_submission_context']);
        $decoded_form_submission_context = json_decode($form_submission_context_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_form_submission_context)) {
            $sanitize_recursive = static function ($value) use (&$sanitize_recursive) {
                if (is_array($value)) {
                    $sanitized = [];
                    foreach ($value as $key => $item) {
                        $sanitized_key = sanitize_text_field((string) $key);
                        if ($sanitized_key === '') {
                            continue;
                        }
                        $sanitized[$sanitized_key] = $sanitize_recursive($item);
                    }
                    return $sanitized;
                }
                return sanitize_text_field((string) $value);
            };
            $form_submission_context = $sanitize_recursive($decoded_form_submission_context);
        }
    }

    if (!class_exists(ChatImageInputValidator::class)) {
        $validator_path = WPAICG_PLUGIN_DIR . 'classes/chat/core/validation/class-chat-image-input-validator.php';
        if (file_exists($validator_path)) {
            require_once $validator_path;
        }
    }

    $image_inputs_data = null;
    if ($image_inputs_json) {
        if (!class_exists(ChatImageInputValidator::class)) {
            $error_data_for_response = [
                'message' => __('Image validation component is unavailable.', 'gpt3-ai-content-generator'),
                'code' => 'image_validator_missing',
            ];
            wp_send_json_error($error_data_for_response, 500);
            return;
        }

        $image_validation_result = ChatImageInputValidator::parse_and_validate($image_inputs_json);
        if (is_wp_error($image_validation_result)) {
            $error_data_for_response = [
                'message' => $image_validation_result->get_error_message(),
                'code' => $image_validation_result->get_error_code(),
            ];
            $error_status = is_array($image_validation_result->get_error_data()) && isset($image_validation_result->get_error_data()['status'])
                ? absint($image_validation_result->get_error_data()['status'])
                : 400;
            wp_send_json_error($error_data_for_response, $error_status);
            return;
        }

        if (is_array($image_validation_result)) {
            $image_inputs_data = $image_validation_result;
        }
    }

    if (empty($user_message) && empty($image_inputs_data)) {
        $error_data_for_response = [
            'message' => __('Data to cache (message or image) cannot be empty.', 'gpt3-ai-content-generator'),
            'code' => 'empty_data_to_cache'
        ];
        wp_send_json_error($error_data_for_response, 400);
        return;
    }

    $data_to_cache_structured = [
        'user_message' => $user_message,
        'image_inputs' => $image_inputs_data,
        'client_user_message_id' => $client_user_message_id
    ];
    if ($resume_after_form_submission) {
        $data_to_cache_structured['resume_after_form_submission'] = true;
        if (!empty($form_submission_context)) {
            $data_to_cache_structured['form_submission_context'] = $form_submission_context;
        }
        if ($form_resume_token !== '') {
            $data_to_cache_structured['form_resume_token'] = $form_resume_token;
        }
    }
    if ($active_openai_vs_id) {
        $data_to_cache_structured['active_openai_vs_id'] = $active_openai_vs_id;
    }
    if ($active_pinecone_index_name) {
        $data_to_cache_structured['active_pinecone_index_name'] = $active_pinecone_index_name;
    }
    if ($active_pinecone_namespace) {
        $data_to_cache_structured['active_pinecone_namespace'] = $active_pinecone_namespace;
    }
    if ($active_qdrant_collection_name) {
        $data_to_cache_structured['active_qdrant_collection_name'] = $active_qdrant_collection_name;
    }
    if ($active_qdrant_file_upload_context_id) {
        $data_to_cache_structured['active_qdrant_file_upload_context_id'] = $active_qdrant_file_upload_context_id;
    }
    if ($active_chroma_collection_name) {
        $data_to_cache_structured['active_chroma_collection_name'] = $active_chroma_collection_name;
    }
    if ($active_chroma_file_upload_context_id) {
        $data_to_cache_structured['active_chroma_file_upload_context_id'] = $active_chroma_file_upload_context_id;
    }
    if ($active_claude_file_id) {
        $data_to_cache_structured['active_claude_file_id'] = $active_claude_file_id;
    }
    
    $data_to_cache = wp_json_encode($data_to_cache_structured);

    if ($data_to_cache === false) {
        $error_data_for_response = [
            'message' => __('Failed to encode data for caching. The message may contain invalid characters.', 'gpt3-ai-content-generator'),
            'code' => 'json_encode_failed'
        ];
        wp_send_json_error($error_data_for_response, 400);
        return;
    }


    if (!class_exists(AIPKit_SSE_Message_Cache::class)) {
        $cache_path = WPAICG_PLUGIN_DIR . 'classes/core/stream/cache/class-sse-message-cache.php';
        if (file_exists($cache_path)) {
            require_once $cache_path;
        } else {
            $error_data_for_response = [
                'message' => __('Cache component missing.', 'gpt3-ai-content-generator'),
                'code' => 'cache_component_missing'
            ];
            wp_send_json_error($error_data_for_response, 500);
            return;
        }
    }
    $sse_message_cache = new AIPKit_SSE_Message_Cache();
    
    $cache_key_result = $sse_message_cache->set($data_to_cache);

    if (is_wp_error($cache_key_result)) {
        $error_data_for_response = [
            'message' => $cache_key_result->get_error_message(),
            'code'    => $cache_key_result->get_error_code()
        ];
        $error_status = $cache_key_result->get_error_data()['status'] ?? 500;
        wp_send_json_error($error_data_for_response, $error_status);
    } else {
        wp_send_json_success(['cache_key' => $cache_key_result]);
    }
}

// --- fn-ajax-frontend-chat-stream.php ---

/**
 * AJAX handler for processing STREAMING messages using Server-Sent Events (SSE).
 *
 * @param \WPAICG\Core\Stream\Handler\SSEHandler $handlerInstance The instance of the SSEHandler class.
 * @return void
 */
function ajax_frontend_chat_stream_logic(SSEHandler $handlerInstance): void {
    $response_formatter = $handlerInstance->get_response_formatter();
    $request_handler    = $handlerInstance->get_request_handler();
    $stream_processor   = $handlerInstance->get_stream_processor();

    // --- Handle preflight OPTIONS request ---
    AIPKit_CORS_Manager::handle_preflight_request();

    // --- CORS Check ---
    $bot_id = 0;
    if (isset($_GET['bot_id'])) {
        $bot_id = absint(wp_unslash($_GET['bot_id']));
    }

    if ($bot_id > 0) {
        $origin_allowed = AIPKit_CORS_Manager::check_and_set_cors_headers($bot_id);
        if (!$origin_allowed) {
            $response_formatter->set_sse_headers();
            $response_formatter->send_sse_error(__('This domain is not permitted to access the chatbot.', 'gpt3-ai-content-generator'));
            $response_formatter->send_sse_done();
            exit;
        }
    }

    $response_formatter->set_sse_headers();

    // --- FIX for PHPCS NonceVerification ---
    // Perform nonce check at the top of the AJAX handler before using any user input.
    if (!check_ajax_referer('aipkit_frontend_chat_nonce', '_ajax_nonce', false)) {
        $response_formatter->send_sse_error(__('Security check failed. Please refresh the page and try again.', 'gpt3-ai-content-generator'));
        $response_formatter->send_sse_done();
        exit;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified above. All $_GET usage is now considered safe.
    $get_data = wp_unslash($_GET);
    // --- END FIX ---


    $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
    $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
    $triggers_enabled = false;
    if (class_exists('\WPAICG\aipkit_dashboard')) {
        $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
    }

    if (!$request_handler || !$stream_processor) {
        $error_message = __('Server error: Stream processing components not ready.', 'gpt3-ai-content-generator');
        $response_formatter->send_sse_error($error_message);
        if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
             $bot_id = isset($get_data['bot_id']) ? absint($get_data['bot_id']) : null; 
             $log_storage_for_trigger_error = class_exists('\WPAICG\Chat\Storage\LogStorage') ? new \WPAICG\Chat\Storage\LogStorage() : null;
             if ($log_storage_for_trigger_error) {
                $error_event_context = [
                    'error_code'    => 'sse_component_not_ready', 'error_message' => $error_message,
                    'bot_id'        => $bot_id, 'user_id'       => get_current_user_id() ?: null, 
                    'session_id'    => isset($get_data['session_id']) ? sanitize_text_field($get_data['session_id']) : null,
                    'module'        => 'chat_stream_handler', 'operation'     => 'initialize_sse_components',
                ];
                $trigger_storage = new $trigger_storage_class();
                $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage_for_trigger_error);
                $trigger_manager->process_event($bot_id ?? 0, 'system_error_occurred', $error_event_context);
             }
        }
        $response_formatter->send_sse_done(); 
        exit;
    }

    try {
        $processed_data = $request_handler->process_initial_request($get_data);

        if (is_wp_error($processed_data)) {
            $error_code = $processed_data->get_error_code();
            $error_data = $processed_data->get_error_data() ?: []; 
            $status_code = is_array($error_data) && isset($error_data['status']) && is_int($error_data['status']) ? $error_data['status'] : 500;
            $user_facing_message = $processed_data->get_error_message();
            $client_error_payload = [];
            if (is_array($error_data) && !empty($error_data['quota_notice']) && is_array($error_data['quota_notice'])) {
                $client_error_payload['quota_notice'] = $error_data['quota_notice'];
            }

            if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
                $bot_id = isset($get_data['bot_id']) ? absint($get_data['bot_id']) : null; 
                $log_storage_for_trigger_error = class_exists('\WPAICG\Chat\Storage\LogStorage') ? new \WPAICG\Chat\Storage\LogStorage() : null;
                if ($log_storage_for_trigger_error) {
                    $error_event_context = [
                        'error_code'    => $error_code, 'error_message' => $user_facing_message,
                        'bot_id'        => $bot_id, 'user_id'       => get_current_user_id() ?: null,
                        'session_id'    => isset($get_data['session_id']) ? sanitize_text_field($get_data['session_id']) : null,
                        'module'        => $error_data['failed_module'] ?? 'chat_stream_handler', 
                        'operation'     => $error_data['failed_operation'] ?? 'process_initial_sse_request',
                        'failed_provider' => $error_data['failed_provider'] ?? null,
                        'failed_model'    => $error_data['failed_model'] ?? null,
                        'http_code'     => $status_code,
                    ];
                    $trigger_storage = new $trigger_storage_class();
                    $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage_for_trigger_error);
                    $trigger_manager->process_event($bot_id ?? 0, 'system_error_occurred', $error_event_context);
                }
            }

            if ($error_code === 'trigger_blocked') {
                $response_formatter->send_sse_error($user_facing_message, false); 
                $response_formatter->send_sse_done();
                exit;
            } elseif ($error_code === 'trigger_direct_reply') {
                $reply_bot_message_id = is_array($error_data) && isset($error_data['message_id']) ? $error_data['message_id'] : ('trigger-reply-' . uniqid());
                $response_formatter->send_sse_event('message_start', ['message_id' => $reply_bot_message_id]);
                $response_formatter->send_sse_data(['delta' => $user_facing_message]); 
                $response_formatter->send_sse_done();
                exit;
            } elseif ($error_code === 'trigger_display_form') {
                $form_event_data = $error_data['display_form_event_data'] ?? null;
                if ($form_event_data) {
                    $response_formatter->send_sse_event('display_form_event', $form_event_data);
                } else {
                    $response_formatter->send_sse_error(__('Form display requested but data is missing.', 'gpt3-ai-content-generator'), false);
                }
                $response_formatter->send_sse_done(); 
                exit; 
            } elseif (!empty($client_error_payload)) {
                $response_formatter->send_sse_error($user_facing_message, false, $client_error_payload);
                $response_formatter->send_sse_done();
                exit;
            } else {
                throw new \Exception($user_facing_message, $status_code);
            }
        }
        
        if (isset($processed_data['initial_trigger_reply_data']) && is_array($processed_data['initial_trigger_reply_data'])) {
            $initial_reply = $processed_data['initial_trigger_reply_data'];
            if (!empty($initial_reply['message_id']) && !empty($initial_reply['message'])) {
                $response_formatter->send_sse_event('message_start', ['message_id' => $initial_reply['message_id']]);
                $response_formatter->send_sse_data(['delta' => $initial_reply['message']]);
            }
        }

        if (empty($processed_data['bot_message_id'])) {
            throw new \Exception('Internal error: Missing generated message ID for stream.', 500);
        }

        $base_log_data_with_msg_id = $processed_data['base_log_data'] ?? [];
        if (empty($base_log_data_with_msg_id['bot_message_id'])) {
            $base_log_data_with_msg_id['bot_message_id'] = $processed_data['bot_message_id'];
        }

        $response_formatter->send_sse_event('message_start', ['message_id' => $processed_data['bot_message_id']]);

        // Set vector search scores in the stream processor for logging
        if (isset($processed_data['vector_search_scores']) && is_array($processed_data['vector_search_scores'])) {
            $stream_processor->set_vector_search_scores($processed_data['vector_search_scores']);
        }

        $stream_processor->start_stream(
            $processed_data['provider'], $processed_data['model'],
            $processed_data['user_message'], $processed_data['history'],
            $processed_data['system_instruction_filtered'],
            $processed_data['api_params'], $processed_data['ai_params'],
            $processed_data['conversation_uuid'], $base_log_data_with_msg_id
        );

    } catch (\Exception $e) {
        $error_code_http = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
        $error_message_final = $e->getMessage();
        if (!$response_formatter->get_headers_sent_status()) $response_formatter->set_sse_headers();
        $response_formatter->send_sse_error($error_message_final);
        
        if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
             $bot_id = isset($get_data['bot_id']) ? absint($get_data['bot_id']) : null; 
             $log_storage_for_trigger_error = class_exists('\WPAICG\Chat\Storage\LogStorage') ? new \WPAICG\Chat\Storage\LogStorage() : null;
             if ($log_storage_for_trigger_error) {
                $error_event_context = [
                   'error_code'    => 'sse_handler_exception_' . $error_code_http, 'error_message' => $error_message_final,
                   'bot_id'        => $bot_id, 'user_id'       => get_current_user_id() ?: null, 
                   'session_id'    => isset($get_data['session_id']) ? sanitize_text_field($get_data['session_id']) : null,
                   'module'        => 'chat_stream_handler', 'operation'     => 'process_stream_request',
                   'http_code'     => $error_code_http,
                ];
                $trigger_storage = new $trigger_storage_class();
                $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage_for_trigger_error);
                $trigger_manager->process_event($bot_id ?? 0, 'system_error_occurred', $error_event_context);
             }
        }
        
        $response_formatter->send_sse_done();
    } finally {
        if ($response_formatter) {
            $response_formatter->finish_request();
        }
        // Ensure script exits after sending all SSE data or handling errors
        exit;
    }
}
