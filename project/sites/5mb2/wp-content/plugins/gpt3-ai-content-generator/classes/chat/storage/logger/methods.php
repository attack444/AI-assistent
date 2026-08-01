<?php

namespace WPAICG\Chat\Storage\LoggerMethods;

use WPAICG\Core\AIPKit_Payload_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates a unique ID for message parents.
 *
 * @return string
 */
function generate_parent_id_logic(): string {
    return str_replace('.', '', uniqid('aipkit-parent-', true));
}

/**
 * Generates a unique ID for individual messages, removing the dot.
 *
 * @return string
 */
function generate_message_id_logic(): string {
    return str_replace('.', '', uniqid('aipkit-msg-', true));
}

/**
 * Sanitizes a payload array for safe log persistence.
 *
 * @param mixed $payload
 * @return mixed
 */
function sanitize_chat_log_payload_if_array($payload) {
    return AIPKit_Payload_Sanitizer::sanitize_payload_if_array($payload);
}

/**
 * Builds the new message object for logging.
 * UPDATED: Moved request_payload and response_data handling to be general.
 * UPDATED: Add provider, model, usage, feedback, and OpenAI/Google specific fields if present in log_data, regardless of role.
 * ADDED: Handling for 'system' role with 'trigger_log' event_sub_type to store detailed trigger log data.
 * FIXED: Ensure 'form_submission_stored' subtype correctly logs 'form_id' and 'submitted_data_snapshot'.
 *
 * @param array $log_data Associative array containing message details.
 * @param string $message_id The generated or provided message ID.
 * @param int $current_timestamp The current timestamp for the message.
 * @return array The structured message object.
 */
