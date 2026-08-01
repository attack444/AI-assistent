<?php

namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Azure Image Generation Provider Strategy.
 * Implements generation using Azure OpenAI image deployments.
 */
class AIPKit_Image_Azure_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    /**
     * Build the full API endpoint URL for Azure image generation.
     *
     * @param string $operation Expected to be 'images/generations'.
     * @param array  $params Required parameters ('azure_endpoint', 'deployment', 'api_version_images').
     * @return string|WP_Error The full URL or WP_Error.
     */
    private function build_api_url(string $operation, array $params)
    {
        $endpoint = $params['azure_endpoint'] ?? '';
        $deployment_name = $params['deployment'] ?? '';
        $api_version = $params['api_version_images'] ?? '2025-04-01-preview';
        if ($api_version === '' || $api_version === '2024-04-01-preview') {
            $api_version = '2025-04-01-preview';
        }

        if (empty($endpoint)) {
            return new WP_Error('azure_image_missing_endpoint', __('Azure Endpoint is required.', 'gpt3-ai-content-generator'));
        }
        if (empty($deployment_name)) {
            return new WP_Error('azure_image_missing_deployment', __('Azure Deployment Name (model) is required.', 'gpt3-ai-content-generator'));
        }

        return rtrim($endpoint, '/') . '/openai/deployments/' . urlencode($deployment_name) . '/images/generations?api-version=' . urlencode($api_version);
    }

    /**
     * Generate an image based on a text prompt using Azure image deployments.
     *
     * @param string $prompt The text prompt describing the image.
     * @param array $api_params API connection parameters ('api_key', 'endpoint', 'api_version_images').
     * @param array $options Generation options ('model' which is deployment, 'n', 'size', 'quality', 'output_format', etc.).
     * @return array|WP_Error Array containing 'images' and 'usage' or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $prompt = AIPKit_Prompt_Sanitizer::sanitize($prompt);
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('azure_image_missing_key', __('Azure API Key is required for image generation.', 'gpt3-ai-content-generator'));
        }

        $url_params = [
            'azure_endpoint' => $api_params['endpoint'] ?? '',
            'deployment' => $options['model'] ?? '',
            'api_version_images' => $api_params['api_version_images'] ?? '2025-04-01-preview',
        ];

        $url = $this->build_api_url('images/generations', $url_params);
        if (is_wp_error($url)) {
            return $url;
        }

        // Build a payload compatible with Azure OpenAI image generation.
        $payload = [
            'model' => sanitize_text_field((string) ($options['model'] ?? '')),
            'prompt' => $prompt,
            'n' => max(1, min(isset($options['n']) ? absint($options['n']) : 1, 10)),
        ];

        $size = $this->sanitize_size($options['size'] ?? '');
        if ($size !== '') {
            $payload['size'] = $size;
        }

        $quality = $this->sanitize_choice($options['quality'] ?? '', ['auto', 'low', 'medium', 'high']);
        if ($quality !== '') {
            $payload['quality'] = $quality;
        }

        $output_format = $this->sanitize_choice($options['output_format'] ?? '', ['png', 'jpeg']);
        $background = $this->sanitize_choice($options['background'] ?? '', ['auto', 'transparent']);
        if ($background === 'transparent') {
            $output_format = 'png';
        }
        if ($output_format !== '') {
            $payload['output_format'] = $output_format;
        }
        if ($background !== '') {
            $payload['background'] = $background;
        }
        if ($output_format === 'jpeg' && isset($options['output_compression']) && $options['output_compression'] !== '') {
            $payload['output_compression'] = max(0, min(absint($options['output_compression']), 100));
        }
        if (!empty($options['user'])) {
            $payload['user'] = sanitize_text_field((string) $options['user']);
        }

        $headers_array = $this->get_api_headers($api_key, 'generate');
        $request_options = $this->get_request_options('generate');
        $request_body_json = wp_json_encode($payload);

        $request_args = array_merge($request_options, [
            'headers' => $headers_array,
            'body' => $request_body_json,
        ]);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('azure_image_http_error', __('HTTP error during Azure image generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = $this->decode_json($body, 'Azure Image Generation');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_msg = is_wp_error($decoded_response) ? $decoded_response->get_error_message() : $this->parse_error_response($body, $status_code, 'Azure Image');
            /* translators: %1$d: HTTP status code, %2$s: error message */
            return new WP_Error('azure_image_api_error', sprintf(__('Azure Image API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        // The response structure is the same as OpenAI's
        $images = [];
        if (isset($decoded_response['data']) && is_array($decoded_response['data'])) {
            foreach ($decoded_response['data'] as $imageData) {
                $images[] = [
                    'url'            => $imageData['url'] ?? null,
                    'b64_json'       => $imageData['b64_json'] ?? null,
                    'revised_prompt' => $imageData['revised_prompt'] ?? null,
                ];
            }
        }
        $estimated_usage = null;
        if (!empty($images)) {
            $num_images = count($images);
            $prompt_words = str_word_count($prompt);
            $estimated_input_tokens = max(1, (int)($prompt_words * 1.3));
            $tokens_per_image = 2000;
            $estimated_output_tokens = $num_images * $tokens_per_image;
            $total_tokens = $estimated_input_tokens + $estimated_output_tokens;
            
            $estimated_usage = [
                'input_tokens' => $estimated_input_tokens,
                'output_tokens' => $estimated_output_tokens,
                'total_tokens' => $total_tokens,
                'provider_raw' => [
                    'source' => 'estimated_azure_image_cost',
                    'model' => $options['model'] ?? '',
                    'images_generated' => $num_images,
                    'tokens_per_image' => $tokens_per_image,
                    'prompt_tokens_estimated' => $estimated_input_tokens,
                ],
            ];
        }

        return ['images' => $images, 'usage' => $estimated_usage];
    }

    /**
     * Get the supported image sizes for Azure image generation.
     */
    public function get_supported_sizes(): array
    {
        return ['1024x1024', '1536x1024', '1024x1536'];
    }

    /**
     * Get API headers required for Azure requests.
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        return [
            'Content-Type' => 'application/json',
            'api-key' => $api_key,
        ];
    }

    private function sanitize_choice($value, array $allowed): string
    {
        $value = sanitize_key(is_scalar($value) ? (string) $value : '');

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_size($value): string
    {
        $size = sanitize_text_field(is_scalar($value) ? (string) $value : '');
        if ($size === '') {
            return '';
        }
        if ($size === 'auto') {
            return $size;
        }
        if (!preg_match('/^([1-9][0-9]{2,4})x([1-9][0-9]{2,4})$/', $size, $matches)) {
            return '';
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];
        $fixed_sizes = [
            '1024x1024',
            '1536x1024',
            '1024x1536',
        ];
        if (in_array($size, $fixed_sizes, true)) {
            return $size;
        }

        $long_edge = max($width, $height);
        $short_edge = min($width, $height);
        $pixel_count = $width * $height;

        if ($width % 16 !== 0 || $height % 16 !== 0) {
            return '';
        }
        if ($long_edge > 3840 || $short_edge <= 0 || ($long_edge / $short_edge) > 3) {
            return '';
        }
        if ($pixel_count < 655360 || $pixel_count > 8294400) {
            return '';
        }

        return $size;
    }
}
