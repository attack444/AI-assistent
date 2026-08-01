<?php

namespace WPAICG\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses the core https_local_ssl_verify hook.

/**
 * Loopback worker, cron fallback, and queue health support for async delivery.
 */
class AIPKit_Event_Queue_Worker
{
    public const AJAX_ACTION = 'aipkit_process_event_delivery_queue';
    public const CRON_HOOK = 'aipkit_process_event_delivery_queue_cron';
    private const DEFAULT_BATCH_SIZE = 5;
    private const CLEANUP_LAST_RUN_OPTION = 'aipkit_event_delivery_queue_cleanup_last_run_gmt';
    private const QUEUE_CONTEXT_KEY = 'aipkit_queue';
    private const QUEUE_META_EMITTED_KEY = 'emitted_hooks_fired';
    private const QUEUE_META_FAILURE_MESSAGES_KEY = 'failed_messages';
    private static bool $shutdown_processing_registered = false;
    private static bool $shutdown_processing_needed = false;

    public static function register_hooks(): void
    {
        add_action('init', [__CLASS__, 'bootstrap_cron']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_process_queue']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_process_queue']);
        add_action(self::CRON_HOOK, [__CLASS__, 'process_cron_queue']);
        add_action('aipkit_event_delivery_queue_process_job', [__CLASS__, 'process_job']);
    }

    public static function bootstrap_cron(): void
    {
        if (!self::should_bootstrap_cron()) {
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $recurrence = (string) apply_filters('aipkit_event_delivery_queue_cron_recurrence', 'aipkit_five_minutes');
            if ($recurrence === '') {
                $recurrence = 'aipkit_five_minutes';
            }

            wp_schedule_event(time() + MINUTE_IN_SECONDS, $recurrence, self::CRON_HOOK);
        }
    }

    public static function unschedule_cron(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @param array<string, mixed> $queue_job
     * @param array<string, mixed> $dispatch_result
     * @param array<string, mixed> $event_context
     */
    public static function maybe_trigger_async_worker(array $queue_job, array $dispatch_result = [], array $event_context = []): void
    {
        $job_uuid = sanitize_text_field((string) ($queue_job['job_uuid'] ?? ''));
        $job_status = sanitize_key((string) ($queue_job['status'] ?? ''));
        if ($job_uuid === '' || $job_status !== 'pending') {
            return;
        }

        $sync_delivery_enabled = !empty($dispatch_result['sync_delivery_enabled']);
        if ($sync_delivery_enabled) {
            return;
        }

        $event_name = sanitize_text_field((string) ($dispatch_result['event_name'] ?? ''));
        $envelope = isset($dispatch_result['envelope']) && is_array($dispatch_result['envelope'])
            ? $dispatch_result['envelope']
            : [];
        $targets = isset($dispatch_result['targets']) && is_array($dispatch_result['targets'])
            ? $dispatch_result['targets']
            : self::get_targets_from_context($event_context);
        $async_delivery_enabled = array_key_exists('async_delivery_enabled', $dispatch_result)
            ? !empty($dispatch_result['async_delivery_enabled'])
            : self::is_async_mode_enabled($event_name, $envelope, $event_context, $targets);

        if (!$async_delivery_enabled) {
            return;
        }

        self::schedule_immediate_cron_fallback();

        $request_url = admin_url('admin-ajax.php');
        $request_args = [
            'blocking' => false,
            'timeout' => max(0.1, (float) apply_filters('aipkit_event_delivery_queue_worker_timeout', 1.0)),
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            'body' => [
                'action' => self::AJAX_ACTION,
                'worker_token' => self::get_worker_token(),
                'batch_size' => self::DEFAULT_BATCH_SIZE,
            ],
        ];

        do_action('aipkit_event_delivery_queue_worker_before_trigger', $queue_job, $dispatch_result, $event_context, $request_args);
        $triggered = self::trigger_worker_via_socket($request_url, $request_args);

        if (!$triggered) {
            wp_remote_post($request_url, $request_args);
        }

        if (function_exists('spawn_cron')) {
            spawn_cron();
        }

        self::schedule_shutdown_processing();
    }

    public static function ajax_process_queue(): void
    {
        if (!self::is_worker_request_authorized()) {
            wp_send_json_error([
                'message' => __('Unauthorized event queue worker request.', 'gpt3-ai-content-generator'),
            ], 403);
        }

        if (!self::is_processing_enabled()) {
            wp_send_json_success([
                'processed_count' => 0,
                'message' => __('Event queue processing is not enabled yet.', 'gpt3-ai-content-generator'),
            ]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Internal worker requests are authorized with a signed worker token instead of a nonce.
        $batch_size = isset($_REQUEST['batch_size']) ? max(1, min(20, (int) $_REQUEST['batch_size'])) : self::DEFAULT_BATCH_SIZE;
        $processed_count = self::process_queue_batch($batch_size);

        wp_send_json_success([
            'processed_count' => $processed_count,
        ]);
    }

    public static function process_queue_batch(int $batch_size = self::DEFAULT_BATCH_SIZE): int
    {
        if (!self::is_processing_enabled()) {
            return 0;
        }

        if (!has_action('aipkit_event_delivery_queue_process_job')) {
            return 0;
        }

        self::maybe_cleanup_expired_jobs();

        $recovery_summary = AIPKit_Event_Queue_Store::recover_stale_jobs();
        if (!empty($recovery_summary['recovered_count']) || !empty($recovery_summary['failed_count'])) {
            do_action('aipkit_event_delivery_queue_stale_jobs_recovered', $recovery_summary);
        }

        $claimed_jobs = AIPKit_Event_Queue_Store::claim_due_jobs($batch_size);
        if (empty($claimed_jobs)) {
            return 0;
        }

        foreach ($claimed_jobs as $job) {
            do_action('aipkit_event_delivery_queue_process_job', $job);
        }

        return count($claimed_jobs);
    }

    public static function process_cron_queue(): void
    {
        $batch_size = (int) apply_filters('aipkit_event_delivery_queue_cron_batch_size', self::DEFAULT_BATCH_SIZE);
        self::process_queue_batch(max(1, min(20, $batch_size)));
    }

    /**
     * Returns queue health information including worker scheduling state.
     *
     * @return array<string, mixed>
     */
    public static function get_health_snapshot(): array
    {
        $queue_snapshot = AIPKit_Event_Queue_Store::get_health_snapshot();
        $next_cron_timestamp = wp_next_scheduled(self::CRON_HOOK);

        return [
            'processing_enabled' => self::is_processing_enabled(),
            'cron_hook' => self::CRON_HOOK,
            'next_cron_timestamp' => $next_cron_timestamp ? (int) $next_cron_timestamp : 0,
            'next_cron_gmt' => $next_cron_timestamp ? gmdate('Y-m-d H:i:s', (int) $next_cron_timestamp) : '',
            'queue' => $queue_snapshot,
        ];
    }

    /**
     * @param array<string, mixed> $job
     */
    public static function process_job(array $job): void
    {
        $job_uuid = sanitize_text_field((string) ($job['job_uuid'] ?? ''));
        if ($job_uuid === '') {
            return;
        }

        try {
            $envelope = self::decode_json_array((string) ($job['envelope_json'] ?? ''));
            $context = self::decode_json_array((string) ($job['context_json'] ?? ''));
            $event_name = sanitize_text_field((string) ($job['event_name'] ?? ($envelope['event'] ?? '')));

            if ($event_name === '' || empty($envelope)) {
                AIPKit_Event_Queue_Store::mark_job_failed($job_uuid, __('Queued event payload is incomplete.', 'gpt3-ai-content-generator'));
                return;
            }

            $queue_meta = self::get_queue_meta($context);
            $targets = self::resolve_targets($event_name, $envelope, $context);
            $retry_schedule = AIPKit_Event_Delivery_Manager::get_retry_delays();
            $current_attempt = max(1, (int) ($job['attempt_count'] ?? 1));
            $deliveries = !empty($targets)
                ? AIPKit_Event_Delivery_Manager::deliver($event_name, $envelope, $targets, [
                    'retry_delays' => $retry_schedule,
                    'attempt_offset' => max(0, $current_attempt - 1),
                    'max_attempts_per_call' => 1,
                    'sleep_between_attempts' => false,
                ])
                : [];

            $dispatch_result = [
                'event_name' => $event_name,
                'envelope' => $envelope,
                'targets' => $targets,
                'target_count' => count($targets),
                'queue_job' => [
                    'job_uuid' => $job_uuid,
                    'event_id' => sanitize_text_field((string) ($job['event_id'] ?? '')),
                    'event_name' => $event_name,
                    'status' => 'processing',
                ],
                'queue_error' => null,
                'queued_count' => 1,
                'deliveries' => $deliveries,
                'delivered_count' => count(array_filter($deliveries, static function ($delivery): bool {
                    return is_array($delivery) && (($delivery['status'] ?? '') === 'delivered');
                })),
                'failed_count' => count(array_filter($deliveries, static function ($delivery): bool {
                    return is_array($delivery) && (($delivery['status'] ?? '') === 'failed');
                })),
                'async_delivery_requested' => true,
                'async_delivery_enabled' => true,
                'sync_delivery_enabled' => false,
                'processed_by_worker' => true,
            ];

            if (empty($queue_meta[self::QUEUE_META_EMITTED_KEY])) {
                do_action('aipkit_event_webhooks_emitted', $dispatch_result, $context);
                do_action('aipkit_event_webhooks_emitted_' . self::get_hook_suffix($event_name), $dispatch_result, $context);
                $queue_meta[self::QUEUE_META_EMITTED_KEY] = true;
            }

            $queue_meta = self::merge_failure_messages($queue_meta, $deliveries);
            $retry_state = self::build_retry_state($targets, $deliveries);
            if (!empty($retry_state['targets'])) {
                $context = self::set_queue_meta($context, array_merge($queue_meta, [
                    'targets' => $retry_state['targets'],
                ]));

                $rescheduled = AIPKit_Event_Queue_Store::update_job_state($job_uuid, [
                    'status' => 'pending',
                    'locked_at' => null,
                    'processed_at' => null,
                    'available_at' => gmdate('Y-m-d H:i:s', time() + (int) ceil((float) ($retry_state['delay'] ?? 0.0))),
                    'last_error_message' => sanitize_text_field((string) ($retry_state['message'] ?? '')),
                    'target_count' => count($retry_state['targets']),
                    'context_json' => $context,
                ]);

                if ($rescheduled) {
                    $pending_job = [
                        'job_uuid' => $job_uuid,
                        'status' => 'pending',
                    ];

                    if ((float) ($retry_state['delay'] ?? 0.0) <= 0.0) {
                        self::maybe_trigger_async_worker($pending_job, $dispatch_result, $context);
                    }

                    do_action('aipkit_event_delivery_queue_job_rescheduled', $job, $dispatch_result, $context, $retry_state);
                    return;
                }
            }

            $has_failed_deliveries = !empty(array_filter($deliveries, static function ($delivery): bool {
                return is_array($delivery) && (($delivery['status'] ?? '') === 'failed');
            }));
            $persisted_failure_messages = isset($queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY]) && is_array($queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY])
                ? $queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY]
                : [];

            if ($has_failed_deliveries || !empty($persisted_failure_messages)) {
                $error_message = self::build_final_failure_message($deliveries, $persisted_failure_messages);
                $failed_targets = self::extract_failed_targets($targets, $deliveries);
                if (!empty($failed_targets)) {
                    $context = self::set_queue_meta($context, array_merge($queue_meta, [
                        'targets' => $failed_targets,
                    ]));
                }

                AIPKit_Event_Queue_Store::mark_job_failed($job_uuid, $error_message, [
                    'context_json' => $context,
                    'target_count' => !empty($failed_targets) ? count($failed_targets) : count($targets),
                ]);
                do_action('aipkit_event_delivery_queue_job_failed', $job, $error_message, $dispatch_result, $context);
                return;
            }

            AIPKit_Event_Queue_Store::mark_job_completed($job_uuid);
            do_action('aipkit_event_delivery_queue_job_completed', $job, $dispatch_result, $context);
        } catch (\Throwable $throwable) {
            $error_message = sanitize_text_field($throwable->getMessage());
            if ($error_message === '') {
                $error_message = __('Event delivery worker processing failed.', 'gpt3-ai-content-generator');
            }

            AIPKit_Event_Queue_Store::mark_job_failed($job_uuid, $error_message);
            do_action('aipkit_event_delivery_queue_job_failed', $job, $error_message);
        }
    }

    /**
     * @param array<string, mixed> $event_context
     * @param array<string, mixed> $envelope
     * @param array<int, array<string, mixed>> $targets
     */
    private static function is_async_mode_enabled(string $event_name, array $envelope = [], array $event_context = [], array $targets = []): bool
    {
        return (bool) apply_filters(
            'aipkit_event_delivery_queue_async_enabled',
            false,
            $event_name,
            $envelope,
            $event_context,
            $targets
        );
    }

    /**
     * @param array<string, mixed> $event_context
     * @return array<int, array<string, mixed>>
     */
    private static function get_targets_from_context(array $event_context = []): array
    {
        $queue_context = isset($event_context[self::QUEUE_CONTEXT_KEY]) && is_array($event_context[self::QUEUE_CONTEXT_KEY])
            ? $event_context[self::QUEUE_CONTEXT_KEY]
            : [];

        $targets = isset($queue_context['targets']) && is_array($queue_context['targets'])
            ? $queue_context['targets']
            : [];

        return array_values(array_filter($targets, static function ($target): bool {
            return is_array($target);
        }));
    }

    private static function is_processing_enabled(): bool
    {
        return (bool) apply_filters('aipkit_event_delivery_queue_processing_enabled', true);
    }

    private static function is_worker_request_authorized(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Internal loopback requests use a deterministic worker token.
        $submitted_token = sanitize_text_field((string) wp_unslash($_REQUEST['worker_token'] ?? ''));
        if ($submitted_token === '') {
            return false;
        }

        return hash_equals(self::get_worker_token(), $submitted_token);
    }

    private static function get_worker_token(): string
    {
        return hash_hmac('sha256', self::AJAX_ACTION, wp_salt('auth'));
    }

    private static function maybe_cleanup_expired_jobs(): void
    {
        $cleanup_interval_seconds = max(
            HOUR_IN_SECONDS,
            (int) apply_filters('aipkit_event_delivery_queue_cleanup_interval_seconds', DAY_IN_SECONDS)
        );

        $last_run_gmt = sanitize_text_field((string) get_option(self::CLEANUP_LAST_RUN_OPTION, ''));
        if ($last_run_gmt !== '') {
            $last_run_timestamp = strtotime($last_run_gmt . ' GMT');
            if (is_int($last_run_timestamp) && $last_run_timestamp > 0 && (time() - $last_run_timestamp) < $cleanup_interval_seconds) {
                return;
            }
        }

        update_option(self::CLEANUP_LAST_RUN_OPTION, gmdate('Y-m-d H:i:s'), false);
        $cleanup_summary = AIPKit_Event_Queue_Store::cleanup_expired_jobs();

        if (!empty($cleanup_summary['deleted_completed']) || !empty($cleanup_summary['deleted_failed'])) {
            do_action('aipkit_event_delivery_queue_cleanup_completed', $cleanup_summary);
        }
    }

    private static function schedule_immediate_cron_fallback(): void
    {
        if (!function_exists('wp_schedule_single_event')) {
            return;
        }

        wp_schedule_single_event(time(), self::CRON_HOOK);
    }

    private static function schedule_shutdown_processing(): void
    {
        self::$shutdown_processing_needed = true;

        if (self::$shutdown_processing_registered) {
            return;
        }

        self::$shutdown_processing_registered = true;
        add_action('shutdown', [__CLASS__, 'process_shutdown_queue'], 9999);
    }

    public static function process_shutdown_queue(): void
    {
        if (!self::$shutdown_processing_needed || !self::is_processing_enabled()) {
            return;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Internal shutdown guard only inspects the current AJAX action for routing.
            $action = sanitize_key((string) ($_REQUEST['action'] ?? ''));
            if ($action === self::AJAX_ACTION) {
                return;
            }
        }

        self::$shutdown_processing_needed = false;
        self::maybe_finish_response();

        $batch_size = (int) apply_filters('aipkit_event_delivery_queue_shutdown_batch_size', 3);
        self::process_queue_batch(max(1, min(5, $batch_size)));
    }

    private static function maybe_finish_response(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }

    /**
     * Sends a fire-and-forget POST request to the worker endpoint using a raw
     * socket so local environments do not depend entirely on WP HTTP loopbacks.
     *
     * @param array<string, mixed> $request_args
     */
    private static function trigger_worker_via_socket(string $request_url, array $request_args = []): bool
    {
        if (!function_exists('wp_parse_url')) {
            return false;
        }

        $url_parts = wp_parse_url($request_url);
        if (!is_array($url_parts) || empty($url_parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) ($url_parts['scheme'] ?? 'http'));
        $host = (string) $url_parts['host'];
        $port = isset($url_parts['port'])
            ? (int) $url_parts['port']
            : ($scheme === 'https' ? 443 : 80);
        $path = (string) ($url_parts['path'] ?? '/');
        $query = (string) ($url_parts['query'] ?? '');
        if ($query !== '') {
            $path .= '?' . $query;
        }

        $body = isset($request_args['body']) && is_array($request_args['body'])
            ? http_build_query($request_args['body'], '', '&')
            : '';
        if ($body === '') {
            return false;
        }

        $transport_host = $scheme === 'https' ? 'ssl://' . $host : $host;
        $timeout = max(0.1, (float) ($request_args['timeout'] ?? 1.0));
        $errno = 0;
        $errstr = '';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen -- Raw socket dispatch preserves the non-loopback async worker trigger path.
        $socket = @fsockopen($transport_host, $port, $errno, $errstr, $timeout);
        if (!is_resource($socket)) {
            return false;
        }

        stream_set_blocking($socket, false);

        $host_header = $host;
        $is_default_http_port = $scheme === 'http' && $port === 80;
        $is_default_https_port = $scheme === 'https' && $port === 443;
        if (!$is_default_http_port && !$is_default_https_port) {
            $host_header .= ':' . $port;
        }

        $request = "POST {$path} HTTP/1.1\r\n";
        $request .= "Host: {$host_header}\r\n";
        $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $request .= "Content-Length: " . strlen($body) . "\r\n";
        $request .= "Connection: Close\r\n\r\n";
        $request .= $body;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Raw socket dispatch preserves the non-loopback async worker trigger path.
        $written = @fwrite($socket, $request);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Raw socket dispatch preserves the non-loopback async worker trigger path.
        fclose($socket);

        return $written !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode_json_array(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $event_context
     * @param array<string, mixed> $envelope
     * @return array<int, array<string, mixed>>
     */
    private static function resolve_targets(string $event_name, array $envelope, array $event_context = []): array
    {
        $queue_meta = self::get_queue_meta($event_context);
        $targets = isset($queue_meta['targets']) && is_array($queue_meta['targets'])
            ? $queue_meta['targets']
            : AIPKit_Event_Webhooks_Settings::get_active_endpoints_for_event($event_name);

        $targets = apply_filters('aipkit_event_webhooks_targets', $targets, $event_name, $envelope, $event_context);

        return is_array($targets) ? $targets : [];
    }

    /**
     * @param array<string, mixed> $event_context
     * @return array<string, mixed>
     */
    private static function get_queue_meta(array $event_context = []): array
    {
        $queue_meta = isset($event_context[self::QUEUE_CONTEXT_KEY]) && is_array($event_context[self::QUEUE_CONTEXT_KEY])
            ? $event_context[self::QUEUE_CONTEXT_KEY]
            : [];

        return is_array($queue_meta) ? $queue_meta : [];
    }

    /**
     * @param array<string, mixed> $event_context
     * @param array<string, mixed> $queue_meta
     * @return array<string, mixed>
     */
    private static function set_queue_meta(array $event_context, array $queue_meta): array
    {
        $event_context[self::QUEUE_CONTEXT_KEY] = $queue_meta;

        return $event_context;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private static function build_retry_state(array $targets, array $deliveries): array
    {
        $retry_targets = [];
        $retry_delay = 0.0;
        $messages = [];

        foreach ($deliveries as $index => $delivery) {
            if (!is_array($delivery) || empty($delivery['should_retry'])) {
                continue;
            }

            if (isset($targets[$index]) && is_array($targets[$index])) {
                $retry_targets[] = $targets[$index];
            }

            $retry_delay = max($retry_delay, (float) ($delivery['retry_delay'] ?? 0.0));
            $message = sanitize_text_field((string) ($delivery['error_message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return [
            'targets' => $retry_targets,
            'delay' => $retry_delay,
            'message' => !empty($messages) ? implode(' | ', array_unique($messages)) : __('Retrying failed webhook delivery.', 'gpt3-ai-content-generator'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, array<string, mixed>>
     */
    private static function extract_failed_targets(array $targets, array $deliveries): array
    {
        $failed_targets = [];

        foreach ($deliveries as $index => $delivery) {
            if (!is_array($delivery) || (($delivery['status'] ?? '') !== 'failed')) {
                continue;
            }

            if (isset($targets[$index]) && is_array($targets[$index])) {
                $failed_targets[] = $targets[$index];
            }
        }

        return $failed_targets;
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @param array<int, string> $persisted_messages
     */
    private static function build_final_failure_message(array $deliveries, array $persisted_messages = []): string
    {
        $messages = [];
        foreach ($deliveries as $delivery) {
            if (!is_array($delivery) || (($delivery['status'] ?? '') !== 'failed')) {
                continue;
            }

            $message = sanitize_text_field((string) ($delivery['error_message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        foreach ($persisted_messages as $message) {
            $message = sanitize_text_field((string) $message);
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return !empty($messages)
            ? implode(' | ', array_unique($messages))
            : __('Event delivery failed after retry attempts were exhausted.', 'gpt3-ai-content-generator');
    }

    /**
     * @param array<string, mixed> $queue_meta
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private static function merge_failure_messages(array $queue_meta, array $deliveries): array
    {
        $messages = isset($queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY]) && is_array($queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY])
            ? $queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY]
            : [];

        foreach ($deliveries as $delivery) {
            if (!is_array($delivery) || (($delivery['status'] ?? '') !== 'failed') || !empty($delivery['should_retry'])) {
                continue;
            }

            $message = sanitize_text_field((string) ($delivery['error_message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        $queue_meta[self::QUEUE_META_FAILURE_MESSAGES_KEY] = array_values(array_unique(array_filter(array_map(
            static function ($message): string {
                return sanitize_text_field((string) $message);
            },
            $messages
        ))));

        return $queue_meta;
    }

    private static function get_hook_suffix(string $event_name): string
    {
        return str_replace(['.', '-'], '_', sanitize_key($event_name));
    }

    private static function should_bootstrap_cron(): bool
    {
        if (is_admin() || wp_doing_cron()) {
            return true;
        }

        return defined('WP_CLI') && WP_CLI;
    }
}
