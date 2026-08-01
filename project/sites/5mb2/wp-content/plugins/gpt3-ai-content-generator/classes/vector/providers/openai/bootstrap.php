<?php

namespace WPAICG\Vector\Providers;

use WPAICG\Vector\AIPKit_Vector_Base_Provider_Strategy;
use WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder;
use WP_Error;

require_once __DIR__ . '/methods.php';


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenAI Vector Store Provider Strategy (Modularized).
 * Interacts with OpenAI's Vector Store API.
 */
class AIPKit_Vector_OpenAI_Strategy extends AIPKit_Vector_Base_Provider_Strategy {

    // Properties are now protected to be accessible by the namespaced functions via $this
    protected $api_key;
    protected $base_url = 'https://api.openai.com';
    protected $api_version = 'v1';
    protected static $static_mime_type_map; // Renamed to avoid potential conflicts

    public function __construct() {
        if (empty(self::$static_mime_type_map)) {
            self::$static_mime_type_map = require __DIR__ . '/mime-map.php';
        }
        // Ensure OpenAIUrlBuilder is loaded
        if (!class_exists(OpenAIUrlBuilder::class)) {
            $url_builder_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/openai/bootstrap-provider-strategy.php';
            if (file_exists($url_builder_path)) {
                require_once $url_builder_path;
            }
        }
    }

    /**
     * @return bool|\WP_Error
     */
    public function connect(array $config) {
        // The main logic of setting properties is done here, the _logic file might do a test call.
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', __('OpenAI API Key is required for connection.', 'gpt3-ai-content-generator'));
        }
        $this->api_key = $config['api_key'];
        $this->base_url = $config['base_url'] ?? $this->base_url;
        $this->api_version = $config['api_version'] ?? $this->api_version;
        $this->is_connected = true; // Mark as connected before trying a test call in logic file

        return \WPAICG\Vector\Providers\OpenAI\Methods\connect_logic($this, $config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function create_index_if_not_exists(string $index_name, array $index_config) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\create_index_if_not_exists_logic($this, $index_name, $index_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upsert_vectors(string $index_name, array $vectors) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\upsert_vectors_logic($this, $index_name, $vectors);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function query_vectors(string $index_name, array $query_vector, int $top_k, array $filter = []) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\query_vectors_logic($this, $index_name, $query_vector, $top_k, $filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_vectors(string $index_name, array $vector_ids) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\delete_vectors_logic($this, $index_name, $vector_ids);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_index(string $index_name) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\delete_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_indexes(?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\list_indexes_logic($this, $limit, $order, $after, $before);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function describe_index(string $index_name) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\describe_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function retrieve_file_batch(string $vector_store_id, string $batch_id) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\retrieve_file_batch_logic($this, $vector_store_id, $batch_id);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_vector_store_files(string $vector_store_id, array $query_params = []) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\list_vector_store_files_logic($this, $vector_store_id, $query_params);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'assistants_file') {
        return \WPAICG\Vector\Providers\OpenAI\Methods\upload_file_for_vector_store_logic($this, $file_path, $original_filename, $purpose);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_openai_file_object(string $file_id) {
        return \WPAICG\Vector\Providers\OpenAI\Methods\delete_openai_file_object_logic($this, $file_id);
    }

    // Getter for static mime map for use in get_mime_type_from_filename_logic
    public static function get_static_mime_type_map(): array {
        if (empty(self::$static_mime_type_map)) {
            self::$static_mime_type_map = require __DIR__ . '/mime-map.php';
        }
        return self::$static_mime_type_map;
    }
    
    // Getters for protected properties if needed by externalized functions (if not passing $this context fully)
    public function get_api_key(): ?string { return $this->api_key; }
    public function get_base_url(): string { return $this->base_url; }
    public function get_api_version(): string { return $this->api_version; }
    public function get_is_connected_status(): bool { return $this->is_connected; }

    // Public wrappers for protected base class methods, if needed by external functions
    /**
     * @return mixed[]|\WP_Error
     */
    public function decode_json_public_wrapper(string $json_string, string $context) {
        return parent::decode_json($json_string, $context);
    }
    public function parse_error_response_public_wrapper($response_body, int $status_code, string $context): string {
        return parent::parse_error_response($response_body, $status_code, $context);
    }
}
