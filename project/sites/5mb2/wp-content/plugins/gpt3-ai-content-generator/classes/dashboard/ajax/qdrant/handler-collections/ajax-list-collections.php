<?php


namespace WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Qdrant_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for listing Qdrant collections.
 * Called by AIPKit_Vector_Store_Qdrant_Ajax_Handler::ajax_list_collections_qdrant().
 *
 * @param AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance
 * @return void
 */
function _aipkit_qdrant_ajax_list_collections_logic(AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance): void {
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_list_qdrant', __('Vector Store components not available for Qdrant.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $qdrant_config = $handler_instance->_get_qdrant_config();
    if (is_wp_error($qdrant_config)) {
        $handler_instance->send_wp_error($qdrant_config);
        return;
    }

    $response = $vector_store_manager->list_all_indexes('Qdrant', $qdrant_config);
    if (is_wp_error($response)) {
        $handler_instance->send_wp_error($response);
        return;
    }

    $detailed_collections = [];
    if (is_array($response)) {
        foreach ($response as $collection_summary) {
            if (!is_array($collection_summary)) {
                continue;
            }
            $collection_name = $collection_summary['name'] ?? ($collection_summary['id'] ?? null);
            if ($collection_name) {
                $details = $vector_store_manager->describe_single_index('Qdrant', $collection_name, $qdrant_config);
                $detailed_collections[] = is_wp_error($details) ? $collection_summary : array_merge($collection_summary, $details);
            }
        }
    }

    $detailed_collections = $vector_store_registry->replace_provider_cache('Qdrant', $detailed_collections);
    
    wp_send_json_success(['collections' => $detailed_collections, 'message' => __('Qdrant collections synced successfully.', 'gpt3-ai-content-generator')]);
}
