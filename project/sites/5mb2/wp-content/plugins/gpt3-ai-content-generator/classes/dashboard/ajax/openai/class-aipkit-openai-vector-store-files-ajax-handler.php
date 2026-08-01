<?php


namespace WPAICG\Dashboard\Ajax; // Corrected namespace

use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;
use WPAICG\aipkit_dashboard; // For Pro check

// DO NOT require_once the fn-*.php files from here; they are loaded by Vector_Store_Ajax_Handlers_Loader
// However, the new handler-files/ajax-*.php WILL be required by the methods below.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for OpenAI Vector Store file operations.
 * Delegates logic to namespaced functions defined in separate files.
 */
class AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler extends BaseDashboardAjaxHandler
{
    private $vector_store_manager;
    private $vector_store_registry;
    private $data_source_table_name;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data_source_table_name = $wpdb->prefix . 'aipkit_vector_data_source';

        $this->vector_store_manager = $this->create_vector_store_manager();
        $this->vector_store_registry = $this->create_vector_store_registry();

        if (!class_exists(\WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory::class)) {
            $factory_path = WPAICG_PLUGIN_DIR . 'classes/vector/class-aipkit-vector-provider-strategy-factory.php';
            if (file_exists($factory_path)) {
                require_once $factory_path;
            }
        }
        if (!class_exists(\WPAICG\Includes\AIPKit_Upload_Utils::class)) {
            $upload_utils_path = WPAICG_PLUGIN_DIR . 'includes/class-aipkit-upload-utils.php';
            if (file_exists($upload_utils_path)) {
                require_once $upload_utils_path;
            }
        }
        if (!class_exists(aipkit_dashboard::class)) {
            $dashboard_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_dashboard.php';
            if (file_exists($dashboard_path)) {
                require_once $dashboard_path;
            }
        }
    }

    // --- Getter methods for dependencies needed by the new standalone functions ---
    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }
    public function get_vector_store_registry(): ?AIPKit_Vector_Store_Registry
    {
        return $this->vector_store_registry;
    }
    public function get_wpdb(): \wpdb
    {
        return $this->wpdb;
    }
    public function get_data_source_table_name(): string
    {
        return $this->data_source_table_name;
    }
    // --- End Getters ---

    public function ajax_add_text_to_vector_store_openai()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_nonce_openai'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-files/ajax-add-text-to-vector-store-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles\do_ajax_add_text_to_vector_store_openai_logic($this);
    }

    public function ajax_upload_and_add_file_to_store_direct_openai()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_nonce_openai');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-files/ajax-upload-and-add-file-to-store-direct-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles\do_ajax_upload_and_add_file_to_store_direct_openai_logic($this);
    }

    public function ajax_get_openai_file_batch_status()
    {
        $permission_check = $this->check_any_module_access_permissions(['sources', 'chatbot'], 'aipkit_vector_store_nonce_openai');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-files/ajax-get-file-batch-status-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerFiles\do_ajax_get_openai_file_batch_status_logic($this);
    }
}
