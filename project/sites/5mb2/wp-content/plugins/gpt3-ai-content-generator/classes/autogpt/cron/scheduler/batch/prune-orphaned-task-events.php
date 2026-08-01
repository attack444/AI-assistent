<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Batch;

use WPAICG\AutoGPT\Cron\Scheduler\Schedule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Removes task-specific cron events that do not belong to a real active task.
 *
 * @return void
 */
function prune_orphaned_task_events_logic(): void
{
    global $wpdb;

    if (!function_exists('_get_cron_array')) {
        return;
    }

    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    $active_task_ids = [];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table for cron cleanup.
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tasks_table_name));
    if ($table_exists === $tasks_table_name) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table for cron cleanup.
        $active_task_ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT id FROM ' . esc_sql($tasks_table_name) . ' WHERE status = %s',
                'active'
            )
        );
        $active_task_ids = array_map('absint', (array) $active_task_ids);
    }

    $active_task_ids = array_flip(array_filter($active_task_ids));
    $cron_events = _get_cron_array();
    if (!is_array($cron_events)) {
        return;
    }

    foreach ($cron_events as $events) {
        if (!is_array($events)) {
            continue;
        }
        foreach ($events as $hook => $hook_events) {
            $hook = (string) $hook;
            if (!preg_match('/^aipkit_automated_task_(\d+)$/', $hook, $matches)) {
                continue;
            }

            $task_id = absint($matches[1]);
            if ($task_id > 0 && isset($active_task_ids[$task_id])) {
                continue;
            }

            Schedule\clear_task_hook_events_logic($hook);
        }
    }
}
