<?php

namespace WPAICG\Images\Providers;

use WPAICG\AIPKit_Providers;
use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Images\Providers\OpenAI\OpenAIImageUrlBuilder;
use WPAICG\Images\Providers\OpenAI\OpenAIPayloadFormatter;
use WPAICG\Images\Providers\OpenAI\OpenAIImageResponseParser;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenAI Image Generation Provider Strategy.
 * Implements generation and edit flows using OpenAI Images API.
 * Delegates logic to specialized component classes.
 */
class AIPKit_Image_OpenAI_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy {

    /**
     * Generate an image based on a text prompt using OpenAI image models.
     *
     * @param string $prompt The text prompt describing the image.
     * @param array $api_params API connection parameters ('api_key', 'base_url', 'api_version').
     * @param array $options Generation options merged with defaults ('model', 'n', 'size', 'quality', 'response_format', 'style', 'user', etc.).
     * @return array|WP_Error Array containing 'images' => [['url'=>..., 'b64_json'=>..., 'revised_prompt'=>...], ...], 'usage' => array|null or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = []) {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) return new WP_Error('openai_image_missing_key', __('OpenAI API Key is required for image generation.', 'gpt3-ai-content-generator'));
        if (empty($prompt)) return new WP_Error('openai_image_missing_prompt', __('Prompt cannot be empty for image generation.', 'gpt3-ai-content-generator'));
        $image_mode = isset($options['image_mode']) && $options['image_mode'] === 'edit' ? 'edit' : 'generate';

        // Ensure component classes are loaded (they should be by constructor, but defensive check)
        if (!class_exists(OpenAIImageUrlBuilder::class) || !class_exists(OpenAIPayloadFormatter::class) || !class_exists(OpenAIImageResponseParser::class)) {
            return new WP_Error('openai_image_dependency_missing', __('OpenAI image generation components are missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // --- Build URL using image-specific builder ---
        $url_builder_params = [
            'base_url' => $api_params['base_url'] ?? 'https://api.openai.com',
            'api_version' => $api_params['api_version'] ?? 'v1',
        ];
        $operation = $image_mode === 'edit' ? 'images/edits' : 'images/generations';
        $url = OpenAIImageUrlBuilder::build($operation, $url_builder_params);
        if (is_wp_error($url)) return $url;

        $headers_array = $this->get_api_headers($api_key, $image_mode);
        $request_options = $this->get_request_options($image_mode);
        $selected_model = isset($options['model']) ? (string) $options['model'] : AIPKit_Providers::get_default_openai_image_model();
        if (AIPKit_Providers::is_openai_gpt_image_model($selected_model)) {
            $request_options['timeout'] = max((int) ($request_options['timeout'] ?? 120), 240);
        }

        if ($image_mode === 'edit') {
            if (empty($options['source_image']) || !is_array($options['source_image'])) {
                return new WP_Error(
                    'openai_edit_missing_source_image',
                    __('Source image is required for OpenAI edit mode.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }
            $edit_model = AIPKit_Providers::normalize_openai_image_model(
                isset($options['model']) && is_string($options['model']) ? $options['model'] : null
            );
            $options['model'] = $edit_model;
            if (!OpenAIPayloadFormatter::supports_edit_model($edit_model)) {
                return new WP_Error(
                    'openai_edit_model_not_supported',
                    __('Selected OpenAI model does not support image editing.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $multipart_data = OpenAIPayloadFormatter::format_edit_multipart($prompt, $options);
            if (is_wp_error($multipart_data)) {
                return $multipart_data;
            }

            $headers_array['Content-Type'] = $multipart_data['content_type'];
            $request_args = array_merge($request_options, [
                'headers' => $headers_array,
                'body' => $multipart_data['body'],
                'data_format' => 'body',
            ]);
        } else {
            // --- Build Payload using image-specific formatter ---
            $payload = OpenAIPayloadFormatter::format($prompt, $options);
            // --- End Build Payload ---

            $request_body_json = wp_json_encode($payload);
            $request_args = array_merge($request_options, [
                'headers' => $headers_array,
                'body' => $request_body_json,
                'data_format' => 'body',
            ]);
        }

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('openai_image_http_error', __('HTTP error during image generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $decoded_response = $this->decode_json($body, 'OpenAI Image Generation'); // Uses base class helper

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_msg = is_wp_error($decoded_response)
                        ? $decoded_response->get_error_message()
                        : OpenAIImageResponseParser::parse_error($body, $status_code); // Use image-specific error parser

            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('openai_image_api_error', sprintf(__('OpenAI Image API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        // --- Parse response using image-specific parser ---
        $parsed_data = OpenAIImageResponseParser::parse(
            $decoded_response,
            isset($options['model']) ? (string) $options['model'] : AIPKit_Providers::get_default_openai_image_model(),
            $prompt
        );
        if (!isset($parsed_data['images']) || !is_array($parsed_data['images'])) { // Check if 'images' key exists and is an array

            return new WP_Error('openai_image_no_data_parsed', __('OpenAI API returned success but image data structure is invalid.', 'gpt3-ai-content-generator'));
        }
        // --- End Parse ---

        return $parsed_data; // Returns ['images' => [...], 'usage' => ...]
    }

    /**
     * Get the supported image sizes for OpenAI image generation.
     */
    public function get_supported_sizes(): array {
        return ['1024x1024', '1792x1024', '1024x1792', '1536x1024', '1024x1536', '512x512', '256x256'];
    }

    /**
     * Get API headers required for OpenAI Image requests.
     */
    public function get_api_headers(string $api_key, string $operation): array {
         $headers = ['Authorization' => 'Bearer ' . $api_key];
         if ($operation !== 'edit') {
             $headers['Content-Type'] = 'application/json';
         }
         return $headers;
    }
}
