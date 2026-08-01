<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Core\Stream\Vector;

use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\AIPKit_Providers; // This class is used by the logic file
use WP_Error; // For type hinting, though not directly returned by this orchestrator

// Load vector context helper functions.
require_once __DIR__ . '/build-context/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Builds the vector search results context string.
 * This function now orchestrates calls to modularized logic functions.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller $ai_caller Instance of AI Caller.
 * @param \WPAICG\Vector\AIPKit_Vector_Store_Manager $vector_store_manager Instance of Vector Store Manager.
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
function build_vector_search_context_logic(
    AIPKit_AI_Caller $ai_caller,
    AIPKit_Vector_Store_Manager $vector_store_manager,
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
    global $wpdb;
    $data_source_table_name = $wpdb->prefix . 'aipkit_vector_data_source';

    $vector_store_enabled = ($bot_settings['enable_vector_store'] ?? '0') === '1';

    if (!BuildContext\check_prerequisites_logic($vector_store_enabled, $user_message, $ai_caller, $vector_store_manager)) {
        return "";
    }

    $all_formatted_results = "";
    $vector_provider_from_bot = $bot_settings['vector_store_provider'] ?? '';
    $vector_top_k = absint($bot_settings['vector_store_top_k'] ?? 3);
    $vector_top_k = max(1, min($vector_top_k, 20));

    // Initialize scores output array if reference provided
    if ($vector_search_scores_output !== null) {
        $vector_search_scores_output = [];
    }

    if ($vector_provider_from_bot === 'openai') {
        $openai_results = BuildContext\resolve_openai_context_logic(
            $vector_store_manager,
            $user_message,
            $bot_settings,
            $main_provider,
            $frontend_active_openai_vs_id,
            $vector_top_k,
            $vector_search_scores_output
        );
        $all_formatted_results .= $openai_results;
    } elseif ($vector_provider_from_bot === 'pinecone') {
        $pinecone_results = BuildContext\resolve_pinecone_context_logic(
            $ai_caller,
            $vector_store_manager,
            $user_message,
            $bot_settings,
            $frontend_active_pinecone_index_name,
            $frontend_active_pinecone_namespace,
            $vector_top_k,
            $wpdb,
            $data_source_table_name,
            $vector_search_scores_output
        );
        $all_formatted_results .= $pinecone_results;
    } elseif ($vector_provider_from_bot === 'qdrant') {
        $qdrant_results = BuildContext\resolve_qdrant_context_logic(
            $ai_caller,
            $vector_store_manager,
            $user_message,
            $bot_settings,
            $frontend_active_qdrant_collection_name,
            $frontend_active_qdrant_file_upload_context_id,
            $vector_top_k,
            $wpdb,
            $data_source_table_name,
            $vector_search_scores_output
        );
        $all_formatted_results .= $qdrant_results;
    } elseif ($vector_provider_from_bot === 'chroma') {
        $chroma_results = BuildContext\resolve_chroma_context_logic(
            $ai_caller,
            $vector_store_manager,
            $user_message,
            $bot_settings,
            $frontend_active_chroma_collection_name,
            $frontend_active_chroma_file_upload_context_id,
            $vector_top_k,
            $wpdb,
            $data_source_table_name,
            $vector_search_scores_output
        );
        $all_formatted_results .= $chroma_results;
    }
    
    return trim($all_formatted_results);
}
