<?php

namespace AiBuddy\OpenAi;

use Exception;

class AssistantApi {
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

    public function list_vector_stores(): array {
        return $this->make_request('GET', '/vector_stores');
    }

    public function list_assistants(): array {
        return $this->make_request('GET', '/assistants');
    }


} 