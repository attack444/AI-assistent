<?php

namespace WPAICG\Core\TokenManager\Cron;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for scheduling the daily token reset event.
 *
 * @param string $cronHook The cron hook name (e.g., from CronHookConstant::CRON_HOOK).
 */
function ScheduleTokenResetEventLogic(string $cronHook): void {
    if (!wp_next_scheduled($cronHook)) {
        wp_schedule_event(time(), 'daily', $cronHook);
    }

    // Ensure the action is hooked (can be called multiple times, add_action handles duplicates)
    // The actual callback method is on the AIPKit_Token_Manager class itself.
    // This function just schedules the WP Cron event. The hook registration for the callback
    // happens where the AIPKit_Token_Manager is instantiated and its cron methods are added to hooks.
    // However, for self-contained logic, if this file were *solely* responsible and not called by a manager,
    // you might add the action here. But since it's part of a class's responsibility,
    // the add_action is better placed in the class that owns the callback or in a central hook registrar.

    // For this modularization, we assume the add_action is handled elsewhere, like in includes/class-wp-ai-content-generator.php
    // or a dedicated hook manager if AIPKit_Token_Manager::perform_token_reset is static or the instance is passed.
    // Since perform_token_reset is an instance method, the `add_action` for its execution
    // will be tied to where an instance of AIPKit_Token_Manager is available and its hook is registered.
    // This specific logic file is *only* for scheduling the WordPress cron event.
}