<?php

namespace WPAICG\Vector;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for Vector Store Provider Strategies.
 * Defines the contract for interacting with different vector database services.
 */
interface AIPKit_Vector_Provider_Strategy_Interface {

    /**
     * Connects to the vector store provider.
     * Specific connection parameters are handled by the concrete strategy.
     *
     * @param array $config Configuration array specific to the provider (e.g., API key, environment, URL).
     * @return bool|WP_Error True on successful connection, WP_Error on failure.
     */
    public function connect(array $config);

    /**
     * Creates an index (or collection/vector store) in the vector store if it does not already exist.
     *
     * @param string $index_name The name of the index/collection/vector store.
     * @param array  $index_config Provider-specific configuration for the index (e.g., dimension, metric type).
     * @return array|WP_Error The store object (array) on success (whether new or existing), WP_Error on failure.
     */
    public function create_index_if_not_exists(string $index_name, array $index_config);

    /**
     * Upserts (adds or updates) vectors into the specified index.
     * For OpenAI, this means adding files to a vector store batch.
     *
     * @param string $index_name The name of the index/collection (or vector_store_id for OpenAI).
     * @param array  $vectors    An array of vectors to upsert.
     *                           For OpenAI: ['file_ids' => [id1, id2], 'chunking_strategy' => (optional)].
     *                           For others: Each vector should be an object or associative array,
     *                           typically containing an 'id' (string), 'values' (array of floats - the embedding),
     *                           and optionally 'metadata' (associative array).
     * @return array|WP_Error An array with results (e.g., ['upserted_count' => int], or OpenAI batch object) or WP_Error on failure.
     */
    public function upsert_vectors(string $index_name, array $vectors);

    /**
     * Queries the index for vectors similar to the query_vector.
     *
     * @param string $index_name   The name of the index/collection.
     * @param array  $query_vector An array of floats representing the query embedding, or for OpenAI, an array like ['query_text' => 'search term'].
     * @param int    $top_k        The number of nearest neighbors to return.
     * @param array  $filter       Optional. Provider-specific metadata filter.
     * @return array|WP_Error An array of matching vectors with scores/distances, or WP_Error on failure.
     *                        Each result typically includes 'id', 'score', and 'metadata'.
     */
    public function query_vectors(string $index_name, array $query_vector, int $top_k, array $filter = []);

    /**
     * Deletes vectors from the specified index by their IDs.
     * For OpenAI, this means detaching a file from a vector store.
     *
     * @param string $index_name The name of the index/collection (or vector_store_id for OpenAI).
     * @param array  $vector_ids An array of vector IDs (or file_ids for OpenAI) to delete.
     * @return bool|WP_Error True on success, WP_Error on failure or if some IDs were not found/deleted.
     */
    public function delete_vectors(string $index_name, array $vector_ids);

    /**
     * Deletes an entire index (or collection / vector store).
     *
     * @param string $index_name The name of the index/collection to delete.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_index(string $index_name);

    /**
     * Lists available indexes (or collections / vector stores) for a given provider.
     *
     * @param int|null $limit The maximum number of items to return.
     * @param string|null $order The order of items ('asc' or 'desc').
     * @param string|null $after A cursor for use in pagination (fetch items after this ID).
     * @param string|null $before A cursor for use in pagination (fetch items before this ID).
     * @return array|WP_Error An array of index names or index detail objects,
     *                        or for paginated results, a structure like
     *                        ['data' => [], 'first_id' => null, 'last_id' => null, 'has_more' => false],
     *                        or WP_Error on failure.
     */
    public function list_indexes(?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null);


    /**
     * Describes an index (or collection / vector store), returning its configuration and status.
     *
     * @param string $index_name The name of the index/collection.
     * @return array|WP_Error An array containing index details, or WP_Error if not found or on failure.
     */
    public function describe_index(string $index_name);

    /**
     * Uploads a file to the provider, typically for later use in a vector store.
     * This is particularly relevant for OpenAI.
     *
     * @param string $file_path Absolute path to the file on the server.
     * @param string $original_filename The original filename with extension.
     * @param string $purpose Purpose of the file (e.g., 'user_data', 'batch').
     * @return array|WP_Error Provider-specific file object on success, WP_Error on failure.
     */
    public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'user_data');
}