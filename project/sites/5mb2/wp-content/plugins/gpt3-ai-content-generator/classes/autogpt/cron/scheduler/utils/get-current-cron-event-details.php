<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Gets details about a currently scheduled cron event.
*
* @param string $hook The name of the cron hook.
* @param array $args The arguments for the cron event.
* @return array ['timestamp' => int|false, 'frequency' => string|null]
*/
function get_current_cron_event_details_logic(string $hook, array $args): array
{
    $timestamp = wp_next_scheduled($hook, $args);
    if ($timestamp === false) {
        return ['timestamp' => false, 'frequency' => null];
    }

    $event_details = wp_get_scheduled_event($hook, $args, $timestamp);
    $frequency = $event_details ? $event_details->schedule : null;

    return ['timestamp' => $timestamp, 'frequency' => $frequency];
}
