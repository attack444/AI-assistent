<?php

namespace WPAICG\AutoGPT\Ajax;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for deleting an item from the automated task queue.
 */
class AIPKit_Delete_Automated_Task_Queue_Item_Action extends AIPKit_Automated_Task_Base_Ajax_Action {

    public function handle_request() {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) { $this->send_wp_error($permission_check); return; }
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        if (empty($item_id)) { $this->send_wp_error(new WP_Error('missing_item_id', __('Queue item ID is required.', 'gpt3-ai-content-generator')), 400); return; }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct deletion from a custom table. Cache will be invalidated.
        $result = $wpdb->delete($this->queue_table_name, ['id' => $item_id], ['%d']);
        if ($result === false) {
            $this->send_wp_error(new WP_Error('db_error_delete_queue_item', __('Failed to delete queue item.', 'gpt3-ai-content-generator')), 500);
        } else {
            wp_send_json_success(['message' => __('Queue item deleted successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}