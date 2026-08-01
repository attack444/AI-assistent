<?php

namespace WPAICG\AutoGPT\Ajax;

use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for retrying a failed item in the automated task queue.
 */
class AIPKit_Retry_Automated_Task_Queue_Item_Action extends AIPKit_Automated_Task_Base_Ajax_Action {

    public function handle_request() {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) { $this->send_wp_error($permission_check); return; }
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        if (empty($item_id)) { $this->send_wp_error(new WP_Error('missing_item_id_retry', __('Queue item ID is required.', 'gpt3-ai-content-generator')), 400); return; }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Cache will be invalidated.
        $result = $wpdb->update(
            $this->queue_table_name,
            ['status' => 'pending', 'last_attempt_time' => null, 'error_message' => null, 'attempts' => 0],
            ['id' => $item_id, 'status' => 'failed'],
            ['%s', '%s', '%s', '%d'],
            ['%d', '%s']
        );

        if ($result === false) {
            $this->send_wp_error(new WP_Error('db_error_retry_queue_item', __('Failed to mark item for retry.', 'gpt3-ai-content-generator')), 500);
        } elseif ($result === 0) {
            $this->send_wp_error(new WP_Error('item_not_retryable', __('Item not found or not in a failed state.', 'gpt3-ai-content-generator')), 404);
        } else {
             if (class_exists(AIPKit_Automated_Task_Event_Processor::class)) {
                 AIPKit_Automated_Task_Event_Processor::process_task_queue_event();
             }
            wp_send_json_success(['message' => __('Queue item marked for retry.', 'gpt3-ai-content-generator')]);
        }
    }
}