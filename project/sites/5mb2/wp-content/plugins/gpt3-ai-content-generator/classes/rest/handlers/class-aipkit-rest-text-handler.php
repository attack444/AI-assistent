<?php


namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Core\AIPKit_Instruction_Manager;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles REST API requests for text generation.
 */
class AIPKit_REST_Text_Handler extends AIPKit_REST_Base_Handler
{
    /**
     * Define arguments for the TEXT generation endpoint.
     */
    public function get_endpoint_args(): array
    {
        return array(
            'provider' => array(
                'description' => __('The AI provider to use for text generation.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['openai', 'azure', 'google', 'openrouter', 'claude', 'deepseek', 'xai', 'ollama'],
                'required'    => true,
            ),
            'model' => array(
                'description' => __('The specific text model or deployment ID.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => true,
            ),
            'messages' => array(
                'description' => __('An array of message objects (role/content).', 'gpt3-ai-content-generator'),
                'type'        => 'array',
                'required'    => true,
                'items'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'role'    => array('type' => 'string', 'enum' => ['system', 'user', 'assistant'], 'required' => true),
                        'content' => array('type' => 'string', 'required' => true),
                    ),
                ),
            ),
            'stream' => array(
                'description' => __('Whether to stream the response (currently not supported via this endpoint).', 'gpt3-ai-content-generator'),
                'type'        => 'boolean',
                'default'     => false,
            ),
            'system_instruction' => array(
                'description' => __('Optional system instructions for the AI. Supports [date] and [username] placeholders.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
            'ai_params' => array(
                'description' => __('Optional AI parameters (temperature, max_tokens, etc.).', 'gpt3-ai-content-generator'),
                'type'        => 'object',
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint (if required by settings). Send as parameter or Authorization: Bearer header.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    /**
     * Define the schema for the TEXT generation response.
     */
    public function get_item_schema(): array
    {
        return array(
           '$schema'    => 'http://json-schema.org/draft-04/schema#',
           'title'      => 'aipkit_generate_response',
           'type'       => 'object',
           'properties' => array(
               'content' => array(
                   'description' => esc_html__('The generated AI response content.', 'gpt3-ai-content-generator'),
                   'type'        => 'string',
                   'readonly'    => true,
               ),
               'usage' => array(
                   'description' => esc_html__('Token usage information.', 'gpt3-ai-content-generator'),
                   'type'        => ['object', 'null'],
                   'properties' => array(
                       'input_tokens' => array( 'type' => 'integer' ),
                       'output_tokens' => array( 'type' => 'integer' ),
                       'total_tokens' => array( 'type' => 'integer' ),
                   ),
                   'readonly' => true,
               ),
               'model' => array(
                   'description' => esc_html__('The model used for the response.', 'gpt3-ai-content-generator'),
                   'type'        => 'string',
                   'readonly'    => true,
               ),
                'provider' => array(
                   'description' => esc_html__('The provider used for the response.', 'gpt3-ai-content-generator'),
                   'type'        => 'string',
                   'readonly'    => true,
               ),
           ),
        );
    }

    /**
     * Handles the TEXT generation request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request)
    {
        $params = $request->get_params();
        $provider_raw = $params['provider'] ?? null;
        $model = $params['model'] ?? null;
        $messages = $params['messages'] ?? null;
        $stream = filter_var($params['stream'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $system_instruction = $params['system_instruction'] ?? null;
        $ai_params_override = $params['ai_params'] ?? [];

        if (empty($provider_raw)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_param', __('Missing required parameter: provider', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (empty($model)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_param', __('Missing required parameter: model', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (empty($messages) || !is_array($messages)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', __('Invalid or missing parameter: messages (must be an array)', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (!is_array($ai_params_override)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', __('Invalid parameter type: ai_params (must be an object)', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if ($system_instruction !== null && !is_string($system_instruction)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', __('Invalid parameter type: system_instruction (must be a string)', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if ($system_instruction !== null) {
            $system_instruction = AIPKit_Prompt_Sanitizer::sanitize($system_instruction);
        }

        $provider = AIPKit_Providers::normalize_provider_label((string) $provider_raw);
        if (!in_array($provider, ['OpenAI', 'Azure', 'Google', 'OpenRouter', 'Claude', 'DeepSeek', 'xAI', 'Ollama'], true)) {
            /* translators: %s is the invalid provider name */
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_param', sprintf(__('Invalid provider specified: %s', 'gpt3-ai-content-generator'), $provider_raw), ['status' => 400]));
        }
        foreach ($messages as $index => $msg) {
            if (!is_array($msg) || empty($msg['role']) || !in_array($msg['role'], ['system', 'user', 'assistant']) || !isset($msg['content']) || !is_string($msg['content'])) {
                /* translators: %d is the index of the message in the array */
                return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_message_format', sprintf(__('Invalid message format at index %d.', 'gpt3-ai-content-generator'), $index), ['status' => 400]));
            }
            $messages[$index]['role'] = sanitize_key($msg['role']);
            $messages[$index]['content'] = AIPKit_Prompt_Sanitizer::sanitize($msg['content']);
        }
        if ($stream) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_streaming_not_supported', __('Streaming responses are not supported via this REST endpoint.', 'gpt3-ai-content-generator'), ['status' => 400]));
        }
        if (!class_exists(AIPKit_AI_Caller::class) || !class_exists(AIPKit_Instruction_Manager::class) || !class_exists(AIPKit_Providers::class) || !class_exists(AIPKIT_AI_Settings::class)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $ai_caller = new AIPKit_AI_Caller();
        $global_ai_params = AIPKIT_AI_Settings::get_ai_parameters();
        $final_ai_params = array_merge($global_ai_params, $ai_params_override);
        if (isset($final_ai_params['temperature'])) {
            $final_ai_params['temperature'] = max(0.0, min(2.0, floatval($final_ai_params['temperature'])));
        }
        if (isset($final_ai_params['max_completion_tokens'])) {
            $final_ai_params['max_completion_tokens'] = max(1, min(128000, absint($final_ai_params['max_completion_tokens'])));
        }
        $instruction_context = ['base_instructions' => $system_instruction ?? ''];
        $instructions_processed = AIPKit_Instruction_Manager::build_instructions($instruction_context);

        $result = $ai_caller->make_standard_call($provider, $model, $messages, $final_ai_params, $instructions_processed);

        if (is_wp_error($result)) {
            return $this->send_wp_error_response($result);
        }

        $response_data = [ 'content' => $result['content'] ?? '', 'usage' => $result['usage'] ?? null, 'provider' => $provider, 'model' => $model ];
        if (isset($response_data['usage']['provider_raw'])) {
            unset($response_data['usage']['provider_raw']);
        }
        return new WP_REST_Response($response_data, 200);
    }
}
