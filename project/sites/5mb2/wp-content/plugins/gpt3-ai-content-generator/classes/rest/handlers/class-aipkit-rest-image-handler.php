<?php

namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Images\AIPKit_Image_Manager;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles REST API requests for image generation.
 */
class AIPKit_REST_Image_Handler extends AIPKit_REST_Base_Handler {

    /**
     * Define arguments for the IMAGE generation endpoint.
     */
    public function get_endpoint_args(): array {
        return array(
            'prompt' => array(
                'description' => __('A text description of the desired image(s).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => true,
                'sanitize_callback' => static function ($value, $request = null, $param = null): string {
                    return AIPKit_Prompt_Sanitizer::sanitize($value);
                },
            ),
            'provider' => array(
                'description' => __('The AI image provider to use.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['openai', 'azure', 'google'],
                'default'     => 'openai',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'model' => array(
                'description' => __('The specific image model ID (e.g., gpt-image-2, gemini-3.1-flash-image-preview, imagen-4.0-generate-001).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'n' => array(
                'description' => __('The number of images to generate.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'default'     => 1,
                'minimum'     => 1,
                'maximum'     => 10,
                'sanitize_callback' => 'absint',
            ),
            'size' => array(
                'description' => __('The size of the generated images.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'required'    => false,
                'enum'        => ['256x256', '512x512', '1024x1024', '1792x1024', '1024x1792', '1536x1024', '1024x1536', '1024x768', '768x1024'],
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'quality' => array(
                'description' => __('The quality of the image (for supported provider/model combinations: standard or hd).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['standard', 'hd'],
                'required'    => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
             'style' => array(
                'description' => __('The style of the generated images when supported by the selected model.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['vivid', 'natural'],
                'required'    => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
             'response_format' => array(
                'description' => __('The format in which the generated images are returned (url or b64_json).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'enum'        => ['url', 'b64_json'],
                'default'     => 'url',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint (if required by settings).', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    /**
     * Define the schema for the IMAGE generation response.
     */
    public function get_item_schema(): array {
         return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'aipkit_image_generate_response',
            'type'       => 'object',
            'properties' => array(
                'images' => array(
                    'description' => esc_html__( 'An array of generated image data objects.', 'gpt3-ai-content-generator' ),
                    'type'        => 'array',
                    'readonly'    => true,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'url'            => array( 'type' => ['string', 'null'], 'description' => 'URL of the generated image, valid for 60 minutes.' ),
                            'b64_json'       => array( 'type' => ['string', 'null'], 'description' => 'Base64 encoded JSON data of the image.' ),
                            'revised_prompt' => array( 'type' => ['string', 'null'], 'description' => 'Revised prompt used by the model (if applicable).' ),
                        ),
                    ),
                ),
                'message' => array(
                    'description' => esc_html__( 'A status message indicating success or failure count.', 'gpt3-ai-content-generator' ),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
                 'usage' => array(
                    'description' => esc_html__( 'Token usage information (if available).', 'gpt3-ai-content-generator' ),
                    'type'        => ['object', 'null'],
                    'properties'  => array(
                        'input_tokens' => array('type' => 'integer'),
                        'output_tokens' => array('type' => 'integer'),
                        'total_tokens' => array('type' => 'integer'),
                    ),
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * Handles the IMAGE generation request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request) {
        if (!class_exists(AIPKit_Image_Manager::class)) {
             return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error: Image generation component not loaded.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $params = $request->get_params();
        $prompt = isset($params['prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($params['prompt']) : '';
        $options = [
            'provider'        => isset($params['provider']) ? sanitize_text_field($params['provider']) : 'openai',
            'model'           => isset($params['model']) ? sanitize_text_field($params['model']) : null,
            'size'            => isset($params['size']) ? sanitize_text_field($params['size']) : null,
            'n'               => isset($params['n']) ? absint($params['n']) : 1,
            'quality'         => isset($params['quality']) ? sanitize_text_field($params['quality']) : null,
            'style'           => isset($params['style']) ? sanitize_text_field($params['style']) : null,
            'response_format' => isset($params['response_format']) ? sanitize_text_field($params['response_format']) : 'url',
            'user'            => 'rest_api_user',
            'aipkit_event_module' => 'rest_api',
            'aipkit_event_origin' => 'rest_image_request',
        ];

        if (empty($prompt)) {
             return $this->send_wp_error_response(new WP_Error('rest_aipkit_missing_prompt', __('Missing required parameter: prompt', 'gpt3-ai-content-generator'), ['status' => 400]));
        }

        $image_manager = new AIPKit_Image_Manager();
        $result = $image_manager->generate_image($prompt, $options);

        if (is_wp_error($result)) {
            return $this->send_wp_error_response($result);
        }
        $response_data = [
            'images' => $result['images'] ?? [],
            'usage' => $result['usage'] ?? null,
            /* translators: %d is the count of images generated */
            'message' => sprintf(_n('%d image generated successfully.', '%d images generated successfully.', count($result['images'] ?? []), 'gpt3-ai-content-generator'), count($result['images'] ?? [])),
        ];
        return new WP_REST_Response($response_data, 200);
    }
}
