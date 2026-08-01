<?php

namespace WPAICG\Vector\ManagerMethods;

use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Interface;
use WPAICG\Vector\AIPKit_Vector_Provider_Strategy_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper to get and connect a strategy.
 * This logic was previously a private method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider name.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return AIPKit_Vector_Provider_Strategy_Interface|WP_Error The connected strategy instance or WP_Error.
 */
function get_connected_strategy_logic(string $provider, array $provider_config) {
    if (!class_exists(AIPKit_Vector_Provider_Strategy_Factory::class)) {
        // This should ideally be caught by the main class constructor or dependency loader
        return new WP_Error('factory_missing', __('Vector Provider Strategy Factory is not available.', 'gpt3-ai-content-generator'));
    }

    $strategy = AIPKit_Vector_Provider_Strategy_Factory::get_strategy($provider);
    if (is_wp_error($strategy)) {
        return $strategy;
    }

    $connect_result = $strategy->connect($provider_config);
    if (is_wp_error($connect_result) || $connect_result === false) {
        /* translators: %s is the vector store provider name */
        return is_wp_error($connect_result) ? $connect_result : new WP_Error('connection_failed', sprintf(__('Failed to connect to %s vector store.', 'gpt3-ai-content-generator'), $provider));
    }
    return $strategy;
}

/**
 * Logic for creating an index in the specified vector store if it doesn't already exist.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider (e.g., 'Pinecone', 'Qdrant', 'OpenAI').
 * @param string $index_name The name of the index to create.
 * @param array $index_config Provider-specific configuration for the index.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return array|WP_Error The store object (array) on success, WP_Error on failure.
 */
function create_index_if_not_exists_logic(string $provider, string $index_name, array $index_config, array $provider_config) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->create_index_if_not_exists($index_name, $index_config);
}

/**
 * Logic for adding or updating vectors in the specified index.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param string $index_name The name of the index.
 * @param array $vectors An array of vector objects/data to upsert.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return array|WP_Error Result of the upsert operation or WP_Error.
 */
function upsert_vectors_logic(string $provider, string $index_name, array $vectors, array $provider_config) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->upsert_vectors($index_name, $vectors);
}

/**
 * Logic for querying vectors from the specified index.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param string $index_name The name of the index.
 * @param array $query_vector The vector to query against.
 * @param int $top_k The number of nearest neighbors to return.
 * @param array $filter Optional metadata filter.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return array|WP_Error Array of matching vectors or WP_Error.
 */
function query_vectors_logic(string $provider, string $index_name, array $query_vector, int $top_k, array $filter = [], array $provider_config = []) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->query_vectors($index_name, $query_vector, $top_k, $filter);
}

/**
 * Logic for deleting vectors from the specified index by their IDs.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param string $index_name The name of the index.
 * @param array $vector_ids An array of vector IDs to delete.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function delete_vectors_logic(string $provider, string $index_name, array $vector_ids, array $provider_config) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->delete_vectors($index_name, $vector_ids);
}

/**
 * Logic for deleting an entire index.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param string $index_name The name of the index to delete.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function delete_index_logic(string $provider, string $index_name, array $provider_config) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->delete_index($index_name);
}

/**
 * Logic for listing available indexes (or collections) for a given provider.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @param int|null $limit The maximum number of items to return.
 * @param string|null $order The order of items ('asc' or 'desc').
 * @param string|null $after A cursor for use in pagination.
 * @param string|null $before A cursor for use in pagination.
 * @return array|WP_Error An array of index names or index detail objects, or WP_Error on failure.
 */
function list_all_indexes_logic(string $provider, array $provider_config, ?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->list_indexes($limit, $order, $after, $before);
}

/**
 * Logic for describing an index (or collection), returning its configuration and status.
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider.
 * @param string $index_name The name of the index/collection.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @return array|WP_Error An array containing index details, or WP_Error if not found or on failure.
 */
function describe_single_index_logic(string $provider, string $index_name, array $provider_config) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    return $strategy->describe_index($index_name);
}

/**
 * Logic for listing files in a specific vector store (primarily for OpenAI).
 * This was previously a method in AIPKit_Vector_Store_Manager.
 *
 * @param string $provider The vector store provider (should be 'OpenAI').
 * @param string $vector_store_id The ID of the vector store.
 * @param array $provider_config Provider-specific connection/API configuration.
 * @param array $query_params Optional query parameters for listing.
 * @return array|WP_Error List of file objects or WP_Error.
 */
function list_files_in_store_logic(string $provider, string $vector_store_id, array $provider_config, array $query_params = []) {
    $strategy = get_connected_strategy_logic($provider, $provider_config);
    if (is_wp_error($strategy)) {
        return $strategy;
    }
    if (method_exists($strategy, 'list_vector_store_files')) {
        return $strategy->list_vector_store_files($vector_store_id, $query_params);
    }
    return new WP_Error('method_not_supported', __('Listing files is not supported by this provider strategy.', 'gpt3-ai-content-generator'));
}
