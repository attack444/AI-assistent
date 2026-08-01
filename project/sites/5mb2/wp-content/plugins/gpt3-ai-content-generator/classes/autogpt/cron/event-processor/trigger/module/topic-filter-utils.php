<?php


namespace WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies include/exclude keyword filters to a list of RSS/URL items.
 *
 * @param array  $items The array of items to filter (each must have a 'title' key).
 * @param string $include_keywords_str A comma-separated string of keywords to include.
 * @param string $exclude_keywords_str A comma-separated string of keywords to exclude.
 * @return array The filtered array of items.
 */
function apply_include_exclude_keywords_logic(array $items, string $include_keywords_str, string $exclude_keywords_str): array
{
    if (empty($include_keywords_str) && empty($exclude_keywords_str)) {
        return $items;
    }

    $include_keywords = !empty($include_keywords_str) ? array_map('trim', explode(',', strtolower($include_keywords_str))) : [];
    $exclude_keywords = !empty($exclude_keywords_str) ? array_map('trim', explode(',', strtolower($exclude_keywords_str))) : [];

    return array_filter($items, function ($item) use ($include_keywords, $exclude_keywords) {
        if (!isset($item['title'])) {
            return false;
        }
        $title_lower = strtolower($item['title']);

        // Exclude logic: if any exclude keyword is found, discard the item.
        if (!empty($exclude_keywords)) {
            foreach ($exclude_keywords as $keyword) {
                if ($keyword !== '' && strpos($title_lower, $keyword) !== false) {
                    return false;
                }
            }
        }

        // Include logic: if include keywords are provided, title MUST contain one of them.
        if (!empty($include_keywords)) {
            foreach ($include_keywords as $keyword) {
                if ($keyword !== '' && strpos($title_lower, $keyword) !== false) {
                    return true; // Found a match, keep it.
                }
            }
            return false; // No include keywords matched, discard it.
        }

        return true; // Keep if no include rules apply or only exclude rules passed.
    });
}
