<?php

namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerStores;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Stores_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for creating an OpenAI Vector Store.
 * Called by AIPKit_OpenAI_Vector_Stores_Ajax_Handler::ajax_create_vector_store_openai().
 *
 * @param AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_create_vector_store_openai_logic(AIPKit_OpenAI_Vector_Stores_Ajax_Handler $handler_instance): void
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
    $store_name = isset($post_data['name']) ? sanitize_text_field($post_data['name']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $source_type = isset($post_data['source_type']) ? sanitize_key($post_data['source_type']) : 'backend_ai_training_panel_create';

    if (empty($store_name)) {
        $handler_instance->send_wp_error(new WP_Error('missing_name', __('Vector Store name is required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $index_config = ['metadata' => ['source_type' => $source_type]];

    $store_result = $vector_store_manager->create_index_if_not_exists('OpenAI', $store_name, $index_config, $openai_config);
    if (is_wp_error($store_result)) {
        $handler_instance->send_wp_error($store_result);
        return;
    }

    $vector_store_registry->add_registered_store('OpenAI', $store_result);
    wp_send_json_success(['store' => $store_result, 'message' => __('Vector Store created.', 'gpt3-ai-content-generator')]);
}