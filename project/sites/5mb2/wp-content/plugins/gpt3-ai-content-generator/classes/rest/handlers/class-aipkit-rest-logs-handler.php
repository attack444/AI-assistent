<?php


namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Chat\Storage\LogStorage;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles REST API requests for retrieving chatbot conversation logs.
 */
class AIPKit_REST_Logs_Handler extends AIPKit_REST_Base_Handler
{
    private $log_storage;

    public function __construct()
    {
        if (class_exists(LogStorage::class)) {
            $this->log_storage = new LogStorage();
        }
    }

    /**
     * Define arguments for the logs endpoint.
     */
    public function get_endpoint_args(): array
    {
        return array(
            'page' => array(
                'description' => __('The page number for pagination.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'default'     => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => __('The number of conversation logs to return per page.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'default'     => 20,
                'sanitize_callback' => 'absint',
            ),
            'bot_id' => array(
                'description' => __('Filter logs for a specific chatbot ID.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'user_search' => array(
                'description' => __("Search for logs by a user's display name, username, or email.", 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'message_search' => array(
                'description' => __('Search for logs where the conversation contains specific text.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'aipkit_api_key' => array(
                'description' => __('API Key for accessing this endpoint.', 'gpt3-ai-content-generator'),
                'type'        => 'string',
            ),
        );
    }

    /**
     * Define the schema for the logs response.
     */
    public function get_item_schema(): array
    {
        return array(
           '$schema'    => 'http://json-schema.org/draft-04/schema#',
           'title'      => 'aipkit_logs_response',
           'type'       => 'array',
           'items'      => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array('type' => 'string'),
                    'bot_id' => array('type' => ['string', 'null']),
                    'user_id' => array('type' => ['string', 'null']),
                    'session_id' => array('type' => ['string', 'null']),
                    'conversation_uuid' => array('type' => 'string'),
                    'messages' => array('type' => 'string', 'description' => 'A JSON string of the full conversation history.'),
                    'message_count' => array('type' => 'string'),
                    'last_message_ts' => array('type' => 'string'),
                    'created_at' => array('type' => 'string', 'format' => 'date-time'),
                    'bot_name' => array('type' => 'string'),
                    'user_display_name' => array('type' => 'string'),
                ),
            ),
        );
    }


    /**
     * Handles the logs request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request)
    {
        if (!$this->log_storage) {
            return $this->send_wp_error_response(new WP_Error('rest_aipkit_internal_error', __('Internal server error: Log storage component not loaded.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }

        $params = $request->get_params();
        $filters = [];
        if (!empty($params['bot_id'])) {
            $filters['bot_id'] = $params['bot_id'];
        }
        if (!empty($params['user_search'])) {
            $filters['user_name'] = $params['user_search'];
        }
        if (!empty($params['message_search'])) {
            $filters['message_like'] = $params['message_search'];
        }

        $page = $params['page'];
        $per_page = min(100, $params['per_page']);
        $offset = ($page - 1) * $per_page;

        $total_logs = $this->log_storage->count_logs($filters);
        $logs = $this->log_storage->get_raw_conversations_for_export($filters, $per_page, $offset);
        
        $response = new WP_REST_Response($logs);
        $response->header('X-WP-Total', $total_logs);
        $response->header('X-WP-TotalPages', ceil($total_logs / $per_page));

        return $response;
    }
}