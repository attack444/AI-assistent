<?php


namespace WPAICG\Chat\Frontend\Ajax;

use WPAICG\Chat\Admin\Ajax\Traits\Trait_CheckFrontendPermissions;
use WPAICG\Chat\Admin\Ajax\Traits\Trait_SendWPError;
use WPAICG\aipkit_dashboard;
use WPAICG\Chat\Storage\BotStorage;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for chatbot form submissions from the frontend.
 */
class ChatFormSubmissionAjaxHandler {

    use Trait_CheckFrontendPermissions;
    use Trait_SendWPError;

    private $bot_storage;

    public function __construct() {
        if (class_exists(\WPAICG\Chat\Storage\BotStorage::class)) {
            $this->bot_storage = new \WPAICG\Chat\Storage\BotStorage();
        } else {
            $this->bot_storage = null;
        }
    }

    /**
     * AJAX handler for 'aipkit_handle_form_submission'.
     */
    public function ajax_handle_form_submission(): void {

        $permission_check = $this->check_frontend_permissions();
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $post_data = wp_unslash($_POST);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $bot_id = isset($post_data['bot_id']) ? absint($post_data['bot_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $form_id = isset($post_data['form_id']) ? sanitize_text_field($post_data['form_id']) : '';
        $form_title = isset($post_data['form_title']) ? sanitize_text_field($post_data['form_title']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $submitted_data_json = isset($post_data['submitted_data']) ? wp_kses_post($post_data['submitted_data']) : '{}';
        // Optional compatibility payloads from newer frontend bundles.
        $submitted_data_display_json = isset($post_data['submitted_data_display']) ? wp_kses_post($post_data['submitted_data_display']) : '{}';
        $submitted_data_labels_json = isset($post_data['submitted_data_labels']) ? wp_kses_post($post_data['submitted_data_labels']) : '{}';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $conversation_uuid = isset($post_data['conversation_uuid']) ? sanitize_key($post_data['conversation_uuid']) : '';
        
        $user_id = get_current_user_id();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_frontend_permissions().
        $session_id_from_post = isset($post_data['session_id']) ? sanitize_text_field($post_data['session_id']) : '';

        $final_session_id = ''; 
        if (!$user_id) { 
            if (!empty($session_id_from_post)) {
                $final_session_id = $session_id_from_post;
            }
        }
        
        $post_id_from_request = isset($post_data['post_id']) ? absint($post_data['post_id']) : 0;

        if (empty($bot_id) || empty($form_id) || empty($conversation_uuid)) {
            $this->send_wp_error(new WP_Error('missing_params', __('Missing required parameters (bot, form, or conversation ID).', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        $submitted_data = json_decode($submitted_data_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($submitted_data)) {
            $this->send_wp_error(new WP_Error('invalid_submitted_data', __('Invalid submitted form data.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }
        $submitted_data_display = json_decode($submitted_data_display_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($submitted_data_display)) {
            $submitted_data_display = [];
        }
        $submitted_data_labels = json_decode($submitted_data_labels_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($submitted_data_labels)) {
            $submitted_data_labels = [];
        }

        $sanitize_recursive_values = static function ($value) use (&$sanitize_recursive_values) {
            if (is_array($value)) {
                $sanitized = [];
                foreach ($value as $key => $item) {
                    $sanitized_key = sanitize_text_field((string) $key);
                    if ($sanitized_key === '') {
                        continue;
                    }
                    $sanitized[$sanitized_key] = $sanitize_recursive_values($item);
                }
                return $sanitized;
            }
            return sanitize_text_field((string) $value);
        };
        $sanitize_labels_map = static function (array $labels): array {
            $sanitized = [];
            foreach ($labels as $key => $label) {
                $sanitized_key = sanitize_text_field((string) $key);
                if ($sanitized_key === '') {
                    continue;
                }
                $sanitized[$sanitized_key] = sanitize_text_field((string) $label);
            }
            return $sanitized;
        };

        $submitted_data_display = $sanitize_recursive_values($submitted_data_display);
        $submitted_data_labels = $sanitize_labels_map($submitted_data_labels);

        // Backward-compatible fallbacks for older frontend bundles.
        if (empty($submitted_data_display) && !empty($submitted_data)) {
            $submitted_data_display = $sanitize_recursive_values($submitted_data);
        }
        if (empty($submitted_data_labels) && !empty($submitted_data)) {
            foreach ($submitted_data as $field_key => $_unused) {
                $sanitized_key = sanitize_text_field((string) $field_key);
                if ($sanitized_key !== '') {
                    $submitted_data_labels[$sanitized_key] = $sanitized_key;
                }
            }
        }

        if (!$user_id && empty($final_session_id)) {
             $this->send_wp_error(new WP_Error('missing_identifier', __('User or Session ID is required for guests.', 'gpt3-ai-content-generator'), ['status' => 400]));
             return;
        }


        $triggers_enabled = false;
        if (class_exists(\WPAICG\aipkit_dashboard::class)) {
            $triggers_enabled = \WPAICG\aipkit_dashboard::is_pro_plan();
        }

        $trigger_storage_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Storage';
        $trigger_manager_class = '\WPAICG\Lib\Chat\Triggers\AIPKit_Trigger_Manager';
        
        $trigger_handler_function = '\WPAICG\Lib\Chat\Triggers\process_chat_triggers';

        if (!$triggers_enabled || !class_exists($trigger_storage_class) || !class_exists($trigger_manager_class) || !function_exists($trigger_handler_function)) {
            wp_send_json_success(['message' => __('Form submitted.', 'gpt3-ai-content-generator') . ' (' . __('Triggers not active or fully available.', 'gpt3-ai-content-generator') . ')']);
            return;
        }

        if (!$this->bot_storage) {
             $this->send_wp_error(new WP_Error('internal_error', __('Chat system (storage) not ready.', 'gpt3-ai-content-generator'), ['status' => 500]));
            return;
        }
        $bot_settings = $this->bot_storage->get_chatbot_settings($bot_id);
        if (empty($bot_settings)) {
            $this->send_wp_error(new WP_Error('bot_not_found', __('Chatbot configuration not found.', 'gpt3-ai-content-generator'), ['status' => 404]));
            return;
        }

        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
        $http_referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $user_wp_roles = $user_id ? (array) wp_get_current_user()->roles : ['guest'];
        $log_storage_instance = null;
        if (class_exists('\WPAICG\Chat\Storage\LogStorage')) {
            $log_storage_instance = new \WPAICG\Chat\Storage\LogStorage();
        }

        $base_log_data_for_triggers = [
            'bot_id'            => $bot_id,
            'user_id'           => $user_id ?: null,
            'session_id'        => $final_session_id,
            'conversation_uuid' => $conversation_uuid,
            'module'            => 'chat', // This ensures trigger meta-logs go to the right conversation
            'is_guest'          => ($user_id === 0 || $user_id === null),
            'ip_address'        => $client_ip,
            'role'              => $user_wp_roles ? implode(', ', $user_wp_roles) : null,
        ];

        $trigger_context = [
            'event_type'            => 'form_submitted', // Added for clarity
            'bot_id'                => $bot_id,
            'form_id'               => $form_id,
            'form_title'            => $form_title,
            'submitted_data'        => $submitted_data,
            'submitted_data_json'   => $submitted_data_json,
            'submitted_data_display' => $submitted_data_display,
            'submitted_data_display_json' => wp_json_encode($submitted_data_display, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'submitted_data_labels' => $submitted_data_labels,
            'submitted_data_labels_json' => wp_json_encode($submitted_data_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id'               => $user_id ?: null,
            'session_id'            => $final_session_id,
            'conversation_uuid'     => $conversation_uuid,
            'client_ip'             => $client_ip,
            'post_id'               => $post_id_from_request,
            'bot_settings'          => $bot_settings,
            'user_roles'            => $user_wp_roles,
            'current_provider'      => $bot_settings['provider'] ?? null,
            'current_model_id'      => $bot_settings['model'] ?? null,
            'http_referer'          => $http_referer,
            'user_agent'            => $user_agent,
            'log_storage'           => $log_storage_instance,
            'base_log_data'         => $base_log_data_for_triggers, // Pass this populated array
            'module'                => 'chat' // Explicitly set top-level module as well
        ];
        
        try {
            $trigger_storage_instance = new $trigger_storage_class();
            $trigger_manager_instance = new $trigger_manager_class($trigger_storage_instance, $log_storage_instance);
            $result = $trigger_manager_instance->process_event($bot_id, 'form_submitted', $trigger_context);

            $this->emit_chatbot_form_submitted_event(
                $bot_id,
                $form_id,
                $form_title,
                $submitted_data,
                $submitted_data_display,
                $submitted_data_labels,
                $trigger_context,
                $log_storage_instance
            );

            $has_message_to_user = isset($result['message_to_user']) && is_string($result['message_to_user']) && trim($result['message_to_user']) !== '';
            $resume_token = '';
            if (!$has_message_to_user && ($result['status'] ?? 'processed') !== 'blocked') {
                $resume_token = $this->create_form_resume_token(
                    $bot_id,
                    $form_id,
                    $form_title,
                    $conversation_uuid,
                    $user_id,
                    $final_session_id,
                    $submitted_data,
                    $submitted_data_display,
                    $submitted_data_labels
                );
            }

            $response_data = [
                'message' => $has_message_to_user ? $result['message_to_user'] : __('Form processed.', 'gpt3-ai-content-generator'),
                'message_to_user' => $has_message_to_user ? $result['message_to_user'] : null,
                'has_message_to_user' => $has_message_to_user,
                'resume_token' => $resume_token,
                'actions_executed' => $result['actions_executed'] ?? [],
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['status'] ?? 'processed',
            ];

            if ($result['status'] === 'blocked') {
                wp_send_json_error($response_data, 400);
            } else {
                wp_send_json_success($response_data);
            }

        } catch (\Exception $e) {
            $this->send_wp_error(new WP_Error('trigger_processing_error', __('Error processing form submission triggers.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }
    }

    /**
     * Emits the canonical Connected Apps event for a Rules-based chatbot form submission.
     *
     * @param array<string, mixed> $submitted_data
     * @param array<string, mixed> $submitted_data_display
     * @param array<string, string> $submitted_data_labels
     * @param array<string, mixed> $trigger_context
     * @param mixed $log_storage_instance
     * @return void
     */
    private function emit_chatbot_form_submitted_event(
        int $bot_id,
        string $form_id,
        string $form_title,
        array $submitted_data,
        array $submitted_data_display,
        array $submitted_data_labels,
        array $trigger_context,
        $log_storage_instance
    ): void {
        if (!class_exists(\WPAICG\Core\AIPKit_Event_Webhooks::class)) {
            return;
        }

        try {
            $conversation_uuid = sanitize_key((string) ($trigger_context['conversation_uuid'] ?? ''));
            if ($bot_id <= 0 || $form_id === '' || $conversation_uuid === '') {
                return;
            }

            $user_id = absint($trigger_context['user_id'] ?? 0);
            $actor_type = $user_id > 0 ? 'user' : 'guest';
            $bot_name = sanitize_text_field((string) (get_the_title($bot_id) ?: ''));
            $form_name = $form_title !== '' ? $form_title : $form_id;
            $submission_id = 'chat-form-' . wp_generate_uuid4();
            $inputs_for_event = $this->add_common_input_aliases($submitted_data);
            $summary = $this->build_form_submission_summary($submitted_data_display, $submitted_data_labels);
            $message_count = $this->get_conversation_message_count(
                $log_storage_instance,
                $user_id,
                sanitize_text_field((string) ($trigger_context['session_id'] ?? '')),
                $bot_id,
                $conversation_uuid
            );

            $payload = [
                'bot' => [
                    'id' => $bot_id,
                    'name' => $bot_name,
                ],
                'conversation' => [
                    'id' => $conversation_uuid,
                    'message_count' => $message_count,
                ],
                'actor' => [
                    'type' => $actor_type,
                ],
                'form' => [
                    'id' => $form_id,
                    'name' => $form_name,
                ],
                'submission' => [
                    'id' => $submission_id,
                ],
                'inputs' => $inputs_for_event,
                'display_values' => $submitted_data_display,
                'labels' => $submitted_data_labels,
                'summary' => $summary,
                'page' => [
                    'id' => absint($trigger_context['post_id'] ?? 0),
                    'url' => esc_url_raw((string) ($trigger_context['http_referer'] ?? '')),
                ],
                'ai' => [
                    'provider' => sanitize_text_field((string) ($trigger_context['current_provider'] ?? '')),
                    'model' => sanitize_text_field((string) ($trigger_context['current_model_id'] ?? '')),
                ],
            ];

            if ($user_id > 0) {
                $payload['actor']['user_id'] = $user_id;
            }

            \WPAICG\Core\AIPKit_Event_Webhooks::emit(
                'chatbot.form_submitted',
                $payload,
                [
                    'module' => 'chatbot',
                    'origin' => 'frontend_rule_form_submission',
                    'resource' => [
                        'type' => 'chatbot_form_submission',
                        'id' => $submission_id,
                        'label' => sprintf(
                            /* translators: %s: form name */
                            __('Chatbot form submitted: %s', 'gpt3-ai-content-generator'),
                            $form_name
                        ),
                    ],
                    'meta' => [
                        'bot_id' => $bot_id,
                        'conversation_uuid' => $conversation_uuid,
                        'form_id' => $form_id,
                        'submission_id' => $submission_id,
                        'message_count' => $message_count,
                        'is_guest' => $user_id > 0 ? 0 : 1,
                    ],
                    'idempotency_key' => sha1(implode('|', [
                        'chatbot.form_submitted',
                        (string) $bot_id,
                        $conversation_uuid,
                        $form_id,
                        $submission_id,
                    ])),
                ]
            );
        } catch (\Throwable $event_error) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $submitted_data_display
     * @param array<string, string> $submitted_data_labels
     */
    private function build_form_submission_summary(array $submitted_data_display, array $submitted_data_labels): string {
        $summary_lines = [];
        foreach ($submitted_data_display as $field_key => $value) {
            $label = isset($submitted_data_labels[$field_key]) && $submitted_data_labels[$field_key] !== ''
                ? $submitted_data_labels[$field_key]
                : (string) $field_key;
            $display_value = $this->stringify_form_value($value);
            if ($display_value === '') {
                continue;
            }
            $summary_lines[] = sanitize_text_field($label) . ': ' . sanitize_text_field($display_value);
        }

        return implode("\n", $summary_lines);
    }

    /**
     * @param mixed $value
     */
    private function stringify_form_value($value): string {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $item_value = $this->stringify_form_value($item);
                if ($item_value !== '') {
                    $parts[] = $item_value;
                }
            }
            return implode(', ', $parts);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Adds normalized aliases for common CRM fields without removing original field IDs.
     *
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function add_common_input_aliases(array $inputs): array {
        $aliases = [
            'firstname' => 'first_name',
            'first_name' => 'first_name',
            'first-name' => 'first_name',
            'lastname' => 'last_name',
            'last_name' => 'last_name',
            'last-name' => 'last_name',
            'fullname' => 'name',
            'full_name' => 'name',
            'full-name' => 'name',
            'emailaddress' => 'email',
            'email_address' => 'email',
            'email-address' => 'email',
            'phone_number' => 'phone',
            'phone-number' => 'phone',
            'company_name' => 'company',
            'company-name' => 'company',
        ];

        foreach ($inputs as $field_key => $value) {
            $normalized_key = strtolower((string) $field_key);
            $normalized_key = str_replace([' ', '.'], ['_', '_'], $normalized_key);
            $alias = $aliases[$normalized_key] ?? null;
            if ($alias && !array_key_exists($alias, $inputs)) {
                $inputs[$alias] = $value;
            }
        }

        return $inputs;
    }

    private function get_conversation_message_count($log_storage_instance, int $user_id, string $session_id, int $bot_id, string $conversation_uuid): int {
        if (!is_object($log_storage_instance) || !method_exists($log_storage_instance, 'get_conversation_thread_history')) {
            return 0;
        }

        $history = $log_storage_instance->get_conversation_thread_history(
            $user_id > 0 ? $user_id : null,
            $user_id > 0 ? null : $session_id,
            $bot_id,
            $conversation_uuid
        );

        return is_array($history) ? count($history) : 0;
    }

    private function create_form_resume_token(
        int $bot_id,
        string $form_id,
        string $form_title,
        string $conversation_uuid,
        int $user_id,
        string $session_id,
        array $submitted_data,
        array $submitted_data_display,
        array $submitted_data_labels
    ): string {
        $token = wp_generate_password(32, false, false);
        set_transient(
            'aipkit_chat_form_resume_' . sha1($token),
            [
                'bot_id' => $bot_id,
                'form_id' => $form_id,
                'conversation_uuid' => $conversation_uuid,
                'user_id' => $user_id,
                'session_id' => $session_id,
                'form_submission_context' => [
                    'form_id' => $form_id,
                    'form_title' => $form_title,
                    'submitted_data' => $submitted_data,
                    'submitted_data_display' => $submitted_data_display,
                    'submitted_data_labels' => $submitted_data_labels,
                ],
            ],
            5 * MINUTE_IN_SECONDS
        );

        return $token;
    }
}
