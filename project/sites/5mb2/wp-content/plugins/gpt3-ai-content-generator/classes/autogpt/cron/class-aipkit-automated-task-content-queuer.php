<?php


namespace WPAICG\AutoGPT\Cron;

// No need for WP_Query here anymore

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Require the new logic files
require_once __DIR__ . '/queuer/maybe-queue-initial-indexing-content.php';
require_once __DIR__ . '/queuer/queue-new-or-updated-indexing-content.php';

/**
 * Handles queueing content for automated tasks, specifically for content indexing.
 * This class now acts as a router/wrapper for the modularized logic functions.
 */
class AIPKit_Automated_Task_Content_Queuer
{
    /**
     * Queues initial content for indexing based on task configuration.
     * Delegates logic to an external function.
     */
    public static function maybe_queue_initial_indexing_content(int $task_id, array $task_config, bool $force_all = false)
    {
        Queuer\maybe_queue_initial_indexing_content_logic($task_id, $task_config, $force_all);
    }

    /**
     * Queues new or updated content for indexing since the last run time.
     * Delegates logic to an external function.
     */
    public static function queue_new_or_updated_indexing_content(int $task_id, array $task_config, ?string $last_run_time)
    {
        Queuer\queue_new_or_updated_indexing_content_logic($task_id, $task_config, $last_run_time);
    }
}
