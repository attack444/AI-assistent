<?php

namespace WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/topic-filter-utils.php';

/**
 * Generates items to be queued from a textarea input (single, bulk, or csv modes).
 * Each non-empty line can contain pipe-separated columns:
 * 0: topic (required)
 * 1: keywords (optional)
 * 2: category id (optional)
 * 3: author login (optional)
 * 4: post type (optional)
 * 5: schedule datetime (optional; multiple formats supported by parse_schedule_datetime_simple_logic later)
 *
 * Returns an array of mixed item arrays (structured) or strings (legacy) for backward compatibility.
 * Structured arrays enable schedule date usage with schedule_mode=from_input.
 *
 * @param array $task_config The configuration of the task.
 * @return array<int, array|string>
 */
function manual_mode_generate_items_logic(array $task_config): array
{
    $raw = $task_config['content_title'] ?? '';
    if ($raw === '') {
        return [];
    }
    $lines = preg_split('/\r?\n/', $raw);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        // Split by pipe only if present; allow legacy plain topic lines.
        if (strpos($line, '|') === false) {
            $items[] = $line; // legacy simple topic
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $topic = $parts[0] ?? '';
        if ($topic === '') {
            continue; // skip invalid line
        }
        $item = [
            'topic' => $topic,
        ];
        if (isset($parts[1]) && $parts[1] !== '') {
            $item['keywords'] = $parts[1];
        }
        if (isset($parts[2]) && is_numeric($parts[2])) {
            $item['category'] = $parts[2];
        }
        if (isset($parts[3]) && $parts[3] !== '') {
            $item['author'] = $parts[3];
        }
        if (isset($parts[4]) && $parts[4] !== '') {
            $item['post_type'] = $parts[4];
        }
        if (isset($parts[5]) && $parts[5] !== '') {
            $item['schedule_date'] = $parts[5];
        }
        $items[] = $item;
    }
    return $items;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$premium_logic_path = WPAICG_LIB_DIR . 'autogpt/cron/event-processor/trigger/module/rss-task-generator.php';
if (file_exists($premium_logic_path)) {
    require_once $premium_logic_path;
}

/**
 * Safe fallback when the premium RSS runtime is not present.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @param string|null $last_run_time The GMT timestamp of the last check.
 * @return array|WP_Error An array of items or WP_Error on failure.
 */
if (!function_exists(__NAMESPACE__ . '\\rss_mode_generate_items_logic')) {
    /**
     * @return mixed[]|\WP_Error
     */
    function rss_mode_generate_items_logic(int $task_id, array $task_config, ?string $last_run_time)
    {
        unset($task_id, $task_config, $last_run_time);

        return new WP_Error('rss_feature_unavailable', __('RSS generation is a Pro feature or its components are missing.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$premium_logic_path = WPAICG_LIB_DIR . 'autogpt/cron/event-processor/trigger/module/url-task-generator.php';
if (file_exists($premium_logic_path)) {
    require_once $premium_logic_path;
}

/**
 * Safe fallback when the premium URL runtime is not present.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @return array|WP_Error An array containing 'topics' and 'contexts' or a WP_Error on failure.
 */
if (!function_exists(__NAMESPACE__ . '\\url_mode_generate_items_logic')) {
    /**
     * @return mixed[]|\WP_Error
     */
    function url_mode_generate_items_logic(int $task_id, array $task_config)
    {
        unset($task_id, $task_config);

        return new WP_Error('url_feature_unavailable', __('URL extracting is a Pro feature or its components are missing.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$premium_logic_path = WPAICG_LIB_DIR . 'autogpt/cron/event-processor/trigger/module/gsheets-task-generator.php';
if (file_exists($premium_logic_path)) {
    require_once $premium_logic_path;
}

/**
 * Safe fallback when the premium Google Sheets runtime is not present.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @return array|WP_Error An array of items or WP_Error on failure.
 */
if (!function_exists(__NAMESPACE__ . '\\gsheets_mode_generate_items_logic')) {
    /**
     * @return mixed[]|\WP_Error
     */
    function gsheets_mode_generate_items_logic(int $task_id, array $task_config)
    {
        unset($task_id, $task_config);

        return new WP_Error('gsheets_feature_unavailable', __('Google Sheets integration is a Pro feature or its components are missing.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }
}

/**
 * Checks if an item with the given identifier already exists for the task.
 * @param int $task_id
 * @param string $target_identifier
 * @return bool
 */
function is_duplicate_topic_logic(int $task_id, string $target_identifier): bool
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caches will be invalidated.
    $existing_item = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$queue_table_name} WHERE task_id = %d AND target_identifier = %s",
        $task_id,
        $target_identifier
    ));
    return (bool) $existing_item;
}

/**
 * Generates a consistent target identifier for an item.
 * @param mixed  $item_data (string or array)
 * @param int    $task_id
 * @param int    $index
 * @return string
 */
function generate_target_identifier_logic($item_data, int $task_id, int $index): string
{
    if (is_array($item_data) && !empty($item_data['guid'])) {
        return $item_data['guid'];
    }
    if (is_array($item_data) && !empty($item_data['link'])) {
        return $item_data['link'];
    }
    $base_identifier = is_string($item_data) ? $item_data : ($item_data['title'] ?? ($item_data['topic'] ?? ''));
    return 'cw_scheduled_' . $task_id . '_' . sanitize_title(substr($base_identifier, 0, 50)) . '_' . time() . '_' . $index;
}

/**
 * Prepares the specific item_config by merging item-specific data with the main task config.
 * @param mixed $item_data (string or array)
 * @param array $task_config
 * @param array $scraped_contexts
 * @return array
 */
function prepare_item_config_logic($item_data, array $task_config, array $scraped_contexts): array
{
    $item_specific_config = $task_config;
    $is_structured_item = is_array($item_data);

    if ($is_structured_item) {
        $topic = $item_data['topic'] ?? ($item_data['title'] ?? '');
        $inline_keywords = $item_data['keywords'] ?? '';
        $category_id = $item_data['category'] ?? null;
        $author_login = $item_data['author'] ?? null;
        $post_type_slug = $item_data['post_type'] ?? null;

        $item_specific_config['content_title'] = $topic;
        $item_specific_config['inline_keywords'] = $inline_keywords;

        if (isset($item_data['description'])) {
            $item_specific_config['rss_description'] = $item_data['description'];
        }
        if (isset($item_data['link'])) {
            $item_specific_config['source_url'] = $item_data['link'];
        }
        if (isset($item_data['guid'])) {
            $item_specific_config['rss_item_guid'] = $item_data['guid'];
        }

        $link = $item_data['link'] ?? md5($topic);
        if (isset($scraped_contexts[$link])) {
            $item_specific_config['url_content_context'] = $scraped_contexts[$link];
            $item_specific_config['source_url'] = $link;
        }

        if (isset($item_data['row_index'])) {
            $item_specific_config['gsheets_row_index'] = $item_data['row_index'];
        }
        if (isset($task_config['gsheets_sheet_id'])) {
            $item_specific_config['gsheets_sheet_id'] = $task_config['gsheets_sheet_id'];
        }

        if ($post_type_slug && post_type_exists($post_type_slug)) {
            $item_specific_config['post_type'] = $post_type_slug;
        }

        if ($category_id && is_numeric($category_id)) {
            $item_specific_config['post_categories'] = [absint($category_id)];
        }

        if ($author_login) {
            $user = get_user_by('login', $author_login);
            if ($user) {
                $post_type = $item_specific_config['post_type'] ?? 'post';
                $post_type_object = get_post_type_object($post_type);
                if ($post_type_object && user_can($user->ID, $post_type_object->cap->create_posts)) {
                    $item_specific_config['post_author'] = $user->ID;
                }
            }
        }
    } else {
        $parts = array_map('trim', explode('|', $item_data));
        $item_specific_config['content_title'] = $parts[0] ?? '';
        $item_specific_config['inline_keywords'] = $parts[1] ?? '';
    }
    return $item_specific_config;
}

/**
 * Inserts a single prepared item into the queue.
 * @param int    $task_id
 * @param string $target_identifier
 * @param array  $item_config
 * @return bool True on success
 */
function insert_topic_into_queue_logic(int $task_id, string $target_identifier, array $item_config): bool
{
    global $wpdb;
    $queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Direct query to a custom table.
    $inserted = $wpdb->insert(
        $queue_table_name,
        [
            'task_id' => $task_id,
            'target_identifier' => $target_identifier,
            'task_type' => 'content_writing',
            'item_config' => wp_json_encode($item_config),
            'status' => 'pending',
            'added_at' => current_time('mysql', 1)
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    if (!$inserted) {
        return false;
    }

    if (
        isset($item_config['cw_generation_mode']) &&
        $item_config['cw_generation_mode'] === 'gsheets' &&
        !empty($item_config['gsheets_row_index']) &&
        !empty($item_config['gsheets_sheet_id']) &&
        !empty($item_config['gsheets_credentials']) &&
        class_exists('\WPAICG\Lib\ContentWriter\AIPKit_Google_Sheets_Parser')
    ) {
        try {
            $sheets_parser = new \WPAICG\Lib\ContentWriter\AIPKit_Google_Sheets_Parser($item_config['gsheets_credentials']);
            $status_to_write = 'Queued on ' . current_time('mysql');
            $sheets_parser->update_row_status(
                $item_config['gsheets_sheet_id'],
                (int) $item_config['gsheets_row_index'],
                $status_to_write
            );
        } catch (\Exception $e) {
            // Fail silently; queue insertion succeeded.
        }
    }

    return true;
}

// Purpose: Robust parsing of user-provided schedule date strings for "Use Dates from Input" mode.

namespace WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Attempts to parse a schedule datetime string provided by the user / Google Sheet.
 * Accepts multiple common formats and normalizes to a GMT datetime string (Y-m-d H:i:s) on success.
 *
 * Supported examples:
 *  - 2025-08-22 10:00
 *  - 2025-08-22 10:00:30
 *  - 2025/08/22 10:00
 *  - 08/22/2025 10:00  (US)
 *  - 22/08/2025 10:00  (EU – heuristic: if first part > 12 treat as D/M/Y)
 *  - 2025-08-22T10:00:00Z (ISO UTC)
 *  - 2025-08-22T10:00:00+02:00 (ISO with offset)
 *
 * Heuristic for ambiguous numeric dates with slashes:
 *  If first component > 12 => D/M/Y, else M/D/Y.
 *
 * @param string $raw Raw user-entered or sheet-extracted string.
 * @return array{gmt:string|null, error:string|null} gmt is normalized GMT datetime or null if parse failed; error is reason when failed.
 */
function parse_schedule_datetime_logic(string $raw): array
{
    $raw_original = trim($raw);
    if ($raw_original === '') {
        return ['gmt' => null, 'error' => 'empty'];
    }

    $raw_clean = preg_replace('/\s+/', ' ', $raw_original); // collapse whitespace

    $candidates = [];

    // Normalize ISO 'T'
    if (preg_match('/T/', $raw_clean)) {
        $iso = $raw_clean;
        // If Z or offset present, we can rely on strtotime (UTC or offset aware)
        $candidates[] = function() use ($iso) {
            $ts = strtotime($iso);
            return $ts ? ['ts' => $ts, 'is_utc' => true] : null;
        };
    }

    // Add direct known dash formats
    $dash_no_sec = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/';
    if (preg_match($dash_no_sec, $raw_clean)) {
        $candidates[] = function() use ($raw_clean) {
            $dt = date_create_from_format('Y-m-d H:i', $raw_clean, wp_timezone());
            return $dt ? ['ts' => $dt->getTimestamp(), 'is_utc' => false] : null;
        };
    }
    $dash_with_sec = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/';
    if (preg_match($dash_with_sec, $raw_clean)) {
        $candidates[] = function() use ($raw_clean) {
            $dt = date_create_from_format('Y-m-d H:i:s', $raw_clean, wp_timezone());
            return $dt ? ['ts' => $dt->getTimestamp(), 'is_utc' => false] : null;
        };
    }

    // Slash formats (ambiguous)
    $slash_pattern = '/^(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{2}):(\d{2})(?::(\d{2}))?$/';
    if (preg_match($slash_pattern, $raw_clean, $m)) {
        $a = (int)$m[1]; $b = (int)$m[2]; $Y = (int)$m[3];
        $H = (int)$m[4]; $i = (int)$m[5]; $s = isset($m[6]) ? (int)$m[6] : 0;
        $is_day_first = $a > 12; // heuristic
        $day = $is_day_first ? $a : $b;
        $mon = $is_day_first ? $b : $a;
        if (checkdate($mon, $day, $Y)) {
            $candidates[] = function() use ($Y,$mon,$day,$H,$i,$s) {
                $dt = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $Y,$mon,$day,$H,$i,$s), wp_timezone());
                return ['ts' => $dt->getTimestamp(), 'is_utc' => false];
            };
        }
    }

    // Fallback strtotime (interprets as site timezone if no TZ info)
    $candidates[] = function() use ($raw_clean) {
        $ts = strtotime($raw_clean);
        return $ts ? ['ts' => $ts, 'is_utc' => false] : null;
    };

    foreach ($candidates as $resolver) {
        $res = $resolver();
        if ($res && !empty($res['ts'])) {
            // If resolver flagged as UTC we already have UTC ts; else interpret as site-local and convert to UTC.
            $utc_ts = $res['is_utc'] ? $res['ts'] : $res['ts'] - (int) get_option('gmt_offset') * HOUR_IN_SECONDS;
            return ['gmt' => gmdate('Y-m-d H:i:s', $utc_ts), 'error' => null];
        }
    }

    return ['gmt' => null, 'error' => 'unparsed'];
}

/**
 * Convenience wrapper: returns GMT datetime string or null (ignores error detail).
 * @param string $raw
 * @return string|null
 */
function parse_schedule_datetime_simple_logic(string $raw): ?string
{
    $res = parse_schedule_datetime_logic($raw);
    return $res['gmt'];
}

/**
 * Computes the scheduled GMT time for a queue item based on task_config schedule settings.
 * Handles both schedule_mode=from_input (using provided schedule_date field or pipe/array tail) and schedule_mode=smart.
 * Ensures returned string is TRUE GMT (Y-m-d H:i:s) not local.
 *
 * @param array|string $item_data Structured array or raw line/string.
 * @param array $task_config Task configuration.
 * @param int $item_index Zero-based index of item in queue (used for smart schedule offset).
 * @param string $generation_mode Mode (bulk,csv,single,rss,gsheets,url...)
 * @return string|null GMT datetime string or null if not scheduled.
 */
function compute_item_schedule_gmt_logic($item_data, array $task_config, int $item_index, string $generation_mode): ?string
{
    $schedule_mode = $task_config['schedule_mode'] ?? 'immediate';
    $scheduled_gmt_time = null;

    if ($schedule_mode === 'from_input') {
        $date_str = '';
        if ($generation_mode === 'gsheets' && is_array($item_data) && !empty($item_data['schedule_date'])) {
            $date_str = $item_data['schedule_date'];
        } elseif (is_array($item_data) && !empty($item_data['schedule_date'])) {
            $date_str = $item_data['schedule_date'];
        } else {
            $raw = is_array($item_data) ? ($item_data['topic'] ?? '') : $item_data;
            if (is_string($raw) && strpos($raw, '|') !== false) {
                $parts = array_map('trim', explode('|', $raw));
                if (count($parts) > 1) {
                    $date_str_candidate = end($parts);
                    // Only treat it as date if it has digit
                    if (preg_match('/\d/', $date_str_candidate)) {
                        $date_str = $date_str_candidate;
                    }
                }
            }
        }
        if ($date_str !== '') {
            $parsed = parse_schedule_datetime_simple_logic($date_str);
            if ($parsed) {
                $scheduled_gmt_time = $parsed;
            }
        }
    } elseif ($schedule_mode === 'smart' && !empty($task_config['smart_schedule_start_datetime'])) {
        try {
            $start_local = new \DateTime($task_config['smart_schedule_start_datetime'], wp_timezone());
            $interval_value = absint($task_config['smart_schedule_interval_value'] ?? 1);
            $interval_unit = $task_config['smart_schedule_interval_unit'] ?? 'hours';
            $offset_value = $item_index * $interval_value;
            $start_local->modify("+{$offset_value} {$interval_unit}");
            // Convert the local scheduled time to GMT explicitly.
            $local_str = $start_local->format('Y-m-d H:i:s');
            $scheduled_gmt_time = get_gmt_from_date($local_str, 'Y-m-d H:i:s');
        } catch (\Exception $e) {
            $scheduled_gmt_time = null;
        }
    }
    return $scheduled_gmt_time;
}
