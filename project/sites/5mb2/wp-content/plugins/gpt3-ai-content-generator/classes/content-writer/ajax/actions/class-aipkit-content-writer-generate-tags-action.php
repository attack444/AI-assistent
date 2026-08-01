<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\AIPKit_Providers;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Tags_Prompt_Builder;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the dedicated AJAX action for generating post tags.
 */
class AIPKit_Content_Writer_Generate_Tags_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_error_response($permission_check);
            return;
        }
        [
            'generated_content' => $generated_content,
            'final_title' => $final_title,
            'keywords' => $keywords,
            'provider_raw' => $provider_raw,
            'model' => $model,
            'prompt_mode' => $prompt_mode,
            'custom_prompt' => $custom_tags_prompt,
        ] = $this->get_content_writer_generation_request('custom_tags_prompt');

        if (empty($generated_content) || empty($final_title) || empty($provider_raw) || empty($model)) {
            $this->send_error_response(new WP_Error('missing_tags_data', 'Missing required data for tags generation.', ['status' => 400]));
            return;
        }

        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);

        if (!class_exists(AIPKit_Content_Writer_Summarizer::class) || !class_exists(AIPKit_Content_Writer_Tags_Prompt_Builder::class) || !$this->get_ai_caller()) {
            $this->send_wp_error(new WP_Error('missing_tags_dependencies', 'A component required for tags generation is missing.', ['status' => 500]));
            return;
        }

        $content_summary = AIPKit_Content_Writer_Summarizer::summarize($generated_content);
        $tags_user_prompt = AIPKit_Content_Writer_Tags_Prompt_Builder::build($final_title, $content_summary, $keywords, $prompt_mode, $custom_tags_prompt);
        $tags_system_instruction = 'You are an SEO expert. Your task is to provide a comma-separated list of relevant tags for a piece of content.';
        $tags_ai_params = [];

        [$tags_system_instruction, $tags_ai_params, $tags_instruction_context] = $this->prepare_content_writer_vector_context(
            $tags_user_prompt,
            $provider,
            $tags_system_instruction,
            $tags_ai_params
        );
        $tags_ai_params['top_p'] = null;

        $tags_result = $this->get_ai_caller()->make_standard_call(
            $provider,
            $model,
            [['role' => 'user', 'content' => $tags_user_prompt]],
            $tags_ai_params,
            $tags_system_instruction,
            $tags_instruction_context
        );

        if (is_wp_error($tags_result)) {
            $this->send_wp_error($tags_result);
            return;
        }

        $tags = !empty($tags_result['content']) ? trim(str_replace(['"', "'"], '', $tags_result['content'])) : null;
        if (empty($tags)) {
            $this->send_wp_error(new WP_Error('tags_gen_empty', 'AI did not return any valid tags.', ['status' => 500]));
            return;
        }
        $conversation_uuid = $this->log_content_writer_generation_step(
            $provider,
            $model,
            'Generate Tags',
            [
                'title' => $final_title,
                'keywords' => $keywords,
                'prompt_mode' => $prompt_mode,
                'custom_tags_prompt' => $custom_tags_prompt,
            ],
            $tags,
            $tags_result['usage'] ?? null,
            $tags_user_prompt,
            $tags_ai_params,
            $tags_system_instruction,
            $tags_result
        );

        wp_send_json_success([
            'tags' => $tags,
            'usage' => $tags_result['usage'] ?? null,
            'conversation_uuid' => $conversation_uuid,
        ]);
    }
}
