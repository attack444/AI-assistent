<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Processor;

// Import all required processor logic files
use WPAICG\AutoGPT\Cron\EventProcessor\Processor\ContentIndexing;
use WPAICG\AutoGPT\Cron\EventProcessor\Processor\ContentWriting;
use WPAICG\AutoGPT\Cron\EventProcessor\Processor\CommentReply;
use WPAICG\AutoGPT\Cron\EventProcessor\Processor\ContentEnhancement;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/content-indexing/openai-post-index-processor.php';
require_once __DIR__ . '/content-indexing/pinecone-post-index-processor.php';
require_once __DIR__ . '/content-indexing/qdrant-post-index-processor.php';
require_once __DIR__ . '/content-indexing/chroma-post-index-processor.php';
require_once __DIR__ . '/content-writing/methods.php';
require_once __DIR__ . '/comment-reply/process-comment-reply-item.php';
if (file_exists(__DIR__ . '/content-enhancement/process-enhancement-item.php')) {
    require_once __DIR__ . '/content-enhancement/process-enhancement-item.php';
}


/**
 * Sub-dispatcher that routes a single queue item to the correct processor logic.
 *
 * @param array $item The queue item from the database.
 * @return array ['status' => 'success'|'error', 'message' => '...']
 */
function process_queue_item_logic(array $item): array
{
    $item_task_type = $item['task_type'];
    $item_config = json_decode($item['item_config'], true) ?: [];

    if (strncmp($item_task_type, 'content_writing', strlen('content_writing')) === 0) {
        return ContentWriting\process_content_writing_item_logic($item_config, $item);
    } elseif ($item_task_type === 'content_indexing') {
        $provider = $item_config['target_store_provider'] ?? null;
        switch ($provider) {
            case 'openai':
                return ContentIndexing\process_openai_indexing_logic($item, $item_config);
            case 'pinecone':
                return ContentIndexing\process_pinecone_indexing_logic($item, $item_config);
            case 'qdrant':
                return ContentIndexing\process_qdrant_indexing_logic($item, $item_config);
            case 'chroma':
                return ContentIndexing\process_chroma_indexing_logic($item, $item_config);
            default:
                return ['status' => 'error', 'message' => "Unsupported provider '{$provider}' for content_indexing task."];
        }
    } elseif ($item_task_type === 'community_reply_comments') {
        return CommentReply\process_comment_reply_item_logic($item, $item_config);
    } elseif ($item_task_type === 'enhance_existing_content') {
        return ContentEnhancement\process_enhancement_item_logic($item, $item_config);
    } else {
        return ['status' => 'error', 'message' => "Unsupported task type: {$item_task_type}"];
    }
}
