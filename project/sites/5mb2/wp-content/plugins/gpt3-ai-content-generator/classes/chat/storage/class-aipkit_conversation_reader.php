<?php

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Storage\LogQueryHelper; // Keep this for constructor
use WPAICG\Chat\Storage\ReaderMethods;  // Use the new namespace for logic functions

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/reader/methods.php';


/**
 * Handles reading conversation history and summaries from the log table.
 * Public methods now delegate to namespaced functions in the reader/ subdirectory.
 */
class ConversationReader {

    private $wpdb;
    private $table_name;
    private $query_helper;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aipkit_chat_logs';
        $this->query_helper = new LogQueryHelper($this->table_name);
    }

    /**
     * Public getter for $wpdb.
     * @return \wpdb
     */
    public function get_wpdb(): \wpdb {
        return $this->wpdb;
    }

    /**
     * Public getter for $table_name.
     * @return string
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    /**
     * Public getter for $query_helper.
     * @return LogQueryHelper
     */
    public function get_query_helper(): LogQueryHelper {
        return $this->query_helper;
    }


    /**
     * Retrieves the conversation history (array of messages) for a specific conversation thread.
     * Handles the new JSON structure. Includes feedback, usage, openai_response_id, and used_previous_response_id.
     *
     * @param int|null $user_id The user ID (null for guests).
     * @param string|null $session_id The guest UUID (null for logged-in users).
     * @param int $bot_id The bot ID.
     * @param string $conversation_uuid The specific conversation thread UUID.
     * @return array The array of messages [{message_id, role, content, timestamp, provider?, model?, feedback?, usage?, openai_response_id?, used_previous_response_id?}, ...].
     */
    public function get_conversation_thread_history(?int $user_id, ?string $session_id, int $bot_id, string $conversation_uuid): array {
        return ReaderMethods\get_conversation_thread_history_logic($this, $user_id, $session_id, $bot_id, $conversation_uuid);
    }

     /**
      * Retrieves summary data for all distinct conversations for a user/session and bot.
      * Handles the new JSON structure to extract the title.
      *
      * @param int|null $user_id The user ID (null for guests).
      * @param string|null $session_id The guest UUID (null for logged-in users).
      * @param int $bot_id The bot ID.
      * @return array|null An array of conversation summaries or null on error.
      */
     public function get_all_conversation_data(?int $user_id, ?string $session_id, int $bot_id): ?array {
        return ReaderMethods\get_all_conversation_data_logic($this, $user_id, $session_id, $bot_id);
     }

    // Private generate_message_id removed, now available as namespaced ReaderMethods\generate_message_id_logic
}
