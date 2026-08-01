<?php


namespace WPAICG\Core\Stream\Vector;

use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor;
use WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor;
use WPAICG\AIPKit_Providers; // This class is used by the logic file

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic file
require_once __DIR__ . '/fn-build-vector-search-context.php';

/**
 * SSEVectorContextHelper
 *
 * Helper class to build context from vector store search results for SSE requests.
 * This class now delegates its core logic to the namespaced function.
 */
class SSEVectorContextHelper
{
    private $ai_caller;
    private $vector_store_manager;
    private $pinecone_post_processor; // Kept for future use if specific Pinecone logic needed here
    private $qdrant_post_processor;   // Kept for future use if specific Qdrant logic needed here

    public function __construct(
        AIPKit_AI_Caller $ai_caller,
        AIPKit_Vector_Store_Manager $vector_store_manager,
        ?PineconePostProcessor $pinecone_post_processor,
        ?QdrantPostProcessor $qdrant_post_processor
    ) {
        $this->ai_caller = $ai_caller;
        $this->vector_store_manager = $vector_store_manager;
        $this->pinecone_post_processor = $pinecone_post_processor;
        $this->qdrant_post_processor = $qdrant_post_processor;

        // Ensure AIPKit_Providers is loaded, as it's used by the logic function
        if (!class_exists(AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            }
        }
    }

    // Getters for dependencies (though not directly used by this class anymore,
    // they are kept for consistency or if methods were to be added back)
    public function get_ai_caller(): AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }
    public function get_vector_store_manager(): AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }
    public function get_pinecone_post_processor(): ?PineconePostProcessor
    {
        return $this->pinecone_post_processor;
    }
    public function get_qdrant_post_processor(): ?QdrantPostProcessor
    {
        return $this->qdrant_post_processor;
    }


    /**
     * Builds the vector search results context string by calling the externalized logic.
     *
     * @param string $user_message The user's current message.
     * @param array  $bot_settings The settings of the current bot.
     * @param string $main_provider The main AI provider being used for the chat.
     * @param string|null $frontend_active_openai_vs_id Optional active OpenAI Vector Store ID from frontend.
     * @param string|null $frontend_active_pinecone_index_name Optional active Pinecone index name from frontend.
     * @param string|null $frontend_active_pinecone_namespace Optional active Pinecone namespace from frontend.
     * @param string|null $frontend_active_qdrant_collection_name Optional active Qdrant collection name.
     * @param string|null $frontend_active_qdrant_file_upload_context_id Optional active Qdrant file upload context ID.
     * @param string|null $frontend_active_chroma_collection_name Optional active Chroma collection name.
     * @param string|null $frontend_active_chroma_file_upload_context_id Optional active Chroma file upload context ID.
     * @return string The formatted context string from vector searches, or an empty string.
     */
    public function build_vector_search_context(
        string $user_message,
        array $bot_settings,
        string $main_provider,
        ?string $frontend_active_openai_vs_id = null,
        ?string $frontend_active_pinecone_index_name = null,
        ?string $frontend_active_pinecone_namespace = null,
        ?string $frontend_active_qdrant_collection_name = null,
        ?string $frontend_active_qdrant_file_upload_context_id = null,
        ?string $frontend_active_chroma_collection_name = null,
        ?string $frontend_active_chroma_file_upload_context_id = null
    ): string {
        // Call the namespaced logic function, passing the required dependencies
        return build_vector_search_context_logic(
            $this->ai_caller,
            $this->vector_store_manager,
            // The logic function itself doesn't need these post-processors directly.
            // It uses ai_caller for embeddings and vector_store_manager for queries.
            // If pinecone/qdrant post-processors were needed *by the logic function*,
            // they would be passed here: $this->pinecone_post_processor, $this->qdrant_post_processor,
            $user_message,
            $bot_settings,
            $main_provider,
            $frontend_active_openai_vs_id,
            $frontend_active_pinecone_index_name,
            $frontend_active_pinecone_namespace,
            $frontend_active_qdrant_collection_name,
            $frontend_active_qdrant_file_upload_context_id,
            $frontend_active_chroma_collection_name,
            $frontend_active_chroma_file_upload_context_id
        );
    }
}
