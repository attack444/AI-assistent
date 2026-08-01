<?php

namespace WPAICG\Core\TokenManager\Helpers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Builds the guest quota identifiers used for server-side rate limiting.
 *
 * The browser-provided session ID remains the default identifier so existing
 * guests keep isolated quota buckets. A server-derived network fingerprint is
 * only used as a fallback when no client session ID is available.
 *
 * @param string|null $session_id Guest session ID supplied by the client.
 * @return array<int, string>
 */
function GetGuestQuotaIdentifiersLogic(?string $session_id): array {
    $normalized_session_id = is_string($session_id) ? sanitize_text_field($session_id) : '';
    $normalized_client_ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '';
    $normalized_user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
        : '';

    $identifiers = [];

    if ($normalized_session_id !== '') {
        $identifiers[] = $normalized_session_id;
    }

    $network_fingerprint = null;
    if ($normalized_session_id === '' && $normalized_client_ip !== '') {
        $fingerprint_source = $normalized_client_ip . '|' . strtolower(substr($normalized_user_agent, 0, 190));
        $network_fingerprint = 'net-' . substr(hash_hmac('sha256', $fingerprint_source, wp_salt('auth')), 0, 48);
        $identifiers[] = $network_fingerprint;
    }

    $identifiers = array_values(array_unique(array_filter(array_map(static function ($identifier): string {
        if (!is_string($identifier)) {
            return '';
        }

        return substr(sanitize_text_field($identifier), 0, 64);
    }, $identifiers))));

    /**
     * Filters the guest quota identifiers used for unauthenticated rate limiting.
     *
     * @param array<int, string> $identifiers
     * @param array<string, string|null> $context
     */
    $identifiers = apply_filters('aipkit_guest_quota_identifiers', $identifiers, [
        'session_id' => $normalized_session_id !== '' ? $normalized_session_id : null,
        'client_ip' => $normalized_client_ip !== '' ? $normalized_client_ip : null,
        'user_agent' => $normalized_user_agent !== '' ? $normalized_user_agent : null,
        'network_fingerprint' => $network_fingerprint,
    ]);

    if (!is_array($identifiers)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(static function ($identifier): string {
        if (!is_string($identifier)) {
            return '';
        }

        return substr(sanitize_text_field($identifier), 0, 64);
    }, $identifiers))));
}
