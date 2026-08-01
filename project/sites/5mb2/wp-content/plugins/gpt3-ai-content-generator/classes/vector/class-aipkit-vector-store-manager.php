<?php

namespace WPAICG\Vector;

use WP_Error;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory;
// Use the new namespace for the method logic functions
use WPAICG\Vector\ManagerMethods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load method logic files
$manager_methods_path = __DIR__ . '/manager/';
require_once $manager_methods_path . 'methods.php';


/**
 * AIPKit_Vector_Store_Manager (Modularized)
 *
 * Centralized class for managing interactions with various vector store providers.
 * Methods now delegate to namespaced functions.
 */
class AIPKit_Vector_Store_Manager {

    public function __construct() {
        // Ensure the factory class is available (should be loaded by DependencyLoader)
        if (!class_exists(AIPKit_Vector_Provider_Strategy_Factory::class)) {
            $factory_path = __DIR__ . '/class-aipkit-vector-provider-strategy-factory.php';
            if (file_exists($factory_path)) {
                require_once $factory_path;
            }
        }
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function create_index_if_not_exists(string $provider, string $index_name, array $index_config, array $provider_config) {
        return ManagerMethods\create_index_if_not_exists_logic($provider, $index_name, $index_config, $provider_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function upsert_vectors(string $provider, string $index_name, array $vectors, array $provider_config) {
        return ManagerMethods\upsert_vectors_logic($provider, $index_name, $vectors, $provider_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function query_vectors(string $provider, string $index_name, array $query_vector, int $top_k, array $filter = [], array $provider_config = []) {
        return ManagerMethods\query_vectors_logic($provider, $index_name, $query_vector, $top_k, $filter, $provider_config);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_vectors(string $provider, string $index_name, array $vector_ids, array $provider_config) {
        return ManagerMethods\delete_vectors_logic($provider, $index_name, $vector_ids, $provider_config);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_index(string $provider, string $index_name, array $provider_config) {
        return ManagerMethods\delete_index_logic($provider, $index_name, $provider_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_all_indexes(string $provider, array $provider_config, ?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null) {
        return ManagerMethods\list_all_indexes_logic($provider, $provider_config, $limit, $order, $after, $before);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function describe_single_index(string $provider, string $index_name, array $provider_config) {
        return ManagerMethods\describe_single_index_logic($provider, $index_name, $provider_config);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function list_files_in_store(string $provider, string $vector_store_id, array $provider_config, array $query_params = []) {
        return ManagerMethods\list_files_in_store_logic($provider, $vector_store_id, $provider_config, $query_params);
    }
}
