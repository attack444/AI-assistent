<?php


namespace WPAICG\AutoGPT\Cron\Queuer;

use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

// Load helpers
require_once __DIR__ . '/helpers/build-index-item-config.php';
require_once __DIR__ . '/helpers/insert-item-into-queue.php';
require_once __DIR__ . '/helpers/update-task-flag.php';

/**
 * Queues a batch of initial content for indexing based on task configuration.
 * Uses a transient to track pagination and process across multiple cron runs.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @param bool $force_all If true, restarts the queuing process from the beginning.
 * @return void
 */
function maybe_queue_initial_indexing_content_logic(int $task_id, array $task_config, bool $force_all = false): void
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    $transient_key = 'aipkit_initial_queue_page_' . $task_id;
    $completed_transient_key = 'aipkit_initial_queue_done_' . $task_id;

    // If forcing a re-queue, delete completion marker and reset page to 1
    if ($force_all) {
        delete_transient($completed_transient_key);
        delete_transient($transient_key); // Clear any in-progress page tracking
    }

    // Check if initial indexing is requested or if it's already completed.
    $is_already_completed = get_transient($completed_transient_key);
    $initial_indexing_flag_set = isset($task_config['index_existing_now_flag']) && $task_config['index_existing_now_flag'] === '1';

    // If not forcing a re-queue, and the job is either completed or not flagged to run, exit.
    if (!$force_all && ($is_already_completed || !$initial_indexing_flag_set)) {
        return;
    }

    $specific_post_ids = isset($task_config['specific_post_ids']) && is_array($task_config['specific_post_ids'])
        ? array_values(array_filter(array_map('absint', $task_config['specific_post_ids'])))
        : [];
    if (!empty($specific_post_ids)) {
        $args = [
            'post_type'      => !empty($task_config['post_types']) ? $task_config['post_types'] : 'any',
            'post_status'    => 'publish',
            'post__in'       => $specific_post_ids,
            'posts_per_page' => count($specific_post_ids),
            'fields'         => 'ids',
            'orderby'        => 'post__in',
        ];

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            $item_config = Helpers\build_index_item_config_logic($task_config);
            foreach ($query->posts as $post_id) {
                Helpers\insert_item_into_queue_logic($wpdb, $queue_table_name, $task_id, $post_id, 'content_indexing', $item_config);
            }
        }

        delete_transient($transient_key);
        set_transient($completed_transient_key, 'yes', MONTH_IN_SECONDS);
        if (!$force_all) {
            Helpers\update_task_flag_logic($wpdb, $tasks_table_name, $task_id, $task_config);
        }
        return;
    }

    $page_to_process = get_transient($transient_key);
    if ($page_to_process === false) {
        $page_to_process = 1; // Start from page 1 if transient not set
    }

    $batch_size = 200; // Process 200 posts per batch
    $args = [
        'post_type'      => $task_config['post_types'] ?? ['post'],
        'post_status'    => 'publish',
        'posts_per_page' => $batch_size,
        'paged'          => $page_to_process,
        'fields'         => 'ids',
        'orderby'        => 'ID', // Consistent ordering is important for pagination
        'order'          => 'ASC',
    ];

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

    if ($page_to_process < $query->max_num_pages) {
        // There are more pages, set transient for the next page.
        // The cron that calls this function will run again to process the next page.
        set_transient($transient_key, $page_to_process + 1, DAY_IN_SECONDS * 7); // Long expiration
    } else {
        // This was the last page, or no posts were found on page 1.
        delete_transient($transient_key);
        set_transient($completed_transient_key, 'yes', MONTH_IN_SECONDS); // Mark as done for a month
        // Only update the DB flag if it was a one-time operation (not forced by "Run Now")
        if (!$force_all) {
            Helpers\update_task_flag_logic($wpdb, $tasks_table_name, $task_id, $task_config);
        }
    }
}
