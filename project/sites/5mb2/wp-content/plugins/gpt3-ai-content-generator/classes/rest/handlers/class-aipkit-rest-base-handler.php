<?php

namespace WPAICG\REST\Handlers;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Base class for REST API Endpoint Handlers.
 * Provides common utility methods like permission checks.
 */
abstract class AIPKit_REST_Base_Handler {

    /**
     * Retrieve the stored public API key from WordPress options.
     * This key is used to authenticate external requests to the plugin's REST API.
     *
     * @return string The stored public API key, or an empty string if not set.
     */
    protected static function get_stored_public_api_key(): string {
        $opts = get_option('aipkit_options', []);
        $api_keys = $opts['api_keys'] ?? [];
        return isset($api_keys['public_api_key']) ? trim($api_keys['public_api_key']) : '';
    }

    /**
     * Returns whether external REST API access is explicitly enabled.
     */
    protected static function is_public_api_enabled(): bool {
        $opts = get_option('aipkit_options', []);
        $api_keys = isset($opts['api_keys']) && is_array($opts['api_keys']) ? $opts['api_keys'] : [];

        // Backward compatibility for installations saved before the explicit
        // enabled flag was introduced.
        if (!array_key_exists('public_api_enabled', $api_keys)) {
            return trim((string) ($api_keys['public_api_key'] ?? '')) !== '';
        }

        return (string) $api_keys['public_api_enabled'] === '1';
    }

    /**
     * Checks permissions for the REST API request.
     * Verifies if a public API key is configured and if the submitted key matches.
     * The key can be submitted either as 'aipkit_api_key' in the request parameters
     * or as a Bearer token in the 'Authorization' header.
     *
     * @param WP_REST_Request $request The current REST API request object.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function check_permissions(WP_REST_Request $request) {
        if (!self::is_public_api_enabled()) {
            return new WP_Error(
                'rest_aipkit_api_access_disabled',
                __('REST API access is disabled.', 'gpt3-ai-content-generator'),
                array('status' => 403)
            );
        }

        $stored_key = self::get_stored_public_api_key();

        // If no key is configured in settings, deny access.
        if (empty($stored_key)) {
            return new WP_Error(
                'rest_aipkit_no_api_key_configured',
                __('Public API access is not configured. Please set an API key in AIPKit settings.', 'gpt3-ai-content-generator'),
                array('status' => 403)
            );
        }

        // Check 'Authorization: Bearer <token>' header first.
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header) && strpos(strtolower($auth_header), 'bearer ') === 0) {
            $submitted_key_header = trim((string) substr($auth_header, 7));
            if (hash_equals($stored_key, $submitted_key_header)) {
                return true;
            }
        }

        // Check 'aipkit_api_key' request parameter as a fallback.
        $submitted_key_param = $request->get_param('aipkit_api_key');
        if (!empty($submitted_key_param) && is_string($submitted_key_param)) {
            if (hash_equals($stored_key, $submitted_key_param)) {
                return true;
            }
        }

        return new WP_Error(
            'rest_aipkit_invalid_api_key',
            __('Invalid or missing API Key.', 'gpt3-ai-content-generator'),
            array('status' => 401)
        );
    }

    /**
     * Helper to send a WP_Error object as a REST API error response.
     *
     * @param WP_Error $error The WP_Error object.
     * @return WP_Error A WP_Error object formatted for REST response.
     */
    protected function send_wp_error_response(WP_Error $error): WP_Error {
        $status_code = $error->get_error_data()['status'] ?? 500;
        if(!is_int($status_code) || $status_code < 400 || $status_code > 599) $status_code = 500;
        return new WP_Error(
            $error->get_error_code(),
            $error->get_error_message(),
            ['status' => $status_code]
        );
    }
}
