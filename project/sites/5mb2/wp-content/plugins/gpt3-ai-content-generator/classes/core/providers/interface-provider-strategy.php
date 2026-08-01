<?php

namespace WPAICG\Core\Providers; // *** Correct namespace ***

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for AI Provider Strategies.
 * Defines the contract for handling provider-specific logic.
 */
interface ProviderStrategyInterface {

    /**
     * Build the full API endpoint URL for a given operation.
     *
     * @param string $operation ('chat', 'models', 'stream', 'deployments', 'embeddings', etc.)
     * @param array  $params Required parameters (api_key, base_url, api_version, model, deployment, etc.)
     * @return string|WP_Error The full URL or WP_Error.
     */
    public function build_api_url(string $operation, array $params);

    /**
     * Get necessary HTTP headers for API requests.
     *
     * @param string $api_key The API key for the provider.
     * @param string $operation The specific operation being performed.
     * @return array Key-value array of headers.
     */
    public function get_api_headers(string $api_key, string $operation): array;

    /**
     * Format the payload (messages, instructions) for a standard chat request.
     *
     * @param string $user_message The user's message.
     * @param string $instructions System instructions.
     * @param array  $history Conversation history.
     * @param array  $ai_params AI parameters (temperature, max_tokens, etc.).
     * @param string $model The target model/deployment ID.
     * @return array The formatted request body data.
     */
    public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array;

    /**
     * Parse the response from a standard chat request.
     *
     * @param array $decoded_response The decoded JSON response body.
     * @param array $request_data The original request data sent.
     * @return array|WP_Error ['content' => string, 'usage' => array|null] or WP_Error.
     */
    public function parse_chat_response(array $decoded_response, array $request_data);

    /**
     * Parse an error response from the provider.
     *
     * @param mixed $response_body The raw or decoded error response body.
     * @param int $status_code The HTTP status code.
     * @return string A user-friendly error message.
     */
    public function parse_error_response($response_body, int $status_code): string;

    /**
     * Fetch the list of available models/deployments.
     *
     * @param array $api_params Connection parameters (api_key, base_url, etc.).
     * @return array|WP_Error Formatted list [['id' => ..., 'name' => ...]] or WP_Error.
     */
    public function get_models(array $api_params);

    /**
     * Build the payload for an SSE (streaming) chat request.
     *
     * @param array $messages Formatted messages/input/contents array.
     * @param string|array|null $system_instruction Formatted system instruction.
     * @param array $ai_params AI parameters.
     * @param string $model Target model/deployment.
     * @return array The formatted request body data for SSE.
     */
    public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array;

    /**
     * Parse a chunk of data received from an SSE stream.
     *
     * @param string $sse_chunk The raw chunk received from the stream.
     * @param string &$current_buffer The reference to the incomplete buffer for this provider.
     * @return array {
     *     'delta'      => string|null, // Text delta
     *     'usage'      => array|null,  // Token usage data
     *     'is_error'   => bool,        // Fatal error flag
     *     'is_warning' => bool,        // Non-fatal warning/block flag
     *     'is_done'    => bool         // End signal flag
     * }
     */
    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array;

     /**
     * Get provider-specific request options for wp_remote_request or cURL.
     *
     * @param string $operation The operation ('chat', 'stream', 'models').
     * @return array Additional options (e.g., method, user-agent, timeout).
     */
    public function get_request_options(string $operation): array;

    /**
     * Format headers array into the ['Header: Value', ...] format needed by cURL.
     *
     * @param array $headers Key-value array of headers.
     * @return array Indexed array of header strings.
     */
    public function format_headers_for_curl(array $headers): array;

    /**
     * Generate embeddings for the given input text(s).
     *
     * @param string|array $input The input text or array of texts.
     * @param array $api_params Provider-specific API connection parameters.
     * @param array $options Embedding options (model, dimensions, encoding_format, etc.).
     * @return array|WP_Error An array of embedding vectors or WP_Error on failure.
     *                        Example success: ['embeddings' => [[0.1, ...], [0.2, ...]], 'usage' => [...]]
     */
    public function generate_embeddings($input, array $api_params, array $options = []); // NEW
}