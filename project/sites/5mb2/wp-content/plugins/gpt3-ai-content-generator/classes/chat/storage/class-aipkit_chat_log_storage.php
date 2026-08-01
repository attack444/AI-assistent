<?php

namespace WPAICG\Chat\Storage;

// Include the new classes
use WPAICG\Chat\Storage\ConversationLogger;
use WPAICG\Chat\Storage\ConversationReader;
use WPAICG\Chat\Storage\LogManager;
use WPAICG\Chat\Storage\FeedbackManager;
// Keep dependencies needed by the new classes if they aren't self-contained
use WPAICG\Chat\Storage\LogQueryHelper;
use WPAICG\Chat\Admin\AdminSetup; // Needed for Bot name lookup

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Facade class for interacting with chat conversation logs.
 * Delegates operations to specialized classes: ConversationLogger, ConversationReader, LogManager, FeedbackManager.
 */
class LogStorage {

    private $logger;
    private $reader;
    private $manager;
    private $feedback_manager;

    public function __construct() {
        // Instantiate the specialized classes
        $this->logger = new ConversationLogger();
        $this->reader = new ConversationReader();
        $this->manager = new LogManager();
        $this->feedback_manager = new FeedbackManager();
    }

    /**
     * Logs a single message. Delegates to ConversationLogger.
     * @return mixed[]|false
     */
    public function log_message(array $log_data) {
        return $this->logger->log_message($log_data);
    }

    /**
     * Retrieves the conversation history. Delegates to ConversationReader.
     */
    public function get_conversation_thread_history(?int $user_id, ?string $session_id, int $bot_id, string $conversation_uuid): array {
        return $this->reader->get_conversation_thread_history($user_id, $session_id, $bot_id, $conversation_uuid);
    }

    /**
     * Retrieves conversation summaries for the sidebar. Delegates to ConversationReader.
     */
     public function get_all_conversation_data(?int $user_id, ?string $session_id, int $bot_id): ?array {
         return $this->reader->get_all_conversation_data($user_id, $session_id, $bot_id);
     }

    /**
     * Retrieves conversation summaries for the admin log view. Delegates to LogManager.
     */
    public function get_logs(array $filters = [], int $limit = 50, int $offset = 0, string $orderby = 'last_message_ts', string $order = 'DESC'): array {
        return $this->manager->get_logs($filters, $limit, $offset, $orderby, $order);
    }

    /**
     * Counts conversation rows matching filters. Delegates to LogManager.
     */
    public function count_logs(array $filters = []): int {
        return $this->manager->count_logs($filters);
    }

    /**
     * Returns aggregate metrics for conversation rows matching the filters.
     */
    public function get_log_summary(array $filters = []): array {
        return $this->manager->get_log_summary($filters);
    }

    /**
     * Deletes conversation rows older than X days. Delegates to LogManager.
     * @return int|false
     */
    public function prune_logs(float $days) {
        return $this->manager->prune_logs($days);
    }

    /**
     * Deletes conversation rows matching filters. Delegates to LogManager.
     * @return int|false
     */
    public function delete_logs(array $filters = [], int $limit = 500) {
        return $this->manager->delete_logs($filters, $limit);
    }

    /**
     * NEW: Deletes a single conversation thread. Delegates to LogManager.
     * @return bool|\WP_Error
     */
    public function delete_single_conversation(?int $user_id, ?string $session_id, ?int $bot_id, string $conversation_uuid) {
         return $this->manager->delete_single_conversation($user_id, $session_id, $bot_id, $conversation_uuid);
    }

    /**
     * Gets raw conversation data for export. Delegates to LogManager.
     */
    public function get_raw_conversations_for_export(array $filters = [], int $limit = 100, int $offset = 0): array {
        return $this->manager->get_raw_conversations_for_export($filters, $limit, $offset);
    }

    /**
     * Stores feedback for a specific message. Delegates to FeedbackManager.
     * Note: This method wasn't in the original LogStorage but makes sense here for the facade.
     * @return bool|\WP_Error
     */
    public function store_feedback(?int $user_id, ?string $session_id, int $bot_id, string $conversation_uuid, string $message_id, string $feedback_type) {
        return $this->feedback_manager->store_feedback_for_message($user_id, $session_id, $bot_id, $conversation_uuid, $message_id, $feedback_type);
    }

    // --- Potentially keep methods that don't fit neatly elsewhere or are simple helpers ---

    /**
     * Retrieves single conversation row by its primary ID. Delegates to LogManager.
     * Kept here for completeness, though less commonly needed externally.
     */
    public function get_log_by_id(int $log_id): ?array {
        return $this->manager->get_log_by_id($log_id);
    }
}
