<?php


namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Pexels Image Provider Strategy.
 * Fetches images from Pexels based on a search query.
 */
class AIPKit_Image_Pexels_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    /**
     * Generate an image by searching Pexels.
     *
     * @param string $prompt The search query.
     * @param array $api_params API connection parameters.
     * @param array $options Generation options (orientation, size, color, n, page).
     * @return array|WP_Error Array of image data objects or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('pexels_missing_key', __('Pexels API Key is required.', 'gpt3-ai-content-generator'));
        }

        $query_args = [
            'query' => urlencode($prompt),
            'per_page' => isset($options['n']) ? absint($options['n']) : 1,
        ];

        if (isset($options['page']) && $options['page'] > 0) {
            $query_args['page'] = absint($options['page']);
        }
        if (!empty($options['orientation']) && $options['orientation'] !== 'none' && in_array($options['orientation'], ['landscape', 'portrait', 'square'])) {
            $query_args['orientation'] = $options['orientation'];
        }
        if (!empty($options['size']) && $options['size'] !== 'none' && in_array($options['size'], ['large', 'medium', 'small'])) {
            $query_args['size'] = $options['size'];
        }
        if (!empty($options['color'])) {
            $query_args['color'] = $options['color'];
        }

        $base_url = 'https://api.pexels.com/v1/search';
        $url = add_query_arg($query_args, $base_url);

        $headers = $this->get_api_headers($api_key, 'generate');
        $request_options = $this->get_request_options('generate');
        $request_args = array_merge($request_options, ['headers' => $headers]);

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('pexels_http_error', __('HTTP error during Pexels API request.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = $this->decode_json($body, 'Pexels Image Search');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_msg = is_wp_error($decoded_response)
                        ? $decoded_response->get_error_message()
                        : $this->parse_error_response($body, $status_code, 'Pexels');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('pexels_api_error', sprintf(__('Pexels API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        $images = [];
        if (isset($decoded_response['photos']) && is_array($decoded_response['photos'])) {
            foreach ($decoded_response['photos'] as $photo) {
                $images[] = [
                    'url' => $photo['src']['large2x'] ?? ($photo['src']['large'] ?? ($photo['src']['original'] ?? '')),
                    'b64_json' => null,
                    'revised_prompt' => null, // Not applicable for Pexels
                    'photographer' => $photo['photographer'] ?? null,
                    'alt' => $photo['alt'] ?? null,
                ];
            }
        }

        return ['images' => $images, 'usage' => null];
    }

    /**
     * Get the supported image sizes for this provider. Pexels uses descriptive sizes.
     */
    public function get_supported_sizes(): array
    {
        return ['large', 'medium', 'small'];
    }

    /**
     * Get provider-specific request options.
     */
    public function get_request_options(string $operation): array
    {
        $options = parent::get_request_options($operation);
        $options['method'] = 'GET';
        return $options;
    }

    /**
     * Get API headers required for the request.
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        $headers = parent::get_api_headers($api_key, $operation);
        unset($headers['Content-Type']); // Not needed for GET request
        $headers['Authorization'] = $api_key;
        return $headers;
    }
}
