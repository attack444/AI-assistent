<?php

namespace WPAICG\Images\Manager\Ajax;

use WPAICG\Images\AIPKit_Image_Manager;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKit_Role_Manager;
use WPAICG\Core\Moderation\AIPKit_Global_Security_Settings;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WPAICG\Core\AIPKit_Content_Moderator;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use function WPAICG\Images\Manager\Utils\parse_edit_source_image_upload_logic;
use WP_Error;
use WPAICG\Shortcodes\AIPKit_Image_Generator_Shortcode;
use WP_Query;
use WPAICG\Images\Providers\Google\GoogleVideoResponseParser;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- ajax_generate_image.php ---
function ajax_generate_image_logic(AIPKit_Image_Manager $managerInstance): void
{
    // Unslash all POST data at the beginning for security
    $post_data = wp_unslash($_POST);
    // Sanitize SERVER variable
    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;

    $user_id = get_current_user_id();
    $is_logged_in = $user_id > 0;
    $session_id_from_post = isset($post_data['session_id']) ? sanitize_text_field($post_data['session_id']) : null;
    $session_id_for_guest = $is_logged_in
        ? null
        : (class_exists(AIPKit_Global_Security_Settings::class)
            ? AIPKit_Global_Security_Settings::resolve_guest_session_id($session_id_from_post, $client_ip)
            : (!empty($session_id_from_post) ? $session_id_from_post : $client_ip));

    $request_time = time();
    $conversation_uuid = 'imagegen-' . $request_time . '-' . wp_generate_password(12, false);
    $error_response = null;
    $usage_data = null;
    $bot_response_message_id = null;

    $nonce_action = 'aipkit_nonce';
    if (isset($post_data['_ajax_nonce']) && wp_verify_nonce(sanitize_key($post_data['_ajax_nonce']), 'aipkit_image_generator_nonce')) {
        $nonce_action = 'aipkit_image_generator_nonce';
    } elseif (!check_ajax_referer($nonce_action, '_ajax_nonce', false)) {
        $error_response = new WP_Error('nonce_failure', __('Security check failed (nonce).', 'gpt3-ai-content-generator'), ['status' => 403]);
        $managerInstance->log_image_generation_attempt($conversation_uuid, $post_data['prompt'] ?? '', $post_data, $error_response, null, $user_id, $session_id_for_guest, $client_ip);
        $managerInstance->send_wp_error($error_response);
        return;
    }

    if ($is_logged_in && !AIPKit_Role_Manager::user_can_access_module($managerInstance::MODULE_SLUG)) {
        $error_response = new WP_Error('permission_denied', __('You do not have permission to use the Image Generator.', 'gpt3-ai-content-generator'), ['status' => 403]);
        $managerInstance->log_image_generation_attempt($conversation_uuid, $post_data['prompt'] ?? '', $post_data, $error_response, null, $user_id, $session_id_for_guest, $client_ip);
        $managerInstance->send_wp_error($error_response);
        return;
    }

    $prompt = isset($post_data['prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($post_data['prompt']) : '';
    if (empty($prompt)) {
        $error_response = new WP_Error('missing_prompt', __('Image prompt cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400]);
        $managerInstance->log_image_generation_attempt($conversation_uuid, $prompt, $post_data, $error_response, null, $user_id, $session_id_for_guest, $client_ip);
        $managerInstance->send_wp_error($error_response);
        return;
    }

    $provider = isset($post_data['provider']) ? sanitize_text_field($post_data['provider']) : 'OpenAI';
    $image_mode = isset($post_data['image_mode']) ? sanitize_key($post_data['image_mode']) : 'generate';
    if (!in_array($image_mode, ['generate', 'edit'], true)) {
        $image_mode = 'generate';
    }

    if (class_exists(AIPKit_Content_Moderator::class)) {
        $moderation_context = [
            'client_ip' => $client_ip,
            'bot_settings' => ['provider' => $provider], // Provide a minimal settings array for the check
            'module' => 'image_generator',
        ];
        $moderation_check = AIPKit_Content_Moderator::check_content($prompt, $moderation_context);
        if (is_wp_error($moderation_check)) {
            // Log the moderation failure and send error response
            $managerInstance->log_image_generation_attempt($conversation_uuid, $prompt, $post_data, $moderation_check, null, $user_id, $session_id_for_guest, $client_ip);
            $managerInstance->send_wp_error($moderation_check);
            return;
        }
    }

    $source_image_payload = null;
    if ($image_mode === 'edit') {
        $edit_provider = strtolower($provider);
        if (!in_array($edit_provider, ['google', 'openai', 'openrouter', 'xai'], true)) {
            $provider_error = new WP_Error(
                'image_edit_provider_unsupported',
                __('Image editing is currently supported only for Google, OpenAI, OpenRouter, and xAI providers.', 'gpt3-ai-content-generator'),
                ['status' => 400]
            );
            $managerInstance->log_image_generation_attempt($conversation_uuid, $prompt, $post_data, $provider_error, null, $user_id, $session_id_for_guest, $client_ip);
            $managerInstance->send_wp_error($provider_error);
            return;
        }

        $source_image_payload = parse_edit_source_image_upload_logic($_FILES);
        if (is_wp_error($source_image_payload)) {
            $managerInstance->log_image_generation_attempt($conversation_uuid, $prompt, $post_data, $source_image_payload, null, $user_id, $session_id_for_guest, $client_ip);
            $managerInstance->send_wp_error($source_image_payload);
            return;
        }
    }

    $num_images_to_generate = isset($post_data['n']) ? absint($post_data['n']) : 1;
    $num_images_to_generate = max(1, $num_images_to_generate);
    $selected_model = isset($post_data['model']) ? sanitize_text_field($post_data['model']) : '';
    $pricing_operation = $image_mode;

    if (strtolower($provider) === 'google' && $selected_model !== '') {
        $google_video_model_ids = [];
        if (class_exists('\WPAICG\AIPKit_Providers')) {
            $google_video_models = \WPAICG\AIPKit_Providers::get_google_video_models();
            if (!empty($google_video_models)) {
                $google_video_model_ids = wp_list_pluck($google_video_models, 'id');
            }
        }

        if (in_array($selected_model, $google_video_model_ids, true) || strpos($selected_model, 'veo') !== false) {
            $pricing_operation = 'video_generate';
        }
    }

    $token_manager = $managerInstance->get_token_manager();
    if ($token_manager) {
        $token_check_result = null;
        $context_id_for_token_check = $is_logged_in ? GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID : GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
        $token_check_result = $token_manager->check_and_reset_tokens(
            $user_id ?: null,
            $session_id_for_guest,
            $context_id_for_token_check,
            'image_generator',
            [
                'provider' => $provider,
                'model' => $selected_model,
                'operation' => $pricing_operation,
                'usage_data' => [
                    'unit_count' => $num_images_to_generate,
                    'image_count' => $num_images_to_generate,
                    'total_units' => $num_images_to_generate,
                ],
                'fallback_units' => $num_images_to_generate * $managerInstance::TOKENS_PER_IMAGE,
            ]
        );

        if (is_wp_error($token_check_result)) {
            $managerInstance->log_image_generation_attempt($conversation_uuid, $prompt, $post_data, $token_check_result, null, $user_id, $session_id_for_guest, $client_ip);
            $managerInstance->send_wp_error($token_check_result);
            return;
        }
    }

    // --- MODIFICATION: Changed user identifier format ---
    $user_identifier = $is_logged_in ? (string)$user_id : 'guest';

    $runtime_options = array_filter([
        'image_mode' => $image_mode,
        'provider' => $provider,
        'model' => $selected_model !== '' ? $selected_model : null,
        'size' => isset($post_data['size']) ? sanitize_text_field($post_data['size']) : null,
        'n' => $num_images_to_generate,
        'quality' => isset($post_data['quality']) ? sanitize_text_field($post_data['quality']) : null,
        'style' => isset($post_data['style']) ? sanitize_text_field($post_data['style']) : null,
        'response_format' => isset($post_data['response_format']) ? sanitize_text_field($post_data['response_format']) : 'url',
        'user' => $user_identifier,
        'aipkit_event_module' => 'image_generator',
        'aipkit_event_origin' => 'image_generator_ajax',
    ], function ($value) { return $value !== null; });

    if ($image_mode === 'edit' && is_array($source_image_payload)) {
        $runtime_options['source_image'] = $source_image_payload;
    }

    $request_options_for_log = $runtime_options;
    if (isset($request_options_for_log['source_image']) && is_array($request_options_for_log['source_image'])) {
        $request_options_for_log['source_image'] = [
            'mime_type' => $request_options_for_log['source_image']['mime_type'] ?? '',
            'size_bytes' => $request_options_for_log['source_image']['size_bytes'] ?? 0,
            'file_name' => $request_options_for_log['source_image']['file_name'] ?? '',
        ];
    }

    if (
        strtolower($provider) === 'openai'
        && AIPKit_Providers::is_openai_gpt_image_model((string) ($runtime_options['model'] ?? ''))
    ) {
        $runtime_options['output_format'] = 'png';
        unset($runtime_options['response_format']);
    }

    $result = $managerInstance->generate_image($prompt, $runtime_options, $is_logged_in ? $user_id : null);
    $images_array = [];
    $videos_array = [];
    $usage_data = null;

    if (!is_wp_error($result)) {
        // Check if this is an async video operation
        if (isset($result['status']) && $result['status'] === 'processing') {

            // Log the attempt as processing
            $managerInstance->log_image_generation_attempt(
                $conversation_uuid,
                $prompt,
                $request_options_for_log,
                $result,
                null, // No usage data yet
                $user_id,
                $session_id_for_guest,
                $client_ip,
                null,
                !$is_logged_in ? null : implode(', ', wp_get_current_user()->roles),
                $bot_response_message_id
            );

            // Return async operation info
            wp_send_json_success([
                'status' => 'processing',
                'operation_name' => $result['operation_name'],
                'message' => $result['message']
            ]);
            return;
        }

        // Handle completed generation (images or videos)
        $images_array = $result['images'] ?? [];
        $videos_array = $result['videos'] ?? [];
        $usage_data = $result['usage'] ?? null;

        $media_generated_count = count($images_array) + count($videos_array);
        $tokens_to_record = $usage_data['total_tokens'] ?? ($media_generated_count * $managerInstance::TOKENS_PER_IMAGE);

        if ($token_manager && $tokens_to_record > 0) {
            $context_id_for_token_record = $is_logged_in ? GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID : GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
            $token_manager->record_token_usage(
                $user_id ?: null,
                $session_id_for_guest,
                $context_id_for_token_record,
                $tokens_to_record,
                'image_generator',
                [
                    'provider' => $provider,
                    'model' => $selected_model,
                    'operation' => !empty($videos_array) ? 'video_generate' : $pricing_operation,
                    'usage_data' => array_merge(
                        is_array($usage_data) ? $usage_data : [],
                        !empty($videos_array)
                            ? ['unit_count' => count($videos_array), 'video_count' => count($videos_array)]
                            : ['unit_count' => $media_generated_count, 'image_count' => $media_generated_count]
                    ),
                ]
            );
        }
    }

    $user_wp_role = !$is_logged_in ? null : implode(', ', wp_get_current_user()->roles);
    $managerInstance->log_image_generation_attempt(
        $conversation_uuid,
        $prompt,
        $request_options_for_log,
        $result,
        $usage_data,
        $user_id,
        $session_id_for_guest,
        $client_ip,
        null,
        $user_wp_role,
        $bot_response_message_id
    );

    if (is_wp_error($result)) {
        $managerInstance->send_wp_error($result);
    } else {
        // Return appropriate success response
        if (!empty($videos_array)) {
            wp_send_json_success([
                /* translators: %d: Number of videos generated. */
                'message' => sprintf(_n('%d video generated successfully.', '%d videos generated successfully.', count($videos_array), 'gpt3-ai-content-generator'), count($videos_array)),
                'videos' => $videos_array
            ]);
        } else {
            wp_send_json_success([
                /* translators: %d: Number of images generated. */
                'message' => sprintf(_n('%d image generated successfully.', '%d images generated successfully.', count($images_array), 'gpt3-ai-content-generator'), count($images_array)),
                'images' => $images_array
            ]);
        }
    }
}

// --- ajax_delete_generated_image.php ---
function ajax_delete_generated_image_logic(): void
{
    check_ajax_referer('aipkit_image_generator_nonce', '_ajax_nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to delete media.', 'gpt3-ai-content-generator')], 403);
        return;
    }
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
    if (empty($attachment_id)) {
        wp_send_json_error(['message' => __('Invalid media ID.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    $post_author_id = get_post_field('post_author', $attachment_id);
    if (get_current_user_id() != $post_author_id) {
        if (!\WPAICG\AIPKit_Role_Manager::user_can_manage_others_content()) {
            wp_send_json_error(['message' => __('You do not have permission to delete this media.', 'gpt3-ai-content-generator')], 403);
            return;
        }
    }

    // Check if this is an AI-generated image or video
    $is_aipkit_image = get_post_meta($attachment_id, '_aipkit_generated_image', true);
    $is_aipkit_video = get_post_meta($attachment_id, '_aipkit_generated_video', true);

    if ($is_aipkit_image !== '1' && $is_aipkit_video !== '1') {
        wp_send_json_error(['message' => __('This media was not generated by AI Power.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    $deleted = wp_delete_attachment($attachment_id, true);

    if ($deleted === false) {
        $media_type = ($is_aipkit_video === '1') ? __('video', 'gpt3-ai-content-generator') : __('image', 'gpt3-ai-content-generator');
        /* translators: %s: media type (image or video) */
        wp_send_json_error(['message' => sprintf(__('Failed to delete the %s from the media library.', 'gpt3-ai-content-generator'), $media_type)], 500);
    } else {
        $media_type = ($is_aipkit_video === '1') ? __('Video', 'gpt3-ai-content-generator') : __('Image', 'gpt3-ai-content-generator');
        /* translators: %s: media type (Image or Video) */
        wp_send_json_success(['message' => sprintf(__('%s deleted successfully.', 'gpt3-ai-content-generator'), $media_type)]);
    }
}

// --- ajax_load_more_image_history.php ---
function ajax_load_more_image_history_logic(): void
{
    check_ajax_referer('aipkit_image_generator_nonce', '_ajax_nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to view history.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    $page = isset($_POST['page']) ? absint($_POST['page']) : 2; // Start from page 2
    $shortcode_mode = isset($_POST['shortcode_mode']) ? sanitize_key(wp_unslash((string) $_POST['shortcode_mode'])) : 'both';
    if (!in_array($shortcode_mode, ['generate', 'edit', 'both'], true)) {
        $shortcode_mode = 'both';
    }
    $allow_edit_action = in_array($shortcode_mode, ['edit', 'both'], true);
    $user_id = get_current_user_id();
    if (!class_exists(AIPKit_Image_Generator_Shortcode::class)) {
        $shortcode_path = WPAICG_PLUGIN_DIR . 'classes/shortcodes/class-aipkit-image-generator-shortcode.php';
        if (file_exists($shortcode_path)) {
            require_once $shortcode_path;
        }
    }
    if (!class_exists(AIPKit_Image_Generator_Shortcode::class)) {
        wp_send_json_error(['message' => __('Image history renderer is unavailable.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    $query = new WP_Query(AIPKit_Image_Generator_Shortcode::build_history_query_args($user_id, $page));

    $html_items = '';
    ob_start();
    if ($query->have_posts()) {
        while ($query->have_posts()) : $query->the_post();
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_history_item() returns plugin-generated markup with escaped dynamic values.
            echo AIPKit_Image_Generator_Shortcode::render_history_item((int) get_the_ID(), $allow_edit_action);
        endwhile;
    }
    $html_items = ob_get_clean();
    wp_reset_postdata();

    $has_more = ($page < $query->max_num_pages);

    wp_send_json_success([
        'html' => $html_items,
        'has_more' => $has_more
    ]);
}

// --- ajax_check_video_status.php ---
function ajax_check_video_status_logic(AIPKit_Image_Manager $managerInstance): void
{
    // Unslash all POST data at the beginning for security
    $post_data = wp_unslash($_POST);

    $user_id = get_current_user_id();
    $is_logged_in = $user_id > 0;

    // Check nonce
    if (!isset($post_data['_ajax_nonce']) || !wp_verify_nonce(sanitize_key($post_data['_ajax_nonce']), 'aipkit_image_generator_nonce')) {
        wp_send_json_error(['message' => __('Security check failed (nonce).', 'gpt3-ai-content-generator')], 403);
        return;
    }

    // Check permissions (same as generate_image)
    if ($is_logged_in && !AIPKit_Role_Manager::user_can_access_module($managerInstance::MODULE_SLUG)) {
        wp_send_json_error(['message' => __('You do not have permission to check video status.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    // Get required parameters
    $operation_name = isset($post_data['operation_name']) ? sanitize_text_field($post_data['operation_name']) : '';
    $model_id = isset($post_data['model_id']) ? sanitize_text_field($post_data['model_id']) : '';
    $prompt = isset($post_data['prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($post_data['prompt']) : '';

    if (empty($operation_name) || empty($model_id)) {
        wp_send_json_error(['message' => __('Missing required parameters for video status check.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    // Get API key from server-side provider configuration (secure)
    if (!class_exists('WPAICG\AIPKit_Providers')) {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($providers_path)) {
            require_once $providers_path;
        }
    }

    if (!class_exists('WPAICG\AIPKit_Providers')) {
        wp_send_json_error(['message' => __('Provider configuration not available.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    $api_params = \WPAICG\AIPKit_Providers::get_provider_data('Google');

    if (empty($api_params['api_key'])) {
        wp_send_json_error(['message' => __('Google API Key is not configured.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    // Prepare API params with defaults
    $api_params = array_merge($api_params, [
        'base_url' => $api_params['base_url'] ?? 'https://generativelanguage.googleapis.com',
        'api_version' => $api_params['api_version'] ?? 'v1beta'
    ]);

    // Verify all required classes are loaded
    $response_parser_exists = class_exists(GoogleVideoResponseParser::class);
    $url_builder_exists = class_exists('WPAICG\Images\Providers\Google\GoogleVideoUrlBuilder');

    if (!$response_parser_exists) {
        wp_send_json_error(['message' => __('Video response parser not available.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    if (!$url_builder_exists) {
        wp_send_json_error(['message' => __('Video URL builder not available.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    // Check the operation status - now includes prompt and user information
    try {
        $status_result = GoogleVideoResponseParser::check_operation_status(
            $operation_name,
            $model_id,
            $api_params,
            $prompt,
            $is_logged_in ? $user_id : null
        );
    } catch (Error $e) {
        wp_send_json_error(['message' => 'Internal error during video status check: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error during video status check: ' . $e->getMessage()]);
        return;
    }

    if (is_wp_error($status_result)) {
        wp_send_json_error(['message' => $status_result->get_error_message()]);
        return;
    }
    // Handle the different response types
    if (isset($status_result['status'])) {
        if ($status_result['status'] === 'processing') {
            // Still processing
            wp_send_json_success([
                'status' => 'processing',
                'message' => $status_result['message']
            ]);
        } elseif ($status_result['status'] === 'completed') {
            // Completed - record token usage and return video data
            $videos_array = $status_result['videos'] ?? [];
            $usage_data = $status_result['usage'] ?? null;

            // Record token usage when video generation completes
            $token_manager = $managerInstance->get_token_manager();
            if ($token_manager && !empty($videos_array)) {
                $videos_generated_count = count($videos_array);
                $tokens_to_record = $usage_data['total_tokens'] ?? ($videos_generated_count * $managerInstance::TOKENS_PER_IMAGE);

                if ($tokens_to_record > 0) {
                    $context_id_for_token_record = $is_logged_in ? GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID : GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;

                    // Get session ID for guests (this should match the session from the original generation request)
                    $session_id_for_guest = null;
                    if (!$is_logged_in) {
                        $posted_session_id = isset($post_data['session_id']) ? sanitize_text_field($post_data['session_id']) : null;
                        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
                        $session_id_for_guest = class_exists(AIPKit_Global_Security_Settings::class)
                            ? AIPKit_Global_Security_Settings::resolve_guest_session_id($posted_session_id, $client_ip)
                            : (!empty($posted_session_id) ? $posted_session_id : $client_ip);
                    }

                    $token_manager->record_token_usage(
                        $user_id ?: null,
                        $session_id_for_guest,
                        $context_id_for_token_record,
                        $tokens_to_record,
                        'image_generator',
                        [
                            'provider' => 'Google',
                            'model' => $model_id,
                            'operation' => 'video_generate',
                            'usage_data' => array_merge(
                                is_array($usage_data) ? $usage_data : [],
                                [
                                    'unit_count' => $videos_generated_count,
                                    'video_count' => $videos_generated_count,
                                ]
                            ),
                        ]
                    );
                }
            }

            $managerInstance->emit_generated_event(
                $prompt,
                [
                    'videos' => $videos_array,
                    'usage' => $usage_data,
                ],
                [
                    'provider' => 'Google',
                    'model' => $model_id,
                    'image_mode' => 'generate',
                    'aipkit_event_module' => 'image_generator',
                    'aipkit_event_origin' => 'image_generator_ajax',
                ],
                $is_logged_in ? $user_id : null,
                !$is_logged_in ? ($session_id_for_guest ?? null) : null
            );

            wp_send_json_success([
                'status' => 'completed',
                'videos' => $videos_array,
                'usage' => $usage_data,
                'message' => __('Video generation completed successfully!', 'gpt3-ai-content-generator')
            ]);
        } else {
            // Unknown status
            wp_send_json_error(['message' => __('Unknown video generation status.', 'gpt3-ai-content-generator')]);
        }
    } else {
        // Unexpected response format
        wp_send_json_error(['message' => __('Unexpected response format from video status check.', 'gpt3-ai-content-generator')]);
    }
}
