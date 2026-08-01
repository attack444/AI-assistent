<?php


namespace WPAICG\AIForms\Core\Ajax;

use WPAICG\AIForms\Core\AIPKit_AI_Form_Processor;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for uploading and parsing a file from an AI Form.
 * Called by AIPKit_AI_Form_Processor::ajax_upload_and_parse_file().
 *
 * @param AIPKit_AI_Form_Processor $processorInstance The instance of the processor class.
 * @return void
 */
function upload_and_parse_file_logic(AIPKit_AI_Form_Processor $processorInstance): void
{
    // 1. Security & Permission Checks
    check_ajax_referer('aipkit_ai_form_upload_nonce', '_ajax_nonce');

    if (!class_exists('\\WPAICG\\aipkit_dashboard') || !\WPAICG\aipkit_dashboard::is_pro_plan()) {
        wp_send_json_error(['message' => __('This is a Pro feature.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    // 2. File Validation
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is verified above.
    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => __('No file received.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: $_FILES is validated by AIPKit_Upload_Utils::validate_upload_file below.
    $file_data = $_FILES['file'];

    if (!class_exists('\\WPAICG\\Includes\\AIPKit_Upload_Utils')) {
        wp_send_json_error(['message' => __('File validation component is missing.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    // Using the same validation logic as vector store uploads (checks size and MIME)
    $allowed_mime_types = \WPAICG\Includes\AIPKit_Upload_Utils::get_vector_upload_allowed_mime_types();
    $validation_result = \WPAICG\Includes\AIPKit_Upload_Utils::validate_upload_file($file_data, $allowed_mime_types);

    if (is_wp_error($validation_result)) {
        wp_send_json_error(['message' => $validation_result->get_error_message()], 400);
        return;
    }

    // 3. File Parsing
    if (!class_exists('\\WPAICG\\Lib\\AIForms\\AIPKit_AI_Form_File_Parser')) {
        // The file parser class should be loaded by the pro loader
        wp_send_json_error(['message' => __('File parsing component is unavailable.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    $parse_result = \WPAICG\Lib\AIForms\AIPKit_AI_Form_File_Parser::parse_file($file_data);

    if (is_wp_error($parse_result)) {
        wp_send_json_error(['message' => $parse_result->get_error_message()], 500);
        return;
    }

    // 4. Success Response
    wp_send_json_success(['text' => $parse_result]);
}
