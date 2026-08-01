<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Batch;

// Load dependency
use WPAICG\AutoGPT\Cron\Scheduler\Schedule;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Re-schedules all active tasks. Typically called on plugin activation or update.
*
* @return void
*/
function reschedule_all_active_tasks_logic(): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    if ($wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($tasks_table_name) . "'") != $tasks_table_name) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $active_tasks = $wpdb->get_results("SELECT id, task_config, task_type FROM " . esc_sql($tasks_table_name) . " WHERE status = 'active'", ARRAY_A);
    if ($active_tasks) {
        foreach ($active_tasks as $task) {
            $config = json_decode($task['task_config'], true);
            $frequency = $task['task_type'] === 'content_indexing'
            ? ($config['indexing_frequency'] ?? 'daily')
            : ($config['task_frequency'] ?? 'daily');
            Schedule\schedule_task_event_logic((int)$task['id'], $frequency, 'active');
        }
    }
}
