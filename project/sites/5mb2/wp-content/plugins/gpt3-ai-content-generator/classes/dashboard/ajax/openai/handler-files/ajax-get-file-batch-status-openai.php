<?php


namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for fetching an OpenAI Vector Store batch status.
 * Called by AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler::ajax_get_openai_file_batch_status().
 *
 * @param AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_get_openai_file_batch_status_logic(AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance): void
{
    $openai_config = $handler_instance->_get_openai_config();
    if (is_wp_error($openai_config)) {
        $handler_instance->send_wp_error($openai_config);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $store_id = isset($post_data['store_id']) ? sanitize_text_field($post_data['store_id']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $batch_id = isset($post_data['batch_id']) ? sanitize_text_field($post_data['batch_id']) : '';

    if (empty($store_id) || empty($batch_id)) {
        $handler_instance->send_wp_error(new WP_Error('missing_batch_id', __('Vector Store ID and Batch ID are required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    if (!class_exists(AIPKit_Vector_Provider_Strategy_Factory::class)) {
        $factory_path = WPAICG_PLUGIN_DIR . 'classes/vector/class-aipkit-vector-provider-strategy-factory.php';
        if (file_exists($factory_path)) {
            require_once $factory_path;
        }
    }

    $strategy = AIPKit_Vector_Provider_Strategy_Factory::get_strategy('OpenAI');
    if (is_wp_error($strategy) || !method_exists($strategy, 'retrieve_file_batch')) {
        $handler_instance->send_wp_error(new WP_Error('openai_strategy_missing', __('OpenAI vector strategy not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $strategy->connect($openai_config);
    $batch = $strategy->retrieve_file_batch($store_id, $batch_id);
    if (is_wp_error($batch)) {
        $handler_instance->send_wp_error($batch);
        return;
    }

    $status = '';
    if (is_array($batch) && isset($batch['status'])) {
        $status = sanitize_key($batch['status']);
    }

    wp_send_json_success([
        'status' => $status,
        'batch' => $batch,
    ]);
}
