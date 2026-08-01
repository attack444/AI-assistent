<?php

namespace WPAICG\Chat\Admin\Ajax\Traits;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

trait Trait_SendWPError {
    /**
     * Helper to send WP_Error as a standard JSON error response.
     *
     * @param WP_Error $error The WP_Error object.
     */
    protected function send_wp_error(WP_Error $error) {
        $error_data_for_json_response = [ // Renamed to avoid confusion with WP_Error's internal data
            'message' => $error->get_error_message(),
            'code' => $error->get_error_code(),
        ];
        $wp_error_internal_data = $error->get_error_data(); // This is the $data param passed to new WP_Error
        $status_code = isset($wp_error_internal_data['status']) && is_int($wp_error_internal_data['status'])
                       ? $wp_error_internal_data['status']
                       : 400; // Default to 400 Bad Request if not specified

        wp_send_json_error($error_data_for_json_response, $status_code);
    }
}