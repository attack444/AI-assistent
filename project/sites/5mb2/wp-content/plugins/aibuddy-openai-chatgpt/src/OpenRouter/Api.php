<?php

namespace AiBuddy\OpenRouter;

use AiBuddy\Arr;
use DateTimeInterface;

class Api
{
    public const OPENROUTER_V1 = 'https://openrouter.ai/api/v1';

    /**
     * @var \AiBuddy\Plugin
     */
    private $core;

    private $api_key;

    public function __construct($core, $api_key)
    {
        $this->core = $core;
        $this->api_key = $api_key;
    }

    public function request($method, $endpoint, $body = null)
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        if (is_array($body)) {
            $headers['Content-Type'] = 'application/json';
            $body = wp_json_encode($body);
        }

        $options = [
            'headers' => $headers,
            'method' => $method,
            'timeout' => 120,
            'body' => $body,
            'sslverify' => false,
        ];

        try {
            $response = wp_remote_request(self::OPENROUTER_V1 . $endpoint, $options);
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);

            if ('application/json' === wp_remote_retrieve_header($response, 'content-type')
                || 'application/json; charset=utf-8' === wp_remote_retrieve_header($response, 'content-type')) {
                $data = json_decode($body, true);
            } else {
                $data = $body;
            }

            if (isset($data['error']) && !is_null($data['error']['error'])) {
                $message = str_replace($this->api_key, str_repeat('*', 6), $data['error']['message']);
                throw new Exception($message);
            }

            return $data;
        } catch (Exception $e) {
            throw new Exception('OpenRouter API Error: ' . esc_html($e->getMessage()));
        }
    }

    public static function check_api_key_validity($api_key) {
        if (empty($api_key)) {
            return false;
        }

        $body = json_encode([
            'messages' =>  [
                [
                    "role" => "user",
                    "content" => "This is a test."
                ]
            ],
            'max_tokens' => 5,
            'model' => 'openai/gpt-4.1',
        ]);

        try {
            $response = wp_remote_post(self::OPENROUTER_V1.'/chat/completions', [
                'body'    => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 200) {
                    return true;
                }
                elseif ($response_code === 402) {
                    throw new Exception( __( 'Your credit balance is too low to access the OpenRouter API. Please go to Plans & Billing to upgrade or purchase credits.', 'aibuddy-openai-chatgpt' ));
             }
                else {
                    throw new Exception( __( 'We encountered a problem registering your OpenRouter API key. Please check your API Key.', 'aibuddy-openai-chatgpt' ));
                }

        } catch (Exception $e) {
            throw new Exception('OpenRouter API Error: ' . esc_html($e->getMessage()));
        }
    }
    

    public function create_completions(array $data)
    {
        return $this->request('POST', '/chat/completions', $data);
    }
}
