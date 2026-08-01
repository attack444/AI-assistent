<?php

namespace WPAICG\Core\Moderation;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_BannedIP_Checker
 *
 * Checks if a client's IP address is in the list of banned IPs.
 */
class AIPKit_BannedIP_Checker {

    /**
     * Checks if the client's IP is banned.
     *
     * @param string|null $client_ip The IP address of the user.
     * @param array $banned_ips_settings Associative array with 'ips' (string) and 'message' (string).
     * @return WP_Error|null WP_Error if banned, null otherwise.
     */
    public static function check(?string $client_ip, array $banned_ips_settings): ?WP_Error {
        if ($client_ip && !empty($banned_ips_settings['ips'])) {
            $banned_ips_list = array_map('trim', explode(',', $banned_ips_settings['ips']));
            if (in_array($client_ip, $banned_ips_list, true)) {
                $banned_ip_message = $banned_ips_settings['message'] ?: __('Access from your IP address has been blocked.', 'gpt3-ai-content-generator');
                return new WP_Error('ip_banned', $banned_ip_message, ['status' => 403]); // Forbidden
            }
        }
        return null;
    }
}