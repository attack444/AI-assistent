<?php
// REVISED FILE

namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Images\Providers\Google\GoogleImageUrlBuilder;
use WPAICG\Images\Providers\Google\GoogleImagePayloadFormatter;
use WPAICG\Images\Providers\Google\GoogleImageResponseParser;
use WPAICG\Images\Providers\Google\GoogleImageTokenCounter;
use WPAICG\Images\Providers\Google\GoogleVideoUrlBuilder;
use WPAICG\Images\Providers\Google\GoogleVideoPayloadFormatter;
use WPAICG\Images\Providers\Google\GoogleVideoResponseParser;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Google Image and Video Generation Provider Strategy.
 * Supports Gemini native image and Imagen models for images, and Veo models for videos.
 */
class AIPKit_Image_Google_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy {
    private const GEMINI_FALLBACK_OUTPUT_TOKENS_PER_IMAGE = 2000;

    /**
     * Generate an image or video based on a text prompt using Google's services.
     *
     * @param string $prompt The text prompt describing the image or video.
     * @param array $api_params API connection parameters. Must include 'api_key'.
     *                          Optional: 'base_url', 'api_version'.
     * @param array $options Generation options. Must include 'model'.
     *                       Optional: 'n', 'size' (interpreted based on model), 'aspect_ratio', 'negative_prompt', etc.
     * @return array|WP_Error Array containing 'images'/'videos' and 'usage' or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = []) {
        $api_key = $api_params['api_key'] ?? null;
        $model_id = $options['model'] ?? null; // Full model ID like 'gemini-3.1-flash-image-preview' or 'veo-3.0-generate-preview'
        $image_mode = isset($options['image_mode']) && $options['image_mode'] === 'edit' ? 'edit' : 'generate';

        if (empty($api_key)) return new WP_Error('google_missing_key', __('Google API Key is required for generation.', 'gpt3-ai-content-generator'));
        if (empty($model_id)) return new WP_Error('google_missing_model', __('Google model ID is required.', 'gpt3-ai-content-generator'));
        if (empty($prompt)) return new WP_Error('google_missing_prompt', __('Prompt cannot be empty for generation.', 'gpt3-ai-content-generator'));

        if ($image_mode === 'edit') {
            if ($this->is_video_model($model_id)) {
                return new WP_Error(
                    'google_video_model_not_supported_for_edit',
                    __('Selected Google video model does not support image editing.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }
            if (!$this->supports_image_editing_model((string) $model_id)) {
                return new WP_Error(
                    'google_model_not_supported_for_edit',
                    __('Selected Google model does not support image editing.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }
            $source_image = $options['source_image'] ?? null;
            if (!is_array($source_image) || empty($source_image['mime_type']) || empty($source_image['base64_data'])) {
                return new WP_Error(
                    'missing_source_image_for_edit',
                    __('Source image is required for edit mode.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }
        }

        // Check if this is a video model and route accordingly
        if ($this->is_video_model($model_id)) {
            return $this->generate_video($prompt, $api_params, $options);
        }

        // Ensure component classes are loaded (they should be by constructor, but defensive check)
        if (!class_exists(GoogleImageUrlBuilder::class) || !class_exists(GoogleImagePayloadFormatter::class) || !class_exists(GoogleImageResponseParser::class)) {
            return new WP_Error('google_image_dependency_missing', __('Google image generation components are missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // Pass the full model ID to the URL builder
        $url = GoogleImageUrlBuilder::build($model_id, $api_params);
        if (is_wp_error($url)) return $url;

        // Options already contain the model ID which the formatter will use to switch logic
        $payload = GoogleImagePayloadFormatter::format($prompt, $options);
        if (empty($payload)) { // Formatter might return empty for unsupported models
            return new WP_Error('google_image_payload_error', __('Failed to format payload for Google image model: ', 'gpt3-ai-content-generator') . $model_id);
        }

        $headers_array = $this->get_api_headers($api_key, 'generate');
        $request_options_base = $this->get_request_options('generate');
        $request_body_json = wp_json_encode($payload);

        $request_args = array_merge($request_options_base, [
            'headers' => $headers_array,
            'body' => $request_body_json,
            'data_format' => 'body', // wp_remote_request handles JSON encoding if body is array
        ]);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('google_image_http_error', __('HTTP error during Google image generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $decoded_response = $this->decode_json($body, 'Google Image Generation');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_msg = is_wp_error($decoded_response)
                        ? $decoded_response->get_error_message()
                        : GoogleImageResponseParser::parse_error($body, $status_code);
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('google_image_api_error', sprintf(__('Google Image API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        $parse_result = GoogleImageResponseParser::parse($decoded_response, $model_id);
        if (is_wp_error($parse_result)) {
            return $parse_result;
        }

        $usage = isset($parse_result['usage']) && is_array($parse_result['usage'])
            ? $parse_result['usage']
            : null;
        $has_total_usage = is_array($usage) && !empty($usage['total_tokens']);

        if ($image_mode === 'edit' && strpos($model_id, 'gemini') !== false && !$has_total_usage) {
            $parts = GoogleImagePayloadFormatter::build_gemini_parts($prompt, $options);
            $prompt_tokens = GoogleImageTokenCounter::count_prompt_tokens((string) $model_id, $api_params, $parts);
            if (!is_wp_error($prompt_tokens)) {
                $images_generated = isset($parse_result['images']) && is_array($parse_result['images'])
                    ? count($parse_result['images'])
                    : 0;
                $estimated_output_tokens = $images_generated * self::GEMINI_FALLBACK_OUTPUT_TOKENS_PER_IMAGE;
                $parse_result['usage'] = [
                    'input_tokens' => $prompt_tokens,
                    'output_tokens' => $estimated_output_tokens,
                    'total_tokens' => $prompt_tokens + $estimated_output_tokens,
                    'provider_raw' => [
                        'source' => 'google_count_tokens_fallback',
                        'prompt_tokens' => $prompt_tokens,
                        'images_generated' => $images_generated,
                        'estimated_output_tokens_per_image' => self::GEMINI_FALLBACK_OUTPUT_TOKENS_PER_IMAGE,
                    ],
                ];
            }
        }

        return $parse_result;
    }

    /**
     * Check if the given model ID is a video model.
     *
     * @param string $model_id The model ID to check.
     * @return bool True if it's a video model, false otherwise.
     */
    private function is_video_model(string $model_id): bool {
        // Prefer synced list of Google Video models; fallback to heuristic
        if (class_exists('\\WPAICG\\AIPKit_Providers')) {
            $video_models = \WPAICG\AIPKit_Providers::get_google_video_models();
            if (is_array($video_models) && !empty($video_models)) {
                $ids = array_map(function($m){ return is_array($m) ? ($m['id'] ?? '') : (is_string($m)? $m : ''); }, $video_models);
                if (in_array($model_id, $ids, true)) {
                    return true;
                }
            }
        }
        return strpos($model_id, 'veo') !== false;
    }

