<?php

namespace WPAICG\Chat\Core\AIService\GenerateResponse;

use WP_Error;
use WPAICG\AIPKit_Providers;
use WPAICG\Chat\Storage\BotSettingsManager; // For default constants
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage;
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/ai-params/methods.php';

// --- validate-request.php ---
/**
 * Validates the initial request parameters for generate_response.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller|null $ai_caller Instance of AI Caller.
 * @param string $user_message The user's input message.
 * @param array|null $image_inputs_for_service Optional array of image data.
 * @param array $bot_settings Settings for the specific bot.
 * @return true|WP_Error True if valid, WP_Error otherwise.
 */
function validate_request_logic(
    ?\WPAICG\Core\AIPKit_AI_Caller $ai_caller,
    string $user_message,
    ?array $image_inputs_for_service,
    array $bot_settings
) {
    if (!$ai_caller) {
        return new WP_Error('ai_caller_missing_validation', 'AI Caller component is not available for request validation.');
    }
    if (empty($user_message) && empty($image_inputs_for_service)) {
        return new WP_Error('empty_content_validation', __('User message or image cannot be empty.', 'gpt3-ai-content-generator'));
    }
    if (empty($bot_settings['bot_id'])) {
        return new WP_Error('missing_bot_id_validation', __('Bot ID is missing in settings for request validation.', 'gpt3-ai-content-generator'));
    }
    return true;
}

// --- load-instruction-manager.php ---
/**
 * Ensures the AIPKit_Instruction_Manager class is loaded.
 *
 * @return true|WP_Error True if loaded, WP_Error otherwise.
 */
function load_instruction_manager_logic()
{
    if (!class_exists(\WPAICG\Core\AIPKit_Instruction_Manager::class)) {
        $manager_path = WPAICG_PLUGIN_DIR . 'classes/core/class-aipkit-instruction-manager.php';
        if (file_exists($manager_path)) {
            require_once $manager_path;
        } else {
            return new WP_Error('internal_error_im_load', 'Instruction processing component missing (load logic).');
        }
    }
    if (!class_exists(\WPAICG\Core\AIPKit_Instruction_Manager::class)) { // Double check after require_once
        return new WP_Error('internal_error_im_not_loaded', 'InstructionManager class still not available after attempting load.');
    }
    return true;
}

// --- prepare-vector-search-context.php ---
// No direct use statements needed here if dependencies are passed.
// The function `build_vector_search_context_logic` is in a different namespace
// and will be called with its FQN.

/**
 * Prepares the vector search context for non-streaming chat interactions.
 *
 * This function serves as a wrapper for the centralized vector context building logic,
 * specifically for use in non-streaming chat scenarios.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller|null $ai_caller Instance of AI Caller, or null.
 * @param \WPAICG\Vector\AIPKit_Vector_Store_Manager|null $vector_store_manager Instance of Vector Store Manager, or null.
 * @param string $user_message The user's current message.
 * @param array  $bot_settings The settings of the current bot.
 * @param string $main_provider The main AI provider being used for the chat.
 * @param string|null $frontend_active_openai_vs_id Optional active OpenAI Vector Store ID from frontend.
 * @param string|null $frontend_active_pinecone_index_name Optional active Pinecone index name from frontend.
 * @param string|null $frontend_active_pinecone_namespace Optional active Pinecone namespace from frontend.
 * @param string|null $frontend_active_qdrant_collection_name Optional active Qdrant collection name.
 * @param string|null $frontend_active_qdrant_file_upload_context_id Optional active Qdrant file context ID.
 * @param string|null $frontend_active_chroma_collection_name Optional active Chroma collection name.
 * @param string|null $frontend_active_chroma_file_upload_context_id Optional active Chroma file context ID.
 * @param array|null &$vector_search_scores_output Optional reference to capture vector search scores for logging.
 * @return string The formatted context string from vector searches, or an empty string.
 */
function prepare_vector_search_context_logic(
    ?\WPAICG\Core\AIPKit_AI_Caller $ai_caller,
    ?\WPAICG\Vector\AIPKit_Vector_Store_Manager $vector_store_manager,
    string $user_message,
    array $bot_settings,
    string $main_provider,
    ?string $frontend_active_openai_vs_id = null,
    ?string $frontend_active_pinecone_index_name = null,
    ?string $frontend_active_pinecone_namespace = null,
    ?string $frontend_active_qdrant_collection_name = null,
    ?string $frontend_active_qdrant_file_upload_context_id = null,
    ?string $frontend_active_chroma_collection_name = null,
    ?string $frontend_active_chroma_file_upload_context_id = null,
    ?array &$vector_search_scores_output = null
): string {
    if (!$ai_caller || !$vector_store_manager) {
        return "";
    }

    // Call the centralized logic function
    return \WPAICG\Core\Stream\Vector\build_vector_search_context_logic(
        $ai_caller,
        $vector_store_manager,
        $user_message,
        $bot_settings,
        $main_provider,
        $frontend_active_openai_vs_id,
        $frontend_active_pinecone_index_name,
        $frontend_active_pinecone_namespace,
        $frontend_active_qdrant_collection_name,
        $frontend_active_qdrant_file_upload_context_id,
        $frontend_active_chroma_collection_name,
        $frontend_active_chroma_file_upload_context_id,
        $vector_search_scores_output
    );
}

