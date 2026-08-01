<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\AIPKit_Providers;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Keyword_Prompt_Builder;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver;
use WP_Error;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\load_smart_seo_keyword_resolver_logic;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\merge_smart_seo_usage_logic;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\smart_seo_keyword_resolution_response_fields_logic;

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_smart_seo_keyword_helper_path = __DIR__ . '/shared/methods.php';
if (file_exists($aipkit_smart_seo_keyword_helper_path)) {
    require_once $aipkit_smart_seo_keyword_helper_path;
}

/**
 * Handles the dedicated AJAX action for generating an SEO focus keyword.
 */
class AIPKit_Content_Writer_Generate_Keyword_Action extends AIPKit_Content_Writer_Base_Ajax_Action
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
            'provider_raw' => $provider_raw,
            'model' => $model,
            'prompt_mode' => $prompt_mode,
            'custom_prompt' => $custom_keyword_prompt,
        ] = $this->get_content_writer_generation_request('custom_keyword_prompt');

        if (empty($generated_content) || empty($final_title) || empty($provider_raw) || empty($model)) {
            $this->send_wp_error(new WP_Error('missing_keyword_data', 'Missing required data for keyword generation.', ['status' => 400]));
            return;
        }

        $provider = AIPKit_Providers::normalize_provider_label($provider_raw);

        if (!class_exists(AIPKit_Content_Writer_Summarizer::class) || !class_exists(AIPKit_Content_Writer_Keyword_Prompt_Builder::class) || !$this->get_ai_caller()) {
            $this->send_wp_error(new WP_Error('missing_keyword_dependencies', 'A component required for keyword generation is missing.', ['status' => 500]));
            return;
        }

        $content_summary = AIPKit_Content_Writer_Summarizer::summarize($generated_content);
        $keyword_user_prompt = AIPKit_Content_Writer_Keyword_Prompt_Builder::build($final_title, $content_summary, $prompt_mode, $custom_keyword_prompt);
        $keyword_system_instruction = 'You are an SEO expert. Your task is to provide the single best focus keyword for a piece of content.';
        $keyword_ai_params = [];

        [$keyword_system_instruction, $keyword_ai_params, $keyword_instruction_context] = $this->prepare_content_writer_vector_context(
            $keyword_user_prompt,
            $provider,
            $keyword_system_instruction,
            $keyword_ai_params
        );
        $keyword_ai_params['top_p'] = null;

        $keyword_result = $this->get_ai_caller()->make_standard_call(
            $provider,
            $model,
            [['role' => 'user', 'content' => $keyword_user_prompt]],
            $keyword_ai_params,
            $keyword_system_instruction,
            $keyword_instruction_context
        );

        if (is_wp_error($keyword_result)) {
            $this->send_wp_error($keyword_result);
            return;
        }

        $focus_keyword = !empty($keyword_result['content']) ? trim(str_replace(['"', "'", '.'], '', $keyword_result['content'])) : null;
        if (empty($focus_keyword)) {
            $this->send_wp_error(new WP_Error('keyword_gen_empty', 'AI did not return a valid focus keyword.', ['status' => 500]));
            return;
        }

        $keyword_usage = $keyword_result['usage'] ?? null;
        $smart_seo_keyword_resolution = [];
        load_smart_seo_keyword_resolver_logic();
        if (class_exists(AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver::class) && $this->get_ai_caller()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
            $seo_score_improvement_enabled = isset($_POST['seo_score_improvement_enabled']) ? sanitize_text_field(wp_unslash($_POST['seo_score_improvement_enabled'])) : '0';
            $seo_score_disabled_rules = '[]';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
            if (isset($_POST['seo_score_disabled_rules']) && class_exists(AIPKit_Content_Writer_SEO_Config::class)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in check_module_access_permissions; sanitize_disabled_rules() sanitizes the nested rules payload after wp_unslash().
                $raw_seo_score_disabled_rules = wp_unslash($_POST['seo_score_disabled_rules']);
                $seo_score_disabled_rules = AIPKit_Content_Writer_SEO_Config::sanitize_disabled_rules($raw_seo_score_disabled_rules);
            }
            $smart_seo_config = [
                'seo_score_improvement_enabled' => $seo_score_improvement_enabled,
                'seo_score_continue_until_target' => '1',
                'seo_score_target' => '100',
                'seo_score_max_passes' => '3',
                'seo_score_profile' => 'auto',
                'seo_score_disabled_rules' => $seo_score_disabled_rules,
                'ai_provider' => $provider,
                'ai_model' => $model,
                'content_title' => $final_title,
            ];
            $keyword_resolver = new AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver();
            $keyword_resolution = $keyword_resolver->maybe_resolve_keyword(
                $focus_keyword,
                $smart_seo_config,
                $this->get_ai_caller(),
                [
                    'ai_provider' => $provider,
                    'ai_model' => $model,
                    'topic' => $final_title,
                    'title' => $final_title,
                    'content_summary' => $content_summary,
                ]
            );

            if (!empty($keyword_resolution['changed'])) {
                $focus_keyword = (string) $keyword_resolution['keyword'];
                $keyword_usage = merge_smart_seo_usage_logic($keyword_usage, $keyword_resolution['usage'] ?? null);
                $keyword_resolution['source'] = 'generated';
                $keyword_resolution['resolved_content_title'] = $final_title;
                $smart_seo_keyword_resolution = $keyword_resolution;
            }
        }

        $conversation_uuid = $this->log_content_writer_generation_step(
            $provider,
            $model,
            'Generate Focus Keyword',
            [
                'title' => $final_title,
                'prompt_mode' => $prompt_mode,
                'custom_keyword_prompt' => $custom_keyword_prompt,
            ],
            $focus_keyword,
            $keyword_usage,
            $keyword_user_prompt,
            $keyword_ai_params,
            $keyword_system_instruction,
            $keyword_result
        );

        wp_send_json_success(array_merge([
            'focus_keyword' => $focus_keyword,
            'usage' => $keyword_usage,
            'conversation_uuid' => $conversation_uuid,
        ], smart_seo_keyword_resolution_response_fields_logic($smart_seo_keyword_resolution)));
    }

}
