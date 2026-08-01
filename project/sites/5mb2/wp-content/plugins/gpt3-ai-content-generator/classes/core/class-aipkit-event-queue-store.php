<?php

namespace WPAICG\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This store only queries plugin-owned queue tables and scalar values are normalized before each prepared call.

/**
 * Durable queue storage for emitted events before async delivery.
 */
class AIPKit_Event_Queue_Store
{
    private const TABLE_SUFFIX = 'aipkit_event_delivery_queue';
    private const ALLOWED_STATUSES = ['captured', 'pending', 'processing', 'completed', 'failed'];

    private static bool $table_ensured = false;

    public static function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function ensure_table(): void
    {
        if (self::$table_ensured) {
            return;
        }

        $table_name = self::get_table_name();
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check custom queue table existence.
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($table_exists !== $table_name && function_exists('aipkit_create_event_delivery_queue_table')) {
            aipkit_create_event_delivery_queue_table();
        }

        self::$table_ensured = true;
    }

    /**
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $context
     * @param array<string, mixed> $meta
     * @return array<string, mixed>|WP_Error
     */
    public static function enqueue_event(string $event_name, array $envelope, array $context = [], array $meta = [])
    {
        global $wpdb;

        self::ensure_table();

        $normalized_event_name = sanitize_text_field($event_name);
        $event_id = sanitize_text_field((string) ($envelope['id'] ?? ''));
        if ($normalized_event_name === '' || $event_id === '') {
            return new WP_Error(
                'aipkit_event_queue_invalid_payload',
                __('Event queue payload is missing required identifiers.', 'gpt3-ai-content-generator')
            );
        }

        $envelope_json = self::encode_json($envelope);
        if ($envelope_json === '') {
            return new WP_Error(
                'aipkit_event_queue_encode_failed',
                __('Failed to encode the event envelope for queue storage.', 'gpt3-ai-content-generator')
            );
        }

        $context_json = self::encode_json(self::normalize_context_for_storage($context));
        $source = isset($envelope['source']) && is_array($envelope['source']) ? $envelope['source'] : [];
        $source_module = sanitize_key((string) ($source['module'] ?? ''));
        $table_name = self::get_table_name();
        $job_uuid = wp_generate_uuid4();
        $available_at = gmdate('Y-m-d H:i:s');
        $initial_status = self::normalize_status((string) ($meta['initial_status'] ?? 'captured'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into a plugin-owned queue table.
        $inserted = $wpdb->insert(
            $table_name,
            [
                'job_uuid' => $job_uuid,
                'event_id' => $event_id,
                'event_name' => $normalized_event_name,
                'event_idempotency_key' => sanitize_text_field((string) ($envelope['idempotency_key'] ?? '')),
                'source_module' => $source_module,
                'status' => $initial_status,
                'attempt_count' => 0,
                'target_count' => max(0, (int) ($meta['target_count'] ?? 0)),
                'available_at' => $available_at,
                'envelope_json' => $envelope_json,
                'context_json' => $context_json !== '' ? $context_json : null,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            return new WP_Error(
                'aipkit_event_queue_insert_failed',
                __('Failed to queue the emitted event.', 'gpt3-ai-content-generator'),
                [
                    'db_error' => sanitize_text_field((string) $wpdb->last_error),
                    'event_id' => $event_id,
                    'event_name' => $normalized_event_name,
                ]
            );
        }

        return [
            'job_uuid' => $job_uuid,
            'event_id' => $event_id,
            'event_name' => $normalized_event_name,
            'status' => $initial_status,
            'attempt_count' => 0,
            'target_count' => max(0, (int) ($meta['target_count'] ?? 0)),
            'available_at' => $available_at,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function claim_due_jobs(int $limit = 5): array
    {
        global $wpdb;

        self::ensure_table();

        $safe_limit = max(1, min(20, $limit));
        $table_name = self::get_table_name();
        $now_gmt = gmdate('Y-m-d H:i:s');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue reads.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s AND available_at <= %s ORDER BY created_at ASC LIMIT %d",
                'pending',
                $now_gmt,
                $safe_limit
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $claimed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $job_uuid = sanitize_text_field((string) ($row['job_uuid'] ?? ''));
            if ($job_uuid === '') {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for queue claim updates.
            $updated = $wpdb->update(
                $table_name,
                [
                    'status' => 'processing',
                    'locked_at' => $now_gmt,
                    'attempt_count' => max(1, (int) ($row['attempt_count'] ?? 0) + 1),
                ],
                [
                    'job_uuid' => $job_uuid,
                    'status' => 'pending',
                ],
                ['%s', '%s', '%d'],
                ['%s', '%s']
            );

            if ($updated !== 1) {
                continue;
            }

            $row['status'] = 'processing';
            $row['locked_at'] = $now_gmt;
            $row['attempt_count'] = max(1, (int) ($row['attempt_count'] ?? 0) + 1);
            $claimed[] = self::normalize_row($row);
        }

        return $claimed;
    }

    public static function mark_job_completed(string $job_uuid): bool
    {
        return self::delete_job($job_uuid);
    }

    /**
     * @param array<string, mixed> $updates
     */
    public static function mark_job_failed(string $job_uuid, string $error_message = '', array $updates = []): bool
    {
        return self::update_job_state($job_uuid, array_merge([
            'status' => 'failed',
            'locked_at' => null,
            'processed_at' => gmdate('Y-m-d H:i:s'),
            'last_error_message' => sanitize_text_field($error_message),
        ], $updates));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_job_by_uuid(string $job_uuid): ?array
    {
        global $wpdb;

        self::ensure_table();

        $normalized_uuid = sanitize_text_field($job_uuid);
        if ($normalized_uuid === '') {
            return null;
        }

        $table_name = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for direct queue row reads.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE job_uuid = %s LIMIT 1",
                $normalized_uuid
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return self::normalize_job_with_payload($row);
    }

    /**
     * Returns recent failed webhook queue jobs for the Event Webhooks admin surface.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_recent_failed_webhook_jobs(int $limit = 8): array
    {
        global $wpdb;

        self::ensure_table();

        $safe_limit = max(1, min(20, $limit));
        $table_name = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for direct queue diagnostics reads.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s AND target_count > 0 ORDER BY COALESCE(processed_at, created_at) DESC LIMIT %d",
                'failed',
                $safe_limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map([__CLASS__, 'normalize_job_with_payload'], $rows), 'is_array'));
    }

    /**
     * Claims a specific pending job for immediate processing.
     *
     * @return array<string, mixed>|null
     */
    public static function claim_job_by_uuid(string $job_uuid): ?array
    {
        global $wpdb;

        self::ensure_table();

        $normalized_uuid = sanitize_text_field($job_uuid);
        if ($normalized_uuid === '') {
            return null;
        }

        $table_name = self::get_table_name();
        $now_gmt = gmdate('Y-m-d H:i:s');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for direct queue row reads.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE job_uuid = %s AND status = %s LIMIT 1",
                $normalized_uuid,
                'pending'
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for queue claim updates.
        $updated = $wpdb->update(
            $table_name,
            [
                'status' => 'processing',
                'locked_at' => $now_gmt,
                'attempt_count' => max(1, (int) ($row['attempt_count'] ?? 0) + 1),
            ],
            [
                'job_uuid' => $normalized_uuid,
                'status' => 'pending',
            ],
            ['%s', '%s', '%d'],
            ['%s', '%s']
        );

        if ($updated !== 1) {
            return null;
        }

        $row['status'] = 'processing';
        $row['locked_at'] = $now_gmt;
        $row['attempt_count'] = max(1, (int) ($row['attempt_count'] ?? 0) + 1);

        return self::normalize_job_with_payload($row);
    }

    public static function clear_failed_webhook_job(string $job_uuid): bool
    {
        $job = self::get_job_by_uuid($job_uuid);
        if (!is_array($job) || !self::is_failed_webhook_job($job)) {
            return false;
        }

        return self::delete_job($job_uuid);
    }

    /**
     * Deletes completed queue jobs immediately and prunes failed queue jobs
     * after a short retention window.
     *
     * @return array<string, int>
     */
    public static function cleanup_expired_jobs(): array
    {
        global $wpdb;

        self::ensure_table();

        $table_name = self::get_table_name();
        $failed_retention_days = max(1, (int) apply_filters('aipkit_event_delivery_queue_failed_retention_days', 7));
        $failed_cutoff = gmdate('Y-m-d H:i:s', time() - ($failed_retention_days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required cleanup on custom queue table.
        $deleted_completed = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE status = %s",
                'completed'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required cleanup on custom queue table.
        $deleted_failed = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE status = %s AND ((processed_at IS NOT NULL AND processed_at < %s) OR (processed_at IS NULL AND created_at < %s))",
                'failed',
                $failed_cutoff,
                $failed_cutoff
            )
        );

        return [
            'deleted_completed' => is_int($deleted_completed) ? $deleted_completed : 0,
            'deleted_failed' => is_int($deleted_failed) ? $deleted_failed : 0,
            'failed_retention_days' => $failed_retention_days,
        ];
    }

    /**
     * Recovers stale processing jobs that were abandoned by a crashed worker.
     *
     * Jobs that exceed the allowed processing attempts are marked failed. The
     * rest are returned to `pending` so a later loopback/cron pass can reclaim
     * them.
     *
     * @return array<string, mixed>
     */
    public static function recover_stale_jobs(): array
    {
        global $wpdb;

        self::ensure_table();

        $stale_after_seconds = max(60, (int) apply_filters('aipkit_event_delivery_queue_stale_after_seconds', 15 * MINUTE_IN_SECONDS));
        $max_processing_attempts = max(1, (int) apply_filters('aipkit_event_delivery_queue_max_processing_attempts', 6));
        $recovery_limit = max(1, min(100, (int) apply_filters('aipkit_event_delivery_queue_recovery_limit', 25)));

        $table_name = self::get_table_name();
        $cutoff_gmt = gmdate('Y-m-d H:i:s', time() - $stale_after_seconds);
        $now_gmt = gmdate('Y-m-d H:i:s');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue recovery reads.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s AND locked_at IS NOT NULL AND locked_at <= %s ORDER BY locked_at ASC LIMIT %d",
                'processing',
                $cutoff_gmt,
                $recovery_limit
            ),
            ARRAY_A
        );

        $summary = [
            'checked_count' => is_array($rows) ? count($rows) : 0,
            'recovered_count' => 0,
            'failed_count' => 0,
            'stale_after_seconds' => $stale_after_seconds,
            'max_processing_attempts' => $max_processing_attempts,
            'oldest_locked_at' => '',
            'recovered_job_uuids' => [],
            'failed_job_uuids' => [],
        ];

        if (!is_array($rows) || empty($rows)) {
            return $summary;
        }

        $oldest_locked_at = sanitize_text_field((string) ($rows[0]['locked_at'] ?? ''));
        if ($oldest_locked_at !== '') {
            $summary['oldest_locked_at'] = $oldest_locked_at;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $job_uuid = sanitize_text_field((string) ($row['job_uuid'] ?? ''));
            if ($job_uuid === '') {
                continue;
            }

            $attempt_count = max(0, (int) ($row['attempt_count'] ?? 0));
            if ($attempt_count >= $max_processing_attempts) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue recovery writes.
                $updated = $wpdb->update(
                    $table_name,
                    [
                        'status' => 'failed',
                        'locked_at' => null,
                        'processed_at' => $now_gmt,
                        'last_error_message' => sanitize_text_field(__('Queue job exceeded stale-processing recovery attempts.', 'gpt3-ai-content-generator')),
                    ],
                    [
                        'job_uuid' => $job_uuid,
                        'status' => 'processing',
                    ],
                    ['%s', '%s', '%s', '%s'],
                    ['%s', '%s']
                );

                if ($updated === 1) {
                    $summary['failed_count']++;
                    $summary['failed_job_uuids'][] = $job_uuid;
                }

                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue recovery writes.
            $updated = $wpdb->update(
                $table_name,
                [
                    'status' => 'pending',
                    'locked_at' => null,
                    'processed_at' => null,
                    'available_at' => $now_gmt,
                    'last_error_message' => sanitize_text_field(__('Recovered stale queue job after worker timeout.', 'gpt3-ai-content-generator')),
                ],
                [
                    'job_uuid' => $job_uuid,
                    'status' => 'processing',
                ],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%s', '%s']
            );

            if ($updated === 1) {
                $summary['recovered_count']++;
                $summary['recovered_job_uuids'][] = $job_uuid;
            }
        }

        return $summary;
    }

    /**
     * Returns a queue health summary for diagnostics.
     *
     * @return array<string, mixed>
     */
    public static function get_health_snapshot(): array
    {
        global $wpdb;

        self::ensure_table();

        $table_name = self::get_table_name();
        $stale_after_seconds = max(60, (int) apply_filters('aipkit_event_delivery_queue_stale_after_seconds', 15 * MINUTE_IN_SECONDS));
        $cutoff_gmt = gmdate('Y-m-d H:i:s', time() - $stale_after_seconds);

        $status_counts = [
            'captured' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue diagnostics reads.
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS total FROM {$table_name} GROUP BY status",
            ARRAY_A
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $status = self::normalize_status((string) ($row['status'] ?? 'captured'));
                $status_counts[$status] = max(0, (int) ($row['total'] ?? 0));
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue diagnostics reads.
        $stale_processing_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s AND locked_at IS NOT NULL AND locked_at <= %s",
                'processing',
                $cutoff_gmt
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue diagnostics reads.
        $oldest_pending_available_at = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT available_at FROM {$table_name} WHERE status = %s ORDER BY available_at ASC LIMIT 1",
                'pending'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue diagnostics reads.
        $oldest_processing_locked_at = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT locked_at FROM {$table_name} WHERE status = %s AND locked_at IS NOT NULL ORDER BY locked_at ASC LIMIT 1",
                'processing'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue diagnostics reads.
        $last_failed_at = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT processed_at FROM {$table_name} WHERE status = %s AND processed_at IS NOT NULL ORDER BY processed_at DESC LIMIT 1",
                'failed'
            )
        );

        $snapshot = [
            'total_jobs' => array_sum($status_counts),
            'status_counts' => $status_counts,
            'stale_processing_count' => max(0, $stale_processing_count),
            'stale_after_seconds' => $stale_after_seconds,
            'oldest_pending_available_at' => sanitize_text_field($oldest_pending_available_at),
            'oldest_processing_locked_at' => sanitize_text_field($oldest_processing_locked_at),
            'last_failed_at' => sanitize_text_field($last_failed_at),
        ];

        return apply_filters('aipkit_event_delivery_queue_health_snapshot', $snapshot, $table_name);
    }

    /**
     * @param array<string, mixed> $updates
     */
    public static function update_job_state(string $job_uuid, array $updates): bool
    {
        global $wpdb;

        self::ensure_table();

        $normalized_uuid = sanitize_text_field($job_uuid);
        if ($normalized_uuid === '') {
            return false;
        }

        $table_name = self::get_table_name();
        $update_data = [];
        $update_format = [];

        if (isset($updates['status'])) {
            $update_data['status'] = self::normalize_status((string) $updates['status']);
            $update_format[] = '%s';
        }

        if (array_key_exists('locked_at', $updates)) {
            $update_data['locked_at'] = $updates['locked_at'] !== null ? sanitize_text_field((string) $updates['locked_at']) : null;
            $update_format[] = '%s';
        }

        if (array_key_exists('processed_at', $updates)) {
            $update_data['processed_at'] = $updates['processed_at'] !== null ? sanitize_text_field((string) $updates['processed_at']) : null;
            $update_format[] = '%s';
        }

        if (array_key_exists('available_at', $updates)) {
            $update_data['available_at'] = sanitize_text_field((string) $updates['available_at']);
            $update_format[] = '%s';
        }

        if (array_key_exists('last_error_message', $updates)) {
            $update_data['last_error_message'] = sanitize_text_field((string) $updates['last_error_message']);
            $update_format[] = '%s';
        }

        if (array_key_exists('target_count', $updates)) {
            $update_data['target_count'] = max(0, (int) $updates['target_count']);
            $update_format[] = '%d';
        }

        if (array_key_exists('attempt_count', $updates)) {
            $update_data['attempt_count'] = max(0, (int) $updates['attempt_count']);
            $update_format[] = '%d';
        }

        if (array_key_exists('context_json', $updates)) {
            $context_json = '';
            if (is_string($updates['context_json'])) {
                $context_json = $updates['context_json'];
            } elseif (is_array($updates['context_json'])) {
                $context_json = self::encode_json(self::normalize_context_for_storage($updates['context_json']));
            }

            $update_data['context_json'] = $context_json !== '' ? $context_json : null;
            $update_format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for custom queue writes.
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['job_uuid' => $normalized_uuid],
            $update_format,
            ['%s']
        );

        return $updated !== false;
    }

    private static function delete_job(string $job_uuid): bool
    {
        global $wpdb;

        self::ensure_table();

        $normalized_uuid = sanitize_text_field($job_uuid);
        if ($normalized_uuid === '') {
            return false;
        }

        $table_name = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required delete on custom queue table.
        $deleted = $wpdb->delete(
            $table_name,
            ['job_uuid' => $normalized_uuid],
            ['%s']
        );

        return $deleted === 1;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function normalize_context_for_storage($value)
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $child_value) {
                $normalized_key = is_int($key) ? $key : sanitize_key((string) $key);
                if ($normalized_key === '') {
                    continue;
                }

                $normalized_child = self::normalize_context_for_storage($child_value);
                if ($normalized_child === null) {
                    continue;
                }

                $normalized[$normalized_key] = $normalized_child;
            }

            return $normalized;
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function encode_json($value): string
    {
        $encoded = wp_json_encode($value);

        return is_string($encoded) ? $encoded : '';
    }

    private static function normalize_status(string $status): string
    {
        $normalized_status = sanitize_key($status);

        if (!in_array($normalized_status, self::ALLOWED_STATUSES, true)) {
            return 'captured';
        }

        return $normalized_status;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize_row(array $row): array
    {
        return [
            'job_uuid' => sanitize_text_field((string) ($row['job_uuid'] ?? '')),
            'event_id' => sanitize_text_field((string) ($row['event_id'] ?? '')),
            'event_name' => sanitize_text_field((string) ($row['event_name'] ?? '')),
            'event_idempotency_key' => sanitize_text_field((string) ($row['event_idempotency_key'] ?? '')),
            'status' => self::normalize_status((string) ($row['status'] ?? 'captured')),
            'attempt_count' => max(0, (int) ($row['attempt_count'] ?? 0)),
            'target_count' => max(0, (int) ($row['target_count'] ?? 0)),
            'available_at' => sanitize_text_field((string) ($row['available_at'] ?? '')),
            'locked_at' => sanitize_text_field((string) ($row['locked_at'] ?? '')),
            'processed_at' => sanitize_text_field((string) ($row['processed_at'] ?? '')),
            'last_error_message' => sanitize_text_field((string) ($row['last_error_message'] ?? '')),
            'envelope_json' => (string) ($row['envelope_json'] ?? ''),
            'context_json' => (string) ($row['context_json'] ?? ''),
            'source_module' => sanitize_key((string) ($row['source_module'] ?? '')),
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($row['updated_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize_job_with_payload(array $row): array
    {
        $normalized = self::normalize_row($row);
        $envelope = self::decode_json_array((string) ($normalized['envelope_json'] ?? ''));
        $context = self::decode_json_array((string) ($normalized['context_json'] ?? ''));
        $targets = self::extract_webhook_targets_from_context($context);

        $normalized['envelope'] = $envelope;
        $normalized['context'] = $context;
        $normalized['targets'] = $targets;
        $normalized['target_labels'] = self::extract_target_labels($targets);
        $normalized['target_summary'] = self::build_target_summary($targets);
        $normalized['error_message'] = sanitize_text_field((string) ($normalized['last_error_message'] ?? ''));
        $normalized['resource_label'] = sanitize_text_field((string) (($envelope['resource']['label'] ?? '') ?: ($envelope['type'] ?? '')));
        $normalized['displayed_at'] = sanitize_text_field((string) (($normalized['processed_at'] ?? '') ?: ($normalized['created_at'] ?? '')));

        return $normalized;
    }

    /**
     * @param array<string, mixed> $job
     */
    private static function is_failed_webhook_job(array $job): bool
    {
        return self::normalize_status((string) ($job['status'] ?? '')) === 'failed'
            && max(0, (int) ($job['target_count'] ?? 0)) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode_json_array(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private static function extract_webhook_targets_from_context(array $context): array
    {
        $queue_meta = isset($context['aipkit_queue']) && is_array($context['aipkit_queue'])
            ? $context['aipkit_queue']
            : [];
        $targets = isset($queue_meta['targets']) && is_array($queue_meta['targets'])
            ? $queue_meta['targets']
            : [];

        $normalized_targets = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $url = esc_url_raw((string) ($target['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $normalized_targets[] = [
                'id' => sanitize_key((string) ($target['id'] ?? '')),
                'name' => sanitize_text_field((string) ($target['name'] ?? '')),
                'url' => $url,
            ];
        }

        return $normalized_targets;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @return array<int, string>
     */
    private static function extract_target_labels(array $targets): array
    {
        $labels = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $label = sanitize_text_field((string) ($target['name'] ?? ''));
            if ($label === '') {
                $url = esc_url_raw((string) ($target['url'] ?? ''));
                $host = $url !== '' ? wp_parse_url($url, PHP_URL_HOST) : '';
                $label = sanitize_text_field((string) ($host ?: $url));
            }

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     */
    private static function build_target_summary(array $targets): string
    {
        $labels = self::extract_target_labels($targets);
        if (empty($labels)) {
            return __('Webhook endpoint', 'gpt3-ai-content-generator');
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        $visible_labels = array_slice($labels, 0, 2);
        $remaining_count = count($labels) - count($visible_labels);
        $summary = implode(', ', $visible_labels);

        if ($remaining_count > 0) {
            $summary .= sprintf(
                /* translators: %d: remaining endpoint count */
                __(' +%d more', 'gpt3-ai-content-generator'),
                $remaining_count
            );
        }

        return $summary;
    }
}
