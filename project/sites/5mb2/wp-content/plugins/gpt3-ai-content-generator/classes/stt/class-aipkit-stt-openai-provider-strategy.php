<?php

// MODIFIED FILE - Use dynamic STT model from options.

namespace WPAICG\STT; // Use new STT namespace

use WP_Error;
use WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenAI Speech-to-Text Provider Strategy.
 * Implements transcription using OpenAI API.
 * Allows specifying the transcription model via options.
 * USES NATIVE cURL for multipart request reliability.
 */
class AIPKit_STT_OpenAI_Provider_Strategy extends AIPKit_STT_Base_Provider_Strategy
{
    /**
    * Constructor. Ensures necessary component classes are loaded.
    */
    public function __construct()
    {
        $openai_core_provider_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/openai/';
        if (!class_exists(OpenAIUrlBuilder::class)) {
            $url_builder_file = $openai_core_provider_path . 'bootstrap-provider-strategy.php';
            if (file_exists($url_builder_file)) {
                require_once $url_builder_file;
            }
        }
    }

    /**
     * Transcribe audio data to text using OpenAI API via native cURL.
     *
     * @param string $audio_data Binary audio data string.
     * @param string $audio_format The format/extension of the audio (e.g., 'wav', 'mp3', 'webm').
     * @param array $api_params Must include 'api_key'. Optional: 'base_url', 'api_version'.
     * @param array $options Transcription options (e.g., language, stt_model).
     * @return string|WP_Error The transcribed text or WP_Error on failure.
     */
    public function transcribe_audio(string $audio_data, string $audio_format, array $api_params, array $options = [])
    {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('openai_stt_missing_key', __('OpenAI API Key is required for transcription.', 'gpt3-ai-content-generator'));
        }

        // Ensure URL builder is loaded
        if (!class_exists(OpenAIUrlBuilder::class)) {
            return new WP_Error('openai_stt_dependency_missing', __('OpenAI URL Builder component is missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // Build URL using the builder
        $url_builder_params = [
            'base_url' => $api_params['base_url'] ?? 'https://api.openai.com',
            'api_version' => $api_params['api_version'] ?? 'v1',
        ];
        $url = OpenAIUrlBuilder::build('audio/transcriptions', $url_builder_params); // Use correct operation key
        if (is_wp_error($url)) {
            return $url;
        }

        // --- Prepare temporary file ---
        $tmp_filename = wp_tempnam('openai_stt_upload');
        if ($tmp_filename === false) {
            return new WP_Error('stt_tmp_file_error', __('Could not create temporary file for audio upload.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        $write_result = file_put_contents($tmp_filename, $audio_data);
        if ($write_result === false) {
            wp_delete_file($tmp_filename); // Clean up
            return new WP_Error('stt_tmp_write_error', __('Could not write audio data to temporary file.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        $effective_filename = 'audio.' . strtolower($audio_format);

        if (!class_exists('\CURLFile')) {
            wp_delete_file($tmp_filename);
            return new WP_Error('stt_curlfile_missing', __('Server configuration error (CURLFile missing).', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        $cfile = new \CURLFile($tmp_filename, mime_content_type($tmp_filename) ?: 'application/octet-stream', $effective_filename);
        // --- End temporary file ---


        // --- Select Model ---
        // *** Use model from options, fallback to whisper-1 ***
        $stt_model = !empty($options['stt_model']) ? sanitize_text_field($options['stt_model']) : 'whisper-1';
        // --- End Select Model ---

        // --- Prepare cURL Request ---
        $post_fields = [
            'file' => $cfile,
            'model' => $stt_model, // *** Use dynamic model ***
        ];
        if (!empty($options['language'])) {
            $post_fields['language'] = sanitize_text_field($options['language']);
        }

        $headers_array = $this->get_api_headers($api_key, 'transcribe'); // Get base headers (Authorization)
        // *** Call the format method ***
        $curl_headers = $this->format_headers_for_curl($headers_array); // Format for cURL

        $request_options = $this->get_request_options('transcribe'); // Get base options
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Reason: Using cURL for streaming.
        $ch = curl_init();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Reason: Using cURL for streaming.
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_HTTPHEADER => $curl_headers, // Use formatted headers
            CURLOPT_TIMEOUT => $request_options['timeout'] ?? 60,
            CURLOPT_CONNECTTIMEOUT => 15,
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
            return new WP_Error('openai_stt_curl_error', sprintf(__('Network error during transcription: %s', 'gpt3-ai-content-generator'), $curl_error), ['status' => 503]);
        }

        // Handle API errors (non-200 status)
        if ($status_code !== 200) {
            $error_msg = $this->parse_error_response($body, $status_code, 'OpenAI STT');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('openai_stt_api_error', sprintf(__('OpenAI STT API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        // Parse successful response
        $decoded_response = $this->decode_json($body, 'OpenAI STT');
        if (is_wp_error($decoded_response)) {
            return new WP_Error($decoded_response->get_error_code(), $decoded_response->get_error_message(), ['status' => 500]);
        }

        if (isset($decoded_response['text'])) {
            return $decoded_response['text'];
        } else {
            return new WP_Error('openai_stt_no_text', __('Transcription successful but no text found in response.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
    }

    /**
     * Get supported audio input formats for OpenAI STT.
     */
    public function get_supported_formats(): array
    {
        // Based on OpenAI Whisper documentation (subject to change)
        return ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];
    }

    /**
     * Get API headers required for OpenAI STT requests.
     * Content-Type is handled by cURL for multipart.
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        return [
            'Authorization' => 'Bearer ' . $api_key,
        ];
    }

    /**
     * Format headers array into the ['Header: Value', ...] format needed by cURL.
     * This method is inherited from the base class, but we explicitly define it here
     * to ensure it's present in this specific strategy class.
     *
     * @param array $headers Associative array of headers.
     * @return array Indexed array of header strings.
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
