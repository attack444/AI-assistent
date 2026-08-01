<?php


namespace WPAICG\Chat\Storage;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles storing feedback for specific messages within a conversation log.
 */
class FeedbackManager
{
    private $wpdb;
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aipkit_chat_logs';
    }

    /**
     * Stores feedback ('up' or 'down') for a specific message within a conversation.
     *
     * @param int|null $user_id The user ID (null for guests).
     * @param string|null $session_id The guest UUID (null for logged-in users).
     * @param int $bot_id The bot ID.
     * @param string $conversation_uuid The specific conversation thread UUID.
     * @param string $message_id The ID of the message receiving feedback.
     * @param string $feedback_type 'up' or 'down'.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function store_feedback_for_message(?int $user_id, ?string $session_id, int $bot_id, string $conversation_uuid, string $message_id, string $feedback_type)
    {
        // Validation
        if (empty($bot_id) || empty($conversation_uuid) || empty($message_id) || !in_array($feedback_type, ['up', 'down'])) {
            return new \WP_Error('invalid_feedback_data', __('Missing required data for feedback.', 'gpt3-ai-content-generator'));
        }
        if (!$user_id && empty($session_id)) {
            return new \WP_Error('missing_identifier', __('User or Session ID is required for feedback.', 'gpt3-ai-content-generator'));
        }

        // Find the conversation log row
        $where_sql = "bot_id = %d AND conversation_uuid = %s";
        $params = [$bot_id, $conversation_uuid];
        if ($user_id) {
            $where_sql .= " AND user_id = %d";
            $params[] = $user_id;
        } else {
            $where_sql .= " AND user_id IS NULL AND session_id = %s AND is_guest = 1";
            $params[] = $session_id;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Reason: $this->table_name is safe (from $wpdb->prefix), and $where_sql contains placeholders.
        $sql = "SELECT id, messages FROM {$this->table_name} WHERE {$where_sql} LIMIT 1";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: $this->wpdb->prepare is used below.
        $log_row = $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A); 

        if (!$log_row) {
            return new \WP_Error('conversation_not_found', __('Conversation not found.', 'gpt3-ai-content-generator'), ['status' => 404]);
        }

        $messages_json = $log_row['messages'] ?? null;
        $conversation_data = $messages_json ? json_decode($messages_json, true) : null;

        // Check structure
        if (!is_array($conversation_data) || !isset($conversation_data['parent_id']) || !isset($conversation_data['messages']) || !is_array($conversation_data['messages'])) {
            return new \WP_Error('invalid_log_structure', __('Error processing conversation data.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        $messages_array = $conversation_data['messages'];
        $message_found = false;

        // Find the message and add/update feedback
        foreach ($messages_array as &$msg) { // Use reference to modify
            if (isset($msg['message_id']) && $msg['message_id'] === $message_id) {
                $msg['feedback'] = sanitize_key($feedback_type); // Add or overwrite feedback
                $message_found = true;
                break;
            }
        }
        unset($msg); // Unset reference

        if (!$message_found) {
            return new \WP_Error('message_not_found', __('Message not found within the conversation.', 'gpt3-ai-content-generator'), ['status' => 404]);
        }

        // Update the database
        $updated_conversation_data = [
            'parent_id' => $conversation_data['parent_id'],
            'messages' => $messages_array,
            // Note: We don't update last_message_ts for feedback
        ];
        $updated = $this->wpdb->update(
            $this->table_name,
            ['messages' => wp_json_encode($updated_conversation_data, JSON_UNESCAPED_UNICODE)],
            ['id' => $log_row['id']],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            return new \WP_Error('db_update_failed', __('Failed to save feedback.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }

        return true;
    }
}
