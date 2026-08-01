<?php

namespace WPAICG\Images\Manager\Utils;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate and normalize the source image upload for image-edit mode.
 *
 * @param array $files_data Raw $_FILES data.
 * @return array|WP_Error Normalized image payload or WP_Error on invalid input.
 */
function parse_edit_source_image_upload_logic(array $files_data)
{
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size_bytes = 10 * 1024 * 1024; // 10MB
    $allowed_extensions_label = 'JPG, PNG, WEBP, GIF';

    if (!isset($files_data['source_image']) || !is_array($files_data['source_image'])) {
        return new WP_Error(
            'missing_source_image',
            __('Please upload an image to edit.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }

    $file = $files_data['source_image'];
    if (isset($file['name']) && is_array($file['name'])) {
        return new WP_Error(
            'multiple_source_images_not_supported',
            __('Only one source image is supported in this version.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code !== UPLOAD_ERR_OK) {
        $error_message = __('Source image upload failed. Please try again.', 'gpt3-ai-content-generator');
        $status_code = 400;
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            $error_message = __('Please upload an image to edit.', 'gpt3-ai-content-generator');
        } elseif ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) {
            $error_message = __('Source image is too large. Maximum allowed size is 10MB.', 'gpt3-ai-content-generator');
            $status_code = 413;
        }
        return new WP_Error(
            'source_image_upload_failed',
            $error_message,
            ['status' => $status_code]
        );
    }

    $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    if ($tmp_name === '' || !is_readable($tmp_name)) {
        return new WP_Error(
            'source_image_upload_failed',
            __('Source image upload failed. Please try again.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }

    $reported_size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($reported_size <= 0) {
        return new WP_Error(
            'invalid_source_image',
            __('Invalid source image file.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }
    if ($reported_size > $max_size_bytes) {
        return new WP_Error(
            'source_image_too_large',
            __('Source image is too large. Maximum allowed size is 10MB.', 'gpt3-ai-content-generator'),
            ['status' => 413]
        );
    }

    $file_name = sanitize_file_name((string) ($file['name'] ?? 'image'));
    $file_info = wp_check_filetype_and_ext($tmp_name, $file_name);
    $mime_type = isset($file_info['type']) ? strtolower((string) $file_info['type']) : '';

    if ($mime_type === '' && function_exists('mime_content_type')) {
        $detected_mime = mime_content_type($tmp_name);
        if (is_string($detected_mime) && $detected_mime !== '') {
            $mime_type = strtolower($detected_mime);
        }
    }

    if (!in_array($mime_type, $allowed_mime_types, true)) {
        return new WP_Error(
            'invalid_source_image_type',
            sprintf(
                /* translators: %s: Comma-separated list of allowed file extensions. */
                __('Invalid image type. Allowed types: %s.', 'gpt3-ai-content-generator'),
                $allowed_extensions_label
            ),
            ['status' => 400]
        );
    }

    $binary = file_get_contents($tmp_name);
    if (!is_string($binary) || $binary === '') {
        return new WP_Error(
            'invalid_source_image',
            __('Invalid source image file.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }

    $actual_size = strlen($binary);
    if ($actual_size <= 0) {
        return new WP_Error(
            'invalid_source_image',
            __('Invalid source image file.', 'gpt3-ai-content-generator'),
            ['status' => 400]
        );
    }
    if ($actual_size > $max_size_bytes) {
        return new WP_Error(
            'source_image_too_large',
            __('Source image is too large. Maximum allowed size is 10MB.', 'gpt3-ai-content-generator'),
            ['status' => 413]
        );
    }

    return [
        'mime_type' => $mime_type,
        'base64_data' => base64_encode($binary),
        'size_bytes' => $actual_size,
        'file_name' => $file_name,
    ];
}
