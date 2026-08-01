<?php
namespace WPAICG\Vector\Providers\OpenAI\Methods;

use WPAICG\Core\AIPKit_HTTP_Request;
use WPAICG\Vector\Providers\AIPKit_Vector_OpenAI_Strategy;
use WP_Error;
use WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder;
use CURLFile;

if (!defined('ABSPATH')) {
    exit;
}

// --- _request.php ---
/**
 * Logic for the _request method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $method HTTP method (GET, POST, DELETE).
 * @param string $url Full request URL.
 * @param array $body Request body for POST requests or multipart data for file uploads.
 * @param bool $is_file_upload True if this is a multipart/form-data file upload.
 * @return array|WP_Error Decoded JSON response or WP_Error.
 */
function _request_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $method, string $url, array $body = [], bool $is_file_upload = false)
{
    $api_key = $strategyInstance->get_api_key();
    if (empty($api_key)) {
        return new WP_Error('openai_vector_missing_key', __('OpenAI API Key is not configured.', 'gpt3-ai-content-generator'));
    }

    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'OpenAI-Beta'   => 'assistants=v2',
    ];
    if (!$is_file_upload) {
        $headers['Content-Type'] = 'application/json';
    }

    $request_args = [
        'method'  => strtoupper($method),
        'headers' => $headers,
        'timeout' => 120,
    ];

    if (!$is_file_upload && !empty($body) && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
        $request_args['body'] = wp_json_encode($body);
    } elseif ($is_file_upload && !empty($body)) {
        $request_args['body'] = $body;
    }

    $response_body = null;
    $status_code = null;

    if ($is_file_upload) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Reason: Using cURL for streaming.
        $ch = curl_init();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_URL, $url);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = "{$key}: {$value}";
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Reason: Using cURL for streaming.
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- Reason: Using cURL for streaming.
        $response_body = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Reason: Using cURL for streaming.
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- Reason: Using cURL for streaming.
        $curl_error = curl_error($ch);
        $ch = null;
        if ($curl_error) {
            return new WP_Error('openai_vector_curl_error', 'cURL error during request: ' . $curl_error);
        }
    } else {
        $response = class_exists(AIPKit_HTTP_Request::class)
            ? AIPKit_HTTP_Request::request($url, $request_args, true)
            : wp_remote_request($url, $request_args);
        if (is_wp_error($response)) {
            return $response;
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    }

    // Call public methods on the strategy instance
    $decoded_response = $strategyInstance->decode_json($response_body, 'OpenAI Vector Store');


    if ($status_code >= 400) {
        $error_msg = $strategyInstance->parse_error_response($decoded_response ?: $response_body, $status_code, 'OpenAI Vector Store');
        /* translators: %1$d: HTTP status code, %2$s: API error message. */
        return new WP_Error('openai_vector_api_error', sprintf(__('OpenAI Vector API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, esc_html($error_msg)));
    }

    if (is_wp_error($decoded_response)) {
        return $decoded_response;
    }
    return $decoded_response;
}

// --- connect.php ---
/**
 * Logic for the connect method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param array $config Configuration array. Must include 'api_key'.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function connect_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, array $config) {
    if (empty($config['api_key'])) {
        return new WP_Error('missing_api_key', __('OpenAI API Key is required for connection.', 'gpt3-ai-content-generator'));
    }

    if (!$strategyInstance->get_is_connected_status()) { // This should be true if bootstrap's connect set it
        return new WP_Error('internal_error_connect_openai', 'Strategy instance not marked as connected before test call.');
    }
    
    // Test connection by trying to list a single vector store (less intensive)
    // This uses the _request_logic which is also externalized.
    $test_list_response = \WPAICG\Vector\Providers\OpenAI\Methods\_request_logic(
        $strategyInstance,
        'GET',
        \WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder::build('vector_stores', [
            'base_url' => $strategyInstance->get_base_url(),
            'api_version' => $strategyInstance->get_api_version(),
            'limit' => 1
        ])
    );

    if (is_wp_error($test_list_response)) {
        return $test_list_response;
    }
    return true;
}

// --- create-index-if-not-exists.php ---
/**
 * Logic for the create_index_if_not_exists method of AIPKit_Vector_OpenAI_Strategy.
 * For OpenAI, this means creating a "Vector Store".
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The name of the Vector Store to create.
 * @param array  $index_config Configuration for the Vector Store.
 * @return array|WP_Error The store object (array) on success, WP_Error on failure.
 */
function create_index_if_not_exists_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name, array $index_config) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }

    // Check if it exists first
    $list_response = list_indexes_logic($strategyInstance, 100); // List up to 100
    if (!is_wp_error($list_response) && isset($list_response['data'])) {
        foreach ($list_response['data'] as $store) {
            if (isset($store['name']) && $store['name'] === $index_name) {
                // If found by name, return the existing store object
                return $store;
            }
        }
    }

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version()
    ];
    $url = OpenAIUrlBuilder::build('vector_stores', $url_params);
    if (is_wp_error($url)) return $url;

    $body = ['name' => $index_name];
    if (isset($index_config['file_ids'])) $body['file_ids'] = $index_config['file_ids'];
    if (isset($index_config['metadata'])) $body['metadata'] = $index_config['metadata'];
    if (isset($index_config['expires_after']) && is_array($index_config['expires_after'])) {
        $body['expires_after'] = $index_config['expires_after'];
    }
    if (isset($index_config['chunking_strategy'])) {
        $body['chunking_strategy'] = $index_config['chunking_strategy'];
    } elseif (!empty($index_config['file_ids']) && is_array($index_config['file_ids'])) {
        $configured_chunking_strategy = apply_filters(
            'aipkit_openai_file_search_chunking_strategy',
            null,
            [
                'operation' => 'create_vector_store',
                'index_name' => $index_name,
                'file_ids' => $index_config['file_ids'],
            ]
        );
        if (is_array($configured_chunking_strategy)) {
            $body['chunking_strategy'] = $configured_chunking_strategy;
        }
    }


    $response = _request_logic($strategyInstance, 'POST', $url, $body);
    if (is_wp_error($response)) return $response;

    return isset($response['id']) ? $response : new WP_Error('store_creation_malformed_response', __('Malformed response after store creation.', 'gpt3-ai-content-generator'));
}

