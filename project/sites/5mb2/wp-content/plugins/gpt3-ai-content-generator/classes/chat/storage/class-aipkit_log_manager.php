<?php

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Storage\LogQueryHelper;
use WPAICG\Chat\Admin\AdminSetup; // Needed for Bot name lookup
use WPAICG\Chat\Utils\Utils; // Needed for time diff
use WP_Error; // Added use statement

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles admin-focused log management tasks: fetching for display, counting, pruning, exporting, deleting.
 */
class LogManager
{
    private $wpdb;
    private $table_name;
    private $query_helper;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aipkit_chat_logs';
        $this->query_helper = new LogQueryHelper($this->table_name);
    }

    private function is_content_writer_placeholder(string $content): bool
    {
        $normalized = strtolower(trim($content));
        if ($normalized === '') {
            return false;
        }
        return (strpos($normalized, 'generate ') === 0) || (strpos($normalized, 'content writer request') === 0);
    }

    private function extract_prompt_from_payload_sent(array $payload): ?string
    {
        $sent = $payload['payload_sent'] ?? null;
        if (!is_array($sent)) {
            return null;
        }
        $messages = $sent['messages'] ?? null;
        if (!is_array($messages)) {
            $messages = $sent['input'] ?? null;
        }
        if (!is_array($messages)) {
            return null;
        }
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            if (($message['role'] ?? '') !== 'user') {
                continue;
            }
            $content = $message['content'] ?? '';
            if (!is_string($content)) {
                continue;
            }
            $content = trim($content);
            if ($content !== '') {
                return $content;
            }
        }
        return null;
    }

    private function find_prompt_field(array $payload, string $content): ?string
    {
        $map = [
            'generate excerpt' => 'custom_excerpt_prompt',
            'generate tags' => 'custom_tags_prompt',
            'generate meta description' => 'custom_meta_prompt',
            'generate focus keyword' => 'custom_keyword_prompt',
            'generate image prompt' => 'image_prompt',
            'generate featured image' => 'featured_image_prompt',
            'generate title' => 'custom_title_prompt',
        ];
        $normalized = strtolower(trim($content));
        if (!isset($map[$normalized])) {
            return null;
        }
        $field = $map[$normalized];
        $value = $payload[$field] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function find_any_prompt_field(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (preg_match('/_prompt$/i', $key) !== 1) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    private function resolve_content_writer_preview(array $messages): ?string
    {
        $preview = null;
        $count = count($messages);
        for ($i = 0; $i < $count; $i++) {
            $message = $messages[$i] ?? null;
            if (!is_array($message)) {
                continue;
            }
            if (($message['role'] ?? '') !== 'user') {
                continue;
            }
            $content = $message['content'] ?? '';
            if (!is_string($content)) {
                continue;
            }
            $content = trim($content);
            if ($content === '') {
                continue;
            }
            $preview = $content;
            if (!$this->is_content_writer_placeholder($content)) {
                continue;
            }

            $replacement = null;
            $next_payload = $messages[$i + 1]['request_payload'] ?? null;
            if (is_array($next_payload)) {
                $replacement = $this->extract_prompt_from_payload_sent($next_payload);
            }
            if (!$replacement && isset($message['request_payload']) && is_array($message['request_payload'])) {
                $replacement = $this->extract_prompt_from_payload_sent($message['request_payload']);
            }
            if (!$replacement && isset($message['request_payload']) && is_array($message['request_payload'])) {
                $replacement = $this->find_prompt_field($message['request_payload'], $content);
            }
            if (!$replacement && isset($message['request_payload']) && is_array($message['request_payload'])) {
                $replacement = $this->find_any_prompt_field($message['request_payload']);
            }
            if ($replacement) {
                $preview = $replacement;
            }
        }
        return $preview;
    }

    /**
     * Retrieves conversation summary rows for the admin log view.
     * Handles the new JSON structure to extract the last message snippet and check for feedback.
     * Calculates total tokens used in the conversation.
     * Includes 'module' in the results.
     * Changed default sort order.
     */
    public function get_logs(array $filters = [], int $limit = 50, int $offset = 0, string $orderby = 'last_message_ts', string $order = 'DESC'): array // Default orderby changed to 'last_message_ts'
    {$query_parts = $this->query_helper->build_conversation_query_parts($filters, $orderby, $order, $limit, $offset, true); // Select messages JSON and module
        $query = "SELECT {$query_parts['select_sql']} FROM {$this->table_name} {$query_parts['join_sql']} WHERE {$query_parts['where_sql']} ORDER BY {$query_parts['orderby']} {$query_parts['order']} {$query_parts['limit_sql']}";
        if (!empty($query_parts['params'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->wpdb->prepare is safe to use here.
            $query = $this->wpdb->prepare($query, $query_parts['params']);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to custom table for log retrieval.
        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results) {
            foreach ($results as &$log_row) {
                // Enrich Bot/User Name
                if (!empty($log_row['bot_id'])) {
                    $log_row['bot_name'] = get_the_title($log_row['bot_id']) ?: __('(Deleted Bot)', 'gpt3-ai-content-generator');
                } elseif (empty($log_row['module'])) {
                    $log_row['bot_name'] = __('(No Bot)', 'gpt3-ai-content-generator');
                } else {
                    // Friendly labels for non-bot sources
                    if ($log_row['module'] === 'ai_post_enhancer') {
                        $log_row['bot_name'] = __('Content Assistant', 'gpt3-ai-content-generator');
                    } elseif ($log_row['module'] === 'wp_ai_client') {
                        $log_row['bot_name'] = __('WP AI Client', 'gpt3-ai-content-generator');
                    } else {
                        $log_row['bot_name'] = esc_html(ucfirst(str_replace('_', ' ', $log_row['module'])));
                    }
                }

                if (!isset($log_row['user_display_name'])) {
                    if (!$log_row['is_guest'] && !empty($log_row['user_id'])) {
                        $ud = get_userdata($log_row['user_id']);
                        $log_row['user_display_name'] = $ud ? $ud->display_name : __('(Deleted User)', 'gpt3-ai-content-generator');
                    } elseif ($log_row['is_guest']) {
                        $log_row['user_display_name'] = __('Guest', 'gpt3-ai-content-generator');
                    } else {
                        $log_row['user_display_name'] = __('(Unknown User)', 'gpt3-ai-content-generator');
                    }
                }

                // Extract last message info from JSON
                $conversation_data = json_decode($log_row['messages'] ?? '[]', true);
                $messages_array = null;
                $has_feedback = false;
                $total_conversation_tokens = 0;

                if (is_array($conversation_data) && isset($conversation_data['messages']) && is_array($conversation_data['messages'])) {
                    $messages_array = $conversation_data['messages'];
                } elseif (is_array($conversation_data)) {
                    $messages_array = $conversation_data;
                }

                if (is_array($messages_array)) {
                    $last_message_obj = end($messages_array);
                    $log_row['last_message_role'] = $last_message_obj['role'] ?? '';
                    $log_row['last_message_content'] = $last_message_obj['content'] ?? __('(No messages)', 'gpt3-ai-content-generator');

                    if (($log_row['module'] ?? '') === 'content_writer') {
                        $content_writer_preview = $this->resolve_content_writer_preview($messages_array);
                        if ($content_writer_preview) {
                            $log_row['last_message_role'] = 'user';
                            $log_row['last_message_content'] = $content_writer_preview;
                        }
                    }

                    foreach ($messages_array as $msg) {
                        if (isset($msg['feedback']) && ($msg['feedback'] === 'up' || $msg['feedback'] === 'down')) {
                            $has_feedback = true;
                        }
                        if (isset($msg['usage']['total_tokens']) && is_numeric($msg['usage']['total_tokens'])) {
                            $total_conversation_tokens += (int) $msg['usage']['total_tokens'];
                        } elseif (isset($msg['usage']['totalTokenCount']) && is_numeric($msg['usage']['totalTokenCount'])) {
                            $total_conversation_tokens += (int) $msg['usage']['totalTokenCount'];
                        }
                    }
                } else {
                    $log_row['last_message_role'] = '';
                    $log_row['last_message_content'] = __('(No messages)', 'gpt3-ai-content-generator');
                }

                $log_row['has_feedback'] = $has_feedback;
                $log_row['total_conversation_tokens'] = $total_conversation_tokens;

                unset($log_row['messages']);
            }
            unset($log_row);
        }
        return $results ?: [];
    }

    /**
     * Count how many distinct conversation rows match the given filters.
     * Includes 'module' filter.
     */
    public function count_logs(array $filters = []): int
    {
        $query_parts = $this->query_helper->build_conversation_count_query_parts($filters);
        $query = $query_parts['count_sql'];
        if (!empty($query_parts['params'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->wpdb->prepare is safe to use here.
            $query = $this->wpdb->prepare($query, $query_parts['params']);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to custom table for log counting.
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Returns exact aggregate metrics for the filtered conversation set.
     * Rows are processed in batches to avoid loading an entire log history into memory.
     */
    public function get_log_summary(array $filters = []): array
    {
        $summary = [
            'conversations' => 0,
            'messages' => 0,
            'tokens_used' => 0,
            'active_users' => 0,
        ];
        $active_users = [];
        $batch_size = 250;
        $offset = 0;

        do {
            $query_parts = $this->query_helper->build_conversation_query_parts(
                $filters,
                'id',
                'ASC',
                $batch_size,
                $offset,
                true
            );
            $query = "SELECT {$this->table_name}.id, {$this->table_name}.user_id, {$this->table_name}.session_id,
                             {$this->table_name}.message_count, {$this->table_name}.messages
                      FROM {$this->table_name}
                      {$query_parts['join_sql']}
                      WHERE {$query_parts['where_sql']}
                      ORDER BY {$query_parts['orderby']} {$query_parts['order']}
                      {$query_parts['limit_sql']}";

            if (!empty($query_parts['params'])) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query fragments and placeholders are built by LogQueryHelper.
                $query = $this->wpdb->prepare($query, $query_parts['params']);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only aggregate over the plugin's custom log table. The table name and query fragments are controlled internally.
            $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

            foreach ($rows as $row) {
                $summary['conversations']++;
                $summary['messages'] += isset($row['message_count']) ? (int) $row['message_count'] : 0;

                if (!empty($row['user_id'])) {
                    $active_users['user:' . (int) $row['user_id']] = true;
                } elseif (!empty($row['session_id'])) {
                    $active_users['guest:' . (string) $row['session_id']] = true;
                } else {
                    $active_users['unknown'] = true;
                }

                $conversation_data = json_decode($row['messages'] ?? '[]', true);
                $messages = [];
                if (is_array($conversation_data) && isset($conversation_data['messages']) && is_array($conversation_data['messages'])) {
                    $messages = $conversation_data['messages'];
                } elseif (is_array($conversation_data)) {
                    $messages = $conversation_data;
                }

                foreach ($messages as $message) {
                    if (isset($message['usage']['total_tokens']) && is_numeric($message['usage']['total_tokens'])) {
                        $summary['tokens_used'] += (int) $message['usage']['total_tokens'];
                    } elseif (isset($message['usage']['totalTokenCount']) && is_numeric($message['usage']['totalTokenCount'])) {
                        $summary['tokens_used'] += (int) $message['usage']['totalTokenCount'];
                    }
                }
            }

            $row_count = count($rows);
            $offset += $row_count;
        } while ($row_count === $batch_size);

        $summary['active_users'] = count($active_users);

        return $summary;
    }

    /**
     * Deletes conversation rows older than X days (based on last_message_ts).
     * @return int|false
     */
    public function prune_logs(float $days)
    {
        if ($days <= 0) {
            return 0;
        }
        $timestamp_threshold = time() - (int)($days * DAY_IN_SECONDS);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Reason: Bulk deletion on a custom table for a cron job. Caching is not applicable. $this->table_name is safe.
        return $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->table_name} WHERE last_message_ts < %d", $timestamp_threshold));
    }

    /**
     * Deletes conversation rows matching the provided filters, up to a specified limit.
     * Includes 'module' filter.
     * @return int|false
     */
    public function delete_logs(array $filters = [], int $limit = 500)
    {
        if ($limit <= 0) {
            return 0;
        }
        $query_parts = $this->query_helper->build_conversation_query_parts($filters, 'id', 'ASC', $limit, 0, false);
        $select_ids_query = "SELECT {$this->table_name}.id FROM {$this->table_name} {$query_parts['join_sql']} WHERE {$query_parts['where_sql']} ORDER BY {$query_parts['orderby']} {$query_parts['order']} {$query_parts['limit_sql']}";
        if (!empty($query_parts['params'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->wpdb->prepare is safe to use here.
            $select_ids_query = $this->wpdb->prepare($select_ids_query, $query_parts['params']);
        }
        if (!$select_ids_query) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to custom table for log deletion.
        $log_ids_to_delete = $this->wpdb->get_col($select_ids_query);
        if (empty($log_ids_to_delete)) {
            return 0;
        }
        $ids_placeholder = implode(', ', array_fill(0, count($log_ids_to_delete), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: $this->table_name is safe. $ids_placeholder is an array of %d.
        $delete_query = "DELETE FROM {$this->table_name} WHERE id IN ($ids_placeholder)";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->wpdb->prepare is safe to use here.
        $delete_query_prepared = $this->wpdb->prepare($delete_query, $log_ids_to_delete);
        if (!$delete_query_prepared) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Reason: Direct query to custom table for log deletion.
        return $this->wpdb->query($delete_query_prepared);
    }

    /**
     * Helper function to get raw conversation rows including the messages JSON.
     * Used specifically for the export feature. Includes feedback and usage data.
     * Includes 'module' filter and data.
     */
    public function get_raw_conversations_for_export(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query_parts = $this->query_helper->build_message_query_parts($filters, 'id', 'ASC', $limit, $offset);

        $query = "SELECT {$query_parts['select_sql']}
                   FROM {$this->table_name}
                   {$query_parts['join_sql']}
                   WHERE {$query_parts['where_sql']}
                   ORDER BY {$query_parts['orderby']} {$query_parts['order']}
                   {$query_parts['limit_sql']}";

        if (!empty($query_parts['params'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->wpdb->prepare is safe to use here.
            $query = $this->wpdb->prepare($query, $query_parts['params']);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to custom table for log retrieval.
        $results = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        if ($results) {
            foreach ($results as &$log_row) {
                if (!empty($log_row['bot_id'])) {
                    $log_row['bot_name'] = get_the_title($log_row['bot_id']) ?: __('(Deleted Bot)', 'gpt3-ai-content-generator');
                } elseif (empty($log_row['module'])) {
                    $log_row['bot_name'] = __('(No Bot)', 'gpt3-ai-content-generator');
                } else {
                    // Friendly labels for non-bot sources
                    if ($log_row['module'] === 'ai_post_enhancer') {
                        $log_row['bot_name'] = __('Content Assistant', 'gpt3-ai-content-generator');
                    } else {
                        $log_row['bot_name'] = esc_html(ucfirst(str_replace('_', ' ', $log_row['module'])));
                    }
                }

                if (!isset($log_row['user_display_name'])) {
                    if (!$log_row['is_guest'] && !empty($log_row['user_id'])) {
                        $ud = get_userdata($log_row['user_id']);
                        $log_row['user_display_name'] = $ud ? $ud->display_name : __('(Deleted User)', 'gpt3-ai-content-generator');
                    } elseif ($log_row['is_guest']) {
                        $log_row['user_display_name'] = __('Guest', 'gpt3-ai-content-generator');
                    } else {
                        $log_row['user_display_name'] = __('(Unknown User)', 'gpt3-ai-content-generator');
                    }
                }

            }
            unset($log_row);
        }
        return $results;
    }

    /**
     * Retrieves single conversation row by its primary ID.
     * Includes enrichment with user/bot names and module. Does NOT decode messages JSON here.
     */
    public function get_log_by_id(int $log_id): ?array
    {
        if (empty($log_id)) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Reason: $this->table_name is safe.
        $log_row = $this->wpdb->get_row($this->wpdb->prepare("SELECT id, bot_id, user_id, session_id, conversation_uuid, module, is_guest, message_count, first_message_ts, last_message_ts, ip_address, user_wp_role, created_at, updated_at FROM {$this->table_name} WHERE id = %d", $log_id), ARRAY_A);
        if (!$log_row) {
            return null;
        }
        if (!empty($log_row['bot_id'])) {
            $log_row['bot_name'] = get_the_title($log_row['bot_id']) ?: __('(Deleted Bot)', 'gpt3-ai-content-generator');
        } elseif (empty($log_row['module'])) {
            $log_row['bot_name'] = __('(No Bot)', 'gpt3-ai-content-generator');
        } else {
            // Friendly labels for non-bot sources
            if ($log_row['module'] === 'ai_post_enhancer') {
                $log_row['bot_name'] = __('Content Assistant', 'gpt3-ai-content-generator');
            } else {
                $log_row['bot_name'] = esc_html(ucfirst(str_replace('_', ' ', $log_row['module'])));
            }
        }

        if (!$log_row['is_guest'] && !empty($log_row['user_id'])) {
            $ud = get_userdata($log_row['user_id']);
            $log_row['user_display_name'] = $ud ? $ud->display_name : __('(Deleted User)', 'gpt3-ai-content-generator');
        } elseif ($log_row['is_guest']) {
            $log_row['user_display_name'] = __('Guest', 'gpt3-ai-content-generator');
            if (!empty($log_row['session_id'])) {
                $log_row['user_display_name'] .= ' (' . substr($log_row['session_id'], 0, 8) . '...)';
            }
        } else {
            $log_row['user_display_name'] = __('(Unknown User)', 'gpt3-ai-content-generator');
        }
        return $log_row;
    }

    /**
     * Deletes a single conversation thread based on provided identifiers.
     *
     * @param int|null $user_id The user ID (null for guests).
     * @param string|null $session_id The guest session ID (null for users).
     * @param int|null $bot_id The bot ID (null if not associated with a specific bot).
     * @param string $conversation_uuid The conversation UUID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_single_conversation(?int $user_id, ?string $session_id, ?int $bot_id, string $conversation_uuid)
    {
        // Basic validation
        if (empty($conversation_uuid)) {
            return new WP_Error('invalid_data', __('Conversation UUID is required for deletion.', 'gpt3-ai-content-generator'));
        }
        if (!$user_id && empty($session_id)) {
            return new WP_Error('invalid_data', __('User ID or Session ID is required for deletion.', 'gpt3-ai-content-generator'));
        }

        $where_clauses = ["conversation_uuid = %s"];
        $params = [$conversation_uuid];

        if ($bot_id !== null) {
            $where_clauses[] = "bot_id = %d";
            $params[] = $bot_id;
        } else {
            $where_clauses[] = "bot_id IS NULL";
        }

        if ($user_id) {
            $where_clauses[] = "user_id = %d";
            $params[] = $user_id;
        } else {
            $where_clauses[] = "(user_id IS NULL AND session_id = %s AND is_guest = 1)";
            $params[] = $session_id;
        }

        $where_sql = implode(" AND ", $where_clauses);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Reason: $this->table_name is safe. $where_sql contains placeholders for the prepare method.
        $query = $this->wpdb->prepare("DELETE FROM {$this->table_name} WHERE {$where_sql}", $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to custom table for log deletion.
        $deleted_rows = $this->wpdb->query($query);

        if ($deleted_rows === false) {
            return new WP_Error('db_delete_failed', __('Failed to delete conversation log.', 'gpt3-ai-content-generator'));
        }

        if ($deleted_rows === 0) {
            // Not necessarily an error, might have been deleted already or IDs didn't match
            return true; // Return true as the state is now "deleted"
        }

        return true;
    }
}
