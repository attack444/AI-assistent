<?php

namespace WPAICG\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles outbound webhook delivery and retry policy.
 */
class AIPKit_Event_Delivery_Manager
{
    /**
     * Delivers an event envelope to all matched endpoints.
     *
     * @param string $event_name
     * @param array<string, mixed> $envelope
     * @param array<int, array<string, mixed>> $targets
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public static function deliver(string $event_name, array $envelope, array $targets, array $options = []): array
    {
        $settings = AIPKit_Event_Webhooks_Settings::get_settings();
        $results = [];
        $normalized_options = self::normalize_delivery_options($options);

        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $results[] = self::deliver_to_target($event_name, $envelope, $target, $settings, $normalized_options);
        }

        return $results;
    }

    /**
     * Returns the configured retry delays in seconds.
     *
     * @return array<int, float>
     */
    public static function get_retry_delays(): array
    {
        $retry_delays = apply_filters('aipkit_event_webhooks_retry_delays', [0.0, 0.25, 1.0]);
        if (!is_array($retry_delays) || empty($retry_delays)) {
            return [0.0];
        }

        return array_values(array_map(static function ($delay): float {
            return max(0.0, (float) $delay);
        }, $retry_delays));
    }

    /**
     * Performs delivery to one endpoint with retry handling.
     *
     * @param string $event_name
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $target
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function deliver_to_target(string $event_name, array $envelope, array $target, array $settings, array $options = []): array
    {
        $delivery_uuid = wp_generate_uuid4();
        $body = wp_json_encode($envelope);

        if (!is_string($body) || $body === '') {
            return [
                'delivery_id' => $delivery_uuid,
                'endpoint_id' => sanitize_key((string) ($target['id'] ?? '')),
                'status' => 'failed',
                'attempt_count' => 0,
                'http_status' => null,
                'error_message' => __('Failed to encode webhook payload.', 'gpt3-ai-content-generator'),
                'should_retry' => false,
                'retry_delay' => 0.0,
            ];
        }

        $secret = sanitize_text_field((string) ($settings['signing_secret'] ?? ''));
        $retry_delays = isset($options['retry_delays']) && is_array($options['retry_delays'])
            ? $options['retry_delays']
            : self::get_retry_delays();
        $attempt_offset = max(0, (int) ($options['attempt_offset'] ?? 0));
        $max_attempts_per_call = max(1, (int) ($options['max_attempts_per_call'] ?? count($retry_delays)));
        $sleep_between_attempts = !empty($options['sleep_between_attempts']);
        $attempt_count = 0;
        $last_http_status = null;
        $last_error_message = '';
        $should_retry_after_failure = false;
        $next_retry_delay = 0.0;

        foreach ($retry_delays as $attempt_index => $retry_delay) {
            if (($attempt_index + 1) <= $attempt_offset) {
                continue;
            }

            if ($attempt_count >= $max_attempts_per_call) {
                break;
            }

            $attempt_count = $attempt_index + 1;
            $timestamp = (string) time();
            $request_headers = self::build_request_headers($event_name, $envelope, $delivery_uuid, $timestamp, $body, $secret);
            $request_args = self::build_request_args($request_headers, $body);
            $response = wp_remote_post(esc_url_raw((string) ($target['url'] ?? '')), $request_args);

            if (!is_wp_error($response)) {
                $last_http_status = (int) wp_remote_retrieve_response_code($response);
                if ($last_http_status >= 200 && $last_http_status < 300) {
                    $result = [
                        'delivery_id' => $delivery_uuid,
                        'endpoint_id' => sanitize_key((string) ($target['id'] ?? '')),
                        'status' => 'delivered',
                        'attempt_count' => $attempt_count,
                        'http_status' => $last_http_status,
                        'error_message' => '',
                        'should_retry' => false,
                        'retry_delay' => 0.0,
                    ];

                    do_action('aipkit_event_webhooks_delivery_completed', $result, $target, $envelope);
                    return $result;
                }

                $last_error_message = sprintf(
                    /* translators: %d: HTTP response code */
                    __('Webhook returned HTTP %d.', 'gpt3-ai-content-generator'),
                    $last_http_status
                );
            } else {
                $last_error_message = $response->get_error_message();
                $last_http_status = null;
            }

            $should_retry = self::should_retry($response, $last_http_status, $attempt_count, count($retry_delays));
            $should_retry_after_failure = $should_retry;
            $next_retry_delay = $should_retry ? max(0.0, (float) $retry_delay) : 0.0;
            if (!$should_retry) {
                break;
            }

            if ($sleep_between_attempts && $retry_delay > 0) {
                usleep((int) round($retry_delay * 1000000));
            }
        }

        $result = [
            'delivery_id' => $delivery_uuid,
            'endpoint_id' => sanitize_key((string) ($target['id'] ?? '')),
            'status' => 'failed',
            'attempt_count' => $attempt_count,
            'http_status' => $last_http_status,
            'error_message' => $last_error_message,
            'should_retry' => $should_retry_after_failure,
            'retry_delay' => $next_retry_delay,
        ];

        do_action('aipkit_event_webhooks_delivery_failed', $result, $target, $envelope);
        return $result;
    }

    /**
     * Returns the retry delay schedule in seconds.
     *
     * @return array<int, float>
     */
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function normalize_delivery_options(array $options): array
    {
        $retry_delays = isset($options['retry_delays']) && is_array($options['retry_delays'])
            ? $options['retry_delays']
            : self::get_retry_delays();
        if (empty($retry_delays)) {
            $retry_delays = [0.0];
        }

        return [
            'retry_delays' => array_values(array_map(static function ($delay): float {
                return max(0.0, (float) $delay);
            }, $retry_delays)),
            'attempt_offset' => max(0, (int) ($options['attempt_offset'] ?? 0)),
            'max_attempts_per_call' => max(1, (int) ($options['max_attempts_per_call'] ?? count($retry_delays))),
            'sleep_between_attempts' => array_key_exists('sleep_between_attempts', $options)
                ? !empty($options['sleep_between_attempts'])
                : true,
        ];
    }

    /**
     * Builds request headers for one delivery attempt.
     *
     * @param string $event_name
     * @param array<string, mixed> $envelope
     * @param string $delivery_uuid
     * @param string $timestamp
     * @param string $body
     * @param string $secret
     * @return array<string, string>
     */
    private static function build_request_headers(string $event_name, array $envelope, string $delivery_uuid, string $timestamp, string $body, string $secret): array
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'User-Agent' => sprintf(
                'AIPKit/%s (%s)',
                defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.0.0',
                site_url()
            ),
            'X-AIPKit-Event' => $event_name,
            'X-AIPKit-Event-Id' => sanitize_text_field((string) ($envelope['id'] ?? '')),
            'X-AIPKit-Delivery-Id' => $delivery_uuid,
            'X-AIPKit-Idempotency-Key' => sanitize_text_field((string) ($envelope['idempotency_key'] ?? '')),
            'X-AIPKit-Schema-Version' => sanitize_text_field((string) ($envelope['schema_version'] ?? '')),
            'X-AIPKit-Timestamp' => $timestamp,
        ];

        $signature = AIPKit_Event_Signature::build($timestamp, $body, $secret);
        if ($signature !== '') {
            $headers['X-AIPKit-Signature'] = $signature;
            $headers['X-AIPKit-Signature-Alg'] = 'sha256';
        }

        /**
         * Filters outbound webhook headers for Universal Event Webhooks.
         *
         * @param array<string, string> $headers
         * @param array<string, mixed>  $envelope
         * @param string                $delivery_uuid
         */
        $headers = apply_filters('aipkit_event_webhooks_request_headers', $headers, $envelope, $delivery_uuid);

        return is_array($headers) ? $headers : [];
    }

    /**
     * Builds wp_remote_post arguments.
     *
     * @param array<string, string> $headers
     * @param string $body
     * @return array<string, mixed>
     */
    private static function build_request_args(array $headers, string $body): array
    {
        $args = [
            'timeout' => 5,
            'redirection' => 2,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => $headers,
            'body' => $body,
            'data_format' => 'body',
        ];

        $filtered_args = apply_filters('aipkit_event_webhooks_request_args', $args, $headers, $body);
        return is_array($filtered_args) ? $filtered_args : $args;
    }

    /**
     * Determines whether a failed attempt should be retried.
     *
     * @param mixed $response
     * @param int|null $http_status
     * @param int $attempt_count
     * @param int $max_attempts
     * @return bool
     */
    private static function should_retry($response, ?int $http_status, int $attempt_count, int $max_attempts): bool
    {
        if ($attempt_count >= $max_attempts) {
            return false;
        }

        $is_retryable = is_wp_error($response)
            || $http_status === 408
            || $http_status === 409
            || $http_status === 425
            || $http_status === 429
            || ($http_status !== null && $http_status >= 500);

        $filtered = apply_filters('aipkit_event_webhooks_should_retry', $is_retryable, $response, $http_status, $attempt_count, $max_attempts);
        return (bool) $filtered;
    }
}