// --- delete-index.php ---
/**
 * Logic for the delete_index method of AIPKit_Vector_OpenAI_Strategy.
 * Deletes an entire OpenAI Vector Store.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The ID of the Vector Store to delete.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function delete_index_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    $vector_store_id = $index_name;

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id', $url_params);
    if (is_wp_error($url)) return $url;

    $response = _request_logic($strategyInstance, 'DELETE', $url);
    if (is_wp_error($response)) return $response;

    // OpenAI delete vector store returns {"id": "vs_...", "object": "vector_store.deleted", "deleted": true}
    return isset($response['deleted']) && $response['deleted'] === true;
}

// --- delete-openai-file.php ---
/**
 * Logic for the delete_openai_file_object method of AIPKit_Vector_OpenAI_Strategy.
 * Deletes a file object from OpenAI account.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $file_id The ID of the file to delete.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function delete_openai_file_object_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $file_id) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    if (empty($file_id)) {
        return new WP_Error('missing_file_id', __('File ID is required to delete the file object.', 'gpt3-ai-content-generator'));
    }

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'file_id' => $file_id
    ];
    // Assuming 'files_id' is a valid operation key for deleting a specific file.
    // If not, OpenAIUrlBuilder needs to be updated or a direct URL constructed.
    $url = OpenAIUrlBuilder::build('files_id', $url_params);
    if (is_wp_error($url)) {
        // Fallback if 'files_id' operation is not in builder for DELETE.
        // OpenAI DELETE file endpoint is /v1/files/{file_id}
        $version_segment = '/' . trim($strategyInstance->get_api_version(), '/');
        $url = $strategyInstance->get_base_url() . $version_segment . '/files/' . urlencode($file_id);
    }

    $response = _request_logic($strategyInstance, 'DELETE', $url);
    if (is_wp_error($response)) return $response;

    if (isset($response['deleted']) && $response['deleted'] === true && isset($response['id']) && $response['id'] === $file_id) {
        return true;
    } else {
        return new WP_Error('file_object_delete_failed', __('OpenAI API did not confirm file object deletion.', 'gpt3-ai-content-generator'));
    }
}

// --- delete-vectors.php ---
/**
 * Logic for the delete_vectors method of AIPKit_Vector_OpenAI_Strategy.
 * For OpenAI, this means detaching a file from a vector store.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The ID of the Vector Store.
 * @param array $vector_ids Array of file_ids to detach.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function delete_vectors_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name, array $vector_ids) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    if (empty($vector_ids)) return true; // No IDs to delete

    $vector_store_id = $index_name;
    $all_successful = true;

    foreach ($vector_ids as $file_id) {
        $url_params = [
            'base_url' => $strategyInstance->get_base_url(),
            'api_version' => $strategyInstance->get_api_version(),
            'vector_store_id' => $vector_store_id,
            'file_id' => $file_id
        ];
        $url = OpenAIUrlBuilder::build('vector_stores_id_files_id', $url_params);
        if (is_wp_error($url)) {
            $all_successful = false;
            continue;
        }

        $response = _request_logic($strategyInstance, 'DELETE', $url);
        if (is_wp_error($response)) {
            $all_successful = false;
        } elseif (!isset($response['deleted']) || $response['deleted'] !== true) {
            $all_successful = false;
        }
    }
    return $all_successful ? true : new WP_Error('partial_delete_failure', __('Some files could not be deleted from the vector store.', 'gpt3-ai-content-generator'));
}

// --- describe-index.php ---
/**
 * Logic for the describe_index method of AIPKit_Vector_OpenAI_Strategy.
 * Describes an OpenAI Vector Store.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The ID of the Vector Store.
 * @return array|WP_Error Store details or WP_Error.
 */
