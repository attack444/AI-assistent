<?php


namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for adding text content to an OpenAI Vector Store.
 * Called by AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler::ajax_add_text_to_vector_store_openai().
 *
 * @param AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_add_text_to_vector_store_openai_logic(AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance): void
{
    // Permission check already done by the handler calling this

    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;

    if (is_wp_error($wp_filesystem) || !$wp_filesystem) {
        $error = is_wp_error($wp_filesystem) ? $wp_filesystem : new WP_Error('filesystem_init_failed', __('Could not initialize the WordPress filesystem.', 'gpt3-ai-content-generator'));
        $handler_instance->send_wp_error($error, 500);
        return;
    }

    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready', __('Vector Store components not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $openai_config = $handler_instance->_get_openai_config();
    if (is_wp_error($openai_config)) {
        $handler_instance->send_wp_error($openai_config);
        return;
    }

    // Logic from old _aipkit_openai_vs_files_ajax_add_text_to_vector_store_openai_logic
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $target_store_id = isset($post_data['target_store_id']) ? sanitize_text_field($post_data['target_store_id']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $text_content = isset($post_data['text_content']) ? wp_kses_post(wp_unslash($post_data['text_content'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $source_type = isset($post_data['source_type']) ? sanitize_key($post_data['source_type']) : 'text_entry_global_form';

    if (empty($target_store_id) || $target_store_id === '_create_new_') {
        $handler_instance->send_wp_error(new WP_Error('no_target_store_text_direct', __('Please select an existing store for text content.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    if (empty($text_content)) {
        $handler_instance->send_wp_error(new WP_Error('no_text_content_direct', __('Text content cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $actual_store_id = $target_store_id;
    $final_store_name = '';
    $store_details = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
    if (!is_wp_error($store_details)) {
        $final_store_name = $store_details['name'] ?? $actual_store_id;
    }

    $temp_file_path_result = \WPAICG\Dashboard\Ajax\OpenAI\_aipkit_openai_vs_files_create_temp_file_from_string($text_content, 'text-content-');
    if (is_wp_error($temp_file_path_result)) {
        $handler_instance->send_wp_error($temp_file_path_result);
        return;
    }
    $temp_file_path = $temp_file_path_result;

    $strategy = AIPKit_Vector_Provider_Strategy_Factory::get_strategy('OpenAI');
    if (is_wp_error($strategy) || !method_exists($strategy, 'upload_file_for_vector_store')) {
        $wp_filesystem->delete($temp_file_path);
        $handler_instance->send_wp_error(new WP_Error('strategy_error_text_direct', __('File upload component not available for text content.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }
    $strategy->connect($openai_config);
    $upload_result = $strategy->upload_file_for_vector_store($temp_file_path, basename($temp_file_path), 'user_data');
    $wp_filesystem->delete($temp_file_path);

    if (is_wp_error($upload_result) || !isset($upload_result['id'])) {
        $err_msg = is_wp_error($upload_result) ? $upload_result->get_error_message() : 'Missing file ID in upload response for text.';
        $handler_instance->send_wp_error(new WP_Error('file_upload_failed_text_direct', 'Failed to upload text content as file: ' . $err_msg, ['status' => 500]));
        return;
    }
    $file_id_to_add = $upload_result['id'];

    $batch_result = $vector_store_manager->upsert_vectors('OpenAI', $actual_store_id, ['file_ids' => [$file_id_to_add]], $openai_config);
    if (is_wp_error($batch_result)) {
        $handler_instance->send_wp_error($batch_result);
        return;
    }

    \WPAICG\Dashboard\Ajax\OpenAI\_aipkit_openai_vs_files_log_vector_data_source_entry($wpdb, $data_source_table_name, [
        'vector_store_id' => $actual_store_id,
        'vector_store_name' => $final_store_name,
        'status' => 'indexed',
        'message' => 'Text content submitted for indexing.',
        'indexed_content' => $text_content,
        'file_id' => $file_id_to_add,
        'batch_id' => $batch_result['id'] ?? null,
        'source_type_for_log' => $source_type
    ]);

    $updated_store_data = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
    if (!is_wp_error($updated_store_data) && is_array($updated_store_data) && isset($updated_store_data['id'])) {
        $vector_store_registry->add_registered_store('OpenAI', $updated_store_data);
    }

    wp_send_json_success(['message' => __('Text content uploaded and added to vector store. Processing is asynchronous.', 'gpt3-ai-content-generator'), 'store_id' => $actual_store_id, 'batch' => $batch_result]);
}
