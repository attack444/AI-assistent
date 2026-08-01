<?php


namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler;
use WPAICG\aipkit_dashboard;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory;
use WPAICG\Includes\AIPKit_Upload_Utils;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for uploading a file and adding it directly to an OpenAI Vector Store.
 * Called by AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler::ajax_upload_and_add_file_to_store_direct_openai().
 *
 * @param AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_upload_and_add_file_to_store_direct_openai_logic(AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $handler_instance): void
{
    // Permission check already done by the handler calling this

    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();
    $wpdb = $handler_instance->get_wpdb();
    $data_source_table_name = $handler_instance->get_data_source_table_name();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready', __('Vector Store components not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    // --- Pro Check ---
    if (!aipkit_dashboard::is_pro_plan()) {
        $handler_instance->send_wp_error(new WP_Error('pro_feature_openai_upload_direct', __('Direct file upload and add to OpenAI store is a Pro feature. Please upgrade.', 'gpt3-ai-content-generator'), ['status' => 403]));
        return;
    }
    // --- End Pro Check ---

    $openai_config = $handler_instance->_get_openai_config();
    if (is_wp_error($openai_config)) {
        $handler_instance->send_wp_error($openai_config);
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in the calling handler method; superglobals are sanitized below.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in the calling handler method; superglobals are sanitized below.
    $files_data = wp_unslash($_FILES);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in the calling handler method; superglobals are sanitized below.
    $target_store_id = isset($post_data['target_store_id']) ? sanitize_text_field($post_data['target_store_id']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in the calling handler method; superglobals are sanitized below.
    $source_type = isset($post_data['source_type']) ? sanitize_key($post_data['source_type']) : 'file_upload_global_form';

    if (empty($target_store_id) || $target_store_id === '_create_new_') {
        $handler_instance->send_wp_error(new WP_Error('no_target_store_direct_lib', __('Please select an existing store for direct file upload.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    if (!isset($files_data['aipkit_vs_global_file_to_submit'])) {
        $handler_instance->send_wp_error(new WP_Error('no_file_sent_direct_lib', __('No file was sent for upload.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    if (!class_exists(\WPAICG\Includes\AIPKit_Upload_Utils::class)) {
        $handler_instance->send_wp_error(new WP_Error('upload_util_missing_lib', __('Upload utility is missing.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }
    $file = $files_data['aipkit_vs_global_file_to_submit'];
    $upload_limits = \WPAICG\Includes\AIPKit_Upload_Utils::get_effective_upload_limit_summary();
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $handler_instance->send_wp_error(new WP_Error('upload_error_direct_lib', __('Error during file upload: Code ', 'gpt3-ai-content-generator') . $file['error'], ['status' => 400]));
        return;
    }
    // Enforce OpenAI's 10MB cap per file, clamped by server limits
    $openai_cap_bytes = 10 * 1024 * 1024;
    $effective_limit_for_openai = min((int) ($upload_limits['limit_bytes'] ?? $openai_cap_bytes), $openai_cap_bytes);
    if ($file['size'] > $effective_limit_for_openai) {
        /* translators: %s: Formatted upload limit (e.g., "10 MB"). */
        $handler_instance->send_wp_error(new WP_Error('file_too_large_direct_lib', sprintf(__('File is too large (max %s).', 'gpt3-ai-content-generator'), size_format($effective_limit_for_openai)), ['status' => 400]));
        return;
    }

    $actual_store_id = $target_store_id;
    $final_store_name = '';
    $store_details = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
    if (!is_wp_error($store_details)) {
        $final_store_name = $store_details['name'] ?? $actual_store_id;
    }

    $strategy = AIPKit_Vector_Provider_Strategy_Factory::get_strategy('OpenAI');
    if (is_wp_error($strategy) || !method_exists($strategy, 'upload_file_for_vector_store')) {
        $handler_instance->send_wp_error(new WP_Error('strategy_error_direct_upload_lib', __('File upload component not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }
    $strategy->connect($openai_config);
    $upload_result = $strategy->upload_file_for_vector_store($file['tmp_name'], $file['name'], 'user_data');
    if (is_wp_error($upload_result) || !isset($upload_result['id'])) {
        $err_msg = is_wp_error($upload_result) ? $upload_result->get_error_message() : 'Missing file ID in upload response.';
        $handler_instance->send_wp_error(new WP_Error('file_upload_failed_direct_final_lib', 'Failed to upload file: ' . $err_msg, ['status' => 500]));
        return;
    }
    $file_id_to_add = $upload_result['id'];

    // --- FIX START: Conditionally read file content for logging ---
    $file_content_for_log = '';
    $file_type = wp_check_filetype($file['name']);
    $file_extension = $file_type['ext'] ?? '';

    if (in_array($file_extension, ['txt', 'md', 'json', 'html', 'css', 'js', 'php', 'py', 'c', 'cpp', 'java', 'cs', 'go', 'rb', 'sh', 'tex', 'ts', 'xml', 'log', 'csv', 'rtf'], true)) {
        // Only read content for known plain text formats
        $file_content_for_log = file_get_contents($file['tmp_name']);
    } else {
        // For binary files like PDF, DOCX, etc., store a placeholder instead of raw content.
        /* translators: 1: The file type (e.g., application/pdf), 2: The filename. */
        $file_content_for_log = sprintf(__('Binary file content (%1$s) not stored in local. Filename: %2$s', 'gpt3-ai-content-generator'), esc_html($file['type']), esc_html($file['name']));
    }
    // --- FIX END ---

    $batch_result = $vector_store_manager->upsert_vectors('OpenAI', $actual_store_id, ['file_ids' => [$file_id_to_add]], $openai_config);
    if (is_wp_error($batch_result)) {
        $handler_instance->send_wp_error($batch_result);
        return;
    }

    \WPAICG\Dashboard\Ajax\OpenAI\_aipkit_openai_vs_files_log_vector_data_source_entry($wpdb, $data_source_table_name, [
        'vector_store_id' => $actual_store_id,
        'vector_store_name' => $final_store_name,
        'status' => 'indexed',
        'message' => 'File content submitted for indexing. Original Filename: ' . sanitize_file_name($file['name']),
        'indexed_content' => $file_content_for_log ?: 'Content not available for logging',
        'post_title' => sanitize_file_name($file['name']),
        'file_id' => $file_id_to_add,
        'batch_id' => $batch_result['id'] ?? null,
        'source_type_for_log' => $source_type
    ]);

    $updated_store_data = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
    if (!is_wp_error($updated_store_data) && is_array($updated_store_data) && isset($updated_store_data['id'])) {
        $vector_store_registry->add_registered_store('OpenAI', $updated_store_data);
    }

    wp_send_json_success(['message' => __('File uploaded and added to vector store. Processing is asynchronous.', 'gpt3-ai-content-generator'), 'store_id' => $actual_store_id, 'batch' => $batch_result]);
}
