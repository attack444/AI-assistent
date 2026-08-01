<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Schedule;

// Load dependency
require_once __DIR__ . '/get-task-specific-cron-hook.php';

if (!defined('ABSPATH')) {
    exit;
}

/**
* Clears every scheduled event for a task-specific hook, including older
* events that may have been saved with malformed or unexpected arguments.
*
* @param string $hook The task-specific hook name.
* @return void
*/
function clear_task_hook_events_logic(string $hook): void
{
    if (!function_exists('_get_cron_array')) {
        return;
    }

    $cron_events = _get_cron_array();
    if (!is_array($cron_events)) {
        return;
    }

    foreach ($cron_events as $timestamp => $events) {
        if (!is_array($events) || empty($events[$hook]) || !is_array($events[$hook])) {
            continue;
        }

        foreach ($events[$hook] as $event) {
            $args = isset($event['args']) && is_array($event['args']) ? $event['args'] : [];
            wp_unschedule_event((int) $timestamp, $hook, $args);
        }
    }
}

/**
* Clears the scheduled cron event for a specific task and updates the database.
*
* @param int $task_id The ID of the task.
* @return void
*/
function clear_task_event_logic(int $task_id): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    $hook = get_task_specific_cron_hook_logic($task_id);
    $current_schedule_args = [$task_id];

    clear_task_hook_events_logic($hook);

    // Keep the exact-argument clear as a safe fallback if direct cron scanning is unavailable.
    wp_clear_scheduled_hook($hook, $current_schedule_args);

    // Update the database to reflect the change
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
    $wpdb->update(
        $tasks_table_name,
        ['next_run_time' => null],
        ['id' => $task_id],
        ['%s'],
        ['%d']
    );
}
