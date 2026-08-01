<?php

namespace WPAICG\Chat\Storage\ReaderMethods;

use WPAICG\Chat\Storage\ConversationReader;
use WPAICG\Chat\Storage\LogQueryHelper;
use WPAICG\Core\AIPKit_Payload_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

// --- get-conversation-thread-history.php ---
/**
 * Redacts sensitive payload fields in an already-stored message object.
 *
 * @param array $message
 * @return array
 */
function sanitize_message_payload_fields_for_history(array $message): array {
    foreach (['request_payload', 'response_data'] as $field_key) {
        if (array_key_exists($field_key, $message)) {
            $message[$field_key] = AIPKit_Payload_Sanitizer::sanitize_payload_if_array($message[$field_key]);
        }
    }

    return $message;
}

/**
 * Logic for the get_conversation_thread_history method of ConversationReader.
 * Retrieves the conversation history (array of messages) for a specific conversation thread.
 * Handles the new JSON structure. Includes feedback, usage, openai_response_id, and used_previous_response_id.
 * MODIFIED: Filters out system messages with event_sub_type 'trigger_log'.
 *
 * @param ConversationReader $readerInstance The instance of the ConversationReader class.
 * @param int|null $user_id The user ID (null for guests).
 * @param string|null $session_id The guest UUID (null for logged-in users).
 * @param int $bot_id The bot ID.
 * @param string $conversation_uuid The specific conversation thread UUID.
 * @return array The array of messages [{message_id, role, content, timestamp, provider?, model?, feedback?, usage?, openai_response_id?, used_previous_response_id?}, ...].
 */
