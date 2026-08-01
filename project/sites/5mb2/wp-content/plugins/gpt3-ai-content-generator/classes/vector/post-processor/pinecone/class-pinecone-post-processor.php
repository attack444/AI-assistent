<?php

namespace WPAICG\Vector\PostProcessor\Pinecone;

use WPAICG\AIPKit_Providers;
use WPAICG\Vector\PostProcessor\Base\AIPKit_Vector_Post_Processor_Base;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

if (!class_exists(AIPKit_Vector_Post_Processor_Base::class)) {
    $base_class_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/base/class-aipkit-vector-post-processor-base.php';
    if (file_exists($base_class_path)) {
        require_once $base_class_path;
    }
}

/**
 * Handles indexing WordPress post content into Pinecone Vector Stores.
 */
class PineconePostProcessor extends AIPKit_Vector_Post_Processor_Base {

    private const EMBEDDING_BATCH_SIZE = 50;

    private $vector_store_manager;
    private $config_handler;
    private $embedding_handler;

    public function __construct() {
        parent::__construct();
        $this->vector_store_manager = $this->create_vector_store_manager();

        if (!class_exists(PineconeConfig::class)) {
            $config_path = __DIR__ . '/class-pinecone-config.php';
            if (file_exists($config_path)) require_once $config_path;
        }
        if (class_exists(PineconeConfig::class)) {
            $this->config_handler = new PineconeConfig();
        }

        if (!class_exists(PineconeEmbeddingHandler::class)) {
            $embed_path = __DIR__ . '/class-pinecone-embedding-handler.php';
            if (file_exists($embed_path)) require_once $embed_path;
        }
        if (class_exists(PineconeEmbeddingHandler::class)) {
            $this->embedding_handler = new PineconeEmbeddingHandler();
        }
    }

