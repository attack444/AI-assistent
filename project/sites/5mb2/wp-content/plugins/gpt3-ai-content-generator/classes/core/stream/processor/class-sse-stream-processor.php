<?php

namespace WPAICG\Core\Stream\Processor;

use WPAICG\Core\Stream\Formatter\SSEResponseFormatter;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\Providers\ProviderStrategyInterface;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;

// Load stream processor logic files.
require_once __DIR__ . '/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the actual SSE connection, chunked streaming, and final logging of the bot response.
 * Uses Provider Strategies for provider-specific logic.
 * This class now mainly holds state and delegates the main stream starting logic.
 */
class SSEStreamProcessor {

    private $formatter;
    private $log_storage;
    private ?AIPKit_Token_Manager $token_manager = null;
    private ?ProviderStrategyInterface $strategy = null;

    // State (made public or with getters/setters for externalized logic)
    public $current_provider = null;
    public $current_model    = null;
    public $current_conversation_uuid = null;
    public $current_bot_message_id = null;
    public $incomplete_sse_buffer  = '';
    public $curl_callback_invoked  = false;
    public $curl_chunk_counter     = 0;
    public $data_sent_to_frontend  = false;
    public $full_bot_response      = '';
    public $final_usage_data       = null;
    public $log_base_data          = [];
    public $error_occurred         = false;
    public $request_payload_log    = null;
    public $current_openai_response_id = null;
    public $used_previous_openai_response_id = false;
    public $grounding_metadata = null;
    public $citations = null;
    public $current_stream_context = 'chat';
    public $vector_search_scores = []; // Store vector search scores for logging

    public function __construct(SSEResponseFormatter $formatter, LogStorage $log_storage) {
        $this->formatter   = $formatter;
        $this->log_storage = $log_storage;
        if (!class_exists(\WPAICG\Core\TokenManager\AIPKit_Token_Manager::class)) {
            $token_manager_path = WPAICG_PLUGIN_DIR . 'classes/core/token-manager/AIPKit_Token_Manager.php';
            if (file_exists($token_manager_path)) {
                require_once $token_manager_path;
            }
        }
        if (class_exists(\WPAICG\Core\TokenManager\AIPKit_Token_Manager::class)) {
            $this->token_manager = new \WPAICG\Core\TokenManager\AIPKit_Token_Manager();
        }
    }

