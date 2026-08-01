<?php


namespace WPAICG\Chat\Core\Validation;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Shared validator for frontend chat image payloads.
 */
class ChatImageInputValidator
{
    private const MAX_IMAGE_SIZE_MB = 20;
    private const MAX_IMAGE_SIZE_BYTES = 20971520; // 20MB
    private const MAX_IMAGE_COUNT = 4;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Parses and validates image payloads received from frontend JSON.
     *
     * @param string|null $image_inputs_json Frontend JSON payload.
     * @return array|WP_Error|null Normalized image input array, WP_Error on invalid payload, or null when empty.
     */
    public static function parse_and_validate(?string $image_inputs_json)
    {
        if (empty($image_inputs_json)) {
            return null;
        }

        $decoded = json_decode($image_inputs_json, true);
        if (!is_array($decoded) || empty($decoded)) {
            return new WP_Error(
                'invalid_image_payload',
                __('Invalid image upload payload.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        if (count($decoded) > self::MAX_IMAGE_COUNT) {
            return new WP_Error(
                'too_many_images',
                sprintf(
                    /* translators: %d: max image count */
                    __('Too many images uploaded. Max images: %d.', 'gpt3-ai-content-generator'),
                    self::MAX_IMAGE_COUNT
                ),
                ['status' => 400]
            );
        }

        $normalized = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                return new WP_Error(
                    'invalid_image_payload',
                    __('Invalid image upload payload.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $mime_type = isset($item['mime_type']) ? strtolower(sanitize_text_field((string) $item['mime_type'])) : '';
            $base64_data = isset($item['base64_data']) ? trim((string) $item['base64_data']) : '';

            if ($mime_type === '' || $base64_data === '') {
                return new WP_Error(
                    'invalid_image_payload',
                    __('Invalid image upload payload.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            if (strncmp($base64_data, 'data:', strlen('data:')) === 0) {
                $parts = explode('base64,', $base64_data, 2);
                if (count($parts) === 2) {
                    $base64_data = $parts[1];
                }
            }

            if (!in_array($mime_type, self::ALLOWED_MIME_TYPES, true)) {
                return new WP_Error(
                    'invalid_image_type',
                    __('Invalid image type. Allowed: JPG, PNG, WEBP.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $base64_data = preg_replace('/\s+/', '', $base64_data);
            if (!is_string($base64_data) || $base64_data === '') {
                return new WP_Error(
                    'invalid_image_payload',
                    __('Invalid image data provided.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $decoded_binary = base64_decode($base64_data, true);
            if ($decoded_binary === false || $decoded_binary === '') {
                return new WP_Error(
                    'invalid_image_payload',
                    __('Invalid image data provided.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $binary_size = strlen($decoded_binary);
            if ($binary_size > self::MAX_IMAGE_SIZE_BYTES) {
                return new WP_Error(
                    'image_too_large',
                    sprintf(
                        /* translators: %d: max image size in MB */
                        __('Image is too large. Max size: %dMB.', 'gpt3-ai-content-generator'),
                        self::MAX_IMAGE_SIZE_MB
                    ),
                    ['status' => 413]
                );
            }

            $normalized[] = [
                'type' => $mime_type,
                'base64' => $base64_data,
                'field_id' => isset($item['field_id']) ? sanitize_key((string) $item['field_id']) : '',
                'filename' => isset($item['filename']) ? sanitize_file_name((string) $item['filename']) : '',
                'size' => $binary_size,
            ];
        }

        return $normalized;
    }
}
