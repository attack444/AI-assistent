<?php

namespace WPAICG\Vector;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Abstract Base class for Vector Store Provider Strategies.
 * Implements the AIPKit_Vector_Provider_Strategy_Interface and can provide common helper methods.
 */
abstract class AIPKit_Vector_Base_Provider_Strategy implements AIPKit_Vector_Provider_Strategy_Interface {

    protected $client; // Stores the initialized client for the provider
    protected $is_connected = false;

    /**
     * Common helper to parse JSON, returning a WP_Error on failure.
     * @param string $json_string The JSON string to decode.
     * @param string $context Context for error messages (e.g., "Pinecone API").
     * @return array|WP_Error Decoded array or WP_Error.
     */
    public function decode_json(string $json_string, string $context) { // MODIFIED to public
        if (trim($json_string) === '') {
            return [];
        }
        $decoded = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            /* translators: %1$s: The context of the API call (e.g., "OpenAI Models"), %2$s: The specific JSON error message from PHP. */
            $error_message = sprintf(__('Failed to parse JSON response from %1$s. Error: %2$s', 'gpt3-ai-content-generator'), $context, json_last_error_msg());
            return new WP_Error('json_decode_error', $error_message);
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Common helper to parse API errors. Can be overridden by specific strategies.
     * @param mixed $response_body Raw or decoded response body.
     * @param int $status_code HTTP status code.
     * @param string $context Provider context (e.g., "Pinecone API").
     * @return string User-friendly error message.
     */
    public function parse_error_response($response_body, int $status_code, string $context): string { // MODIFIED to public
        /* translators: %1$s: The context of the error (e.g., "OpenAI Image"), %2$d: The HTTP status code. */
        $message = sprintf(__('An unknown error occurred with %1$s (HTTP %2$d).', 'gpt3-ai-content-generator'), $context, $status_code);
        $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

        if (is_array($decoded)) {
            if (!empty($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (!empty($decoded['status']['error'])) {
                $status_error = $decoded['status']['error'];
                $message = is_string($status_error)
                    ? $status_error
                    : (is_array($status_error) && !empty($status_error['message'])
                        ? $status_error['message']
                        : $message);
            } elseif (!empty($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (!empty($decoded['detail'])) {
                 $message = is_string($decoded['detail']) ? $decoded['detail'] : wp_json_encode($decoded['detail']);
            }
        } elseif (is_string($response_body) && strlen($response_body) < 500 && strlen($response_body) > 0) {
             $message = $response_body;
        }
        return trim($message);
    }

    // Abstract methods from the interface must be implemented by concrete classes
    /**
     * @return bool|\WP_Error
     */
    abstract public function connect(array $config);
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function create_index_if_not_exists(string $index_name, array $index_config);
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function upsert_vectors(string $index_name, array $vectors);
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function query_vectors(string $index_name, array $query_vector, int $top_k, array $filter = []);
    /**
     * @return bool|\WP_Error
     */
    abstract public function delete_vectors(string $index_name, array $vector_ids);
    /**
     * @return bool|\WP_Error
     */
    abstract public function delete_index(string $index_name);
    // Provide a default implementation or declare as abstract. For now, a default that returns not implemented.
    /**
     * @return mixed[]|\WP_Error
     */
    public function list_indexes(?int $limit = 20, ?string $order = 'desc', ?string $after = null, ?string $before = null) {
        return new WP_Error('not_implemented_in_base', __('Listing indexes with pagination is not implemented in the base strategy.', 'gpt3-ai-content-generator'));
    }
    /**
     * @return mixed[]|\WP_Error
     */
    abstract public function describe_index(string $index_name);

    /**
     * Uploads a file to the provider, typically for later use in a vector store.
     * This is particularly relevant for OpenAI. Other providers might return 'not_applicable'.
     *
     * @param string $file_path Absolute path to the file on the server.
     * @param string $original_filename The original filename with extension.
     * @param string $purpose Purpose of the file (e.g., 'user_data', 'batch').
     * @return array|WP_Error Provider-specific file object on success, WP_Error on failure or if not applicable.
     */
    abstract public function upload_file_for_vector_store(string $file_path, string $original_filename, string $purpose = 'user_data');
}
