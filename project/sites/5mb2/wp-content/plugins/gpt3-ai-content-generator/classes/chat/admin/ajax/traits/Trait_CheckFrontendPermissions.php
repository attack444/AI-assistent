<?php

namespace WPAICG\Chat\Admin\Ajax\Traits;

use WPAICG\Utils\AIPKit_CORS_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

trait Trait_CheckFrontendPermissions {
    /**
     * Helper to check nonce for FRONTEND actions (can be called by admin JS).
     * Now includes CORS checking for embedded chatbots.
     *
     * @param string $nonce_action The nonce action string.
     * @return bool|WP_Error True if permissions are valid, WP_Error otherwise.
     */
    protected function check_frontend_permissions(string $nonce_action = 'aipkit_frontend_chat_nonce') {
        // Handle preflight OPTIONS request
        AIPKit_CORS_Manager::handle_preflight_request();

        // Check CORS if bot_id is provided in the request and embed feature is available
        $bot_id = 0;
        if (isset($_POST['bot_id'])) {
            $bot_id = absint(wp_unslash($_POST['bot_id']));
        } elseif (isset($_GET['bot_id'])) {
            $bot_id = absint(wp_unslash($_GET['bot_id']));
        }

        if ($bot_id > 0) {
            // Only perform CORS check if embed feature is available
            if (class_exists('\WPAICG\aipkit_dashboard') && 
                \WPAICG\aipkit_dashboard::is_pro_plan()) {
                
                $origin_allowed = AIPKit_CORS_Manager::check_and_set_cors_headers($bot_id);
                if (!$origin_allowed) {
                    return new WP_Error('cors_denied', __('This domain is not permitted to access the chatbot.', 'gpt3-ai-content-generator'), ['status' => 403]);
                }
            }
        }

        // Use check_ajax_referer for standard WP behavior, checking $_REQUEST
        if (!check_ajax_referer($nonce_action, '_ajax_nonce', false)) {
            return new WP_Error('nonce_failure', __('Security check failed (nonce).', 'gpt3-ai-content-generator'), ['status' => 403]);
        }
        // No capability check here, as it's primarily for frontend/guest use,
        // but can be called by admin JS (e.g., sidebar). Specific methods might add checks.
        return true;
    }
}
