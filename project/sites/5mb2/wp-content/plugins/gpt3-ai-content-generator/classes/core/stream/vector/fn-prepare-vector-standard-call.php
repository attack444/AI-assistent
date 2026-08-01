<?php

// Purpose: DRY helper to prepare vector context, ai_params additions, and instruction_context for standard (non-SSE) calls.

namespace WPAICG\Core\Stream\Vector;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Prepare vector context for a standard AI call.
 * - Optionally builds vector context text (Pinecone/Qdrant; OpenAI returns empty string) and prefixes system instruction.
 * - Collects vector_search_scores for logging and returns them both in ai_params (for payload parity) and instruction_context (for top-level log surfacing).
 * - Configures OpenAI file_search tool when vector provider is OpenAI and IDs present.
 *
 * @param object|null $ai_caller              The AI caller instance.
 * @param object|null $vector_store_manager   The vector store manager instance.
 * @param string      $user_message           The user prompt content for the call.
 * @param array       $form_data              The POST/form data containing vector settings.
 * @param string      $provider               Provider canonical name (e.g., 'OpenAI', 'Google').
 * @param string      $base_system_instruction The base system instruction to augment.
 * @param array       $existing_ai_params     Existing ai_params to extend.
 * @return array { system_instruction: string, ai_params: array, instruction_context: array }
 */
function prepare_vector_standard_call(
    $ai_caller,
    $vector_store_manager,
    string $user_message,
    array $form_data,
    string $provider,
    string $base_system_instruction,
    array $existing_ai_params = []
): array {
    $system_instruction = $base_system_instruction;
    $ai_params = $existing_ai_params;
    $instruction_context = [];

    $is_vector_enabled = ($form_data['enable_vector_store'] ?? '0') === '1';
    if (!$is_vector_enabled || !$ai_caller || !$vector_store_manager) {
        return [
            'system_instruction' => $system_instruction,
            'ai_params' => $ai_params,
            'instruction_context' => $instruction_context,
        ];
    }

    // Ensure vector context builder is available
    if (!function_exists('WPAICG\\Core\\Stream\\Vector\\build_vector_search_context_logic')) {
        $vector_logic_path = defined('WPAICG_PLUGIN_DIR') ? WPAICG_PLUGIN_DIR . 'classes/core/stream/vector/fn-build-vector-search-context.php' : null;
        if ($vector_logic_path && file_exists($vector_logic_path)) {
            require_once $vector_logic_path;
        }
    }

    $collected_vector_search_scores = [];
    if (function_exists('WPAICG\\Core\\Stream\\Vector\\build_vector_search_context_logic')) {
        $vector_context = build_vector_search_context_logic(
            $ai_caller,
            $vector_store_manager,
            $user_message,
            $form_data,
            $provider,
            null, // active OpenAI VS id (none from CW UI)
            $form_data['pinecone_index_name'] ?? null,
            null, // pinecone namespace (optional)
            $form_data['qdrant_collection_name'] ?? null,
            null, // qdrant file upload context id (optional)
            $form_data['chroma_collection_name'] ?? null,
            null, // chroma file upload context id (optional)
            $collected_vector_search_scores
        );

        if (!empty($vector_context)) {
            $system_instruction = $vector_context . "\n\n---\n\n" . $system_instruction;
        }
        if (!empty($collected_vector_search_scores)) {
            // Attach for payload parity and top-level surfacing via instruction_context
            $ai_params['vector_search_scores'] = $collected_vector_search_scores;
            $instruction_context['vector_search_scores'] = $collected_vector_search_scores;
        }
    }

    // Configure OpenAI file_search tool when applicable
    $vector_provider = $form_data['vector_store_provider'] ?? 'openai';
    if ($provider === 'OpenAI' && $vector_provider === 'openai') {
        $openai_vs_ids = $form_data['openai_vector_store_ids'] ?? [];
        if (!empty($openai_vs_ids) && is_array($openai_vs_ids)) {
            $vector_top_k = isset($form_data['vector_store_top_k']) ? absint($form_data['vector_store_top_k']) : 3;
            $vector_top_k = max(1, min($vector_top_k, 20));
            $confidence_threshold_percent = (int)($form_data['vector_store_confidence_threshold'] ?? 20);
            if ($confidence_threshold_percent <= 0) {
                $openai_score_threshold = 0.0;
            } elseif ($confidence_threshold_percent >= 100) {
                $openai_score_threshold = 1.0;
            } else {
                $openai_score_threshold = round($confidence_threshold_percent / 100, 6);
            }
            $ai_params['vector_store_tool_config'] = [
                'type' => 'file_search',
                'vector_store_ids' => $openai_vs_ids,
                'max_num_results' => $vector_top_k,
                'ranking_options' => [
                    'score_threshold' => $openai_score_threshold,
                ],
            ];
        }
    }

    return [
        'system_instruction' => $system_instruction,
        'ai_params' => $ai_params,
        'instruction_context' => $instruction_context,
    ];
}
