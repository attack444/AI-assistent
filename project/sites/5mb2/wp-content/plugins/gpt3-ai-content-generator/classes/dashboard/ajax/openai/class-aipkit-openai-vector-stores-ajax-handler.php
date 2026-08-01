<?php


namespace WPAICG\Dashboard\Ajax; // Corrected namespace

use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;

// DO NOT require_once the fn-*.php files from here; they are loaded by Vector_Store_Ajax_Handlers_Loader
// However, the new handler-stores/ajax-*.php WILL be required by the methods below.


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for OpenAI Vector Store management (list, create, delete, search).
 * Delegates logic to namespaced functions.
 */
class AIPKit_OpenAI_Vector_Stores_Ajax_Handler extends BaseDashboardAjaxHandler
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


    public function ajax_list_vector_stores_openai()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot', 'vector_content_indexer'],
            'aipkit_vector_store_nonce_openai'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-stores/ajax-list-vector-stores-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerStores\do_ajax_list_vector_stores_openai_logic($this);
    }

    public function ajax_create_vector_store_openai()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_nonce_openai'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-stores/ajax-create-vector-store-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerStores\do_ajax_create_vector_store_openai_logic($this);
    }

    public function ajax_delete_vector_store_openai()
    {
        $permission_check = $this->check_any_module_access_permissions(
            ['sources', 'chatbot'],
            'aipkit_vector_store_nonce_openai'
        );
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/handler-stores/ajax-delete-vector-store-openai.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerStores\do_ajax_delete_vector_store_openai_logic($this);
    }
}
