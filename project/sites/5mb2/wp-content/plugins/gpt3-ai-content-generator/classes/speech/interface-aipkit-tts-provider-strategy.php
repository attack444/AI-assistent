<?php
// MODIFIED FILE - Updated generate_speech docblock

namespace WPAICG\Speech;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for Text-to-Speech (TTS) Provider Strategies.
 * Defines the contract for generating speech using different services.
 */
interface AIPKit_TTS_Provider_Strategy_Interface {

    /**
     * Generate speech audio from text.
     *
     * @param string $text The text to synthesize.
     * @param array $api_params Provider-specific API connection parameters (key, region, etc.).
     * @param array $options Synthesis options (voice, speed, format, etc.).
     * @return string|WP_Error Base64 encoded audio data string or WP_Error on failure.
     */
    public function generate_speech(string $text, array $api_params, array $options);

    /**
     * Get the list of available voices for this provider.
     *
     * @param array $api_params Provider-specific API connection parameters.
     * @return array|WP_Error Array of voice objects/data or WP_Error on failure.
     */
    public function get_voices(array $api_params);

    /**
     * Get the supported audio output formats for this provider.
     *
     * @return array List of supported formats (e.g., ['mp3', 'wav', 'ogg']).
     */
    public function get_supported_formats(): array;

     /**
     * Get provider-specific request options for wp_remote_request or cURL.
     * @param string $operation The operation ('generate_speech', 'voices', etc.).
     * @return array Request options.
     */
    public function get_request_options(string $operation): array;

     /**
     * Get API headers required for the request.
     * @param string $api_key (May not be needed for all providers in headers)
     * @param string $operation ('generate_speech', 'voices', etc.)
     * @return array Key-value array of headers.
     */
    public function get_api_headers(string $api_key, string $operation): array;
}