<?php


namespace WPAICG\Core\Stream\Contexts\ContentWriter;

if (!defined('ABSPATH')) {
    exit;
}

use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WP_Error;

// Load method logic file
require_once __DIR__ . '/fn-process-content-writer.php';

/**
 * Handles processing stream requests specifically for the 'content_writer' context.
 */
class SSEContentWriterStreamContextHandler
{
    private $log_storage;
    private $ai_caller;
    private $vector_store_manager;

    public function __construct(LogStorage $log_storage)
    {
        $this->log_storage = $log_storage;

        // Dependencies should be loaded by AIPKit_Dependency_Loader.
        // No late require_once calls here.
        if (class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $this->ai_caller = new AIPKit_AI_Caller();
        }

        if (class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) {
            $this->vector_store_manager = new AIPKit_Vector_Store_Manager();
        }
    }

    // Getter for LogStorage, used by the logic function
    public function get_log_storage(): LogStorage
    {
        return $this->log_storage;
    }

    public function get_ai_caller(): ?AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }

    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }

    /**
     * Processes a content writer stream request.
     * @param array $cached_data Contains 'user_message', 'system_instruction', 'provider', 'model', 'ai_params', 'conversation_uuid', 'user_id'.
     * @param array $get_params  Original $_GET parameters (not directly used here as data comes from cache).
     * @return array|WP_Error Prepared data or WP_Error.
     */
    public function process(array $cached_data, array $get_params)
    {
        return process_content_writer_logic($this, $cached_data, $get_params);
    }
}
