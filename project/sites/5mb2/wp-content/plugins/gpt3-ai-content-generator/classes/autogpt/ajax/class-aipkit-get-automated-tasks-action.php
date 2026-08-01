<?php


namespace WPAICG\AutoGPT\Ajax;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for getting all automated tasks.
 */
class AIPKit_Get_Automated_Tasks_Action extends AIPKit_Automated_Task_Base_Ajax_Action
{
    public function handle_request()
    {
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions method
        $current_page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions method
        $requested_per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;
        $allowed_per_page = [5, 10, 20, 50];
        $items_per_page = in_array($requested_per_page, $allowed_per_page, true) ? $requested_per_page : 10;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin pagination count over a plugin-owned custom table.
        $count_query = "SELECT COUNT(*) FROM {$this->tasks_table_name}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin pagination count over a plugin-owned custom table.
        $total_items = $wpdb->get_var($count_query);
        $total_pages = max(1, (int) ceil((int) $total_items / $items_per_page));
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $items_per_page;

        $tasks_query = "SELECT * FROM {$this->tasks_table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $tasks_query_args = [$items_per_page, $offset];
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query placeholders and arguments are assembled immediately above.
        $tasks_query = $wpdb->prepare($tasks_query, ...$tasks_query_args);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin pagination query over a plugin-owned custom table.
        $tasks = $wpdb->get_results($tasks_query, ARRAY_A);


        wp_send_json_success([
            'tasks' => $tasks ?: [],
            'pagination' => [
                'total_items' => (int) $total_items,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'per_page' => $items_per_page,
            ]
        ]);
    }
}
