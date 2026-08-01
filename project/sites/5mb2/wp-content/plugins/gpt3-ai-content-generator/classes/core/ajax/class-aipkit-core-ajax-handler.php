<?php


namespace WPAICG\Core\Ajax;

use WPAICG\Dashboard\Ajax\BaseDashboardAjaxHandler;
use WPAICG\Core\AIPKit_AI_Caller; // For AI Caller
use WPAICG\AIPKit_Providers;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;
use WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor;
use WPAICG\Vector\PostProcessor\Pinecone\PineconePostProcessor;
use WPAICG\Vector\PostProcessor\Qdrant\QdrantPostProcessor;
use WPAICG\Vector\PostProcessor\Chroma\ChromaPostProcessor;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Chat\Storage\LogCronManager;
use WPAICG\Chat\Utils\LogConfig;
use WPAICG\Stats\AIPKit_Stats;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\Moderation\AIPKit_Global_Security_Settings;
use WPAICG\AIPKit_Role_Manager;
use WP_Error; // For WP_Error usage

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles core AJAX actions not specific to a particular module.
 */
class AIPKit_Core_Ajax_Handler extends BaseDashboardAjaxHandler
{
    private $ai_caller;
    private $vector_store_manager;
    private $openai_post_processor;
    private $pinecone_post_processor;
    private $qdrant_post_processor;
    private $chroma_post_processor;

    public function __construct()
    {
        if (class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $this->ai_caller = new AIPKit_AI_Caller();
        }
        if (class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) {
            $this->vector_store_manager = new AIPKit_Vector_Store_Manager();
        }
        if (class_exists(OpenAIPostProcessor::class)) {
            $this->openai_post_processor = new OpenAIPostProcessor();
        }
        if (class_exists(PineconePostProcessor::class)) {
            $this->pinecone_post_processor = new PineconePostProcessor();
        }
        if (class_exists(QdrantPostProcessor::class)) {
            $this->qdrant_post_processor = new QdrantPostProcessor();
        }
        if (class_exists(ChromaPostProcessor::class)) {
            $this->chroma_post_processor = new ChromaPostProcessor();
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
     * Builds provider-specific selectors that remove every chunk for a WordPress post.
     */
    private static function build_post_chunk_delete_selector(string $provider, int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        if ($provider === 'Pinecone') {
            return ['filter' => ['$or' => [
                ['post_id' => ['$eq' => (string) $post_id]],
                ['post_id' => ['$eq' => $post_id]],
            ]]];
        }

        if ($provider === 'Qdrant') {
            return ['filter' => ['should' => [
                ['key' => 'post_id', 'match' => ['value' => (string) $post_id]],
            ]]];
        }

        if ($provider === 'Chroma') {
            return ['where' => ['$or' => [
                ['post_id' => (string) $post_id],
                ['post_id' => $post_id],
            ]]];
        }

        return null;
    }

    /**
     * Builds selectors that remove every chunk belonging to a text/source parent id.
     */
    private static function build_parent_chunk_delete_selector(string $provider, string $parent_vector_id): ?array
    {
        if ($parent_vector_id === '') {
            return null;
        }

        if ($provider === 'Pinecone') {
            return ['filter' => ['$or' => [
                ['parent_vector_id' => ['$eq' => $parent_vector_id]],
                ['vector_id' => ['$eq' => $parent_vector_id]],
            ]]];
        }

        if ($provider === 'Qdrant') {
            return ['filter' => ['should' => [
                ['key' => 'parent_vector_id', 'match' => ['value' => $parent_vector_id]],
                ['key' => 'vector_id', 'match' => ['value' => $parent_vector_id]],
            ]]];
        }

        if ($provider === 'Chroma') {
            return ['where' => ['$or' => [
                ['parent_vector_id' => $parent_vector_id],
                ['vector_id' => $parent_vector_id],
            ]]];
        }

        return null;
    }

    /**
     * AJAX handler to delete a single vector data source entry from both the vector DB and local log.
     * @since NEXT_VERSION
     */
    public function ajax_delete_vector_data_source_entry()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked above.
        $post_data = wp_unslash($_POST);

        $provider_raw = isset($post_data['provider']) ? sanitize_text_field($post_data['provider']) : '';
        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);
        $allowed_providers = ['OpenAI', 'Pinecone', 'Qdrant', 'Chroma'];
        if (!in_array($provider, $allowed_providers, true)) {
            $this->send_wp_error(new WP_Error('invalid_provider_delete_vector', __('Invalid or unsupported provider for this action.', 'gpt3-ai-content-generator')));
            return;
        }
        
        $store_id   = isset($post_data['store_id']) ? sanitize_text_field($post_data['store_id']) : '';
        $vector_id  = isset($post_data['vector_id']) ? sanitize_text_field($post_data['vector_id']) : '';
        $log_entry_id = isset($post_data['log_id']) ? absint($post_data['log_id']) : 0;

        if (empty($provider) || empty($store_id) || empty($log_entry_id)) {
            $this->send_wp_error(new WP_Error('missing_params_delete_vector', __('Missing required parameters for vector deletion.', 'gpt3-ai-content-generator')));
            return;
        }

        global $wpdb;
        $data_source_table_name = $wpdb->prefix . 'aipkit_vector_data_source';
        $data_source_table_identifier = self::get_validated_table_identifier($data_source_table_name);
        if ($data_source_table_identifier === '') {
            $this->send_wp_error(new WP_Error('invalid_vector_table_identifier', __('Invalid vector data source table.', 'gpt3-ai-content-generator')));
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table lookup for admin delete action; table identifier is plugin-owned, validated, and backticked above.
        $log_entry = $wpdb->get_row($wpdb->prepare("SELECT id, provider, vector_store_id, file_id, post_id, status FROM {$data_source_table_identifier} WHERE id = %d LIMIT 1", $log_entry_id), ARRAY_A);

        if (!$log_entry) {
            wp_send_json_success(['message' => __('Log entry was not found, it might have been already deleted.', 'gpt3-ai-content-generator')]);
            return;
        }

        if ((string) ($log_entry['provider'] ?? '') !== $provider || (string) ($log_entry['vector_store_id'] ?? '') !== $store_id) {
            $this->send_wp_error(new WP_Error('mismatched_delete_vector_log', __('Source details do not match the selected log entry.', 'gpt3-ai-content-generator')));
            return;
        }

        if ($vector_id === '' && !empty($log_entry['file_id'])) {
            $vector_id = (string) $log_entry['file_id'];
        }

        $post_chunk_delete_selector = self::build_post_chunk_delete_selector($provider, absint($log_entry['post_id'] ?? 0));
        $can_delete_post_chunks = $post_chunk_delete_selector !== null;
        $is_failed_log_only = $vector_id === '' && strtolower((string) ($log_entry['status'] ?? '')) === 'failed';
        if ($vector_id === '' && !$is_failed_log_only && !$can_delete_post_chunks) {
            $this->send_wp_error(new WP_Error('missing_vector_id_delete_vector', __('Missing vector ID for vector deletion.', 'gpt3-ai-content-generator')));
            return;
        }

        if ($vector_id !== '' || $can_delete_post_chunks) {
            // Ensure Vector Store Manager is loaded
            if (!class_exists('\\WPAICG\\Vector\\AIPKit_Vector_Store_Manager')) {
                $this->send_wp_error(new WP_Error('vsm_missing_delete_vector', __('Vector management component is not available.', 'gpt3-ai-content-generator')));
                return;
            }
            $vector_store_manager = new \WPAICG\Vector\AIPKit_Vector_Store_Manager();

            // Get provider config
            $provider_config = \WPAICG\AIPKit_Providers::get_provider_data($provider);
            if ($provider === 'Chroma') {
                if (empty($provider_config['url'])) {
                    $this->send_wp_error(new WP_Error('missing_chroma_url_delete_vector', __('Chroma URL is missing.', 'gpt3-ai-content-generator')));
                    return;
                }
            } elseif (empty($provider_config['api_key'])) {
                /* translators: %s: Provider name. */
                $this->send_wp_error(new WP_Error('missing_api_key_delete_vector', sprintf(__('API key for %s is missing.', 'gpt3-ai-content-generator'), $provider)));
                return;
            }

            // 1. Delete from external vector store
            $delete_selector = $post_chunk_delete_selector ?: (self::build_parent_chunk_delete_selector($provider, $vector_id) ?: [$vector_id]);
            $delete_result = $vector_store_manager->delete_vectors($provider, $store_id, $delete_selector, $provider_config);

            // We proceed even if the external deletion fails, as the vector might not exist there anymore but the log does.
            // We will log the error if one occurs.
            if (is_wp_error($delete_result)) {
                // This is not a fatal error for the process, so we just log it and continue to delete from local DB.
            }
        }

        // 2. Delete from local database log
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete for admin action.
        $deleted_rows = $wpdb->delete(
            $data_source_table_name,
            ['id' => $log_entry_id],
            ['%d']
        );

        if ($deleted_rows === false) {
             $this->send_wp_error(new WP_Error('db_delete_failed_vector_log', __('Failed to delete the log entry from the local database.', 'gpt3-ai-content-generator')));
             return;
        }
        if ($deleted_rows === 0) {
            // This could mean it was already deleted, which is a success state for the user.
            wp_send_json_success(['message' => __('Log entry was not found, it might have been already deleted.', 'gpt3-ai-content-generator')]);
            return;
        }

        wp_send_json_success(['message' => __('Vector record and log entry deleted successfully.', 'gpt3-ai-content-generator')]);
    }

    /**
     * AJAX handler to re-index a single vector data source entry from a WordPress post.
     * @since 2.4.2
     */
    public function ajax_reindex_vector_data_source_entry() {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // Sanitize all inputs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_module_access_permissions() above.
        $post_data = wp_unslash($_POST);
        $provider_raw = isset($post_data['provider']) ? sanitize_text_field($post_data['provider']) : '';
        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);
        $store_id = isset($post_data['store_id']) ? sanitize_text_field($post_data['store_id']) : '';
        $vector_id = isset($post_data['vector_id']) ? sanitize_text_field($post_data['vector_id']) : '';
        $log_id = isset($post_data['log_id']) ? absint($post_data['log_id']) : 0;
        $post_id = isset($post_data['post_id']) ? absint($post_data['post_id']) : 0;
        $embedding_provider = isset($post_data['embedding_provider']) ? sanitize_key($post_data['embedding_provider']) : '';
        $embedding_model = isset($post_data['embedding_model']) ? sanitize_text_field($post_data['embedding_model']) : '';

        // Validate required parameters
        if (empty($provider) || empty($store_id) || empty($vector_id) || empty($log_id) || empty($post_id)) {
            $this->send_wp_error(new WP_Error('missing_params_reindex', __('Missing required parameters for re-indexing.', 'gpt3-ai-content-generator')));
            return;
        }

        // Validate dependencies
        if (!$this->vector_store_manager || !$this->openai_post_processor || !$this->pinecone_post_processor || !$this->qdrant_post_processor || !$this->chroma_post_processor) {
            $this->send_wp_error(new WP_Error('vsm_missing_reindex', __('Vector processing components are not available.', 'gpt3-ai-content-generator')));
            return;
        }

        // Step 1: Delete the existing vector and log entry
        $provider_config = AIPKit_Providers::get_provider_data($provider);
        if ($provider === 'Chroma') {
            if (empty($provider_config['url'])) {
                $this->send_wp_error(new WP_Error('missing_chroma_url_reindex', __('Chroma URL is missing.', 'gpt3-ai-content-generator')));
                return;
            }
        } elseif (empty($provider_config['api_key'])) {
            /* translators: %s: Provider name. */
             $this->send_wp_error(new WP_Error('missing_api_key_reindex', sprintf(__('API key for %s is missing.', 'gpt3-ai-content-generator'), $provider)));
             return;
        }
        $delete_selector = self::build_post_chunk_delete_selector($provider, $post_id) ?: [$vector_id];
        $delete_result = $this->vector_store_manager->delete_vectors($provider, $store_id, $delete_selector, $provider_config);
        if (is_wp_error($delete_result)) {
            // Log this but don't fail, as the vector might already be gone from the remote.
        }

        global $wpdb;
        $data_source_table_name = $wpdb->prefix . 'aipkit_vector_data_source';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete for admin action.
        $wpdb->delete($data_source_table_name, ['id' => $log_id], ['%d']);

        // Step 2: Re-index the post
        $reindex_result = null;
        switch ($provider) {
            case 'OpenAI':
                $reindex_result = $this->openai_post_processor->index_single_post_to_store($post_id, $store_id);
                break;
            case 'Pinecone':
                if (empty($embedding_provider) || empty($embedding_model)) {
                    $this->send_wp_error(new WP_Error('missing_embedding_config_reindex', __('Embedding provider and model are required for Pinecone re-indexing.', 'gpt3-ai-content-generator')));
                    return;
                }
                $reindex_result = $this->pinecone_post_processor->index_single_post_to_index($post_id, $store_id, $embedding_provider, $embedding_model);
                break;
            case 'Qdrant':
                if (empty($embedding_provider) || empty($embedding_model)) {
                    $this->send_wp_error(new WP_Error('missing_embedding_config_reindex', __('Embedding provider and model are required for Qdrant re-indexing.', 'gpt3-ai-content-generator')));
                    return;
                }
                $reindex_result = $this->qdrant_post_processor->index_single_post_to_collection($post_id, $store_id, $embedding_provider, $embedding_model);
                break;
            case 'Chroma':
                if (empty($embedding_provider) || empty($embedding_model)) {
                    $this->send_wp_error(new WP_Error('missing_embedding_config_reindex', __('Embedding provider and model are required for Chroma re-indexing.', 'gpt3-ai-content-generator')));
                    return;
                }
                $reindex_result = $this->chroma_post_processor->index_single_post_to_collection($post_id, $store_id, $embedding_provider, $embedding_model);
                break;
            default:
                $this->send_wp_error(new WP_Error('invalid_provider_reindex', __('Invalid provider for re-indexing.', 'gpt3-ai-content-generator')));
                return;
        }

        if (isset($reindex_result['status']) && $reindex_result['status'] === 'success') {
            wp_send_json_success(['message' => __('Content successfully re-indexed.', 'gpt3-ai-content-generator')]);
        } else {
            $error_message = $reindex_result['message'] ?? __('An unknown error occurred during re-indexing.', 'gpt3-ai-content-generator');
            $this->send_wp_error(new WP_Error('reindex_failed', 'Re-indexing failed: ' . $error_message));
        }
    }

