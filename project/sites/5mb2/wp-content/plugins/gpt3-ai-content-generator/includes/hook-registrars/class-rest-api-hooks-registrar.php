<?php

namespace WPAICG\Includes\HookRegistrars;

use WPAICG\REST\AIPKit_REST_Controller;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Registers REST API hooks.
 */
class Rest_Api_Hooks_Registrar {

    public static function register(?AIPKit_REST_Controller $rest_controller) { // Nullable
        if ($rest_controller && method_exists($rest_controller, 'register_routes')) {
            add_action('rest_api_init', [$rest_controller, 'register_routes']);
        }
    }
}