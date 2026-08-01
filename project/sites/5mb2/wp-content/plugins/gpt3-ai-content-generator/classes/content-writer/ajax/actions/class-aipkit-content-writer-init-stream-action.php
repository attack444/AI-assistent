<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WP_Error;

// Load the new shared and specific logic files
$shared_path = __DIR__ . '/shared/';
require_once $shared_path . 'methods.php';

$init_stream_path = __DIR__ . '/init-stream/';
require_once $init_stream_path . 'methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Handles the AJAX action for initializing a content generation stream.
* This class now orchestrates calls to modularized logic functions.
*/
class AIPKit_Content_Writer_Init_Stream_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    /**
    * Handles the AJAX request to initialize the stream.
    */
    public function handle()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in Shared\validate_and_normalize_input_logic().
        $settings = isset($_POST) ? wp_unslash($_POST) : [];

        // 1. Validate permissions and normalize input
        $validated_params = Shared\validate_and_normalize_input_logic($this, $settings);
        if (is_wp_error($validated_params)) {
            $this->send_wp_error($validated_params);
            return;
        }

        // 2. Ensure SSE Cache class is available
        $cache_check_result = InitStream\ensure_sse_cache_available_logic();
        if (is_wp_error($cache_check_result)) {
            $this->send_wp_error($cache_check_result);
            return;
        }

        $keyword_resolution = [];
        $resolved_keyword_params = Shared\resolve_smart_seo_keywords_logic(
            $validated_params,
            $this->get_ai_caller(),
            [
                'topic' => $validated_params['content_title'] ?? '',
                'title' => $validated_params['content_title'] ?? '',
            ]
        );
        $validated_params = $resolved_keyword_params['params'];
        $keyword_resolution = $resolved_keyword_params['resolution'];

        // 3. Build prompts
        $prompts = Shared\build_prompts_logic($validated_params);
        if (is_wp_error($prompts)) {
            $this->send_wp_error($prompts);
            return;
        }

        // 4. Merge AI Parameters
        $ai_params_for_cache = InitStream\merge_ai_params_logic($validated_params);

        // 5. Build the final cache payload
        $data_to_cache = InitStream\build_cache_payload_logic(
            $prompts['system_instruction'],
            $prompts['user_prompt'],
            $validated_params['provider'],
            $validated_params['model'],
            $ai_params_for_cache,
            $validated_params // Pass all validated params for logging context
        );

        // 6. Write payload to SSE cache
        $cache_key_result = InitStream\write_to_sse_cache_logic($data_to_cache);
        if (is_wp_error($cache_key_result)) {
            $this->send_wp_error($cache_key_result);
            return;
        }

    // 7. Send success response (include conversation_uuid for downstream logging)
        $conversation_uuid = isset($data_to_cache['conversation_uuid']) ? $data_to_cache['conversation_uuid'] : '';
        wp_send_json_success(array_merge(
            ['cache_key' => $cache_key_result, 'conversation_uuid' => $conversation_uuid],
            Shared\smart_seo_keyword_resolution_response_fields_logic($keyword_resolution)
        ));
    }
}