    /**
     * AJAX handler to fetch global vector sources for the Sources module.
     * @since NEXT_VERSION
     */
    public function ajax_get_global_vector_sources()
    {
        $permission_check = $this->check_module_access_permissions('sources', 'aipkit_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked above.
        $post_data = wp_unslash($_POST);

        $provider_map = $this->get_vector_source_provider_map();
        $filters = $this->get_vector_sources_filters($post_data, $provider_map);
        [$where_sql, $params] = $this->build_vector_sources_where($filters);

        global $wpdb;
        $table_name = $wpdb->prefix . 'aipkit_vector_data_source';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name and assembled WHERE clause are internal and scalar values are prepared below.
        $total_logs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}", ...$params));

        $logs_params = array_merge($params, [$filters['per_page'], $filters['offset']]);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name and assembled WHERE clause are internal and scalar values are prepared below.
        $logs = $wpdb->get_results($wpdb->prepare("SELECT id, timestamp, provider, status, message, indexed_content, post_id, post_title, file_id, batch_id, embedding_provider, embedding_model, vector_store_id, vector_store_name FROM {$table_name} WHERE {$where_sql} ORDER BY timestamp DESC LIMIT %d OFFSET %d", ...$logs_params), ARRAY_A);
        if (is_array($logs)) {
            $openai_store_names = [];
            if (class_exists(AIPKit_Vector_Store_Registry::class)) {
                foreach (AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('OpenAI') as $store) {
                    if (is_object($store)) {
                        $store = (array) $store;
                    }
                    if (!is_array($store)) {
                        continue;
                    }
                    $store_id = isset($store['id']) ? (string) $store['id'] : '';
                    $store_name = isset($store['name']) ? (string) $store['name'] : '';
                    if ($store_id !== '' && $store_name !== '') {
                        $openai_store_names[$store_id] = $store_name;
                    }
                }
            }

            foreach ($logs as &$log) {
                $log['is_user_upload'] = $this->is_vector_source_user_upload($log);

                $provider = strtolower((string) ($log['provider'] ?? ''));
                $store_id = (string) ($log['vector_store_id'] ?? '');
                $stored_name = (string) ($log['vector_store_name'] ?? '');
                if (
                    $provider === 'openai'
                    && $store_id !== ''
                    && ($stored_name === '' || $stored_name === $store_id)
                    && isset($openai_store_names[$store_id])
                ) {
                    $log['vector_store_name'] = $openai_store_names[$store_id];
                }
            }
            unset($log);
        }

        $total_pages = $filters['per_page'] > 0 ? (int) ceil($total_logs / $filters['per_page']) : 0;

        wp_send_json_success([
            'logs' => $logs ?: [],
            'pagination' => [
                'total_logs' => $total_logs,
                'total_pages' => $total_pages,
                'current_page' => $filters['page'],
            ],
            'providers' => $this->get_available_vector_source_providers($provider_map),
        ]);
    }

