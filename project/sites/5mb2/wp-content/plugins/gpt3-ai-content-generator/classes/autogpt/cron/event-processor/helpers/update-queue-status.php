<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Helpers;

use WPAICG\Core\AIPKit_Event_Webhooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a compact queue-item payload summary for webhook delivery.
 *
 * @param array<string, mixed> $item_config
 * @return array<string, mixed>
 */
function build_queue_item_event_summary_logic(array $item_config): array
{
    $summary = [];

    $summary_map = [
        'content_title' => 'content_title',
        'post_id' => 'post_id',
        'comment_id' => 'comment_id',
        'cw_generation_mode' => 'generation_mode',
        'target_store_provider' => 'target_store_provider',
        'target_store_id' => 'target_store_id',
        'scheduled_gmt_time' => 'scheduled_gmt_time',
    ];

    foreach ($summary_map as $config_key => $payload_key) {
        if (!array_key_exists($config_key, $item_config)) {
            continue;
        }
        $summary[$payload_key] = $item_config[$config_key];
    }

    if (!empty($item_config['content_keywords'])) {
        $summary['content_keywords'] = sanitize_text_field((string) $item_config['content_keywords']);
    }

    return $summary;
}

/**
 * Builds AI metadata for automated task queue webhook payloads.
 *
 * @param array<string, mixed> $item_config
 * @return array<string, mixed>
 */
function build_queue_item_ai_payload_logic(array $item_config): array
{
    $ai = [];
    $provider = sanitize_text_field((string) ($item_config['ai_provider'] ?? ''));
    $model = sanitize_text_field((string) ($item_config['ai_model'] ?? ''));

    if ($provider !== '') {
        $ai['provider'] = $provider;
    }

    if ($model !== '') {
        $ai['model'] = $model;
    }

    return $ai;
}

/**
 * Emits the canonical automated-task queue item completed event for a final item state.
 *
 * @param int         $item_id
 * @param string      $db_status
 * @param string|null $status_message
 * @return void
 */
function emit_queue_status_event_logic(int $item_id, string $db_status, ?string $status_message = null): void
{
    if (!class_exists(AIPKit_Event_Webhooks::class)) {
        return;
    }

    if ($db_status !== 'completed') {
        return;
    }

    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue lookup for event payload construction.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT q.*, t.task_name
             FROM " . esc_sql($queue_table_name) . " q
             LEFT JOIN " . esc_sql($tasks_table_name) . " t ON q.task_id = t.id
             WHERE q.id = %d
             LIMIT 1",
            $item_id
        ),
        ARRAY_A
    );

    if (!is_array($row) || empty($row)) {
        return;
    }

    $item_config = json_decode((string) ($row['item_config'] ?? ''), true);
    if (!is_array($item_config)) {
        $item_config = [];
    }

    $generated_post_id = null;
    $message_to_parse = (string) ($row['error_message'] ?? $status_message ?? '');
    if ($db_status === 'completed' && preg_match('/ID:\s*(\d+)/', $message_to_parse, $matches) === 1) {
        $generated_post_id = (int) $matches[1];
    }

    $event_name = 'task.item_completed';
    $resource_label = !empty($row['task_name'])
        ? sprintf(
            /* translators: %s: automated task name */
            __('Queue item for %s', 'gpt3-ai-content-generator'),
            (string) $row['task_name']
        )
        : __('Automated task queue item', 'gpt3-ai-content-generator');

    $payload = [
        'task' => [
            'id' => isset($row['task_id']) ? (int) $row['task_id'] : 0,
            'name' => (string) ($row['task_name'] ?? ''),
            'type' => (string) ($row['task_type'] ?? ''),
        ],
        'queue_item' => [
            'id' => (int) ($row['id'] ?? 0),
            'target_identifier' => (string) ($row['target_identifier'] ?? ''),
            'status' => $db_status,
            'attempts' => (int) ($row['attempts'] ?? 0),
            'added_at' => (string) ($row['added_at'] ?? ''),
            'last_attempt_time' => (string) ($row['last_attempt_time'] ?? ''),
        ],
        'result' => [
            'message' => $message_to_parse,
        ],
        'item' => build_queue_item_event_summary_logic($item_config),
    ];
    $ai_payload = build_queue_item_ai_payload_logic($item_config);
    if (!empty($ai_payload)) {
        $payload['ai'] = $ai_payload;
    }

    if ($generated_post_id) {
        $payload['result']['generated_post_id'] = $generated_post_id;
    }

    $event_meta = [
        'task_id' => isset($row['task_id']) ? (int) $row['task_id'] : 0,
        'task_type' => (string) ($row['task_type'] ?? ''),
        'queue_status' => $db_status,
    ];
    if (!empty($ai_payload['provider'])) {
        $event_meta['ai_provider'] = $ai_payload['provider'];
    }
    if (!empty($ai_payload['model'])) {
        $event_meta['ai_model'] = $ai_payload['model'];
    }

    AIPKit_Event_Webhooks::emit(
        $event_name,
        $payload,
        [
            'module' => 'automated_tasks',
            'origin' => 'queue_processor',
            'resource' => [
                'type' => 'queue_item',
                'id' => (int) ($row['id'] ?? 0),
                'label' => $resource_label,
            ],
            'meta' => $event_meta,
            'idempotency_key' => sha1(implode('|', [
                $event_name,
                (string) ($row['id'] ?? 0),
                $db_status,
                (string) ($row['attempts'] ?? 0),
                $message_to_parse,
            ])),
        ]
    );
}

/**
 * Updates the status and error message of a specific queue item.
 *
 * @param int $itemId The ID of the queue item.
 * @param string $status The new status ('processing', 'completed', 'failed', 'success').
 * @param string|null $errorMessage The error message, if status is 'failed'.
 * @return void
 */
function update_queue_status_logic(int $itemId, string $status, ?string $errorMessage = null): void
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    $db_status = $status;

    $update_data = [];
    $formats = [];

    if ($status === 'success') {
        $update_data['status'] = 'completed'; // Set final DB status to 'completed'
        $update_data['error_message'] = $errorMessage; // Store the success message (which has post ID)
        $formats = ['%s', '%s'];
        $db_status = 'completed';
    } else {
        if ($status === 'error') {
            $update_data['status'] = 'failed'; // Standardize DB status to 'failed'
            $db_status = 'failed';
        } else {
            $update_data['status'] = $status;
            $db_status = $status;
        }
        $formats[] = '%s';

        if ($status === 'processing') {
            $update_data['last_attempt_time'] = current_time('mysql', 1);
            $formats[] = '%s';
        } elseif ($status === 'failed' || $status === 'error') {
            $update_data['error_message'] = $errorMessage;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
            $wpdb->query($wpdb->prepare("UPDATE " . esc_sql($queue_table_name) . " SET attempts = attempts + 1 WHERE id = %d", $itemId));
            $formats[] = '%s';
        }
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
    $wpdb->update(
        $queue_table_name,
        $update_data,
        ['id' => $itemId],
        $formats,
        ['%d']
    );

    emit_queue_status_event_logic($itemId, $db_status, $errorMessage);
}
