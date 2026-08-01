<?php

namespace WPAICG\Speech;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Google Cloud Text-to-Speech Provider Strategy.
 * Implements voice fetching and speech generation.
 * REVISED: Returns base64 audio data instead of saving to file.
 */
class AIPKit_TTS_Google_Provider_Strategy extends AIPKit_TTS_Base_Provider_Strategy {

    /**
     * Generates speech audio using Google Cloud TTS API.
     *
     * @param string $text The text to synthesize.
     * @param array $api_params Must include 'api_key'.
     * @param array $options Must include 'voice' (voice ID) and 'format' (e.g., 'mp3').
     * @return string|WP_Error Base64 encoded audio data string or WP_Error on failure.
     */
    public function generate_speech(string $text, array $api_params, array $options) {
        $api_key = $api_params['api_key'] ?? null;
        $voice_id = $options['voice'] ?? null;
        $output_format = $options['format'] ?? 'mp3'; // Default to mp3

        // --- Re-check API key and Voice ID within strategy ---
        if (empty($api_key)) {
            return new WP_Error('google_tts_missing_key', __('Google API Key is required for speech generation.', 'gpt3-ai-content-generator'));
        }
        if (empty($voice_id)) {
            return new WP_Error('google_tts_missing_voice', __('Google Voice ID is required for speech generation.', 'gpt3-ai-content-generator'));
        }
        // --- End Re-check ---

        if (empty($text)) {
             return new WP_Error('google_tts_empty_text', __('Text cannot be empty for speech generation.', 'gpt3-ai-content-generator'));
        }

        switch (strtolower($output_format)) {
            case 'mp3':
                $audio_encoding = 'MP3';
                break;
            case 'wav':
                $audio_encoding = 'LINEAR16';
                break;
            case 'ogg_opus':
                $audio_encoding = 'OGG_OPUS';
                break;
            default:
                $audio_encoding = 'MP3';
                break;
        }

        // Extract language code from voice ID (e.g., "en-US" from "en-US-Wavenet-A")
        $language_code = (string) substr($voice_id, 0, strpos($voice_id, '-', strpos($voice_id, '-') + 1) ?: 5); // Basic extraction
        if (empty($language_code)) {
            $language_code = 'en-US'; // Fallback language
        }

        $url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode($api_key);

        $request_body = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $language_code,
                'name' => $voice_id,
            ],
            'audioConfig' => [
                'audioEncoding' => $audio_encoding,
                // Add optional parameters like speakingRate, pitch if implemented later
                // 'speakingRate' => $options['speed'] ?? 1.0,
                // 'pitch' => $options['pitch'] ?? 0.0,
            ],
        ];

        $request_args = $this->get_request_options('generate_speech'); // Use base options
        $request_args['method'] = 'POST';
        $request_args['headers'] = $this->get_api_headers($api_key, 'generate_speech');
        $request_args['body'] = wp_json_encode($request_body);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            // --- Add status code to WP_Error data ---
            return new WP_Error('google_tts_http_error', __('HTTP error during speech generation.', 'gpt3-ai-content-generator'), ['status' => 503]); // 503 Service Unavailable
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_msg = $this->parse_error_response($body, $status_code, 'Google TTS Speech');
            // --- Add status code to WP_Error data ---
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('google_tts_api_error', sprintf(__('Google Speech API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        $decoded_response = $this->decode_json($body, 'Google TTS Speech');
        if (is_wp_error($decoded_response)) {
            // --- Add status code to WP_Error data ---
             return new WP_Error($decoded_response->get_error_code(), $decoded_response->get_error_message(), ['status' => 500]);
        }

        if (empty($decoded_response['audioContent'])) {
            // --- Add status code to WP_Error data ---
            return new WP_Error('google_tts_no_audio', __('Google API returned success but no audio data.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        $base64_audio_data = $decoded_response['audioContent'];
        return $base64_audio_data;
        // --- END REVISION ---
    }

    /**
     * Fetches the list of available voices from the Google Cloud TTS API.
     * (No changes needed in this method)
     * @return mixed[]|\WP_Error
     */
    public function get_voices(array $api_params) {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('google_tts_missing_key', __('Google API Key is required to fetch voices.', 'gpt3-ai-content-generator'));
        }

        $url = 'https://texttospeech.googleapis.com/v1/voices?key=' . urlencode($api_key); // UPDATED: Use v1 instead of v1beta1

        $request_args = $this->get_request_options('voices');
        $request_args['method'] = 'GET';

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('google_tts_http_error', __('HTTP error fetching Google voices.', 'gpt3-ai-content-generator'), ['status' => 503]); // Use 503 for network/http error
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_msg = $this->parse_error_response($body, $status_code, 'Google TTS Voices');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('google_tts_api_error', sprintf(__('Google Voices API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        $decoded = $this->decode_json($body, 'Google TTS Voices');
        if (is_wp_error($decoded)) {
             return new WP_Error($decoded->get_error_code(), $decoded->get_error_message(), ['status' => 500]);
        }

        $voices_raw = $decoded['voices'] ?? [];
        $formatted_voices = [];
        if (is_array($voices_raw)) {
             foreach ($voices_raw as $voice) {
                if (!isset($voice['name']) || !isset($voice['languageCodes']) || !isset($voice['ssmlGender'])) continue;
                $language_code = $voice['languageCodes'][0] ?? 'Unknown';
                $gender = strtolower($voice['ssmlGender']);
                // Construct a more descriptive name (e.g., en-US-Standard-A (female))
                $display_name = sprintf('%s (%s)', $voice['name'], $gender);
                $formatted_voices[] = [
                    'id' => $voice['name'], // The 'name' field is the ID used in requests
                    'name' => $display_name,
                    'languageCodes' => $voice['languageCodes'],
                    'gender' => $gender,
                ];
            }
            usort($formatted_voices, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        return $formatted_voices;
    }

    /**
     * Returns supported audio formats.
     */
    public function get_supported_formats(): array {
        // Based on Google Cloud TTS documentation
        return ['mp3', 'wav', 'ogg_opus']; // Removed linear16 for simplicity
    }
}
