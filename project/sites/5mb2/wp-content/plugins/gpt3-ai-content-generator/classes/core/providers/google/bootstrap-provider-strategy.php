<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Core\Providers\Google;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/methods.php';

class GoogleUrlBuilder {

    /**
     * @return string|\WP_Error
     */
    public static function build(string $operation, array $params) {
        return Methods\build_logic_for_url_builder($operation, $params);
    }
}

class GooglePayloadFormatter {

    public static function format_chat(string $instructions, array $history, array $ai_params, string $model): array {
        return Methods\format_chat_logic_for_payload_formatter($instructions, $history, $ai_params, $model);
    }

    public static function format_sse(array $messages, $system_instruction, array $ai_params, string $model): array {
        return Methods\format_sse_logic_for_payload_formatter($messages, $system_instruction, $ai_params, $model);
    }

    public static function format_embeddings($input, array $options): array {
        return Methods\format_embeddings_logic_for_payload_formatter($input, $options);
    }
}

class GoogleResponseParser {

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

    /**
     * @return mixed[]|\WP_Error
     */
    public static function parse_embeddings(array $decoded_response) {
        return Methods\parse_embeddings_logic_for_response_parser($decoded_response);
    }
}

class GoogleSettingsHandler {

    const GOOGLE_TTS_VOICES_OPTION = 'aipkit_google_tts_voice_list';

    private static $default_safety_settings = [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_CIVIC_INTEGRITY', 'threshold' => 'BLOCK_NONE']
    ];

    public static function check_and_init_safety_settings() {
        Methods\check_and_init_safety_settings_logic(self::$default_safety_settings);
    }

    public static function get_safety_settings(): array {
        return Methods\get_safety_settings_logic(self::$default_safety_settings);
    }

    public static function save_safety_settings(array $post_data): bool {
        return Methods\save_safety_settings_logic($post_data, self::$default_safety_settings);
    }

    public static function get_synced_google_tts_voices(): array {
        return Methods\get_synced_google_tts_voices_logic(self::GOOGLE_TTS_VOICES_OPTION);
    }

    public static function ajax_sync_google_tts_voices() {
        Methods\ajax_sync_google_tts_voices_logic(self::GOOGLE_TTS_VOICES_OPTION);
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

class GoogleProviderStrategy extends BaseProviderStrategy {

    /**
     * @return string|\WP_Error
     */
    public function build_api_url(string $operation, array $params) {
        return Google\Methods\build_api_url_logic($this, $operation, $params);
    }

    public function get_api_headers(string $api_key, string $operation): array {
        return Google\Methods\get_api_headers_logic($this, $api_key, $operation);
    }

    public function format_chat_payload(string $user_message, string $instructions, array $history, array $ai_params, string $model): array {
        return Google\Methods\format_chat_payload_logic($this, $user_message, $instructions, $history, $ai_params, $model);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function parse_chat_response(array $decoded_response, array $request_data) {
        return Google\Methods\parse_chat_response_logic($this, $decoded_response, $request_data);
    }

    public function parse_error_response($response_body, int $status_code): string {
        return Google\Methods\parse_error_response_logic($this, $response_body, $status_code);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params) {
        return Google\Methods\get_models_logic($this, $api_params);
    }

    public function build_sse_payload(array $messages, $system_instruction, array $ai_params, string $model): array {
        return Google\Methods\build_sse_payload_logic($this, $messages, $system_instruction, $ai_params, $model);
    }

    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        return Google\Methods\parse_sse_chunk_logic($this, $sse_chunk, $current_buffer);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_embeddings($input, array $api_params, array $options = []) {
        return Google\Methods\generate_embeddings_logic($this, $input, $api_params, $options);
    }

    public function format_google_model_list_public(array $raw_models): array {
        return Google\Methods\format_google_model_list_logic($this, $raw_models);
    }
}
