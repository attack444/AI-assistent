<?php


namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Pixabay Image Provider Strategy.
 * Fetches images from Pixabay based on a search query.
 */
class AIPKit_Image_Pixabay_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    /**
     * Generate an image by searching Pixabay.
     *
     * @param string $prompt The search query.
     * @param array $api_params API connection parameters.
     * @param array $options Generation options (orientation, image_type, category, n, page).
     * @return array|WP_Error Array of image data objects or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('pixabay_missing_key', __('Pixabay API Key is required.', 'gpt3-ai-content-generator'));
        }

        $num_images_to_fetch = isset($options['n']) ? absint($options['n']) : 1;
        // Pixabay API requires per_page to be between 3 and 200.
        $per_page_for_api = max(3, min($num_images_to_fetch, 200));

        $query_args = [
            'key' => $api_key,
            'q' => urlencode($prompt),
            'per_page' => $per_page_for_api,
            'safesearch' => 'true', // Always use safesearch
        ];
        
        if (isset($options['page']) && $options['page'] > 0) {
            $query_args['page'] = absint($options['page']);
        }
        if (!empty($options['orientation']) && $options['orientation'] !== 'all') {
            $query_args['orientation'] = $options['orientation'];
        }
        if (!empty($options['image_type']) && $options['image_type'] !== 'all') {
            $query_args['image_type'] = $options['image_type'];
        }
        if (!empty($options['category'])) {
            $query_args['category'] = $options['category'];
        }

        $base_url = 'https://pixabay.com/api/';
        $url = add_query_arg($query_args, $base_url);

        $headers = $this->get_api_headers($api_key, 'generate');
        $request_options = $this->get_request_options('generate');
        $request_args = array_merge($request_options, ['headers' => $headers]);

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('pixabay_http_error', __('HTTP error during Pixabay API request.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_msg = $this->parse_error_response($body, $status_code, 'Pixabay');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('pixabay_api_error', sprintf(__('Pixabay API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg));
        }

        $decoded_response = $this->decode_json($body, 'Pixabay Image Search');
        if (is_wp_error($decoded_response)) {
            return $decoded_response;
        }

        $images = [];
        if (isset($decoded_response['hits']) && is_array($decoded_response['hits'])) {
            // Slice the results to match the originally requested number of images.
            $hits_to_process = array_slice($decoded_response['hits'], 0, $num_images_to_fetch);

            foreach ($hits_to_process as $hit) {
                $images[] = [
                    'url' => $hit['largeImageURL'] ?? ($hit['webformatURL'] ?? ''),
                    'b64_json' => null,
                    'revised_prompt' => null, // Not applicable
                    'photographer' => $hit['user'] ?? null,
                    'alt' => $hit['tags'] ?? null,
                ];
            }
        }

        return ['images' => $images, 'usage' => null];
    }

    /**
     * Get the supported image sizes for this provider.
     */
    public function get_supported_sizes(): array
    {
        return [];
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
        return $headers;
    }
}
