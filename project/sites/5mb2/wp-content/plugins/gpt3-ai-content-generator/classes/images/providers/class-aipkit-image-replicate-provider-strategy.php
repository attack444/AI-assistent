<?php


namespace WPAICG\Images\Providers;

use WPAICG\Images\AIPKit_Image_Base_Provider_Strategy;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Replicate Image Generation Provider Strategy.
 * Handles the asynchronous prediction workflow of the Replicate API.
 */
class AIPKit_Image_Replicate_Provider_Strategy extends AIPKit_Image_Base_Provider_Strategy
{
    private const POLLING_INTERVAL = 2; // seconds
    private const POLLING_TIMEOUT_ITERATIONS = 30; // 30 iterations * 2s = 60s timeout
    private const OPTION_FIELD_ALIASES = [
        'aspect_ratio' => ['aspect_ratio'],
        'width' => ['width'],
        'height' => ['height'],
        'negative_prompt' => ['negative_prompt'],
        'guidance' => ['guidance', 'guidance_scale'],
        'num_inference_steps' => ['num_inference_steps', 'num_steps', 'steps'],
        'seed' => ['seed'],
        'output_format' => ['output_format'],
        'output_quality' => ['output_quality', 'quality'],
        'disable_safety_checker' => ['disable_safety_checker'],
    ];

