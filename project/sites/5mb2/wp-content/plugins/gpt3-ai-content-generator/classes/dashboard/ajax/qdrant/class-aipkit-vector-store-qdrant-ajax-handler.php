<?php


namespace WPAICG\Dashboard\Ajax;

use WP_Error;
use WPAICG\AIPKit_Providers;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Includes\AIPKit_Upload_Utils;
use WPAICG\aipkit_dashboard; // For Pro check


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for Qdrant Vector Store operations.
 * Delegates logic to namespaced functions.
 */
class AIPKit_Vector_Store_Qdrant_Ajax_Handler extends BaseDashboardAjaxHandler
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
    public function _get_qdrant_config()
    {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return new WP_Error('dependency_missing', 'AIPKit_Providers class not found for Qdrant config.');
            }
        }
        $qdrant_data = AIPKit_Providers::get_provider_data('Qdrant');
        if (empty($qdrant_data['url'])) {
            return new WP_Error('missing_qdrant_url', __('Qdrant URL is not configured in global settings.', 'gpt3-ai-content-generator'));
        }
        if (empty($qdrant_data['api_key'])) {
            return new WP_Error('missing_qdrant_api_key', __('Qdrant API Key is not configured in global settings (required for Qdrant Cloud).', 'gpt3-ai-content-generator'));
        }
        return ['url' => $qdrant_data['url'], 'api_key' => $qdrant_data['api_key']];
    }

    /**
     * Wrapper for the logging function, to be called from the standalone logic files.
     * @param array $log_data
     */
    public function _log_vector_data_source_entry(array $log_data): void
    {
        $defaults = [
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql', 1),
            'provider' => 'Qdrant',
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
        $should_truncate = !in_array(
            $source_type,
            ['text_entry_global_form', 'file_upload_global_form', 'text_entry_qdrant_direct', 'file_upload_qdrant_direct', 'chatbot_training_text', 'chatbot_training_qa'],
            true
        );

        if ($should_truncate && is_string($data_to_insert['indexed_content']) && mb_strlen($data_to_insert['indexed_content']) > 1000) {
            $data_to_insert['indexed_content'] = mb_substr($data_to_insert['indexed_content'], 0, 997) . '...';
        }
        unset($data_to_insert['source_type_for_log']);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $this->wpdb->insert($this->data_source_table_name, $data_to_insert);
        if ($result) {
            $provider = $log_data['provider'] ?? 'Qdrant';
            $store_id = $log_data['vector_store_id'] ?? null;
            if ($store_id) {
                $cache_key_logs = strtolower($provider) . '_logs_' . sanitize_key($store_id);
                $cache_key_count = strtolower($provider) . '_logs_count_' . sanitize_key($store_id);
                $cache_group = 'aipkit_vector_logs';
                wp_cache_delete($cache_key_count, $cache_group);
                for ($i = 1; $i <= 5; $i++) {
                    wp_cache_delete($cache_key_logs . '_page_' . $i, $cache_group);
                }
            }
        }
    }


    public function ajax_list_collections_qdrant()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_qdrant_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-list-collections.php';
        \WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections\_aipkit_qdrant_ajax_list_collections_logic($this);
    }

    public function ajax_create_collection_qdrant()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_qdrant_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-create-collection.php';
        \WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections\_aipkit_qdrant_ajax_create_collection_logic($this);
    }

    public function ajax_delete_collection_qdrant()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_qdrant_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-delete-collection.php';
        \WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections\_aipkit_qdrant_ajax_delete_collection_logic($this);
    }

    public function ajax_upsert_to_qdrant_collection()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_qdrant_nonce'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-collections/ajax-upsert-to-collection.php';
        \WPAICG\Dashboard\Ajax\Qdrant\HandlerCollections\_aipkit_qdrant_ajax_upsert_to_collection_logic($this);
    }

    public function ajax_upload_file_and_upsert_to_qdrant()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_qdrant_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        if (!$this->vector_store_manager || !$this->ai_caller || !class_exists(\WPAICG\Includes\AIPKit_Upload_Utils::class)) {
            $this->send_wp_error(new WP_Error('deps_missing_qdrant_upload', __('Required components for Qdrant file upload are missing.', 'gpt3-ai-content-generator'), ['status' => 500]));
            return;
        }

        // --- Pro Check ---
        if (!aipkit_dashboard::is_pro_plan()) {
            $this->send_wp_error(new WP_Error('pro_feature_qdrant_upload', __('File upload to Qdrant is a Pro feature. Please upgrade.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }
        // --- End Pro Check ---

        $fn_file_path = WPAICG_LIB_DIR . 'vector-stores/file-upload/qdrant/fn-upload-file-and-upsert.php';
        if (!file_exists($fn_file_path)) {
            $this->send_wp_error(new WP_Error(
                'required_files_missing_qdrant_upload',
                __('Some required files seem to be missing for Qdrant file uploads. Please reinstall the Pro version of AI Puffer and try again.', 'gpt3-ai-content-generator'),
                ['status' => 500]
            ));
            return;
        }

        $qdrant_config = $this->_get_qdrant_config();
        if (is_wp_error($qdrant_config)) {
            $this->send_wp_error($qdrant_config);
            return;
        }

        require_once $fn_file_path;
        $result = \WPAICG\Lib\VectorStores\FileUpload\Qdrant\_aipkit_qdrant_ajax_upload_file_and_upsert_logic(
            $this->vector_store_manager,
            $this->ai_caller,
            $qdrant_config,
            $this
        );

        if (is_wp_error($result)) {
            // Log if error object contains log_data
            $log_data_on_error = $result->get_error_data();
            if (is_array($log_data_on_error) && isset($log_data_on_error['log_data'])) {
                $this->_log_vector_data_source_entry($log_data_on_error['log_data']);
            }
            $this->send_wp_error($result);
        } else {
            // Log if success result contains log_data
            if (isset($result['log_data']) && is_array($result['log_data'])) {
                $this->_log_vector_data_source_entry($result['log_data']);
                unset($result['log_data']); // Don't send full log data back to client
            }
            wp_send_json_success($result);
        }
    }
    // Getter methods for dependencies needed by the new standalone functions
    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
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
    public function get_vector_store_registry(): ?AIPKit_Vector_Store_Registry
    {
        return $this->vector_store_registry;
    }

}
