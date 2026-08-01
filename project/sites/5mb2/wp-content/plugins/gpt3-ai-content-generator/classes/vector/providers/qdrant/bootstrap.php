<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Vector\Providers;

use WPAICG\Vector\AIPKit_Vector_Base_Provider_Strategy;
use WP_Error;

require_once __DIR__ . '/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Qdrant Vector Store Provider Strategy (Modularized).
 */
class AIPKit_Vector_Qdrant_Strategy extends AIPKit_Vector_Base_Provider_Strategy {
    protected $api_key;
    protected $qdrant_url;
    // is_connected is inherited from AIPKit_Vector_Base_Provider_Strategy

    public function __construct() {
        // No specific constructor logic needed here for now
    }

    /**
     * @return bool|\WP_Error
     */
    public function connect(array $config) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\connect_logic($this, $config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function create_index_if_not_exists(string $index_name, array $index_config) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\create_index_if_not_exists_logic($this, $index_name, $index_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upsert_vectors(string $index_name, array $vectors_data) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\upsert_vectors_logic($this, $index_name, $vectors_data);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function query_vectors(string $index_name, array $query_vector_param, int $top_k, array $filter = []) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\query_vectors_logic($this, $index_name, $query_vector_param, $top_k, $filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_vectors(string $index_name, array $vector_ids_or_filter) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\delete_vectors_logic($this, $index_name, $vector_ids_or_filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_index(string $index_name) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\delete_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_indexes(?int $limit = null, ?string $order = null, ?string $after = null, ?string $before = null) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\list_indexes_logic($this, $limit, $order, $after, $before);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function describe_index(string $index_name) {
        return \WPAICG\Vector\Providers\Qdrant\Methods\describe_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'user_data') {
        return \WPAICG\Vector\Providers\Qdrant\Methods\upload_file_for_vector_store_logic($this, $file_path, $original_filename, $purpose);
    }
    
    // Getters for protected properties needed by externalized functions
    public function get_api_key(): ?string { return $this->api_key; }
    public function get_qdrant_url(): ?string { return $this->qdrant_url; }
    public function get_is_connected_status(): bool { return $this->is_connected; }

    // Setters for protected properties
    public function set_api_key(?string $key): void { $this->api_key = $key; }
    public function set_qdrant_url(?string $url): void { $this->qdrant_url = $url ? rtrim($url, '/') : null; }
    public function set_is_connected_status(bool $status): void { $this->is_connected = $status; }

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
