<?php
// REVISED FILE - Changed generate_image return type hint to array|WP_Error.

namespace WPAICG\Images;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for Image Generation Provider Strategies.
 * Defines the contract for generating images using different services.
 * REVISED: Changed generate_image return type hint to array|WP_Error.
 */
interface AIPKit_Image_Provider_Strategy_Interface {

    /**
     * Generate an image based on a text prompt.
     *
     * @param string $prompt The text prompt describing the image.
     * @param array $api_params Provider-specific API connection parameters (key, region, etc.).
     * @param array $options Generation options (size, quality, style, number of images, etc.).
     * @return array|WP_Error Array of image data objects ([['url'=>..., 'b64_json'=>...], ...]) or WP_Error on failure.
     */
    public function generate_image(string $prompt, array $api_params, array $options = []); // <-- UPDATED RETURN TYPE

    /**
     * Get the supported image sizes for this provider.
     *
     * @return array List of supported sizes (e.g., ['1024x1024', '512x512']).
     */
    public function get_supported_sizes(): array;

     /**
     * Get provider-specific request options for wp_remote_request or cURL.
     * @param string $operation The operation (e.g., 'generate').
     * @return array Request options.
     */
    public function get_request_options(string $operation): array;

     /**
     * Get API headers required for the request.
     * @param string $api_key API key.
     * @param string $operation The operation (e.g., 'generate').
     * @return array Key-value array of headers.
     */
    public function get_api_headers(string $api_key, string $operation): array;
}