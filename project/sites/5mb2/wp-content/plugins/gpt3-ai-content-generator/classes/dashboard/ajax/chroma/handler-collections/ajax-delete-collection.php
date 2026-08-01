<?php

namespace WPAICG\Dashboard\Ajax\Chroma\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Chroma_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

function _aipkit_chroma_ajax_delete_collection_logic(AIPKit_Vector_Store_Chroma_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_delete_chroma', __('Vector Store components not available for Chroma.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $chroma_config = $handler_instance->_get_chroma_config();
    if (is_wp_error($chroma_config)) {
        $handler_instance->send_wp_error($chroma_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $collection_name = isset($_POST['collection_name']) ? sanitize_text_field(wp_unslash($_POST['collection_name'])) : '';
    if ($collection_name === '') {
        $handler_instance->send_wp_error(new WP_Error('missing_name_delete_chroma', __('Collection name is required for deletion.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $delete_result = $vector_store_manager->delete_index('Chroma', $collection_name, $chroma_config);
    if (is_wp_error($delete_result)) {
        $handler_instance->send_wp_error($delete_result);
        return;
    }

    $vector_store_registry->remove_registered_store('Chroma', $collection_name);
    wp_cache_delete('chroma_logs_' . sanitize_key($collection_name), 'aipkit_vector_logs');
    wp_cache_delete('chroma_logs_count_' . sanitize_key($collection_name), 'aipkit_vector_logs');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete($data_source_table_name, ['provider' => 'Chroma', 'vector_store_id' => $collection_name], ['%s', '%s']);

    wp_send_json_success(['message' => __('Chroma collection deleted successfully.', 'gpt3-ai-content-generator')]);
}
