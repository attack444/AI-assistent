<?php


namespace WPAICG\Chat\Core\AIService;

use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\AIPKit_Instruction_Manager;
use WP_Error;
use WPAICG\Chat\Storage\LogStorage; // For handle_ai_call_error_logic
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage; // For handle_ai_call_error_logic
use WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager; // For handle_ai_call_error_logic

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load method logic files for this orchestrator.
require_once __DIR__ . '/generate-response/methods.php';

// Ensure the existing determine_provider_model function is available
require_once __DIR__ . '/determine_provider_model.php';


/**
 * Orchestrates the generation of an AI response for the chat.
 * This function now delegates its logic to smaller, specialized functions.
 *
 * @param \WPAICG\Chat\Core\AIService $serviceInstance The instance of the AIService class.
 * @param string $user_message The user's input message.
 * @param array  $bot_settings Settings for the specific bot.
 * @param array  $history The pre-fetched and limited conversation history.
 * @param int    $post_id The ID of the post/page where the chat is embedded.
 * @param string|null $frontend_previous_openai_response_id The last OpenAI response ID from frontend.
 * @param bool   $frontend_openai_web_search_active Flag for OpenAI web search.
 * @param bool   $frontend_google_search_grounding_active Flag for Google Search Grounding.
 * @param array|null $image_inputs_for_service Optional array of image data.
 * @param string|null $frontend_active_openai_vs_id Optional active OpenAI Vector Store ID from frontend.
 * @param string|null $frontend_active_pinecone_index_name Optional active Pinecone index name from frontend.
 * @param string|null $frontend_active_pinecone_namespace Optional active Pinecone namespace from frontend.
 * @param string|null $frontend_active_qdrant_collection_name Optional active Qdrant collection name from frontend.
 * @param string|null $frontend_active_qdrant_file_upload_context_id Optional active Qdrant file context ID from frontend.
 * @param string|null $frontend_active_chroma_collection_name Optional active Chroma collection name from frontend.
 * @param string|null $frontend_active_chroma_file_upload_context_id Optional active Chroma file context ID from frontend.
 * @param string|null $frontend_active_claude_file_id Optional active Claude file ID from frontend.
 * @return array|WP_Error Response data or WP_Error.
 */
function generate_response(
    \WPAICG\Chat\Core\AIService $serviceInstance,
    string $user_message,
    array $bot_settings,
    array $history,
    int $post_id = 0,
    ?string $frontend_previous_openai_response_id = null,
    bool $frontend_openai_web_search_active = false,
    bool $frontend_google_search_grounding_active = false,
    ?array $image_inputs_for_service = null,
    ?string $frontend_active_openai_vs_id = null,
    ?string $frontend_active_pinecone_index_name = null,
    ?string $frontend_active_pinecone_namespace = null,
    ?string $frontend_active_qdrant_collection_name = null,
    ?string $frontend_active_qdrant_file_upload_context_id = null,
    ?string $frontend_active_chroma_collection_name = null,
    ?string $frontend_active_chroma_file_upload_context_id = null,
    ?string $frontend_active_claude_file_id = null
) {
    $ai_caller = $serviceInstance->get_ai_caller();
    $vector_store_manager = $serviceInstance->get_vector_store_manager(); // Get Vector Store Manager

    $validation_result = GenerateResponse\validate_request_logic($ai_caller, $user_message, $image_inputs_for_service, $bot_settings);
    if (is_wp_error($validation_result)) {
        return $validation_result;
    }

    $provider_info = determine_provider_model($serviceInstance, $bot_settings);
    $main_provider = $provider_info['provider'];
    $model = $provider_info['model'];
    if (empty($model)) {
        return new WP_Error('missing_model_orchestrator', __('Chatbot AI Model or Deployment Name is missing in settings.', 'gpt3-ai-content-generator'));
    }

    if (!empty($image_inputs_for_service)) {
        /**
         * Allow provider-specific image capability validation for chat requests.
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
            $image_inputs_for_service,
            $main_provider,
            $model,
            $bot_settings,
            'non_stream'
        );
        if (is_wp_error($image_validation_error)) {
            return $image_validation_error;
        }
    }

    $im_load_result = GenerateResponse\load_instruction_manager_logic();
    if (is_wp_error($im_load_result)) {
        return $im_load_result;
    }

    $vector_search_scores = []; // Initialize array to capture vector search scores
    $all_formatted_results_for_instruction = GenerateResponse\prepare_vector_search_context_logic(
        $ai_caller, // Pass AI Caller
        $vector_store_manager, // Pass Vector Store Manager
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
        $vector_search_scores // Pass reference to capture scores
    );

    $base_instructions = $bot_settings['instructions'] ?? '';
    $instruction_context_for_logging = [
        'bot_settings' => $bot_settings, 
        'post_id' => $post_id, 
        'vector_search_results' => $all_formatted_results_for_instruction,
        'vector_search_scores' => $vector_search_scores // Add vector search scores for logging
    ];
    $instructions_processed = GenerateResponse\build_final_system_instruction_logic($bot_settings, $post_id, $base_instructions, $all_formatted_results_for_instruction);

    $messages_prep_result = GenerateResponse\prepare_messages_for_api_logic($history, $user_message);
    $messages_payload = $messages_prep_result['messages_payload'];
    $latest_user_message_obj_for_stateful = $messages_prep_result['latest_user_message_obj_for_stateful'];
    $last_openai_response_id_from_history = $messages_prep_result['last_openai_response_id_from_history'];

    $ai_params_override_from_config = [];
    if (isset($bot_settings['temperature'])) {
        $ai_params_override_from_config['temperature'] = floatval($bot_settings['temperature']);
    }
    if (isset($bot_settings['max_completion_tokens'])) {
        $ai_params_override_from_config['max_completion_tokens'] = absint($bot_settings['max_completion_tokens']);
    }
    if (!empty($image_inputs_for_service)) {
        $ai_params_override_from_config['image_inputs'] = $image_inputs_for_service;
    }

    $final_ai_params_result = GenerateResponse\prepare_final_ai_params_logic(
        $ai_params_override_from_config,
        $bot_settings,
        $main_provider,
        $model,
        $frontend_previous_openai_response_id,
        $last_openai_response_id_from_history,
        $messages_payload,
        $frontend_openai_web_search_active,
        $frontend_google_search_grounding_active,
        $frontend_active_openai_vs_id,
        $frontend_active_claude_file_id
    );
    $final_ai_params = $final_ai_params_result['final_ai_params'];
    $actual_previous_response_id_to_use = $final_ai_params_result['actual_previous_response_id_to_use'];

    $ai_call_result = GenerateResponse\execute_ai_call_logic(
        $ai_caller,
        $main_provider,
        $model,
        $messages_payload,
        $final_ai_params,
        $instructions_processed,
        $instruction_context_for_logging
    );

    if (is_wp_error($ai_call_result)) {
        $triggers_enabled = false;
        if (class_exists('\WPAICG\aipkit_dashboard')) {
            $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
        }
        GenerateResponse\handle_ai_call_error_logic(
            $ai_call_result,
            $triggers_enabled,
            $serviceInstance->get_log_storage(),
            [],
            $main_provider,
            $model,
            $bot_settings['bot_id'] ?? 0
        );
        return $ai_call_result;
    }

    return GenerateResponse\finalize_ai_response_logic(
        $ai_call_result,
        $main_provider,
        $model,
        $history,
        $base_instructions,
        $final_ai_params,
        $actual_previous_response_id_to_use
    );
}
