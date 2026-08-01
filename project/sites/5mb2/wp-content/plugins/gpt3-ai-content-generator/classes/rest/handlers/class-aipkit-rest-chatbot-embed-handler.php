<?php

namespace WPAICG\REST\Handlers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAICG\Includes\AIPKit_Shared_Assets_Manager;
use WPAICG\Chat\Frontend\Shortcode\DataProvider;
use WPAICG\Chat\Frontend\Shortcode\FeatureManager;
use WPAICG\Chat\Frontend\Shortcode\Configurator;
use WPAICG\Chat\Frontend\Shortcode\Renderer;


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST API requests for chatbot embed configurations.
 */
class AIPKit_REST_Chatbot_Embed_Handler extends AIPKit_REST_Base_Handler
{
    /**
     * Define arguments for the chatbot embed config endpoint.
     */
    public function get_endpoint_args(): array
    {
        return [
            'bot_id' => [
                'description' => __('The ID of the chatbot to get the configuration for.', 'gpt3-ai-content-generator'),
                'type'        => 'integer',
                'required'    => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
        ];
    }
    
    /**
     * Define the schema for the embed configuration response.
     * UPDATED: Added 'html' property to the schema.
     */
    public function get_item_schema(): array
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'aipkit_chatbot_embed_config',
            'type'       => 'object',
            'properties' => [
                'config' => [
                    'description' => esc_html__('The frontend JavaScript configuration object for the chatbot.', 'gpt3-ai-content-generator'),
                    'type'        => 'object',
                    'readonly'    => true,
                ],
                'html' => [
                    'description' => esc_html__('The fully rendered HTML for the chatbot container.', 'gpt3-ai-content-generator'),
                    'type'        => 'string',
                    'readonly'    => true,
                ],
            ],
        ];
    }


    /**
     * Handles the chatbot embed configuration request.
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function handle_request(WP_REST_Request $request)
    {
        if (!class_exists('\WPAICG\aipkit_dashboard') || !\WPAICG\aipkit_dashboard::is_pro_plan()) {
            return $this->send_wp_error_response(new WP_Error(
                'rest_aipkit_embed_requires_pro',
                __('External chatbot embeds require an active Pro plan.', 'gpt3-ai-content-generator'),
                ['status' => 403]
            ));
        }

        $bot_id = (int) $request->get_param('bot_id');
        
        // Fetch Bot Data using existing DataProvider
        $bot_data = DataProvider::get_bot_data($bot_id);
        if (is_wp_error($bot_data)) {
            return $this->send_wp_error_response(new WP_Error(
                'rest_aipkit_bot_not_found',
                __('The specified chatbot was not found or is not published.', 'gpt3-ai-content-generator'),
                ['status' => 404]
            ));
        }

        $bot_post = $bot_data['post'];
        $bot_settings = $bot_data['settings'];

        // --- CORS Check ---
        $allowed_domains_str = $bot_settings['embed_allowed_domains'] ?? '';
        $allowed_domains = preg_split('/[\s,]+/', $allowed_domains_str, -1, PREG_SPLIT_NO_EMPTY);
        $request_origin = $request->get_header('Origin');
        $origin_is_allowed = false;
        $origin_to_allow = '*'; // Default to all if no domains are set and no origin header is present

        if (empty($allowed_domains)) {
            // If no domains are specified in settings, allow any origin that sends an Origin header.
            $origin_is_allowed = true;
            if (!empty($request_origin)) {
                $origin_to_allow = $request_origin;
            }
        } else {
            if (!empty($request_origin)) {
                // Normalize the request origin by removing a potential trailing slash
                $normalized_request_origin = rtrim($request_origin, '/');
                foreach ($allowed_domains as $allowed_domain) {
                    if ($normalized_request_origin === $allowed_domain) {
                        $origin_is_allowed = true;
                        $origin_to_allow = $request_origin; // Set specific origin for the header
                        break;
                    }
                }
            }
            // If Origin header is missing but domains are specified, it will fail (origin_is_allowed remains false)
            // This is correct behavior as browsers will not send an Origin header for same-origin requests.
        }

        if (!$origin_is_allowed) {
            return $this->send_wp_error_response(new WP_Error(
                'rest_aipkit_cors_denied',
                __('This domain is not permitted to embed the chatbot.', 'gpt3-ai-content-generator'),
                ['status' => 403]
            ));
        }
        // --- End CORS Check ---


        // Determine Feature Flags
        $feature_flags = FeatureManager::determine_flags($bot_settings);

        // Prepare Frontend Config
        $frontend_config = Configurator::prepare_config($bot_id, $bot_post, $bot_settings, $feature_flags);
        
        // Add plugin asset URLs to the config for the embed script
        $version = defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.0.0';
        $frontend_config['assetUrls'] = array_merge(AIPKit_Shared_Assets_Manager::get_public_asset_urls(), [
            'css' => WPAICG_PLUGIN_URL . 'dist/css/public-main.bundle.css?ver=' . $version,
            'mainJs' => WPAICG_PLUGIN_URL . 'dist/js/public-main.bundle.js?ver=' . $version,
        ]);
        
        // Render the HTML for the chatbot
        $renderer = new Renderer();
        $rendered_html = $renderer->render_chatbot_html($bot_id, $bot_settings, $feature_flags, $frontend_config);

        // Create the final response object with both config and HTML
        $response_data = [
            'config' => $frontend_config,
            'html'   => $rendered_html,
        ];

        $response = new WP_REST_Response($response_data, 200);

        // Set the CORS header dynamically based on the check above
        $response->header('Access-Control-Allow-Origin', $origin_to_allow);

        return $response;
    }
}
