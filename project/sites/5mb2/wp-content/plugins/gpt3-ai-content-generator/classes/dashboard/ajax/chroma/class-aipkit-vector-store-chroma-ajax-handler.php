<?php

namespace WPAICG\Dashboard\Ajax;

use WP_Error;
use WPAICG\AIPKit_Providers;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Includes\AIPKit_Upload_Utils;
use WPAICG\aipkit_dashboard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX requests for Chroma collection operations.
 */
class AIPKit_Vector_Store_Chroma_Ajax_Handler extends BaseDashboardAjaxHandler
{
    private $vector_store_manager;
    private $vector_store_registry;
    private $ai_caller;
    private $data_source_table_name;
    private $wpdb;

    public function __construct()
    {
        $dependencies = $this->bootstrap_vector_store_ajax_dependencies();
        $this->wpdb = $dependencies['wpdb'];
        $this->data_source_table_name = $dependencies['data_source_table_name'];
        $this->vector_store_manager = $dependencies['vector_store_manager'];
        $this->vector_store_registry = $dependencies['vector_store_registry'];
        $this->ai_caller = $dependencies['ai_caller'];
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function _get_chroma_config()
    {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return new WP_Error('dependency_missing', __('AIPKit_Providers class not found for Chroma config.', 'gpt3-ai-content-generator'));
            }
        }

        $chroma_data = AIPKit_Providers::get_provider_data('Chroma');
        if (empty($chroma_data['url'])) {
            return new WP_Error('missing_chroma_url', __('Chroma URL is not configured in global settings.', 'gpt3-ai-content-generator'));
        }

        return [
            'url' => $chroma_data['url'],
            'api_key' => $chroma_data['api_key'] ?? '',
            'tenant' => $chroma_data['tenant'] ?? 'default_tenant',
            'database' => $chroma_data['database'] ?? 'default_database',
        ];
    }

    public function _log_vector_data_source_entry(array $log_data): void
    {
        $defaults = [
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql', 1),
            'provider' => 'Chroma',
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
        $should_truncate = !in_array($source_type, ['text_entry_global_form', 'file_upload_global_form', 'text_entry_chroma_direct', 'file_upload_chroma_direct', 'chatbot_training_text', 'chatbot_training_qa'], true);
        if ($should_truncate && is_string($data_to_insert['indexed_content']) && mb_strlen($data_to_insert['indexed_content']) > 1000) {
            $data_to_insert['indexed_content'] = mb_substr($data_to_insert['indexed_content'], 0, 997) . '...';
        }
        unset($data_to_insert['source_type_for_log']);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $this->wpdb->insert($this->data_source_table_name, $data_to_insert);
        if (!$result) {
            return;
        }

        $store_id = $log_data['vector_store_id'] ?? null;
        if ($store_id) {
            $cache_group = 'aipkit_vector_logs';
            $cache_key_logs = 'chroma_logs_' . sanitize_key($store_id);
            $cache_key_count = 'chroma_logs_count_' . sanitize_key($store_id);
            wp_cache_delete($cache_key_count, $cache_group);
            for ($i = 1; $i <= 5; $i++) {
                wp_cache_delete($cache_key_logs . '_page_' . $i, $cache_group);
            }
        }
    }

    public function ajax_list_collections_chroma()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_chroma_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-list-collections.php';
        \WPAICG\Dashboard\Ajax\Chroma\HandlerCollections\_aipkit_chroma_ajax_list_collections_logic($this);
    }

    public function ajax_create_collection_chroma()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_chroma_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-create-collection.php';
        \WPAICG\Dashboard\Ajax\Chroma\HandlerCollections\_aipkit_chroma_ajax_create_collection_logic($this);
    }

    public function ajax_delete_collection_chroma()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_chroma_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-delete-collection.php';
        \WPAICG\Dashboard\Ajax\Chroma\HandlerCollections\_aipkit_chroma_ajax_delete_collection_logic($this);
    }

    public function ajax_upsert_to_chroma_collection()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_chroma_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-upsert-to-collection.php';
        \WPAICG\Dashboard\Ajax\Chroma\HandlerCollections\_aipkit_chroma_ajax_upsert_to_collection_logic($this);
    }

    public function ajax_upload_file_and_upsert_to_chroma()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_chroma_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        if (!$this->vector_store_manager || !$this->ai_caller || !class_exists(\WPAICG\Includes\AIPKit_Upload_Utils::class)) {
            $this->send_wp_error(new WP_Error('deps_missing_chroma_upload', __('Required components for Chroma file upload are missing.', 'gpt3-ai-content-generator'), ['status' => 500]));
            return;
        }

        if (!class_exists(aipkit_dashboard::class) || !aipkit_dashboard::is_pro_plan()) {
            $this->send_wp_error(new WP_Error('pro_feature_chroma_upload', __('File upload to Chroma is a Pro feature. Please upgrade.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }

        $fn_file_path = WPAICG_LIB_DIR . 'vector-stores/file-upload/chroma/fn-upload-file-and-upsert.php';
        if (!file_exists($fn_file_path)) {
            $this->send_wp_error(new WP_Error(
                'required_files_missing_chroma_upload',
                __('Some required files seem to be missing for Chroma file uploads. Please reinstall the Pro version of AI Puffer and try again.', 'gpt3-ai-content-generator'),
                ['status' => 500]
            ));
            return;
        }

        $chroma_config = $this->_get_chroma_config();
        if (is_wp_error($chroma_config)) {
            $this->send_wp_error($chroma_config);
            return;
        }

        require_once $fn_file_path;
        $result = \WPAICG\Lib\VectorStores\FileUpload\Chroma\_aipkit_chroma_ajax_upload_file_and_upsert_logic(
            $this->vector_store_manager,
            $this->ai_caller,
            $chroma_config,
            $this
        );

        if (is_wp_error($result)) {
            $log_data_on_error = $result->get_error_data();
            if (is_array($log_data_on_error) && isset($log_data_on_error['log_data'])) {
                $this->_log_vector_data_source_entry($log_data_on_error['log_data']);
            }
            $this->send_wp_error($result);
            return;
        }

        if (isset($result['log_data']) && is_array($result['log_data'])) {
            $this->_log_vector_data_source_entry($result['log_data']);
            unset($result['log_data']);
        }
        wp_send_json_success($result);
    }

    public function get_vector_store_manager(): ?\WPAICG\Vector\AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }

    public function get_vector_store_registry(): ?\WPAICG\Vector\AIPKit_Vector_Store_Registry
    {
        return $this->vector_store_registry;
    }

    public function get_ai_caller(): ?\WPAICG\Core\AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }

    public function get_wpdb(): \wpdb
    {
        return $this->wpdb;
    }

    public function get_data_source_table_name(): string
    {
        return $this->data_source_table_name;
    }
}
