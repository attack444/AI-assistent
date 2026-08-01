<?php

namespace WPAICG\Core\Stream\Request;

use WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache;
use WPAICG\Core\Stream\Contexts\Chat\SSEChatStreamContextHandler;
use WPAICG\Core\Stream\Contexts\ContentWriter\SSEContentWriterStreamContextHandler;
use WPAICG\Core\Stream\Contexts\AIForms\SSEAIFormsStreamContextHandler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- fn-process-initial-request.php ---
/**
 * Validates the incoming SSE request parameters and prepares data for streaming.
 * Retrieves cached data based on cache_key and routes to context-specific processors.
 *
 * @param \WPAICG\Core\Stream\Request\SSERequestHandler $handlerInstance The instance of the SSERequestHandler.
 * @param array $get_params $_GET parameters from the SSE request.
 * @return array|WP_Error Prepared data for SSEStreamProcessor or WP_Error.
 */
function process_initial_request_logic(
    \WPAICG\Core\Stream\Request\SSERequestHandler $handlerInstance,
    array $get_params
) {
    $cache_key = isset($get_params['cache_key']) ? sanitize_key($get_params['cache_key']) : '';
    if (empty($cache_key)) {
        return new WP_Error(
            'missing_cache_key',
            __('Message cache key is missing.', 'gpt3-ai-content-generator'),
            ['status' => 400, 'failed_module' => 'sse_handler', 'failed_operation' => 'cache_key_validation']
        );
    }

    $sse_message_cache = $handlerInstance->get_sse_message_cache();
    $cached_content_result = $sse_message_cache->get($cache_key);

    if (is_wp_error($cached_content_result)) {
        $error_data = $cached_content_result->get_error_data() ?: [];
        $error_data['failed_module'] = $error_data['failed_module'] ?? 'sse_cache';
        $error_data['failed_operation'] = $error_data['failed_operation'] ?? 'get_cached_message';
        $error_data['status_code'] = $error_data['status_code'] ?? ($cached_content_result->get_error_code() === 'sse_cache_expired' ? 410 : 404);
        return new WP_Error(
            $cached_content_result->get_error_code(),
            $cached_content_result->get_error_message(),
            $error_data
        );
    }
    $sse_message_cache->delete($cache_key);

    $stream_context = 'chat'; // Default context
    $cached_data_decoded_for_handler = null;

    if (is_string($cached_content_result)) {
        $outer_decoded = json_decode($cached_content_result, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($outer_decoded)) {
            if (isset($outer_decoded['stream_context'])) {
                // Top-level stream_context found (this means it's likely a direct cache from non-chat modules)
                $stream_context = $outer_decoded['stream_context'];
                $cached_data_decoded_for_handler = $outer_decoded;
            } elseif (isset($outer_decoded['user_message']) && is_string($outer_decoded['user_message'])) {
                // The 'user_message' field might itself be a JSON string containing the actual context data
                // This happens when AI Forms or Content Writer cache their data via the generic ajax_cache_sse_message action.
                $nested_decoded = json_decode($outer_decoded['user_message'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($nested_decoded) && isset($nested_decoded['stream_context'])) {
                    $stream_context = $nested_decoded['stream_context'];
                    $cached_data_decoded_for_handler = $nested_decoded; // Use the inner decoded data
                    // Merge top-level keys from outer_decoded if they are relevant for all contexts (e.g., active_openai_vs_id)
                    if (isset($outer_decoded['active_openai_vs_id'])) {
                        $cached_data_decoded_for_handler['active_openai_vs_id'] = $outer_decoded['active_openai_vs_id'];
                    }
                    if (isset($outer_decoded['active_pinecone_index_name'])) {
                        $cached_data_decoded_for_handler['active_pinecone_index_name'] = $outer_decoded['active_pinecone_index_name'];
                    }
                    if (isset($outer_decoded['active_pinecone_namespace'])) {
                        $cached_data_decoded_for_handler['active_pinecone_namespace'] = $outer_decoded['active_pinecone_namespace'];
                    }
                    if (isset($outer_decoded['active_qdrant_collection_name'])) {
                        $cached_data_decoded_for_handler['active_qdrant_collection_name'] = $outer_decoded['active_qdrant_collection_name'];
                    }
                    if (isset($outer_decoded['active_qdrant_file_upload_context_id'])) {
                        $cached_data_decoded_for_handler['active_qdrant_file_upload_context_id'] = $outer_decoded['active_qdrant_file_upload_context_id'];
                    }
                    if (isset($outer_decoded['active_chroma_collection_name'])) {
                        $cached_data_decoded_for_handler['active_chroma_collection_name'] = $outer_decoded['active_chroma_collection_name'];
                    }
                    if (isset($outer_decoded['active_chroma_file_upload_context_id'])) {
                        $cached_data_decoded_for_handler['active_chroma_file_upload_context_id'] = $outer_decoded['active_chroma_file_upload_context_id'];
                    }
                    if (isset($outer_decoded['active_claude_file_id'])) {
                        $cached_data_decoded_for_handler['active_claude_file_id'] = $outer_decoded['active_claude_file_id'];
                    }
                    if (isset($outer_decoded['client_user_message_id'])) {
                        $cached_data_decoded_for_handler['client_user_message_id'] = $outer_decoded['client_user_message_id'];
                    }
                    if (isset($outer_decoded['image_inputs'])) {
                        $cached_data_decoded_for_handler['image_inputs'] = $outer_decoded['image_inputs'];
                    }

                } else {
                    // 'user_message' was not valid JSON or didn't contain 'stream_context', treat as chat.
                    $cached_data_decoded_for_handler = $outer_decoded;
                }
            } else {
                // No 'stream_context' at top level, and 'user_message' is not a string to parse.
                // Treat as chat context using the outer decoded data.
                $cached_data_decoded_for_handler = $outer_decoded;
            }
        } else {
            // $cached_content_result was not a JSON string (e.g., older simple string cache for chat)
            $cached_data_decoded_for_handler = ['user_message' => $cached_content_result, 'image_inputs' => null];
        }
    } else {
        // Fallback if $cached_content_result was not a string (should not happen with current cache logic)
        $cached_data_decoded_for_handler = ['user_message' => '', 'image_inputs' => null];
    }

    // Route to specific context handlers
    if ($stream_context === 'chat') {
        $chat_handler = $handlerInstance->get_chat_context_handler();
        if (!$chat_handler) {
            return new WP_Error(
                'handler_missing_chat',
                'Chat stream handler component missing.',
                ['status' => 500, 'failed_module' => 'chat_stream_context', 'failed_operation' => 'get_context_handler']
            );
        }
        return $chat_handler->process($cached_data_decoded_for_handler, $get_params);
    } elseif ($stream_context === 'content_writer') {
        $content_writer_handler = $handlerInstance->get_content_writer_context_handler();
        if (!$content_writer_handler) {
            return new WP_Error(
                'handler_missing_cw',
                'Content writer stream handler component missing.',
                ['status' => 500, 'failed_module' => 'content_writer_stream_context', 'failed_operation' => 'get_context_handler']
            );
        }
        return $content_writer_handler->process($cached_data_decoded_for_handler, $get_params);
    } elseif ($stream_context === 'ai_forms') {
        $ai_forms_handler = $handlerInstance->get_ai_forms_context_handler();
        if (!$ai_forms_handler) {
            return new WP_Error(
                'handler_missing_aif',
                'AI Forms stream handler component missing.',
                ['status' => 500, 'failed_module' => 'ai_forms_stream_context', 'failed_operation' => 'get_context_handler']
            );
        }
        return $ai_forms_handler->process($cached_data_decoded_for_handler, $get_params);
    } else {
        return new WP_Error(
            'unsupported_stream_context',
            __('Unsupported stream context.', 'gpt3-ai-content-generator'),
            ['status' => 400, 'failed_module' => $stream_context, 'failed_operation' => 'resolve_stream_context']
        );
    }
}
