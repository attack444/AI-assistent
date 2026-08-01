<?php

namespace WPAICG\Core\Stream\Handler;

use WPAICG\Core\Stream\Formatter\SSEResponseFormatter;
use WPAICG\Core\Stream\Request\SSERequestHandler;
use WPAICG\Core\Stream\Processor\SSEStreamProcessor;
use WPAICG\Chat\Storage\LogStorage;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic files
require_once __DIR__ . '/ajax/methods.php';

/**
 * Handles the AJAX request for STREAMING messages using Server-Sent Events (SSE).
 * This class acts as the entry point for the SSE request, orchestrating validation,
 * caching (if needed), and stream processing.
 */
class SSEHandler {

    private $request_handler;
    private $stream_processor;
    private $response_formatter;

    public function __construct() {
        // Dependencies should be loaded by AIPKit_Dependency_Loader.
        // Constructors now assume classes are available.

        $log_storage_instance = class_exists(\WPAICG\Chat\Storage\LogStorage::class)
            ? new \WPAICG\Chat\Storage\LogStorage()
            : null;

        $this->response_formatter = class_exists(\WPAICG\Core\Stream\Formatter\SSEResponseFormatter::class)
            ? new \WPAICG\Core\Stream\Formatter\SSEResponseFormatter()
            : null;

        $this->request_handler = ($log_storage_instance && class_exists(\WPAICG\Core\Stream\Request\SSERequestHandler::class))
            ? new \WPAICG\Core\Stream\Request\SSERequestHandler($log_storage_instance)
            : null;

        $this->stream_processor = ($this->response_formatter && $log_storage_instance && class_exists(\WPAICG\Core\Stream\Processor\SSEStreamProcessor::class))
            ? new \WPAICG\Core\Stream\Processor\SSEStreamProcessor($this->response_formatter, $log_storage_instance)
            : null;
    }

    // Getters for externalized logic
    public function get_response_formatter(): ?SSEResponseFormatter { return $this->response_formatter; }
    public function get_request_handler(): ?SSERequestHandler { return $this->request_handler; }
    public function get_stream_processor(): ?SSEStreamProcessor { return $this->stream_processor; }


    public function ajax_cache_sse_message() {
        // Ensure dependencies are met before calling logic
        if ($this->response_formatter) { // Check one, assuming others fine if this one is
            \WPAICG\Core\Stream\Handler\Ajax\ajax_cache_sse_message_logic($this);
        } else {
             wp_send_json_error(['message' => __('SSE service not ready.', 'gpt3-ai-content-generator')], 503);
        }
    }

    public function ajax_frontend_chat_stream() {
        // Ensure dependencies are met before calling logic
        if ($this->response_formatter && $this->request_handler && $this->stream_processor) {
            \WPAICG\Core\Stream\Handler\Ajax\ajax_frontend_chat_stream_logic($this);
        } else {
            // Attempt to send an SSE error if possible, otherwise just exit.
            if ($this->response_formatter) {
                $this->response_formatter->set_sse_headers();
                $this->response_formatter->send_sse_error(__('SSE service components not fully initialized.', 'gpt3-ai-content-generator'));
            }
            exit;
        }
    }
}