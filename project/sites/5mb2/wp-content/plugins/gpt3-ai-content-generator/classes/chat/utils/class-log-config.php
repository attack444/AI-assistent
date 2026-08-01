<?php

namespace WPAICG\Chat\Utils;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Configuration constants and utilities for log management.
 * 
 * Centralizes configuration options to reduce code duplication
 * and make maintenance easier.
 */
class LogConfig
{
    /**
     * Valid retention period options in days.
     * Used by both frontend form and backend validation.
     *
     * @return array Array of period values => labels
     */
    public static function get_retention_periods(): array
    {
        return [
            1 => __('1 day', 'gpt3-ai-content-generator'),
            3 => __('3 days', 'gpt3-ai-content-generator'),
            7 => __('7 days', 'gpt3-ai-content-generator'),
            15 => __('15 days', 'gpt3-ai-content-generator'),
            30 => __('30 days', 'gpt3-ai-content-generator'),
            60 => __('60 days', 'gpt3-ai-content-generator'),
            90 => __('90 days', 'gpt3-ai-content-generator'),
            180 => __('6 months', 'gpt3-ai-content-generator'),
            365 => __('1 year', 'gpt3-ai-content-generator')
        ];
    }

    /**
     * Get valid retention period values only.
     *
     * @return array Array of valid period values
     */
    public static function get_valid_periods(): array
    {
        return array_keys(self::get_retention_periods());
    }

    /**
     * Validates if a retention period is valid.
     *
     * @param mixed $period The period to validate
     * @return bool True if valid, false otherwise
     */
    public static function is_valid_period($period): bool
    {
        if (!is_numeric($period)) {
            return false;
        }

        $numericPeriod = (float) $period;
        if ($numericPeriod < 1 || $numericPeriod > 365) {
            return false;
        }

        return true;
    }

    /**
     * Get default log settings.
     *
     * @return array Default settings array
     */
    public static function get_default_settings(): array
    {
        return [
            'enable_pruning' => false,
            'retention_period_days' => 90
        ];
    }

    /**
     * Get sanitized log settings from database.
     *
     * @return array Sanitized settings with defaults
     */
    public static function get_log_settings(): array
    {
        $settings = get_option('aipkit_log_settings', self::get_default_settings());
        
        // Ensure settings have required keys with proper types
        return [
            'enable_pruning' => (bool)($settings['enable_pruning'] ?? false),
            'retention_period_days' => (float)($settings['retention_period_days'] ?? 90)
        ];
    }
}
