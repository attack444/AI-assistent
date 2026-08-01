<?php


namespace WPAICG\ContentWriter\Ajax;

use WPAICG\Dashboard\Ajax\BaseDashboardAjaxHandler;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Base class for Content Writer AJAX actions.
* Initializes common dependencies like LogStorage, AICaller, and VectorStoreManager.
*/
abstract class AIPKit_Content_Writer_Base_Ajax_Action extends BaseDashboardAjaxHandler
{
    public $log_storage;
    public $ai_caller;
    public $vector_store_manager;
    protected $disabled_functions = [];

    public function __construct()
    {
        $this->disabled_functions = $this->get_disabled_functions();

        // Ensure LogStorage is available
        if (class_exists(\WPAICG\Chat\Storage\LogStorage::class)) {
            $this->log_storage = new LogStorage();
        }

        // Ensure AICaller is available
        if (class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $this->ai_caller = new AIPKit_AI_Caller();
        }

        // Ensure VectorStoreManager is available
        if (class_exists(\WPAICG\Vector\AIPKit_Vector_Store_Manager::class)) {
            $this->vector_store_manager = new AIPKit_Vector_Store_Manager();
        }
    }

    /**
    * Public getter for the ai_caller dependency.
    * @return AIPKit_AI_Caller|null
    */
    public function get_ai_caller(): ?AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }

    /**
    * Public getter for the vector_store_manager dependency.
    * @return AIPKit_Vector_Store_Manager|null
    */
    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }

    public function ensure_content_writer_conversation_uuid(string $conversation_uuid): string
    {
        if ($conversation_uuid !== '') {
            return $conversation_uuid;
        }

        return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('aipkit-', true);
    }

    public function build_content_writer_log_base(string $conversation_uuid, string $provider = '', string $model = ''): array
    {
        $current_user = wp_get_current_user();

        return [
            'bot_id' => null,
            'user_id' => get_current_user_id(),
            'session_id' => null,
            'conversation_uuid' => $conversation_uuid,
            'module' => 'content_writer',
            'is_guest' => 0,
            'role' => is_a($current_user, 'WP_User') ? implode(', ', $current_user->roles) : '',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
            'timestamp' => time(),
            'ai_provider' => $provider,
            'ai_model' => $model,
        ];
    }

    public function get_content_writer_request_conversation_uuid(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the content writer nonce before logging.
        return isset($_POST['conversation_uuid']) ? sanitize_text_field(wp_unslash($_POST['conversation_uuid'])) : '';
    }

    public function log_content_writer_generation_step(
        string $provider,
        string $model,
        string $user_message_content,
        array $user_request_payload,
        string $bot_message_content,
        $usage,
        string $user_prompt,
        array $ai_params,
        string $system_instruction,
        array $ai_result
    ): string {
        $conversation_uuid = $this->get_content_writer_request_conversation_uuid();
        if (!$this->log_storage) {
            return $conversation_uuid;
        }

        $conversation_uuid = $this->ensure_content_writer_conversation_uuid($conversation_uuid);
        $base = $this->build_content_writer_log_base($conversation_uuid, $provider, $model);
        $this->log_storage->log_message(array_merge($base, [
            'message_role' => 'user',
            'message_content' => $user_message_content,
            'request_payload' => $user_request_payload,
        ]));

        $bot_log = array_merge($base, [
            'message_role' => 'bot',
            'message_content' => $bot_message_content,
            'usage' => $usage,
            'request_payload' => [
                'provider' => $provider,
                'model' => $model,
                'payload_sent' => [
                    'messages' => [['role' => 'user', 'content' => $user_prompt]],
                    'ai_params' => $ai_params,
                    'system_instruction' => $system_instruction,
                ],
            ],
        ]);
        if (!empty($ai_result['vector_search_scores'])) {
            $bot_log['vector_search_scores'] = $ai_result['vector_search_scores'];
        }
        $this->log_storage->log_message($bot_log);

        return $conversation_uuid;
    }

    public function get_content_writer_generation_request(string $custom_prompt_key): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers verify the content writer nonce; custom prompt text is sanitized by AIPKit_Prompt_Sanitizer.
        return [
            'generated_content' => isset($_POST['generated_content']) ? wp_kses_post(wp_unslash($_POST['generated_content'])) : '',
            'final_title' => isset($_POST['final_title']) ? sanitize_text_field(wp_unslash($_POST['final_title'])) : '',
            'keywords' => isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '',
            'provider_raw' => isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '',
            'model' => isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '',
            'prompt_mode' => isset($_POST['prompt_mode']) ? sanitize_key($_POST['prompt_mode']) : 'standard',
            'custom_prompt' => isset($_POST[$custom_prompt_key]) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($_POST[$custom_prompt_key])) : null,
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    public function prepare_content_writer_vector_context(
        string $user_prompt,
        string $provider,
        string $system_instruction,
        array $ai_params,
        ?array $form_data = null
    ): array {
        if (!function_exists('WPAICG\\Core\\Stream\\Vector\\prepare_vector_standard_call')) {
            $helper_path = WPAICG_PLUGIN_DIR . 'classes/core/stream/vector/fn-prepare-vector-standard-call.php';
            if (file_exists($helper_path)) {
                require_once $helper_path;
            }
        }

        $instruction_context = [];
        if (function_exists('WPAICG\\Core\\Stream\\Vector\\prepare_vector_standard_call')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the content writer nonce before preparing vector context.
            $form_data = $form_data ?? $_POST;
            $prep = \WPAICG\Core\Stream\Vector\prepare_vector_standard_call(
                $this->get_ai_caller(),
                $this->get_vector_store_manager(),
                $user_prompt,
                $form_data,
                $provider,
                $system_instruction,
                $ai_params
            );
            $system_instruction = $prep['system_instruction'] ?? $system_instruction;
            $ai_params = $prep['ai_params'] ?? $ai_params;
            $instruction_context = $prep['instruction_context'] ?? [];
        }

        return [$system_instruction, $ai_params, $instruction_context];
    }

    protected function check_content_writer_or_autogpt_request_access(): bool
    {
        if (!\WPAICG\AIPKit_Role_Manager::user_can_access_module('content-writer') && !\WPAICG\AIPKit_Role_Manager::user_can_access_module('autogpt')) {
            $this->send_wp_error(new WP_Error('permission_denied', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return false;
        }

        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_key(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aipkit_content_writer_nonce') && !wp_verify_nonce($nonce, 'aipkit_nonce') && !wp_verify_nonce($nonce, 'aipkit_automated_tasks_manage_nonce')) {
            $this->send_wp_error(new WP_Error('nonce_failure', __('Security check failed.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return false;
        }

        return true;
    }

    protected function maybe_extend_execution_limits(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $max_execution_time = function_exists('ini_get') ? (int) ini_get('max_execution_time') : 0;
        if ($max_execution_time > 0 && $max_execution_time < $seconds) {
            $this->maybe_set_time_limit($seconds);
            $this->maybe_set_ini_value('max_execution_time', (string) $seconds);
        }

        $socket_timeout = function_exists('ini_get') ? (int) ini_get('default_socket_timeout') : 0;
        if ($socket_timeout > 0 && $socket_timeout < $seconds) {
            $this->maybe_set_ini_value('default_socket_timeout', (string) $seconds);
        }
    }

    private function maybe_set_time_limit(int $seconds): void
    {
        if (!$this->can_use_function('set_time_limit')) {
            return;
        }

        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Content generation requests may legitimately need a longer admin-request window.
        set_time_limit($seconds);
    }

    private function maybe_set_ini_value(string $option_name, string $value): void
    {
        if (!$this->can_use_function('ini_set')) {
            return;
        }

        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Scoped runtime tuning is intentional for long-running admin-triggered generation requests.
        ini_set($option_name, $value);
    }

    private function can_use_function(string $function_name): bool
    {
        return function_exists($function_name) && !in_array($function_name, $this->disabled_functions, true);
    }

    private function get_disabled_functions(): array
    {
        if (!function_exists('ini_get')) {
            return [];
        }

        $disabled_functions = (string) ini_get('disable_functions');
        if ($disabled_functions === '') {
            return [];
        }

        return array_map('trim', explode(',', $disabled_functions));
    }
}
