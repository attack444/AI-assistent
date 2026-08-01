<?php

namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Text_Ingestion_Service;
use WPAICG\AIPKit_Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST API requests for interacting with Vector Stores (upserting data).
 */
class AIPKit_REST_Vector_Store_Handler extends AIPKit_REST_Base_Handler
{
    private $ai_caller;
    private $vector_store_manager;

    public function __construct()
    {
        if (class_exists(AIPKit_AI_Caller::class)) {
            $this->ai_caller = new AIPKit_AI_Caller();
        }
        if (class_exists(AIPKit_Vector_Store_Manager::class)) {
            $this->vector_store_manager = new AIPKit_Vector_Store_Manager();
        }
    }

    public function get_endpoint_args(): array
    {
        $embedding_provider_keys = AIPKit_Providers::get_embedding_provider_keys('rest_vector_store_endpoint_args');

        return array(
            'provider' => array(
                'description' => __('The vector database provider.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['pinecone', 'qdrant', 'chroma'],
                'required'    => true,
            ),
            'target_id' => array(
                'description' => __('The name of the target index (for Pinecone) or collection (for Qdrant/Chroma).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => true,
            ),
            'vectors' => array(
                'description' => __('An array of objects to be embedded and upserted.', 'gpt3-ai-content-generator'),
                'type'        => 'array',
                'required'    => true,
                'items'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'       => array('type' => 'string', 'description' => 'A unique ID for the vector. If omitted, one will be generated.', 'required' => false),
                        'content'  => array('type' => 'string', 'description' => 'The text content to be embedded.', 'required' => true),
                        'metadata' => array('type' => 'object', 'description' => 'Key-value metadata to store with the vector.', 'required' => false),
                        'uri'      => array('type' => 'string', 'description' => 'Optional URI associated with the vector.', 'required' => false),
                    ),
                ),
            ),
            'embedding_provider' => array(
                'description' => __('The AI provider to use for generating embeddings.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => $embedding_provider_keys,
                'required'    => true,
            ),
            'embedding_model' => array(
                'description' => __('The specific model ID to use for generating embeddings.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => true,
            ),
            'namespace' => array(
                 'description' => __('(Pinecone only) The namespace to upsert vectors into.', 'gpt3-ai-content-generator'),
                 'type'        => 'string',
                 'required'    => false,
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    public function get_item_schema(): array
    {
         return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'aipkit_vector_upsert_response',
            'type'       => 'object',
            'properties' => array(
                'upserted_count' => array(
                    'description' => esc_html__('The number of vectors successfully processed and sent for upserting.', 'gpt3-ai-content-generator'),
                    'type'        => 'integer',
                    'readonly'    => true,
                ),
                'status' => array(
                    'description' => esc_html__('The final status from the vector database provider.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request(WP_REST_Request $request)
    {
        if (!$this->ai_caller || !$this->vector_store_manager) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error: Vector components not loaded.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $params = $request->get_params();
        $provider_key = sanitize_key((string) ($params['provider'] ?? ''));
        $provider_map = [
            'pinecone' => 'Pinecone',
            'qdrant'  => 'Qdrant',
            'chroma'  => 'Chroma',
        ];
        if (!isset($provider_map[$provider_key])) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_vector_provider', __('Invalid vector database provider.', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        $provider_normalized = $provider_map[$provider_key];
        $target_id = $params['target_id'];
        $vectors_data = $params['vectors'];
        $embedding_provider_key = $params['embedding_provider'];
        $embedding_model = $params['embedding_model'];
        $namespace = $params['namespace'] ?? null;

        if (empty($vectors_data) || !is_array($vectors_data)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_vectors', __('The "vectors" parameter must be a non-empty array.', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        
        $provider_config = AIPKit_Providers::get_provider_data($provider_normalized);
        $ingestion_service = new AIPKit_Vector_Text_Ingestion_Service($this->vector_store_manager, $this->ai_caller);
        $results = [];
        $total_upserted = 0;
        $total_chunks = 0;
        $options = [];
        if ($provider_key === 'pinecone' && is_scalar($namespace) && (string) $namespace !== '') {
            $options['namespace'] = (string) $namespace;
        }

        foreach ($vectors_data as $item) {
            if (!is_array($item) || !isset($item['content']) || !is_scalar($item['content']) || trim((string) $item['content']) === '') {
                return $this->send_wp_error_response(new WP_Error('rest_aipkit_no_content', __('Each object in the "vectors" array must have a non-empty "content" key.', 'gpt3-ai-content-generator'), ['status' => 400]));
            }

            $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
            if (isset($item['id']) && is_scalar($item['id']) && (string) $item['id'] !== '') {
                $metadata['vector_id'] = (string) $item['id'];
            }
            if (isset($item['uri']) && is_scalar($item['uri']) && (string) $item['uri'] !== '') {
                $metadata['uri'] = (string) $item['uri'];
            }

            $result = $ingestion_service->ingest_text(
                $provider_normalized,
                (string) $target_id,
                (string) $item['content'],
                (string) $embedding_provider_key,
                (string) $embedding_model,
                $metadata,
                $provider_config,
                $options
            );

            if (is_wp_error($result)) {
                return $this->send_wp_error_response($result);
            }

            $total_upserted += (int) ($result['upserted_count'] ?? 0);
            $total_chunks += (int) ($result['total_chunks'] ?? 0);
            $results[] = [
                'parent_vector_id' => $result['parent_vector_id'] ?? null,
                'upserted_count' => (int) ($result['upserted_count'] ?? 0),
                'total_chunks' => (int) ($result['total_chunks'] ?? 0),
            ];
        }

        $response_data = [
            'upserted_count' => $total_upserted,
            'status' => 'success',
            'source_count' => count($vectors_data),
            'total_chunks' => $total_chunks,
            'results' => $results,
        ];

        return new WP_REST_Response($response_data, 200);
    }
}
