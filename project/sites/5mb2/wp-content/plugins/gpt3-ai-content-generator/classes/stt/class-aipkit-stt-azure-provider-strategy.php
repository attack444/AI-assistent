<?php


namespace WPAICG\STT; // Use new STT namespace

use WP_Error;
use CURLFile; // Needed for multipart file upload

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Azure Speech-to-Text Provider Strategy.
 * Implements transcription using Azure AI Services Speech to Text REST API.
 */
class AIPKit_STT_Azure_Provider_Strategy extends AIPKit_STT_Base_Provider_Strategy
{
    /**
     * Transcribe audio data to text using Azure Speech Service API via native cURL.
     * API Reference: https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-speech-to-text#speech-to-text-rest-api-v31
     * Endpoint Example: {endpoint}/speechtotext/transcriptions:transcribe?api-version=...
     *
     * @param string $audio_data Binary audio data string.
     * @param string $audio_format The format/extension of the audio (e.g., 'wav', 'mp3'). Matched against get_supported_formats().
     * @param array $api_params Must include 'api_key' and 'azure_endpoint'. Optional: 'stt_model'.
     * @param array $options Transcription options (e.g., 'language').
     * @return string|WP_Error Transcribed text or WP_Error.
     */
    public function transcribe_audio(string $audio_data, string $audio_format, array $api_params, array $options = [])
    {
        $api_key = $api_params['api_key'] ?? null;
        $endpoint = $api_params['azure_endpoint'] ?? null;
        $azure_model_id = $api_params['stt_model'] ?? null; // Optional model identifier from settings

        if (empty($api_key)) {
            return new WP_Error('azure_stt_missing_key', __('Azure Subscription Key is required.', 'gpt3-ai-content-generator'));
        }
        if (empty($endpoint)) {
            return new WP_Error('azure_stt_missing_endpoint', __('Azure Endpoint/Region URL is required.', 'gpt3-ai-content-generator'));
        }
        if (!in_array(strtolower($audio_format), $this->get_supported_formats())) {
            /* translators: %s is the audio format */
            return new WP_Error('azure_stt_unsupported_format', sprintf(__('Audio format "%s" is not supported by Azure STT.', 'gpt3-ai-content-generator'), $audio_format));
        }
        if (!class_exists('\CURLFile')) {
            return new WP_Error('stt_curlfile_missing', __('Server configuration error (CURLFile missing).', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // --- Prepare temporary file for cURL ---
        $tmp_filename = wp_tempnam('azure_stt_upload');
        if ($tmp_filename === false) {
            return new WP_Error('stt_tmp_file_error', __('Could not create temporary file for audio upload.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        if (file_put_contents($tmp_filename, $audio_data) === false) {
            wp_delete_file($tmp_filename);
            return new WP_Error('stt_tmp_write_error', __('Could not write audio data to temporary file.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        $effective_filename = 'audio.' . strtolower($audio_format);
        $file_mime_type = mime_content_type($tmp_filename) ?: 'application/octet-stream';
        $cfile = new \CURLFile($tmp_filename, $file_mime_type, $effective_filename);
        // --- End temporary file ---

        // --- Build URL ---
        // Example: https://YOUR_REGION.api.cognitive.microsoft.com/speechtotext/transcriptions:transcribe?api-version=2024-11-15
        $api_version = '2024-11-15'; // Use a recent, stable version
        $url = rtrim($endpoint, '/') . '/speechtotext/transcriptions:transcribe?api-version=' . $api_version;
        // Add language if provided in options
        if (!empty($options['language'])) {
            $url = add_query_arg('language', sanitize_text_field($options['language']), $url);
        } else {
            // Default to en-US if not provided (Azure requires language)
            $url = add_query_arg('language', 'en-US', $url);
        }

        // --- Prepare Request Definition (JSON part of multipart) ---
        $definition = [
            'displayName' => 'AIPKit Transcription ' . current_time('mysql', 1),
            'description' => 'Transcription requested by AIPKit plugin.',
            'locale' => !empty($options['language']) ? sanitize_text_field($options['language']) : 'en-US',
            'properties' => [
                'wordLevelTimestampsEnabled' => false,
                'diarizationEnabled' => false, // Keep simple for now
                // Add more properties like 'punctuationMode', 'profanityFilterMode' if needed
            ]
        ];
        // Add model identifier if provided in options/api_params
        if (!empty($azure_model_id)) {
            $definition['model'] = ['self' => $azure_model_id]; // Assuming it's a full model URI, adjust if it's just an ID
        }
        $definition_json = wp_json_encode($definition);


        // --- Prepare cURL POST fields ---
        $post_fields = [
            'audio' => $cfile,
            'definition' => $definition_json,
        ];

        // --- Prepare cURL Request ---
        $headers_array = $this->get_api_headers($api_key, 'transcribe');
        $curl_headers = $this->format_headers_for_curl($headers_array); // Format for cURL
        // Note: Content-Type for multipart/form-data is set automatically by cURL

        $request_options = $this->get_request_options('transcribe');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Reason: Using cURL for streaming.
        $ch = curl_init();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Reason: Using cURL for streaming.
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_fields, // cURL handles multipart encoding
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_TIMEOUT => $request_options['timeout'] ?? 90, // Longer timeout might be needed
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT => $request_options['user-agent'] ?? 'AIPKit STT',
            CURLOPT_SSL_VERIFYPEER => $request_options['sslverify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => ($request_options['sslverify'] ?? true) ? 2 : 0,
        ]);
        // --- End Prepare cURL Request ---

        // Execute cURL request
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- Reason: Using cURL for streaming.
        $body = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno -- Reason: Using cURL for streaming.
        $curl_errno = curl_errno($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- Reason: Using cURL for streaming.
        $curl_error = curl_error($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Reason: Using cURL for streaming.
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ch = null;
        wp_delete_file($tmp_filename); // Clean up temporary file

        // Handle cURL errors
        if ($curl_errno) {
            /* translators: %s: cURL error message. */
            return new WP_Error('azure_stt_curl_error', sprintf(__('Network error during transcription: %s', 'gpt3-ai-content-generator'), $curl_error), ['status' => 503]);
        }

        // Handle API errors (non-200/202 status)
        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $this->parse_error_response($body, $status_code, 'Azure STT');
            /* translators: %1$d: HTTP status code, %2$s: API error message. */
            return new WP_Error('azure_stt_api_error', sprintf(__('Azure STT API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        // Parse successful response (status code 200)
        $decoded_response = $this->decode_json($body, 'Azure STT');
        if (is_wp_error($decoded_response)) {
            return new WP_Error($decoded_response->get_error_code(), $decoded_response->get_error_message(), ['status' => 500]);
        }

        // Extract transcription text
        $transcribed_text = $decoded_response['combinedPhrases'][0]['text'] ?? null;

        if ($transcribed_text !== null) {
            return trim($transcribed_text);
        } else {
            return new WP_Error('azure_stt_no_text', __('Transcription successful but no text found in response.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
    }

    /**
     * Get supported audio input formats for Azure STT.
     */
    public function get_supported_formats(): array
    {
        // Common formats supported by Azure Speech Service REST API v3.1
        return ['wav', 'mp3', 'ogg', 'flac', 'mp4', 'webm']; // webm often uses opus or vorbis
    }

    /**
     * Get API headers required for Azure STT requests.
     * Content-Type is set automatically by cURL for multipart/form-data.
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        return [
            'Ocp-Apim-Subscription-Key' => $api_key,
            // 'Content-Type: multipart/form-data' is handled by cURL when using CURLOPT_POSTFIELDS with an array.
        ];
    }

    /**
     * Override base method to ensure correct format for cURL headers.
     */
    public function format_headers_for_curl(array $headers): array
    {
        $result = [];
        foreach ($headers as $k => $v) {
            $result[] = $k . ': ' . $v;
        }
        return $result;
    }
}
