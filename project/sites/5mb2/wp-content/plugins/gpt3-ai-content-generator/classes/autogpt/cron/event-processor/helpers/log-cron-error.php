<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logs a standardized error message for cron processing failures.
 *
 * @param string $message The specific error message.
 * @param int|null $itemId The ID of the queue item that failed, if applicable.
 * @return void
 */
function log_cron_error_logic(string $message, ?int $itemId = null): void
{
    $log_message = "AIPKit Cron Processor Error: {$message}";
    if ($itemId !== null) {
        $log_message .= " (Queue Item ID: {$itemId})";
    }
}
