<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\Includes\AIPKit_Upload_Utils;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Content_Writer_Parse_Csv_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    public function handle()
    {
        // Manual permission check for either 'content-writer' or 'autogpt'
        if (
            !\WPAICG\AIPKit_Role_Manager::user_can_access_module('content-writer') &&
            !\WPAICG\AIPKit_Role_Manager::user_can_access_module('autogpt')
        ) {
            $this->send_wp_error(new WP_Error('permission_denied', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }

        // Manual nonce check for either page's nonce
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce is verified directly with wp_verify_nonce().
        $nonce = $_POST['_ajax_nonce'] ?? '';
        if (
            !wp_verify_nonce($nonce, 'aipkit_content_writer_nonce') &&
            !wp_verify_nonce($nonce, 'aipkit_automated_tasks_manage_nonce')
        ) {
            $this->send_wp_error(new WP_Error('nonce_failure', __('Security check failed.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }

        // --- Task 2.2: File Validation ---
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES data is validated by AIPKit_Upload_Utils::validate_upload_file().
        if (!isset($_FILES['file'])) {
            $this->send_wp_error(new WP_Error('no_file_received', __('No CSV file was received.', 'gpt3-ai-content-generator')), 400);
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES data is validated by AIPKit_Upload_Utils::validate_upload_file().
        $file_data = $_FILES['file'];

        if (!class_exists(AIPKit_Upload_Utils::class)) {
            $this->send_wp_error(new WP_Error('internal_error', __('File validation component is missing.', 'gpt3-ai-content-generator')), 500);
            return;
        }

        $allowed_mime_types = AIPKit_Upload_Utils::get_content_writer_allowed_mime_types();
        // Use the general validation function, passing our specific MIME types
        $validation_result = AIPKit_Upload_Utils::validate_upload_file($file_data, $allowed_mime_types);

        if (is_wp_error($validation_result)) {
            $this->send_wp_error($validation_result);
            return;
        }

        // --- Task 2.3: CSV Parsing Logic ---
        $csv_file_path = $file_data['tmp_name'];
        $formatted_data = '';
        $tasks_found = 0;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading from a temporary uploaded file is a standard and safe use case for these functions.
        if (($handle = fopen($csv_file_path, "r")) !== false) {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Convert row array to pipe-separated string
                $formatted_data .= implode('|', $row) . "\n";
                $tasks_found++;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading from a temporary uploaded file is a standard and safe use case for these functions.
            fclose($handle);
        } else {
            $this->send_wp_error(new WP_Error('csv_read_error', __('Could not open the uploaded CSV file.', 'gpt3-ai-content-generator')), 500);
            return;
        }

        wp_send_json_success([
            'tasks_found' => $tasks_found,
            'formatted_data' => trim($formatted_data)
        ]);
    }
}
