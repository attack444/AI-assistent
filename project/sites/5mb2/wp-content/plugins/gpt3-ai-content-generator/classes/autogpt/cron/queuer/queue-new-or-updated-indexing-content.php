<?php


namespace WPAICG\AutoGPT\Cron\Queuer;

use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

// Load helpers
require_once __DIR__ . '/helpers/build-index-item-config.php';
require_once __DIR__ . '/helpers/insert-item-into-queue.php';

function queue_new_or_updated_indexing_content_logic(int $task_id, array $task_config, ?string $last_run_time): void
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';

    $args = [
        'post_type' => $task_config['post_types'] ?? ['post'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'modified',
        'order' => 'DESC',
    ];
    if (isset($task_config['only_new_updated_flag']) && $task_config['only_new_updated_flag'] === '1' && $last_run_time) {
        $args['date_query'] = [['column' => 'post_modified_gmt', 'after' => $last_run_time, 'inclusive' => false]];
    }
    $query = new WP_Query($args);
    $queued_count = 0;

    if ($query->have_posts()) {
        $item_config = Helpers\build_index_item_config_logic($task_config);
        foreach ($query->posts as $post_id) {
            $inserted = Helpers\insert_item_into_queue_logic($wpdb, $queue_table_name, $task_id, $post_id, 'content_indexing', $item_config);
            if ($inserted) {
                $queued_count++;
            }
        }
    }
}
