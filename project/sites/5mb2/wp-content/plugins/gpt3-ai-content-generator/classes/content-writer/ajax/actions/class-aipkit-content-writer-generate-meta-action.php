<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\AIPKit_Providers;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Meta_Prompt_Builder;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the dedicated AJAX action for generating an SEO meta description after main content is created.
 */
class AIPKit_Content_Writer_Generate_Meta_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    public function handle()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        [
            'generated_content' => $generated_content,
            'final_title' => $final_title,
            'keywords' => $keywords,
            'provider_raw' => $provider_raw,
            'model' => $model,
            'prompt_mode' => $prompt_mode,
            'custom_prompt' => $custom_meta_prompt,
        ] = $this->get_content_writer_generation_request('custom_meta_prompt');

        if (empty($generated_content) || empty($final_title) || empty($provider_raw) || empty($model)) {
            $this->send_wp_error(new WP_Error('missing_meta_data', 'Missing required data for meta description generation.', ['status' => 400]));
            return;
        }

        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);

        if (!class_exists(AIPKit_Content_Writer_Summarizer::class) || !class_exists(AIPKit_Content_Writer_Meta_Prompt_Builder::class) || !$this->get_ai_caller()) {
            $this->send_wp_error(new WP_Error('missing_meta_dependencies', 'A component required for meta description generation is missing.', ['status' => 500]));
            return;
        }

        $content_summary = AIPKit_Content_Writer_Summarizer::summarize($generated_content);
        $meta_user_prompt = AIPKit_Content_Writer_Meta_Prompt_Builder::build($final_title, $content_summary, $keywords, $prompt_mode, $custom_meta_prompt);
        $meta_system_instruction = 'You are an SEO expert specializing in writing meta descriptions.';
        $meta_ai_params = [];

        [$meta_system_instruction, $meta_ai_params, $meta_instruction_context] = $this->prepare_content_writer_vector_context(
            $meta_user_prompt,
            $provider,
            $meta_system_instruction,
            $meta_ai_params
        );
        $meta_ai_params['top_p'] = null;

        $meta_result = $this->get_ai_caller()->make_standard_call(
            $provider,
            $model,
            [['role' => 'user', 'content' => $meta_user_prompt]],
            $meta_ai_params,
            $meta_system_instruction,
            $meta_instruction_context
        );

        if (is_wp_error($meta_result)) {
            $this->send_wp_error($meta_result);
            return;
        }

        $meta_description = !empty($meta_result['content']) ? AIPKit_Content_Writer_Output_Cleaner::clean_meta_description((string) $meta_result['content']) : null;
        if (empty($meta_description)) {
            $this->send_wp_error(new WP_Error('meta_gen_empty', 'AI did not return a valid meta description.', ['status' => 500]));
            return;
        }
        $conversation_uuid = $this->log_content_writer_generation_step(
            $provider,
            $model,
            'Generate Meta Description',
            [
                'title' => $final_title,
                'keywords' => $keywords,
                'prompt_mode' => $prompt_mode,
                'custom_meta_prompt' => $custom_meta_prompt,
            ],
            $meta_description,
            $meta_result['usage'] ?? null,
            $meta_user_prompt,
            $meta_ai_params,
            $meta_system_instruction,
            $meta_result
        );

        wp_send_json_success([
            'meta_description' => $meta_description,
            'usage' => $meta_result['usage'] ?? null,
            'conversation_uuid' => $conversation_uuid,
        ]);
    }
}
