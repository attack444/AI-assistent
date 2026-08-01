<?php

namespace WPAICG\Dashboard\Ajax\Chroma\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Chroma_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists(__NAMESPACE__ . '\_aipkit_chroma_normalize_collection_for_ui')) {
    require_once __DIR__ . '/ajax-list-collections.php';
}

function _aipkit_chroma_ajax_create_collection_logic(AIPKit_Vector_Store_Chroma_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_create_chroma', __('Vector Store components not available for Chroma.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $chroma_config = $handler_instance->_get_chroma_config();
    if (is_wp_error($chroma_config)) {
        $handler_instance->send_wp_error($chroma_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    $collection_name = isset($post_data['name']) ? sanitize_text_field($post_data['name']) : '';

    if ($collection_name === '') {
        $handler_instance->send_wp_error(new WP_Error('missing_name_chroma_create', __('Collection name is required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $create_result = $vector_store_manager->create_index_if_not_exists('Chroma', $collection_name, [], $chroma_config);
    if (is_wp_error($create_result)) {
        $handler_instance->send_wp_error($create_result);
        return;
    }

    $collection = is_array($create_result) ? $create_result : ['name' => $collection_name, 'id' => $collection_name];
    $collection['name'] = $collection['name'] ?? $collection_name;
    $collection = _aipkit_chroma_normalize_collection_for_ui($collection);
    $vector_store_registry->add_registered_store('Chroma', $collection);

    wp_send_json_success([
        'collection' => $collection,
        'message' => __('Chroma collection created/verified.', 'gpt3-ai-content-generator'),
    ]);
}
