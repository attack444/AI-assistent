<?php

namespace WPAICG\Core\TokenManager\Reset;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Logic for checking if a token reset is due based on the last reset timestamp and period.
*
* @param int    $last_reset_timestamp Unix timestamp of the last reset.
* @param string $period 'daily', 'weekly', 'monthly', or 'never'.
* @return bool True if a reset is due, false otherwise.
*/
function IsResetDueLogic(int $last_reset_timestamp, string $period): bool
{
    if ($period === 'never' || $last_reset_timestamp <= 0) {
        return false;
    }

    $current_time = time();
    $site_timezone = wp_timezone();

    // Create a DateTime object from the last reset timestamp, in the site's timezone
    $last_reset_dt = new \DateTimeImmutable('@' . $last_reset_timestamp);
    $last_reset_dt = $last_reset_dt->setTimezone($site_timezone);

    // Calculate the start of the next reset period
    $next_reset_dt = null;

    switch ($period) {
        case 'daily':
            // The next day at midnight
            $next_reset_dt = $last_reset_dt->setTime(0, 0, 0)->modify('+1 day');
            break;
        case 'weekly':
            $start_of_week = (int) get_option('start_of_week', 1); // 0=Sun, 1=Mon...
            $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $start_of_week_name = $day_names[$start_of_week];

            // Find the start of the week that contains the last reset date
            $start_of_last_period = clone $last_reset_dt;
            if ((int)$start_of_last_period->format('w') !== $start_of_week) {
                $start_of_last_period = $start_of_last_period->modify('last ' . $start_of_week_name);
            }
            // The next reset is one week after the start of the last reset period
            $next_reset_dt = $start_of_last_period->setTime(0, 0, 0)->modify('+1 week');
            break;
        case 'monthly':
            // The first day of the next month at midnight
            $next_reset_dt = $last_reset_dt->setTime(0, 0, 0)->modify('first day of next month');
            break;
        default:
            return false;
    }

    if ($next_reset_dt === null) {
        return false;
    }

    // Get the UTC timestamp of the next reset time and compare with current UTC time
    $next_reset_timestamp = $next_reset_dt->getTimestamp();

    return $current_time >= $next_reset_timestamp;
}