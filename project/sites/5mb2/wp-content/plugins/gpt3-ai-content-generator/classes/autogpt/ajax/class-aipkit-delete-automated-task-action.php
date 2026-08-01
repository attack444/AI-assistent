<?php

namespace WPAICG\AutoGPT\Ajax;

use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for deleting an automated task.
 */
class AIPKit_Delete_Automated_Task_Action extends AIPKit_Automated_Task_Base_Ajax_Action {

    public function handle_request() {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) { $this->send_wp_error($permission_check); return; }

        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $task_id = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
        if (empty($task_id)) { $this->send_wp_error(new WP_Error('missing_task_id_delete', __('Task ID is required.', 'gpt3-ai-content-generator')), 400); return; }

        if (class_exists(AIPKit_Automated_Task_Scheduler::class)) {
             AIPKit_Automated_Task_Scheduler::clear_task_event($task_id);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct deletion from a custom table. Caches will be invalidated.
        $wpdb->delete($this->queue_table_name, ['task_id' => $task_id], ['%d']);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct deletion from a custom table. Caches will be invalidated.
        $result = $wpdb->delete($this->tasks_table_name, ['id' => $task_id], ['%d']);

        if ($result === false) {
            $this->send_wp_error(new WP_Error('db_error_delete_task', __('Failed to delete task.', 'gpt3-ai-content-generator')), 500);
        } else {
            wp_send_json_success(['message' => __('Task deleted successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}