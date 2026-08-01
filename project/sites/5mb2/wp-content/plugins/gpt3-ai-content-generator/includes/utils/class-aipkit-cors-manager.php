<?php

namespace WPAICG\Utils;

use WPAICG\Chat\Storage\BotStorage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Comprehensive CORS (Cross-Origin Resource Sharing) manager for embedded chatbots.
 * Handles CORS validation, header setting, and WordPress AJAX integration.
 */
class AIPKit_CORS_Manager
{
    /**
     * List of AJAX actions that require CORS support for embedded chatbots.
     */
    private static $cors_enabled_actions = [
        'aipkit_get_frontend_chat_nonce',
        'aipkit_get_conversations_list',
        'aipkit_get_conversation_history',
        'aipkit_store_feedback',
        'aipkit_generate_speech',
        'aipkit_transcribe_audio',
        'aipkit_delete_single_conversation',
        'aipkit_cache_sse_message',
        'aipkit_frontend_chat_stream',
        'aipkit_chat_generate_image',
    ];

    /**
     * Initialize the CORS manager.
     */
    public static function init(): void
    {
        // Only initialize CORS handling if user has pro plan and embed addon is enabled
        if (!self::is_embed_feature_available()) {
            return;
        }

        // Hook into WordPress admin_init to catch AJAX requests early
        add_action('admin_init', [self::class, 'handle_ajax_cors']);
    }

    /**
     * Check if the embed anywhere feature is available.
     *
     * @return bool True if embed feature is available, false otherwise.
     */
    private static function is_embed_feature_available(): bool
    {
        // Check if dashboard class exists
        if (!class_exists('\WPAICG\aipkit_dashboard')) {
            return false;
        }

        // Check if user is on pro plan
        return \WPAICG\aipkit_dashboard::is_pro_plan();
    }

    /**
     * Handle CORS for AJAX requests at the WordPress level.
     */
    public static function handle_ajax_cors(): void
    {
        // Only process AJAX requests
        if (!wp_doing_ajax()) {
            return;
        }

        // Double-check that embed feature is available
        if (!self::is_embed_feature_available()) {
            return;
        }

        // Check if this is one of our CORS-enabled actions
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is non-mutating routing logic that runs before individual AJAX handlers verify their own nonces.
        $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        
        if (!in_array($action, self::$cors_enabled_actions, true)) {
            return;
        }

        // Handle preflight OPTIONS request
        self::handle_preflight_request();

        // Extract bot_id for CORS check
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- This block only scopes CORS headers before the target handler verifies its own nonce.
        $bot_id = 0;
        if (isset($_POST['bot_id'])) {
            $bot_id = absint(wp_unslash($_POST['bot_id']));
        } elseif (isset($_GET['bot_id'])) {
            $bot_id = absint(wp_unslash($_GET['bot_id']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        // Set CORS headers if bot_id is provided and origin is allowed
        if ($bot_id > 0) {
            $origin_allowed = self::check_and_set_cors_headers($bot_id);
            if (!$origin_allowed) {
                // If origin is not allowed, we'll let the individual handlers deal with it
                // but we still need to set some basic CORS headers for the error response
                self::set_cors_headers('null');
            }
        } else {
            // If no bot_id, set basic CORS headers anyway for error responses
            self::set_cors_headers();
        }
    }

    /**
     * Check if the request origin is allowed for a specific bot and set CORS headers if needed.
     *
     * @param int $bot_id The chatbot ID.
     * @param BotStorage|null $bot_storage Optional bot storage instance.
     * @return bool True if origin is allowed, false otherwise.
     */
    public static function check_and_set_cors_headers(int $bot_id, ?BotStorage $bot_storage = null): bool
    {
        // Get the Origin header from the request
        $request_origin = null;
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $request_origin = esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN']));
        }

        // If no origin header, this is likely a same-origin request, allow it
        if (empty($request_origin)) {
            return true;
        }

        // Check if this is actually a cross-origin request
        $is_cross_origin = false;
        $site_url = get_site_url();
        $site_parsed = wp_parse_url($site_url);
        $origin_parsed = wp_parse_url($request_origin);
        
        if ($origin_parsed && $site_parsed && 
            ($origin_parsed['host'] !== $site_parsed['host'] || 
             ($origin_parsed['scheme'] ?? 'http') !== ($site_parsed['scheme'] ?? 'http'))) {
            $is_cross_origin = true;
        }

        // If this is not a cross-origin request, allow it (same domain)
        if (!$is_cross_origin) {
            return true;
        }

        // For cross-origin requests, check if embed feature is available
        if (!self::is_embed_feature_available()) {
            return false;
        }

        // Get bot storage if not provided
        if (!$bot_storage) {
            $bot_storage = new BotStorage();
        }

        // Get bot settings
        $bot_settings = $bot_storage->get_chatbot_settings($bot_id);
        if (empty($bot_settings)) {
            return false;
        }

        // Get allowed domains from bot settings
        $allowed_domains_str = $bot_settings['embed_allowed_domains'] ?? '';
        $allowed_domains = preg_split('/[\s,]+/', $allowed_domains_str, -1, PREG_SPLIT_NO_EMPTY);

        $origin_is_allowed = false;
        $origin_to_allow = '*'; // Default to all if no domains are set

        if (empty($allowed_domains)) {
            // If no domains are specified in settings, allow any origin that sends an Origin header
            $origin_is_allowed = true;
            $origin_to_allow = $request_origin;
        } else {
            // Normalize the request origin by removing a potential trailing slash
            $normalized_request_origin = rtrim($request_origin, '/');
            foreach ($allowed_domains as $allowed_domain) {
                $normalized_allowed_domain = rtrim($allowed_domain, '/');
                if ($normalized_request_origin === $normalized_allowed_domain) {
                    $origin_is_allowed = true;
                    $origin_to_allow = $request_origin; // Set specific origin for the header
                    break;
                }
            }
        }

        // Set CORS headers if origin is allowed
        if ($origin_is_allowed) {
            self::set_cors_headers($origin_to_allow);
        }

        return $origin_is_allowed;
    }

    /**
     * Set CORS headers for the response.
     *
     * @param string $allowed_origin The origin to allow in the Access-Control-Allow-Origin header.
     * @return void
     */
    public static function set_cors_headers(string $allowed_origin = '*'): void
    {
        // Set CORS headers
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
        header('Access-Control-Allow-Credentials: true');
    }

    /**
     * Handle preflight OPTIONS request for CORS.
     *
     * @return void
     */
    public static function handle_preflight_request(): void
    {
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) : '';
        if ($request_method === 'OPTIONS') {
            self::set_cors_headers();
            status_header(200);
            exit;
        }
    }
}