function describe_index_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    $vector_store_id = $index_name;

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id', $url_params);
    if (is_wp_error($url)) return $url;

    return _request_logic($strategyInstance, 'GET', $url);
}

// --- get-mime-type-from-filename.php ---
/**
 * Logic for the get_mime_type_from_filename method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $filename The filename.
 * @return string|WP_Error The MIME type string or WP_Error.
 */
function get_mime_type_from_filename_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (empty($extension)) {
        return new WP_Error('missing_extension', __('Filename has no extension, cannot determine MIME type.', 'gpt3-ai-content-generator'));
    }

    $mime_map = $strategyInstance::get_static_mime_type_map(); // Access static property via class

    if (isset($mime_map[$extension])) {
        return $mime_map[$extension];
    }

    /* translators: %s: File extension */
    return new WP_Error('unsupported_extension_for_mime', sprintf(__('File extension ".%s" is not explicitly mapped to a supported MIME type for OpenAI.', 'gpt3-ai-content-generator'), $extension));
}

// --- list-indexes.php ---
/**
 * Logic for the list_indexes method of AIPKit_Vector_OpenAI_Strategy.
 * Lists OpenAI Vector Stores with pagination.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param int|null $limit Max items.
 * @param string|null $order Sort order.
 * @param string|null $after Cursor for next page.
 * @param string|null $before Cursor for previous page.
 * @return array|WP_Error List of stores or WP_Error.
 */
function list_indexes_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, ?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }

    // Ensure OpenAIUrlBuilder is available
    if (!class_exists(\WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder::class)) {
        $url_builder_bootstrap = WPAICG_PLUGIN_DIR . 'classes/core/providers/openai/bootstrap-provider-strategy.php';
        if (file_exists($url_builder_bootstrap)) {
            require_once $url_builder_bootstrap;
        } else {
            return new WP_Error('openai_url_builder_missing_logic', 'OpenAI URL builder component is not available.');
        }
    }

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'limit' => $limit,
        'order' => $order,
        'after' => $after,
        'before' => $before,
    ];
    $url = \WPAICG\Core\Providers\OpenAI\OpenAIUrlBuilder::build('vector_stores', $url_params);
    if (is_wp_error($url)) return $url;

    $response = _request_logic($strategyInstance, 'GET', $url);
    if (is_wp_error($response)) return $response;

    // OpenAI list vector stores returns: {"object": "list", "data": [...], "first_id": "...", "last_id": "...", "has_more": true/false}
    return [
        'data' => $response['data'] ?? [],
        'first_id' => $response['first_id'] ?? null,
        'last_id' => $response['last_id'] ?? null,
        'has_more' => $response['has_more'] ?? false,
    ];
}

// --- list-vector-store-files.php ---
/**
 * Logic for the list_vector_store_files method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $vector_store_id The ID of the Vector Store.
 * @param array $query_params Optional query parameters.
 * @return array|WP_Error List of file objects or WP_Error.
 */
function list_vector_store_files_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $vector_store_id, array $query_params = []) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id_files', $url_params);
    if (is_wp_error($url)) return $url;

    if (!empty($query_params)) {
        $url = add_query_arg(array_map('sanitize_text_field', $query_params), $url);
    }

    $response = _request_logic($strategyInstance, 'GET', $url);
    if (is_wp_error($response)) return $response;

    return $response['data'] ?? [];
}

// --- query-vectors.php ---
/**
 * Logic for the query_vectors method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The ID of the Vector Store.
 * @param array $query_vector Array containing 'query_text'.
 * @param int $top_k Number of results.
 * @param array $filter Optional filters.
 * @return array|WP_Error Search results or WP_Error.
 */