// --- build-final-system-instruction.php ---
// AIPKit_Instruction_Manager is loaded by load_instruction_manager_logic

/**
 * Builds the final system instruction string using AIPKit_Instruction_Manager.
 *
 * @param array $bot_settings Settings of the specific bot.
 * @param int $post_id ID of the current post.
 * @param string $base_instructions The user-defined base instructions.
 * @param string $all_formatted_results_for_instruction Pre-formatted string of vector search results.
 * @return string The fully constructed system instruction string.
 */
function build_final_system_instruction_logic(
    array $bot_settings,
    int $post_id,
    string $base_instructions,
    string $all_formatted_results_for_instruction
): string {
    $instruction_context = [
        'base_instructions' => $base_instructions,
        'bot_settings' => $bot_settings,
        'post_id' => $post_id
    ];
    if (!empty($all_formatted_results_for_instruction)) {
        $instruction_context['vector_search_results'] = trim($all_formatted_results_for_instruction);
    }
    return \WPAICG\Core\AIPKit_Instruction_Manager::build_instructions($instruction_context);
}

// --- prepare-messages-for-api.php ---
/**
 * Prepares the messages array for the API call and extracts relevant info for stateful OpenAI.
 *
 * @param array $history Conversation history.
 * @param string $user_message_text The latest user message.
 * @return array Contains 'messages_payload', 'latest_user_message_obj_for_stateful', 'last_openai_response_id_from_history'.
 */
function prepare_messages_for_api_logic(array $history, string $user_message_text): array
{
    $messages_payload = [];
    $latest_user_message_obj_for_stateful = null;
    $last_openai_response_id_from_history = null;

    foreach ($history as $msg) {
        $role = ($msg['role'] === 'bot') ? 'assistant' : $msg['role'];
        $content = isset($msg['content']) ? trim($msg['content']) : '';
        if ($content !== '' && in_array($role, ['system', 'user', 'assistant'])) {
            $messages_payload[] = ['role' => $role, 'content' => $content];
            if ($role === 'user' && $msg['content'] === $user_message_text) { // Assuming history includes the current user message for this logic
                $latest_user_message_obj_for_stateful = ['role' => 'user', 'content' => $content];
            }
        }
        if (($role === 'assistant' || $role === 'bot') && isset($msg['openai_response_id']) && !empty($msg['openai_response_id'])) {
            $last_openai_response_id_from_history = $msg['openai_response_id'];
        }
    }
    // Note: The original AIService::generate_response adds the current user message to history
    // *after* limiting it, then loops through this potentially modified history.
    // For this refactor, this function receives the already limited $history.
    // The current $user_message_text is the latest message from the user.
    // The AI_Caller will typically add the latest user message to the final payload.
    // This function here primarily formats the existing $history for the API.
    // Let's adjust to assume $history is the *previous* history, and add $user_message_text here.
    // No, the original AIService directly adds $user_message as the last item to the history array for some providers,
    // and for OpenAI stateful, it uses only the latest user message.
    // The AIPKit_AI_Caller's format_chat_completions_payload actually expects the latest user message separately.
    // Let's stick to what AIPKit_AI_Caller expects for $messages_payload (which is history + latest user message).
    // This means this function should append the current user message to the $messages_payload.
    if (!empty($user_message_text)) {
        $messages_payload[] = ['role' => 'user', 'content' => $user_message_text];
        $latest_user_message_obj_for_stateful = ['role' => 'user', 'content' => $user_message_text];
    }

    return [
        'messages_payload' => $messages_payload,
        'latest_user_message_obj_for_stateful' => $latest_user_message_obj_for_stateful,
        'last_openai_response_id_from_history' => $last_openai_response_id_from_history
    ];
}

// --- prepare-final-ai-params.php ---
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

