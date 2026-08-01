<?php


namespace WPAICG\Chat\Storage;

use WPAICG\AIPKit\Addons\AIPKit_IP_Anonymization;
use WPAICG\Core\Moderation\AIPKit_Global_Security_Settings;
// Use new LoggerMethods namespace
use WPAICG\Chat\Storage\LoggerMethods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles logging individual messages to the conversation log table.
 * Creates new conversation rows or updates existing ones.
 * Manages the JSON structure within the 'messages' column.
 * This class now delegates its core logic to namespaced functions.
 */
class ConversationLogger
{
    private $wpdb;
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aipkit_chat_logs';

        // Ensure AIPKit_IP_Anonymization is loaded as it's used by externalized logic
        if (!class_exists(AIPKit_IP_Anonymization::class)) {
            $ip_anon_path = WPAICG_PLUGIN_DIR . 'classes/addons/class-aipkit-ip-anonymization.php';
            if (file_exists($ip_anon_path)) {
                require_once $ip_anon_path;
            }
        }
        if (!class_exists(AIPKit_Global_Security_Settings::class)) {
            $security_settings_path = WPAICG_PLUGIN_DIR . 'classes/core/moderation/AIPKit_Global_Security_Settings.php';
            if (file_exists($security_settings_path)) {
                require_once $security_settings_path;
            }
        }
    }

    /**
     * Logs a single message by finding the appropriate conversation row
     * and appending the message to its 'messages' JSON array within the structured object.
     * Creates a new conversation row if one doesn't exist.
     *
     * @param array $log_data Associative array containing message details.
     *              Must include: conversation_uuid, message_role, message_content, module.
     *              Must include either user_id OR session_id.
     *              Should include bot_id if applicable to the module (can be null).
     *              May include: bot_message_id, usage (token usage data), ai_provider, ai_model, feedback,
     *                           request_payload, response_data (for image gen),
     *                           openai_response_id, used_previous_response_id.
     * @return array|false ['log_id' => int, 'message_id' => string, 'is_new_session' => bool] on success, false on failure.
     */
    public function log_message(array $log_data)
    {
        // --- 1. Basic Validation ---
        if (empty($log_data['conversation_uuid']) || empty($log_data['message_role']) ||
            !isset($log_data['message_content']) || empty($log_data['module']) ||
            (!isset($log_data['user_id']) && empty($log_data['session_id']))
        ) {
            return false;
        }

        // --- 2. Sanitize Core Identifiers ---
        $bot_id = null;
        if (isset($log_data['bot_id'])) {
            if (is_numeric($log_data['bot_id']) && intval($log_data['bot_id']) > 0) {
                $bot_id = absint($log_data['bot_id']);
            } // null, empty string, '0' will result in $bot_id = null
        }
        $user_id           = isset($log_data['user_id']) && $log_data['user_id'] > 0 ? absint($log_data['user_id']) : null;
        $session_id        = $user_id ? null : sanitize_text_field($log_data['session_id'] ?? '');
        $conversation_uuid = sanitize_key($log_data['conversation_uuid']);
        $module            = sanitize_key($log_data['module']);
        $is_guest          = $user_id ? 0 : 1;
        $original_ip = isset($log_data['ip_address']) ? sanitize_text_field($log_data['ip_address']) : null;
        $global_ip_anonymize = class_exists(AIPKit_Global_Security_Settings::class)
            && AIPKit_Global_Security_Settings::is_ip_anonymization_enabled();
        $ip_anonymize = $global_ip_anonymize
            || (isset($log_data['ip_anonymize']) && in_array($log_data['ip_anonymize'], ['1', 1, true], true));
        $ip_to_store = ($ip_anonymize && class_exists(AIPKit_IP_Anonymization::class))
            ? AIPKit_IP_Anonymization::maybe_anonymize($original_ip, true)
            : $original_ip;
        $user_wp_role = $log_data['user_wp_role'] ?? ($user_id ? implode(', ', wp_get_current_user()->roles) : null);

        // --- 3. Determine Message ID and Timestamp ---
        $current_timestamp = isset($log_data['timestamp']) ? absint($log_data['timestamp']) : time();
        $message_id = isset($log_data['bot_message_id']) && !empty(trim($log_data['bot_message_id']))
                       ? str_replace('.', '', sanitize_key($log_data['bot_message_id']))
                       : (isset($log_data['message_id']) && !empty(trim($log_data['message_id']))
                          ? str_replace('.', '', sanitize_key($log_data['message_id']))
                          : LoggerMethods\generate_message_id_logic());

        // --- 4. Build the New Message Object ---
        $new_message = LoggerMethods\build_message_object_logic($log_data, $message_id, $current_timestamp);

        // --- 5. Find Existing Conversation Row ---
        $where_parts = LoggerMethods\build_where_clauses_logic($conversation_uuid, $module, $bot_id, $user_id, $session_id);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Reason: $this->table_name is safe (from $wpdb->prefix), and $where_parts['where_sql'] contains placeholders for the prepare method.
        $sql = "SELECT id, messages FROM {$this->table_name} WHERE {$where_parts['where_sql']} LIMIT 1";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: $sql is constructed with placeholders and safe variables, and is prepared here.
        $existing_log_row = $this->wpdb->get_row($this->wpdb->prepare($sql, $where_parts['params']), ARRAY_A);

        // --- 6. Update or Insert ---
        if ($existing_log_row) {
            $update_result = LoggerMethods\update_existing_log_logic(
                $this->wpdb,
                $this->table_name,
                $existing_log_row,
                $new_message,
                $current_timestamp,
                $ip_to_store,
                $user_wp_role
            );
            if (is_array($update_result)) {
                $update_result['is_new_session'] = false; // It's an update to an existing log
            }
            return $update_result;
        } else {
            // insert_new_log_logic already sets 'is_new_session' => true
            return LoggerMethods\insert_new_log_logic(
                $this->wpdb,
                $this->table_name,
                $bot_id,
                $user_id,
                $session_id,
                $conversation_uuid,
                $module,
                $is_guest,
                $new_message,
                $current_timestamp,
                $ip_to_store,
                $user_wp_role
            );
        }
    }
}
