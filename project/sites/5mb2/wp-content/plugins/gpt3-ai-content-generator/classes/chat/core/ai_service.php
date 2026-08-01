<?php


namespace WPAICG\Chat\Core; // *** Correct namespace ***

use WP_Error;

// Dependencies used by logic files will be required within those files or by the class constructor.
// No need for direct use statements for AIPKit_Providers, AIPKIT_AI_Settings, etc., here
// if the logic files handle their own dependencies or they are passed to them.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic files
require_once __DIR__ . '/ai-service/constructor.php';
require_once __DIR__ . '/ai-service/generate_response.php';
// determine_provider_model.php is loaded by generate_response.php

/**
 * AIService (Chat Module Specific) - Modularized
 * Prepares chat-specific context (history, instructions) and uses the generic
 * AIPKit_AI_Caller service to interact with AI services for non-streaming responses.
 */
class AIService
{
    private $ai_caller;
    private $log_storage;
    private $vector_store_manager;
    private ?\WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor $pinecone_post_processor;
    private ?\WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor $qdrant_post_processor;


    public function __construct()
    {
        AIService\constructor($this);
    }

    // --- Setters for constructor to set properties ---
    public function set_ai_caller(?\WPAICG\Core\AIPKit_AI_Caller $caller): void
    {
        $this->ai_caller = $caller;
    }
    public function set_log_storage(?\WPAICG\Chat\Storage\LogStorage $storage): void
    {
        $this->log_storage = $storage;
    }
    public function set_vector_store_manager(?\WPAICG\Vector\AIPKit_Vector_Store_Manager $manager): void
    {
        $this->vector_store_manager = $manager;
    }
    public function set_pinecone_post_processor(?\WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor $processor): void
    {
        $this->pinecone_post_processor = $processor;
    }
    public function set_qdrant_post_processor(?\WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor $processor): void
    {
        $this->qdrant_post_processor = $processor;
    }
    // --- End Setters ---

    // --- Getters for logic files to access properties ---
    public function get_ai_caller(): ?\WPAICG\Core\AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }
    public function get_vector_store_manager(): ?\WPAICG\Vector\AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }
    public function get_log_storage(): ?\WPAICG\Chat\Storage\LogStorage
    {
        return $this->log_storage;
    }
    public function get_pinecone_post_processor(): ?\WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor
    {
        return $this->pinecone_post_processor;
    }
    public function get_qdrant_post_processor(): ?\WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor
    {
        return $this->qdrant_post_processor;
    }
    // --- End Getters ---
    /**
     * MODIFIED: Updated signature to include Pinecone & Qdrant parameters.
     * @return mixed[]|\WP_Error
     */
    public function generate_response(
        string $user_message,
        array $bot_settings,
        array $history,
        int $post_id = 0,
        ?string $frontend_previous_openai_response_id = null,
        bool $frontend_openai_web_search_active = false,
        bool $frontend_google_search_grounding_active = false,
        ?array $image_inputs_for_service = null,
        ?string $frontend_active_openai_vs_id = null,
        ?string $frontend_active_pinecone_index_name = null,
        ?string $frontend_active_pinecone_namespace = null,
        ?string $frontend_active_qdrant_collection_name = null,
        ?string $frontend_active_qdrant_file_upload_context_id = null,
        ?string $frontend_active_chroma_collection_name = null,
        ?string $frontend_active_chroma_file_upload_context_id = null,
        ?string $frontend_active_claude_file_id = null
    ) {
        return AIService\generate_response(
            $this,
            $user_message,
            $bot_settings,
            $history,
            $post_id,
            $frontend_previous_openai_response_id,
            $frontend_openai_web_search_active,
            $frontend_google_search_grounding_active,
            $image_inputs_for_service,
            $frontend_active_openai_vs_id,
            $frontend_active_pinecone_index_name,
            $frontend_active_pinecone_namespace,
            $frontend_active_qdrant_collection_name,
            $frontend_active_qdrant_file_upload_context_id,
            $frontend_active_chroma_collection_name,
            $frontend_active_chroma_file_upload_context_id,
            $frontend_active_claude_file_id
        );
    }

    // _determine_provider_model is now a namespaced function used by generate_response
}
