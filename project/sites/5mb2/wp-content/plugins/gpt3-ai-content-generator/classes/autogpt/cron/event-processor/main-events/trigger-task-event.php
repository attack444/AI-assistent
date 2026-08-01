<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\MainEvents;

use WPAICG\AutoGPT\Cron\EventProcessor\Trigger;
use WPAICG\AutoGPT\Cron\EventProcessor\Helpers;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor; // Added for immediate processing

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/../trigger/trigger-comment-reply-task.php')) {
    require_once __DIR__ . '/../trigger/trigger-comment-reply-task.php';
}
if (file_exists(__DIR__ . '/../trigger/trigger-content-enhancement-task.php')) {
    require_once __DIR__ . '/../trigger/trigger-content-enhancement-task.php';
}

/**
 * Callback for task-specific cron events. Triggers processing for that task.
 *
 * @param int $task_id The ID of the task to trigger.
 * @return void
 */
function trigger_task_event_logic(int $task_id): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . esc_sql($tasks_table_name) . " WHERE id = %d AND status = 'active'", $task_id), ARRAY_A);
    if (!$task) {
        Helpers\log_cron_error_logic("Task ID {$task_id} not found or not active for cron trigger.");
        return;
    }

    $task_config = json_decode($task['task_config'], true) ?: [];
    $task_type = $task['task_type'];
    $frequency = $task_type === 'content_indexing'
        ? ($task_config['indexing_frequency'] ?? 'daily')
        : ($task_config['task_frequency'] ?? 'daily');

    if (strncmp($task_type, 'content_writing', strlen('content_writing')) === 0) {
        Trigger\trigger_content_writing_task_logic($task_id, $task_config);
    } elseif ($task_type === 'content_indexing') {
        Trigger\trigger_content_indexing_task_logic($task_id, $task_config, $task['last_run_time']);
    } elseif ($task_type === 'community_reply_comments') {
        Trigger\trigger_comment_reply_task_logic($task_id, $task_config, $task['last_run_time']);
    } elseif ($task_type === 'enhance_existing_content') {
        Trigger\trigger_content_enhancement_task_logic($task_id, $task_config, $task['last_run_time']);
    } else {
        Helpers\log_cron_error_logic("Unsupported task type '{$task_type}' for cron trigger, task ID {$task_id}.");
    }

    if (class_exists('\WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor')) {
        // Schedule a one-off event to start processing the queue almost immediately,
        // instead of calling it directly and risking a timeout before the parent cron can update its run times.
        wp_schedule_single_event(time() + 10, AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK);
        
        // For one-time tasks, also ensure the main hourly cron stays scheduled while there are pending items
        if ($frequency === 'one-time' && !wp_next_scheduled(AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK);
        }
    } else {
        Helpers\log_cron_error_logic("Could not schedule queue processor for task ID {$task_id}: AIPKit_Automated_Task_Event_Processor class not found.");
    }

    // After task logic has run, get the new next-scheduled time and update the database
    $hook = AIPKit_Automated_Task_Scheduler::get_task_specific_cron_hook($task_id);
    $args = [$task_id];
    $next_timestamp = wp_next_scheduled($hook, $args);
    $next_run_datetime_gmt = $next_timestamp ? gmdate('Y-m-d H:i:s', $next_timestamp) : null;

    $update_data = [
        'last_run_time' => current_time('mysql', 1),
        'next_run_time' => $next_run_datetime_gmt,
    ];
    $update_formats = ['%s', '%s'];

    if ($frequency === 'one-time') {
        $update_data['status'] = 'paused';
        $update_formats[] = '%s';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
    $wpdb->update(
        $tasks_table_name,
        $update_data,
        ['id' => $task_id],
        $update_formats,
        ['%d']
    );
}