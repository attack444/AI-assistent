<?php


namespace WPAICG\Dashboard\Ajax\Pinecone\HandlerIndexes;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Pinecone_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for creating a Pinecone index.
 * Called by AIPKit_Vector_Store_Pinecone_Ajax_Handler::ajax_create_index_pinecone().
 *
 * @param AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_create_index_logic(AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_create_pinecone', __('Vector Store components not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $pinecone_config = $handler_instance->_get_pinecone_config();
    if (is_wp_error($pinecone_config)) {
        $handler_instance->send_wp_error($pinecone_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    $index_name = isset($post_data['name']) ? sanitize_text_field($post_data['name']) : '';
    $dimension = isset($post_data['dimension']) ? absint($post_data['dimension']) : 0;
    $metric = isset($post_data['metric']) ? sanitize_text_field($post_data['metric']) : 'cosine';
    $spec_cloud = isset($post_data['spec_cloud']) ? sanitize_text_field($post_data['spec_cloud']) : 'aws';
    $spec_region = isset($post_data['spec_region']) ? sanitize_text_field($post_data['spec_region']) : 'us-east-1';

    if (empty($index_name)) {
        $handler_instance->send_wp_error(new WP_Error('missing_name_pinecone_create', __('Pinecone index name is required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    if ($dimension <= 0) {
        $handler_instance->send_wp_error(new WP_Error('invalid_dimension_pinecone_create', __('Vector dimension must be a positive integer.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    if (!in_array(strtolower($metric), ['cosine', 'euclidean', 'dotproduct'], true)) {
        $metric = 'cosine';
    }

    $index_create_config = [
        'dimension' => $dimension,
        'metric' => strtolower($metric),
        'spec' => [
            'serverless' => [
                'cloud' => strtolower($spec_cloud),
                'region' => strtolower($spec_region),
            ]
        ]
    ];

    $create_result = $vector_store_manager->create_index_if_not_exists('Pinecone', $index_name, $index_create_config, $pinecone_config);
    if (is_wp_error($create_result)) {
        $handler_instance->send_wp_error($create_result);
        return;
    }

    if (is_array($create_result) && isset($create_result['name'])) {
        $vector_store_registry->add_registered_store('Pinecone', $create_result);
        wp_send_json_success(['index' => $create_result, 'message' => __('Pinecone index created/verified successfully.', 'gpt3-ai-content-generator')]);
    } else {
        $handler_instance->send_wp_error(new WP_Error('pinecone_create_malformed_response', __('Malformed response after Pinecone index creation.', 'gpt3-ai-content-generator')));
    }
}