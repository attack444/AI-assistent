<?php

namespace WPAICG\Chat\Storage;

use wpdb;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Helper class to build SQL query parts for conversation log rows.
 * FIXED: Improved filter handling for message_like searches.
 * ADDED: Handles 'module' column filtering and selection.
 * FIXED: Ensure empty module filter fetches ALL logs (including NULL module).
 * FIXED: Stricter checks for bot_id and module filters to ensure empty filters don't restrict query.
 * ADDED: Module and date-range filters.
 */
class LogQueryHelper
{
    private $table_name;

    public function __construct($table_name)
    {
        $this->table_name = $table_name;
    }

    /**
     * Builds SQL query parts for fetching conversation log rows based on filters.
     *
     * @param array $filters
     *  - bot_id => int|string|null (null, '', '0' for no bot)
     *  - user_id => int|null (null for guests)
     *  - session_id => string (for guests)
     *  - conversation_uuid => string
     *  - search => string (OR match across user display_name, guest session_id, and messages JSON)
     *  - user_name => string (partial match of user display_name or session_id for guests)
     *  - message_like => string (partial match in the 'messages' JSON column - **performance warning**)
     *  - module => string (module slug; use 'chatbot' to match NULL/empty)
     *  - start_ts => int (unix timestamp, inclusive)
     *  - end_ts => int (unix timestamp, inclusive)
     * @param string $orderby Field to order by (e.g., 'updated_at', 'last_message_ts').
     * @param string $order 'ASC' or 'DESC'.
     * @param int $limit Max rows.
     * @param int $offset Start row.
     * @param bool $select_messages Whether to include the messages JSON column in the SELECT clause.
     *
     * @return array {
     *    'select_sql' => string,
     *    'where_sql' => string,
     *    'params'    => array,
     *    'orderby'   => string,
     *    'order'     => string,
     *    'limit_sql' => string,
     *    'join_sql'  => string
     * }
     */
    public function build_conversation_query_parts(
        array $filters = [],
        string $orderby = 'updated_at',
        string $order = 'DESC',
        int $limit = 50,
        int $offset = 0,
        bool $select_messages = false // Flag to include the messages JSON column
    ): array {
        global $wpdb;

        $where_clauses = ['1=1'];
        $params = [];
        $join_sql = ''; // For joining wp_users if user_name filter is used

        // --- SELECT Clause ---
        // Select core metadata by default. Include messages JSON only if requested.
        // Module is still selected because LogManager uses it for display names.
        $select_fields = [
            "{$this->table_name}.id", "{$this->table_name}.bot_id", "{$this->table_name}.user_id",
            "{$this->table_name}.session_id", "{$this->table_name}.conversation_uuid",
            "{$this->table_name}.is_guest", "{$this->table_name}.module",
            "{$this->table_name}.message_count",
            "{$this->table_name}.first_message_ts", "{$this->table_name}.last_message_ts",
            "{$this->table_name}.ip_address", "{$this->table_name}.user_wp_role",
            "{$this->table_name}.created_at", "{$this->table_name}.updated_at"
        ];
        if ($select_messages) {
            $select_fields[] = "{$this->table_name}.messages";
        }

        // --- WHERE Clause & JOIN ---

        // Bot ID Filter: Only apply if bot_id is explicitly set
        if (isset($filters['bot_id'])) {
            $bot_filter_value = trim((string)$filters['bot_id']);
            if ($bot_filter_value === '' || $bot_filter_value === '0') {
                $where_clauses[] = "{$this->table_name}.bot_id IS NULL";
            } else {
                $where_clauses[] = "{$this->table_name}.bot_id = %d";
                $params[] = absint($bot_filter_value);
            }
        }

        // User ID Filter: Only apply if user_id is explicitly set
        if (isset($filters['user_id'])) {
            if ($filters['user_id'] === null || $filters['user_id'] === 0) {
                $where_clauses[] = "{$this->table_name}.user_id IS NULL";
            } else {
                $where_clauses[] = "{$this->table_name}.user_id = %d";
                $params[] = absint($filters['user_id']);
            }
        }

        if (!empty($filters['session_id'])) {
            $where_clauses[] = "{$this->table_name}.session_id = %s";
            $params[] = sanitize_text_field($filters['session_id']);
        }
        if (!empty($filters['conversation_uuid'])) {
            $where_clauses[] = "{$this->table_name}.conversation_uuid = %s";
            $params[] = sanitize_key($filters['conversation_uuid']);
        }

        if (!empty($filters['module'])) {
            $module_filter = sanitize_key($filters['module']);
            if ($module_filter === 'chatbot') {
                $where_clauses[] = "({$this->table_name}.module IS NULL OR {$this->table_name}.module = '')";
            } else {
                $where_clauses[] = "{$this->table_name}.module = %s";
                $params[] = $module_filter;
            }
        }

        if (!empty($filters['start_ts'])) {
            $where_clauses[] = "{$this->table_name}.last_message_ts >= %d";
            $params[] = absint($filters['start_ts']);
        }

        if (!empty($filters['end_ts'])) {
            $where_clauses[] = "{$this->table_name}.last_message_ts <= %d";
            $params[] = absint($filters['end_ts']);
        }

        // search filter (OR across user display_name, guest session_id, and messages JSON)
        $search_value = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search_value !== '') {
            $join_sql = " LEFT JOIN {$wpdb->users} AS u ON u.ID = {$this->table_name}.user_id ";
            $select_fields[] = "u.display_name as user_display_name";
            $likeVal = '%' . $wpdb->esc_like($search_value) . '%';
            $where_clauses[] = "( (u.display_name IS NOT NULL AND u.display_name LIKE %s) OR ({$this->table_name}.is_guest = 1 AND {$this->table_name}.session_id LIKE %s) OR {$this->table_name}.messages LIKE %s )";
            $params[] = $likeVal;
            $params[] = $likeVal;
            $params[] = $likeVal;
        } else {
            // user_name filter (joins wp_users)
            if (!empty($filters['user_name'])) {
                $join_sql = " LEFT JOIN {$wpdb->users} AS u ON u.ID = {$this->table_name}.user_id ";
                $select_fields[] = "u.display_name as user_display_name";
                $where_clauses[] = "( (u.display_name IS NOT NULL AND u.display_name LIKE %s) OR ({$this->table_name}.is_guest = 1 AND {$this->table_name}.session_id LIKE %s) )";
                $likeVal = '%' . $wpdb->esc_like($filters['user_name']) . '%';
                $params[] = $likeVal;
                $params[] = $likeVal;
            } else {
                $select_fields[] = "NULL as user_display_name";
            }

            // message_like filter
            if (!empty($filters['message_like'])) {
                $where_clauses[] = "{$this->table_name}.messages LIKE %s";
                $likeVal = '%' . $wpdb->esc_like($filters['message_like']) . '%';
                $params[] = $likeVal;
            }
        }

