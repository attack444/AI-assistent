<?php


namespace WPAICG\AutoGPT\Ajax;

use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for updating the status of an automated task.
 */
class AIPKit_Update_Automated_Task_Status_Action extends AIPKit_Automated_Task_Base_Ajax_Action
{
    public function handle_request()
    {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $task_id = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $status = isset($_POST['status']) && in_array($_POST['status'], ['active', 'paused']) ? sanitize_key($_POST['status']) : null;
        if (empty($task_id) || $status === null) {
            $this->send_wp_error(new WP_Error('missing_params_status_update', __('Task ID and status are required.', 'gpt3-ai-content-generator')), 400);
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caches will be invalidated
        $task = $wpdb->get_row($wpdb->prepare("SELECT task_config FROM {$this->tasks_table_name} WHERE id = %d", $task_id), ARRAY_A);
        if (!$task) {
            $this->send_wp_error(new WP_Error('task_not_found_status', __('Task not found.', 'gpt3-ai-content-generator')), 404);
            return;
        }
        $task_config = json_decode($task['task_config'], true);
        $frequency = $task_config['indexing_frequency'] ?? ($task_config['task_frequency'] ?? 'daily');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
        $result = $wpdb->update($this->tasks_table_name, ['status' => $status, 'updated_at' => current_time('mysql', 1)], ['id' => $task_id], ['%s', '%s'], ['%d']);
        if ($result === false) {
            $this->send_wp_error(new WP_Error('db_error_update_status', __('Failed to update task status.', 'gpt3-ai-content-generator')), 500);
        } else {
            if (class_exists(AIPKit_Automated_Task_Scheduler::class)) {
                if ($status === 'active') {
                    AIPKit_Automated_Task_Scheduler::schedule_task_event($task_id, $frequency, 'active');
                } else {
                    AIPKit_Automated_Task_Scheduler::clear_task_event($task_id);
                }
            }
            wp_send_json_success(['message' => __('Task status updated successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}