    /**
     * Provider label map for vector sources.
     *
     * @return array<string, string>
     */
    private function get_vector_source_provider_map(): array
    {
        return [
            'openai' => 'OpenAI',
            'pinecone' => 'Pinecone',
            'qdrant' => 'Qdrant',
            'chroma' => 'Chroma',
        ];
    }

    /**
     * Detect frontend/chat file uploads for Knowledgebase source rows.
     *
     * @param array<string, mixed> $log Source log row.
     */
    private function is_vector_source_user_upload(array $log): bool
    {
        $provider = strtolower((string) ($log['provider'] ?? ''));
        $file_id = (string) ($log['file_id'] ?? '');
        $batch_id = (string) ($log['batch_id'] ?? '');
        $store_name = (string) ($log['vector_store_name'] ?? '');

        if ($provider === 'openai') {
            return strncmp($store_name, 'chat_file_', strlen('chat_file_')) === 0;
        }
        if ($provider === 'pinecone') {
            return strncmp($file_id, 'chatfile_', strlen('chatfile_')) === 0 || strncmp($batch_id, 'pinecone_chat_file_', strlen('pinecone_chat_file_')) === 0;
        }
        if ($provider === 'qdrant') {
            return strncmp($batch_id, 'qdrant_chat_file_', strlen('qdrant_chat_file_')) === 0 || strncmp($file_id, 'qdrant_chat_file_', strlen('qdrant_chat_file_')) === 0;
        }
        if ($provider === 'chroma') {
            return strncmp($batch_id, 'chroma_chat_file_', strlen('chroma_chat_file_')) === 0 || strncmp($file_id, 'chroma_chat_file_', strlen('chroma_chat_file_')) === 0;
        }

        return false;
    }

