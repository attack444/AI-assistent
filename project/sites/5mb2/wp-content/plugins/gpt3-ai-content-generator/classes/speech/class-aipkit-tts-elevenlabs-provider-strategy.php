<?php
// UPDATED FILE - Moved model_id to request body for generate_speech
// UPDATED FILE - Correctly map generic 'mp3' format to a specific ElevenLabs format.

namespace WPAICG\Speech;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * ElevenLabs Text-to-Speech Provider Strategy.
 * Implements voice fetching and speech generation.
 * ADDED: get_models method to fetch ElevenLabs synthesis models.
 * UPDATED: generate_speech to use model_id from options in the request body.
 */
class AIPKit_TTS_ElevenLabs_Provider_Strategy extends AIPKit_TTS_Base_Provider_Strategy {

    /**
     * Generates speech audio using ElevenLabs API.
     *
     * @param string $text The text to synthesize.
     * @param array $api_params Must include 'api_key'.
     * @param array $options Must include 'voice' (voice_id) and 'format' (e.g., 'mp3').
     *                       May include 'model_id' for ElevenLabs specific synthesis model.
     * @return string|WP_Error Base64 encoded audio data string or WP_Error on failure.
     */
    public function generate_speech(string $text, array $api_params, array $options) {
        $api_key = $api_params['api_key'] ?? null;
        $voice_id = $options['voice'] ?? null; // This is voice_id
        $synthesis_model_id = $options['model_id'] ?? null; // This is the synthesis model_id
        
        // --- START: Format Handling ---
        $format_from_options = strtolower(trim($options['format'] ?? ''));
        $supported_formats = $this->get_supported_formats();
        $output_format = 'mp3_44100_128'; // Default valid format

        if (!empty($format_from_options)) {
            if (in_array($format_from_options, $supported_formats, true)) {
                // If the passed format is already a valid ElevenLabs specific format
                $output_format = $format_from_options;
            } elseif ($format_from_options === 'mp3') {
                // If generic 'mp3' is passed, use a default specific mp3 format
                $output_format = 'mp3_44100_128'; // Or another mp3_... from the list
            }
        }
        // --- END: Format Handling ---


        if (empty($api_key)) return new WP_Error('elevenlabs_tts_missing_key', __('ElevenLabs API Key is required.', 'gpt3-ai-content-generator'));
        if (empty($voice_id)) return new WP_Error('elevenlabs_tts_missing_voice', __('ElevenLabs Voice ID is required.', 'gpt3-ai-content-generator'));
        if (empty($text)) return new WP_Error('elevenlabs_tts_empty_text', __('Text cannot be empty.', 'gpt3-ai-content-generator'));

        $base_url = !empty($api_params['base_url']) ? rtrim($api_params['base_url'], '/') : 'https://api.elevenlabs.io';
        $api_version = !empty($api_params['api_version']) ? trim($api_params['api_version'], '/') : 'v1';
        $url = "{$base_url}/{$api_version}/text-to-speech/{$voice_id}";

        // Add output_format (ElevenLabs specific) to query string
        $url = add_query_arg('output_format', $output_format, $url);

        $request_body = [
            'text' => $text,
        ];
        // Add model_id to the body if provided
        if (!empty($synthesis_model_id)) {
            $request_body['model_id'] = $synthesis_model_id;
        }
        
        $request_args = $this->get_request_options('generate_speech');
        $request_args['method'] = 'POST';
        $request_args['headers'] = $this->get_api_headers($api_key, 'generate_speech');
        $request_args['body'] = wp_json_encode($request_body);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('elevenlabs_tts_http_error', __('HTTP error during speech generation.', 'gpt3-ai-content-generator'), ['status' => 503]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_msg = $this->parse_error_response($body, $status_code, 'ElevenLabs TTS Speech');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('elevenlabs_tts_api_error', sprintf(__('ElevenLabs Speech API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        if (empty($body)) {
            return new WP_Error('elevenlabs_tts_no_audio', __('ElevenLabs API returned success but no audio data.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        return base64_encode($body); 
    }

    /**
     * Fetches the list of available voices from the ElevenLabs API.
     * API Docs: https://elevenlabs.io/docs/api-reference/get-voices
     *
     * @param array $api_params Must include 'api_key'. Optional: 'base_url', 'api_version'.
     * @return array|WP_Error Array of voice objects/data or WP_Error on failure.
     *                        Voice object structure: ['id' => string, 'name' => string, 'gender' => string (optional), 'accent' => string (optional)]
     */
    public function get_voices(array $api_params) {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('elevenlabs_tts_missing_key', __('ElevenLabs API Key is required to fetch voices.', 'gpt3-ai-content-generator'));
        }

        $base_url = !empty($api_params['base_url']) ? rtrim($api_params['base_url'], '/') : 'https://api.elevenlabs.io';
        $api_version = !empty($api_params['api_version']) ? trim($api_params['api_version'], '/') : 'v1';
        $url = $base_url . '/' . $api_version . '/voices';

        $request_args = $this->get_request_options('voices');
        $request_args['method'] = 'GET';
        $request_args['headers'] = $this->get_api_headers($api_key, 'voices'); // Use the overridden get_api_headers

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('elevenlabs_tts_http_error', __('HTTP error fetching ElevenLabs voices.', 'gpt3-ai-content-generator'), ['status' => 503]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
             $error_msg = $this->parse_error_response($body, $status_code, 'ElevenLabs Voices');
             /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
             return new WP_Error('elevenlabs_tts_api_error', sprintf(__('ElevenLabs Voices API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        $decoded = $this->decode_json($body, 'ElevenLabs Voices');
        if (is_wp_error($decoded)) {
             return new WP_Error($decoded->get_error_code(), $decoded->get_error_message(), ['status' => 500]);
        }

        $voices_raw = $decoded['voices'] ?? [];
        $formatted_voices = [];
        if (is_array($voices_raw)) {
             foreach ($voices_raw as $voice) {
                if (!isset($voice['voice_id']) || !isset($voice['name'])) continue;

                $labels = $voice['labels'] ?? [];
                $gender = strtolower($labels['gender'] ?? '');
                $accent = $labels['accent'] ?? '';
                $description = $labels['description'] ?? '';

                $display_name = $voice['name'];
                $details = [];
                if ($gender) $details[] = ucfirst($gender);
                if ($accent) $details[] = ucfirst($accent);
                if ($description) $details[] = ucfirst($description);
                if (!empty($details)) $display_name .= ' (' . implode(', ', $details) . ')';

                $formatted_voices[] = [
                    'id' => $voice['voice_id'],
                    'name' => $display_name,
                    'gender' => $gender,
                    'accent' => $accent,
                    'category' => $voice['category'] ?? '',
                ];
            }
            usort($formatted_voices, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        return $formatted_voices;
    }

    /**
     * NEW: Fetches the list of available synthesis models from the ElevenLabs API.
     * API Docs: https://elevenlabs.io/docs/api-reference/get-models
     *
     * @param array $api_params Must include 'api_key'. Optional: 'base_url', 'api_version'.
     * @return array|WP_Error Array of model objects/data or WP_Error on failure.
     *                        Model object structure: ['model_id' => string, 'name' => string, ...]
     */
    public function get_models(array $api_params) {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('elevenlabs_tts_missing_key', __('ElevenLabs API Key is required to fetch models.', 'gpt3-ai-content-generator'));
        }

        $base_url = !empty($api_params['base_url']) ? rtrim($api_params['base_url'], '/') : 'https://api.elevenlabs.io';
        $api_version = !empty($api_params['api_version']) ? trim($api_params['api_version'], '/') : 'v1';
        $url = $base_url . '/' . $api_version . '/models';

        $request_args = $this->get_request_options('models'); // Use base options
        $request_args['method'] = 'GET';
        $request_args['headers'] = $this->get_api_headers($api_key, 'models'); // Use the overridden get_api_headers

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('elevenlabs_tts_http_error', __('HTTP error fetching ElevenLabs models.', 'gpt3-ai-content-generator'), ['status' => 503]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
             $error_msg = $this->parse_error_response($body, $status_code, 'ElevenLabs Models');
             /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
             return new WP_Error('elevenlabs_tts_api_error', sprintf(__('ElevenLabs Models API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        $decoded_models = $this->decode_json($body, 'ElevenLabs Models');
        if (is_wp_error($decoded_models)) {
             return new WP_Error($decoded_models->get_error_code(), $decoded_models->get_error_message(), ['status' => 500]);
        }

        // The API returns an array of model objects directly
        $formatted_models = [];
        if (is_array($decoded_models)) {
             foreach ($decoded_models as $model) {
                if (!isset($model['model_id'])) continue;
                // We can include more fields if needed by the UI
                $formatted_models[] = [
                    'id'   => $model['model_id'],
                    'name' => $model['name'] ?? $model['model_id'],
                    // Optionally add other fields from the response like 'description', 'can_do_text_to_speech'
                    'can_do_text_to_speech' => $model['can_do_text_to_speech'] ?? false,
                    'description' => $model['description'] ?? '',
                ];
            }
            // Filter out models that cannot do text-to-speech
            $formatted_models = array_filter($formatted_models, fn($m) => $m['can_do_text_to_speech'] === true);
            usort($formatted_models, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        return $formatted_models;
    }


    /**
     * Returns supported audio formats for ElevenLabs.
     */
    public function get_supported_formats(): array {
        // From ElevenLabs documentation (as of late 2023 / early 2024)
        // https://elevenlabs.io/docs/api-reference/text-to-speech#output_format
        return [
            'mp3_22050_32', 'mp3_44100_32', 'mp3_44100_64', 'mp3_44100_96', 'mp3_44100_128', 'mp3_44100_192',
            'pcm_8000', 'pcm_16000', 'pcm_22050', 'pcm_24000', 'pcm_44100', 'pcm_48000',
            'ulaw_8000', 'alaw_8000', // Added alaw_8000 as per user's error message list
            'opus_48000_32', 'opus_48000_64', 'opus_48000_96', 'opus_48000_128', 'opus_48000_192' // Added opus formats
        ];
    }

    /**
     * Override get_api_headers to include xi-api-key for ElevenLabs.
     */
    public function get_api_headers(string $api_key, string $operation): array {
        $headers = parent::get_api_headers($api_key, $operation); // Get base headers (Content-Type)
        $headers['xi-api-key'] = $api_key; // Add ElevenLabs specific key
        if ($operation === 'generate_speech') {
            // ElevenLabs often expects 'application/json' for request but returns 'audio/mpeg' for response
            // The parent sets Content-Type. Accept is managed by wp_remote_post based on response.
            $headers['Accept'] = 'audio/mpeg'; // Or other audio formats if supported
        } elseif ($operation === 'voices' || $operation === 'models') {
            $headers['Accept'] = 'application/json';
        }
        return $headers;
    }
}