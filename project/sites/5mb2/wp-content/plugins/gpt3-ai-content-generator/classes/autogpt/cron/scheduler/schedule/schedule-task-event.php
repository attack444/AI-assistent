<?php


namespace WPAICG\AutoGPT\Cron\Scheduler\Schedule;

// Load dependency
require_once __DIR__ . '/get-task-specific-cron-hook.php';
require_once __DIR__ . '/clear-task-event.php';
require_once __DIR__ . '/../utils/get-current-cron-event-details.php';

if (!defined('ABSPATH')) {
    exit;
}

/**
* Schedules or re-schedules a specific task's cron event and updates the database.
* Only clears and reschedules if the status or frequency requires it.
*
* @param int $task_id The ID of the task.
* @param string $frequency The desired frequency (e.g., 'hourly', 'daily', 'one-time').
* @param string $status The current status of the task ('active' or 'paused').
* @return void
*/
function schedule_task_event_logic(int $task_id, string $frequency, string $status): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    $hook = get_task_specific_cron_hook_logic($task_id);
    $current_schedule_args = [$task_id];

    if ($status === 'active') {
        clear_task_hook_events_logic($hook);

        if ($frequency === 'one-time') {
            // Schedule to run once, very soon.
            wp_schedule_single_event(time() + 10, $hook, $current_schedule_args);
        } else {
            // wp_schedule_event's first run is immediate unless a timestamp is provided. Let's add a small delay.
            wp_schedule_event(time() + (MINUTE_IN_SECONDS / 2), $frequency, $hook, $current_schedule_args);
        }

        // Always update the next_run_time column after scheduling/checking
        $next_run_timestamp = wp_next_scheduled($hook, $current_schedule_args);
        // The DB `datetime` column should store in UTC. `wp_next_scheduled` returns a UTC timestamp.
        $next_run_datetime_gmt = $next_run_timestamp ? gmdate('Y-m-d H:i:s', $next_run_timestamp) : null;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
        $wpdb->update(
            $tasks_table_name,
            ['next_run_time' => $next_run_datetime_gmt],
            ['id' => $task_id],
            ['%s'],
            ['%d']
        );

    } else { // Status is not 'active'
        // `clear_task_event_logic` will be called, which handles both clearing the hook and updating the DB.
        clear_task_event_logic($task_id);
    }
}
