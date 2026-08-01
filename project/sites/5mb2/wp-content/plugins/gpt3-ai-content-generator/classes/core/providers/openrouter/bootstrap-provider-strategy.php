<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Core\Providers\OpenRouter;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/methods.php';

class OpenRouterUrlBuilder {

    /**
     * @return string|\WP_Error
     */
    public static function build(string $operation, array $params) {
        return Methods\build_logic_for_url_builder($operation, $params);
    }
}

class OpenRouterPayloadFormatter {

    public static function format_chat(string $instructions, array $history, array $ai_params, string $model): array {
        return Methods\format_chat_logic_for_payload_formatter($instructions, $history, $ai_params, $model);
    }

    public static function format_sse(array $messages, string $system_instruction, array $ai_params, string $model): array {
        return Methods\format_sse_logic_for_payload_formatter($messages, $system_instruction, $ai_params, $model);
    }
}

class OpenRouterResponseParser {

    /**
     * @return mixed[]|\WP_Error
     */
    public static function parse_chat(array $decoded_response) {
        return Methods\parse_chat_logic_for_response_parser($decoded_response);
    }

    public static function parse_error($response_body, int $status_code): string {
        return Methods\parse_error_logic_for_response_parser($response_body, $status_code);
    }

    public static function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        return Methods\parse_sse_chunk_logic_for_response_parser($sse_chunk, $current_buffer);
    }
}

namespace WPAICG\Core\Providers;

use WP_Error;

class OpenRouterProviderStrategy extends BaseProviderStrategy {

    /**
     * @return string|\WP_Error
     */
    public function build_api_url(string $operation, array $params) {
        return OpenRouter\Methods\build_api_url_logic($this, $operation, $params);
    }

    public function get_api_headers(string $api_key, string $operation): array {
        return OpenRouter\Methods\get_api_headers_logic($this, $api_key, $operation);
    }

    public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array {
        return OpenRouter\Methods\format_chat_payload_logic($this, $user_message, $instructions, $history, $ai_params, $model);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function parse_chat_response(array $decoded_response, array $request_data) {
        return OpenRouter\Methods\parse_chat_response_logic($this, $decoded_response, $request_data);
    }

    public function parse_error_response($response_body, int $status_code): string {
        return OpenRouter\Methods\parse_error_response_logic($this, $response_body, $status_code);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params) {
        return OpenRouter\Methods\get_models_logic($this, $api_params);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_image_models(array $api_params) {
        return OpenRouter\Methods\get_image_models_logic($this, $api_params);
    }

    public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array {
        return OpenRouter\Methods\build_sse_payload_logic($this, $messages, $system_instruction, $ai_params, $model);
    }

    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        return OpenRouter\Methods\parse_sse_chunk_logic($this, $sse_chunk, $current_buffer);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_embeddings($input, array $api_params, array $options = []) {
        return OpenRouter\Methods\generate_embeddings_logic($this, $input, $api_params, $options);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_embedding_models(array $api_params) {
        return OpenRouter\Methods\get_embedding_models_logic($this, $api_params);
    }
}
