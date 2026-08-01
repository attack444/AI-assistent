<?php


namespace WPAICG\AutoGPT\Cron\Queuer\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Updates the 'index_existing_now_flag' in a task's configuration to '0'.
 *
 * @param \wpdb $wpdb The WordPress database object.
 * @param string $tasks_table_name The name of the tasks table.
 * @param int $task_id The ID of the task to update.
 * @param array $task_config The configuration array of the task.
 * @return void
 */
function update_task_flag_logic(\wpdb $wpdb, string $tasks_table_name, int $task_id, array $task_config): void
{
    if (isset($task_config['index_existing_now_flag']) && $task_config['index_existing_now_flag'] === '1') {
        $task_config['index_existing_now_flag'] = '0'; // Mark as processed
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
        $wpdb->update(
            $tasks_table_name,
            ['task_config' => wp_json_encode($task_config)],
            ['id' => $task_id]
        );
    }
}
