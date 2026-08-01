<?php

namespace WPAICG\AutoGPT\Ajax;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles deletion of explicitly selected automated-task queue items.
 */
class AIPKit_Delete_Automated_Task_Queue_Items_Action extends AIPKit_Automated_Task_Base_Ajax_Action
{
    /**
     * Delete only the queue item IDs supplied by the current user.
     */
    public function handle_request()
    {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $encoded_item_ids = isset($_POST['item_ids']) ? sanitize_text_field(wp_unslash($_POST['item_ids'])) : '';
        $decoded_item_ids = json_decode($encoded_item_ids, true);
        $item_ids = is_array($decoded_item_ids)
            ? array_values(array_unique(array_filter(array_map('absint', $decoded_item_ids))))
            : [];

        if (empty($item_ids)) {
            $this->send_wp_error(new WP_Error('missing_item_ids', __('Select at least one queue item to delete.', 'gpt3-ai-content-generator')), 400);
            return;
        }

        if (count($item_ids) > 100) {
            $this->send_wp_error(new WP_Error('too_many_item_ids', __('You can delete up to 100 queue items at a time.', 'gpt3-ai-content-generator')), 400);
            return;
        }

        global $wpdb;
        $placeholders = implode(', ', array_fill(0, count($item_ids), '%d'));
        $query = "DELETE FROM {$this->queue_table_name} WHERE id IN ({$placeholders})";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query contains only the internal table name and a validated placeholder list.
        $prepared_query = $wpdb->prepare($query, $item_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately above; custom-table deletion by validated integer IDs; caching does not apply.
        $result = $wpdb->query($prepared_query);

        if ($result === false) {
            $this->send_wp_error(new WP_Error('db_error_delete_queue_items', __('Failed to delete queue items.', 'gpt3-ai-content-generator')), 500);
            return;
        }

        /* translators: %d: Number of deleted queue items. */
        wp_send_json_success(['message' => sprintf(_n('%d queue item deleted.', '%d queue items deleted.', $result, 'gpt3-ai-content-generator'), $result)]);
    }
}
