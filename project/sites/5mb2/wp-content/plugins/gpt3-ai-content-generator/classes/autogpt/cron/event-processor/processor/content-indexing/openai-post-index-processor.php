<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Processor\ContentIndexing;

use WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processes a single OpenAI content indexing queue item.
 *
 * @param array $item The queue item from the database.
 * @param array $item_config The decoded item_config from the queue item.
 * @return array ['status' => 'success'|'error', 'message' => '...']
 */
function process_openai_indexing_logic(array $item, array $item_config): array
{
    if (!class_exists(OpenAIPostProcessor::class)) {
        return ['status' => 'error', 'message' => "OpenAI Vector Post Processor class not found."];
    }
    $processor = new OpenAIPostProcessor();
    $post_id_to_index = absint($item['target_identifier']);
    $target_store_id = $item_config['target_store_id'] ?? null;

    if (empty($target_store_id)) {
        return ['status' => 'error', 'message' => "Target Store ID is missing for OpenAI indexing task."];
    }

    return $processor->index_single_post_to_store($post_id_to_index, $target_store_id, null);
}
