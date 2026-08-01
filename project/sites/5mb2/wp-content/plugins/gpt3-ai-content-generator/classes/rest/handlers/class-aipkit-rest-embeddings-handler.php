<?php


namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\AIPKit_Providers;
use WPAICG\Core\AIPKit_AI_Caller;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles REST API requests for generating embeddings.
 */
class AIPKit_REST_Embeddings_Handler extends AIPKit_REST_Base_Handler
{
    /**
     * Define arguments for the EMBEDDINGS generation endpoint.
     */
    public function get_endpoint_args(): array
    {
        $embedding_provider_keys = AIPKit_Providers::get_embedding_provider_keys('rest_embeddings_endpoint_args');

        return array(
            'provider' => array(
                'description' => __('The AI provider key for embeddings (from enabled embedding providers).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => $embedding_provider_keys,
                'required'    => true,
            ),
            'model' => array(
                'description' => __('The specific embedding model ID (or Azure deployment ID).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => true,
            ),
            'input' => array(
                'description' => __('Input text(s) to embed. String or array of strings.', 'gpt3-ai-content-generator'),
                'type'        => ['string', 'array'],
                'required'    => true,
                'items'       => ['type' => 'string'],
            ),
            'dimensions' => array(
                'description' => __('(OpenAI/OpenRouter text-embedding-3+, Azure) Number of dimensions for output embeddings.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'required'    => false,
            ),
            'encoding_format' => array(
                'description' => __('(OpenAI/OpenRouter) Format to return embeddings: float or base64.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['float', 'base64'],
                'default'     => 'float',
            ),
            'task_type' => array(
                'description' => __('(Google Gemini Embeddings) Optimized task type for embeddings.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['SEMANTIC_SIMILARITY', 'CLASSIFICATION', 'CLUSTERING', 'RETRIEVAL_DOCUMENT', 'RETRIEVAL_QUERY', 'QUESTION_ANSWERING', 'FACT_VERIFICATION', 'CODE_RETRIEVAL_QUERY'],
                'required'    => false,
            ),
            'output_dimensionality' => array(
                'description' => __('(Google Gemini Embeddings) Output dimension size for embeddings.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'required'    => false,
            ),
            'user' => array(
                'description' => __('(OpenAI, Azure) End-user identifier for abuse monitoring.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => false,
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint (if required by settings).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    /**
     * Define the schema for the EMBEDDINGS generation response.
     */
    public function get_item_schema(): array
    {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'aipkit_embeddings_response',
            'type'       => 'object',
            'properties' => array(
                'embeddings' => array(
                    'description' => esc_html__('An array of embedding vectors (arrays of floats).', 'gpt3-ai-content-generator'),
                    'type'        => 'array',
                    'items'       => array('type' => 'array', 'items' => array('type' => 'number')),
                    'readonly'    => true,
                ),
                'usage' => array(
                    'description' => esc_html__('Token usage information.', 'gpt3-ai-content-generator'),
                    'type'        => ['object', 'null'],
                    'properties'  => array(
                        'input_tokens' => array('type' => 'integer'),
                        'total_tokens' => array('type' => 'integer'),
                    ),
                    'readonly'    => true,
                ),
                'model' => array(
                    'description' => esc_html__('The model used for the embeddings.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
                'provider' => array(
                    'description' => esc_html__('The provider used for the embeddings.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * Handles the EMBEDDINGS generation request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request)
    {
        $params = $request->get_params();
        $provider_raw = $params['provider'] ?? null;
        $model = $params['model'] ?? null;
        $input = $params['input'] ?? null;

        if (empty($provider_raw)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_param', __('Missing required parameter: provider', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (empty($model)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_param', __('Missing required parameter: model', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (empty($input)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_param', __('Missing required parameter: input', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (!is_string($input) && !is_array($input)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', __('Invalid parameter type: input (must be a string or array of strings)', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (is_array($input)) {
            foreach ($input as $idx => $text) {
                if (!is_string($text)) {
                    /* translators: %s is the index of the input array */
                    return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', sprintf(__('Invalid input array item at index %d (must be a string)', 'gpt3-ai-content-generator'), $idx), ['status' => 400]));
                }
            }
        }

        $provider = AIPKit_Providers::resolve_embedding_provider_name(
            $provider_raw,
            'rest_embeddings_handle_request'
        );
        if ($provider === null) {
            /* translators: %s is the provider name */
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', sprintf(__('Invalid provider specified for embeddings: %s', 'gpt3-ai-content-generator'), $provider_raw), ['status' => 400]));
        }

        if (!class_exists(AIPKit_AI_Caller::class)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $ai_caller = new AIPKit_AI_Caller();
        $embedding_options = [
            'model' => sanitize_text_field($model)
        ];

        if ($provider === 'OpenAI' || $provider === 'Azure' || $provider === 'OpenRouter') {
            if (isset($params['dimensions'])) {
                $embedding_options['dimensions'] = absint($params['dimensions']);
            }
            if (isset($params['user'])) {
                $embedding_options['user'] = sanitize_text_field($params['user']);
            }
            if (($provider === 'OpenAI' || $provider === 'OpenRouter') && isset($params['encoding_format'])) {
                $embedding_options['encoding_format'] = sanitize_key($params['encoding_format']);
            }
        }
        if ($provider === 'Google') {
            if (isset($params['task_type'])) {
                $task_type = strtoupper(sanitize_text_field((string) $params['task_type']));
                $allowed_task_types = [
                    'SEMANTIC_SIMILARITY',
                    'CLASSIFICATION',
                    'CLUSTERING',
                    'RETRIEVAL_DOCUMENT',
                    'RETRIEVAL_QUERY',
                    'QUESTION_ANSWERING',
                    'FACT_VERIFICATION',
                    'CODE_RETRIEVAL_QUERY',
                ];
                if (in_array($task_type, $allowed_task_types, true)) {
                    $embedding_options['taskType'] = $task_type;
                }
            }
            if (isset($params['output_dimensionality'])) {
                $embedding_options['outputDimensionality'] = absint($params['output_dimensionality']);
            }
        }

        $result = $ai_caller->generate_embeddings($provider, $input, $embedding_options);

        if (is_wp_error($result)) {
            return $this->send_wp_error_response($result);
        }

        $response_data = [
            'embeddings' => $result['embeddings'] ?? [],
            'usage'      => $result['usage'] ?? null,
            'provider'   => $provider,
            'model'      => $model,
        ];
        return new WP_REST_Response($response_data, 200);
    }
}
