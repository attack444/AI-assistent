<?php


namespace WPAICG\Core\Stream\Request;

use WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager; // Corrected use statement
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor;
use WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor;
use WPAICG\Core\Stream\Vector\SSEVectorContextHelper;
use WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage;
use WPAICG\Core\Stream\Contexts\Chat\SSEChatStreamContextHandler;
use WPAICG\Core\Stream\Contexts\ContentWriter\SSEContentWriterStreamContextHandler;
use WPAICG\Core\Stream\Contexts\AIForms\SSEAIFormsStreamContextHandler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic file
require_once __DIR__ . '/methods.php';

/**
 * Handles initial validation, data retrieval, and payload formatting for SSE requests.
 * Routes requests to context-specific handlers.
 */
class SSERequestHandler
{
    private $log_storage;
    private $sse_message_cache;
    private $token_manager;
    private $ai_caller;
    private $vector_store_manager;
    private $pinecone_post_processor;
    private $qdrant_post_processor;
    private $sse_vector_context_helper;
    private $ai_form_storage;
    // Context Handlers
    private $chat_context_handler;
    private $content_writer_context_handler;
    private $ai_forms_context_handler;


    public function __construct(?LogStorage $log_storage_passed = null)
    {
        // Dependencies should be loaded by AIPKit_Dependency_Loader.
        // Constructors now assume classes are available.

        $this->log_storage = $log_storage_passed;
        if (!$this->log_storage && class_exists(\WPAICG\Chat\Storage\LogStorage::class)) {
            $this->log_storage = new \WPAICG\Chat\Storage\LogStorage();
        }

        $this->sse_message_cache = class_exists(\WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache::class)
            ? new \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache()
            : null;

        $this->token_manager = class_exists(\WPAICG\Core\TokenManager\AIPKit_Token_Manager::class)
            ? new \WPAICG\Core\TokenManager\AIPKit_Token_Manager()
            : null;

        $this->ai_caller = class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)
            ? new \WPAICG\Core\AIPKit_AI_Caller()
            : null;

        $this->vector_store_manager = class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)
            ? new \WPAICG\Vector\AIPKit_Vector_Store_Manager()
            : null;

        $this->pinecone_post_processor = class_exists(\WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor::class)
            ? new \WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor()
            : null;

        $this->qdrant_post_processor = class_exists(\WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor::class)
            ? new \WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor()
            : null;


        $this->sse_vector_context_helper = (class_exists(\WPAICG\Core\Stream\Vector\SSEVectorContextHelper::class) && $this->ai_caller && $this->vector_store_manager)
            ? new \WPAICG\Core\Stream\Vector\SSEVectorContextHelper($this->ai_caller, $this->vector_store_manager, $this->pinecone_post_processor, $this->qdrant_post_processor)
            : null;

        $this->ai_form_storage = class_exists(\WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage::class)
            ? new \WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage()
            : null;

        // Instantiate Context Handlers
        $bot_storage_for_chat_handler = class_exists(\WPAICG\Chat\Storage\BotStorage::class) ? new \WPAICG\Chat\Storage\BotStorage() : null;
        if ($this->log_storage && $this->token_manager && $bot_storage_for_chat_handler && class_exists(\WPAICG\Core\Stream\Contexts\Chat\SSEChatStreamContextHandler::class)) {
            $this->chat_context_handler = new \WPAICG\Core\Stream\Contexts\Chat\SSEChatStreamContextHandler($bot_storage_for_chat_handler, $this->log_storage, $this->token_manager, $this->sse_vector_context_helper);
        } else {
            $this->chat_context_handler = null;
        }

        if ($this->log_storage && class_exists(\WPAICG\Core\Stream\Contexts\ContentWriter\SSEContentWriterStreamContextHandler::class)) {
            $this->content_writer_context_handler = new \WPAICG\Core\Stream\Contexts\ContentWriter\SSEContentWriterStreamContextHandler($this->log_storage);
        } else {
            $this->content_writer_context_handler = null;
        }

        $this->ai_forms_context_handler = (
            $this->log_storage && $this->ai_form_storage && $this->token_manager &&
            $this->ai_caller && $this->vector_store_manager &&
            class_exists(\WPAICG\Core\Stream\Contexts\AIForms\SSEAIFormsStreamContextHandler::class)
        ) ? new \WPAICG\Core\Stream\Contexts\AIForms\SSEAIFormsStreamContextHandler(
            $this->log_storage,
            $this->ai_form_storage,
            $this->token_manager,
            $this->ai_caller,
            $this->vector_store_manager
        )
          : null;
    }

    // Getters for externalized logic
    public function get_sse_message_cache(): ?AIPKit_SSE_Message_Cache
    {
        return $this->sse_message_cache;
    }
    public function get_chat_context_handler(): ?SSEChatStreamContextHandler
    {
        return $this->chat_context_handler;
    }
    public function get_content_writer_context_handler(): ?SSEContentWriterStreamContextHandler
    {
        return $this->content_writer_context_handler;
    }
    public function get_ai_forms_context_handler(): ?SSEAIFormsStreamContextHandler
    {
        return $this->ai_forms_context_handler;
    }


    /**
     * @return mixed[]|\WP_Error
     */
    public function process_initial_request(array $get_params)
    {
        return process_initial_request_logic($this, $get_params);
    }
}
