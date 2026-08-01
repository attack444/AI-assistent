<?php


namespace WPAICG\Dashboard\Ajax\Pinecone\HandlerIndexes;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Pinecone_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for deleting a Pinecone index.
 * Called by AIPKit_Vector_Store_Pinecone_Ajax_Handler::ajax_delete_index_pinecone().
 *
 * @param AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_delete_index_logic(AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_delete_pinecone', __('Vector Store components not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $pinecone_config = $handler_instance->_get_pinecone_config();
    if (is_wp_error($pinecone_config)) {
        $handler_instance->send_wp_error($pinecone_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
    if (empty($index_name)) {
        $handler_instance->send_wp_error(new WP_Error('missing_index_name_delete_pinecone', __('Pinecone index name is required for deletion.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $delete_result = $vector_store_manager->delete_index('Pinecone', $index_name, $pinecone_config);
    if (is_wp_error($delete_result)) {
        $handler_instance->send_wp_error($delete_result);
        return;
    }

    $vector_store_registry->remove_registered_store('Pinecone', $index_name);

    // Invalidate the cache for this index's logs before deleting
    $cache_key = 'pinecone_logs_' . sanitize_key($index_name);
    $cache_group = 'aipkit_vector_logs';
    wp_cache_delete($cache_key, $cache_group);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($data_source_table_name, ['provider' => 'Pinecone', 'vector_store_id' => $index_name], ['%s', '%s']);

    wp_send_json_success(['message' => __('Pinecone index deleted successfully.', 'gpt3-ai-content-generator')]);
}