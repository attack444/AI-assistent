<?php
// MODIFIED FILE

namespace WPAICG\Speech;

use WP_Error;
use WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder; // Use the OpenAI URL builder

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenAI Text-to-Speech Provider Strategy.
 * Implements voice fetching and speech generation using OpenAI's TTS API.
 * UPDATED: Uses 'model_id' from options instead of hardcoding 'tts-1'.
 */
class AIPKit_TTS_OpenAI_Provider_Strategy extends AIPKit_TTS_Base_Provider_Strategy {

    /**
     * Constructor. Ensures necessary component classes are loaded.
     */
    public function __construct() {
        $openai_core_provider_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/openai/';
        if (!class_exists(OpenAIUrlBuilder::class)) {
            $url_builder_file = $openai_core_provider_path . 'bootstrap-provider-strategy.php';
            if (file_exists($url_builder_file)) {
                require_once $url_builder_file;
            }
        }
    }

    /**
     * Generates speech audio using OpenAI TTS API.
     *
     * @param string $text The text to synthesize.
     * @param array $api_params Must include 'api_key'. Optional: 'base_url', 'api_version'.
     * @param array $options Must include 'voice' (voice ID) and 'format' (e.g., 'mp3', 'opus').
     *                       May include 'speed' (0.25 to 4.0) and 'model_id' (e.g., 'tts-1', 'tts-1-hd').
     * @return string|WP_Error Base64 encoded audio data string or WP_Error on failure.
     */
    public function generate_speech(string $text, array $api_params, array $options) {
        $api_key = $api_params['api_key'] ?? null;
        $voice = $options['voice'] ?? null;
        $output_format = strtolower($options['format'] ?? 'mp3');
        $speed = isset($options['speed']) ? floatval($options['speed']) : 1.0;
        $model_id = isset($options['model_id']) && !empty($options['model_id'])
                    ? $options['model_id']
                    : BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID; // Use default if not provided


        if (empty($api_key)) return new WP_Error('openai_tts_missing_key', __('OpenAI API Key is required.', 'gpt3-ai-content-generator'));
        if (empty($voice)) return new WP_Error('openai_tts_missing_voice', __('OpenAI Voice is required.', 'gpt3-ai-content-generator'));
        if (empty($text)) return new WP_Error('openai_tts_empty_text', __('Text cannot be empty.', 'gpt3-ai-content-generator'));
        if (!in_array($voice, array_column($this->get_voices(), 'id'))) return new WP_Error('openai_tts_invalid_voice', __('Invalid OpenAI voice selected.', 'gpt3-ai-content-generator'));
        if (!in_array($output_format, $this->get_supported_formats())) $output_format = 'mp3'; // Default to mp3 if invalid format provided
        $speed = max(0.25, min($speed, 4.0)); // Clamp speed

        // Use OpenAIUrlBuilder for consistency
        if (!class_exists(OpenAIUrlBuilder::class)) {
             return new WP_Error('openai_tts_dependency_missing', __('OpenAI URL Builder component is missing.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        $url_builder_params = [
            'base_url' => $api_params['base_url'] ?? 'https://api.openai.com',
            'api_version' => $api_params['api_version'] ?? 'v1',
        ];
        $url = OpenAIUrlBuilder::build('audio/speech', $url_builder_params); // Correct operation key based on API structure
        if (is_wp_error($url)) return $url;

        $request_body = [
            'model' => $model_id, // Use the selected/default TTS model ID
            'input' => $text,
            'voice' => $voice,
            'response_format' => $output_format,
            'speed' => $speed,
        ];
        // --- END UPDATE ---


        $request_args = $this->get_request_options('generate_speech');
        $request_args['method'] = 'POST';
        $request_args['headers'] = $this->get_api_headers($api_key, 'generate_speech');
        $request_args['body'] = wp_json_encode($request_body);

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            return new WP_Error('openai_tts_http_error', __('HTTP error during speech generation.', 'gpt3-ai-content-generator'), ['status' => 503]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response); // This should be the raw audio data on success

        if ($status_code !== 200) {
            // Try to parse error from body (likely JSON)
            $error_msg = $this->parse_error_response($body, $status_code, 'OpenAI TTS Speech');
            /* translators: %1$d: HTTP status code, %2$s: Error message from the API. */
            return new WP_Error('openai_tts_api_error', sprintf(__('OpenAI Speech API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_msg), ['status' => $status_code]);
        }

        if (empty($body)) {
             return new WP_Error('openai_tts_no_audio', __('OpenAI API returned success but no audio data.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        // Return base64 encoded audio data
        return base64_encode($body);
    }

    /**
     * Returns the hardcoded list of OpenAI TTS voices.
     *
     * @param array $api_params Ignored for OpenAI voices.
     * @return array List of voice objects.
     */
    public function get_voices(array $api_params = []) {
        // As per documentation + OpenAI.fm demo
        return [
            ['id' => 'alloy', 'name' => 'Alloy', 'gender' => 'neutral'],
            ['id' => 'echo', 'name' => 'Echo', 'gender' => 'male'],
            ['id' => 'fable', 'name' => 'Fable', 'gender' => 'male'],
            ['id' => 'onyx', 'name' => 'Onyx', 'gender' => 'male'],
            ['id' => 'nova', 'name' => 'Nova', 'gender' => 'female'],
            ['id' => 'shimmer', 'name' => 'Shimmer', 'gender' => 'female'],
            // ['id' => 'ash', 'name' => 'Ash', 'gender' => 'male'],
            // ['id' => 'ballad', 'name' => 'Ballad', 'gender' => 'neutral'],
            // ['id' => 'coral', 'name' => 'Coral', 'gender' => 'female'],
            // ['id' => 'sage', 'name' => 'Sage', 'gender' => 'female'],
        ];
    }

    /**
     * Returns supported audio formats for OpenAI TTS.
     */
    public function get_supported_formats(): array {
        // Based on OpenAI documentation
        return ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'];
    }

     /**
     * Override get_api_headers to include Authorization.
     */
    public function get_api_headers(string $api_key, string $operation): array {
        $headers = parent::get_api_headers($api_key, $operation); // Get base headers (Content-Type)
        $headers['Authorization'] = 'Bearer ' . $api_key; // Add OpenAI specific key
        // Accept header is generally not needed for OpenAI TTS, response type is dictated by request format
        return $headers;
    }
}