        // --- ORDER BY Clause ---
        $valid_orderby = ['id', 'bot_id', 'user_id', 'session_id', 'conversation_uuid', 'message_count', 'first_message_ts', 'last_message_ts', 'created_at', 'updated_at'];
        if ($join_sql) {
            $valid_orderby[] = 'user_display_name';
        }

        $orderby_final = 'last_message_ts'; // Changed default order
        if (in_array(strtolower($orderby), $valid_orderby)) {
            if ($orderby === 'user_display_name' && $join_sql) {
                $orderby_final = 'u.display_name';
            } else {
                $orderby_final = $this->table_name . '.' . strtolower($orderby);
            }
        }
        $order_final = in_array(strtoupper($order), ['ASC','DESC']) ? strtoupper($order) : 'DESC';

        // --- LIMIT Clause ---
        $limit_sql = '';
        if ($limit > 0) {
            $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', absint($limit), absint($offset));
        }

        $where_sql = implode(' AND ', $where_clauses);
        $select_sql = implode(', ', $select_fields);

        return [
            'select_sql' => $select_sql,
            'where_sql' => $where_sql,
            'params'    => $params,
            'orderby'   => $orderby_final,
            'order'     => $order_final,
            'limit_sql' => $limit_sql,
            'join_sql'  => $join_sql,
        ];
    }

    /**
    * Builds SQL query parts for counting conversation rows based on filters.
    * ADDED: Module and date-range filters (inherited from build_conversation_query_parts).
    *
    * @param array $filters Filters (same as build_conversation_query_parts).
    *
    * @return array {
    *    'count_sql' => string, // The full SQL query for counting
    *    'params'    => array   // Parameters for the query
    * }
    */
    public function build_conversation_count_query_parts(array $filters = []): array
    {
        global $wpdb;
        $query_parts = $this->build_conversation_query_parts($filters, '', '', 0, 0, false);

        $count_sql = "SELECT COUNT(*)
                      FROM {$this->table_name}
                      {$query_parts['join_sql']}
                      WHERE {$query_parts['where_sql']}";

        return [
            'count_sql' => $count_sql,
            'params'    => $query_parts['params'],
        ];
    }

    /**
     * Builds SQL query parts for fetching message rows based on filters.
     * Used specifically for export operations.
     * ADDED: Module and date-range filters.
     *
     * @param array $filters Filters (same as build_conversation_query_parts)
     * @param string $orderby Field to order by
     * @param string $order 'ASC' or 'DESC'
     * @param int $limit Max rows
     * @param int $offset Start row
     *
     * @return array Query parts
     */
    public function build_message_query_parts(array $filters = [], string $orderby = 'id', string $order = 'ASC', int $limit = 100, int $offset = 0): array
    {
        return $this->build_conversation_query_parts($filters, $orderby, $order, $limit, $offset, true);
    }


}
