<?php

namespace WPAICG\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Shared dispatcher for Universal Event Webhooks.
 *
 * This foundation validates supported event names, builds the canonical
 * payload envelope, persists a durable queue job, resolves currently
 * subscribed endpoints, performs delivery with retries/signing, and
 * exposes WordPress hooks for module integrations.
 */
class AIPKit_Event_Dispatcher
{
    /**
     * Emits an event and returns the prepared dispatch result.
     *
     * @param string $event_name
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>|WP_Error
     */
    public static function emit(string $event_name, array $payload = [], array $context = [])
    {
        $normalized_event_name = sanitize_text_field(trim($event_name));
        if ($normalized_event_name === '') {
            return new WP_Error('aipkit_event_name_missing', __('Event name is required.', 'gpt3-ai-content-generator'));
        }

        if (!AIPKit_Event_Registry::has_event($normalized_event_name)) {
            return new WP_Error(
                'aipkit_event_not_supported',
                sprintf(
                    /* translators: %s: event name */
                    __('Unsupported event: %s', 'gpt3-ai-content-generator'),
                    $normalized_event_name
                )
            );
        }

        $envelope = AIPKit_Event_Payload_Builder::build_envelope($normalized_event_name, $payload, $context);
        $envelope = apply_filters('aipkit_event_webhooks_envelope', $envelope, $normalized_event_name, $payload, $context);

        $targets = AIPKit_Event_Webhooks_Settings::get_active_endpoints_for_event($normalized_event_name);
        $targets = apply_filters('aipkit_event_webhooks_targets', $targets, $normalized_event_name, $envelope, $context);
        if (!is_array($targets)) {
            $targets = [];
        }

        $async_delivery_requested = (bool) apply_filters(
            'aipkit_event_delivery_queue_async_enabled',
            false,
            $normalized_event_name,
            $envelope,
            $context,
            $targets
        );

        $queue_context = $context;
        $queue_context['aipkit_queue'] = [
            'targets' => $targets,
        ];

        $queue_job = null;
        $queue_error = null;
        $queue_enabled = (bool) apply_filters(
            'aipkit_event_delivery_queue_enabled',
            $async_delivery_requested,
            $normalized_event_name,
            $envelope,
            $context,
            $targets
        );

        if ($queue_enabled && class_exists(AIPKit_Event_Queue_Store::class)) {
            $queue_result = AIPKit_Event_Queue_Store::enqueue_event(
                $normalized_event_name,
                $envelope,
                $queue_context,
                [
                    'target_count' => count($targets),
                ]
            );

            if (is_wp_error($queue_result)) {
                $queue_error = [
                    'code' => $queue_result->get_error_code(),
                    'message' => $queue_result->get_error_message(),
                    'details' => is_array($queue_result->get_error_data()) ? $queue_result->get_error_data() : [],
                ];

                do_action(
                    'aipkit_event_webhooks_enqueue_failed',
                    $queue_result,
                    $normalized_event_name,
                    $envelope,
                    $context,
                    $targets
                );
            } else {
                $queue_job = $queue_result;

                do_action(
                    'aipkit_event_webhooks_enqueued',
                    $queue_job,
                    $normalized_event_name,
                    $envelope,
                    $context,
                    $targets
                );
            }
        }

        $result = [
            'event_name' => $normalized_event_name,
            'envelope' => $envelope,
            'targets' => $targets,
            'target_count' => count($targets),
            'queue_job' => $queue_job,
            'queue_error' => $queue_error,
            'queued_count' => is_array($queue_job) ? 1 : 0,
            'deliveries' => [],
            'delivered_count' => 0,
            'failed_count' => 0,
            'async_delivery_requested' => $async_delivery_requested,
            'async_delivery_enabled' => false,
            'sync_delivery_enabled' => true,
        ];

        $async_delivery_available = $async_delivery_requested && is_array($queue_job);
        $sync_delivery_enabled = (bool) apply_filters(
            'aipkit_event_webhooks_sync_delivery_enabled',
            !$async_delivery_available,
            $normalized_event_name,
            $envelope,
            $context,
            $targets,
            $queue_job
        );
        $async_delivery_enabled = $async_delivery_available && !$sync_delivery_enabled;

        if ($async_delivery_enabled && is_array($queue_job) && class_exists(AIPKit_Event_Queue_Store::class)) {
            $pending_updated = AIPKit_Event_Queue_Store::update_job_state((string) ($queue_job['job_uuid'] ?? ''), [
                'status' => 'pending',
                'locked_at' => null,
                'processed_at' => null,
                'available_at' => gmdate('Y-m-d H:i:s'),
                'last_error_message' => '',
            ]);

            if ($pending_updated) {
                $queue_job['status'] = 'pending';
                $queue_job['available_at'] = gmdate('Y-m-d H:i:s');
                $result['queue_job'] = $queue_job;
            } else {
                $async_delivery_enabled = false;
                $sync_delivery_enabled = true;
            }
        }

        $result['async_delivery_enabled'] = $async_delivery_enabled;
        $result['sync_delivery_enabled'] = $sync_delivery_enabled;

        if ($sync_delivery_enabled && !empty($targets)) {
            $deliveries = AIPKit_Event_Delivery_Manager::deliver($normalized_event_name, $envelope, $targets);
            $result['deliveries'] = $deliveries;
            $result['delivered_count'] = count(array_filter($deliveries, static function ($delivery): bool {
                return is_array($delivery) && (($delivery['status'] ?? '') === 'delivered');
            }));
            $result['failed_count'] = count(array_filter($deliveries, static function ($delivery): bool {
                return is_array($delivery) && (($delivery['status'] ?? '') === 'failed');
            }));
        }

        if (is_array($queue_job) && class_exists(AIPKit_Event_Queue_Worker::class)) {
            AIPKit_Event_Queue_Worker::maybe_trigger_async_worker($queue_job, $result, $context);
        }

        if (!$async_delivery_enabled) {
            do_action('aipkit_event_webhooks_emitted', $result, $context);
            do_action('aipkit_event_webhooks_emitted_' . self::get_hook_suffix($normalized_event_name), $result, $context);
        }

        return $result;
    }

    /**
     * Normalizes an event name into a hook-safe suffix.
     *
     * @param string $event_name
     * @return string
     */
    private static function get_hook_suffix(string $event_name): string
    {
        return str_replace(['.', '-'], '_', sanitize_key($event_name));
    }
}
