<?php


namespace WPAICG\Dashboard\Ajax; // Corrected namespace

use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;

// DO NOT require_once the fn-*.php files from here; they are loaded by Vector_Store_Ajax_Handlers_Loader
// However, the new handler-indexing/ajax-*.php WILL be required by the methods below.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for fetching and indexing WordPress content into OpenAI Vector Stores.
 */
class AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler extends BaseDashboardAjaxHandler
{
    private $vector_store_manager;
    private $vector_store_registry;
    private $openai_post_processor;
    private $data_source_table_name;
    private $wpdb;


    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data_source_table_name = $wpdb->prefix . 'aipkit_vector_data_source';

        $this->vector_store_manager = $this->create_vector_store_manager();
        $this->vector_store_registry = $this->create_vector_store_registry();

        if (!class_exists(\WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor::class)) {
            $processor_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/openai/class-openai-post-processor.php';
            if (file_exists($processor_path)) {
                require_once $processor_path;
            }
        }
        if (class_exists(\WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor::class)) {
            $this->openai_post_processor = new \WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor();
        }
    }

    // --- Getter methods for dependencies needed by the new standalone functions ---
    public function get_openai_post_processor(): ?\WPAICG\Vector\PostProcessor\OpenAI\OpenAIPostProcessor
    {
        return $this->openai_post_processor;
    }
    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }
    public function get_vector_store_registry(): ?AIPKit_Vector_Store_Registry
    {
        return $this->vector_store_registry;
    }
    // --- End Getters ---


    public function ajax_fetch_wp_content_for_indexing()
    {
        require_once __DIR__ . '/handler-indexing/ajax-fetch-wp-content-for-indexing.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerIndexing\do_ajax_fetch_wp_content_for_indexing_logic($this);
    }

    public function ajax_index_selected_wp_content()
    {
        require_once __DIR__ . '/handler-indexing/ajax-index-selected-wp-content.php';
        \WPAICG\Dashboard\Ajax\OpenAI\HandlerIndexing\do_ajax_index_selected_wp_content_logic($this);
    }
}
