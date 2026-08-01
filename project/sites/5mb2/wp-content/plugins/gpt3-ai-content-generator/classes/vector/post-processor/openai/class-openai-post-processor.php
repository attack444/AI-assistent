<?php


namespace WPAICG\Vector\PostProcessor\OpenAI;

use WPAICG\Vector\PostProcessor\Base\AIPKit_Vector_Post_Processor_Base;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory; // Corrected Factory namespace
use WP_Error;

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
 * Handles indexing WordPress post content into OpenAI Vector Stores.
 */
class OpenAIPostProcessor extends AIPKit_Vector_Post_Processor_Base
{
    private $vector_store_manager;
    private $config_handler;

    public function __construct()
    {
        parent::__construct();
        $this->vector_store_manager = $this->create_vector_store_manager();
        if (!class_exists(OpenAIConfig::class)) {
            $config_path = __DIR__ . '/class-openai-config.php';
            if (file_exists($config_path)) {
                require_once $config_path;
            }
        }
        if (class_exists(OpenAIConfig::class)) {
            $this->config_handler = new OpenAIConfig();
        }
    }

    private static function get_validated_table_identifier(string $table_name): string
    {
        $table_name = trim($table_name);
        if ($table_name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table_name)) {
            return '';
        }

        return '`' . $table_name . '`';
    }

    /**
     * Indexes a single post's content to a specified OpenAI Vector Store.
     * @param int $post_id The ID of the post to index.
     * @param string $vector_store_id The ID of the target OpenAI Vector Store.
     * @param string|null $vector_store_name_for_log Optional name of the store for logging purposes.
     * @return array ['status' => 'success'|'error', 'message' => string, 'file_id' => string|null, 'batch_id' => string|null]
     */
    public function index_single_post_to_store(int $post_id, string $vector_store_id, ?string $vector_store_name_for_log = null): array
    {        
        $post_obj = get_post($post_id);
        $post_title_for_log = $post_obj ? $post_obj->post_title : 'N/A';
        
        $log_entry_base = [
            'provider' => 'OpenAI', 'vector_store_id' => $vector_store_id, 'vector_store_name' => $vector_store_name_for_log ?: $vector_store_id,
            'post_id' => $post_id, 'post_title' => $post_title_for_log,
            'source_type_for_log' => 'wordpress_post'
        ];

        $return_error = function (string $error_msg, ?string $file_id = null, ?string $batch_id = null) use ($log_entry_base): array {
            $failure_log = array_merge($log_entry_base, [
                'status' => 'failed',
                'message' => $error_msg,
            ]);
            if ($file_id !== null) {
                $failure_log['file_id'] = $file_id;
            }
            if ($batch_id !== null) {
                $failure_log['batch_id'] = $batch_id;
            }
            $this->log_event($failure_log);

            return ['status' => 'error', 'message' => $error_msg, 'file_id' => $file_id, 'batch_id' => $batch_id];
        };

        if (!$this->config_handler || !$this->vector_store_manager) {
            $error_msg = __('OpenAI processing components not available.', 'gpt3-ai-content-generator');
            return $return_error($error_msg);
        }

        $openai_config = $this->config_handler->get_config();
        if (is_wp_error($openai_config)) {
            $error_msg = $openai_config->get_error_message();
            return $return_error($error_msg);
        }

        $strategy = AIPKit_Vector_Provider_Strategy_Factory::get_strategy('OpenAI');
        if (is_wp_error($strategy) || !method_exists($strategy, 'delete_openai_file_object') || !method_exists($strategy, 'upload_file_for_vector_store')) {
            $error_msg = __('OpenAI file processing strategy not available.', 'gpt3-ai-content-generator');
            return $return_error($error_msg);
        }
        $connect_result = $strategy->connect($openai_config);
        if (is_wp_error($connect_result) || $connect_result === false) {
            $error_msg = is_wp_error($connect_result)
                ? $connect_result->get_error_message()
                : __('Failed to connect to OpenAI vector store.', 'gpt3-ai-content-generator');
            return $return_error('Connection error: ' . $error_msg);
        }

        $old_file_id_meta_key = '_aipkit_openai_file_id_for_vs_' . sanitize_key($vector_store_id);
        $old_file_ids = $this->get_existing_file_ids_for_post($post_id, $vector_store_id, $old_file_id_meta_key);

        if (!empty($old_file_ids)) {
            $delete_existing_result = $this->delete_existing_openai_files($old_file_ids, $vector_store_id, $openai_config, $strategy);
            if (is_wp_error($delete_existing_result)) {
                return $return_error('Existing file cleanup error: ' . $delete_existing_result->get_error_message());
            }
            delete_post_meta($post_id, $old_file_id_meta_key);
            $this->delete_existing_log_entries_for_post($post_id, $vector_store_id, $old_file_ids);
        }

        $content_string_or_error = $this->get_post_content_as_string($post_id);
        if (is_wp_error($content_string_or_error) || empty(trim($content_string_or_error))) {
            $error_msg = is_wp_error($content_string_or_error) ? 'Content retrieval error: ' . $content_string_or_error->get_error_message() : __('Post content is empty.', 'gpt3-ai-content-generator');
            return $return_error($error_msg);
        }
        
        $log_entry_base['indexed_content'] = $content_string_or_error;

        $temp_file_result = $this->create_temp_file_from_string($content_string_or_error, 'post-' . $post_id . '-vs-' . $vector_store_id . '-'); // Call base method
        if (is_wp_error($temp_file_result)) {
            $error_msg = 'Temp file error: ' . $temp_file_result->get_error_message();
            return $return_error($error_msg);
        }

        $upload_result = $strategy->upload_file_for_vector_store($temp_file_result, basename($temp_file_result), 'user_data'); // Call strategy method
        wp_delete_file($temp_file_result);

        if (is_wp_error($upload_result) || !isset($upload_result['id'])) {
            $err_msg = is_wp_error($upload_result) ? $upload_result->get_error_message() : 'Missing file ID in response.';
            return $return_error('Upload error: ' . $err_msg);
        }
        $uploaded_file_id = $upload_result['id'];

        $batch_result = $this->vector_store_manager->upsert_vectors('OpenAI', $vector_store_id, ['file_ids' => [$uploaded_file_id]], $openai_config);
        if (is_wp_error($batch_result)) {
            $error_msg = 'Batch add error: ' . $batch_result->get_error_message();
            return $return_error($error_msg, $uploaded_file_id);
        }
        $batch_id = $batch_result['id'] ?? null;
        $this->log_event(array_merge($log_entry_base, ['status' => 'indexed', 'message' => 'WordPress post content submitted for indexing.', 'file_id' => $uploaded_file_id, 'batch_id' => $batch_id]));

        update_post_meta($post_id, $old_file_id_meta_key, $uploaded_file_id);
        update_post_meta($post_id, '_aipkit_indexed_to_vs_' . sanitize_key($vector_store_id), '1');

        return ['status' => 'success', 'message' => 'Submitted to batch.', 'file_id' => $uploaded_file_id, 'batch_id' => $batch_id];
    }

    /**
     * Gets every known OpenAI file ID for a post/store pair.
     *
     * Meta is the fast path, while the data-source table lets incremental indexing
     * replace files that were created by older or alternate indexing paths.
     *
     * @param int $post_id WordPress post ID.
     * @param string $vector_store_id OpenAI vector store ID.
     * @param string $meta_key Post meta key that stores the current OpenAI file ID.
     * @return string[]
     */
    private function get_existing_file_ids_for_post(int $post_id, string $vector_store_id, string $meta_key): array
    {
        global $wpdb;

        $file_ids = [];
        $meta_file_id = get_post_meta($post_id, $meta_key, true);
        if (is_string($meta_file_id) && trim($meta_file_id) !== '') {
            $file_ids[] = trim($meta_file_id);
        }

        $data_source_table_identifier = self::get_validated_table_identifier($this->data_source_table_name);
        if ($data_source_table_identifier === '') {
            return array_values(array_unique($file_ids));
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is plugin-owned, validated, and backticked before interpolation for pre-WP-6.2 compatibility.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table lookup for replacing prior OpenAI source files; table identifier is plugin-owned, validated, and backticked above.
        $logged_file_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT file_id
                 FROM {$data_source_table_identifier}
                 WHERE provider = %s
                   AND vector_store_id = %s
                   AND post_id = %d
                   AND status = %s
                   AND file_id IS NOT NULL
                   AND file_id <> %s
                 ORDER BY id DESC",
                'OpenAI',
                $vector_store_id,
                $post_id,
                'indexed',
                ''
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (is_array($logged_file_ids)) {
            foreach ($logged_file_ids as $logged_file_id) {
                if (is_string($logged_file_id) && trim($logged_file_id) !== '') {
                    $file_ids[] = trim($logged_file_id);
                }
            }
        }

        return array_values(array_unique($file_ids));
    }

    /**
     * Detaches and deletes prior OpenAI files before replacement.
     *
     * @param string[] $file_ids OpenAI file IDs.
     * @param string $vector_store_id OpenAI vector store ID.
     * @param array $openai_config Provider config.
     * @param object $strategy Connected OpenAI vector provider strategy.
     * @return true|WP_Error
     */
    private function delete_existing_openai_files(array $file_ids, string $vector_store_id, array $openai_config, object $strategy)
    {
        $cleanup_errors = [];

        foreach ($file_ids as $file_id) {
            if (!is_string($file_id) || trim($file_id) === '') {
                continue;
            }

            $file_id = trim($file_id);
            $detached = false;
            $deleted = false;
            $detach_result = $this->vector_store_manager->delete_vectors('OpenAI', $vector_store_id, [$file_id], $openai_config);
            if ($detach_result === true || $this->is_not_found_error($detach_result)) {
                $detached = true;
            }

            $delete_file_result = method_exists($strategy, 'delete_openai_file_object')
                ? $strategy->delete_openai_file_object($file_id)
                : new WP_Error('openai_delete_file_unavailable', __('OpenAI file delete method is unavailable.', 'gpt3-ai-content-generator'));

            if ($delete_file_result === true || $this->is_not_found_error($delete_file_result)) {
                $deleted = true;
            }

            if (!$detached && !$deleted) {
                $error_messages = [];
                if (is_wp_error($detach_result)) {
                    $error_messages[] = $detach_result->get_error_message();
                }
                if (is_wp_error($delete_file_result)) {
                    $error_messages[] = $delete_file_result->get_error_message();
                }
                $cleanup_errors[] = $file_id . ': ' . implode(' | ', array_unique($error_messages));
            }
        }

        if (!empty($cleanup_errors)) {
            return new WP_Error(
                'openai_existing_file_cleanup_failed',
                implode('; ', $cleanup_errors)
            );
        }

        return true;
    }

    /**
     * Removes superseded local source rows so the source table reflects current files.
     *
     * @param int $post_id WordPress post ID.
     * @param string $vector_store_id OpenAI vector store ID.
     * @param string[] $file_ids Replaced OpenAI file IDs.
     */
    private function delete_existing_log_entries_for_post(int $post_id, string $vector_store_id, array $file_ids): void
    {
        global $wpdb;

        $file_ids = array_values(array_filter(array_map('trim', $file_ids)));
        if (empty($file_ids)) {
            return;
        }

        $data_source_table_identifier = self::get_validated_table_identifier($this->data_source_table_name);
        if ($data_source_table_identifier === '') {
            return;
        }

        foreach ($file_ids as $file_id) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is plugin-owned, validated, and backticked before interpolation for pre-WP-6.2 compatibility.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table cleanup for superseded OpenAI source rows; table identifier is plugin-owned, validated, and backticked above.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$data_source_table_identifier}
                 WHERE provider = %s
                   AND vector_store_id = %s
                   AND post_id = %d
                   AND status = %s
                   AND file_id = %s",
                    'OpenAI',
                    $vector_store_id,
                    $post_id,
                    'indexed',
                    $file_id
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    /**
     * Treat already-removed remote files as a successful cleanup.
     *
     * @param mixed $result Result from an OpenAI cleanup call.
     * @return bool
     */
    private function is_not_found_error($result): bool
    {
        return is_wp_error($result) && strpos($result->get_error_message(), '(404)') !== false;
    }
}
