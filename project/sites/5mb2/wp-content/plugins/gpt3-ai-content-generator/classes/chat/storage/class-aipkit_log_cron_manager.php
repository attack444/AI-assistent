<?php

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Storage\LogManager;
use WPAICG\aipkit_dashboard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages the WP-Cron job for automatic log pruning.
 */
class LogCronManager
{
    public const HOOK_NAME = 'aipkit_prune_logs_cron';

    /**
     * Schedules the daily pruning event if it's not already scheduled.
     */
    public static function schedule_event()
    {
        if (!wp_next_scheduled(self::HOOK_NAME)) {
            wp_schedule_event(time(), 'daily', self::HOOK_NAME);
        }
    }

    /**
     * Unschedules the pruning event.
     */
    public static function unschedule_event()
    {
        $timestamp = wp_next_scheduled(self::HOOK_NAME);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_NAME);
        }
        // Also clear any other potential schedules for the same hook
        wp_clear_scheduled_hook(self::HOOK_NAME);
    }

    /**
     * The main callback function for the cron job.
     * Reads settings and triggers the pruning process.
     */
    public static function run_pruning()
    {
        if (!class_exists('\WPAICG\aipkit_dashboard') || !aipkit_dashboard::is_pro_plan()) {
            // If not pro, unschedule the event and stop execution.
            self::unschedule_event();
            return;
        }

        $log_settings = get_option('aipkit_log_settings', [
            'enable_pruning' => false,
            'retention_period_days' => 90
        ]);

        $enable_pruning = (bool)($log_settings['enable_pruning'] ?? false);
        $retention_period_days = (float)($log_settings['retention_period_days'] ?? 90);

        if ($enable_pruning && $retention_period_days > 0) {
            if (class_exists(LogManager::class)) {
                $log_manager = new LogManager();
                $deleted_count = $log_manager->prune_logs($retention_period_days);
                
                // Update last run time
                update_option('aipkit_log_pruning_last_run', current_time('mysql', true), 'no');
            }
        }
    }
}