    /**
     * Indexes a single post's content to a specified Pinecone index.
     *
     * @param int $post_id The ID of the post to index.
     * @param string $index_name The name of the target Pinecone index.
     * @param string $embedding_provider_key Key of the provider for embeddings.
     * @param string $embedding_model The specific embedding model to use.
     * @return array ['status' => 'success'|'error', 'message' => string]
     */
    public function index_single_post_to_index(int $post_id, string $index_name, string $embedding_provider_key, string $embedding_model): array {
        
        $post_obj = get_post($post_id);
        $post_title_for_log = $post_obj ? $post_obj->post_title : 'N/A';
        
        $provider_lookup = sanitize_key((string) strtolower($embedding_provider_key));
        $embedding_provider_normalized = AIPKit_Providers::resolve_embedding_provider_name(
            $provider_lookup,
            'pinecone_post_processor'
        );
        $base_failure_log = [
            'provider' => 'Pinecone',
            'vector_store_id' => $index_name,
            'vector_store_name' => $index_name,
            'post_id' => $post_id,
            'post_title' => $post_title_for_log,
            'embedding_provider' => $provider_lookup,
            'embedding_model' => $embedding_model,
            'source_type_for_log' => 'wordpress_post',
        ];
        if (!is_string($embedding_provider_normalized) || $embedding_provider_normalized === '') {
            $this->log_event(array_merge($base_failure_log, [
                'status' => 'failed',
                'message' => __('Invalid embedding provider for Pinecone indexing.', 'gpt3-ai-content-generator'),
            ]));
            return [
                'status' => 'error',
                'message' => __('Invalid embedding provider for Pinecone indexing.', 'gpt3-ai-content-generator'),
            ];
        }
        $pinecone_vector_id = 'wp_post_' . $post_id;

        $log_entry_base = [
            'provider' => 'Pinecone', 'vector_store_id' => $index_name, 'vector_store_name' => $index_name,
            'post_id' => $post_id, 'post_title' => $post_title_for_log,
            'embedding_provider' => $embedding_provider_normalized, 'embedding_model' => $embedding_model,
            'file_id' => $pinecone_vector_id,
            'source_type_for_log' => 'wordpress_post'
        ];

        $return_error = function (string $error_msg) use ($log_entry_base): array {
            $this->log_event(array_merge($log_entry_base, [
                'status' => 'failed',
                'message' => $error_msg,
            ]));

            return ['status' => 'error', 'message' => $error_msg];
        };

        if (!$this->embedding_handler || !$this->vector_store_manager || !$this->config_handler) {
            $error_msg = __('Pinecone processing components not available.', 'gpt3-ai-content-generator');
            return $return_error($error_msg);
        }

        $pinecone_api_config = $this->config_handler->get_config();
        if (is_wp_error($pinecone_api_config)) {
            $error_msg = $pinecone_api_config->get_error_message();
            return $return_error($error_msg);
        }
        
        $content_string_or_error = $this->get_post_content_as_string($post_id);
        if (is_wp_error($content_string_or_error)) {
            $error_msg = 'Content retrieval error: ' . $content_string_or_error->get_error_message();
            return $return_error($error_msg);
        }
        $log_entry_base['indexed_content'] = $content_string_or_error;

        if (empty(trim($content_string_or_error))) {
            $error_msg = __('Post content is empty for Pinecone.', 'gpt3-ai-content-generator');
            return $return_error($error_msg);
        }
        
        $chunks = $this->prepare_embedding_chunks(
            $content_string_or_error,
            $embedding_model,
            $provider_lookup,
            $index_name,
            'pinecone',
            'Pinecone'
        );
        if (is_wp_error($chunks)) {
            return $return_error($chunks->get_error_message());
        }

        $embedding_batch_size = $this->resolve_embedding_batch_size(
            $embedding_provider_normalized,
            'Pinecone',
            $embedding_model,
            $index_name,
            $post_id,
            self::EMBEDDING_BATCH_SIZE
        );

        $vectors_to_upsert = [];
        $chunk_batches = array_chunk($chunks, $embedding_batch_size);
        $total_chunks = count($chunks);
        foreach ($chunk_batches as $chunk_batch) {
            $chunk_texts = array_map(static function ($chunk): string {
                return (string) ($chunk['text'] ?? '');
            }, $chunk_batch);
            $embedding_result = $this->embedding_handler->generate_embeddings($chunk_texts, $embedding_provider_normalized, $embedding_model);
            if (is_wp_error($embedding_result)) {
                return $return_error('Embedding failed: ' . $embedding_result->get_error_message());
            }

            $embedding_vectors = $embedding_result['embeddings'] ?? [];
            if (!is_array($embedding_vectors) || count($embedding_vectors) !== count($chunk_batch)) {
                return $return_error(__('Embedding result count did not match Pinecone chunk count.', 'gpt3-ai-content-generator'));
            }

            foreach ($chunk_batch as $offset => $chunk) {
                $chunk_index = (int) ($chunk['index'] ?? 0);
                $record_id = $total_chunks === 1 ? $pinecone_vector_id : $pinecone_vector_id . '_chunk_' . $chunk_index;
                $chunk_text = (string) ($chunk['text'] ?? '');
                $metadata = [
                    'source' => 'wordpress_post',
                    'post_id' => (string) $post_id,
                    'title' => $post_title_for_log,
                    'type' => get_post_type($post_id),
                    'url' => get_permalink($post_id),
                    'vector_id' => $record_id,
                    'parent_vector_id' => $pinecone_vector_id,
                    'chunk_index' => $chunk_index,
                    'total_chunks' => $total_chunks,
                    'char_start' => (int) ($chunk['start'] ?? 0),
                    'char_end' => (int) ($chunk['end'] ?? 0),
                    'original_content' => $chunk_text,
                ];
                $vectors_to_upsert[] = [
                    'id' => $record_id,
                    'values' => $embedding_vectors[$offset],
                    'metadata' => $metadata,
                ];
            }
        }

        $delete_existing_result = $this->vector_store_manager->delete_vectors(
            'Pinecone',
            $index_name,
            ['filter' => ['$or' => [
                ['post_id' => ['$eq' => (string) $post_id]],
                ['post_id' => ['$eq' => $post_id]],
            ]]],
            $pinecone_api_config
        );
        if (is_wp_error($delete_existing_result) && stripos($delete_existing_result->get_error_message(), 'Namespace not found') === false) {
            return $return_error('Deleting existing Pinecone chunks failed: ' . $delete_existing_result->get_error_message());
        }

        $upsert_result = $this->vector_store_manager->upsert_vectors('Pinecone', $index_name, ['vectors' => $vectors_to_upsert], $pinecone_api_config);
        if (is_wp_error($upsert_result)) {
            $error_msg = 'Upsert to Pinecone failed: ' . $upsert_result->get_error_message();
            return $return_error($error_msg);
        }
        
        $this->log_event(array_merge($log_entry_base, ['status' => 'indexed', 'message' => sprintf('WordPress post content chunked and submitted for indexing. Chunks: %d.', $total_chunks)]));
        update_post_meta($post_id, '_aipkit_indexed_to_vs_' . sanitize_key($index_name), '1');
        update_post_meta($post_id, '_aipkit_vector_id_for_vs_' . sanitize_key($index_name), $pinecone_vector_id);
        
        return ['status' => 'success', 'message' => 'Post content indexed to Pinecone.'];
    }
}
