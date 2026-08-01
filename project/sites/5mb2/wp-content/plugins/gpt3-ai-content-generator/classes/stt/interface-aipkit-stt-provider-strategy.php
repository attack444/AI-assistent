<?php

namespace WPAICG\STT; // Use new STT namespace

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for Speech-to-Text (STT) Provider Strategies.
 * Defines the contract for transcribing audio using different services.
 */
interface AIPKit_STT_Provider_Strategy_Interface {

    /**
     * Transcribe audio data to text.
     *
     * @param string $audio_data Binary audio data string (decoded from base64 if needed).
     * @param string $audio_format The format/extension of the audio (e.g., 'wav', 'mp3', 'ogg', 'flac').
     * @param array $api_params Provider-specific API connection parameters (key, region, etc.).
     * @param array $options Transcription options (language, model, etc.).
     * @return string|WP_Error The transcribed text or WP_Error on failure.
     */
    public function transcribe_audio(string $audio_data, string $audio_format, array $api_params, array $options = []);

    /**
     * Get the supported audio input formats for this provider.
     *
     * @return array List of supported formats (e.g., ['wav', 'mp3', 'ogg']).
     */
    public function get_supported_formats(): array;

     /**
     * Get provider-specific request options for wp_remote_request or cURL.
     * @param string $operation The operation (e.g., 'transcribe').
     * @return array Request options.
     */
    public function get_request_options(string $operation): array;

     /**
     * Get API headers required for the request.
     * @param string $api_key (May not be needed for all providers in headers)
     * @param string $operation (e.g., 'transcribe')
     * @return array Key-value array of headers.
     */
    public function get_api_headers(string $api_key, string $operation): array;
}