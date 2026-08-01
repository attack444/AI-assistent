<?php


namespace WPAICG\Images\Manager\Log;

use WPAICG\Images\AIPKit_Image_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed[]|\WP_Error $result
 */
function log_image_generation_attempt_logic(
    AIPKit_Image_Manager $managerInstance,
    string $conversation_uuid,
    string $extracted_prompt,
    array $request_options_for_log,
    $result,
    ?array $usage_data,
    ?int $user_id,
    ?string $session_id,
    ?string $client_ip,
    ?int $bot_id_for_log = null,
    ?string $user_wp_role = null,
    ?string $bot_response_message_id = null
): void {
    $log_storage = $managerInstance->get_log_storage();
    if (!$log_storage) {
        return;
    }
    $is_error = is_wp_error($result);
    $response_data_for_log = [];
    $message_content = '';
    $request_options_for_log_safe = $request_options_for_log;
    if (isset($request_options_for_log_safe['source_image'])) {
        if (is_array($request_options_for_log_safe['source_image'])) {
            $request_options_for_log_safe['source_image'] = [
                'mime_type' => $request_options_for_log_safe['source_image']['mime_type'] ?? '',
                'size_bytes' => $request_options_for_log_safe['source_image']['size_bytes'] ?? 0,
                'file_name' => $request_options_for_log_safe['source_image']['file_name'] ?? '',
            ];
        } else {
            unset($request_options_for_log_safe['source_image']);
        }
    }
    $logged_request_payload = ['prompt' => $extracted_prompt, 'options' => $request_options_for_log_safe];
    $model_used = $request_options_for_log['model'] ?? 'default_image_model';
    $provider_used = $request_options_for_log['provider'] ?? 'OpenAI';
    if ($is_error) {
        $message_content = "Error generating image: " . $result->get_error_message();
        $response_data_for_log['error_code'] = $result->get_error_code();
    } else {
        $images = $result['images'] ?? [];
        $prompt_snippet = esc_html(mb_substr($extracted_prompt, 0, 50));
        $message_content = sprintf('[Image generated for prompt: "%s..."]', $prompt_snippet);
        $response_data_for_log['type'] = 'image';
        $response_data_for_log['images'] = [];
        foreach ($images as $img_data) {
            $response_data_for_log['images'][] = [ 'url' => $img_data['url'] ?? null, 'revised_prompt' => $img_data['revised_prompt'] ?? null, 'has_b64' => isset($img_data['b64_json']), 'attachment_id' => $img_data['attachment_id'] ?? null, 'media_library_url' => $img_data['media_library_url'] ?? null ];
        }
    }
    if ($bot_response_message_id === null) {
        $bot_response_message_id = 'aipkit-img-err-' . time() . '-' . wp_generate_password(8, false);
    }

    $log_data = [
        'bot_id'             => $bot_id_for_log, 'user_id'            => $user_id ?: null, 'session_id'         => $session_id, 'conversation_uuid' => $conversation_uuid,
        'module'             => $bot_id_for_log ? 'chat' : $managerInstance::MODULE_SLUG, 'is_guest'           => ($user_id === 0 || $user_id === null), 'role'               => $user_wp_role,
        'ip_address'         => $client_ip,
        'message_role'       => 'bot', 'message_content'    => $message_content, 'timestamp'          => time(),
        'ai_provider'        => $provider_used, 'ai_model'           => $model_used, 'usage'              => $usage_data,
        'message_id'         => $bot_response_message_id,
        'request_payload'    => $logged_request_payload, 'response_data'      => $response_data_for_log,
    ];
    $log_result = $log_storage->log_message($log_data);
}
