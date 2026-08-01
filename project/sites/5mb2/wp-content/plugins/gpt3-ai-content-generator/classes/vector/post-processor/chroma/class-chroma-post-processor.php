<?php

namespace WPAICG\Vector\PostProcessor\Chroma;

use WPAICG\AIPKit_Providers;
use WPAICG\Vector\PostProcessor\Base\AIPKit_Vector_Post_Processor_Base;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(AIPKit_Vector_Post_Processor_Base::class)) {
    $aipkit_base_class_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/base/class-aipkit-vector-post-processor-base.php';
    if (file_exists($aipkit_base_class_path)) {
        require_once $aipkit_base_class_path;
    }
}

/**
 * Handles indexing WordPress post content into Chroma collections.
 */
class ChromaPostProcessor extends AIPKit_Vector_Post_Processor_Base
{
    private const EMBEDDING_BATCH_SIZE = 50;

    private $vector_store_manager;
    private $config_handler;
    private $embedding_handler;

    public function __construct()
    {
        parent::__construct();
        $this->vector_store_manager = $this->create_vector_store_manager();

        if (!class_exists(ChromaConfig::class)) {
            $config_path = __DIR__ . '/class-chroma-config.php';
            if (file_exists($config_path)) {
                require_once $config_path;
            }
        }
        if (class_exists(ChromaConfig::class)) {
            $this->config_handler = new ChromaConfig();
        }

        if (!class_exists(ChromaEmbeddingHandler::class)) {
            $embed_path = __DIR__ . '/class-chroma-embedding-handler.php';
            if (file_exists($embed_path)) {
                require_once $embed_path;
            }
        }
        if (class_exists(ChromaEmbeddingHandler::class)) {
            $this->embedding_handler = new ChromaEmbeddingHandler();
        }
    }

    /**
     * Indexes a single post's content to a specified Chroma collection.
     *
     * @param int $post_id The ID of the post to index.
     * @param string $collection_name The name of the target Chroma collection.
     * @param string $embedding_provider_key Key of the provider for embeddings.
     * @param string $embedding_model The specific embedding model to use.
     * @return array{status:string,message:string}
     */
    public function index_single_post_to_collection(int $post_id, string $collection_name, string $embedding_provider_key, string $embedding_model): array
    {
        $post_obj = get_post($post_id);
        $post_title_for_log = $post_obj ? $post_obj->post_title : 'N/A';
        $provider_lookup = sanitize_key((string) strtolower($embedding_provider_key));
        $embedding_provider_normalized = AIPKit_Providers::resolve_embedding_provider_name(
            $provider_lookup,
            'chroma_post_processor'
        );

        $base_log = [
            'provider' => 'Chroma',
            'vector_store_id' => $collection_name,
            'vector_store_name' => $collection_name,
            'post_id' => $post_id,
            'post_title' => $post_title_for_log,
            'embedding_provider' => $provider_lookup,
            'embedding_model' => $embedding_model,
            'source_type_for_log' => 'wordpress_post',
        ];

        if (!is_string($embedding_provider_normalized) || $embedding_provider_normalized === '') {
            $error_msg = __('Invalid embedding provider for Chroma indexing.', 'gpt3-ai-content-generator');
            $this->log_event(array_merge($base_log, [
                'status' => 'failed',
                'message' => $error_msg,
            ]));
            return ['status' => 'error', 'message' => $error_msg];
        }

        $chroma_record_id = 'wp_post_' . $post_id;
        $log_entry_base = array_merge($base_log, [
            'embedding_provider' => $embedding_provider_normalized,
            'file_id' => $chroma_record_id,
        ]);

        $return_error = function (string $error_msg) use ($log_entry_base): array {
            $this->log_event(array_merge($log_entry_base, [
                'status' => 'failed',
                'message' => $error_msg,
            ]));

            return ['status' => 'error', 'message' => $error_msg];
        };

        if (!$this->embedding_handler || !$this->vector_store_manager || !$this->config_handler) {
            return $return_error(__('Chroma processing components not available.', 'gpt3-ai-content-generator'));
        }

        $chroma_api_config = $this->config_handler->get_config();
        if (is_wp_error($chroma_api_config)) {
            return $return_error($chroma_api_config->get_error_message());
        }

        $content_string_or_error = $this->get_post_content_as_string($post_id);
        if (is_wp_error($content_string_or_error)) {
            return $return_error('Content retrieval error: ' . $content_string_or_error->get_error_message());
        }
        $log_entry_base['indexed_content'] = $content_string_or_error;

        if (trim($content_string_or_error) === '') {
            return $return_error(__('Post content is empty for Chroma.', 'gpt3-ai-content-generator'));
        }

        $chunks = $this->prepare_embedding_chunks(
            $content_string_or_error,
            $embedding_model,
            $provider_lookup,
            $collection_name,
            'chroma',
            'Chroma'
        );
        if (is_wp_error($chunks)) {
            return $return_error($chunks->get_error_message());
        }

        $embedding_batch_size = $this->resolve_embedding_batch_size(
            $embedding_provider_normalized,
            'Chroma',
            $embedding_model,
            $collection_name,
            $post_id,
            self::EMBEDDING_BATCH_SIZE
        );

        $records_to_upsert = [];
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
                return $return_error(__('Embedding result count did not match Chroma chunk count.', 'gpt3-ai-content-generator'));
            }

            foreach ($chunk_batch as $offset => $chunk) {
                $chunk_index = (int) ($chunk['index'] ?? 0);
                $record_id = $total_chunks === 1 ? $chroma_record_id : $chroma_record_id . '_chunk_' . $chunk_index;
                $metadata = [
                    'source' => 'wordpress_post',
                    'post_id' => (string) $post_id,
                    'title' => $post_title_for_log,
                    'type' => get_post_type($post_id),
                    'url' => get_permalink($post_id),
                    'vector_id' => $record_id,
                    'parent_vector_id' => $chroma_record_id,
                    'chunk_index' => $chunk_index,
                    'total_chunks' => $total_chunks,
                    'char_start' => (int) ($chunk['start'] ?? 0),
                    'char_end' => (int) ($chunk['end'] ?? 0),
                ];
                $records_to_upsert[] = [
                    'id' => $record_id,
                    'vector' => $embedding_vectors[$offset],
                    'payload' => $metadata,
                    'document' => (string) ($chunk['text'] ?? ''),
                ];
            }
        }

        $delete_existing_result = $this->vector_store_manager->delete_vectors(
            'Chroma',
            $collection_name,
            ['where' => ['$or' => [
                ['post_id' => (string) $post_id],
                ['post_id' => $post_id],
            ]]],
            $chroma_api_config
        );
        if (is_wp_error($delete_existing_result)) {
            return $return_error('Deleting existing Chroma chunks failed: ' . $delete_existing_result->get_error_message());
        }

        $upsert_result = $this->vector_store_manager->upsert_vectors('Chroma', $collection_name, ['points' => $records_to_upsert], $chroma_api_config);
        if (is_wp_error($upsert_result)) {
            return $return_error('Upsert to Chroma failed: ' . $upsert_result->get_error_message());
        }

        $this->log_event(array_merge($log_entry_base, [
            'status' => 'indexed',
            'message' => sprintf('WordPress post content chunked and submitted for indexing. Chunks: %d.', $total_chunks),
        ]));
        update_post_meta($post_id, '_aipkit_indexed_to_vs_' . sanitize_key($collection_name), '1');
        update_post_meta($post_id, '_aipkit_vector_id_for_vs_' . sanitize_key($collection_name), $chroma_record_id);

        return ['status' => 'success', 'message' => 'Post content indexed to Chroma.'];
    }
}
