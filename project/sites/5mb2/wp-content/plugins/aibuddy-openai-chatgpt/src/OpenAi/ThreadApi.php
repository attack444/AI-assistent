<?php

namespace AiBuddy\OpenAi;

use Exception;

class ThreadApi {
    private string $api_key;
    private string $base_url = 'https://api.openai.com/v1';

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }

    private function make_request(string $method, string $endpoint, array $data = null): array {
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2'
        ];

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 120
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception(esc_html($response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            throw new Exception(esc_html("OpenAI API Error ($status_code): $message"));
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI API');
        }

        return $decoded;
    }

    public function create_thread(array $data = []): array {
        return $this->make_request('POST', '/threads', $data);
    }

    public function list_thread_messages(string $thread_id): array {
        return $this->make_request('GET', "/threads/$thread_id/messages");
    }

    public function create_thread_message(string $thread_id, array $data): array {
        return $this->make_request('POST', "/threads/$thread_id/messages", $data);
    }

    public function create_thread_run(string $thread_id, array $data): array {
        return $this->make_request('POST', "/threads/$thread_id/runs", $data);
    }

    public function list_thread_runs(string $thread_id): array {
        return $this->make_request('GET', "/threads/$thread_id/runs");
    }

    public function create_streamed_run(string $thread_id, array $data): array {
        // Validate assistant_id is provided
        if (empty($data['assistant_id'])) {
            throw new Exception('Assistant ID is required to create a run');
        }
        
        // For now, we'll do a simple non-streamed run and simulate streaming behavior
        // This can be enhanced later to support true streaming if needed
        try {
            $run_data = $this->create_thread_run($thread_id, $data);
        } catch (Exception $e) {
            throw new Exception(esc_html('Failed to create thread run: ' . $e->getMessage()));
        }
        
        // Poll until completion
        $max_attempts = 60; // 60 seconds max wait
        $attempts = 0;
        
        do {
            sleep(1);
            try {
                $runs = $this->list_thread_runs($thread_id);
                $current_run = $runs['data'][0] ?? null;
            } catch (Exception $e) {
                throw new Exception(esc_html('Failed to check run status: ' . $e->getMessage()));
            }
            $attempts++;
        } while ($current_run && $current_run['status'] !== 'completed' && $current_run['status'] !== 'failed' && $attempts < $max_attempts);
        
        if (!$current_run) {
            throw new Exception('No run found for thread');
        }
        
        if ($current_run['status'] === 'failed') {
            $error_message = 'Run failed';
            if (isset($current_run['last_error']['message'])) {
                $error_message .= ': ' . $current_run['last_error']['message'];
            }
            throw new Exception(esc_html($error_message));
        }
        
        if ($attempts >= $max_attempts) {
            throw new Exception('Run timed out after 60 seconds');
        }
        
        if ($current_run['status'] === 'completed') {
            try {
                $messages = $this->list_thread_messages($thread_id);
                $latest_message = $messages['data'][0] ?? null;
                
                if ($latest_message && $latest_message['role'] === 'assistant') {
                    return [
                        'event' => 'thread.message.completed',
                        'data' => [
                            'content' => $latest_message['content'],
                            'status' => 'completed'
                        ]
                    ];
                } else {
                    throw new Exception('No assistant response found in thread messages');
                }
            } catch (Exception $e) {
                throw new Exception(esc_html('Failed to retrieve thread messages: ' . $e->getMessage()));
            }
        }
        
        throw new Exception(esc_html('Run completed with unexpected status: ' . ($current_run['status'] ?? 'unknown')));
    }
} 