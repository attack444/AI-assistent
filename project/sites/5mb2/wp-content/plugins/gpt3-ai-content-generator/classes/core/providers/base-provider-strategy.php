<?php


namespace WPAICG\Core\Providers; // *** Correct namespace ***

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses core WordPress hook names.

/**
 * Abstract Base class for Provider Strategies.
 * Provides common helper methods.
 * MODIFIED: Made decode_json and parse_error_response public.
 */
abstract class BaseProviderStrategy implements ProviderStrategyInterface
{
    /**
     * Common helper to parse JSON, returning a WP_Error on failure.
     * @param string $json_string The JSON string to decode.
     * @param string $context Context for error messages (e.g., "OpenAI Models").
     * @return array|WP_Error Decoded array or WP_Error.
     */
    public function decode_json(string $json_string, string $context) // MODIFIED to public
    {if (trim($json_string) === '') {
        return [];
    }
        $decoded = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            /* translators: %1$s: The context of the API call (e.g., "OpenAI Models"), %2$s: The specific JSON error message from PHP. */
            $error_message = sprintf(__('Failed to parse JSON response from %1$s. Error: %2$s', 'gpt3-ai-content-generator'), $context, json_last_error_msg());
            return new WP_Error('json_decode_error', $error_message);
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Common helper to format model lists based on specified ID and name keys.
     * @param array $raw_models The array of raw model data from the API.
     * @param string $id_key The key in the raw data representing the model ID.
     * @param string $name_key The key in the raw data representing the display name.
     * @return array Formatted list [['id' => ..., 'name' => ...]].
     */
    public function format_model_list(array $raw_models, string $id_key = 'id', string $name_key = 'id'): array // MODIFIED to public (was protected)
    {$formatted = [];
        if (!is_array($raw_models)) {
            return [];
        }
        foreach ($raw_models as $model) {
            if (!is_array($model)) {
                continue;
            }
            $id = $model[$id_key] ?? null;
            if (!empty($id)) {
                $name = $model[$name_key] ?? $id;
                $formatted[] = [
                    'id'   => $id,
                    'name' => $name,
                    'status' => $model['status'] ?? null,
                    'version' => $model['version'] ?? null,
                ];
            }
        }
        usort($formatted, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        return $formatted;
    }

    /**
     * Format headers array into the ['Header: Value', ...] format needed by cURL.
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

    /**
     * Get default request options for wp_remote_request/cURL. Providers can override.
     * @param string $operation The operation ('chat', 'stream', 'models').
     * @return array Request options.
     */
    public function get_request_options(string $operation): array
    {
        return [
           'method'     => 'POST',
           'timeout'    => ($operation === 'stream') ? 120 : 60,
           'user-agent' => 'AIPKit/' . (defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.0') . '; ' . get_bloginfo('url'),
           'sslverify'  => apply_filters('https_local_ssl_verify', true),
        ];
    }

    /**
     * Build WP_Error data with retry timing when the provider returns Retry-After.
     *
     * @param mixed $response WordPress HTTP API response.
     * @return array<string, int>
     */
    public function build_http_error_data_with_retry_after($response, int $status_code): array
    {
        $error_data = ['status' => $status_code];
        $retry_after_header = wp_remote_retrieve_header($response, 'retry-after');
        if (is_array($retry_after_header)) {
            $retry_after_header = reset($retry_after_header);
        }
        if (is_numeric($retry_after_header)) {
            $error_data['retry_after'] = (int) ceil((float) $retry_after_header);
        } elseif (is_string($retry_after_header) && $retry_after_header !== '') {
            $retry_after_time = strtotime($retry_after_header);
            if ($retry_after_time !== false) {
                $error_data['retry_after'] = max(1, $retry_after_time - time());
            }
        }

        return $error_data;
    }

    // --- Abstract methods to be implemented by concrete strategies ---
    /**
     * @return string|\WP_Error
     */
    abstract public function build_api_url(string $operation, array $params);
    abstract public function get_api_headers(string $api_key, string $operation): array;
    abstract public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array;
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function parse_chat_response(array $decoded_response, array $request_data);
    abstract public function parse_error_response($response_body, int $status_code): string; // Kept abstract, to be implemented by each strategy
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function get_models(array $api_params);
    abstract public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array;
    abstract public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array;
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function generate_embeddings($input, array $api_params, array $options = []);
}
