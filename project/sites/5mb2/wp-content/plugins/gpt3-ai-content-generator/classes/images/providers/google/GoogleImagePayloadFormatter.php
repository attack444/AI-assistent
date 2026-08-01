<?php

namespace WPAICG\Images\Providers\Google;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles formatting request payloads for Google Image Generation models.
 */
class GoogleImagePayloadFormatter {
    private const IMAGEN_ASPECT_RATIOS = ['', '1:1', '3:4', '4:3', '9:16', '16:9'];
    private const GEMINI_ASPECT_RATIOS = ['', '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'];
    private const GEMINI_31_ASPECT_RATIOS = ['', '1:1', '1:4', '1:8', '2:3', '3:2', '3:4', '4:1', '4:3', '4:5', '5:4', '8:1', '9:16', '16:9', '21:9'];
    private const IMAGEN_IMAGE_SIZES = ['', '1K', '2K'];
    private const GEMINI_31_IMAGE_SIZES = ['', '512', '1K', '2K', '4K'];
    private const GEMINI_3_PRO_IMAGE_SIZES = ['', '1K', '2K', '4K'];

    /**
     * Build Gemini parts payload for prompt-only or edit-mode requests.
     *
     * @param string $prompt User prompt text.
     * @param array  $options Request options.
     * @return array<int, array<string, mixed>>
     */
    public static function build_gemini_parts(string $prompt, array $options): array {
        $image_mode = isset($options['image_mode']) && $options['image_mode'] === 'edit' ? 'edit' : 'generate';
        $parts = [['text' => $prompt]];

        $source_image = isset($options['source_image']) && is_array($options['source_image'])
            ? $options['source_image']
            : null;

        if (
            $image_mode === 'edit' &&
            is_array($source_image) &&
            !empty($source_image['mime_type']) &&
            !empty($source_image['base64_data'])
        ) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => sanitize_text_field((string) $source_image['mime_type']),
                    'data' => preg_replace('/\s+/', '', (string) $source_image['base64_data']),
                ],
            ];
        }

        return $parts;
    }

    /**
     * Formats the payload for Google Image Generation API.
     *
     * @param string $prompt The text prompt.
     * @param array  $options Generation options including 'model' (full ID), 'n', 'size', etc.
     * @return array The formatted request body data.
     */
    public static function format(string $prompt, array $options): array {
        $model_id = $options['model'] ?? '';
        $payload = [];

        $n = isset($options['n']) ? max(1, (int)$options['n']) : 1;

        // Gemini image-generation models use the text+image modality on generateContent
        if (self::is_gemini_image_model((string) $model_id)) {
            $parts = self::build_gemini_parts($prompt, $options);

            $payload = [
                'contents' => [[
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ];

            if (self::supports_gemini_response_format((string) $model_id)) {
                $response_format = self::build_gemini_response_format((string) $model_id, $options);
                if (!empty($response_format)) {
                    $payload['generationConfig']['responseFormat'] = $response_format;
                }
            }
        }
        // Imagen models use the :predict endpoint with instances/parameters
        elseif (strpos($model_id, 'imagen') !== false) {
            $parameters = [
                'sampleCount' => self::get_imagen_sample_count((string) $model_id, $n),
            ];

            $aspect_ratio = self::sanitize_choice($options['aspect_ratio'] ?? '', self::IMAGEN_ASPECT_RATIOS);
            if ($aspect_ratio !== '') {
                $parameters['aspectRatio'] = $aspect_ratio;
            }

            $image_size = self::sanitize_image_size($options['image_size'] ?? '', self::IMAGEN_IMAGE_SIZES);
            if ($image_size !== '') {
                $parameters['imageSize'] = $image_size;
            }

            $person_generation = self::sanitize_choice(
                $options['person_generation'] ?? '',
                ['', 'dont_allow', 'allow_adult', 'allow_all']
            );
            if ($person_generation !== '') {
                $parameters['personGeneration'] = $person_generation;
            }

            $payload = [
                'instances' => [ ['prompt' => $prompt] ],
                'parameters' => $parameters,
            ];
        }

        return $payload;
    }

    private static function is_gemini_image_model(string $model_id): bool {
        $model_id = strtolower($model_id);

        return strpos($model_id, 'gemini') !== false
            && (
                strpos($model_id, 'image-generation') !== false
                || strpos($model_id, 'flash-image') !== false
                || strpos($model_id, 'pro-image') !== false
            );
    }

    private static function supports_gemini_response_format(string $model_id): bool {
        $model_id = strtolower($model_id);

        return strpos($model_id, 'gemini-2.5-flash-image') !== false
            || (
                strpos($model_id, 'gemini-3') !== false
                && (
                    strpos($model_id, 'flash-image') !== false
                    || strpos($model_id, 'pro-image') !== false
                )
            );
    }

    private static function build_gemini_response_format(string $model_id, array $options): array {
        $image_config = [];
        $aspect_ratio = self::sanitize_choice(
            $options['aspect_ratio'] ?? '',
            self::get_gemini_aspect_ratios($model_id)
        );
        if ($aspect_ratio !== '') {
            $image_config['aspectRatio'] = $aspect_ratio;
        }

        $image_size = self::sanitize_image_size(
            $options['image_size'] ?? '',
            self::get_gemini_image_sizes($model_id)
        );
        if ($image_size !== '') {
            $image_config['imageSize'] = $image_size;
        }

        return empty($image_config) ? [] : ['image' => $image_config];
    }

    private static function get_gemini_aspect_ratios(string $model_id): array {
        $model_id = strtolower($model_id);

        return strpos($model_id, 'gemini-3.1-flash-image') !== false
            ? self::GEMINI_31_ASPECT_RATIOS
            : self::GEMINI_ASPECT_RATIOS;
    }

    private static function get_gemini_image_sizes(string $model_id): array {
        $model_id = strtolower($model_id);
        if (strpos($model_id, 'gemini-3.1-flash-image') !== false) {
            return self::GEMINI_31_IMAGE_SIZES;
        }
        if (strpos($model_id, 'gemini-3-pro-image') !== false) {
            return self::GEMINI_3_PRO_IMAGE_SIZES;
        }

        return [''];
    }

    private static function get_imagen_sample_count(string $model_id, int $requested_count): int {
        $max = strpos(strtolower($model_id), 'ultra') !== false ? 1 : 4;

        return max(1, min($requested_count, $max));
    }

    private static function sanitize_choice($value, array $allowed): string {
        $value = is_scalar($value) ? sanitize_text_field((string) $value) : '';

        return in_array($value, $allowed, true) ? $value : '';
    }

    private static function sanitize_image_size($value, array $allowed): string {
        $value = is_scalar($value) ? strtoupper(sanitize_text_field((string) $value)) : '';
        if ($value === '512') {
            return in_array('512', $allowed, true) ? '512' : '';
        }

        return in_array($value, $allowed, true) ? $value : '';
    }
}
