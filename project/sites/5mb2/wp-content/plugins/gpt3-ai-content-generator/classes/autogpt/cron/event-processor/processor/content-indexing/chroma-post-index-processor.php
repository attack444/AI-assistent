<?php

namespace WPAICG\AutoGPT\Cron\EventProcessor\Processor\ContentIndexing;

use WPAICG\Vector\PostProcessor\Chroma\ChromaPostProcessor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processes a single Chroma content indexing queue item.
 *
 * @param array $item The queue item from the database.
 * @param array $item_config The decoded item_config from the queue item.
 * @return array ['status' => 'success'|'error', 'message' => '...']
 */
function process_chroma_indexing_logic(array $item, array $item_config): array
{
    if (!class_exists(ChromaPostProcessor::class)) {
        $processor_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/chroma/class-chroma-post-processor.php';
        if (file_exists($processor_path)) {
            require_once $processor_path;
        }
    }

    if (!class_exists(ChromaPostProcessor::class)) {
        return ['status' => 'error', 'message' => 'Chroma Vector Post Processor class not found.'];
    }

    $processor = new ChromaPostProcessor();
    $post_id_to_index = absint($item['target_identifier']);
    $target_store_id = $item_config['target_store_id'] ?? null;
    $embedding_provider = $item_config['embedding_provider'] ?? null;
    $embedding_model = $item_config['embedding_model'] ?? null;

    if (empty($target_store_id) || empty($embedding_provider) || empty($embedding_model)) {
        return ['status' => 'error', 'message' => 'Missing configuration for Chroma indexing task (collection, embedding provider, or model).'];
    }

    return $processor->index_single_post_to_collection($post_id_to_index, $target_store_id, $embedding_provider, $embedding_model);
}
