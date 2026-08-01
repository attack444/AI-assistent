<?php


namespace WPAICG\AutoGPT\Ajax;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for getting items from the automated task queue.
 */
class AIPKit_Get_Automated_Task_Queue_Items_Action extends AIPKit_Automated_Task_Base_Ajax_Action
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
        $items_per_page = 15;

        // Search and filter parameters.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions method
        $search_term = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions method
        $status_filter = isset($_POST['status_filter']) ? sanitize_key(wp_unslash($_POST['status_filter'])) : '';

        $search_where_clauses = [];
        $search_prepare_args = [];

        if (!empty($search_term)) {
            $search_where_clauses[] = "(q.target_identifier LIKE %s OR t.task_name LIKE %s)";
            $search_prepare_args[] = '%' . $wpdb->esc_like($search_term) . '%';
            $search_prepare_args[] = '%' . $wpdb->esc_like($search_term) . '%';
        }

        $where_clauses = $search_where_clauses;
        $prepare_args = $search_prepare_args;

        if (!empty($status_filter) && $status_filter !== 'all') {
            $where_clauses[] = "q.status = %s";
            $prepare_args[] = $status_filter;
        }

        $search_where_sql = '';
        if (!empty($search_where_clauses)) {
            $search_where_sql = ' WHERE ' . implode(' AND ', $search_where_clauses);
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin queue aggregation over plugin-owned custom tables with controlled SQL fragments.
        $total_items_query = "SELECT COUNT(*) FROM {$this->queue_table_name} q LEFT JOIN {$this->tasks_table_name} t ON q.task_id = t.id" . $where_sql;
        if (!empty($prepare_args)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_items_query, $prepare_args));
        } else {
            $total_items = $wpdb->get_var($total_items_query);
        }
        $total_pages = max(1, (int) ceil((int) $total_items / $items_per_page));
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $items_per_page;

        $summary_query = "SELECT q.status, COUNT(*) AS item_count FROM {$this->queue_table_name} q LEFT JOIN {$this->tasks_table_name} t ON q.task_id = t.id" . $search_where_sql . ' GROUP BY q.status';
        if (!empty($search_prepare_args)) {
            $summary_rows = $wpdb->get_results($wpdb->prepare($summary_query, $search_prepare_args), ARRAY_A);
        } else {
            $summary_rows = $wpdb->get_results($summary_query, ARRAY_A);
        }
        // phpcs:enable
        $summary = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];
        if (!empty($summary_rows)) {
            foreach ($summary_rows as $summary_row) {
                $status = isset($summary_row['status']) ? sanitize_key((string) $summary_row['status']) : '';
                if (array_key_exists($status, $summary)) {
                    $summary[$status] = (int) ($summary_row['item_count'] ?? 0);
                }
            }
        }

        $prepare_args_for_select = $prepare_args;
        $prepare_args_for_select[] = $items_per_page;
        $prepare_args_for_select[] = $offset;
        $query = "SELECT q.*, t.task_name FROM {$this->queue_table_name} q LEFT JOIN {$this->tasks_table_name} t ON q.task_id = t.id" . $where_sql . " ORDER BY CASE WHEN q.status = 'processing' THEN 0 ELSE 1 END, q.added_at DESC LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin queue listing query over plugin-owned custom tables with prepared scalar values.
        $items = $wpdb->get_results($wpdb->prepare($query, $prepare_args_for_select), ARRAY_A);

        $enriched_items = [];
        if ($items) {
            foreach ($items as $item) {
                $item_config = json_decode($item['item_config'] ?? '[]', true);

                // Add generated_post_id key and parse from success message if applicable
                $item['generated_post_id'] = null;
                if (strncmp($item['task_type'], 'content_writing', strlen('content_writing')) === 0 && $item['status'] === 'completed' && !empty($item['error_message'])) {
                    if (preg_match('/\(ID: (\d+)\)/', $item['error_message'], $matches)) {
                        $item['generated_post_id'] = (int)$matches[1];
                    }
                }

                // Only expose a destination when the completed queue item still
                // points to a real post the current user can edit. This keeps the
                // UI from presenting stale or status-only links.
                $item['post_edit_url'] = '';
                $linked_post_id = 0;
                if ($item['status'] === 'completed') {
                    if (strncmp($item['task_type'], 'content_writing', strlen('content_writing')) === 0 && !empty($item['generated_post_id'])) {
                        $linked_post_id = absint($item['generated_post_id']);
                    } elseif ($item['task_type'] === 'enhance_existing_content') {
                        $linked_post_id = absint($item['target_identifier']);
                    }
                }
                if ($linked_post_id && get_post($linked_post_id)) {
                    $post_edit_url = get_edit_post_link($linked_post_id, 'raw');
                    if (is_string($post_edit_url) && $post_edit_url !== '') {
                        $item['post_edit_url'] = esc_url_raw($post_edit_url);
                    }
                }

                // Determine target_title for display
                if ($item['task_type'] === 'content_indexing' || $item['task_type'] === 'enhance_existing_content') {
                    $item['target_title'] = get_the_title(absint($item['target_identifier']));
                } elseif ($item['task_type'] === 'community_reply_comments') {
                    $item['target_title'] = 'Comment #' . absint($item['target_identifier']);
                } elseif (strncmp($item['task_type'], 'content_writing', strlen('content_writing')) === 0 && !empty($item_config['content_title'])) {
                    $item['target_title'] = $item_config['content_title'];
                } else {
                    $item['target_title'] = $item['target_identifier'];
                }

                // Expose scheduled time for content writing items (if any) so UI can display it under Added At column.
                if (strncmp($item['task_type'], 'content_writing', strlen('content_writing')) === 0 && !empty($item_config['scheduled_gmt_time'])) {
                    $item['scheduled_gmt_time'] = $item_config['scheduled_gmt_time'];
                }
                $enriched_items[] = $item;
            }
        }

        wp_send_json_success([
            'items' => $enriched_items,
            'pagination' => [
                'total_items' => (int) $total_items,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'per_page' => $items_per_page,
            ],
            'summary' => $summary,
        ]);
    }
}
