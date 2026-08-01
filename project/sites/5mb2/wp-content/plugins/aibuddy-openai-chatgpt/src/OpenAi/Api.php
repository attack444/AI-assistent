<?php

namespace AiBuddy\OpenAi;

use AiBuddy\Arr;
use DateTimeInterface;

class Api
{
    public const OPENAI_V1 = 'https://api.openai.com/v1';

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

    public function get_files()
    {
        return $this->request('GET', '/files');
    }

    public function get_file($file_id)
    {
        return $this->request('GET', "/files/$file_id");
    }

    public function get_finetunes()
    {
        $finetunes = $this->request('GET', '/fine_tuning/jobs');
        $finetunes['data'] = array_map(
            function ($finetune) {
                $finetune['suffix'] = $this->get_model_suffix($finetune['fine_tuned_model']);

                return $finetune;
            },
            $finetunes['data'],
        );
        $this->core->update_option('finetune_models', $finetunes['data']);

        return $finetunes;
    }

    public function get_deleted_finetunes()
    {
        $finetunes = $this->request('GET', '/fine_tuning/jobs');
        $deleted_finetunes = [];
        $finetunes['data'] = array_filter(
            $finetunes['data'],
            function ($finetune) use (&$deleted_finetunes) {
                $name = $finetune['fine_tuned_model'];
                $is_succeeded = 'succeeded' === $finetune['status'];
                $exist = true;
                if ($is_succeeded) {
                    try {
                        $finetune = $this->get_model($name);
                    } catch (Exception $e) {
                        $exist = false;
                        $deleted_finetunes[] = $name;
                    }
                }

                return $exist;
            }
        );

        return $deleted_finetunes;
    }

    public function get_models()
    {
        $models = $this->request('GET', '/models');

        return $models;
    }

    public function get_model($model_id)
    {
        return $this->request('GET', "/models/$model_id");
    }

    public function upload_file(UploadFile $file, string $purpose)
    {
        return $this->request(
            'POST',
            '/files',
            [
                'purpose' => $purpose,
                'file' => $file,
            ]
        );
    }

    public function delete_file($file_id)
    {
        return $this->request('DELETE', "/files/$file_id");
    }

    public function cancel_finetune($fine_tune_id)
    {
        return $this->request('POST', "/fine_tuning/jobs/$fine_tune_id/cancel");
    }

    public function delete_finetune($model_id)
    {
        return $this->request('DELETE', "/models/$model_id");
    }

    public function get_file_content($file_id)
    {
        return $this->request('GET', "/files/$file_id/content");
    }

    public function create_finetune($training_file, $model, $suffix, $hyperparams = [])
    {
        $n_epochs = isset($hyperparams[0]['nEpochs'])
            ? (int) $hyperparams[0]['nEpochs']
            : null;
        $batch_size = isset($hyperparams[0]['batchSize'])
            ? (int) $hyperparams[0]['batchSize']
            : null;
        $learn_rate_mult = isset($hyperparams[0]['learnRate'])
            ? (float) $hyperparams[0]['learnRate']
            : null;

        $args = [
            'training_file' => $training_file,
            'model' => $model,
            'suffix' => $suffix,
        ];

        $args['hyperparameters'] = [];

        if ($n_epochs) {
            $args['hyperparameters']['n_epochs'] = $n_epochs;
        }
        if ($batch_size) {
            $args['hyperparameters']['batch_size'] = $batch_size;
        }
        if ($learn_rate_mult) {
            $args['hyperparameters']['learning_rate_multiplier'] = $learn_rate_mult;
        }

        return $this->request(
            'POST',
            '/fine_tuning/jobs',
            $args
        );
    }

    /**
     * @return array<string>
     */
    public function prepare_upload(array $data)
    {
        $boundary = wp_generate_password(12, false);

        $body = '';
        foreach ($data as $name => $value) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"$name\"";
            if ($value instanceof UploadFile) {
                $body .= "; filename=\"{$value->filename}\"\r\n";
                $body .= "Content-Type: application/json\r\n\r\n";
                $body .= $value->content . "\r\n";
            } else {
                $body .= "\r\n\r\n$value\r\n";
            }
        }
        $body .= "--$boundary--\r\n";

        return [$boundary, $body];
    }

    public function request($method, $endpoint, $body = null)
    {
        if (empty($this->api_key)) {
            throw new \RuntimeException('No API key is configured. Please add at least one API key in the plugin settings.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        if (is_array($body)) {
            $has_file = null !== Arr::first(
                    $body,
                    function ($value) {
                        return $value instanceof UploadFile;
                    }
                );

            if ($has_file) {
                [$boundary, $body] = $this->prepare_upload($body);
                $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            } else {
                $headers['Content-Type'] = 'application/json';
                $body = wp_json_encode($body);
            }
        }

        $options = [
            'headers' => $headers,
            'method' => $method,
            'timeout' => 120,
            'body' => $body,
            'sslverify' => false,
        ];

        try {
            $response = wp_remote_request(self::OPENAI_V1 . $endpoint, $options);
            
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
            throw new Exception('OpenAI API Error: ' . esc_html($e->getMessage()));
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
            'model' => 'gpt-3.5-turbo',
        ]);

        try {
            $response = wp_remote_post(self::OPENAI_V1.'/chat/completions', [
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
                elseif ($response_code === 429) {
                    throw new Exception( __( 'Your credit balance is too low to access the OpenAI API. Please go to Plans & Billing to upgrade or purchase credits.', 'aibuddy-openai-chatgpt' ));
             }
                else {
                    throw new Exception( __( 'We encountered a problem registering your OpenAI API key. Please check your API Key.', 'aibuddy-openai-chatgpt' ));
                }

        } catch (Exception $e) {
            throw new Exception('OpenAI API Error: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * @return array<array{"title": string, "description": string, "date": string}>
     * @throws \Exception
     */
    public function get_incidents(DateTimeInterface $after): array
    {
        $url = 'https://status.openai.com/history.rss';
        $response = wp_remote_get($url, ['sslverify' => false]);

        if (is_wp_error($response)) {
            throw new Exception(esc_html($response->get_error_message()));
        }

        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        $incidents = [];

        foreach ($xml->channel->item as $item) {
            $incident_time = strtotime(
                $item->pubDate
            ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

            // assume that it ordered by date
            if ($after->getTimestamp() > $incident_time) {
                break;
            }
            $incidents[] = [
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'date' => $incident_time,
            ];
        }

        return $incidents;
    }

    public function create_completions(array $data)
    {
        return $this->request('POST', '/chat/completions', $data);
    }

    public function create_images(array $data)
    {
        return $this->request('POST', '/images/generations', $data);
    }

    public function create_message_completions(array $data)
    {
        return $this->request('POST', '/chat/completions', $data);
    }

    private function get_model_suffix($model)
    {
        if ($model === null) {
            return 'Unknown';
        }
        
        // If it's a DALL-E model, return the full model name
        if (strpos($model, 'dall-e') === 0) {
            return $model;
        }
        
        // For fine-tuned models, extract the suffix
        preg_match('/:([^:]+)(?=:[^:]+$)/', $model, $matches);
        if (count($matches) > 0) {
            return $matches[1];
        }

        return 'Unknown';
    }
}
