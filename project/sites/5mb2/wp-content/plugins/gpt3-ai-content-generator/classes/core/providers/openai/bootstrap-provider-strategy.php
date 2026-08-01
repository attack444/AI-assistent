<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Core\Providers\OpenAI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/methods.php';

class OpenAIUrlBuilder {

    const MODERATION_ENDPOINT = '/moderations';
    const SPEECH_ENDPOINT = '/audio/speech';
    const TRANSCRIPTION_ENDPOINT = '/audio/transcriptions';
    const IMAGES_ENDPOINT = '/images/generations';
    const FILES_ENDPOINT = '/files';
    const EMBEDDINGS_ENDPOINT = '/embeddings';
    const VECTOR_STORES_ENDPOINT = '/vector_stores';

    /**
     * @return string|\WP_Error
     */
    public static function build(string $operation, array $params) {
        return Methods\build_logic_for_url_builder($operation, $params);
    }
}

class OpenAIPayloadFormatter {

    public static function format_chat(
        string $instructions,
        array $history,
        array $ai_params,
        string $model,
        bool $use_openai_conversation_state = false,
        ?string $previous_response_id = null
    ): array {
        return Methods\format_chat_logic_for_payload_formatter($instructions, $history, $ai_params, $model, $use_openai_conversation_state, $previous_response_id);
    }

    public static function format_sse(
        array $messages,
        $system_instruction,
        array $ai_params,
        string $model,
        bool $use_openai_conversation_state = false,
        ?string $previous_response_id = null
    ): array {
        return Methods\format_sse_logic_for_payload_formatter($messages, $system_instruction, $ai_params, $model, $use_openai_conversation_state, $previous_response_id);
    }

    public static function format_moderation(string $text): array {
        return Methods\format_moderation_logic_for_payload_formatter($text);
    }

    public static function format_embeddings($input, array $options): array {
        return Methods\format_embeddings_logic_for_payload_formatter($input, $options);
    }
}

class OpenAIResponseParser {

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

    public static function parse_moderation(array $decoded_response): bool {
        return Methods\parse_moderation_logic_for_response_parser($decoded_response);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public static function parse_embeddings(array $decoded_response) {
        return Methods\parse_embeddings_logic_for_response_parser($decoded_response);
    }
}

class OpenAIStatefulConversationHelper {

    public static function prepare_parameters_and_history(
        array $ai_params,
        array $history,
        array $bot_settings,
        ?string $frontend_previous_openai_response_id
    ): array {
        return Methods\prepare_parameters_and_history_logic($ai_params, $history, $bot_settings, $frontend_previous_openai_response_id);
    }
}

namespace WPAICG\Core\Providers;

use WP_Error;

if (!class_exists(BaseProviderStrategy::class)) {
    $base_strategy_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/base-provider-strategy.php';
    if (file_exists($base_strategy_path)) {
        require_once $base_strategy_path;
    } else {
        return;
    }
}

class OpenAIProviderStrategy extends BaseProviderStrategy {

    /**
     * @return string|\WP_Error
     */
    public function build_api_url(string $operation, array $params) {
        return OpenAI\Methods\build_api_url_logic($this, $operation, $params);
    }

    public function get_api_headers(string $api_key, string $operation): array {
        return OpenAI\Methods\get_api_headers_logic($this, $api_key, $operation);
    }

    public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array {
        return OpenAI\Methods\format_chat_payload_logic($this, $user_message, $instructions, $history, $ai_params, $model);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function parse_chat_response(array $decoded_response, array $request_data) {
        return OpenAI\Methods\parse_chat_response_logic($this, $decoded_response, $request_data);
    }

    public function parse_error_response($response_body, int $status_code): string {
        return OpenAI\Methods\parse_error_response_logic($this, $response_body, $status_code);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params) {
        return OpenAI\Methods\get_models_logic($this, $api_params);
    }

    public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array {
        return OpenAI\Methods\build_sse_payload_logic($this, $messages, $system_instruction, $ai_params, $model);
    }

    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        return OpenAI\Methods\parse_sse_chunk_logic($this, $sse_chunk, $current_buffer);
    }

    /**
     * @return bool|\WP_Error
     */
    public function moderate_text(string $text, array $api_params) {
        return OpenAI\Methods\moderate_text_logic($this, $text, $api_params);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_embeddings($input, array $api_params, array $options = []) {
        return OpenAI\Methods\generate_embeddings_logic($this, $input, $api_params, $options);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function decode_json_public(string $json_string, string $context) {
        return $this->decode_json($json_string, $context);
    }
}