function get_conversation_thread_history_logic(
    ConversationReader $readerInstance,
    ?int $user_id,
    ?string $session_id,
    int $bot_id,
    string $conversation_uuid
): array {
    if (empty($bot_id) || empty($conversation_uuid) || (!$user_id && empty($session_id))) {
        return [];
    }

    $wpdb = $readerInstance->get_wpdb();
    $table_name = $readerInstance->get_table_name();

    $cache_key = 'conv_history_' . $conversation_uuid;
    $cache_group = 'aipkit_chat_logs';
    $messages_json = wp_cache_get($cache_key, $cache_group);

    if (false === $messages_json) {
        $where_sql = "bot_id = %d AND conversation_uuid = %s AND ";
        $params = [$bot_id, $conversation_uuid];
        if ($user_id) {
            $where_sql .= "user_id = %d";
            $params[] = $user_id;
        } else {
            $where_sql .= "(user_id IS NULL AND session_id = %s AND is_guest = 1)";
            $params[] = $session_id;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: This is a prepared query with parameters.
        $messages_json = $wpdb->get_var($wpdb->prepare("SELECT messages FROM {$table_name} WHERE {$where_sql} LIMIT 1", $params));
        wp_cache_set($cache_key, $messages_json, $cache_group, HOUR_IN_SECONDS);
    }
    // --- END: Caching logic ---

    if (empty($messages_json)) {
        return [];
    }

    $conversation_data = json_decode($messages_json, true);
    $messages_array = null;

    // Check if it's the new structure or the old simple array
    if (is_array($conversation_data) && isset($conversation_data['parent_id']) && isset($conversation_data['messages']) && is_array($conversation_data['messages'])) {
        $messages_array = $conversation_data['messages'];
    } elseif (is_array($conversation_data)) { // Assume old structure (simple array) for backward compatibility
        $messages_array = $conversation_data;
    } else {
        return [];
    }

    $filtered_messages = [];
    foreach ($messages_array as $msg) {
        // --- MODIFICATION: Filter out system trigger logs ---
        if (isset($msg['role']) && $msg['role'] === 'system' && isset($msg['event_sub_type']) && $msg['event_sub_type'] === 'trigger_log') {
            continue; // Skip this message
        }

        if (isset($msg['timestamp'])) {
            $msg['timestamp'] = (int)$msg['timestamp'];
        }
        if (!isset($msg['message_id'])) {
            $msg['message_id'] = generate_message_id_logic(); // Call namespaced function
        }
        $msg = sanitize_message_payload_fields_for_history($msg);
        $filtered_messages[] = $msg;
    }

    return $filtered_messages;
}

// --- get-all-conversation-data.php ---
/**
 * Logic for the get_all_conversation_data method of ConversationReader.
 * Retrieves summary data for all distinct conversations for a user/session and bot.
 * Handles the new JSON structure to extract the title.
 *
 * @param ConversationReader $readerInstance The instance of the ConversationReader class.
 * @param int|null $user_id The user ID (null for guests).
 * @param string|null $session_id The guest UUID (null for logged-in users).
 * @param int $bot_id The bot ID.
 * @return array|null An array of conversation summaries or null on error.
 */
function get_all_conversation_data_logic(
    ConversationReader $readerInstance,
    ?int $user_id,
    ?string $session_id,
    int $bot_id
): ?array {
    if (empty($bot_id) || (!$user_id && empty($session_id))) {
        return null;
    }

    $wpdb = $readerInstance->get_wpdb();
    $table_name = $readerInstance->get_table_name();
    $query_helper = $readerInstance->get_query_helper();

    $cache_key_identifier = $user_id ? "user_{$user_id}" : "guest_{$session_id}";
    $cache_key = "conv_list_{$bot_id}_{$cache_key_identifier}";
    $cache_group = 'aipkit_chat_logs';
    $summaries = wp_cache_get($cache_key, $cache_group);

    if (false === $summaries) {
        $filters = ['bot_id' => $bot_id];
        if ($user_id) {
            $filters['user_id'] = $user_id;
        } else {
            $filters['session_id'] = $session_id;
            $filters['user_id'] = null; // Explicitly set user_id to null for guest session_id queries
        }

        $query_parts = $query_helper->build_conversation_query_parts($filters, 'last_message_ts', 'DESC', 0, 0, true);
        $query = "SELECT {$query_parts['select_sql']} FROM {$table_name} {$query_parts['join_sql']} WHERE {$query_parts['where_sql']} ORDER BY {$query_parts['orderby']} {$query_parts['order']}";
        if (!empty($query_parts['params'])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Reason: This is a prepared query with parameters.
            $query = $wpdb->prepare($query, $query_parts['params']);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: This is a prepared query with parameters.
        $summaries = $wpdb->get_results($query, ARRAY_A);
        wp_cache_set($cache_key, $summaries, $cache_group, MINUTE_IN_SECONDS * 5); // Cache for 5 minutes
    }
    // --- END: Caching logic ---


    if ($summaries === null) {
        return null;
    }
    if (empty($summaries)) {
        return [];
    }

    $conversation_list = [];
    foreach ($summaries as $summary) {
        $conv_uuid = $summary['conversation_uuid'];
        $timestamp = (int)$summary['last_message_ts'];
        $title = $conv_uuid; // Default title

        $conversation_data = json_decode($summary['messages'] ?? '[]', true);
        $messages_array = null;
        // Check for new structure
        if (is_array($conversation_data) && isset($conversation_data['messages']) && is_array($conversation_data['messages'])) {
            $messages_array = $conversation_data['messages'];
        } elseif (is_array($conversation_data)) { // Backward compatibility for old structure
            $messages_array = $conversation_data;
        }

        if (is_array($messages_array)) {
            foreach ($messages_array as $msg) { // Find first user message
                if (($msg['role'] ?? '') === 'user' && !empty($msg['content'])) {
                    $title = wp_trim_words($msg['content'], 5, '...');
                    break;
                }
            }
            if ($title === $conv_uuid && !empty($messages_array[0]['content'])) { // Fallback to first message
                $title = wp_trim_words($messages_array[0]['content'], 5, '...');
            }
        }

        $conversation_list[] = ['id' => $conv_uuid, 'title' => $title, 'timestamp' => $timestamp];
    }

    usort($conversation_list, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    return $conversation_list;
}

// --- generate-message-id.php ---
/**
 * Generates a unique ID for individual messages, removing the dot.
 * This logic was previously a private method in ConversationReader.
 *
 * @return string
 */
function generate_message_id_logic(): string {
    return str_replace('.', '', uniqid('aipkit-msg-', true));
}