function query_vectors_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name, array $query_vector, int $top_k, array $filter = []) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }

    $vector_store_id = $index_name;
    if (!isset($query_vector['query_text']) || !is_string($query_vector['query_text'])) {
        return new WP_Error('invalid_query_type', __('For OpenAI vector store search, query_vector must be an array containing a "query_text" string.', 'gpt3-ai-content-generator'));
    }

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id_search', $url_params);
    if (is_wp_error($url)) return $url;

    $body = [
        'query' => $query_vector['query_text'],
        'max_num_results' => max(1, min($top_k, 50)), // OpenAI API limit for max_num_results
    ];
    if (!empty($filter)) {
        $body['filters'] = $filter;
    }
    if (isset($query_vector['ranking_options'])) {
        $body['ranking_options'] = $query_vector['ranking_options'];
    }

    $response = _request_logic($strategyInstance, 'POST', $url, $body);
    if (is_wp_error($response)) return $response;

    $results = [];
    if (isset($response['data']) && is_array($response['data'])) {
        foreach ($response['data'] as $item) {
            $results[] = [
                'id' => $item['file_id'] ?? null,
                'score' => $item['score'] ?? null,
                'metadata' => $item['attributes'] ?? [],
                'content' => $item['content'] ?? null,
                'raw_item' => $item // Include raw for potential future use
            ];
        }
    }
    return $results;
}

// --- retrieve-file-batch.php ---
/**
 * Logic for the retrieve_file_batch method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $vector_store_id The ID of the Vector Store.
 * @param string $batch_id The ID of the file batch.
 * @return array|WP_Error Batch details or WP_Error.
 */
function retrieve_file_batch_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $vector_store_id, string $batch_id) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id,
        'batch_id' => $batch_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id_file_batches_id', $url_params);
    if (is_wp_error($url)) return $url;

    return _request_logic($strategyInstance, 'GET', $url);
}

// --- upload-file.php ---
/**
 * Logic for the upload_file_for_vector_store method of AIPKit_Vector_OpenAI_Strategy.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $file_path Absolute path to the file on the server.
 * @param string $original_filename The original filename.
 * @param string $purpose Purpose of the file.
 * @return array|WP_Error OpenAI file object or WP_Error.
 */
function upload_file_for_vector_store_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $file_path, string $original_filename, string $purpose = 'assistants_file') {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return new WP_Error('file_not_readable', __('File not found or not readable at path: ', 'gpt3-ai-content-generator') . $file_path);
    }
    if (!class_exists('CURLFile')) {
        return new WP_Error('curlfile_missing', __('Server configuration error (CURLFile missing for file upload).', 'gpt3-ai-content-generator'), ['status' => 500]);
    }

    $url_builder_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version()
    ];
    $url = OpenAIUrlBuilder::build('files', $url_builder_params);
    if (is_wp_error($url)) return $url;

    $mime_type = get_mime_type_from_filename_logic($strategyInstance, $original_filename);
    if (is_wp_error($mime_type)) {
        $mime_type = 'application/octet-stream';
    }

    $cfile = new CURLFile($file_path, $mime_type, $original_filename);
    $data = ['purpose' => $purpose, 'file' => $cfile];

    return _request_logic($strategyInstance, 'POST', $url, $data, true);
}

// --- upsert-vectors.php ---
/**
 * Logic for the upsert_vectors method of AIPKit_Vector_OpenAI_Strategy.
 * For OpenAI, this means adding files to a vector store batch.
 *
 * @param AIPKit_Vector_OpenAI_Strategy $strategyInstance The instance of the strategy class.
 * @param string $index_name The ID of the Vector Store.
 * @param array $vectors Data containing 'file_ids' and optional 'chunking_strategy'.
 * @return array|WP_Error The batch object or WP_Error on failure.
 */
function upsert_vectors_logic(AIPKit_Vector_OpenAI_Strategy $strategyInstance, string $index_name, array $vectors) {
    if (!$strategyInstance->get_is_connected_status()) {
        return new WP_Error('not_connected', __('Not connected to OpenAI.', 'gpt3-ai-content-generator'));
    }

    $vector_store_id = $index_name;
    $file_ids = $vectors['file_ids'] ?? null;

    if (empty($file_ids) || !is_array($file_ids)) {
        return new WP_Error('missing_file_ids', __('File IDs are required for upserting to OpenAI Vector Store.', 'gpt3-ai-content-generator'));
    }

    $url_params = [
        'base_url' => $strategyInstance->get_base_url(),
        'api_version' => $strategyInstance->get_api_version(),
        'vector_store_id' => $vector_store_id
    ];
    $url = OpenAIUrlBuilder::build('vector_stores_id_file_batches', $url_params);
    if (is_wp_error($url)) return $url;

    $body = ['file_ids' => $file_ids];
    if (isset($vectors['chunking_strategy'])) {
        $body['chunking_strategy'] = $vectors['chunking_strategy'];
    } else {
        $configured_chunking_strategy = apply_filters(
            'aipkit_openai_file_search_chunking_strategy',
            null,
            [
                'operation' => 'file_batch',
                'vector_store_id' => $vector_store_id,
                'file_ids' => $file_ids,
            ]
        );
        if (is_array($configured_chunking_strategy)) {
            $body['chunking_strategy'] = $configured_chunking_strategy;
        }
    }

    return _request_logic($strategyInstance, 'POST', $url, $body);
}
