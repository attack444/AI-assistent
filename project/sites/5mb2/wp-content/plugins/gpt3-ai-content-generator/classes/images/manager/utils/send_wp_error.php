<?php


namespace WPAICG\Images\Manager\Utils;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

function send_wp_error_logic(WP_Error $error): void
{
    $error_data = $error->get_error_data();
    $status = is_array($error_data) && isset($error_data['status']) ? $error_data['status'] : 400;
    $payload = [
        'message' => $error->get_error_message(),
        'code' => $error->get_error_code(),
    ];

    if (is_array($error_data) && !empty($error_data['quota_notice']) && is_array($error_data['quota_notice'])) {
        $payload['quota_notice'] = $error_data['quota_notice'];
    }

    wp_send_json_error($payload, $status);
}
