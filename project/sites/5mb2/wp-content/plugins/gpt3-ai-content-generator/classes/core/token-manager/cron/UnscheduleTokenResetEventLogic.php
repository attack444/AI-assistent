<?php

namespace WPAICG\Core\TokenManager\Cron;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for unscheduling the token reset event.
 *
 * @param string $cronHook The cron hook name (e.g., from CronHookConstant::CRON_HOOK).
 */
function UnscheduleTokenResetEventLogic(string $cronHook): void {
    $timestamp = wp_next_scheduled($cronHook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $cronHook);
    }

    // Similar to scheduling, removing the action hook is typically done
    // where it was added. This logic file focuses on the wp_unschedule_event part.
    // `remove_action` would be called for the specific callback that was hooked.
}