<?php


namespace WPAICG\Dashboard\Ajax\Pinecone\HandlerIndexes;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Pinecone_Ajax_Handler;
use WPAICG\Vector\AIPKit_Vector_Text_Ingestion_Service;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for upserting vectors to a Pinecone index.
 * Called by AIPKit_Vector_Store_Pinecone_Ajax_Handler::ajax_upsert_to_pinecone_index().
 *
 * @param AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_upsert_to_index_logic(AIPKit_Vector_Store_Pinecone_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();

    if (!$vector_store_manager) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_pinecone_upsert', __('Vector Store Manager not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $pinecone_config = $handler_instance->_get_pinecone_config();
    if (is_wp_error($pinecone_config)) {
        $handler_instance->send_wp_error($pinecone_config);
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    $index_name = isset($post_data['index_name']) ? sanitize_text_field($post_data['index_name']) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string, decoded and validated below.
    $vectors_json = isset($post_data['vectors']) ? $post_data['vectors'] : '';
    $embedding_provider = isset($post_data['embedding_provider']) ? sanitize_key($post_data['embedding_provider']) : null;
    $embedding_model = isset($post_data['embedding_model']) ? sanitize_text_field($post_data['embedding_model']) : null;
    $original_text_content = isset($post_data['original_text_content']) ? wp_kses_post($post_data['original_text_content']) : null;
    $text_content = isset($post_data['text_content']) ? wp_kses_post($post_data['text_content']) : '';

    if (empty($index_name)) {
        $handler_instance->send_wp_error(new WP_Error('missing_index_name_pinecone', __('Pinecone index name is required.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    if ($text_content !== '') {
        if (empty($embedding_provider) || empty($embedding_model)) {
            $handler_instance->send_wp_error(new WP_Error('missing_embedding_config_pinecone_text', __('Embedding provider and model are required for Pinecone text indexing.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        $metadata = _aipkit_pinecone_decode_text_metadata($post_data);
        if (is_wp_error($metadata)) {
            $handler_instance->send_wp_error($metadata);
            return;
        }

        $service = new AIPKit_Vector_Text_Ingestion_Service($vector_store_manager, $handler_instance->get_ai_caller());
        $result = $service->ingest_text('Pinecone', $index_name, $text_content, $embedding_provider, $embedding_model, $metadata, $pinecone_config);
        $content_for_log = $original_text_content !== null ? $original_text_content : $text_content;

        if (is_wp_error($result)) {
            $handler_instance->_log_vector_data_source_entry([
                'vector_store_id' => $index_name, 'vector_store_name' => $index_name,
                'status' => 'failed', 'message' => 'Text content indexing failed: ' . $result->get_error_message(),
                'embedding_provider' => $embedding_provider, 'embedding_model' => $embedding_model,
                'indexed_content' => $content_for_log,
                'file_id' => isset($metadata['vector_id']) ? (string) $metadata['vector_id'] : null,
                'source_type_for_log' => isset($metadata['source']) ? (string) $metadata['source'] : 'text_entry_global_form',
            ]);
            $handler_instance->send_wp_error($result);
            return;
        }

        $chunk_count = (int) ($result['total_chunks'] ?? 0);
        $handler_instance->_log_vector_data_source_entry([
            'vector_store_id' => $index_name, 'vector_store_name' => $index_name,
            'status' => 'indexed', 'message' => sprintf('Text content submitted for indexing. Chunks: %d.', $chunk_count),
            'embedding_provider' => $result['embedding_provider'] ?? $embedding_provider,
            'embedding_model' => $embedding_model,
            'indexed_content' => $content_for_log,
            'file_id' => $result['parent_vector_id'] ?? null,
            'source_type_for_log' => $result['source_type'] ?? 'text_entry_global_form',
        ]);

        wp_send_json_success([
            'message' => __('Text content upserted to Pinecone successfully.', 'gpt3-ai-content-generator'),
            'result' => $result,
        ]);
        return;
    }

    if (empty($vectors_json)) {
        $handler_instance->send_wp_error(new WP_Error('missing_vectors_pinecone', __('Vectors data is required for Pinecone upsert.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }
    $vectors = json_decode($vectors_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($vectors) || empty($vectors)) {
        $handler_instance->send_wp_error(new WP_Error('invalid_vectors_json_pinecone', __('Invalid or empty vectors JSON format.', 'gpt3-ai-content-generator'), ['status' => 400]));
        return;
    }

    $result = $vector_store_manager->upsert_vectors('Pinecone', $index_name, $vectors, $pinecone_config);

    $pinecone_vector_id = $vectors[0]['id'] ?? null;
    $source_type_for_log = $vectors[0]['metadata']['source'] ?? 'unknown';
    $wp_post_id_for_log = null;
    $wp_post_title_for_log = null;
    $content_for_log = null;

    if ($source_type_for_log === 'wordpress_post' && isset($vectors[0]['metadata']['post_id'])) {
        $wp_post_id_for_log = absint($vectors[0]['metadata']['post_id']);
        $wp_post_title_for_log = get_the_title($wp_post_id_for_log) ?: 'Post ' . $wp_post_id_for_log;
        $content_for_log = $original_text_content;
    } elseif (in_array($source_type_for_log, ['text_entry_global_form', 'file_upload_global_form', 'text_entry_pinecone_direct']) && $original_text_content !== null) {
        $content_for_log = $original_text_content;
        if ($source_type_for_log === 'file_upload_global_form' && isset($vectors[0]['metadata']['filename'])) {
            $wp_post_title_for_log = sanitize_file_name($vectors[0]['metadata']['filename']);
        }
    }


    if (is_wp_error($result)) {
        $handler_instance->_log_vector_data_source_entry([
            'vector_store_id' => $index_name, 'vector_store_name' => $index_name,
            'post_id' => $wp_post_id_for_log, 'post_title' => $wp_post_title_for_log,
            'status' => 'failed', 'message' => 'Upsert failed: ' . $result->get_error_message(),
            'embedding_provider' => $embedding_provider, 'embedding_model' => $embedding_model,
            'indexed_content' => $content_for_log,
            'file_id' => $pinecone_vector_id,
            'source_type_for_log' => $source_type_for_log
        ]);
        $handler_instance->send_wp_error($result);
    } else {
        $handler_instance->_log_vector_data_source_entry([
            'vector_store_id' => $index_name, 'vector_store_name' => $index_name,
            'post_id' => $wp_post_id_for_log, 'post_title' => $wp_post_title_for_log,
            'status' => 'indexed', 'message' => 'Vectors upserted. Count: ' . ($result['upserted_count'] ?? count($vectors)),
            'embedding_provider' => $embedding_provider, 'embedding_model' => $embedding_model,
            'indexed_content' => $content_for_log,
            'file_id' => $pinecone_vector_id,
            'source_type_for_log' => $source_type_for_log
        ]);
        wp_send_json_success(['message' => __('Vectors upserted to Pinecone successfully.', 'gpt3-ai-content-generator'), 'result' => $result]);
    }
}

/**
 * @param array<string,mixed> $post_data
 * @return array<string,mixed>|WP_Error
 */
function _aipkit_pinecone_decode_text_metadata(array $post_data)
{
    $metadata = [];
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string, decoded and sanitized by the ingestion service.
    $metadata_json = isset($post_data['metadata']) ? (string) $post_data['metadata'] : '';
    if ($metadata_json !== '') {
        $decoded = json_decode($metadata_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return new WP_Error('invalid_pinecone_text_metadata', __('Invalid metadata JSON for Pinecone text indexing.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }
        $metadata = $decoded;
    }
    if (!empty($post_data['source_type'])) {
        $metadata['source'] = sanitize_key((string) $post_data['source_type']);
    }
    if (!empty($post_data['vector_id'])) {
        $metadata['vector_id'] = sanitize_text_field((string) $post_data['vector_id']);
    }
    if (!empty($post_data['source_context'])) {
        $metadata['source_context'] = sanitize_text_field((string) $post_data['source_context']);
    }

    return $metadata;
}
