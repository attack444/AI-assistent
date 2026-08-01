<?php


namespace WPAICG\Core\Stream\Contexts\AIForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

// Load the request processing logic files.
require_once __DIR__ . '/process/methods.php';

/**
 * Orchestrates the processing of an AI Forms stream request by calling modularized logic functions.
 *
 * @param SSEAIFormsStreamContextHandler $handlerInstance The instance of the context handler.
 * @param array $cached_data Contains 'stream_context', 'form_id', 'user_input_values'.
 * @param array $get_params  Original $_GET parameters.
 * @return array|WP_Error Prepared data for SSEStreamProcessor or WP_Error.
 */
function process_ai_forms_logic(
    SSEAIFormsStreamContextHandler $handlerInstance,
    array $cached_data,
    array $get_params
) {
    // 1. Validate the request and check tokens
    $validated_params = Process\validate_request_logic($handlerInstance, $cached_data, $get_params);
    if (is_wp_error($validated_params)) {
        return $validated_params;
    }

    $form_id = $validated_params['form_id'];
    $submitted_fields = $validated_params['user_input_values'];
    $form_config = $validated_params['form_config'];

    // 3. Build the AI prompt
    $final_prompt = Process\build_prompt_logic($form_config, $submitted_fields);
    if (is_wp_error($final_prompt)) {
        return $final_prompt;
    }

    $moderation_text = Process\build_moderation_text_logic($form_config, $submitted_fields);

    if (class_exists(\WPAICG\Core\AIPKit_Content_Moderator::class)) {
        $moderation_context = [
            'client_ip'    => $validated_params['client_ip'],
            'bot_settings' => [ // Minimal settings needed for OpenAI moderation provider check
                'provider' => $form_config['ai_provider'] ?? 'OpenAI'
            ],
            'module' => 'ai_forms',
        ];
        $moderation_check = \WPAICG\Core\AIPKit_Content_Moderator::check_content($moderation_text, $moderation_context);
        if (is_wp_error($moderation_check)) {
            // The error object from the moderator should already have a user-friendly message and status code.
            return $moderation_check;
        }
    }

    $ai_caller = $handlerInstance->get_ai_caller();
    $vector_store_manager = $handlerInstance->get_vector_store_manager();
    $system_instruction = $form_config['system_instruction'] ?? ''; // Currently no UI for this, but support it.
    $vector_search_scores = []; // Initialize array to capture vector search scores

    if ($ai_caller && $vector_store_manager && ($form_config['enable_vector_store'] ?? '0') === '1') {
        $vector_search_context = \WPAICG\Core\Stream\Vector\build_vector_search_context_logic(
            $ai_caller,
            $vector_store_manager,
            $final_prompt, // The user's composed message from the form fields
            $form_config, // The form config acts as the 'bot_settings' for this context
            $form_config['ai_provider'], // The AI provider for this form submission
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $vector_search_scores // Pass reference to capture scores
        );

        if (!empty($vector_search_context)) {
            // Prepend the found context to the system instruction area.
            // If there's an existing system instruction, add a separator.
            $system_instruction = !empty($system_instruction)
                ? $vector_search_context . "\n\n---\n\n" . $system_instruction
                : $vector_search_context;
        }
    }


    // 4. Log the request and prepare the final data for the SSE processor
    return Process\prepare_stream_data_logic($handlerInstance, $validated_params, $form_config, $final_prompt, $system_instruction, $vector_search_scores);
}
