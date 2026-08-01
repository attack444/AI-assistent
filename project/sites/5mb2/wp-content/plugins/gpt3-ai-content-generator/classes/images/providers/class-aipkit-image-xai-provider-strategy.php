<?php

namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * xAI Image Generation Provider Strategy.
 *
 * Uses xAI's JSON Images API for both generation and edits.
 */
class AIPKit_Image_XAI_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    private const EDIT_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * @return string|\WP_Error
     */
    private function build_api_url(string $operation, array $api_params)
    {
        $base_url = !empty($api_params['base_url']) ? rtrim((string) $api_params['base_url'], '/') : 'https://api.x.ai';
        $api_version = !empty($api_params['api_version']) ? trim((string) $api_params['api_version'], '/') : 'v1';

        if ($base_url === '') {
            return new WP_Error('xai_image_missing_base_url', __('xAI Base URL is required for images.', 'gpt3-ai-content-generator'));
        }
        if ($api_version === '') {
            return new WP_Error('xai_image_missing_api_version', __('xAI API Version is required for images.', 'gpt3-ai-content-generator'));
        }

        $paths = [
            'generate' => '/images/generations',
            'edit' => '/images/edits',
            'models' => '/image-generation-models',
        ];
        if (!isset($paths[$operation])) {
            return new WP_Error(
                'xai_image_unsupported_operation',
                sprintf(
                    /* translators: %s: Operation name. */
                    __('Operation "%s" is not supported for xAI images.', 'gpt3-ai-content-generator'),
                    esc_html($operation)
                )
            );
        }

        $version_segment = '/' . $api_version;
        if (strpos($base_url, $version_segment) !== false) {
            return $base_url . $paths[$operation];
        }

        return $base_url . $version_segment . $paths[$operation];
    }

    private function map_size_to_aspect_ratio(string $size): ?string
    {
        $size = strtolower(trim($size));
        if ($size === '') {
            return null;
        }

        $size_map = [
            '1024x1024' => '1:1',
            '512x512' => '1:1',
            '256x256' => '1:1',
            '1536x1024' => '3:2',
            '1024x1536' => '2:3',
            '1024x768' => '4:3',
            '768x1024' => '3:4',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            '1920x1080' => '16:9',
            '1080x1920' => '9:16',
        ];

        return $size_map[$size] ?? null;
    }

    private function normalize_resolution($value): string
    {
        $resolution = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($resolution, ['1k', '2k'], true) ? $resolution : '';
    }

    private function parse_xai_error_response($response_body, int $status_code, string $context): string
    {
        $parser_function = 'WPAICG\\Core\\Providers\\XAI\\Methods\\xai_parse_error_response_body';
        if (!function_exists($parser_function) && defined('WPAICG_PLUGIN_DIR')) {
            $parser_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/xai/methods.php';
            if (file_exists($parser_path)) {
                require_once $parser_path;
            }
        }

        if (function_exists($parser_function)) {
            return $parser_function(
                $response_body,
                $status_code,
                sprintf(
                    /* translators: %s: Provider context. */
                    __('An unknown error occurred with %s.', 'gpt3-ai-content-generator'),
                    $context
                )
            );
        }

        if ($status_code === 429) {
            return __('Rate limit or quota exceeded. Please wait and retry, or check your xAI Console rate limits and billing.', 'gpt3-ai-content-generator');
        }

        return $this->parse_error_response($response_body, $status_code, $context);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    private function build_source_image_payload(array $source_image)
    {
        $mime_type = isset($source_image['mime_type']) ? strtolower(sanitize_text_field((string) $source_image['mime_type'])) : '';
        if ($mime_type === 'image/jpg') {
            $mime_type = 'image/jpeg';
        }
        if ($mime_type === '' || !in_array($mime_type, self::EDIT_ALLOWED_MIME_TYPES, true)) {
            return new WP_Error(
                'xai_image_edit_invalid_mime_type',
                __('Selected source image format is not supported for xAI edit mode. Allowed: PNG, JPG.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        $base64_data = isset($source_image['base64_data']) ? preg_replace('/\s+/', '', (string) $source_image['base64_data']) : '';
        if (!is_string($base64_data) || $base64_data === '') {
            return new WP_Error(
                'xai_image_edit_missing_source_data',
                __('Source image is required for xAI edit mode.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        $decoded_binary = base64_decode($base64_data, true);
        if (!is_string($decoded_binary) || $decoded_binary === '') {
            return new WP_Error(
                'xai_image_edit_invalid_source_data',
                __('Invalid source image payload for xAI edit mode.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        return [
            'type' => 'image_url',
            'url' => 'data:' . $mime_type . ';base64,' . $base64_data,
        ];
    }

    /**
     * @return mixed[]|\WP_Error
     */
    private function build_payload(string $prompt, array $options, string $image_mode)
    {
        $model = isset($options['model']) ? sanitize_text_field((string) $options['model']) : '';
        if ($model === '') {
            return new WP_Error('xai_image_missing_model', __('xAI image model is required.', 'gpt3-ai-content-generator'));
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
        ];

        if ($image_mode === 'edit') {
            $source_image = isset($options['source_image']) && is_array($options['source_image'])
                ? $options['source_image']
                : null;
            if (!is_array($source_image)) {
                return new WP_Error(
                    'xai_image_edit_missing_source',
                    __('Source image is required for xAI edit mode.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $image_payload = $this->build_source_image_payload($source_image);
            if (is_wp_error($image_payload)) {
                return $image_payload;
            }
            $payload['image'] = $image_payload;
        } else {
            $n = isset($options['n']) ? absint($options['n']) : 1;
            $payload['n'] = max(1, min($n, 10));
        }

        $aspect_ratio = '';
        if (!empty($options['aspect_ratio']) && is_string($options['aspect_ratio'])) {
            $aspect_ratio = sanitize_text_field($options['aspect_ratio']);
        } elseif ($image_mode !== 'edit' && !empty($options['size']) && is_string($options['size'])) {
            $aspect_ratio = $this->map_size_to_aspect_ratio($options['size']) ?? '';
        }
        if ($aspect_ratio !== '') {
            $payload['aspect_ratio'] = $aspect_ratio;
        }

        $resolution = '';
        if (isset($options['resolution'])) {
            $resolution = $this->normalize_resolution($options['resolution']);
        } elseif (isset($options['image_size'])) {
            $resolution = $this->normalize_resolution($options['image_size']);
        }
        if ($resolution !== '') {
            $payload['resolution'] = $resolution;
        }

        $response_format = isset($options['response_format']) ? sanitize_text_field((string) $options['response_format']) : 'b64_json';
        $payload['response_format'] = $response_format === 'url' ? 'url' : 'b64_json';

        return $payload;
    }

    /**
     * @param array<string, mixed> $usage
     * @param int $image_count
     * @return array<string, mixed>
     */
    private function normalize_usage(array $usage, int $image_count): array
    {
        $normalized = [
            'unit_count' => $image_count,
            'image_count' => $image_count,
            'total_units' => $image_count,
            'provider_raw' => $usage,
        ];

        $input_tokens = isset($usage['input_tokens']) ? absint($usage['input_tokens']) : absint($usage['prompt_tokens'] ?? 0);
        $output_tokens = isset($usage['output_tokens']) ? absint($usage['output_tokens']) : absint($usage['completion_tokens'] ?? 0);
        $total_tokens = isset($usage['total_tokens']) ? absint($usage['total_tokens']) : 0;
        if ($total_tokens <= 0 && ($input_tokens > 0 || $output_tokens > 0)) {
            $total_tokens = $input_tokens + $output_tokens;
        }

        if ($input_tokens > 0) {
            $normalized['input_tokens'] = $input_tokens;
        }
        if ($output_tokens > 0) {
            $normalized['output_tokens'] = $output_tokens;
        }
        if ($total_tokens > 0) {
            $normalized['total_tokens'] = $total_tokens;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $decoded_response
     * @param string $prompt
     * @return array<string, mixed>
     */
    private function parse_response(array $decoded_response, string $prompt): array
    {
        $images = [];
        $data = isset($decoded_response['data']) && is_array($decoded_response['data'])
            ? $decoded_response['data']
            : [];

        foreach ($data as $image_data) {
            if (!is_array($image_data)) {
                continue;
            }

            $image_item = [
                'url' => null,
                'b64_json' => null,
                'mime_type' => null,
                'revised_prompt' => $image_data['revised_prompt'] ?? null,
            ];

            if (!empty($image_data['b64_json']) && is_string($image_data['b64_json'])) {
                $image_item['b64_json'] = $image_data['b64_json'];
                $image_item['mime_type'] = 'image/png';
            } elseif (!empty($image_data['url']) && is_string($image_data['url'])) {
                $image_url = $image_data['url'];
                if (preg_match('#^data:(image/[^;]+);base64,#i', $image_url, $matches) === 1) {
                    $image_item['b64_json'] = (string) substr($image_url, strpos($image_url, ',') + 1);
                    $image_item['mime_type'] = strtolower(sanitize_text_field((string) $matches[1]));
                    $image_item['url'] = null;
                } else {
                    $image_item['url'] = esc_url_raw($image_url);
                }
            }

            if ($image_item['url'] === null && $image_item['b64_json'] === null) {
                continue;
            }

            if ($image_item['revised_prompt'] === null && isset($image_data['prompt']) && is_string($image_data['prompt'])) {
                $image_item['revised_prompt'] = $image_data['prompt'];
            }
            if ($image_item['revised_prompt'] === null && $prompt !== '') {
                $image_item['revised_prompt'] = $prompt;
            }
            if (array_key_exists('respect_moderation', $image_data)) {
                $image_item['respect_moderation'] = (bool) $image_data['respect_moderation'];
            }

            $images[] = $image_item;
        }

        $usage_raw = isset($decoded_response['usage']) && is_array($decoded_response['usage'])
            ? $decoded_response['usage']
            : [];
        if (isset($decoded_response['cost_in_usd_ticks']) && !isset($usage_raw['cost_in_usd_ticks'])) {
            $usage_raw['cost_in_usd_ticks'] = $decoded_response['cost_in_usd_ticks'];
        }
        if (isset($decoded_response['model']) && !isset($usage_raw['model'])) {
            $usage_raw['model'] = $decoded_response['model'];
        }

        return [
            'images' => $images,
            'usage' => $this->normalize_usage($usage_raw, count($images)),
        ];
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $api_key = isset($api_params['api_key']) ? sanitize_text_field((string) $api_params['api_key']) : '';
        $clean_prompt = AIPKit_Prompt_Sanitizer::sanitize($prompt);
        $image_mode = isset($options['image_mode']) && $options['image_mode'] === 'edit' ? 'edit' : 'generate';

        if ($api_key === '') {
            return new WP_Error('xai_image_missing_key', __('xAI API Key is required for image generation.', 'gpt3-ai-content-generator'));
        }
        if ($clean_prompt === '') {
            return new WP_Error('xai_image_missing_prompt', __('Prompt cannot be empty for image generation.', 'gpt3-ai-content-generator'));
        }

        $payload = $this->build_payload($clean_prompt, $options, $image_mode);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $url = $this->build_api_url($image_mode, $api_params);
        if (is_wp_error($url)) {
            return $url;
        }

        $request_args = array_merge($this->get_request_options($image_mode), [
            'headers' => $this->get_api_headers($api_key, $image_mode),
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ]);

        $response = wp_remote_post($url, $request_args);
        if (is_wp_error($response)) {
            return new WP_Error('xai_image_http_error', __('HTTP error during xAI image generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = $this->decode_json($body, 'xAI Image Generation');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_message = is_wp_error($decoded_response)
                ? $decoded_response->get_error_message()
                : $this->parse_xai_error_response($body, $status_code, 'xAI Image');
            return new WP_Error(
                'xai_image_api_error',
                sprintf(
                    /* translators: %1$d: HTTP status code, %2$s: API error message. */
                    __('xAI Image API Error (%1$d): %2$s', 'gpt3-ai-content-generator'),
                    $status_code,
                    $error_message
                ),
                ['status' => $status_code]
            );
        }

        $parsed = $this->parse_response($decoded_response, $clean_prompt);
        if (empty($parsed['images'])) {
            return new WP_Error('xai_image_no_data', __('xAI API returned success but no image data was found.', 'gpt3-ai-content-generator'));
        }

        return $parsed;
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params)
    {
        $api_key = isset($api_params['api_key']) ? sanitize_text_field((string) $api_params['api_key']) : '';
        if ($api_key === '') {
            return new WP_Error('xai_image_models_missing_key', __('xAI API Key is required to sync image models.', 'gpt3-ai-content-generator'));
        }

        $url = $this->build_api_url('models', $api_params);
        if (is_wp_error($url)) {
            return $url;
        }

        $options = array_merge($this->get_request_options('models'), [
            'method' => 'GET',
            'headers' => $this->get_api_headers($api_key, 'models'),
        ]);
        unset($options['body']);

        $response = wp_remote_get($url, $options);
        if (is_wp_error($response)) {
            return new WP_Error('xai_image_models_http_error', __('HTTP error during xAI image model sync.', 'gpt3-ai-content-generator'));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = $this->decode_json($body, 'xAI Image Models');

        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_message = is_wp_error($decoded_response)
                ? $decoded_response->get_error_message()
                : $this->parse_xai_error_response($body, $status_code, 'xAI Image Models');
            return new WP_Error(
                'xai_image_models_api_error',
                sprintf(
                    /* translators: %1$d: HTTP status code, %2$s: API error message. */
                    __('xAI Image Models API Error (%1$d): %2$s', 'gpt3-ai-content-generator'),
                    $status_code,
                    $error_message
                ),
                ['status' => $status_code]
            );
        }

        $raw_models = [];
        if (isset($decoded_response['models']) && is_array($decoded_response['models'])) {
            $raw_models = $decoded_response['models'];
        } elseif (isset($decoded_response['data']) && is_array($decoded_response['data'])) {
            $raw_models = $decoded_response['data'];
        } elseif (self::is_list_array($decoded_response)) {
            $raw_models = $decoded_response;
        }

        $formatted = [];
        foreach ($raw_models as $model) {
            if (is_string($model)) {
                $formatted[] = ['id' => $model, 'name' => $model];
                continue;
            }
            if (!is_array($model)) {
                continue;
            }

            $id = $model['id'] ?? $model['model'] ?? $model['name'] ?? null;
            if (!is_string($id) || trim($id) === '') {
                continue;
            }

            $name = $model['name'] ?? $model['display_name'] ?? $id;
            $item = [
                'id' => $id,
                'name' => is_string($name) && trim($name) !== '' ? $name : $id,
            ];
            foreach (['aliases', 'created', 'fingerprint', 'image_price', 'input_modalities', 'max_prompt_length', 'output_modalities', 'owned_by', 'version'] as $metadata_key) {
                if (array_key_exists($metadata_key, $model)) {
                    $item[$metadata_key] = $model[$metadata_key];
                }
            }
            $formatted[] = $item;
        }

        usort($formatted, static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $formatted;
    }

    public function get_supported_sizes(): array
    {
        return ['1024x1024', '1536x1024', '1024x1536', '1024x768', '768x1024', '1792x1024', '1024x1792'];
    }

    public function get_request_options(string $operation): array
    {
        $options = parent::get_request_options($operation);
        $options['timeout'] = $operation === 'models' ? 60 : 180;
        return $options;
    }

    public function get_api_headers(string $api_key, string $operation): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];
    }

    private static function is_list_array(array $array): bool
    {
        $expected_key = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $expected_key) {
                return false;
            }
            $expected_key++;
        }
        return true;
    }
}
