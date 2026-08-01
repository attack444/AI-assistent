<?php

namespace WPAICG\Dashboard\Ajax\Pinecone\HandlerIndexes;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Pinecone_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for listing Pinecone indexes.
 * Called by AIPKit_Vector_Store_Pinecone_Ajax_Handler::ajax_list_indexes_pinecone().
 *
 * @param AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_list_indexes_logic(AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance): void {
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_list_pinecone', __('Vector Store components not available for Pinecone.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $pinecone_config = $handler_instance->_get_pinecone_config();
    if (is_wp_error($pinecone_config)) {
        $handler_instance->send_wp_error($pinecone_config);
        return;
    }

    $response = $vector_store_manager->list_all_indexes('Pinecone', $pinecone_config);
    if (is_wp_error($response)) {
        $handler_instance->send_wp_error($response);
        return;
    }

    // Enrich: fetch detailed stats for each index so total_vector_count is available
    $detailed_indexes = [];
    if (is_array($response)) {
        foreach ($response as $index_summary) {
            $index_name = $index_summary['name'] ?? $index_summary['id'] ?? null;
            if (!$index_name) {
                continue;
            }
            $details = $vector_store_manager->describe_single_index('Pinecone', $index_name, $pinecone_config);
            if (!is_wp_error($details)) {
                // Merge summary fields into details to keep any list-only fields
                $detailed_indexes[] = array_merge($index_summary, $details);
            } else {
                // Fallback to summary if describe fails for any index
                $detailed_indexes[] = $index_summary;
            }
        }
    }

    $detailed_indexes = $vector_store_registry->replace_provider_cache('Pinecone', $detailed_indexes);

    wp_send_json_success([
        'indexes' => $detailed_indexes,
        'message' => __('Pinecone indexes synced successfully.', 'gpt3-ai-content-generator')
    ]);
}