    /**
     * Normalize filters for vector source queries.
     *
     * @param array<string, mixed> $post_data Raw POST data.
     * @param array<string, string> $provider_map Provider key => label.
     * @return array<string, mixed>
     */
    private function get_vector_sources_filters(array $post_data, array $provider_map): array
    {
        $page = isset($post_data['page']) ? max(1, absint($post_data['page'])) : 1;
        $per_page = isset($post_data['per_page']) ? absint($post_data['per_page']) : 10;
        $per_page = min(100, max(1, $per_page));

        $provider_key = isset($post_data['provider']) ? sanitize_key($post_data['provider']) : '';
        $provider_label = $provider_key && isset($provider_map[$provider_key]) ? $provider_map[$provider_key] : '';

        $status_filter = isset($post_data['status']) ? sanitize_key($post_data['status']) : '';
        $allowed_statuses = ['indexed', 'failed', 'processing', 'queued', 'skipped_already_indexed', 'success'];
        if ($status_filter && !in_array($status_filter, $allowed_statuses, true)) {
            $status_filter = '';
        }

        $type_filter = isset($post_data['type']) ? sanitize_key($post_data['type']) : '';
        $allowed_types = ['site', 'text', 'file'];
        if ($type_filter && !in_array($type_filter, $allowed_types, true)) {
            $type_filter = '';
        }

        $general_settings = get_option('aipkit_training_general_settings', []);
        $hide_user_uploads = isset($general_settings['hide_user_uploads'])
            ? (bool) $general_settings['hide_user_uploads']
            : true;

        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'provider_label' => $provider_label,
            'status' => $status_filter,
            'type' => $type_filter,
            'search' => isset($post_data['search']) ? sanitize_text_field($post_data['search']) : '',
            'store_id' => isset($post_data['store_id']) ? sanitize_text_field($post_data['store_id']) : '',
            'hide_user_uploads' => $hide_user_uploads,
        ];
    }

    /**
     * Builds WHERE clause and params for vector source queries.
     *
     * @param array<string, mixed> $filters Normalized filters.
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function build_vector_sources_where(array $filters): array
    {
        global $wpdb;

        $where_clauses = ['(post_id IS NOT NULL OR file_id IS NOT NULL OR indexed_content IS NOT NULL)'];
        $params = [];

        if (!empty($filters['provider_label'])) {
            $where_clauses[] = 'provider = %s';
            $params[] = $filters['provider_label'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'processing') {
                $where_clauses[] = '(status = %s OR status = %s)';
                $params[] = 'processing';
                $params[] = 'queued';
            } elseif ($filters['status'] === 'indexed') {
                $where_clauses[] = '(status = %s OR status = %s OR status = %s)';
                $params[] = 'indexed';
                $params[] = 'skipped_already_indexed';
                $params[] = 'success';
            } else {
                $where_clauses[] = 'status = %s';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'site') {
                $where_clauses[] = '(' . implode(' OR ', [
                    '(post_id IS NOT NULL)',
                    '(message LIKE %s)',
                    '(file_id LIKE %s)',
                    '(provider = %s AND message LIKE %s AND message LIKE %s)',
                ]) . ')';
                $params[] = '%wordpress post content submitted for indexing%';
                $params[] = 'wp_post_%';
                $params[] = 'Qdrant';
                $params[] = '%points upserted to qdrant%';
                $params[] = '%post id:%';
            } elseif ($filters['type'] === 'text') {
                $where_clauses[] = '(' . implode(' OR ', [
                    '(message LIKE %s)',
                    '(file_id LIKE %s)',
                    '(provider = %s AND message LIKE %s AND (post_id IS NULL OR post_id = 0) AND message NOT LIKE %s)',
                    '(provider = %s AND message LIKE %s AND (post_id IS NULL OR post_id = 0))',
                ]) . ')';
                $params[] = '%text content submitted for indexing%';
                $params[] = 'text_%';
                $params[] = 'Qdrant';
                $params[] = '%points upserted to qdrant%';
                $params[] = '%post id:%';
                $params[] = 'Chroma';
                $params[] = '%chroma records upserted%';
            } elseif ($filters['type'] === 'file') {
                $where_clauses[] = '(' . implode(' OR ', [
                    '(message LIKE %s)',
                    '(message LIKE %s)',
                    '(message LIKE %s)',
                    '(message LIKE %s)',
                    '(file_id LIKE %s)',
                    '(message LIKE %s)',
                    '(file_id LIKE %s)',
                ]) . ')';
                $params[] = '%file content submitted for indexing%';
                $params[] = '%file content embedded and upserted%';
                $params[] = '%original filename:%';
                $params[] = '%file uploaded%';
                $params[] = 'pinecone_file_%';
                $params[] = '%file chunk embedded%';
                $params[] = 'chroma_file_%';
            }
        }

        if (!empty($filters['hide_user_uploads'])) {
            $where_clauses[] = 'NOT (' . implode(' OR ', [
                '(provider = %s AND COALESCE(vector_store_name, \'\') LIKE %s)',
                '(provider = %s AND COALESCE(file_id, \'\') LIKE %s)',
                '(provider = %s AND (COALESCE(batch_id, \'\') LIKE %s OR COALESCE(file_id, \'\') LIKE %s))',
                '(provider = %s AND (COALESCE(batch_id, \'\') LIKE %s OR COALESCE(file_id, \'\') LIKE %s))',
            ]) . ')';
            $params[] = 'OpenAI';
            $params[] = 'chat_file_%';
            $params[] = 'Pinecone';
            $params[] = 'chatfile_%';
            $params[] = 'Qdrant';
            $params[] = 'qdrant_chat_file_%';
            $params[] = 'qdrant_chat_file_%';
            $params[] = 'Chroma';
            $params[] = 'chroma_chat_file_%';
            $params[] = 'chroma_chat_file_%';
        }

        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = '(message LIKE %s OR post_title LIKE %s OR file_id LIKE %s OR vector_store_name LIKE %s OR indexed_content LIKE %s)';
            $params = array_merge($params, array_fill(0, 5, $like));
        }

        if (!empty($filters['store_id'])) {
            $where_clauses[] = 'vector_store_id = %s';
            $params[] = $filters['store_id'];
        }

        return [implode(' AND ', $where_clauses), $params];
    }

    /**
     * Returns available provider options based on stored vector source entries.
     *
     * @param array<string, string> $provider_map Provider key => label.
     * @return array<int, array<string, string>>
     */
    private function get_available_vector_source_providers(array $provider_map): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipkit_vector_data_source';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Distinct providers from a plugin-owned custom table.
        $provider_labels = $wpdb->get_col("SELECT DISTINCT provider FROM {$table_name} WHERE provider <> ''");
        $provider_labels = array_filter(array_map('sanitize_text_field', (array) $provider_labels));

        if (empty($provider_labels)) {
            $provider_labels = array_values($provider_map);
        }

        $providers = [];
        foreach ($provider_map as $key => $label) {
            if (in_array($label, $provider_labels, true)) {
                $providers[] = [
                    'key' => $key,
                    'label' => $label,
                ];
            }
        }

        return $providers;
    }

    /**
     * AJAX: Retrieves CPTs and their fields/taxonomies for indexing settings UI.
     * @since 2.4.0
     */
    public function ajax_get_cpt_indexing_options()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['settings', 'sources'],
            'aipkit_ai_training_settings_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        $cpt_data = [];
        $post_types = get_post_types(['public' => true], 'objects');
        unset($post_types['attachment']);

        foreach ($post_types as $cpt) {
            $taxonomies = get_object_taxonomies($cpt->name, 'objects');
            $public_taxonomies = [];
            foreach ($taxonomies as $tax) {
                if ($tax->public) {
                    $public_taxonomies[$tax->name] = $tax->label;
                }
            }

            $cpt_data[$cpt->name] = [
                'label'      => $cpt->label,
                'fields'     => $this->get_public_meta_keys_for_post_type($cpt->name),
                'taxonomies' => $public_taxonomies,
                'basic_labels' => [
                    'source_url' => __('Source URL', 'gpt3-ai-content-generator'),
                    'title'      => __('Title', 'gpt3-ai-content-generator'),
                    'excerpt'    => __('Excerpt', 'gpt3-ai-content-generator'),
                    'content'    => __('Content', 'gpt3-ai-content-generator'),
                ]
            ];

            if ($cpt->name === 'product' && class_exists('WooCommerce')) {
                $cpt_data[$cpt->name]['woo_attributes'] = [
                    'sku'        => __('SKU', 'gpt3-ai-content-generator'),
                    'price'      => __('Price', 'gpt3-ai-content-generator'),
                    'stock'      => __('Stock Status', 'gpt3-ai-content-generator'),
                    'dimensions' => __('Weight & Dimensions', 'gpt3-ai-content-generator'),
                    'attributes' => __('Product Attributes', 'gpt3-ai-content-generator'),
                ];
            }
        }

        $saved_settings = get_option('aipkit_indexing_field_settings', []);

        $response_data = [
            'cpt_data'       => $cpt_data,
            'saved_settings' => $saved_settings,
        ];
        
        wp_send_json_success($response_data);
    }

    /**
     * AJAX: Saves the CPT indexing field settings.
     * @since 2.4.0
     */
    public function ajax_save_cpt_indexing_options()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['settings', 'sources'],
            'aipkit_ai_training_settings_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_module_access_permissions() above.
        $post_data = wp_unslash($_POST);
        $settings_json = isset($post_data['settings']) ? (string) $post_data['settings'] : '{}';
        $settings = json_decode($settings_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings)) {
            $this->send_wp_error(new WP_Error('invalid_json', __('Invalid settings format.', 'gpt3-ai-content-generator')));
            return;
        }

        // General settings are stored separately from provider indexing settings.
        $general_settings = get_option('aipkit_training_general_settings', []);
        $general_dirty = false;
        if (isset($settings['hide_user_uploads'])) {
            $general_settings['hide_user_uploads'] = (bool) $settings['hide_user_uploads'];
            unset($settings['hide_user_uploads']);
            $general_dirty = true;
        }
        if (isset($settings['show_index_button'])) {
            $general_settings['show_index_button'] = (bool) $settings['show_index_button'];
            unset($settings['show_index_button']);
            $general_dirty = true;
        }
        // Chunking settings (Pro only)
        if (isset($settings['chunk_avg_chars_per_token']) || isset($settings['chunk_max_tokens_per_chunk']) || isset($settings['chunk_overlap_tokens'])) {
            // Only allow saving if Pro
            $is_pro = \WPAICG\aipkit_dashboard::is_pro_plan();
            if ($is_pro) {
                $avg = isset($settings['chunk_avg_chars_per_token']) ? (int)$settings['chunk_avg_chars_per_token'] : null;
                $max = isset($settings['chunk_max_tokens_per_chunk']) ? (int)$settings['chunk_max_tokens_per_chunk'] : null;
                $ovl = isset($settings['chunk_overlap_tokens']) ? (int)$settings['chunk_overlap_tokens'] : null;
                $effective_max = $max !== null ? $max : (int)($general_settings['chunk_max_tokens_per_chunk'] ?? 3000);
                $effective_overlap = $ovl !== null ? $ovl : (int)($general_settings['chunk_overlap_tokens'] ?? 150);

                // Validate ranges
                if ($avg !== null) {
                    if ($avg < 2 || $avg > 4) {
                        $this->send_wp_error(new \WP_Error('invalid_chunk_avg', __('Average chars per token must be between 2 and 4.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['chunk_avg_chars_per_token'] = $avg;
                    $general_dirty = true;
                }
                if ($max !== null) {
                    if ($max < 256 || $max > 6000) {
                        $this->send_wp_error(new \WP_Error('invalid_chunk_max', __('Max tokens per chunk must be between 256 and 6000.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['chunk_max_tokens_per_chunk'] = $max;
                    $general_dirty = true;
                }
                if ($ovl !== null) {
                    if ($ovl < 0 || $ovl > 1000) {
                        $this->send_wp_error(new \WP_Error('invalid_chunk_overlap', __('Overlap tokens must be between 0 and 1000.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['chunk_overlap_tokens'] = $ovl;
                    $general_dirty = true;
                }
                if ($effective_overlap >= $effective_max) {
                    $this->send_wp_error(new \WP_Error('invalid_chunk_overlap_ratio', __('Overlap tokens must be lower than max tokens per chunk.', 'gpt3-ai-content-generator')));
                    return;
                }
            }
            // Remove from specific settings payload
            unset($settings['chunk_avg_chars_per_token'], $settings['chunk_max_tokens_per_chunk'], $settings['chunk_overlap_tokens']);
        }
        // OpenAI File Search chunking settings (Pro only)
        if (
            isset($settings['openai_file_search_chunking_mode']) ||
            isset($settings['openai_file_search_max_chunk_size_tokens']) ||
            isset($settings['openai_file_search_chunk_overlap_tokens'])
        ) {
            $is_pro = \WPAICG\aipkit_dashboard::is_pro_plan();
            if ($is_pro) {
                $mode = isset($settings['openai_file_search_chunking_mode']) ? sanitize_key((string) $settings['openai_file_search_chunking_mode']) : null;
                $max = isset($settings['openai_file_search_max_chunk_size_tokens']) ? (int) $settings['openai_file_search_max_chunk_size_tokens'] : null;
                $ovl = isset($settings['openai_file_search_chunk_overlap_tokens']) ? (int) $settings['openai_file_search_chunk_overlap_tokens'] : null;
                $effective_max = $max !== null ? $max : (int) ($general_settings['openai_file_search_max_chunk_size_tokens'] ?? 800);
                $effective_overlap = $ovl !== null ? $ovl : (int) ($general_settings['openai_file_search_chunk_overlap_tokens'] ?? 400);

                if ($mode !== null) {
                    if (!in_array($mode, ['auto', 'custom'], true)) {
                        $this->send_wp_error(new \WP_Error('invalid_openai_file_search_chunking_mode', __('OpenAI File Search chunking must be Auto or Custom.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['openai_file_search_chunking_mode'] = $mode;
                    $general_dirty = true;
                }
                if ($max !== null) {
                    if ($max < 100 || $max > 4096) {
                        $this->send_wp_error(new \WP_Error('invalid_openai_file_search_chunk_max', __('OpenAI File Search max tokens must be between 100 and 4096.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['openai_file_search_max_chunk_size_tokens'] = $max;
                    $general_dirty = true;
                }
                if ($ovl !== null) {
                    if ($ovl < 0) {
                        $this->send_wp_error(new \WP_Error('invalid_openai_file_search_chunk_overlap', __('OpenAI File Search overlap tokens must be 0 or higher.', 'gpt3-ai-content-generator')));
                        return;
                    }
                    $general_settings['openai_file_search_chunk_overlap_tokens'] = $ovl;
                    $general_dirty = true;
                }
                if ($effective_overlap > (int) floor($effective_max / 2)) {
                    $this->send_wp_error(new \WP_Error('invalid_openai_file_search_chunk_overlap_ratio', __('OpenAI File Search overlap cannot exceed half of max tokens per chunk.', 'gpt3-ai-content-generator')));
                    return;
                }
            }
            unset($settings['openai_file_search_chunking_mode'], $settings['openai_file_search_max_chunk_size_tokens'], $settings['openai_file_search_chunk_overlap_tokens']);
        }

        // File upload embedding batch settings (Pro only; implementation lives under lib).
        $batch_settings_class = '\\WPAICG\\Lib\\VectorStores\\FileUpload\\AIPKit_Vector_File_Ingestion_Batch_Settings';
        $batch_settings_path = defined('WPAICG_LIB_DIR')
            ? WPAICG_LIB_DIR . 'vector-stores/file-upload/class-aipkit-vector-file-ingestion-batch-settings.php'
            : '';
        if (!class_exists($batch_settings_class) && $batch_settings_path && file_exists($batch_settings_path)) {
            require_once $batch_settings_path;
        }
        if (class_exists($batch_settings_class) && $batch_settings_class::has_payload_keys($settings)) {
            $batch_result = $batch_settings_class::apply_payload_to_general_settings(
                $general_settings,
                $settings,
                \WPAICG\aipkit_dashboard::is_pro_plan()
            );
            if (is_wp_error($batch_result)) {
                $this->send_wp_error($batch_result);
                return;
            }
            if ($batch_result === true) {
                $general_dirty = true;
            }
            $settings = $batch_settings_class::remove_payload_keys($settings);
        }

        if ($general_dirty) {
            update_option('aipkit_training_general_settings', $general_settings);
        }

        if (empty($settings)) {
            wp_send_json_success(['message' => __('Indexing settings saved successfully.', 'gpt3-ai-content-generator')]);
            return;
        }

        // Sanitize the settings array
        $sanitized_settings = [];
        foreach ($settings as $cpt => $cpt_settings) {
            $cpt = sanitize_key($cpt);
            $sanitized_settings[$cpt] = [
                'fields' => [],
                'taxonomies' => [],
                'woo_attributes' => [],
                'basic_labels' => [],
            ];
            
            // Handle basic labels
            if (isset($cpt_settings['basic_labels']) && is_array($cpt_settings['basic_labels'])) {
                $allowed_basic_labels = ['source_url', 'title', 'excerpt', 'content'];
                foreach ($cpt_settings['basic_labels'] as $key => $label) {
                    if (in_array($key, $allowed_basic_labels)) {
                        $sanitized_settings[$cpt]['basic_labels'][sanitize_key($key)] = sanitize_text_field($label);
                    }
                }
            }
            
            if (isset($cpt_settings['fields']) && is_array($cpt_settings['fields'])) {
                foreach ($cpt_settings['fields'] as $key => $config) {
                    // Preserve original meta key (may include ":" etc.)
                    $key = is_string($key) ? wp_unslash($key) : $key;
                    // Ensure enabled is properly converted to boolean
                    $enabled = isset($config['enabled']) && $config['enabled'];
                    $sanitized_settings[$cpt]['fields'][$key] = [
                        'enabled' => (bool) $enabled,
                        'label'   => sanitize_text_field($config['label'] ?? ''),
                    ];
                }
            }
            if (isset($cpt_settings['taxonomies']) && is_array($cpt_settings['taxonomies'])) {
                foreach ($cpt_settings['taxonomies'] as $key => $config) {
                    // Preserve original taxonomy slug key
                    $key = is_string($key) ? wp_unslash($key) : $key;
                    // Ensure enabled is properly converted to boolean
                    $enabled = isset($config['enabled']) && $config['enabled'];
                    $sanitized_settings[$cpt]['taxonomies'][$key] = [
                        'enabled' => (bool) $enabled,
                        'label'   => sanitize_text_field($config['label'] ?? ''),
                    ];
                }
            }
            if (isset($cpt_settings['woo_attributes']) && is_array($cpt_settings['woo_attributes'])) {
                foreach ($cpt_settings['woo_attributes'] as $key => $config) {
                    // Preserve original key
                    $key = is_string($key) ? wp_unslash($key) : $key;
                    // Ensure enabled is properly converted to boolean
                    $enabled = isset($config['enabled']) && $config['enabled'];
                    $sanitized_settings[$cpt]['woo_attributes'][$key] = [
                        'enabled' => (bool) $enabled,
                        'label'   => sanitize_text_field($config['label'] ?? ''),
                    ];
                }
            }
        }

        update_option('aipkit_indexing_field_settings', $sanitized_settings, 'no');

        // DEBUG: Log the saved settings structure
        wp_send_json_success(['message' => __('Indexing settings saved successfully.', 'gpt3-ai-content-generator')]);
    }

    /**
     * Fetches public meta keys for a given post type by sampling recent posts.
     * @param string $post_type
     * @param int $limit
     * @return array
     */
    private function get_public_meta_keys_for_post_type(string $post_type, int $limit = 10): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Efficiently sampling meta keys.
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND meta_key NOT LIKE %s ORDER BY pm.meta_id DESC LIMIT 100",
            $post_type,
            $wpdb->esc_like('_') . '%'
        ));
        $formatted_keys = [];
        if ($keys) {
            foreach ($keys as $key) {
                $formatted_keys[$key] = ucwords(str_replace(['_', '-'], ' ', $key));
            }
        }
        return $formatted_keys;
    }

    private function resolve_stats_days($raw_days): int
    {
        $days = absint($raw_days);
        $allowed = [7, 30, 90];
        if (!in_array($days, $allowed, true)) {
            $days = 30;
        }
        return $days;
    }

    private function get_stats_time_range(int $days): array
    {
        $wp_timezone = wp_timezone();
        $end_datetime = new \DateTime('now', $wp_timezone);
        $start_datetime = new \DateTime("-{$days} days", $wp_timezone);
        $start_datetime->setTime(0, 0, 0);

        return [
            'start_ts' => $start_datetime->getTimestamp(),
            'end_ts' => $end_datetime->getTimestamp(),
        ];
    }

    private function ensure_stats_access(): bool
    {
        $permission_check = $this->check_module_access_permissions('stats', 'aipkit_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return false;
        }

        return true;
    }

    private function get_stats_post_data(): ?array
    {
        if (!$this->ensure_stats_access()) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in ensure_stats_access().
        return wp_unslash($_POST);
    }

    /**
     * Builds the privacy-safe IP blocking state for a conversation detail response.
     * The raw address is never returned to the browser.
     *
     * @param array<string,mixed> $log_row
     * @return array{can_block:bool,is_blocked:bool,reason:string}
     */
    private function build_stats_ip_block_state(array $log_row): array
    {
        $state = [
            'can_block' => false,
            'is_blocked' => false,
            'reason' => '',
        ];

        if (!class_exists(AIPKit_Role_Manager::class) || !AIPKit_Role_Manager::user_can_manage_settings()) {
            $state['reason'] = 'permission_denied';
            return $state;
        }

        if (!class_exists(AIPKit_Global_Security_Settings::class)) {
            $state['reason'] = 'security_settings_unavailable';
            return $state;
        }

        if (AIPKit_Global_Security_Settings::is_ip_anonymization_enabled()) {
            $state['reason'] = 'ip_anonymization_enabled';
            return $state;
        }

        $ip_address = isset($log_row['ip_address']) ? trim((string) $log_row['ip_address']) : '';
        if (filter_var($ip_address, FILTER_VALIDATE_IP) === false) {
            $state['reason'] = 'ip_unavailable';
            return $state;
        }

        $state['can_block'] = true;
        $state['is_blocked'] = AIPKit_Global_Security_Settings::is_ip_blocked($ip_address);
        return $state;
    }

    public function ajax_get_stats_logs()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $days = $this->resolve_stats_days($post_data['days'] ?? 30);
        $range = $this->get_stats_time_range($days);

        $page = isset($post_data['page']) ? absint($post_data['page']) : 1;
        $per_page = isset($post_data['per_page']) ? absint($post_data['per_page']) : 20;
        if ($page < 1) {
            $page = 1;
        }
        if ($per_page < 1) {
            $per_page = 20;
        }
        if ($per_page > 100) {
            $per_page = 100;
        }
        $offset = ($page - 1) * $per_page;

        $filters = [
            'start_ts' => $range['start_ts'],
            'end_ts' => $range['end_ts'],
        ];

        $bot_id_raw = isset($post_data['bot_id']) ? sanitize_text_field($post_data['bot_id']) : '';
        if ($bot_id_raw !== '') {
            $filters['bot_id'] = $bot_id_raw;
        }

        $module = isset($post_data['module']) ? sanitize_key($post_data['module']) : '';
        if ($module !== '') {
            $filters['module'] = $module;
        }

        $search = isset($post_data['search']) ? sanitize_text_field($post_data['search']) : '';
        if ($search !== '') {
            $filters['search'] = $search;
        }

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $logs = $log_storage->get_logs($filters, $per_page, $offset);
        $total_logs = $log_storage->count_logs($filters);
        $summary = $log_storage->get_log_summary($filters);
        $summary['conversations'] = (int) $total_logs;
        $total_pages = $per_page > 0 ? (int) ceil($total_logs / $per_page) : 1;

        wp_send_json_success([
            'logs' => $logs ?: [],
            'summary' => $summary,
            'pagination' => [
                'total_logs' => (int) $total_logs,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'per_page' => $per_page,
            ],
        ]);
    }

    public function ajax_get_stats_log_detail()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $log_id = isset($post_data['log_id']) ? absint($post_data['log_id']) : 0;
        if (!$log_id) {
            $this->send_wp_error(new WP_Error('missing_log_id', __('Log ID is required.', 'gpt3-ai-content-generator')));
            return;
        }

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $log_row = $log_storage->get_log_by_id($log_id);
        if (!$log_row) {
            $this->send_wp_error(new WP_Error('log_not_found', __('Log entry not found.', 'gpt3-ai-content-generator')));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aipkit_chat_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Chat log lookup by primary key on a plugin-owned custom table.
        $messages_json = $wpdb->get_var($wpdb->prepare("SELECT messages FROM {$table_name} WHERE id = %d", $log_id));

        $messages = [];
        if (!empty($messages_json)) {
            $conversation_data = json_decode($messages_json, true);
            if (is_array($conversation_data) && isset($conversation_data['messages']) && is_array($conversation_data['messages'])) {
                $messages = $conversation_data['messages'];
            } elseif (is_array($conversation_data)) {
                $messages = $conversation_data;
            }
        }

        $messages = $this->hydrate_stats_vector_score_chunks($messages);
        $log_row['messages'] = $messages;
        $log_row['message_count'] = $log_row['message_count'] ?? count($messages);
        $log_row['ip_block'] = $this->build_stats_ip_block_state($log_row);
        unset($log_row['ip_address']);

        wp_send_json_success($log_row);
    }

    /**
     * Blocks or unblocks the IP stored on a selected conversation log.
     */
    public function ajax_set_stats_ip_block()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }

        if (!class_exists(AIPKit_Role_Manager::class) || !AIPKit_Role_Manager::user_can_manage_settings()) {
            $this->send_wp_error(new WP_Error('permission_denied', __('You do not have permission to manage blocked IP addresses.', 'gpt3-ai-content-generator')));
            return;
        }

        if (!class_exists(AIPKit_Global_Security_Settings::class)) {
            $this->send_wp_error(new WP_Error('security_settings_unavailable', __('Security settings are unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        if (AIPKit_Global_Security_Settings::is_ip_anonymization_enabled()) {
            $this->send_wp_error(new WP_Error('ip_anonymization_enabled', __('IP blocking from conversation logs is unavailable while IP anonymization is enabled.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_id = isset($post_data['log_id']) ? absint($post_data['log_id']) : 0;
        $block_action = isset($post_data['block_action']) ? sanitize_key($post_data['block_action']) : '';
        if (!$log_id || !in_array($block_action, ['block', 'unblock'], true)) {
            $this->send_wp_error(new WP_Error('invalid_ip_block_request', __('A valid conversation and action are required.', 'gpt3-ai-content-generator')));
            return;
        }

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $log_row = $log_storage->get_log_by_id($log_id);
        if (!$log_row) {
            $this->send_wp_error(new WP_Error('log_not_found', __('Log entry not found.', 'gpt3-ai-content-generator')));
            return;
        }

        $ip_address = isset($log_row['ip_address']) ? trim((string) $log_row['ip_address']) : '';
        if (filter_var($ip_address, FILTER_VALIDATE_IP) === false) {
            $this->send_wp_error(new WP_Error('ip_unavailable', __('This conversation does not contain a valid IP address.', 'gpt3-ai-content-generator')));
            return;
        }

        $should_block = $block_action === 'block';
        if (!AIPKit_Global_Security_Settings::set_ip_blocked($ip_address, $should_block)) {
            $this->send_wp_error(new WP_Error('ip_block_update_failed', __('The blocked IP list could not be updated.', 'gpt3-ai-content-generator')));
            return;
        }

        wp_send_json_success([
            'message' => $should_block
                ? __('IP address blocked.', 'gpt3-ai-content-generator')
                : __('IP address unblocked.', 'gpt3-ai-content-generator'),
            'ip_block' => $this->build_stats_ip_block_state($log_row),
        ]);
    }

    /**
     * Adds chunk labels to older vector score entries when the matching source log is available.
     *
     * @param array<int,array<string,mixed>> $messages
     * @return array<int,array<string,mixed>>
     */
    private function hydrate_stats_vector_score_chunks(array $messages): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aipkit_vector_data_source';
        $table_identifier = self::get_validated_table_identifier($table_name);
        if ($table_identifier === '') {
            return $messages;
        }
        $chunk_lookup_cache = [];

        foreach ($messages as &$message) {
            if (!is_array($message)) {
                continue;
            }

            if (empty($message['vector_search_scores']) || !is_array($message['vector_search_scores'])) {
                continue;
            }

            foreach ($message['vector_search_scores'] as &$score_item) {
                if (!is_array($score_item)) {
                    continue;
                }

                $has_chunk_data = !empty($score_item['total_chunks'])
                    && (isset($score_item['chunk_number']) || isset($score_item['chunk_index']));
                if ($has_chunk_data) {
                    continue;
                }

                $provider = isset($score_item['provider']) ? sanitize_text_field((string) $score_item['provider']) : '';
                if (!in_array($provider, ['Pinecone', 'Qdrant'], true)) {
                    continue;
                }

                $result_id = isset($score_item['result_id']) ? sanitize_text_field((string) $score_item['result_id']) : '';
                if ($result_id === '') {
                    continue;
                }

                $store_id = '';
                if ($provider === 'Pinecone') {
                    $store_id = isset($score_item['index_name']) ? sanitize_text_field((string) $score_item['index_name']) : '';
                } elseif ($provider === 'Qdrant') {
                    $store_id = isset($score_item['collection_name']) ? sanitize_text_field((string) $score_item['collection_name']) : '';
                }
                if ($store_id === '') {
                    continue;
                }

                $cache_key = $provider . '|' . $store_id . '|' . $result_id;
                if (!array_key_exists($cache_key, $chunk_lookup_cache)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only lookup by indexed custom-table columns; table identifier is plugin-owned, validated, and backticked above.
                    $chunk_lookup_cache[$cache_key] = $wpdb->get_row($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is plugin-owned, validated, and backticked before interpolation for pre-WP-6.2 compatibility.
                        "SELECT message, post_title FROM {$table_identifier} WHERE provider = %s AND vector_store_id = %s AND file_id = %s ORDER BY timestamp DESC LIMIT 1",
                        $provider,
                        $store_id,
                        $result_id
                    ), ARRAY_A);
                }

                $source_row = $chunk_lookup_cache[$cache_key];
                if (empty($source_row) || !is_array($source_row)) {
                    continue;
                }

                $message_text = isset($source_row['message']) ? (string) $source_row['message'] : '';
                if (preg_match('/chunk\s+(\d+)\s*\/\s*(\d+)/i', $message_text, $matches)) {
                    $chunk_number = max(1, (int) $matches[1]);
                    $total_chunks = max(1, (int) $matches[2]);
                    $score_item['chunk_index'] = $chunk_number - 1;
                    $score_item['chunk_number'] = $chunk_number;
                    $score_item['total_chunks'] = $total_chunks;
                }

                if (empty($score_item['file_name']) && !empty($source_row['post_title'])) {
                    $score_item['file_name'] = sanitize_text_field((string) $source_row['post_title']);
                }
            }
            unset($score_item);
        }
        unset($message);

        return $messages;
    }

    public function ajax_export_stats_logs()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $filters = $this->build_stats_log_filters($post_data);
        $limit = isset($post_data['limit']) ? absint($post_data['limit']) : 1000;
        if ($limit < 1) {
            $limit = 1000;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $logs = $log_storage->get_logs($filters, $limit, 0);
        $total_logs = $log_storage->count_logs($filters);

        $headers = [
            'log_id' => __('Log ID', 'gpt3-ai-content-generator'),
            'date' => __('Date', 'gpt3-ai-content-generator'),
            'user' => __('User', 'gpt3-ai-content-generator'),
            'source' => __('Source', 'gpt3-ai-content-generator'),
            'module' => __('Module', 'gpt3-ai-content-generator'),
            'messages' => __('Messages', 'gpt3-ai-content-generator'),
            'tokens' => __('Tokens', 'gpt3-ai-content-generator'),
            'preview' => __('Preview', 'gpt3-ai-content-generator'),
            'conversation_uuid' => __('Conversation UUID', 'gpt3-ai-content-generator'),
        ];

        $lines = [];
        $lines[] = implode(',', array_map([$this, 'escape_csv_field'], array_values($headers)));

        if (!empty($logs)) {
            foreach ($logs as $log_row) {
                $date = !empty($log_row['last_message_ts']) ? gmdate('Y-m-d H:i:s', (int) $log_row['last_message_ts']) : '';
                $preview = isset($log_row['last_message_content']) ? (string) $log_row['last_message_content'] : '';
                $line = [
                    $log_row['id'] ?? '',
                    $date,
                    $log_row['user_display_name'] ?? '',
                    $log_row['bot_name'] ?? '',
                    $log_row['module'] ?? '',
                    $log_row['message_count'] ?? '',
                    $log_row['total_conversation_tokens'] ?? '',
                    $preview,
                    $log_row['conversation_uuid'] ?? '',
                ];
                $lines[] = implode(',', array_map([$this, 'escape_csv_field'], $line));
            }
        }

        $csv = implode("\r\n", $lines);
        $filename = sprintf('aipkit-logs-%s.csv', gmdate('Ymd-His'));
        $message = __('Export ready.', 'gpt3-ai-content-generator');
        if ($total_logs > $limit) {
            $message = sprintf(
                /* translators: %d: number of exported rows */
                __('Exported first %d logs (filtered).', 'gpt3-ai-content-generator'),
                $limit
            );
        }

        wp_send_json_success([
            'csv' => $csv,
            'filename' => $filename,
            'message' => $message,
        ]);
    }

    public function ajax_delete_stats_log()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $log_id = isset($post_data['log_id']) ? absint($post_data['log_id']) : 0;
        if (!$log_id) {
            $this->send_wp_error(new WP_Error('missing_log_id', __('Log ID is required.', 'gpt3-ai-content-generator')));
            return;
        }

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $log_row = $log_storage->get_log_by_id($log_id);
        if (!$log_row) {
            $this->send_wp_error(new WP_Error('log_not_found', __('Log entry not found.', 'gpt3-ai-content-generator')));
            return;
        }

        $result = $log_storage->delete_single_conversation(
            $log_row['user_id'] ?? null,
            $log_row['session_id'] ?? null,
            $log_row['bot_id'] ?? null,
            $log_row['conversation_uuid'] ?? ''
        );
        if (is_wp_error($result)) {
            $this->send_wp_error($result);
            return;
        }

        wp_send_json_success([
            'message' => __('Conversation deleted.', 'gpt3-ai-content-generator'),
            'log_id' => $log_id,
        ]);
    }

    public function ajax_delete_stats_logs()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $filters = $this->build_stats_log_filters($post_data);

        if (!class_exists(LogStorage::class)) {
            $this->send_wp_error(new WP_Error('missing_log_storage', __('Log storage is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $log_storage = new LogStorage();
        $batch_size = 500;
        $max_batches = 200;
        $total_deleted = 0;
        $batch = 0;

        do {
            $deleted = $log_storage->delete_logs($filters, $batch_size);
            if ($deleted === false) {
                $this->send_wp_error(new WP_Error('delete_failed', __('Failed to delete logs.', 'gpt3-ai-content-generator')));
                return;
            }
            $total_deleted += (int) $deleted;
            $batch++;
        } while ($deleted === $batch_size && $batch < $max_batches);

        $message = sprintf(
            /* translators: %d: number of log entries deleted */
            _n('%d log deleted.', '%d logs deleted.', $total_deleted, 'gpt3-ai-content-generator'),
            number_format_i18n($total_deleted)
        );

        if ($batch >= $max_batches && $deleted === $batch_size) {
            $message = __('Log deletion reached the maximum batch limit. Please run again to continue.', 'gpt3-ai-content-generator');
        }

        wp_send_json_success([
            'message' => $message,
            'deleted' => $total_deleted,
        ]);
    }

    public function ajax_save_stats_settings()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $enable_pruning = isset($post_data['enable_pruning']) && $post_data['enable_pruning'] === '1';
        $retention_period = isset($post_data['retention_period_days']) ? floatval($post_data['retention_period_days']) : 90;
        $customer_dashboard_page_url = isset($post_data['customer_dashboard_page_url'])
            ? esc_url_raw(trim((string) $post_data['customer_dashboard_page_url']))
            : '';
        $customer_buycredits_url = isset($post_data['customer_buycredits_url'])
            ? esc_url_raw(trim((string) $post_data['customer_buycredits_url']))
            : '';

        if (!LogConfig::is_valid_period($retention_period)) {
            $retention_period = 90;
        }

        $is_pro = class_exists('\\WPAICG\\aipkit_dashboard')
            ? \WPAICG\aipkit_dashboard::is_pro_plan()
            : false;
        if ($enable_pruning && !$is_pro) {
            $this->send_wp_error(new WP_Error('pro_required', __('Auto-delete logs is a Pro feature.', 'gpt3-ai-content-generator')));
            return;
        }

        $settings = [
            'enable_pruning' => $enable_pruning && $is_pro,
            'retention_period_days' => $retention_period,
        ];
        update_option('aipkit_log_settings', $settings, 'no');
        if ($customer_dashboard_page_url === '') {
            delete_option('aipkit_token_dashboard_page_url');
        } else {
            update_option('aipkit_token_dashboard_page_url', $customer_dashboard_page_url, 'no');
        }
        if ($customer_buycredits_url === '') {
            delete_option('aipkit_token_shop_page_url');
        } else {
            update_option('aipkit_token_shop_page_url', $customer_buycredits_url, 'no');
        }

        if (class_exists(LogCronManager::class)) {
            if ($enable_pruning && $is_pro) {
                LogCronManager::schedule_event();
            } else {
                LogCronManager::unschedule_event();
            }
        }

        wp_send_json_success([
            'message' => __('Settings saved.', 'gpt3-ai-content-generator'),
        ]);
    }

    public function ajax_get_stats_log_cron_status()
    {
        if (!$this->ensure_stats_access()) {
            return;
        }

        $log_settings = LogConfig::get_log_settings();
        $enable_pruning = (bool) ($log_settings['enable_pruning'] ?? false);
        $is_pro = class_exists('\\WPAICG\\aipkit_dashboard') ? \WPAICG\aipkit_dashboard::is_pro_plan() : false;

        $cron_hook = LogCronManager::HOOK_NAME;
        $next_scheduled = wp_next_scheduled($cron_hook);

        // Repair a missing event when pruning is enabled so the UI reflects an
        // actionable schedule instead of contradicting the saved setting.
        if ($enable_pruning && $is_pro && $next_scheduled === false) {
            LogCronManager::schedule_event();
            $next_scheduled = wp_next_scheduled($cron_hook);
        }

        $is_cron_active = $next_scheduled !== false;

        $state = 'disabled';
        $status_text = __('Disabled', 'gpt3-ai-content-generator');
        if ($enable_pruning) {
            if ($is_cron_active) {
                $state = 'scheduled';
                $status_text = (int) $next_scheduled <= time()
                    ? __('Next run is due', 'gpt3-ai-content-generator')
                    : sprintf(
                        /* translators: %s: time until the next log-pruning run. */
                        __('Next run in %s', 'gpt3-ai-content-generator'),
                        human_time_diff(time(), (int) $next_scheduled)
                    );
            } else {
                $state = 'pending';
                $status_text = __('Scheduling next run…', 'gpt3-ai-content-generator');
            }
        }

        $last_run_option = get_option('aipkit_log_pruning_last_run', '');
        $last_run_label = $last_run_option
            ? sprintf(
                /* translators: %s: last log-pruning run time. */
                __('Last run %s', 'gpt3-ai-content-generator'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_run_option))
            )
            : __('No cleanup has run yet', 'gpt3-ai-content-generator');

        wp_send_json_success([
            'state' => $state,
            'status_text' => $status_text,
            'last_run_label' => $last_run_label,
        ]);
    }

    public function ajax_get_stats_pricing_management()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $days = $this->resolve_stats_days($post_data['days'] ?? 30);

        $token_manager = class_exists(AIPKit_Token_Manager::class) ? new AIPKit_Token_Manager() : null;
        $price_resolver = $token_manager ? $token_manager->get_price_resolver() : null;
        if (!$price_resolver) {
            $this->send_wp_error(new WP_Error('missing_price_resolver', __('Pricing service is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $rules = $price_resolver->list_rules(['scope_type' => 'module']);
        $ledger_summary = [];
        $recent_activity = [];

        if (class_exists(AIPKit_Stats::class)) {
            $stats = new AIPKit_Stats();
            $ledger_summary = $stats->get_ledger_summary($days);
            $recent_activity = $stats->get_recent_ledger_activity($days, 12);
            if (is_wp_error($ledger_summary)) {
                $this->send_wp_error($ledger_summary);
                return;
            }
            if (is_wp_error($recent_activity)) {
                $this->send_wp_error($recent_activity);
                return;
            }
        }

        wp_send_json_success([
            'pricing_rules' => $rules,
            'ledger_summary' => $ledger_summary,
            'recent_activity' => $recent_activity,
        ]);
    }

    public function ajax_save_stats_pricing_rule()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $rule_id = isset($post_data['id']) ? absint($post_data['id']) : 0;
        $module = isset($post_data['module']) ? sanitize_key($post_data['module']) : '';
        $provider = isset($post_data['provider']) ? sanitize_text_field($post_data['provider']) : '';
        $model = isset($post_data['model']) ? sanitize_text_field($post_data['model']) : '';
        $operation = isset($post_data['operation']) ? sanitize_key($post_data['operation']) : '';
        $billing_method = isset($post_data['billing_method']) ? sanitize_key($post_data['billing_method']) : '';
        $enabled = isset($post_data['enabled']) ? ($post_data['enabled'] === '1' || $post_data['enabled'] === 1) : true;

        $allowed_modules = $this->get_allowed_pricing_modules();
        if (!in_array($module, $allowed_modules, true)) {
            $this->send_wp_error(new WP_Error('invalid_pricing_module', __('Invalid pricing module.', 'gpt3-ai-content-generator')));
            return;
        }

        $allowed_operations = $this->get_allowed_pricing_operations($module);
        if (!in_array($operation, $allowed_operations, true)) {
            $this->send_wp_error(new WP_Error('invalid_pricing_operation', __('Invalid pricing operation.', 'gpt3-ai-content-generator')));
            return;
        }

        $allowed_billing_methods = $this->get_allowed_billing_methods($operation);
        if (!in_array($billing_method, $allowed_billing_methods, true)) {
            $this->send_wp_error(new WP_Error('invalid_billing_method', __('Invalid billing method.', 'gpt3-ai-content-generator')));
            return;
        }

        $token_manager = class_exists(AIPKit_Token_Manager::class) ? new AIPKit_Token_Manager() : null;
        $price_resolver = $token_manager ? $token_manager->get_price_resolver() : null;
        if (!$price_resolver) {
            $this->send_wp_error(new WP_Error('missing_price_resolver', __('Pricing service is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $result = $price_resolver->save_rule([
            'id' => $rule_id,
            'scope_type' => 'module',
            'scope_id' => null,
            'module' => $module,
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'billing_method' => $billing_method,
            'input_rate' => $post_data['input_rate'] ?? null,
            'output_rate' => $post_data['output_rate'] ?? null,
            'unit_rate' => $post_data['unit_rate'] ?? null,
            'enabled' => $enabled ? 1 : 0,
        ]);

        if (is_wp_error($result)) {
            $this->send_wp_error($result);
            return;
        }

        wp_send_json_success([
            'message' => $rule_id > 0
                ? __('Pricing rule updated.', 'gpt3-ai-content-generator')
                : __('Pricing rule created.', 'gpt3-ai-content-generator'),
            'rule_id' => (int) $result,
        ]);
    }

    public function ajax_delete_stats_pricing_rule()
    {
        $post_data = $this->get_stats_post_data();
        if ($post_data === null) {
            return;
        }
        $rule_id = isset($post_data['id']) ? absint($post_data['id']) : 0;
        if ($rule_id <= 0) {
            $this->send_wp_error(new WP_Error('missing_pricing_rule_id', __('Pricing rule ID is required.', 'gpt3-ai-content-generator')));
            return;
        }

        $token_manager = class_exists(AIPKit_Token_Manager::class) ? new AIPKit_Token_Manager() : null;
        $price_resolver = $token_manager ? $token_manager->get_price_resolver() : null;
        if (!$price_resolver) {
            $this->send_wp_error(new WP_Error('missing_price_resolver', __('Pricing service is unavailable.', 'gpt3-ai-content-generator')));
            return;
        }

        $deleted = $price_resolver->delete_rule($rule_id);
        if (!$deleted) {
            $this->send_wp_error(new WP_Error('delete_pricing_rule_failed', __('Failed to delete pricing rule.', 'gpt3-ai-content-generator')));
            return;
        }

        wp_send_json_success([
            'message' => __('Pricing rule deleted.', 'gpt3-ai-content-generator'),
            'rule_id' => $rule_id,
        ]);
    }

    private function build_stats_log_filters(array $post_data): array
    {
        $days = $this->resolve_stats_days($post_data['days'] ?? 30);
        $range = $this->get_stats_time_range($days);

        $filters = [
            'start_ts' => $range['start_ts'],
            'end_ts' => $range['end_ts'],
        ];

        $bot_id_raw = isset($post_data['bot_id']) ? sanitize_text_field($post_data['bot_id']) : '';
        if ($bot_id_raw !== '') {
            $filters['bot_id'] = $bot_id_raw;
        }

        $module = isset($post_data['module']) ? sanitize_key($post_data['module']) : '';
        if ($module !== '') {
            $filters['module'] = $module;
        }

        $search = isset($post_data['search']) ? sanitize_text_field($post_data['search']) : '';
        if ($search !== '') {
            $filters['search'] = $search;
        }

        return $filters;
    }

    /**
     * @param string|int|float|null $value
     */
    private function escape_csv_field($value): string
    {
        $string_value = (string) $value;
        $string_value = str_replace(["\r\n", "\n", "\r"], ' ', $string_value);
        $string_value = str_replace('"', '""', $string_value);
        return '"' . $string_value . '"';
    }

    /**
     * @return array<int, string>
     */
    private function get_allowed_pricing_modules(): array
    {
        return ['chat', 'ai_forms', 'image_generator'];
    }

    /**
     * @return array<int, string>
     */
    private function get_allowed_pricing_operations(string $module): array
    {
        switch ($module) {
            case 'chat':
                return ['chat'];
            case 'ai_forms':
                return ['form_submit'];
            case 'image_generator':
                return ['generate', 'edit', 'video_generate'];
            default:
                return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function get_allowed_billing_methods(string $operation): array
    {
        switch ($operation) {
            case 'chat':
            case 'form_submit':
                return ['per_1k_tokens', 'flat'];
            case 'generate':
            case 'edit':
                return ['per_image', 'flat'];
            case 'video_generate':
                return ['per_video', 'flat'];
            default:
                return ['flat'];
        }
    }

}
