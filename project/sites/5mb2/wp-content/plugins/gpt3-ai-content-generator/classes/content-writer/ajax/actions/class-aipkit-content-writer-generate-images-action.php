<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Image_Handler;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Image_Provider_Options;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(AIPKit_Content_Writer_Image_Provider_Options::class)) {
    $aipkit_image_provider_options_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-content-writer-image-provider-options.php';
    if (file_exists($aipkit_image_provider_options_path)) {
        require_once $aipkit_image_provider_options_path;
    }
}

/**
 * Handles the dedicated AJAX action for generating AI images within the Content Writer.
 * UPDATED: Strips large base64 data from the response to prevent "Request Entity Too Large" errors.
 */
class AIPKit_Content_Writer_Generate_Images_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    private const IMAGE_REQUEST_TTL = DAY_IN_SECONDS;
    private const IMAGE_REQUEST_STALE_SECONDS = 900;

    private function sanitize_image_request_id(string $request_id): string
    {
        $request_id = sanitize_key($request_id);
        if ($request_id === '') {
            return '';
        }

        return substr($request_id, 0, 96);
    }

    private function get_image_request_transient_key(string $request_id): string
    {
        $user_id = get_current_user_id();
        return 'aipkit_cw_img_req_' . md5($user_id . '|' . $request_id);
    }

    private function build_image_request_hash(array $settings): string
    {
        $hash_fields = [
            'original_topic',
            'final_title',
            'post_title',
            'keywords',
            'excerpt',
            'provider',
            'model',
            'ai_provider',
            'ai_model',
            'ai_temperature',
            'reasoning_effort',
            'image_provider',
            'image_model',
            'image_prompt',
            'featured_image_prompt',
            'generate_images_enabled',
            'image_count',
            'image_start_index',
            'generate_featured_image',
            'image_placement',
            'image_placement_param_x',
            'generate_image_title',
            'generate_image_alt_text',
            'generate_image_caption',
            'generate_image_description',
            'image_title_prompt',
            'image_alt_text_prompt',
            'image_caption_prompt',
            'image_description_prompt',
            'pexels_orientation',
            'pexels_size',
            'pexels_color',
            'pixabay_orientation',
            'pixabay_image_type',
            'pixabay_category',
        ];
        $fingerprint = [];

        foreach ($hash_fields as $field) {
            if (array_key_exists($field, $settings)) {
                $fingerprint[$field] = is_scalar($settings[$field]) ? (string) $settings[$field] : '';
            }
        }

        $fingerprint['image_provider_options'] = class_exists(AIPKit_Content_Writer_Image_Provider_Options::class)
            ? AIPKit_Content_Writer_Image_Provider_Options::get_hash_value($settings)
            : (is_scalar($settings['image_provider_options'] ?? null) ? (string) $settings['image_provider_options'] : '');

        ksort($fingerprint);
        return md5(wp_json_encode($fingerprint));
    }

    private function get_image_request_record(string $request_id): array
    {
        $record = get_transient($this->get_image_request_transient_key($request_id));
        return is_array($record) ? $record : [];
    }

    private function set_image_request_record(string $request_id, array $record): void
    {
        set_transient($this->get_image_request_transient_key($request_id), $record, self::IMAGE_REQUEST_TTL);
    }

    private function is_image_request_running(array $record): bool
    {
        return ($record['status'] ?? '') === 'running';
    }

    private function is_image_request_stale(array $record): bool
    {
        $started_at = isset($record['started_at']) ? (int) $record['started_at'] : 0;
        return $started_at <= 0 || (time() - $started_at) > self::IMAGE_REQUEST_STALE_SECONDS;
    }

    private function send_image_request_record_response(string $request_id, array $record): void
    {
        $status = (string) ($record['status'] ?? '');

        if ($status === 'completed' && isset($record['image_data']) && is_array($record['image_data'])) {
            wp_send_json_success([
                'image_data' => $record['image_data'],
                'image_status' => 'completed',
                'image_request_id' => $request_id,
                'recovered' => true,
            ]);
        }

        if ($status === 'failed') {
            $message = isset($record['message']) && is_string($record['message'])
                ? $record['message']
                : __('Image generation failed.', 'gpt3-ai-content-generator');
            wp_send_json_error([
                'message' => $message,
                'code' => 'image_request_failed',
                'image_status' => 'failed',
                'image_request_id' => $request_id,
            ], 500);
        }

        wp_send_json_success([
            'image_status' => 'running',
            'image_request_id' => $request_id,
            'retry_after' => 3,
        ]);
    }

    public function handle()
    {
        $this->maybe_extend_execution_limits(300);
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $permission_check = $this->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions.
        $settings = isset($_POST) ? wp_unslash($_POST) : [];
        $settings['aipkit_event_module'] = 'content_writer';
        $settings['aipkit_event_origin'] = 'content_writer_direct_images';
        $image_request_id = isset($settings['image_request_id'])
            ? $this->sanitize_image_request_id((string) $settings['image_request_id'])
            : '';
        $is_image_request_poll = isset($settings['image_request_poll']) && (string) $settings['image_request_poll'] === '1';
        $image_request_hash = $image_request_id !== '' ? $this->build_image_request_hash($settings) : '';

        if ($image_request_id !== '') {
            $existing_record = $this->get_image_request_record($image_request_id);
            $existing_hash = isset($existing_record['request_hash']) ? (string) $existing_record['request_hash'] : '';
            $hash_matches = $existing_hash === '' || $existing_hash === $image_request_hash;

            if (!empty($existing_record) && $hash_matches) {
                $is_running = $this->is_image_request_running($existing_record);
                $is_stale = $this->is_image_request_stale($existing_record);
                if ($is_running && $is_stale && $is_image_request_poll) {
                    wp_send_json_success([
                        'image_status' => 'missing',
                        'image_request_id' => $image_request_id,
                        'retry_after' => 3,
                    ]);
                    return;
                }

                if (!$is_running || !$is_stale || $is_image_request_poll) {
                    $this->send_image_request_record_response($image_request_id, $existing_record);
                    return;
                }
            } elseif ($is_image_request_poll) {
                wp_send_json_success([
                    'image_status' => 'missing',
                    'image_request_id' => $image_request_id,
                    'retry_after' => 3,
                ]);
                return;
            }

            $this->set_image_request_record($image_request_id, [
                'status' => 'running',
                'request_hash' => $image_request_hash,
                'started_at' => time(),
                'updated_at' => time(),
            ]);
        } elseif ($is_image_request_poll) {
            wp_send_json_success([
                'image_status' => 'missing',
                'retry_after' => 3,
            ]);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions.
        $final_title = isset($settings['final_title']) ? sanitize_text_field($settings['final_title']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions.
        $final_keywords = isset($settings['keywords']) ? sanitize_text_field($settings['keywords']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_module_access_permissions.
        $original_topic = isset($settings['original_topic']) ? sanitize_text_field($settings['original_topic']) : $final_title;

        if (!class_exists(AIPKit_Content_Writer_Image_Handler::class)) {
            $this->send_wp_error(new WP_Error('missing_image_handler', 'Image generation component is missing.', ['status' => 500]));
            return;
        }

        $image_handler = new AIPKit_Content_Writer_Image_Handler();
        $image_result = $image_handler->generate_and_prepare_images($settings, $final_title, $final_keywords, $original_topic);

        if (is_wp_error($image_result)) {
            if ($image_request_id !== '') {
                $this->set_image_request_record($image_request_id, [
                    'status' => 'failed',
                    'request_hash' => $image_request_hash,
                    'message' => $image_result->get_error_message(),
                    'started_at' => time(),
                    'updated_at' => time(),
                    'completed_at' => time(),
                ]);
            }
            $this->send_wp_error($image_result);
            return;
        }

        $requested_inline = ($settings['generate_images_enabled'] ?? '0') === '1' && absint($settings['image_count'] ?? 0) > 0;
        $requested_featured = ($settings['generate_featured_image'] ?? '0') === '1';
        $requested_any_image = $requested_inline || $requested_featured;
        $inline_count = !empty($image_result['in_content_images']) && is_array($image_result['in_content_images'])
            ? count($image_result['in_content_images'])
            : 0;
        $has_featured = !empty($image_result['featured_image_id']) || !empty($image_result['featured_image_url']);
        $generated_any_image = $inline_count > 0 || $has_featured;
        $warning_messages = [];
        if (!empty($image_result['warnings']) && is_array($image_result['warnings'])) {
            foreach ($image_result['warnings'] as $warning_message) {
                $normalized_warning = trim(wp_strip_all_tags((string) $warning_message));
                if ($normalized_warning === '' || in_array($normalized_warning, $warning_messages, true)) {
                    continue;
                }
                $warning_messages[] = $normalized_warning;
            }
        } elseif (!empty($image_result['warning']) && is_string($image_result['warning'])) {
            $normalized_warning = trim(wp_strip_all_tags($image_result['warning']));
            if ($normalized_warning !== '') {
                $warning_messages[] = $normalized_warning;
            }
        }

        if ($requested_any_image && !$generated_any_image && empty($warning_messages)) {
            $provider = isset($settings['image_provider']) ? sanitize_text_field((string) $settings['image_provider']) : '';
            $model = isset($settings['image_model']) ? sanitize_text_field((string) $settings['image_model']) : '';
            $fallback_warning = __('Image generation did not return any image data for the selected provider/model.', 'gpt3-ai-content-generator');
            if ($provider !== '' || $model !== '') {
                $fallback_warning .= ' ' . sprintf(
                    /* translators: 1: provider name, 2: model name */
                    __('Provider: %1$s, Model: %2$s.', 'gpt3-ai-content-generator'),
                    $provider !== '' ? $provider : __('unknown provider', 'gpt3-ai-content-generator'),
                    $model !== '' ? $model : __('unknown model', 'gpt3-ai-content-generator')
                );
            }
            $warning_messages[] = $fallback_warning;
        }

        if (!empty($warning_messages)) {
            $image_result['warnings'] = $warning_messages;
            $image_result['warning'] = $warning_messages[0];
        }

        // --- START FIX: Strip b64_json from response to prevent 413 "Request Entity Too Large" on subsequent saves ---
        if (isset($image_result['in_content_images']) && is_array($image_result['in_content_images'])) {
            foreach ($image_result['in_content_images'] as &$image_item) {
                unset($image_item['b64_json']);
            }
            unset($image_item); // Unset the reference
        }
        // --- END FIX ---

        if ($image_request_id !== '') {
            $this->set_image_request_record($image_request_id, [
                'status' => 'completed',
                'request_hash' => $image_request_hash,
                'image_data' => $image_result,
                'started_at' => time(),
                'updated_at' => time(),
                'completed_at' => time(),
            ]);
        }

        // Optional logging under the same conversation
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $conversation_uuid = isset($_POST['conversation_uuid']) ? sanitize_text_field(wp_unslash($_POST['conversation_uuid'])) : '';
        if (!empty($conversation_uuid) && $this->log_storage) {
            $provider = isset($settings['image_provider']) ? sanitize_text_field($settings['image_provider']) : '';
            $model = isset($settings['image_model']) ? sanitize_text_field($settings['image_model']) : '';
            $generate_in_content = ($settings['generate_images_enabled'] ?? '0') === '1';
            $image_count = absint($settings['image_count'] ?? 0);
            $generate_featured = ($settings['generate_featured_image'] ?? '0') === '1';

            $base = $this->build_content_writer_log_base($conversation_uuid, $provider, $model);

            // Build a compact list of images to avoid large payloads
            $inline_images_meta = [];
            if (!empty($image_result['in_content_images']) && is_array($image_result['in_content_images'])) {
                foreach ($image_result['in_content_images'] as $idx => $img) {
                    $inline_images_meta[] = [
                        'type' => 'inline',
                        'index' => $idx,
                        'attachment_id' => isset($img['attachment_id']) ? $img['attachment_id'] : null,
                        'url' => $img['url'] ?? ($img['src'] ?? ($img['image_url'] ?? null)),
                        'provider' => $provider,
                    ];
                }
            }
            $featured_meta = null;
            if (!empty($image_result['featured_image_id'])) {
                $featured_meta = [
                    'type' => 'featured',
                    'attachment_id' => $image_result['featured_image_id'],
                    'provider' => $provider,
                ];
            }

            // Log the user intent
            $this->log_storage->log_message(array_merge($base, [
                'message_role' => 'user',
                'message_content' => 'Generate Images',
                'request_payload' => [
                    'original_topic' => $original_topic,
                    'final_title' => $final_title,
                    'keywords' => $final_keywords,
                    'image_prompt' => isset($settings['image_prompt']) ? (string) $settings['image_prompt'] : null,
                    'featured_image_prompt' => isset($settings['featured_image_prompt']) ? (string) $settings['featured_image_prompt'] : null,
                    'generate_images_enabled' => $generate_in_content ? 1 : 0,
                    'image_count' => $image_count,
                    'generate_featured_image' => $generate_featured ? 1 : 0,
                    'image_provider' => $provider,
                    'image_model' => $model,
                    'placement' => $settings['image_placement'] ?? 'after_first_h2',
                    'placement_param_x' => isset($settings['image_placement_param_x']) ? absint($settings['image_placement_param_x']) : null,
                ],
            ]));

            // Log the result with compact metadata
            $this->log_storage->log_message(array_merge($base, [
                'message_role' => 'bot',
                'message_content' => $generated_any_image ? 'Images Generated' : 'Images Skipped',
                'usage' => null,
                'request_payload' => [
                    'result_summary' => [
                        'inline_count' => count($inline_images_meta),
                        'featured_present' => !empty($featured_meta),
                        'warning' => $image_result['warning'] ?? null,
                        'warnings' => $image_result['warnings'] ?? [],
                    ],
                    'images' => [
                        'inline' => $inline_images_meta,
                        'featured' => $featured_meta,
                    ],
                ],
            ]));
        }

        wp_send_json_success([
            'image_data' => $image_result,
            'image_status' => 'completed',
            'image_request_id' => $image_request_id,
        ]);
    }
}