/**
 * Prepares the final AI parameters, including provider-specific adjustments.
 * Orchestrates calls to sub-module logic functions.
 *
 * @param array $ai_params_override Initial AI parameter overrides from bot settings or image inputs.
 * @param array $bot_settings Bot settings.
 * @param string $main_provider The main AI provider.
 * @param string $model The selected AI model.
 * @param string|null $frontend_previous_openai_response_id Previous OpenAI response ID from frontend.
 * @param string|null $last_openai_response_id_from_history Last OpenAI response ID from history.
 * @param array &$messages_payload_ref Reference to the messages payload (can be modified for OpenAI stateful).
 * @param bool $frontend_openai_web_search_active Flag for OpenAI web search.
 * @param bool $frontend_google_search_grounding_active Flag for Google Search Grounding.
 * @param string|null $frontend_active_openai_vs_id Active OpenAI Vector Store ID.
 * @param string|null $frontend_active_claude_file_id Active Claude file ID.
 * @return array ['final_ai_params' => array, 'actual_previous_response_id_to_use' => string|null]
 */
function prepare_final_ai_params_logic(
    array $ai_params_override,
    array $bot_settings,
    string $main_provider,
    string $model,
    ?string $frontend_previous_openai_response_id,
    ?string $last_openai_response_id_from_history,
    array &$messages_payload_ref, // Pass by reference
    bool $frontend_openai_web_search_active,
    bool $frontend_google_search_grounding_active,
    ?string $frontend_active_openai_vs_id,
    ?string $frontend_active_claude_file_id
): array {
    // Ensure dependencies are loaded (already handled in original file, repeated here for safety if this file were called standalone)
    if (!class_exists(AIPKit_Providers::class)) {
        $path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
    if (!class_exists(BotSettingsManager::class)) {
        $path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    $final_ai_params = $ai_params_override; // Start with overrides (temperature, max_tokens, image_inputs)
    $actual_previous_response_id_to_use = null;

    if ($main_provider === 'OpenAI') {
        $actual_previous_response_id_to_use = AiParams\apply_openai_stateful_conversation_logic(
            $final_ai_params,
            $messages_payload_ref, // Pass by reference
            $bot_settings,
            $frontend_previous_openai_response_id,
            $last_openai_response_id_from_history
        );

        // Get vector store IDs from bot settings
        $vector_store_ids_to_use_for_tool = $bot_settings['openai_vector_store_ids'] ?? [];
        if ($frontend_active_openai_vs_id && !in_array($frontend_active_openai_vs_id, $vector_store_ids_to_use_for_tool, true)) {
            $vector_store_ids_to_use_for_tool[] = $frontend_active_openai_vs_id;
        }

        AiParams\apply_openai_vector_tool_config_logic(
            $final_ai_params,
            $bot_settings,
            $vector_store_ids_to_use_for_tool,
            null // ai_service not needed for this function
        );
        AiParams\apply_openai_web_search_logic(
            $final_ai_params,
            $bot_settings,
            $frontend_openai_web_search_active
        );
        AiParams\apply_openai_reasoning_logic(
            $final_ai_params,
            $bot_settings,
            $model
        );
    } elseif ($main_provider === 'Claude') {
        AiParams\apply_claude_web_search_logic(
            $final_ai_params,
            $bot_settings,
            $frontend_openai_web_search_active
        );
        $vector_store_provider = sanitize_key((string) ($bot_settings['vector_store_provider'] ?? ''));
        $claude_file_id = sanitize_text_field((string) $frontend_active_claude_file_id);
        if (
            $vector_store_provider === 'claude_files' &&
            $claude_file_id !== '' &&
            preg_match('/^file_[a-zA-Z0-9_-]+$/', $claude_file_id)
        ) {
            $final_ai_params['claude_file_ids'] = [$claude_file_id];
        }
    } elseif ($main_provider === 'OpenRouter') {
        AiParams\apply_openrouter_web_search_logic(
            $final_ai_params,
            $bot_settings,
            $frontend_openai_web_search_active
        );
    } elseif ($main_provider === 'xAI') {
        AiParams\apply_xai_web_search_logic(
            $final_ai_params,
            $bot_settings,
            $frontend_openai_web_search_active
        );
    } elseif ($main_provider === 'Google') {
        AiParams\apply_google_search_grounding_logic(
            $final_ai_params,
            $bot_settings,
            $frontend_google_search_grounding_active
        );
    } elseif ($main_provider === 'Ollama') {
        AiParams\apply_ollama_thinking_logic(
            $final_ai_params,
            $bot_settings
        );
    }

    return [
        'final_ai_params' => $final_ai_params,
        'actual_previous_response_id_to_use' => $actual_previous_response_id_to_use
    ];
}

// --- execute-ai-call.php ---
/**
 * Executes the AI call using AIPKit_AI_Caller.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller $ai_caller Instance of AI Caller.
 * @param string $main_provider The main AI provider.
 * @param string $model The selected AI model.
 * @param array $messages_payload The prepared messages payload for the API.
 * @param array $final_ai_params The final AI parameters.
 * @param string $instructions_processed The processed system instruction.
 * @param array $instruction_context_for_logging Context used for instruction building (for logging).
 * @return array|WP_Error The result from AI Caller.
 */
function execute_ai_call_logic(
    \WPAICG\Core\AIPKit_AI_Caller $ai_caller,
    string $main_provider,
    string $model,
    array $messages_payload,
    array $final_ai_params,
    string $instructions_processed,
    array $instruction_context_for_logging
) {
    return $ai_caller->make_standard_call(
        $main_provider,
        $model,
        $messages_payload,
        $final_ai_params,
        $instructions_processed,
        $instruction_context_for_logging // Pass context for logging
    );
}

// --- handle-ai-call-error.php ---
/**
 * Handles logging and trigger dispatch for AI call errors.
 *
 * @param WP_Error $ai_call_error_result The WP_Error object from the AI call.
 * @param bool $triggers_enabled Whether triggers are enabled.
 * @param \WPAICG\Chat\Storage\LogStorage|null $log_storage_for_triggers Instance of LogStorage for triggers.
 * @param array $base_log_data Base log data (may be empty if not passed down).
 * @param string $main_provider The main AI provider.
 * @param string $model The selected AI model.
 * @param int|null $bot_id The ID of the bot.
 */
function handle_ai_call_error_logic(
    WP_Error $ai_call_error_result,
    bool $triggers_enabled,
    ?LogStorage $log_storage_for_triggers,
    array $base_log_data, // This might be empty depending on the orchestrator
    string $main_provider,
    string $model,
    ?int $bot_id
): void {

    $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
    $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';

    if ($triggers_enabled && class_exists($trigger_manager_class) && class_exists($trigger_storage_class)) {
        // Only proceed if log storage is available for the trigger manager
        if ($log_storage_for_triggers) {
            $error_data_from_caller = $ai_call_error_result->get_error_data() ?? [];
            $error_event_context = [
                'error_code'    => $ai_call_error_result->get_error_code(),
                'error_message' => $ai_call_error_result->get_error_message(),
                'bot_id'        => $bot_id ?? ($base_log_data['bot_id'] ?? null),
                'user_id'       => $base_log_data['user_id'] ?? null,
                'session_id'    => $base_log_data['session_id'] ?? null,
                'module'        => 'chat_ai_service',
                'operation'     => 'make_standard_call_in_generate_response',
                'failed_provider' => $error_data_from_caller['provider'] ?? $main_provider,
                'failed_model'    => $error_data_from_caller['model'] ?? $model,
                'status_code'   => $error_data_from_caller['status_code'] ?? null,
            ];
            $trigger_storage = new $trigger_storage_class();
            $trigger_manager = new $trigger_manager_class($trigger_storage, $log_storage_for_triggers);
            $trigger_manager->process_event($bot_id ?? 0, 'system_error_occurred', $error_event_context);
        }
    }
}

// --- finalize-ai-response.php ---
/**
 * Applies final filters to the successful AI response and prepares the return structure.
 *
 * @param array $ai_call_success_result The successful result from AI Caller.
 * @param string $main_provider The main AI provider.
 * @param string $model The selected AI model.
 * @param array $history Conversation history (used by the filter).
 * @param string $base_instructions Base system instructions (used by the filter).
 * @param array $final_ai_params Final AI parameters (used by the filter).
 * @param string|null $actual_previous_response_id_to_use OpenAI stateful ID, if used.
 * @return array The final response structure.
 */
function finalize_ai_response_logic(
    array $ai_call_success_result,
    string $main_provider,
    string $model,
    array $history,
    string $base_instructions,
    array $final_ai_params,
    ?string $actual_previous_response_id_to_use
): array {
    $final_instructions_for_filter = $ai_call_success_result['request_payload_log']['system_instruction'] ?? $base_instructions;

    $ai_call_success_result['content'] = apply_filters(
        'aipkit_ai_response',
        $ai_call_success_result['content'],
        null, // Stream type is null for non-streaming
        $main_provider,
        $model,
        $history,
        $final_instructions_for_filter,
        null, // SSE chunk data (null here)
        $final_ai_params
    );

    if ($actual_previous_response_id_to_use !== null && ($main_provider === 'OpenAI' && ($final_ai_params['use_openai_conversation_state'] ?? false))) {
        $ai_call_success_result['used_previous_response_id'] = true;
    }

    return $ai_call_success_result;
}
