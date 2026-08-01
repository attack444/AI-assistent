<?php


namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerStores;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Stores_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for listing OpenAI Vector Stores.
 * Called by AIPKit_OpenAI_Vector_Stores_Ajax_Handler::ajax_list_vector_stores_openai().
 *
 * @param AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_list_vector_stores_openai_logic(AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance): void
{
    // Permission check already done by the handler calling this

    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

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
    $limit  = isset($post_data['limit']) ? absint($post_data['limit']) : 20;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $order  = isset($post_data['order']) && in_array($post_data['order'], ['asc', 'desc']) ? sanitize_key($post_data['order']) : 'desc';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $after  = isset($post_data['after']) && !empty($post_data['after']) ? sanitize_text_field($post_data['after']) : null;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $before = isset($post_data['before']) && !empty($post_data['before']) ? sanitize_text_field($post_data['before']) : null;
    $limit = max(1, min($limit, 100));

    $response = $vector_store_manager->list_all_indexes('OpenAI', $openai_config, $limit, $order, $after, $before);

    if (is_wp_error($response)) {
        $handler_instance->send_wp_error($response);
        return;
    }

    // --- MODIFICATION: Filter out expired stores ---
    $stores_from_api = $response['data'] ?? [];
    $active_stores_data = [];
    if (is_array($stores_from_api)) {
        foreach ($stores_from_api as $store_item) {
            // Only include stores that are not 'expired'.
            // Assume stores without a status or with other statuses (e.g., 'in_progress', 'completed') should be included.
            if (isset($store_item['status']) && $store_item['status'] === 'expired') {
                continue;
            }
            $active_stores_data[] = $store_item;
        }
    }
    // Replace the original data with filtered data for registry update and client response
    $response['data'] = $active_stores_data;


    // Only update the full registry if it's a likely full sync attempt
    $is_full_sync_attempt = (empty($after) && empty($before) && $limit >= 100);
    if (isset($response['data']) && is_array($response['data']) && $is_full_sync_attempt) {
        $response['data'] = $vector_store_registry->replace_provider_cache('OpenAI', $response['data']);
    }

    wp_send_json_success([
        'stores' => $response['data'] ?? [], // This will now be the filtered list
        'first_id' => $response['first_id'] ?? null,
        'last_id' => $response['last_id'] ?? null,
        'has_more' => $response['has_more'] ?? false,
    ]);
}
