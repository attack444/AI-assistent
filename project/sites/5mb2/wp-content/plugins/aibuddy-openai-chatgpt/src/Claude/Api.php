<?php

namespace AiBuddy\Claude;

use AiBuddy\Arr;
use DateTimeInterface;

class Api {
    public const ClaudeAI = 'https://api.anthropic.com/v1/';
    /**
     * @var \AiBuddy\Plugin
     */
    private $core;
    private $api_key;

    public function __construct( $core, $api_key ) {
        $this->core    = $core;
        $this->api_key = $api_key;
    }

    /**
     * @return array<string>
     */
    public function prepare_upload( array $data ) {
        $boundary = wp_generate_password( 12, false );

        $body = '';
        foreach ( $data as $name => $value ) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"$name\"";
            if ( $value instanceof UploadFile ) {
                $body .= "; filename=\"{$value->filename}\"\r\n";
                $body .= "Content-Type: application/json\r\n\r\n";
                $body .= $value->content . "\r\n";
            } else {
                $body .= "\r\n\r\n$value\r\n";
            }
        }
        $body .= "--$boundary--\r\n";

        return array( $boundary, $body );
    }
    
    public function messageConverter($body) {
        unset($body['stop']);
        unset($body['n']);
        
        return $body;
    }

    public function request( $method, $endpoint, $body = null ) {
        $headers['Content-Type'] = 'application/json';
        $headers['x-api-key'] = $this->api_key;
        $headers['anthropic-version'] = '2023-06-01';
        $body = $this->messageConverter($body);
        $body = wp_json_encode($body);

        $options = array(
            'headers'   => $headers,
            'method'    => $method,
            'timeout'   => 120,
            'body'      => $body,
            'sslverify' => false,
        );
        
        try {
            $response = wp_remote_request( self::ClaudeAI . $endpoint, $options );
            
            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( isset( $data['error'] ) ) {
                $message = str_replace( $this->api_key, str_repeat( '*', 6 ), $data['error']['message'] );
                throw new Exception( $message );
            }

            return $data;
        } catch ( Exception $e ) {
            throw new Exception( 'Claude API Error: ' . esc_html($e->getMessage()) );
        }
    }

    public static function check_api_key_validity($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        $headers['Content-Type'] = 'application/json';
        $headers['x-api-key'] = $api_key;
        $headers['anthropic-version'] = '2023-06-01';

        $options = array(
            'headers'   => $headers,
            'method'    => 'POST',
            'body'      => json_encode([
                'messages' => [
                    [
                        "role" => "user",
                        'content' => "Write Hello",
                    ]
                ],
                'max_tokens' => 10,
                'model' => 'claude-3-7-sonnet-latest'
            ]),
        );

        try {
            $response = wp_remote_request( self::ClaudeAI . 'messages', $options );
            
            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_code === 200) {
                return true;
            }
            elseif (isset($response_body['error']['message'])) {
                throw new Exception( $response_body['error']['message']);
            }
            else {
                throw new Exception( __( 'We encountered a problem registering your ClaudeAI API key. Please check your API Key.' , 'aibuddy-openai-chatgpt' ));
            }

        } catch ( Exception $e) {
            throw new Exception('Claude API Error: ' . esc_html($e->getMessage()));
        }
    }

    public function create_completions( array $data ) {
        return $this->request( 'POST', 'messages', $data);
    }
}
