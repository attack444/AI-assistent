<?php

namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenRouter Image Generation Provider Strategy.
 * Uses OpenRouter Chat Completions with model-appropriate image output modalities.
 */
class AIPKit_Image_OpenRouter_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    /**
     * Supported source mime types for OpenRouter edit mode.
     *
     * @var array<int, string>
     */
    private const EDIT_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Build OpenRouter image generation endpoint URL.
     *
     * @param array $api_params Provider api params.
     * @return string|WP_Error
     */
    private function build_api_url(array $api_params)
    {
        $base_url = isset($api_params['base_url']) ? esc_url_raw((string) $api_params['base_url']) : 'https://openrouter.ai/api';
        $api_version = isset($api_params['api_version']) ? sanitize_text_field((string) $api_params['api_version']) : 'v1';

        if ($base_url === '') {
            return new WP_Error('openrouter_image_missing_base_url', __('OpenRouter Base URL is required.', 'gpt3-ai-content-generator'));
        }
        if ($api_version === '') {
            $api_version = 'v1';
        }

        return rtrim($base_url, '/') . '/' . trim($api_version, '/') . '/chat/completions';
    }

    /**
     * Maps WxH values to aspect ratio accepted by OpenRouter image_config.
     *
     * @param string $size Size formatted like "1024x1024".
     * @return string|null
     */
    private function map_size_to_aspect_ratio(string $size): ?string
    {
        $size = strtolower(trim($size));
        if ($size === '') {
            return null;
        }

        $size_map = [
            '1024x1024' => '1:1',
            '1536x1024' => '3:2',
            '1024x1536' => '2:3',
            '1024x768' => '4:3',
            '768x1024' => '3:4',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
        ];

        return $size_map[$size] ?? null;
    }

    /**
     * Check if selected OpenRouter model supports image_config.
     *
     * @param string $model_id Model id.
     * @return bool
     */
    private function model_supports_image_config(string $model_id): bool
    {
        $model_id = sanitize_text_field($model_id);
        if ($model_id === '') {
            return false;
        }

        $resolver_fn = '\WPAICG\Core\Providers\OpenRouter\Methods\model_supports_image_config_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }

        if (!function_exists($resolver_fn)) {
            return false;
        }

        return (bool) call_user_func($resolver_fn, $model_id);
    }

    /**
     * @return array<int, string>
     */
    private function get_model_output_modalities(string $model_id): array
    {
        $model_id = sanitize_text_field($model_id);
        if ($model_id === '') {
            return [];
        }

        $resolver_fn = '\WPAICG\Core\Providers\OpenRouter\Methods\model_output_modalities_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }

        if (!function_exists($resolver_fn)) {
            return [];
        }

        $modalities = call_user_func($resolver_fn, $model_id);
        return is_array($modalities) ? $modalities : [];
    }

    /**
     * @return array<int, string>
     */
    private function get_request_modalities(string $model_id): array
    {
        $output_modalities = $this->get_model_output_modalities($model_id);
        if (!empty($output_modalities)) {
            return in_array('text', $output_modalities, true)
                ? ['image', 'text']
                : ['image'];
        }

        $model_id = strtolower($model_id);
        $image_only_prefixes = [
            'black-forest-labs/flux',
            'recraft/',
            'sourceful/riverflow',
            'bytedance-seed/seedream',
        ];
        foreach ($image_only_prefixes as $prefix) {
            if (strpos($model_id, $prefix) === 0) {
                return ['image'];
            }
        }

        return ['image', 'text'];
    }

    private function normalize_aspect_ratio($value, string $model_id): string
    {
        $aspect_ratio = is_string($value) ? sanitize_text_field($value) : '';
        if ($aspect_ratio === '') {
            return '';
        }

        $allowed = ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'];
        if (strpos(strtolower($model_id), 'google/gemini-3.1-flash-image') !== false) {
            $allowed = array_merge($allowed, ['1:4', '4:1', '1:8', '8:1']);
        }

        return in_array($aspect_ratio, $allowed, true) ? $aspect_ratio : '';
    }

    private function normalize_image_size($value, string $model_id): string
    {
        $image_size = is_string($value) ? strtolower(sanitize_text_field($value)) : '';
        if ($image_size === '') {
            return '';
        }

        $allowed = ['1k', '2k', '4k'];
        if (strpos(strtolower($model_id), 'google/gemini-3.1-flash-image') !== false) {
            $allowed[] = '0.5k';
        }

        return in_array($image_size, $allowed, true) ? strtoupper($image_size) : '';
    }

    /**
     * Check if selected OpenRouter model supports image output.
     *
     * @param string $model_id Model id.
     * @return bool
     */
    private function model_supports_image_output(string $model_id): bool
    {
        $model_id = sanitize_text_field($model_id);
        if ($model_id === '') {
            return false;
        }

        $resolver_fn = '\WPAICG\Core\Providers\OpenRouter\Methods\model_supports_image_output_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }

        if (!function_exists($resolver_fn)) {
            return true; // Fallback compatibility.
        }

        return (bool) call_user_func($resolver_fn, $model_id);
    }

    /**
     * Check if selected OpenRouter model supports image editing.
     * Image edit requires both image_input and image_output capabilities.
     *
     * @param string $model_id Model id.
     * @return bool
     */
    private function model_supports_image_editing(string $model_id): bool
    {
        $model_id = sanitize_text_field($model_id);
        if ($model_id === '') {
            return false;
        }

        $resolver_fn = '\WPAICG\Core\Providers\OpenRouter\Methods\model_supports_image_editing_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }

        if (!function_exists($resolver_fn)) {
            return true; // Fallback compatibility.
        }

        return (bool) call_user_func($resolver_fn, $model_id);
    }

    /**
     * Build OpenRouter edit-mode user message content.
     *
     * @param string $prompt Prompt text.
     * @param array  $source_image Source image payload from upload parser.
     * @return array|WP_Error
     */
    private function build_edit_user_content(string $prompt, array $source_image)
    {
        $mime_type = isset($source_image['mime_type']) ? strtolower(sanitize_text_field((string) $source_image['mime_type'])) : '';
        if ($mime_type === '' || !in_array($mime_type, self::EDIT_ALLOWED_MIME_TYPES, true)) {
            return new WP_Error(
                'openrouter_image_edit_invalid_mime_type',
                __('Selected source image format is not supported for OpenRouter edit mode. Allowed: PNG, JPG, WEBP, GIF.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        $base64_data = isset($source_image['base64_data']) ? (string) $source_image['base64_data'] : '';
        if ($base64_data === '') {
            return new WP_Error(
                'openrouter_image_edit_missing_source_data',
                __('Source image is required for OpenRouter edit mode.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        $decoded_binary = base64_decode($base64_data, true);
        if (!is_string($decoded_binary) || $decoded_binary === '') {
            return new WP_Error(
                'openrouter_image_edit_invalid_source_data',
                __('Invalid source image payload for OpenRouter edit mode.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
        }

        return [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime_type . ';base64,' . $base64_data,
                ],
            ],
        ];
    }

    /**
     * Parse image blocks from OpenRouter response into storage-friendly format.
     *
     * @param array $decoded_response Decoded OpenRouter response.
     * @return array<int, array<string, mixed>>
     */
    private function parse_images(array $decoded_response): array
    {
        $images = [];
        $choices = isset($decoded_response['choices']) && is_array($decoded_response['choices'])
            ? $decoded_response['choices']
            : [];

        if (empty($choices) || !isset($choices[0]['message']) || !is_array($choices[0]['message'])) {
            return $images;
        }

        $message = $choices[0]['message'];
        $image_blocks = [];

        if (isset($message['images']) && is_array($message['images'])) {
            $image_blocks = $message['images'];
        } elseif (isset($message['content']) && is_array($message['content'])) {
            foreach ($message['content'] as $content_block) {
                if (!is_array($content_block)) {
                    continue;
                }
                $block_type = isset($content_block['type']) ? strtolower((string) $content_block['type']) : '';
                $has_image_payload = !empty($content_block['image_url']) || !empty($content_block['imageUrl']) || !empty($content_block['url']);
                if ($block_type === 'image_url' || $block_type === 'image' || $has_image_payload) {
                    $image_blocks[] = $content_block;
                }
            }
        }

        $revised_prompt = '';
        if (isset($message['content']) && is_string($message['content'])) {
            $revised_prompt = $message['content'];
        }

        foreach ($image_blocks as $image_block) {
            if (!is_array($image_block)) {
                continue;
            }

            $image_url_value = '';
            if (!empty($image_block['image_url']['url']) && is_string($image_block['image_url']['url'])) {
                $image_url_value = $image_block['image_url']['url'];
            } elseif (!empty($image_block['imageUrl']['url']) && is_string($image_block['imageUrl']['url'])) {
                $image_url_value = $image_block['imageUrl']['url'];
            } elseif (!empty($image_block['url']) && is_string($image_block['url'])) {
                $image_url_value = $image_block['url'];
            }

            if ($image_url_value === '') {
                continue;
            }

            $image_item = [
                'url' => null,
                'b64_json' => null,
                'mime_type' => null,
                'revised_prompt' => $revised_prompt !== '' ? $revised_prompt : null,
            ];

            if (preg_match('#^data:(image/[^;]+);base64,#i', $image_url_value, $matches) === 1) {
                $base64_data = (string) substr($image_url_value, strpos($image_url_value, ',') + 1);
                if ($base64_data !== '') {
                    $image_item['b64_json'] = $base64_data;
                    if (!empty($matches[1])) {
                        $image_item['mime_type'] = strtolower(sanitize_text_field((string) $matches[1]));
                    }
                }
            } else {
                $image_item['url'] = esc_url_raw($image_url_value);
            }

            if ($image_item['url'] === null && $image_item['b64_json'] === null) {
                continue;
            }

            $images[] = $image_item;
        }

        return $images;
    }

    /**
     * Generate image(s) via OpenRouter.
     *
     * @param string $prompt Prompt text.
     * @param array  $api_params Provider API params.
     * @param array  $options Runtime options.
     * @return array|WP_Error
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $api_key = isset($api_params['api_key']) ? sanitize_text_field((string) $api_params['api_key']) : '';
        $model = isset($options['model']) ? sanitize_text_field((string) $options['model']) : '';
        $clean_prompt = AIPKit_Prompt_Sanitizer::sanitize($prompt);
        $image_mode = isset($options['image_mode']) && $options['image_mode'] === 'edit' ? 'edit' : 'generate';

        if ($api_key === '') {
            return new WP_Error('openrouter_image_missing_key', __('OpenRouter API Key is required for image generation.', 'gpt3-ai-content-generator'));
        }
        if ($model === '') {
            return new WP_Error('openrouter_image_missing_model', __('OpenRouter image model is required.', 'gpt3-ai-content-generator'));
        }
        if ($clean_prompt === '') {
            return new WP_Error('openrouter_image_missing_prompt', __('Prompt cannot be empty for image generation.', 'gpt3-ai-content-generator'));
        }
        if ($image_mode === 'edit') {
            if (!$this->model_supports_image_editing($model)) {
                return new WP_Error(
                    'openrouter_image_edit_model_unsupported',
                    __('Selected OpenRouter model does not support image editing.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }
        } elseif (!$this->model_supports_image_output($model)) {
            return new WP_Error('openrouter_image_model_unsupported', __('Selected OpenRouter model does not support image output.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }

        $url = $this->build_api_url($api_params);
        if (is_wp_error($url)) {
            return $url;
        }

        $user_content = $clean_prompt;
        if ($image_mode === 'edit') {
            $source_image = isset($options['source_image']) && is_array($options['source_image'])
                ? $options['source_image']
                : null;
            if (!is_array($source_image)) {
                return new WP_Error(
                    'openrouter_image_edit_missing_source',
                    __('Source image is required for OpenRouter edit mode.', 'gpt3-ai-content-generator'),
                    ['status' => 400]
                );
            }

            $edit_user_content = $this->build_edit_user_content($clean_prompt, $source_image);
            if (is_wp_error($edit_user_content)) {
                return $edit_user_content;
            }
            $user_content = $edit_user_content;
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $user_content,
                ],
            ],
            'modalities' => $this->get_request_modalities($model),
            'stream' => false,
        ];

        if ($image_mode === 'edit') {
            $payload['n'] = 1;
        } elseif (isset($options['n'])) {
            $num_images = absint($options['n']);
            if ($num_images > 0) {
                $payload['n'] = $num_images;
            }
        }

        $image_config = [];
        if ($this->model_supports_image_config($model)) {
            $aspect_ratio = '';
            if (!empty($options['aspect_ratio'])) {
                $aspect_ratio = $this->normalize_aspect_ratio($options['aspect_ratio'], $model);
            } elseif (!empty($options['size']) && is_string($options['size'])) {
                $aspect_ratio = $this->normalize_aspect_ratio(
                    $this->map_size_to_aspect_ratio($options['size']) ?? '',
                    $model
                );
            }
            if ($aspect_ratio !== '') {
                $image_config['aspect_ratio'] = $aspect_ratio;
            }

            $image_size = $this->normalize_image_size($options['image_size'] ?? '', $model);
            if ($image_size !== '') {
                $image_config['image_size'] = $image_size;
            }
        }
        if (!empty($image_config)) {
            $payload['image_config'] = $image_config;
        }

        $headers = $this->get_api_headers($api_key, 'generate');
        $request_options = $this->get_request_options('generate');
        $request_args = array_merge($request_options, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ]);

        $response = wp_remote_post($url, $request_args);
        if (is_wp_error($response)) {
            return new WP_Error('openrouter_image_http_error', __('HTTP error during OpenRouter image generation.', 'gpt3-ai-content-generator'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = $this->decode_json($body, 'OpenRouter Image Generation');
        if ($status_code !== 200 || is_wp_error($decoded_response)) {
            $error_message = is_wp_error($decoded_response)
                ? $decoded_response->get_error_message()
                : $this->parse_error_response($body, $status_code, 'OpenRouter Image');
            /* translators: %1$d: HTTP status code, %2$s: error message */
            return new WP_Error('openrouter_image_api_error', sprintf(__('OpenRouter Image API Error (%1$d): %2$s', 'gpt3-ai-content-generator'), $status_code, $error_message));
        }

        $images = $this->parse_images($decoded_response);
        $requested_count = isset($payload['n']) ? absint($payload['n']) : 1;
        if ($requested_count > 0 && count($images) > $requested_count) {
            $images = array_slice($images, 0, $requested_count);
        }
        if (empty($images)) {
            return new WP_Error('openrouter_image_no_data', __('OpenRouter API returned success but no image data was found.', 'gpt3-ai-content-generator'));
        }

        $usage_data = null;
        if (isset($decoded_response['usage']) && is_array($decoded_response['usage'])) {
            $prompt_tokens = absint($decoded_response['usage']['prompt_tokens'] ?? 0);
            $completion_tokens = absint($decoded_response['usage']['completion_tokens'] ?? 0);
            $total_tokens = absint($decoded_response['usage']['total_tokens'] ?? ($prompt_tokens + $completion_tokens));
            $usage_data = [
                'input_tokens' => $prompt_tokens,
                'output_tokens' => $completion_tokens,
                'total_tokens' => $total_tokens,
                'provider_raw' => $decoded_response['usage'],
            ];
        }

        return [
            'images' => $images,
            'usage' => $usage_data,
        ];
    }

    /**
     * OpenRouter image sizes vary by model. Keep empty to avoid invalid hardcoded constraints.
     *
     * @return array
     */
    public function get_supported_sizes(): array
    {
        return [];
    }

    /**
     * OpenRouter request headers.
     *
     * @param string $api_key API key.
     * @param string $operation Operation name.
     * @return array
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer' => get_bloginfo('url'),
            'X-Title' => get_bloginfo('name'),
        ];
    }
}
