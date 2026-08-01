<?php

namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerStores;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Stores_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for deleting an OpenAI Vector Store.
 * Called by AIPKit_OpenAI_Vector_Stores_Ajax_Handler::ajax_delete_vector_store_openai().
 *
 * @param AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_delete_vector_store_openai_logic(AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance): void
{
    // Permission check already done by the handler calling this

    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();


    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready', __('Vector Store Manager or Registry not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $openai_config = $handler_instance->_get_openai_config();
    if (is_wp_error($openai_config)) {
        $handler_instance->send_wp_error($openai_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $store_id = isset($post_data['store_id']) ? sanitize_text_field($post_data['store_id']) : '';
    if (empty($store_id)) {
        $handler_instance->send_wp_error(new WP_Error('missing_store_id', __('Vector Store ID is required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $delete_result = $vector_store_manager->delete_index('OpenAI', $store_id, $openai_config);
    if (is_wp_error($delete_result)) {
        $handler_instance->send_wp_error($delete_result);
        return;
    }

    $vector_store_registry->remove_registered_store('OpenAI', $store_id);

    // Invalidate the cache for this store's logs before deleting
    $cache_key = 'openai_logs_' . sanitize_key($store_id);
    wp_cache_delete($cache_key, 'aipkit_vector_logs');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Necessary delete operation on a custom table after an API action. Cache was invalidated above.
    $wpdb->delete($data_source_table_name, ['provider' => 'OpenAI', 'vector_store_id' => $store_id], ['%s', '%s']);

    wp_send_json_success(['message' => __('Vector Store deleted successfully.', 'gpt3-ai-content-generator')]);
}