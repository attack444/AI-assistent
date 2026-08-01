<?php

namespace WPAICG\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_HTTP_Request
{
    /**
     * Runs an HTTP request for AI Puffer-owned provider calls.
     *
     * The WordPress AI connector approval guard matches outbound credentials in
     * the WP HTTP stack. AI Puffer stores and uses its own provider settings, so
     * a matching key in a separate connector plugin is a false attribution for
     * these internal provider requests.
     *
     * @param string $url Request URL.
     * @param array $args Request arguments.
     * @param bool $bypass_wp_ai_connector_approval Whether to bypass only the WP AI approval guard.
     * @return array|WP_Error
     */
    public static function request(string $url, array $args = [], bool $bypass_wp_ai_connector_approval = false)
    {
        if (!$bypass_wp_ai_connector_approval) {
            return wp_remote_request($url, $args);
        }

        return self::without_wp_ai_connector_approval_guard(
            static fn() => wp_remote_request($url, $args)
        );
    }

    /**
     * Temporarily removes only WordPress AI's connector approval HTTP guard.
     *
     * AI Puffer enforces WordPress AI Client gateway approvals before gateway
     * calls reach the provider layer. This wrapper avoids duplicate/false
     * approval blocks for AI Puffer's own provider requests.
     *
     * @param callable $callback Request callback.
     * @return mixed
     */
    private static function without_wp_ai_connector_approval_guard(callable $callback)
    {
        $removed = self::remove_wp_ai_connector_approval_filters();

        try {
            return $callback();
        } finally {
            self::restore_filters($removed);
        }
    }

    private static function remove_wp_ai_connector_approval_filters(): array
    {
        if (!class_exists('\WordPress\AI\Connector_Approval\Http_Guard', false)) {
            return [];
        }

        global $wp_filter;
        $hook = $wp_filter['pre_http_request'] ?? null;
        if (!is_object($hook) || empty($hook->callbacks) || !is_array($hook->callbacks)) {
            return [];
        }

        $removed = [];
        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ((array) $callbacks as $callback) {
                $function = $callback['function'] ?? null;
                if (!self::is_wp_ai_connector_approval_callback($function)) {
                    continue;
                }

                $accepted_args = isset($callback['accepted_args']) ? (int) $callback['accepted_args'] : 1;
                remove_filter('pre_http_request', $function, (int) $priority);
                $removed[] = [$function, (int) $priority, $accepted_args];
            }
        }

        return $removed;
    }

    private static function restore_filters(array $removed): void
    {
        foreach ($removed as $filter) {
            [$function, $priority, $accepted_args] = $filter;
            add_filter('pre_http_request', $function, $priority, $accepted_args);
        }
    }

    private static function is_wp_ai_connector_approval_callback($function): bool
    {
        return is_array($function)
            && is_object($function[0] ?? null)
            && $function[0] instanceof \WordPress\AI\Connector_Approval\Http_Guard;
    }
}
