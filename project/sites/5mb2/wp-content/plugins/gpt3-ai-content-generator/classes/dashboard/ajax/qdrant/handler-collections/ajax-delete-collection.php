<?php


namespace WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Qdrant_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for deleting a Qdrant collection.
 * Called by AIPKit_Vector_Store_Qdrant_Ajax_Handler::ajax_delete_collection_qdrant().
 *
 * @param AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance
 * @return void
 */
function _aipkit_qdrant_ajax_delete_collection_logic(AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_delete_qdrant', __('Vector Store components not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $qdrant_config = $handler_instance->_get_qdrant_config();
    if (is_wp_error($qdrant_config)) {
        $handler_instance->send_wp_error($qdrant_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $collection_name = isset($_POST['collection_name']) ? sanitize_text_field(wp_unslash($_POST['collection_name'])) : '';
    if (empty($collection_name)) {
        $handler_instance->send_wp_error(new WP_Error('missing_name_delete_qdrant', __('Collection name is required for deletion.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $delete_result = $vector_store_manager->delete_index('Qdrant', $collection_name, $qdrant_config);
    if (is_wp_error($delete_result)) {
        $handler_instance->send_wp_error($delete_result);
        return;
    }

    $vector_store_registry->remove_registered_store('Qdrant', $collection_name);

    // Invalidate the cache for this collection's logs before deleting
    $cache_key = 'qdrant_logs_' . sanitize_key($collection_name);
    $cache_group = 'aipkit_vector_logs';
    wp_cache_delete($cache_key, $cache_group);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($data_source_table_name, ['provider' => 'Qdrant', 'vector_store_id' => $collection_name], ['%s', '%s']);

    wp_send_json_success(['message' => __('Qdrant collection deleted successfully.', 'gpt3-ai-content-generator')]);
}