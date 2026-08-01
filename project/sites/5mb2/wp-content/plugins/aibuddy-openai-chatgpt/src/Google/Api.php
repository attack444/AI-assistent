<?php

namespace AiBuddy\Google;

use AiBuddy\Arr;
use DateTimeInterface;

class Api {
    public const GoogleAI = 'https://generativelanguage.googleapis.com/v1beta/models/';
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
        $newBody = [
            'contents' => [
                'parts' => [
                    'text'=> $body['messages'][0]['content']
                ]
            ],
            'generationConfig' => [
                'candidateCount' => $body['n'],
                'maxOutputTokens' => $body['max_tokens'],
                'temperature' => $body['temperature']
            ]
        ];
        
        return $newBody;
    }

    public function request( $method, $endpoint, $body = null ) {
        $headers['Content-Type'] = 'application/json';
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
            $response = wp_remote_request( self::GoogleAI . $endpoint . $this->api_key, $options );
            
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
            throw new Exception( 'Google API Error: ' . esc_html($e->getMessage()) );
        }
    }

    public static function check_api_key_validity($api_key) {
        if (empty($api_key)) {
            return false;
        }

        $headers['Content-Type'] = 'application/json';

        $options = array(
            'headers'   => $headers,
            'method'    => 'POST',
            'body'      => json_encode([
                'contents' => [
                    'parts' => [
                        'text'=> "write hello",
                    ]
                ]
            ]),
        );

        try {

            $response = wp_remote_request( self::GoogleAI . 'gemini-1.5-flash:generateContent?key=' . $api_key , $options );

            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 200) {
                return true;
            }
            elseif ($response_code === 429) {
                throw new Exception( __( 'Your credit balance is too low to access the GOOGLEAI API. Please go to Plans & Billing to upgrade or purchase credits.' , 'aibuddy-openai-chatgpt' ));
            }
            else {
                throw new Exception( __( 'We encountered a problem registering your GoogleAI API key. Please check your API Key.' , 'aibuddy-openai-chatgpt' ));
            }

        } catch ( Exception $e) {
            throw new Exception('Google API Error: ' . esc_html($e->getMessage()));
        }
    }

    public function create_completions( array $data, $model = 'gemini-pro' ) {
        return $this->request( 'POST', sprintf('%s:generateContent?key=', sanitize_text_field($model)), $data );
    }
}
