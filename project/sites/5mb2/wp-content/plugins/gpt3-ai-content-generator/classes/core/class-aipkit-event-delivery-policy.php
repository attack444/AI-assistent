<?php

namespace WPAICG\Core;

use WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Delivery policy defaults for the event queue runtime.
 *
 * This keeps latency-sensitive chatbot and AI Form events off the originating
 * request when there is actual downstream delivery work to do.
 */
class AIPKit_Event_Delivery_Policy
{
    /**
     * Registers default delivery policy filters.
     */
    public static function register_hooks(): void
    {
        add_filter('aipkit_event_delivery_queue_async_enabled', [__CLASS__, 'enable_async_for_latency_sensitive_events'], 10, 5);
    }

    /**
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $event_context
     * @param array<int, array<string, mixed>> $targets
     */
    public static function enable_async_for_latency_sensitive_events(bool $enabled, string $event_name, array $envelope = [], array $event_context = [], array $targets = []): bool
    {
        if ($enabled) {
            return true;
        }

        if (!self::is_latency_sensitive_event($event_name)) {
            return false;
        }

        if (self::is_manual_or_admin_origin($event_context)) {
            return false;
        }

        return self::has_delivery_subscribers($event_name, $targets);
    }

    private static function is_latency_sensitive_event(string $event_name): bool
    {
        $normalized_event_name = sanitize_text_field($event_name);

        if (strpos($normalized_event_name, 'chatbot.') === 0) {
            return true;
        }

        return in_array($normalized_event_name, ['form.submitted', 'content.generated', 'image.generated'], true);
    }

    /**
     * @param array<string, mixed> $event_context
     */
    private static function is_manual_or_admin_origin(array $event_context = []): bool
    {
        $origin = sanitize_key((string) ($event_context['origin'] ?? ''));
        if ($origin === '') {
            return false;
        }

        return strpos($origin, 'admin_') === 0 || strpos($origin, 'manual_') === 0;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     */
    private static function has_delivery_subscribers(string $event_name, array $targets = []): bool
    {
        if (!empty($targets)) {
            return true;
        }

        if (!class_exists(AIPKit_Stored_Recipes::class)) {
            return false;
        }

        $recipes = AIPKit_Stored_Recipes::get_matching_enabled_recipes($event_name);

        return !empty($recipes);
    }
}