    /**
     * Get API headers required for Replicate requests.
     */
    public function get_api_headers(string $api_key, string $operation): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ];
        if ($operation === 'create_prediction') {
            // Use sync mode and wait up to 50 seconds. This is a tradeoff.
            // A web request can't block forever. Many models will finish in this time.
            $headers['Prefer'] = 'wait=50';
        }
        return $headers;
    }

    /**
     * Get provider-specific request options. Replicate uses GET for polling.
     */
    public function get_request_options(string $operation): array
    {
        $options = parent::get_request_options($operation);
        if ($operation === 'get_prediction' || $operation === 'models') {
            $options['method'] = 'GET';
        }
        // For the creation request, we increase the timeout to match the `Prefer: wait` header value.
        if ($operation === 'create_prediction') {
            $options['timeout'] = 60; // Slightly more than the Prefer:wait value
        }
        return $options;
    }

    /**
     * Get the list of available text-to-image models from Replicate's collection.
     * @return mixed[]|\WP_Error
     */
    public function get_models(array $api_params)
    {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('replicate_missing_key', __('Replicate API Key is required.', 'gpt3-ai-content-generator'));
        }

        $url = 'https://api.replicate.com/v1/collections/text-to-image';
        $headers = $this->get_api_headers($api_key, 'models');
        $request_options = $this->get_request_options('models');

        $response = wp_remote_get($url, array_merge($request_options, ['headers' => $headers]));
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return new WP_Error('replicate_models_api_error', 'Failed to fetch models: ' . $this->parse_error_response($body, $status_code, 'Replicate Models'));
        }

        $decoded = $this->decode_json($body, 'Replicate Models');
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $raw_models = $decoded['models'] ?? [];

        // Format to standard structure
        $formatted_models = [];
        foreach ($raw_models as $model) {
            if (!empty($model['latest_version']['id'])) {
                $owner = sanitize_text_field((string) ($model['owner'] ?? ''));
                $name = sanitize_text_field((string) ($model['name'] ?? ''));
                if ($owner === '' || $name === '') {
                    continue;
                }

                $openapi_schema = isset($model['latest_version']['openapi_schema']) && is_array($model['latest_version']['openapi_schema'])
                    ? $model['latest_version']['openapi_schema']
                    : $this->fetch_model_openapi_schema($api_key, $owner, $name);
                $input_schema = $this->extract_input_schema_metadata($openapi_schema);
                $formatted_model = [
                    'id' => $owner . '/' . $name . ':' . sanitize_text_field((string) $model['latest_version']['id']),
                    'name' => $owner . '/' . $name
                ];
                if (!empty($input_schema['fields'])) {
                    $formatted_model['input_schema'] = $input_schema;
                }
                $formatted_models[] = $formatted_model;
            }
        }
        return $formatted_models;
    }

    private function fetch_model_openapi_schema(string $api_key, string $owner, string $name): array
    {
        $url = 'https://api.replicate.com/v1/models/' . rawurlencode($owner) . '/' . rawurlencode($name);
        $headers = $this->get_api_headers($api_key, 'models');
        $request_options = $this->get_request_options('models');
        $request_options['timeout'] = min((int) ($request_options['timeout'] ?? 120), 12);

        $response = wp_remote_get($url, array_merge($request_options, ['headers' => $headers]));
        if (is_wp_error($response)) {
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [];
        }

        $decoded = $this->decode_json(wp_remote_retrieve_body($response), 'Replicate Model Schema');
        if (is_wp_error($decoded)) {
            return [];
        }

        return isset($decoded['latest_version']['openapi_schema']) && is_array($decoded['latest_version']['openapi_schema'])
            ? $decoded['latest_version']['openapi_schema']
            : [];
    }

    private function extract_input_schema_metadata(array $openapi_schema): array
    {
        $components = isset($openapi_schema['components']['schemas']) && is_array($openapi_schema['components']['schemas'])
            ? $openapi_schema['components']['schemas']
            : [];
        $properties = $components['Input']['properties'] ?? [];
        if (!is_array($properties)) {
            return ['fields' => []];
        }

        $fields = [];
        foreach (self::OPTION_FIELD_ALIASES as $canonical_field => $input_names) {
            foreach ($input_names as $input_name) {
                if (!isset($properties[$input_name]) || !is_array($properties[$input_name])) {
                    continue;
                }
                $field_schema = $this->resolve_openapi_property_schema($properties[$input_name], $components);
                $normalized = $this->normalize_input_schema_field($field_schema);
                $normalized['input_name'] = $input_name;
                $fields[$canonical_field] = $normalized;
                break;
            }
        }

        return ['fields' => $fields];
    }

    private function resolve_openapi_property_schema(array $property, array $components): array
    {
        $resolved = [];

        if (!empty($property['$ref']) && is_string($property['$ref'])) {
            $ref_name = $this->get_openapi_ref_name($property['$ref']);
            if ($ref_name !== '' && isset($components[$ref_name]) && is_array($components[$ref_name])) {
                $resolved = array_merge($resolved, $this->resolve_openapi_property_schema($components[$ref_name], $components));
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $compound_key) {
            if (empty($property[$compound_key]) || !is_array($property[$compound_key])) {
                continue;
            }
            foreach ($property[$compound_key] as $sub_schema) {
                if (!is_array($sub_schema)) {
                    continue;
                }
                $resolved = array_merge($resolved, $this->resolve_openapi_property_schema($sub_schema, $components));
            }
        }

        $own_values = $property;
        unset($own_values['$ref'], $own_values['allOf'], $own_values['anyOf'], $own_values['oneOf']);

        return array_merge($resolved, $own_values);
    }

    private function get_openapi_ref_name(string $ref): string
    {
        $parts = explode('/', $ref);
        $name = end($parts);

        return is_string($name) ? sanitize_text_field($name) : '';
    }

    private function normalize_input_schema_field(array $schema): array
    {
        $field = [];
        foreach (['type', 'title', 'description', 'default'] as $key) {
            if (isset($schema[$key]) && is_scalar($schema[$key])) {
                $field[$key] = sanitize_text_field((string) $schema[$key]);
            }
        }
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $field['enum'] = array_values(array_filter(array_map(
                static fn ($value) => is_scalar($value) ? sanitize_text_field((string) $value) : '',
                $schema['enum']
            ), static fn ($value) => $value !== ''));
        }
        foreach (['minimum', 'maximum'] as $key) {
            if (isset($schema[$key]) && is_numeric($schema[$key])) {
                $field[$key] = (float) $schema[$key];
            }
        }

        return $field;
    }

    /**
     * Generate an image by creating and polling a prediction on Replicate.
     * @return mixed[]|\WP_Error
     */
    public function generate_image(string $prompt, array $api_params, array $options = [])
    {
        $api_key = $api_params['api_key'] ?? null;
        if (empty($api_key)) {
            return new WP_Error('replicate_missing_key', __('Replicate API Key is required.', 'gpt3-ai-content-generator'));
        }
        if (empty($options['model'])) {
            return new WP_Error('replicate_missing_model', __('Replicate model/version ID is required.', 'gpt3-ai-content-generator'));
        }

        // 1. Create Prediction (using sync mode via headers)
        $input_params = ['prompt' => $prompt];
        $schema_fields = $this->get_synced_model_input_fields((string) $options['model']);

        // Get Replicate settings to check for disable_safety_checker
        if (class_exists('\WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler')) {
            $image_settings = \WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler::get_settings();
            $replicate_settings = $image_settings['replicate'] ?? [];
            $disable_safety_checker = $replicate_settings['disable_safety_checker'] ?? true;
            
            // Add disable_safety_checker to input if enabled
            if ($disable_safety_checker && (empty($schema_fields) || isset($schema_fields['disable_safety_checker']))) {
                $input_params['disable_safety_checker'] = true;
            }
        } else {
            // Fallback: disable safety checker by default if settings class not available
            if (empty($schema_fields) || isset($schema_fields['disable_safety_checker'])) {
                $input_params['disable_safety_checker'] = true;
            }
        }

        $input_params = $this->apply_schema_validated_input_options($input_params, $options, $schema_fields);
        
        $create_payload = [
            'version' => explode(':', $options['model'])[1] ?? $options['model'],
            'input' => $input_params
        ];
    
        $create_url = 'https://api.replicate.com/v1/predictions';
        $create_headers = $this->get_api_headers($api_key, 'create_prediction');
        $create_options = $this->get_request_options('create_prediction');

        $create_response = wp_remote_post($create_url, array_merge($create_options, ['headers' => $create_headers, 'body' => json_encode($create_payload)]));

        if (is_wp_error($create_response)) {
            return $create_response;
        }

        $create_status_code = wp_remote_retrieve_response_code($create_response);
        $create_body = wp_remote_retrieve_body($create_response);

        $create_decoded = $this->decode_json($create_body, 'Replicate Create Prediction');
        if (is_wp_error($create_decoded)) {
            return $create_decoded;
        }

        if ($create_status_code >= 300) {
            return new WP_Error('replicate_create_error', 'Failed to create prediction: ' . $this->parse_error_response($create_body, $create_status_code, 'Replicate Create Prediction'));
        }

        $status = $create_decoded['status'] ?? 'unknown';

        if ($status === 'succeeded') {
            // Finished in sync mode, process result directly.
            return $this->format_successful_response($create_decoded);
        } elseif ($status === 'starting' || $status === 'processing') {
            // Timed out, must poll.
            $get_url = $create_decoded['urls']['get'] ?? null;
            if (!$get_url) {
                return new WP_Error('replicate_no_get_url', 'Replicate API did not return a URL to get the prediction status after sync timeout.');
            }
            return $this->poll_for_result($api_key, $get_url);
        } elseif ($status === 'failed' || $status === 'canceled') {
            return new WP_Error('replicate_prediction_failed_initial', 'Prediction failed or was canceled. Error: ' . ($create_decoded['error'] ?? 'Unknown reason.'));
        } else {
            return new WP_Error('replicate_unknown_status', 'Received unknown prediction status: ' . esc_html($status));
        }
    }

    private function get_synced_model_input_fields(string $model): array
    {
        if (!class_exists('\WPAICG\AIPKit_Providers')) {
            return [];
        }

        $target_model = strtolower(trim($model));
        if ($target_model === '') {
            return [];
        }

        $models = \WPAICG\AIPKit_Providers::get_replicate_models();
        if (!is_array($models)) {
            return [];
        }

        foreach ($models as $model_row) {
            if (!is_array($model_row)) {
                continue;
            }
            if (strtolower(trim((string) ($model_row['id'] ?? ''))) !== $target_model) {
                continue;
            }

            $schema = $model_row['input_schema'] ?? ($model_row['replicate_input_schema'] ?? []);
            if (!is_array($schema)) {
                return [];
            }
            $fields = $schema['fields'] ?? $schema;

            return is_array($fields) ? $fields : [];
        }

        return [];
    }

    private function apply_schema_validated_input_options(array $input_params, array $options, array $schema_fields): array
    {
        if (empty($schema_fields)) {
            return $input_params;
        }

        $requested_inputs = [];
        if (isset($options['replicate_input_options']) && is_array($options['replicate_input_options'])) {
            $requested_inputs = $options['replicate_input_options'];
        }

        foreach (array_keys(self::OPTION_FIELD_ALIASES) as $canonical_field) {
            if ($canonical_field === 'disable_safety_checker') {
                continue;
            }

            $field_schema = $schema_fields[$canonical_field] ?? null;
            if (!is_array($field_schema)) {
                continue;
            }

            $input_name = sanitize_key((string) ($field_schema['input_name'] ?? $canonical_field));
            if ($input_name === '') {
                continue;
            }

            $raw_value = null;
            if (array_key_exists($input_name, $requested_inputs)) {
                $raw_value = $requested_inputs[$input_name];
            } elseif (array_key_exists($canonical_field, $options)) {
                $raw_value = $options[$canonical_field];
            }

            $value = $this->sanitize_schema_input_value($canonical_field, $raw_value, $field_schema);
            if ($value !== null) {
                $input_params[$input_name] = $value;
            }
        }

        return $input_params;
    }

    private function sanitize_schema_input_value(string $field, $value, array $schema_field)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['aspect_ratio', 'output_format'], true)) {
            $value = $field === 'output_format'
                ? sanitize_key((string) $value)
                : sanitize_text_field((string) $value);
            if ($value === '') {
                return null;
            }
            $fallback_allowed = $field === 'output_format'
                ? ['webp', 'png', 'jpg', 'jpeg']
                : ['1:1', '16:9', '21:9', '3:2', '2:3', '4:3', '3:4', '4:5', '5:4', '9:16', '1:2', '2:1', '3:1', '1:3'];
            $enum = isset($schema_field['enum']) && is_array($schema_field['enum'])
                ? array_map('strval', $schema_field['enum'])
                : [];
            $allowed = !empty($enum) ? $enum : $fallback_allowed;

            return in_array($value, $allowed, true) ? $value : null;
        }

        if ($field === 'negative_prompt') {
            $value = AIPKit_Prompt_Sanitizer::sanitize($value);
            if ($value === '') {
                return null;
            }

            return function_exists('mb_substr') ? mb_substr($value, 0, 1000) : substr($value, 0, 1000);
        }

        if ($field === 'guidance') {
            return $this->sanitize_schema_float_value($value, $schema_field, 0.0, 30.0);
        }

        if (in_array($field, ['width', 'height'], true)) {
            return $this->sanitize_schema_int_value($value, $schema_field, 64, 4096);
        }

        if ($field === 'num_inference_steps') {
            return $this->sanitize_schema_int_value($value, $schema_field, 1, 100);
        }

        if ($field === 'seed') {
            return $this->sanitize_schema_int_value($value, $schema_field, 0, 2147483647);
        }

        if ($field === 'output_quality') {
            return $this->sanitize_schema_int_value($value, $schema_field, 0, 100);
        }

        return null;
    }

    private function sanitize_schema_int_value($value, array $schema_field, int $fallback_min, int $fallback_max): ?int
    {
        $value = is_scalar($value) ? (string) wp_unslash($value) : '';
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = absint($value);
        $minimum = isset($schema_field['minimum']) && is_numeric($schema_field['minimum'])
            ? (int) $schema_field['minimum']
            : $fallback_min;
        $maximum = isset($schema_field['maximum']) && is_numeric($schema_field['maximum'])
            ? (int) $schema_field['maximum']
            : $fallback_max;

        return ($number >= $minimum && $number <= $maximum) ? $number : null;
    }

    private function sanitize_schema_float_value($value, array $schema_field, float $fallback_min, float $fallback_max): ?float
    {
        $value = is_scalar($value) ? (string) wp_unslash($value) : '';
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        $minimum = isset($schema_field['minimum']) && is_numeric($schema_field['minimum'])
            ? (float) $schema_field['minimum']
            : $fallback_min;
        $maximum = isset($schema_field['maximum']) && is_numeric($schema_field['maximum'])
            ? (float) $schema_field['maximum']
            : $fallback_max;

        return ($number >= $minimum && $number <= $maximum) ? $number : null;
    }

    /**
     * Polls the Replicate API for a prediction result.
     * @param string $api_key
     * @param string $get_url
     * @return array|WP_Error
     */
    private function poll_for_result(string $api_key, string $get_url)
    {
        $poll_headers = $this->get_api_headers($api_key, 'get_prediction');
        $poll_options = $this->get_request_options('get_prediction');
        for ($i = 0; $i < self::POLLING_TIMEOUT_ITERATIONS; $i++) {
            sleep(self::POLLING_INTERVAL);

            $poll_response = wp_remote_get($get_url, array_merge($poll_options, ['headers' => $poll_headers]));
            if (is_wp_error($poll_response)) {
                return $poll_response;
            }

            $poll_status_code = wp_remote_retrieve_response_code($poll_response);
            $poll_body = wp_remote_retrieve_body($poll_response);

            $poll_decoded = $this->decode_json($poll_body, 'Replicate Poll Prediction');
            if ($poll_status_code >= 300) {
                return new WP_Error('replicate_poll_error', 'Error polling prediction: ' . $this->parse_error_response($poll_body, $poll_status_code, 'Replicate Poll Prediction'));
            }

            $status = $poll_decoded['status'] ?? 'unknown';
            if ($status === 'succeeded') {
                return $this->format_successful_response($poll_decoded);
            } elseif ($status === 'failed' || $status === 'canceled') {
                return new WP_Error('replicate_prediction_failed', 'Prediction failed or was canceled. Error: ' . ($poll_decoded['error'] ?? 'Unknown reason.'));
            }
            // Continue polling if status is 'starting' or 'processing'
        }
        return new WP_Error('replicate_timeout', 'Prediction timed out after ' . (self::POLLING_TIMEOUT_ITERATIONS * self::POLLING_INTERVAL) . ' seconds.');
    }

    /**
     * Formats a successful prediction response into the standard structure.
     * @param array $decoded_response
     * @return array|WP_Error
     */
    private function format_successful_response(array $decoded_response)
    {
        $output = $decoded_response['output'] ?? null;
        if (!$output) {
            return new WP_Error('replicate_no_output', 'Prediction succeeded but no output was found.');
        }

        $image_urls = is_array($output) ? $output : [$output];
        $images = array_map(function ($item) {
            if (is_array($item) && isset($item['url'])) {
                $url = $item['url'];
            } else {
                $url = $item;
            }
            return ['url' => $url, 'b64_json' => null];
        }, $image_urls);
        $images = array_filter($images, fn ($image) => !empty($image['url']));
        if (empty($images)) {
            return new WP_Error('replicate_no_output_url', 'Prediction succeeded but no image URL was found.');
        }

        $predict_time = $decoded_response['metrics']['predict_time'] ?? 0;
        $estimated_tokens = round($predict_time * 500);
        $usage = ['total_tokens' => $estimated_tokens];

        return ['images' => $images, 'usage' => $usage];
    }


    /**
     * Sizes are model-specific on Replicate, so we return an empty array.
     */
    public function get_supported_sizes(): array
    {
        return [];
    }
}
