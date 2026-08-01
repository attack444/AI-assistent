<?php

namespace WPAICG\Core\Providers;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists(BaseProviderStrategy::class)) {
    $aipkit_base_strategy_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/base-provider-strategy.php';
    if (file_exists($aipkit_base_strategy_path)) {
        require_once $aipkit_base_strategy_path;
    } else {
        return;
    }
}

require_once __DIR__ . '/methods.php';

class XAIProviderStrategy extends BaseProviderStrategy {
    /**
     * @return string|\WP_Error
     */
    public function build_api_url(string $operation, array $params) {
        return \WPAICG\Core\Providers\XAI\Methods\build_api_url_logic($this, $operation, $params);
    }

    public function get_api_headers(string $api_key, string $operation): array {
        return \WPAICG\Core\Providers\XAI\Methods\get_api_headers_logic($this, $api_key, $operation);
    }

    public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array {
        return \WPAICG\Core\Providers\XAI\Methods\format_chat_payload_logic($this, $user_message, $instructions, $history, $ai_params, $model);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function parse_chat_response(array $decoded_response, array $request_data) {
        return \WPAICG\Core\Providers\XAI\Methods\parse_chat_response_logic($this, $decoded_response, $request_data);
    }

    public function parse_error_response($response_body, int $status_code): string {
        return \WPAICG\Core\Providers\XAI\Methods\parse_error_response_logic($this, $response_body, $status_code);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params) {
        return \WPAICG\Core\Providers\XAI\Methods\get_models_logic($this, $api_params);
    }

    public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array {
        return \WPAICG\Core\Providers\XAI\Methods\build_sse_payload_logic($this, $messages, $system_instruction, $ai_params, $model);
    }

    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        return \WPAICG\Core\Providers\XAI\Methods\parse_sse_chunk_logic($this, $sse_chunk, $current_buffer);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_embeddings($input, array $api_params, array $options = []) {
        return new WP_Error(
            'embeddings_not_supported_xai',
            __('Embedding generation is not supported by the xAI provider strategy.', 'gpt3-ai-content-generator'),
            ['status' => 501]
        );
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function decode_json_public(string $json_string, string $context) {
        return $this->decode_json($json_string, $context);
    }
}
