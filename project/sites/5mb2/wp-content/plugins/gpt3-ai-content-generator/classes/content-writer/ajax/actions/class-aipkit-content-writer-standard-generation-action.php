<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load the new modular logic files
$shared_path = __DIR__ . '/shared/';
require_once $shared_path . 'methods.php';

$standard_gen_path = __DIR__ . '/standard-generation/';
require_once $standard_gen_path . 'methods.php';


/**
 * Handles the AJAX action for standard (non-streaming) content generation.
 * This class now acts as an orchestrator for modularized logic functions.
 */
class AIPKit_Content_Writer_Standard_Generation_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    /**
     * Handles the AJAX request for standard content generation by orchestrating calls to modular functions.
     */
    public function handle()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in Shared\validate_and_normalize_input_logic().
        $settings = isset($_POST) ? wp_unslash($_POST) : [];

        // 1. Validate input and check permissions
        $validated_params = Shared\validate_and_normalize_input_logic($this, $settings);
        if (is_wp_error($validated_params)) {
            $this->send_wp_error($validated_params);
            return;
        }

        $this->maybe_extend_execution_limits(300);

        Shared\maybe_update_gsheets_row_status_logic($validated_params, 'Queued on');

        // 2. Check for required dependencies (AI Caller, Logger)
        if (!$this->ai_caller) {
            $this->send_wp_error(new WP_Error('ai_caller_missing', __('AI processing component is unavailable.', 'gpt3-ai-content-generator')), 500);
            return;
        }

        $resolved_keyword_params = Shared\resolve_smart_seo_keywords_logic(
            $validated_params,
            $this->get_ai_caller(),
            [
                'topic' => $validated_params['content_title'] ?? '',
                'title' => $validated_params['content_title'] ?? '',
            ]
        );
        $validated_params = $resolved_keyword_params['params'];
        $validated_params['smart_seo_keyword_resolution'] = $resolved_keyword_params['resolution'];

        // 3. Build prompts
        $prompts = Shared\build_prompts_logic($validated_params);
        if (is_wp_error($prompts)) {
            $this->send_wp_error($prompts);
            return;
        }

        $final_title = $validated_params['content_title'];
        $final_user_prompt = str_replace('{topic}', $final_title, $prompts['user_prompt']);

        // 4. Prepare AI parameters
        $ai_params_override = Shared\prepare_ai_params_logic($validated_params);

        // 5. Determine conversation UUID (reuse if provided, else create)
        $conversation_uuid = isset($settings['conversation_uuid']) && !empty($settings['conversation_uuid'])
            ? sanitize_text_field((string) $settings['conversation_uuid'])
            : wp_generate_uuid4();
        // Attach to params so the initial log uses the same conversation
        $validated_params['conversation_uuid'] = $conversation_uuid;
        Shared\log_initial_request_logic($this, $validated_params, 'AJAX');

        // 6. Make the AI call
        $ai_result = StandardGeneration\call_ai_provider_logic(
            $this,
            $validated_params['provider'],
            $validated_params['model'],
            [['role' => 'user', 'content' => $final_user_prompt]], // Use the final prompt
            $ai_params_override,
            $prompts['system_instruction']
        );

        // 7. Handle the response (success or error)
        if (is_wp_error($ai_result)) {
            StandardGeneration\handle_error_response_logic($this, $ai_result, $validated_params, $conversation_uuid);
        } else {
            StandardGeneration\handle_success_response_logic($this, $ai_result, $validated_params, $conversation_uuid);
        }
    }
}