    // --- Getters and Setters for state properties ---
    public function get_log_storage(): LogStorage { return $this->log_storage; }
    public function get_token_manager(): ?AIPKit_Token_Manager { return $this->token_manager; }
    public function get_formatter(): SSEResponseFormatter { return $this->formatter; }
    public function get_strategy(): ?ProviderStrategyInterface { return $this->strategy; }
    public function set_strategy(ProviderStrategyInterface $strategy): void { $this->strategy = $strategy; }
    public function get_current_provider(): ?string { return $this->current_provider; }
    public function get_current_model(): ?string { return $this->current_model; }
    public function get_current_conversation_uuid(): ?string { return $this->current_conversation_uuid; }
    public function get_current_bot_message_id(): ?string { return $this->current_bot_message_id; }
    public function get_incomplete_sse_buffer(): string { return $this->incomplete_sse_buffer; }
    public function set_incomplete_sse_buffer(string $buffer): void { $this->incomplete_sse_buffer = $buffer; }
    public function append_to_incomplete_sse_buffer(string $chunk): void { $this->incomplete_sse_buffer .= $chunk; }
    public function get_curl_callback_invoked_status(): bool { return $this->curl_callback_invoked; }
    public function set_curl_callback_invoked_status(bool $status): void { $this->curl_callback_invoked = $status; }
    public function get_curl_chunk_counter(): int { return $this->curl_chunk_counter; }
    public function increment_curl_chunk_counter(): void { $this->curl_chunk_counter++; }
    public function get_data_sent_to_frontend_status(): bool { return $this->data_sent_to_frontend; }
    public function set_data_sent_to_frontend_status(bool $status): void { $this->data_sent_to_frontend = $status; }
    public function get_full_bot_response(): string { return $this->full_bot_response; }
    public function append_to_full_bot_response(string $delta): void { $this->full_bot_response .= $delta; }
    public function get_final_usage_data(): ?array { return $this->final_usage_data; }
    public function set_final_usage_data(?array $data): void { $this->final_usage_data = $data; }
    public function get_log_base_data(): array { return $this->log_base_data; }
    public function get_error_occurred_status(): bool { return $this->error_occurred; }
    public function set_error_occurred_status(bool $status): void { $this->error_occurred = $status; }
    public function get_request_payload_log(): ?array { return $this->request_payload_log; }
    public function set_request_payload_log(?array $payload): void { $this->request_payload_log = $payload; }
    public function get_current_openai_response_id(): ?string { return $this->current_openai_response_id; }
    public function set_current_openai_response_id(?string $id): void { $this->current_openai_response_id = $id; }
    public function get_used_previous_openai_response_id_status(): bool { return $this->used_previous_openai_response_id; }
    public function get_grounding_metadata(): ?array { return $this->grounding_metadata; }
    public function set_grounding_metadata(?array $metadata): void { $this->grounding_metadata = $metadata; }
    public function get_citations(): ?array { return $this->citations; }
    public function append_citations(array $citations): void {
        if ($this->citations === null) {
            $this->citations = [];
        }

        $seen = [];
        foreach ($this->citations as $existing_citation) {
            $encoded = wp_json_encode($existing_citation);
            if (is_string($encoded)) {
                $seen[$encoded] = true;
            }
        }

        foreach ($citations as $citation) {
            if (!is_array($citation) || empty($citation)) {
                continue;
            }

            $encoded = wp_json_encode($citation);
            if (!is_string($encoded) || isset($seen[$encoded])) {
                continue;
            }

            $seen[$encoded] = true;
            $this->citations[] = $citation;
        }
    }
    public function get_current_stream_context(): string { return $this->current_stream_context; }
    public function get_vector_search_scores(): array { 
        return $this->vector_search_scores; 
    }
    public function set_vector_search_scores(array $scores): void { 
        $this->vector_search_scores = $scores; 
    }

    public function initialize_stream_state(string $provider, string $model, string $conversation_uuid, ?string $bot_message_id, array $base_log_data, string $stream_context, bool $used_previous_openai_id): void {
        $this->current_provider = $provider; $this->current_model = $model;
        $this->current_conversation_uuid = $conversation_uuid; $this->current_bot_message_id = $bot_message_id;
        $this->incomplete_sse_buffer = ''; $this->curl_callback_invoked = false; $this->curl_chunk_counter = 0;
        $this->data_sent_to_frontend = false; $this->full_bot_response = ''; $this->final_usage_data = null;
        $this->log_base_data = $base_log_data; $this->current_stream_context = $stream_context;
        $this->error_occurred = false; $this->request_payload_log = null; $this->current_openai_response_id = null;
        $this->used_previous_openai_response_id = $used_previous_openai_id; $this->grounding_metadata = null; $this->citations = null;
        // NOTE: vector_search_scores should NOT be reset here as they are set before streaming starts
    }
    // --- End Getters and Setters ---

    /**
     * Main entry point to start the streaming process.
     * Now delegates to the namespaced start_stream_logic function.
     */
    public function start_stream(string $provider, string $model, string $user_message, array $history, string $system_instruction_filtered, array $api_params, array $ai_params, string $conversation_uuid, array $base_log_data) {
        if (!function_exists(__NAMESPACE__ . '\start_stream_logic')) {
            if ($this->formatter) {
                $this->formatter->send_sse_error(__('Critical error: Stream processing cannot start.', 'gpt3-ai-content-generator'));
                $this->formatter->send_sse_done();
            }
            exit;
        }
        start_stream_logic($this, $provider, $model, $user_message, $history, $system_instruction_filtered, $api_params, $ai_params, $conversation_uuid, $base_log_data);
    }

    public function curl_stream_callback_public_wrapper($ch, string $chunk): int {
        if (!function_exists(__NAMESPACE__ . '\curl_stream_callback_logic')) {
            return 0; 
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_stream_callback_logic -- Reason: Using cURL for streaming.
        return curl_stream_callback_logic($this, $ch, $chunk);
    }
}
