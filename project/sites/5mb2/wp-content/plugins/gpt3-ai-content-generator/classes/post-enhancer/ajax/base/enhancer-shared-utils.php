<?php


namespace WPAICG\PostEnhancer\Ajax\Base;

use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\SEO\AIPKit_SEO_Helper;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses core WordPress hook names.

function get_post_content_snippet_logic(\WP_Post $post, int $length = 500): string
{
    $content_raw = $post->post_content;
    $content_trimmed = wp_strip_all_tags($content_raw);
    $content_trimmed = mb_substr($content_trimmed, 0, $length);
    return trim($content_trimmed);
}

/**
 * Gets the full, clean text content of a post.
 * @param \WP_Post $post The post object.
 * @return string The clean text content.
 */
function get_post_full_content(\WP_Post $post): string
{
    $content = $post->post_content;
    $content = apply_filters('the_content', $content);
    $content = wp_strip_all_tags($content, true);
    $content = strip_shortcodes($content);
    $content = preg_replace('/\s+/', ' ', $content);
    return trim($content);
}

/**
 * Returns the value the generated suggestions would replace.
 */
function get_current_suggestion_value_logic(string $type, \WP_Post $post): string
{
    switch ($type) {
        case 'title':
            return wp_strip_all_tags((string) $post->post_title);
        case 'excerpt':
            return wp_strip_all_tags((string) $post->post_excerpt);
        case 'meta':
            return AIPKit_SEO_Helper::get_meta_description($post->ID);
        case 'tags':
            return AIPKit_SEO_Helper::get_tags_as_string($post->ID);
        default:
            return '';
    }
}

function generate_suggestions_logic(string $type, \WP_Post $post, string $final_prompt): void
{
    $global_config = AIPKit_Providers::get_default_provider_config();
    $ai_params = AIPKIT_AI_Settings::get_ai_parameters();
    $provider = $global_config['provider'];
    $model = $global_config['model'];

    $ai_caller = new AIPKit_AI_Caller();
    $messages = [['role' => 'user', 'content' => $final_prompt]];

    $result = $ai_caller->make_standard_call(
        $provider,
        $model,
        $messages,
        $ai_params,
        null,
        ['post_id' => $post->ID]
    );

    $suggestions_raw = '';
    $usage_data = null;
    $request_payload_log = null;

    if (is_wp_error($result)) {
        $error_message = 'AI Error: ' . $result->get_error_message();
        $error_data = $result->get_error_data() ?? [];
        $request_payload_log = $error_data['request_payload'] ?? null;
        log_enhancer_interaction_logic(
            $post->ID,
            $type,
            $final_prompt,
            $error_message,
            $provider,
            $model,
            null,
            $request_payload_log
        );
        wp_send_json_error(['message' => $result->get_error_message()], 500);
        return;
    } else {
        $suggestions_raw = $result['content'] ?? '';
        $usage_data = $result['usage'] ?? null;
        $request_payload_log = $result['request_payload_log'] ?? null;
    }

    $suggestions = [];
    $lines = explode("\n", $suggestions_raw);
    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^[\d\*\-\.]+\s*/', '', $line);
        if (preg_match('/^"(.*)"$/', $line, $matches)) {
            $line = $matches[1];
        }
        if (!empty($line)) {
            $suggestions[] = $line;
        }
        if (count($suggestions) >= 5) {
            break;
        }
    }

    $log_content = empty($suggestions) ? "(No valid suggestions generated)" : implode("\n", $suggestions);
    log_enhancer_interaction_logic(
        $post->ID,
        $type,
        $final_prompt,
        $log_content,
        $provider,
        $model,
        $usage_data,
        $request_payload_log
    );

    if (empty($suggestions)) {
        /* translators: %s: The type of suggestions that were expected */
        wp_send_json_error(['message' => sprintf(__('AI did not generate any valid %s suggestions.', 'gpt3-ai-content-generator'), $type)], 500);
        return;
    }

    wp_send_json_success([
        'suggestions' => $suggestions,
        'current_value' => get_current_suggestion_value_logic($type, $post),
    ]);
}

function log_enhancer_interaction_logic(int $post_id, string $type, string $prompt, string $response_content, string $provider, string $model, ?array $usage, ?array $request_payload): void
{
    if (!class_exists(LogStorage::class)) {
        return;
    }
    $log_storage = new LogStorage();
    $user_id = get_current_user_id();
    $user_wp_role = $user_id ? implode(', ', wp_get_current_user()->roles) : null;
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
    $conversation_uuid = 'enhancer-' . $type . '-' . $post_id . '-' . time();

    $log_data = [
        'bot_id'             => null,
        'user_id'            => $user_id ?: null,
        'session_id'         => null,
        'conversation_uuid'  => $conversation_uuid,
        'module'             => 'ai_post_enhancer',
        'is_guest'           => false,
        'role'               => $user_wp_role,
        'ip_address'         => $ip_address,
        'message_role'       => 'bot',
        'message_content'    => sprintf("Generated %s suggestions for Post ID: %d.\nPrompt Snippet: %s...\nResult:\n%s", $type, $post_id, mb_substr($prompt, 0, 100), $response_content),
        'timestamp'          => time(),
        'ai_provider'        => $provider,
        'ai_model'           => $model,
        'usage'              => $usage,
        'request_payload'    => $request_payload,
    ];

    $log_result = $log_storage->log_message($log_data);

}

/**
 * Logs a bulk Post Enhancer update (single-field step) into the shared Admin Logs storage.
 * The content format is aligned with the Post Enhancer log renderer expectations:
 * it includes lines for "Post ID:", "Prompt Snippet:", and "Result:" so the UI can parse and display nicely.
 */
function log_enhancer_bulk_update_logic(int $post_id, string $field, string $prompt, string $response_content, string $provider, string $model, ?array $usage, ?array $request_payload, ?string $conversation_uuid_override = null): void
{
    if (!class_exists(LogStorage::class)) {
        return;
    }
    $log_storage = new LogStorage();
    $user_id = get_current_user_id();
    $user_wp_role = $user_id ? implode(', ', wp_get_current_user()->roles) : null;
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
    // Use provided conversation UUID to aggregate all bulk step messages into one record
    $conversation_uuid = $conversation_uuid_override && is_string($conversation_uuid_override)
        ? substr(sanitize_key($conversation_uuid_override), 0, 36)
        : ('enhancer-bulk-' . $field . '-' . $post_id . '-' . time());

    $message_content = sprintf(
        "Assistant updated %s for Post ID: %d.\nPrompt Snippet: %s...\nResult:\n%s",
        $field,
        $post_id,
        mb_substr($prompt, 0, 100),
        $response_content
    );

    $log_data = [
        'bot_id'             => null,
        'user_id'            => $user_id ?: null,
        'session_id'         => null,
        'conversation_uuid'  => $conversation_uuid,
        'module'             => 'ai_post_enhancer',
        'is_guest'           => false,
        'role'               => $user_wp_role,
        'ip_address'         => $ip_address,
        'message_role'       => 'bot',
        'message_content'    => $message_content,
        'timestamp'          => time(),
        'ai_provider'        => $provider,
        'ai_model'           => $model,
        'usage'              => $usage,
        'request_payload'    => $request_payload,
    ];

    $log_storage->log_message($log_data);
}
