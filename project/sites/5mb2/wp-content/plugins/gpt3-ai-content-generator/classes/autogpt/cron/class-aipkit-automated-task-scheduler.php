<?php

namespace WPAICG\AutoGPT\Cron;

// Load the new namespaced function files
use WPAICG\AutoGPT\Cron\Scheduler\Schedule;
use WPAICG\AutoGPT\Cron\Scheduler\Batch;

// Note: Utils namespace is used internally by the schedule functions.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load all new logic files
$scheduler_base_path = __DIR__ . '/scheduler/';
require_once $scheduler_base_path . 'schedule/get-task-specific-cron-hook.php';
require_once $scheduler_base_path . 'schedule/clear-task-event.php';
require_once $scheduler_base_path . 'schedule/schedule-task-event.php';
require_once $scheduler_base_path . 'batch/clear-all-task-events.php';
require_once $scheduler_base_path . 'batch/prune-orphaned-task-events.php';
require_once $scheduler_base_path . 'batch/reschedule-all-active-tasks.php';
// The utils file is loaded by schedule-task-event.php, no need to load it here again.


/**
* Handles cron scheduling for individual Automated Tasks.
* This class now acts as a facade, delegating its methods to namespaced functions.
*/
class AIPKit_Automated_Task_Scheduler
{
    /**
    * Gets the hook name for a specific task's cron event.
    */
    public static function get_task_specific_cron_hook(int $task_id): string
    {
        return Schedule\get_task_specific_cron_hook_logic($task_id);
    }

    /**
    * Schedules or re-schedules a specific task's cron event.
    */
    public static function schedule_task_event(int $task_id, string $frequency, string $status)
    {
        Schedule\schedule_task_event_logic($task_id, $frequency, $status);
    }

    /**
    * Clears the scheduled cron event for a specific task.
    */
    public static function clear_task_event(int $task_id)
    {
        Schedule\clear_task_event_logic($task_id);
    }

    /**
    * Clears all task-specific cron events. Used on plugin deactivation.
    */
    public static function clear_all_task_events()
    {
        Batch\clear_all_task_events_logic();
    }

    /**
    * Removes task-specific cron events that no longer belong to active tasks.
    */
    public static function prune_orphaned_task_events()
    {
        Batch\prune_orphaned_task_events_logic();
    }

    /**
    * Re-schedules all active tasks. Typically called on plugin activation or update.
    */
    public static function reschedule_all_active_tasks()
    {
        Batch\reschedule_all_active_tasks_logic();
    }
}
