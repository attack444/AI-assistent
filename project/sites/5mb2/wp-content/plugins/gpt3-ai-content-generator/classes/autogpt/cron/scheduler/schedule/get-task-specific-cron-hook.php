<?php

namespace WPAICG\AutoGPT\Cron\Scheduler\Schedule;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Gets the hook name for a specific task's cron event.
*
* @param int $task_id The ID of the task.
* @return string The cron hook name.
*/
function get_task_specific_cron_hook_logic(int $task_id): string
{
    return 'aipkit_automated_task_' . $task_id;
}
