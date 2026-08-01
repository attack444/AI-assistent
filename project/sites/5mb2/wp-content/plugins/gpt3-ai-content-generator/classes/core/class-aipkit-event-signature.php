<?php

namespace WPAICG\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Builds request signatures for Universal Event Webhooks.
 */
class AIPKit_Event_Signature
{
    /**
     * Returns an HMAC signature header value or an empty string when no secret is configured.
     *
     * @param string $timestamp
     * @param string $body
     * @param string $secret
     * @return string
     */
    public static function build(string $timestamp, string $body, string $secret): string
    {
        $normalized_secret = trim($secret);
        if ($normalized_secret === '') {
            return '';
        }

        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $normalized_secret);
        return 'sha256=' . $signature;
    }
}
