<?php

namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Chat\Storage\BotStorage;
use WPAICG\Chat\Core\AIService as ChatAIService;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles REST API requests for interacting with a specific chatbot.
 */
class AIPKit_REST_Chat_Handler extends AIPKit_REST_Base_Handler
{
    private $bot_storage;
    private $ai_service;

    public function __construct()
    {
        // These dependencies are loaded by the main plugin loader.
        if (class_exists(BotStorage::class)) {
            $this->bot_storage = new BotStorage();
        }
        if (class_exists(ChatAIService::class)) {
            $this->ai_service = new ChatAIService();
        }
    }

    /**
     * Define arguments for the chatbot message endpoint.
     */
    public function get_endpoint_args(): array
    {
        return array(
            'bot_id' => array(
                'description' => __('The ID of the chatbot to interact with.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'required'    => true,
                'validate_callback' => function ($param) { return is_numeric($param); }
            ),
            'messages' => array(
                'description' => __('An array of message objects representing the conversation history.', 'gpt3-ai-content-generator'),
                'type'        => 'array',
                'required'    => true,
                'items'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'role'    => array('type' => 'string', 'enum' => ['user', 'assistant'], 'required' => true),
                        'content' => array('type' => 'string', 'required' => true),
                    ),
                ),
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    /**
     * Define the schema for the chatbot message response.
     */
    public function get_item_schema(): array
    {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'aipkit_chat_message_response',
            'type'       => 'object',
            'properties' => array(
                'reply' => array(
                    'description' => esc_html__('The chatbot\'s generated reply.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
                'usage' => array(
                    'description' => esc_html__('Token usage information for the interaction.', 'gpt3-ai-content-generator'),
                    'type'        => ['object', 'null'],
                    'readonly'    => true,
                ),
                'bot_id' => array(
                    'description' => esc_html__('The ID of the bot that replied.', 'gpt3-ai-content-generator'),
                    'type'        => 'integer',
                    'readonly'    => true,
                ),
                'model' => array(
                    'description' => esc_html__('The model used for the response.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * Handles the chatbot message request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request)
    {
        if (!$this->bot_storage || !$this->ai_service) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error: Chat components not loaded.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $bot_id = (int) $request->get_param('bot_id');
        $messages = $request->get_param('messages');

        // Basic validation
        if (empty($messages) || !is_array($messages)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_messages', __('The "messages" parameter must be a non-empty array.', 'gpt3-ai-content-generator'), ['status' => 400]));
        }

        $user_message_obj = end($messages);
        if (!$user_message_obj || $user_message_obj['role'] !== 'user' || empty($user_message_obj['content'])) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_invalid_last_message', __('The last message in the array must be from the "user" role and have content.', 'gpt3-ai-content-generator'), ['status' => 400]));
        }

        $user_message_text = $user_message_obj['content'];
        $history = array_slice($messages, 0, -1);

        // Fetch bot settings
        $bot_settings = $this->bot_storage->get_chatbot_settings($bot_id);
        if (empty($bot_settings)) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_bot_not_found', __('The specified chatbot was not found.', 'gpt3-ai-content-generator'), ['status' => 404]));
        }

        // Call the core AI service to get a response
        $ai_result = $this->ai_service->generate_response(
            $user_message_text,
            $bot_settings,
            $history,
            0, // post_id is not relevant for this REST context
            null, // frontend_previous_openai_response_id
            false, // frontend_openai_web_search_active
            false, // frontend_google_search_grounding_active
            null, // image_inputs_for_service
            null, // frontend_active_openai_vs_id
            null, // frontend_active_pinecone_index_name
            null, // frontend_active_pinecone_namespace
            null, // frontend_active_qdrant_collection_name
            null, // frontend_active_qdrant_file_upload_context_id
            null, // frontend_active_chroma_collection_name
            null, // frontend_active_chroma_file_upload_context_id
            null  // frontend_active_claude_file_id
        );

        if (is_wp_error($ai_result)) {
            return $this->send_wp_error_response($ai_result);
        }

        $response_data = [
            'reply'    => $ai_result['content'] ?? '',
            'usage'    => $ai_result['usage'] ?? null,
            'bot_id'   => $bot_id,
            'model'    => $bot_settings['model'] ?? 'unknown',
        ];

        return new WP_REST_Response($response_data, 200);
    }
}
