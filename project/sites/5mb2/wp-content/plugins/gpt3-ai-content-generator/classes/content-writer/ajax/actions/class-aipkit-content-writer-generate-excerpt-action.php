<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\AIPKit_Providers;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Excerpt_Prompt_Builder;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Content_Writer_Generate_Excerpt_Action extends AIPKit_Content_Writer_Base_Ajax_Action
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
            'custom_prompt' => $custom_excerpt_prompt,
        ] = $this->get_content_writer_generation_request('custom_excerpt_prompt');

        if (empty($generated_content) || empty($final_title) || empty($provider_raw) || empty($model)) {
            $this->send_wp_error(new WP_Error('missing_excerpt_data', 'Missing required data for excerpt generation.', ['status' => 400]));
            return;
        }

        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);

        if (
            !class_exists(AIPKit_Content_Writer_Summarizer::class) ||
            !class_exists(AIPKit_Content_Writer_Excerpt_Prompt_Builder::class) ||
            !$this->get_ai_caller()
        ) {
            $this->send_wp_error(new WP_Error('missing_excerpt_dependencies', 'A component required for excerpt generation is missing.', ['status' => 500]));
            return;
        }

        $content_summary = AIPKit_Content_Writer_Summarizer::summarize($generated_content);
        $excerpt_user_prompt = AIPKit_Content_Writer_Excerpt_Prompt_Builder::build(
            $final_title,
            $content_summary,
            $keywords,
            $prompt_mode,
            $custom_excerpt_prompt
        );
        $excerpt_system_instruction = 'You are an expert copywriter. Your task is to provide an engaging excerpt for a piece of content.';

        $excerpt_ai_params = [];

        [$excerpt_system_instruction, $excerpt_ai_params, $excerpt_instruction_context] = $this->prepare_content_writer_vector_context(
            $excerpt_user_prompt,
            $provider,
            $excerpt_system_instruction,
            $excerpt_ai_params
        );
        $excerpt_ai_params['top_p'] = null;

        $excerpt_result = $this->get_ai_caller()->make_standard_call(
            $provider,
            $model,
            [['role' => 'user', 'content' => $excerpt_user_prompt]],
            $excerpt_ai_params,
            $excerpt_system_instruction,
            $excerpt_instruction_context
        );

        if (is_wp_error($excerpt_result)) {
            $this->send_wp_error($excerpt_result);
            return;
        }

        $excerpt = !empty($excerpt_result['content']) ? trim(str_replace(['"', "'"], '', $excerpt_result['content'])) : null;
        if (empty($excerpt)) {
            $this->send_wp_error(new WP_Error('excerpt_gen_empty', 'AI did not return a valid excerpt.', ['status' => 500]));
            return;
        }

        $conversation_uuid = $this->log_content_writer_generation_step(
            $provider,
            $model,
            'Generate Excerpt',
            [
                'title' => $final_title,
                'keywords' => $keywords,
                'prompt_mode' => $prompt_mode,
                'custom_excerpt_prompt' => $custom_excerpt_prompt,
            ],
            $excerpt,
            $excerpt_result['usage'] ?? null,
            $excerpt_user_prompt,
            $excerpt_ai_params,
            $excerpt_system_instruction,
            $excerpt_result
        );

        wp_send_json_success([
            'excerpt' => $excerpt,
            'usage' => $excerpt_result['usage'] ?? null,
            'conversation_uuid' => $conversation_uuid,
        ]);
    }
}
