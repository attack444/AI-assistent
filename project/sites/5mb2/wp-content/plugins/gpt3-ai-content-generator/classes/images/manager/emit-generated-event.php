<?php

namespace WPAICG\Images\Manager;

use WPAICG\Core\AIPKit_Event_Webhooks;
use WPAICG\Core\AIPKit_Payload_Sanitizer;
use WPAICG\Images\AIPKit_Image_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emits the canonical image generation event without affecting the caller flow.
 *
 * @param AIPKit_Image_Manager  $managerInstance
 * @param string                $prompt
 * @param array<string, mixed>  $result
 * @param array<string, mixed>  $options
 * @param int|null              $user_id
 * @param string|null           $session_id
 * @return void
 */
function emit_generated_event_logic(
    AIPKit_Image_Manager $managerInstance,
    string $prompt,
    array $result,
    array $options = [],
    ?int $user_id = null,
    ?string $session_id = null
): void {
    if (!class_exists(AIPKit_Event_Webhooks::class)) {
        return;
    }

    $images = isset($result['images']) && is_array($result['images']) ? array_values($result['images']) : [];
    $videos = isset($result['videos']) && is_array($result['videos']) ? array_values($result['videos']) : [];
    $output_count = count($images) + count($videos);
    if ($output_count < 1) {
        return;
    }

    $sanitized_outputs = AIPKit_Payload_Sanitizer::sanitize_payload_if_array([
        'images' => $images,
        'videos' => $videos,
    ]);

    $provider = sanitize_text_field((string) ($options['provider'] ?? ''));
    $model = sanitize_text_field((string) ($options['model'] ?? ''));
    $mode = sanitize_key((string) ($options['image_mode'] ?? 'generate'));
    $media_type = !empty($videos) ? 'video' : 'image';
    $source_module = sanitize_key((string) ($options['aipkit_event_module'] ?? 'image_generator'));
    if ($source_module === '') {
        $source_module = 'image_generator';
    }
    $source_origin = sanitize_key((string) ($options['aipkit_event_origin'] ?? 'shared_image_manager'));
    if ($source_origin === '') {
        $source_origin = 'shared_image_manager';
    }

    $payload = [
        'prompt' => $prompt,
        'provider' => $provider,
        'model' => $model,
        'mode' => $mode,
        'media_type' => $media_type,
        'output_count' => $output_count,
        'outputs' => $sanitized_outputs,
        'usage' => isset($result['usage']) && is_array($result['usage']) ? $result['usage'] : null,
        'actor' => [
            'type' => $user_id ? 'user' : 'guest',
        ],
    ];

    if ($user_id) {
        $payload['actor']['user_id'] = $user_id;
    } elseif (!empty($session_id)) {
        $payload['actor']['session_id'] = sanitize_text_field($session_id);
    }

    $output_ids = [];
    foreach ($images as $image) {
        if (!is_array($image)) {
            continue;
        }
        $candidate = $image['attachment_id'] ?? ($image['media_library_url'] ?? ($image['url'] ?? ''));
        if ($candidate === null || $candidate === '') {
            continue;
        }
        $output_ids[] = (string) $candidate;
    }
    foreach ($videos as $video) {
        if (!is_array($video)) {
            continue;
        }
        $candidate = $video['url'] ?? ($video['file_uri'] ?? '');
        if ($candidate === '') {
            continue;
        }
        $output_ids[] = (string) $candidate;
    }

    AIPKit_Event_Webhooks::emit(
        'image.generated',
        $payload,
        [
            'module' => $source_module,
            'origin' => $source_origin,
            'resource' => [
                'type' => 'image_generation',
                'id' => sha1($source_module . '|' . $source_origin . '|' . $provider . '|' . $model . '|' . $mode . '|' . $prompt . '|' . implode('|', $output_ids)),
            'label' => $prompt !== '' ? wp_trim_words($prompt, 8, '...') : __('Image generation', 'gpt3-ai-content-generator'),
        ],
        'meta' => [
            'source_module' => $source_module,
            'source_origin' => $source_origin,
            'provider' => $provider,
            'model' => $model,
            'mode' => $mode,
            'media_type' => $media_type,
            'output_count' => $output_count,
        ],
        'idempotency_key' => sha1(implode('|', [
            'image.generated',
            $source_module,
            $source_origin,
            $provider,
            $model,
            $mode,
            $prompt,
            $media_type,
                implode('|', $output_ids),
            ])),
        ]
    );
}
