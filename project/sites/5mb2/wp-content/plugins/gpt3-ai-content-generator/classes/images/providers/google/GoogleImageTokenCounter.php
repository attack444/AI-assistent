<?php

namespace WPAICG\Images\Providers\Google;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses core WordPress hook names.

/**
 * Provides token counting utilities for Google Gemini image requests.
 */
class GoogleImageTokenCounter {
    /**
     * Count prompt tokens for a Gemini request payload.
     *
     * @param string $model_id  Gemini model identifier.
     * @param array  $api_params Provider connection parameters.
     * @param array  $parts     Gemini parts payload.
     * @return int|WP_Error Prompt token count or WP_Error on failure.
     */
    public static function count_prompt_tokens(string $model_id, array $api_params, array $parts) {
        if ($model_id === '' || empty($parts)) {
            return new WP_Error('google_count_tokens_invalid_request', __('Cannot count tokens for an empty model or payload.', 'gpt3-ai-content-generator'));
        }

        $api_key = (string) ($api_params['api_key'] ?? '');
        if ($api_key === '') {
            return new WP_Error('google_count_tokens_missing_key', __('Google API key is required for token counting.', 'gpt3-ai-content-generator'));
        }

        $base_url = !empty($api_params['base_url']) ? rtrim((string) $api_params['base_url'], '/') : 'https://generativelanguage.googleapis.com';
        $api_version = !empty($api_params['api_version']) ? (string) $api_params['api_version'] : 'v1beta';
        $endpoint = sprintf(
            '%1$s/%2$s/models/%3$s:countTokens?key=%4$s',
            $base_url,
            trim($api_version, '/'),
            urlencode($model_id),
            urlencode($api_key)
        );

        $request_body = wp_json_encode([
            'contents' => [[
                'parts' => $parts,
            ]],
        ]);

        if (!is_string($request_body) || $request_body === '') {
            return new WP_Error('google_count_tokens_encode_error', __('Failed to encode Google token count payload.', 'gpt3-ai-content-generator'));
        }

        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $request_body,
            'data_format' => 'body',
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            'user-agent' => 'AIPKit/' . (defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.0') . '; ' . get_bloginfo('url'),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('google_count_tokens_http_error', __('HTTP error occurred while counting Google prompt tokens.', 'gpt3-ai-content-generator'));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = __('Google token count request failed.', 'gpt3-ai-content-generator');
            if (is_array($decoded) && !empty($decoded['error']['message'])) {
                $error_message = (string) $decoded['error']['message'];
            }
            return new WP_Error('google_count_tokens_api_error', $error_message, ['status' => $status_code]);
        }

        if (!is_array($decoded)) {
            return new WP_Error('google_count_tokens_decode_error', __('Failed to parse Google token count response.', 'gpt3-ai-content-generator'));
        }

        $prompt_tokens = absint($decoded['totalTokens'] ?? 0);
        if ($prompt_tokens <= 0) {
            return new WP_Error('google_count_tokens_missing_total', __('Google token count response did not include totalTokens.', 'gpt3-ai-content-generator'));
        }

        return $prompt_tokens;
    }
}

