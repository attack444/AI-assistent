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
 * Pinecone Vector Store Provider Strategy (Modularized).
 */
class AIPKit_Vector_Pinecone_Strategy extends AIPKit_Vector_Base_Provider_Strategy {
    // Properties are protected to be accessible by the namespaced functions via $this
    protected $api_key;
    protected $base_api_url = 'https://api.pinecone.io'; // Controller plane API

    public function __construct() {
    }

    /**
     * @return bool|\WP_Error
     */
    public function connect(array $config) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\connect_logic($this, $config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function create_index_if_not_exists(string $index_name, array $index_config) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\create_index_if_not_exists_logic($this, $index_name, $index_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upsert_vectors(string $index_name, array $vectors_data) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\upsert_vectors_logic($this, $index_name, $vectors_data);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function query_vectors(string $index_name, array $query_vector_param, int $top_k, array $filter = []) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\query_vectors_logic($this, $index_name, $query_vector_param, $top_k, $filter);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_vectors(string $index_name, array $vector_ids) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\delete_vectors_logic($this, $index_name, $vector_ids);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_index(string $index_name) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\delete_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_indexes(?int $limit = null, ?string $order = null, ?string $after = null, ?string $before = null) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\list_indexes_logic($this, $limit, $order, $after, $before);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function describe_index(string $index_name) {
        return \WPAICG\Vector\Providers\Pinecone\Methods\describe_index_logic($this, $index_name);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'user_data') {
        return \WPAICG\Vector\Providers\Pinecone\Methods\upload_file_for_vector_store_logic($this, $file_path, $original_filename, $purpose);
    }

    // Getters for protected properties if needed by externalized functions
    public function get_api_key(): ?string { return $this->api_key; }
    public function get_base_api_url(): string { return $this->base_api_url; }
    public function get_is_connected_status(): bool { return $this->is_connected; }
    public function set_is_connected_status(bool $status): void { $this->is_connected = $status; }
    public function set_api_key(string $key): void { $this->api_key = $key; }

}