function build_message_object_logic(array $log_data, string $message_id, int $current_timestamp): array {
    $new_message = [
        'message_id'=> $message_id,
        'role'      => sanitize_key($log_data['message_role']),
        'content'   => wp_kses_post($log_data['message_content']), // wp_kses_post for message content
        'timestamp' => $current_timestamp,
    ];

    if ($new_message['role'] === 'system' && isset($log_data['event_sub_type']) && $log_data['event_sub_type'] === 'trigger_log') {
        $new_message['event_sub_type'] = 'trigger_log'; // Explicitly store this sub-type

        // Populate trigger_log_details if provided in $log_data
        if (isset($log_data['trigger_log_details']) && is_array($log_data['trigger_log_details'])) {
            $trigger_details = $log_data['trigger_log_details'];
            $sanitized_details = [];
            // Always copy common fields
            $sanitized_details['log_subtype'] = isset($trigger_details['log_subtype']) ? sanitize_key($trigger_details['log_subtype']) : 'unknown';
            $sanitized_details['trigger_id'] = isset($trigger_details['trigger_id']) ? sanitize_key($trigger_details['trigger_id']) : 'unknown';
            $sanitized_details['trigger_name'] = isset($trigger_details['trigger_name']) ? sanitize_text_field($trigger_details['trigger_name']) : 'Unnamed Trigger';
            $sanitized_details['event_name_processed'] = isset($trigger_details['event_name_processed']) ? sanitize_key($trigger_details['event_name_processed']) : 'unknown_event';

            // Subtype-specific fields
            if ($sanitized_details['log_subtype'] === 'trigger_evaluation') {
                $sanitized_details['conditions_met'] = isset($trigger_details['conditions_met']) ? (bool)$trigger_details['conditions_met'] : false;
                if (isset($trigger_details['conditions_summary'])) {
                    $sanitized_details['conditions_summary'] = sanitize_text_field($trigger_details['conditions_summary']);
                }
            } elseif ($sanitized_details['log_subtype'] === 'action_execution_start') {
                $sanitized_details['action_type'] = isset($trigger_details['action_type']) ? sanitize_key($trigger_details['action_type']) : 'unknown_action';
                if (isset($trigger_details['action_payload_summary'])) {
                    // Keep payload summary as an array/object, it will be JSON encoded later
                    $sanitized_details['action_payload_summary'] = $trigger_details['action_payload_summary'];
                }
            } elseif ($sanitized_details['log_subtype'] === 'action_execution_result') {
                $sanitized_details['action_type'] = isset($trigger_details['action_type']) ? sanitize_key($trigger_details['action_type']) : 'unknown_action';
                $sanitized_details['status'] = isset($trigger_details['status']) ? sanitize_key($trigger_details['status']) : 'unknown';
                if (isset($trigger_details['result_summary'])) {
                    $sanitized_details['result_summary'] = sanitize_text_field($trigger_details['result_summary']);
                }
                if (isset($trigger_details['error_details'])) {
                    $sanitized_details['error_details'] = sanitize_text_field($trigger_details['error_details']);
                }
            } elseif ($sanitized_details['log_subtype'] === 'form_submission_stored') {
                if (isset($trigger_details['form_id'])) {
                    $sanitized_details['form_id'] = sanitize_text_field($trigger_details['form_id']);
                }
                if (isset($trigger_details['submitted_data_snapshot'])) {
                    // submitted_data_snapshot is an array, it will be JSON encoded by the logger.
                    // No complex sanitization needed here as it's structured data for logging.
                    $sanitized_details['submitted_data_snapshot'] = $trigger_details['submitted_data_snapshot'];
                }
            }
            $new_message['trigger_log_details'] = $sanitized_details;
        }
        return $new_message; // Return early for trigger logs
    }


    // Add these fields if they exist in log_data, regardless of role (for user/bot messages)
    if (isset($log_data['ai_provider']) && !empty($log_data['ai_provider'])) {
        $new_message['provider'] = sanitize_text_field($log_data['ai_provider']);
    }
    if (isset($log_data['ai_model']) && !empty($log_data['ai_model'])) {
        $new_message['model'] = sanitize_text_field($log_data['ai_model']);
    }
    if (isset($log_data['usage']) && is_array($log_data['usage'])) {
        $new_message['usage'] = $log_data['usage']; // Assume usage data is safe
    }
    if (isset($log_data['feedback'])) {
        $new_message['feedback'] = sanitize_key($log_data['feedback']);
    }
    if (isset($log_data['request_payload'])) {
        $new_message['request_payload'] = sanitize_chat_log_payload_if_array($log_data['request_payload']);
    }
    if (isset($log_data['response_data'])) {
        $new_message['response_data'] = sanitize_chat_log_payload_if_array($log_data['response_data']);
    }
    // Store OpenAI specific IDs
    if (isset($log_data['openai_response_id']) && !empty($log_data['openai_response_id'])) {
        $new_message['openai_response_id'] = sanitize_text_field($log_data['openai_response_id']);
    }
    if (isset($log_data['used_previous_response_id']) && $log_data['used_previous_response_id'] === true) {
        $new_message['used_previous_response_id'] = true;
    }
    // Store Google Grounding Metadata
    if (isset($log_data['grounding_metadata']) && is_array($log_data['grounding_metadata'])) {
        $new_message['grounding_metadata'] = $log_data['grounding_metadata'];
    }
    if (isset($log_data['citations']) && is_array($log_data['citations']) && !empty($log_data['citations'])) {
        $new_message['citations'] = sanitize_chat_log_payload_if_array($log_data['citations']);
    }
    // Store Vector Search Scores
    if (isset($log_data['vector_search_scores']) && is_array($log_data['vector_search_scores']) && !empty($log_data['vector_search_scores'])) {
        $new_message['vector_search_scores'] = $log_data['vector_search_scores'];
    }

    return $new_message;
}

/**
 * Builds WHERE clauses and parameters for finding an existing conversation log row.
 *
 * @param string $conversation_uuid
 * @param string $module
 * @param int|null $bot_id
 * @param int|null $user_id
 * @param string|null $session_id
 * @return array ['where_sql' => string, 'params' => array]
 */
