<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Batch;

// Load dependency
use WPAICG\AutoGPT\Cron\Scheduler\Schedule;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Clears all task-specific cron events. Used on plugin deactivation.
*
* @return void
*/
function clear_all_task_events_logic(): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    $task_hook_prefix = 'aipkit_automated_task_';

    if (function_exists('_get_cron_array')) {
        $cron_events = _get_cron_array();
        if (is_array($cron_events)) {
            foreach ($cron_events as $timestamp => $events) {
                if (!is_array($events)) {
                    continue;
                }
                foreach ($events as $hook => $hook_events) {
                    if (strpos((string) $hook, $task_hook_prefix) !== 0 || !is_array($hook_events)) {
                        continue;
                    }
                    foreach ($hook_events as $event) {
                        $args = isset($event['args']) && is_array($event['args']) ? $event['args'] : [];
                        wp_unschedule_event((int) $timestamp, (string) $hook, $args);
                    }
                }
            }
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    if ($wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($tasks_table_name) . "'") != $tasks_table_name) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $task_ids = $wpdb->get_col("SELECT id FROM " . esc_sql($tasks_table_name));
    if ($task_ids) {
        foreach ($task_ids as $task_id) {
            Schedule\clear_task_event_logic(absint($task_id));
        }
    }
}
