<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Helpers;

use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checks if there are more pending items in the queue and schedules
 * an immediate one-off event to process them.
 *
 * @return void
 */
function maybe_reschedule_queue_logic(): void
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    $main_cron_hook = AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $remaining_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . esc_sql($queue_table_name) . " WHERE status = %s", 'pending'));

    if ($remaining_items > 0) {
        wp_schedule_single_event(time() + 30, $main_cron_hook);
    }
}
