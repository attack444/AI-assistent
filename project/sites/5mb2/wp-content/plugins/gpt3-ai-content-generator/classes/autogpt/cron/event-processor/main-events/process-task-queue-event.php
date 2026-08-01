<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\MainEvents;

use WPAICG\AutoGPT\Cron\EventProcessor\Processor;
use WPAICG\AutoGPT\Cron\EventProcessor\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Callback for the main cron hook. Processes items from the task queue.
 *
 * @return void
 */
function process_task_queue_event_logic(): void
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';

    Helpers\load_required_classes_logic();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $items_to_process = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM " . esc_sql($queue_table_name) . " WHERE status = %s ORDER BY added_at ASC LIMIT %d", 'pending', 5), // Process 5 items per run
        ARRAY_A
    );

    if (empty($items_to_process)) {
        return;
    }

    foreach ($items_to_process as $item) {
        Helpers\update_queue_status_logic($item['id'], 'processing');
        $result = Processor\process_queue_item_logic($item);
        Helpers\update_queue_status_logic($item['id'], $result['status'], $result['message'] ?? null);

        if ($result['status'] === 'success') {
        } else {
            Helpers\log_cron_error_logic("Failed to process item ID {$item['id']} (Task Type: {$item['task_type']}). Reason: {$result['message']}");
        }
        usleep(500000); // 0.5 second pause between items
    }

    Helpers\maybe_reschedule_queue_logic();
}