function build_where_clauses_logic(
    string $conversation_uuid,
    string $module,
    ?int $bot_id,
    ?int $user_id,
    ?string $session_id
): array {
    $where_clauses = ["conversation_uuid = %s", "module = %s"];
    $params = [$conversation_uuid, $module];

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
        // Ensure session_id is not empty for guest condition
        if (empty($session_id)) {
            // This case should ideally be caught by validation in log_message
            // but adding a safeguard here.
            // Fallback to a condition that won't match anything safely or throw an error.
            // For now, let it proceed, log_message should have caught it.
            $where_clauses[] = "1=0"; // Will not match
        } else {
            $where_clauses[] = "(user_id IS NULL AND session_id = %s AND is_guest = 1)";
            $params[] = $session_id;
        }
    }
    return ['where_sql' => implode(" AND ", $where_clauses), 'params' => $params];
}

/**
 * Updates an existing conversation log row with a new message.
 *
 * @param \wpdb $wpdb WordPress database object.
 * @param string $table_name The name of the log table.
 * @param array $existing_log_row The existing log row data from DB.
 * @param array $new_message The new message object to add.
 * @param int $current_timestamp The current timestamp for the message.
 * @param string|null $ip_to_store Anonymized IP address.
 * @param string|null $user_wp_role User's WordPress role.
 * @return array|false ['log_id' => int, 'message_id' => string] on success, false on failure.
 */
