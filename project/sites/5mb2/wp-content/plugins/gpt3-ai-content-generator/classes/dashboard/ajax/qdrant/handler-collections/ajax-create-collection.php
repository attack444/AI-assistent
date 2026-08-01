<?php


namespace WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Qdrant_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for creating a Qdrant collection.
 * Called by AIPKit_Vector_Store_Qdrant_Ajax_Handler::ajax_create_collection_qdrant().
 *
 * @param AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance
 * @return void
 */
function _aipkit_qdrant_ajax_create_collection_logic(AIPKit_Vector_Store_Qdrant_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $error_message = __('Vector Store components not available for Qdrant Create.', 'gpt3-ai-content-generator');
        // Ensure $handler_instance is valid before calling send_wp_error
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error(new WP_Error('manager_not_ready_create_qdrant', $error_message, ['status' => 500]));
        } else {
            wp_send_json_error(['message' => $error_message], 500); // Fallback if handler is invalid
        }
        return;
    }

    $qdrant_config = $handler_instance->_get_qdrant_config();
    if (is_wp_error($qdrant_config)) {
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error($qdrant_config);
        } else {
            wp_send_json_error(['message' => $qdrant_config->get_error_message()], 400); // Fallback
        }
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    $collection_name = isset($post_data['name']) ? sanitize_text_field($post_data['name']) : '';
    $dimension = isset($post_data['dimension']) ? absint($post_data['dimension']) : 0;
    $metric = isset($post_data['metric']) ? sanitize_text_field($post_data['metric']) : 'Cosine'; // Default metric

    if (empty($collection_name)) {
        $error_message = __('Collection name is required.', 'gpt3-ai-content-generator');
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error(new WP_Error('missing_name_qdrant_create', $error_message, ['status' => 400]));
        } else {
            wp_send_json_error(['message' => $error_message], 400);
        }
        return;
    }
    if ($dimension <= 0) {
        $error_message = __('Vector dimension must be a positive integer.', 'gpt3-ai-content-generator');
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error(new WP_Error('invalid_dimension_qdrant_create', $error_message, ['status' => 400]));
        } else {
            wp_send_json_error(['message' => $error_message], 400);
        }
        return;
    }
    if (!in_array(ucfirst(strtolower($metric)), ['Cosine', 'Euclid', 'Dot'])) { // Validate metric
        $metric = 'Cosine';
    }

    $index_config = ['dimension' => $dimension, 'metric' => $metric];

    $create_result = $vector_store_manager->create_index_if_not_exists('Qdrant', $collection_name, $index_config, $qdrant_config);

    if (is_wp_error($create_result)) {
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error($create_result);
        } else {
            wp_send_json_error(['message' => $create_result->get_error_message()], 500);
        }
        return;
    }

    // Check for a successful Qdrant collection description structure
    if (is_array($create_result) && isset($create_result['status']) && in_array($create_result['status'], ['green', 'yellow', 'red']) && isset($create_result['config'])) {
        // --- FIX: Save the full details object to the registry ---
        $vector_store_registry->add_registered_store('Qdrant', $create_result);
        // --- END FIX ---
        $log_message = __('Qdrant collection created/verified.', 'gpt3-ai-content-generator');
        wp_send_json_success(['collection' => $create_result, 'message' => $log_message]);
    } else {
        $log_message = __('Qdrant collection creation response was malformed.', 'gpt3-ai-content-generator');
        if ($handler_instance && method_exists($handler_instance, 'send_wp_error')) {
            $handler_instance->send_wp_error(new WP_Error('qdrant_create_malformed_response', $log_message));
        } else {
            wp_send_json_error(['message' => $log_message], 500);
        }
    }
}