<?php 

namespace WPAICG\Dashboard\Ajax\OpenAI;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logs an entry for OpenAI vector store related events.
 *
 * @param \wpdb $wpdb WordPress database object.
 * @param string $data_source_table_name The name of the data source log table.
 * @param array $log_data Data for the log entry.
 * @return void
 */
function _aipkit_openai_vs_files_log_vector_data_source_entry(\wpdb $wpdb, string $data_source_table_name, array $log_data): void {
    $defaults = [
        'user_id' => get_current_user_id(),
        'timestamp' => current_time('mysql', 1),
        'provider' => 'OpenAI',
        'vector_store_id' => 'unknown',
        'vector_store_name' => null,
        'post_id' => null,
        'post_title' => null,
        'status' => 'info',
        'message' => '',
        'indexed_content' => null,
        'file_id' => null,
        'batch_id' => null,
        'embedding_provider' => null,
        'embedding_model' => null,
        'source_type_for_log' => null,
    ];
    $data_to_insert = wp_parse_args($log_data, $defaults);

    $source_type = $data_to_insert['source_type_for_log'] ?? ($data_to_insert['post_id'] ? 'wordpress_post' : 'unknown');
    $should_truncate = true;
    if (in_array($source_type, ['text_entry_global_form', 'file_upload_global_form'])) {
        $should_truncate = false;
    }

    if ($should_truncate && is_string($data_to_insert['indexed_content']) && mb_strlen($data_to_insert['indexed_content']) > 1000) {
        $data_to_insert['indexed_content'] = mb_substr($data_to_insert['indexed_content'], 0, 997) . '...';
    }
    unset($data_to_insert['source_type_for_log']);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Necessary insert operation into a custom table for logging. Cache is invalidated below.
    $result = $wpdb->insert($data_source_table_name, $data_to_insert);

    // Invalidate cache for this specific file entry after insert
    if ($result && !empty($data_to_insert['file_id'])) {
        $cache_key = 'openai_log_entry_' . $data_to_insert['file_id'];
        wp_cache_delete($cache_key, 'aipkit_vector_logs');
    }
}