function update_existing_log_logic(
    \wpdb $wpdb,
    string $table_name,
    array $existing_log_row,
    array $new_message,
    int $current_timestamp,
    ?string $ip_to_store,
    ?string $user_wp_role
) {
    $log_id = absint($existing_log_row['id']);
    $messages_json = $existing_log_row['messages'] ?? null;
    $conversation_data = $messages_json ? json_decode($messages_json, true) : null;

    if (!is_array($conversation_data) || !isset($conversation_data['parent_id']) || !isset($conversation_data['messages'])) {
        $parent_id = generate_parent_id_logic(); // Call namespaced function
        $messages_array = [];
    } else {
        $parent_id = $conversation_data['parent_id'];
        $messages_array = $conversation_data['messages'];
         if (!is_array($messages_array)) $messages_array = []; // Ensure it's an array
    }

    $messages_array[] = $new_message;

    $updated_conversation_data = [
        'parent_id' => $parent_id,
        'messages' => $messages_array,
    ];

    $update_data_fields = [
        'messages'         => wp_json_encode($updated_conversation_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'message_count'    => count($messages_array),
        'last_message_ts'  => $current_timestamp,
        'updated_at'       => current_time('mysql', 1),
        'ip_address'       => $ip_to_store, // This might be null
        'user_wp_role'     => $user_wp_role ? sanitize_text_field($user_wp_role) : null, // This might be null
    ];
    $update_formats_map = ['%s', '%d', '%d', '%s', '%s', '%s']; // Corresponds to update_data_fields order

    $data_to_update = [];
    $formats_to_use = [];
    foreach ($update_data_fields as $key => $value) {
        // Include key if it's explicitly not null, OR if it's one of the keys that *can* be null
        if ($value !== null || in_array($key, ['ip_address', 'user_wp_role'])) {
             $data_to_update[$key] = $value;
             $key_index = array_search($key, array_keys($update_data_fields));
             if ($key_index !== false) {
                 $formats_to_use[] = $update_formats_map[$key_index];
             }
        }
    }

    if (empty($data_to_update)) {
        // Nothing changed except potentially the messages array itself if no other metadata was updated
        // This path should ideally not be taken if we always update 'messages' and 'message_count'
        return ['log_id' => $log_id, 'message_id' => $new_message['message_id']];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table is necessary. Cache is invalidated by the calling function.
    $updated = $wpdb->update(
        $table_name,
        $data_to_update,
        ['id' => $log_id],
        $formats_to_use,
        ['%d'] // WHERE format for id
    );

    if ($updated === false) {
        return false;
    }

    if (isset($existing_log_row['conversation_uuid'])) {
        $conversation_uuid = $existing_log_row['conversation_uuid'];
        $cache_group = 'aipkit_chat_logs';
        wp_cache_delete('conv_history_' . $conversation_uuid, $cache_group);
        wp_cache_delete('conv_full_log_' . $conversation_uuid, $cache_group);
        wp_cache_delete('conv_meta_' . $conversation_uuid, $cache_group);
        // Invalidate the list cache as well, as it contains summary data
        $user_id_for_list = $existing_log_row['user_id'] ?: null;
        $session_id_for_list = $existing_log_row['session_id'] ?: null;
        $bot_id_for_list = $existing_log_row['bot_id'] ?: 0;
        $cache_key_identifier = $user_id_for_list ? "user_{$user_id_for_list}" : "guest_{$session_id_for_list}";
        $list_cache_key = "conv_list_{$bot_id_for_list}_{$cache_key_identifier}";
        wp_cache_delete($list_cache_key, $cache_group);
    }
    // --- END: Invalidate cache ---

    return ['log_id' => $log_id, 'message_id' => $new_message['message_id']];
}

/**
 * Inserts a new conversation log row.
 *
 * @param \wpdb $wpdb WordPress database object.
 * @param string $table_name The name of the log table.
 * @param int|null $bot_id
 * @param int|null $user_id
 * @param string|null $session_id
 * @param string $conversation_uuid
 * @param string $module
 * @param int $is_guest
 * @param array $new_message The first message object.
 * @param int $current_timestamp The current timestamp for the message.
 * @param string|null $ip_to_store Anonymized IP address.
 * @param string|null $user_wp_role User's WordPress role.
 * @return array|false ['log_id' => int, 'message_id' => string, 'is_new_session' => true] on success, false on failure.
 */
function insert_new_log_logic(
    \wpdb $wpdb,
    string $table_name,
    ?int $bot_id,
    ?int $user_id,
    ?string $session_id,
    string $conversation_uuid,
    string $module,
    int $is_guest,
    array $new_message,
    int $current_timestamp,
    ?string $ip_to_store,
    ?string $user_wp_role
) {
    $parent_id = generate_parent_id_logic(); // Call namespaced function
    $messages_array = [$new_message];

    $conversation_data = [
         'parent_id' => $parent_id,
         'messages' => $messages_array,
    ];

    $insert_data_fields = [
        'bot_id'            => $bot_id,
        'user_id'           => $user_id,
        'session_id'        => $session_id,
        'conversation_uuid' => $conversation_uuid,
        'module'            => $module,
        'is_guest'          => $is_guest,
        'messages'          => wp_json_encode($conversation_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'message_count'     => 1,
        'first_message_ts'  => $current_timestamp,
        'last_message_ts'   => $current_timestamp,
        'ip_address'        => $ip_to_store, // This might be null
        'user_wp_role'      => $user_wp_role ? sanitize_text_field($user_wp_role) : null, // This might be null
        'created_at'        => current_time('mysql', 1),
        'updated_at'        => current_time('mysql', 1),
    ];
    $formats_map = ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s'];

    $data_to_insert = [];
    $formats_to_use = [];
    foreach ($insert_data_fields as $key => $value) {
        // Always include the key in data_to_insert, even if null.
        // The format string will determine how $wpdb->prepare handles NULLs.
        $data_to_insert[$key] = $value;
        $key_index = array_search($key, array_keys($insert_data_fields));
        if ($key_index !== false) {
            $formats_to_use[] = $formats_map[$key_index];
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Necessary insert operation into a custom table.
    $inserted = $wpdb->insert($table_name, $data_to_insert, $formats_to_use);

    if ($inserted === false) {
        return false;
    }
    $new_log_id = $wpdb->insert_id;
    return ['log_id' => $new_log_id, 'message_id' => $new_message['message_id'], 'is_new_session' => true];
}