    /**
     * Check whether a model supports image edit flow in this implementation.
     *
     * @param string $model_id Model identifier.
     * @return bool
     */
    private function supports_image_editing_model(string $model_id): bool {
        $model_id = strtolower($model_id);
        return strpos($model_id, 'gemini') !== false
            && (
                strpos($model_id, 'image-generation') !== false
                || strpos($model_id, 'flash-image') !== false
                || strpos($model_id, 'pro-image') !== false
            );
    }

    /**
     * Generate a video using Google's Veo 3 service.
     *
     * @param string $prompt The text prompt describing the video.
     * @param array $api_params API connection parameters.
     * @param array $options Generation options.
     * @return array|WP_Error Array containing 'videos' and 'usage' or WP_Error on failure.
     */
    private function generate_video(string $prompt, array $api_params, array $options) {
        
        $model_id = $options['model'] ?? null;

        // Ensure video component classes are loaded
        if (!class_exists(GoogleVideoUrlBuilder::class) || !class_exists(GoogleVideoPayloadFormatter::class) || !class_exists(GoogleVideoResponseParser::class)) {
            return new WP_Error('google_video_dependency_missing', __('Google video generation components are missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // Build URL for video generation
        $url = GoogleVideoUrlBuilder::build($model_id, $api_params, 'generate');
        if (is_wp_error($url)) {
            return $url;
        }

        // Format payload for video generation
        $payload = GoogleVideoPayloadFormatter::format($prompt, $options);
        if (empty($payload)) {
            return new WP_Error('google_video_payload_error', __('Failed to format payload for Google video model: ', 'gpt3-ai-content-generator') . $model_id);
        }

        $headers_array = $this->get_api_headers($api_params['api_key'] ?? '', 'generate');
        $request_options_base = $this->get_request_options('generate');
        $request_body_json = wp_json_encode($payload);

        $request_args = array_merge($request_options_base, [
            'headers' => $headers_array,
            'body' => $request_body_json,
            'data_format' => 'body',
            'timeout' => 120, // Longer timeout for video generation
        ]);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('google_video_http_error', __('HTTP error during Google video generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $decoded_response = $this->decode_json($body, 'Google Video Generation');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_msg = is_wp_error($decoded_response)
                        ? $decoded_response->get_error_message()
                        : GoogleVideoResponseParser::parse_error($body, $status_code);
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('google_video_api_error', sprintf(__('Google Video API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        // Parse the response - now returns operation info for async polling
        $parse_result = GoogleVideoResponseParser::parse($decoded_response, $model_id, $api_params);
        
        if (is_wp_error($parse_result)) {
            return $parse_result;
        }
        
        // Check if this is an async operation or completed result
        if (isset($parse_result['status']) && $parse_result['status'] === 'processing') {
            return [
                'status' => 'processing',
                'operation_name' => $parse_result['operation_name'],
                'message' => $parse_result['message']
            ];
        } else {
            return $parse_result;
        }
    }

    /**
     * Get the supported image sizes (Placeholder - needs specific model logic).
     */
    public function get_supported_sizes(): array {
        // For shortcode UI, a common list. Strategy should validate/adapt.
        return ['1024x1024', '1536x1024', '1024x1536', '1024x768', '768x1024'];
    }

    /**
     * Get API headers (Google API key is in URL).
     */
    public function get_api_headers(string $api_key, string $operation): array {
         return ['Content-Type' => 'application/json'];
    }
}
