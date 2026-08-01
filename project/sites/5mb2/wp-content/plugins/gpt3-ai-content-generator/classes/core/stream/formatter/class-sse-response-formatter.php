<?php

namespace WPAICG\Core\Stream\Formatter;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic files
require_once __DIR__ . '/methods.php';

/**
 * Formats and sends Server-Sent Events (SSE) to the client.
 */
class SSEResponseFormatter {

    private $headers_sent = false;
    private $preamble_sent = false;

    public function set_sse_headers() {
        set_sse_headers_logic($this);
    }

    public function send_sse_data($data, ?string $id = null) {
        send_sse_data_logic($this, $data, $id);
    }

    public function send_sse_event(string $event_type, $data, ?string $id = null) {
        send_sse_event_logic($this, $event_type, $data, $id);
    }

    public function send_sse_error(string $message, bool $non_fatal = false, array $extra_data = []) {
        send_sse_error_logic($this, $message, $non_fatal, $extra_data);
    }

    public function send_sse_done() {
        send_sse_done_logic($this);
    }

    public function finish_request(): void {
        finish_sse_request_logic();
    }

    // Public wrapper for the private send_raw logic
    public function send_raw_public_wrapper(string $output): void {
        send_raw_logic($output);
    }

    // Getter and Setter for private property (needed by externalized logic)
    public function get_headers_sent_status(): bool {
        return $this->headers_sent;
    }

    public function set_headers_sent_status(bool $status): void {
        $this->headers_sent = $status;
    }

    public function get_preamble_sent_status(): bool {
        return $this->preamble_sent;
    }

    public function set_preamble_sent_status(bool $status): void {
        $this->preamble_sent = $status;
    }
}
