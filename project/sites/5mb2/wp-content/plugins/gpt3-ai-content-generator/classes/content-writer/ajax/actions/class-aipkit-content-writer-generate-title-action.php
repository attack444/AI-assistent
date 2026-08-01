<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load the new modular logic files
$logic_path = __DIR__ . '/generate-title/';
require_once $logic_path . 'methods.php';

$shared_logic_path = __DIR__ . '/shared/';
require_once $shared_logic_path . 'methods.php';


/**
 * Handles the AJAX action for generating a new title for content.
 * This class now orchestrates calls to modularized logic functions.
 */
class AIPKit_Content_Writer_Generate_Title_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    /**
     * Handles the AJAX request to generate a title.
     */
    public function handle()
    {
        // 1. Validate input and permissions
        $validated_params = GenerateTitle\validate_and_normalize_input_logic($this);
        if (is_wp_error($validated_params)) {
            $this->send_wp_error($validated_params);
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

        // 2. Build the prompt for the AI
        $prompts = GenerateTitle\build_title_prompt_logic($validated_params);

        // 3. Prepare AI-specific parameters
        $ai_params_override = GenerateTitle\prepare_ai_params_logic($validated_params);

        // 4. Call the AI provider
        $ai_result = GenerateTitle\call_title_generator_logic(
            $this,
            $validated_params['provider'],
            $validated_params['model'],
            [['role' => 'user', 'content' => $prompts['user_prompt']]],
            $ai_params_override,
            $prompts['system_instruction'],
            $validated_params // Pass the full form data for vector support
        );

    // 5. Handle the AI response (success or error) and log under conversation if provided
    GenerateTitle\handle_title_response_logic($this, $ai_result, $validated_params, $prompts, $ai_params_override);
    }
}
