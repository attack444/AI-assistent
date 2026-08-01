<?php

namespace WPAICG\AutoGPT\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns whether the given AutoGPT task type is Pro-only.
 */
function task_type_requires_pro_plan(string $task_type): bool
{
    return in_array(
        $task_type,
        [
            'content_writing_rss',
            'content_writing_url',
            'content_writing_gsheets',
            'enhance_existing_content',
        ],
        true
    );
}

/**
 * Returns whether the current site is running an active Pro plan.
 */
function is_pro_plan_active(): bool
{
    return class_exists('\WPAICG\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
}
