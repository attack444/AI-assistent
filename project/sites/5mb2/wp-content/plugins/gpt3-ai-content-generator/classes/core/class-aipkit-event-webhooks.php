<?php

namespace WPAICG\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Module-facing façade for the Universal Event Webhooks foundation.
 */
class AIPKit_Event_Webhooks
{
    /**
     * Emits a registered event.
     *
     * @param string $event_name
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>|WP_Error
     */
    public static function emit(string $event_name, array $payload = [], array $context = [])
    {
        return AIPKit_Event_Dispatcher::emit($event_name, $payload, $context);
    }

    /**
     * Returns registered event definitions.
     *
     * @return array<string, array<string, string>>
     */
    public static function get_registered_events(): array
    {
        return AIPKit_Event_Registry::get_definitions();
    }

    /**
     * Returns normalized settings for the future delivery layer/UI.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array
    {
        return AIPKit_Event_Webhooks_Settings::get_settings();
    }
}
