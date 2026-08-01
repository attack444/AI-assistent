<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(AIPKit_Content_Writer_Output_Cleaner::class)) {
    $aipkit_output_cleaner_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-content-writer-output-cleaner.php';
    if (file_exists($aipkit_output_cleaner_path)) {
        require_once $aipkit_output_cleaner_path;
    }
}

class AIPKit_PostEnhancer_Process_Text extends AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_permissions('aipkit_process_enhancer_text_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_error_response($permission_check);
            return;
        }
        $feature_permission = $this->check_editor_context_permissions();
        if (is_wp_error($feature_permission)) {
            $this->send_error_response($feature_permission);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked in check_permissions(); AIPKit_Prompt_Sanitizer preserves literal HTML while sanitizing prompt text.
        $final_prompt = isset($_POST['final_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($_POST['final_prompt'])) : '';
        // text_to_process is still useful for context but the prompt is king
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_permissions.
        $text_to_process = isset($_POST['text_to_process']) ? wp_kses_post(wp_unslash($_POST['text_to_process'])) : '';

        if (empty($text_to_process) || empty($final_prompt)) {
            $this->send_error_response(new WP_Error('missing_params', __('Text and a prompt are required.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        // AI Call setup
        $global_config = AIPKit_Providers::get_default_provider_config();
        $ai_params = AIPKIT_AI_Settings::get_ai_parameters();
        $provider = $global_config['provider'];
        $model = $global_config['model'];
        $ai_caller = new AIPKit_AI_Caller();
        $messages = [['role' => 'user', 'content' => $final_prompt]];

        $result = $ai_caller->make_standard_call($provider, $model, $messages, $ai_params);
        if (is_wp_error($result)) {
            $this->send_error_response($result);
            return;
        }

        $new_text_raw = $result['content'] ?? '';

        $html_content = AIPKit_Content_Writer_Output_Cleaner::convert_basic_markdown_to_html((string) $new_text_raw);

        wp_send_json_success(['text' => $html_content]);
    }
}
