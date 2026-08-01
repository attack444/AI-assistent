<?php
// MODIFIED FILE - No changes needed, but providing full content for context.

namespace WPAICG\Speech;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses core WordPress hook names.

/**
 * Abstract Base class for TTS Provider Strategies.
 * Provides common helper methods (optional).
 */
abstract class AIPKit_TTS_Base_Provider_Strategy implements AIPKit_TTS_Provider_Strategy_Interface {

    /**
     * Common helper to parse JSON, returning a WP_Error on failure.
     * @param string $json_string The JSON string to decode.
     * @param string $context Context for error messages (e.g., "OpenAI TTS").
     * @return array|WP_Error Decoded array or WP_Error.
     */
    protected function decode_json(string $json_string, string $context) {
        // Prevent decoding empty strings which result in null
        if (trim($json_string) === '') {
            return [];
        }
        $decoded = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // translators: %1$s is the context label (e.g., provider name); %2$s is the JSON error message
            $error_message = sprintf(__('Failed to parse JSON response from %1$s. Error: %2$s', 'gpt3-ai-content-generator'), $context, json_last_error_msg());
            return new WP_Error('json_decode_error', $error_message);
        }
        // Ensure it's an array, even if JSON was valid but not an object/array (e.g. "true")
        return is_array($decoded) ? $decoded : [];
    }

     /**
     * Common helper to parse API errors. Can be overridden by specific strategies.
     * @param mixed $response_body Raw or decoded response body.
     * @param int $status_code HTTP status code.
     * @param string $context Provider context (e.g., "OpenAI TTS").
     * @return string User-friendly error message.
     */
    protected function parse_error_response($response_body, int $status_code, string $context): string {
        /* translators: %s: Context for the error (e.g., "OpenAI TTS"). */
        $message = sprintf(__('An unknown error occurred with %s.', 'gpt3-ai-content-generator'), $context);
        $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

        if (is_array($decoded)) {
            // Check common error structures
            if (!empty($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (!empty($decoded['detail'])) {
                $message = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
            } elseif (!empty($decoded['message'])) {
                $message = $decoded['message'];
            }
        } elseif (is_string($response_body) && strlen($response_body) < 500) { // Show raw body if short
             $message = $response_body;
        }

        return trim($message);
    }

    /**
     * Get default request options for wp_remote_request or cURL. Providers can override.
     * @param string $operation The operation ('generate_speech', 'voices', etc.).
     * @return array Request options.
     */
    public function get_request_options(string $operation): array {
         return [
            'method'     => 'POST', // Default to POST, override for GET operations like 'voices'
            'timeout'    => 60, // Default timeout
            'user-agent' => 'AIPKit/' . (defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.0') . '; ' . get_bloginfo('url'),
            'sslverify'  => apply_filters('https_local_ssl_verify', true),
        ];
    }

    /**
     * Get default API headers. Specific strategies can override if needed.
     * @param string $api_key (May not be needed for all providers in headers)
     * @param string $operation ('generate_speech', 'voices', etc.)
     * @return array Key-value array of headers.
     */
    public function get_api_headers(string $api_key, string $operation): array {
         return [
             'Content-Type' => 'application/json',
             // Specific providers like OpenAI/ElevenLabs would override this
             // to add Authorization headers if necessary. Google uses API key in URL.
         ];
    }

    // Abstract methods from the interface must be implemented by concrete classes
    /**
     * @return string|\WP_Error
     */
    abstract public function generate_speech(string $text, array $api_params, array $options);
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function get_voices(array $api_params);
    abstract public function get_supported_formats(): array;
}