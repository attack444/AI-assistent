<?php

namespace WPAICG\AutoGPT\Ajax\Actions\RunNow;

use WPAICG\AutoGPT\Ajax\AIPKit_Run_Automated_Task_Now_Action;
use WPAICG\AutoGPT\Helpers;
use WP_Error;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Content_Queuer;
use WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules as ContentWritingModules;
use WPAICG\AutoGPT\Cron\EventProcessor\Trigger;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor;

if (!defined('ABSPATH')) {
    exit;
}

require_once WPAICG_PLUGIN_DIR . 'classes/autogpt/helpers/task-type-access.php';

/**
 * Validates the request for running a task now.
 * Checks permissions, task ID, and task status.
 *
 * @param AIPKit_Run_Automated_Task_Now_Action $handler The handler instance.
 * @return array|WP_Error The task data array on success, or a WP_Error on failure.
 */
function validate_task_and_permissions_logic(AIPKit_Run_Automated_Task_Now_Action $handler)
{
    // Check permissions
    $permission_check = $handler->check_module_access_permissions('autogpt', $handler::NONCE_ACTION);
    if (is_wp_error($permission_check)) {
        return $permission_check;
    }

    // Get and validate task ID
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
    $task_id = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;

    if (empty($task_id)) {
        return new WP_Error('missing_task_id_run_now', __('Task ID is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // Fetch and validate the task from the database
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caching is handled at the read level.
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks_table_name} WHERE id = %d", $task_id), ARRAY_A);

    if (!$task) {
        return new WP_Error('task_not_found_run', __('Task not found.', 'gpt3-ai-content-generator'), ['status' => 404]);
    }

    if ($task['status'] !== 'active') {
        return new WP_Error('task_not_active_run', __('Task must be active to run now.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (Helpers\task_type_requires_pro_plan((string) ($task['task_type'] ?? '')) && !Helpers\is_pro_plan_active()) {
        return new WP_Error('task_type_requires_pro_plan_run_now', __('This is a Pro feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    return $task;
}

/**
 * Queues all existing matching content for a "Run Now" action on a content indexing task.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @return void
 */
function run_now_content_indexing_logic(int $task_id, array $task_config): void
{
    if (class_exists(AIPKit_Automated_Task_Content_Queuer::class)) {
        // For "Run Now", we want to queue all existing content that matches, ignoring last run time.
        AIPKit_Automated_Task_Content_Queuer::maybe_queue_initial_indexing_content($task_id, $task_config, true);
    }
}

$aipkit_autogpt_modules_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/event-processor/trigger/module/';
require_once $aipkit_autogpt_modules_path . 'methods.php';

/**
 * Queues items for a "Run Now" action on a content writing task.
 * This function now acts as an orchestrator, delegating logic to modular components.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @return true|WP_Error True on success, WP_Error if no titles are found.
 */
function run_now_content_writing_logic(int $task_id, array $task_config)
{
    $generation_mode = $task_config['cw_generation_mode'] ?? 'single';
    $topics_to_queue = [];
    $scraped_contexts = [];

    // 1. Generate items based on the generation mode
    switch ($generation_mode) {
        case 'rss':
            // For a "Run Now" action on RSS, we pass null to get all recent items, not just since last run.
            $topics_to_queue = ContentWritingModules\rss_mode_generate_items_logic($task_id, $task_config, null);
            break;
        case 'gsheets':
            $topics_to_queue = ContentWritingModules\gsheets_mode_generate_items_logic($task_id, $task_config);
            break;
        case 'url':
            $result = ContentWritingModules\url_mode_generate_items_logic($task_id, $task_config);
            if (!is_wp_error($result)) {
                $topics_to_queue = $result['topics'];
                $scraped_contexts = $result['contexts'];
            } else {
                $topics_to_queue = $result; // Pass the WP_Error object
            }
            break;
        default: // 'single', 'bulk', 'csv'
            $topics_to_queue = ContentWritingModules\manual_mode_generate_items_logic($task_config);
            break;
    }

    if (is_wp_error($topics_to_queue)) {
        return $topics_to_queue;
    }
    if (empty($topics_to_queue)) {
        return new WP_Error('no_titles_to_queue', __('No new or valid items found in the source to generate content for.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    // 2. Loop through generated items and queue them
    $queued_count = 0;
    $item_index = 0;
    foreach ($topics_to_queue as $index => $item_data) {
        $item_config = ContentWritingModules\prepare_item_config_logic($item_data, $task_config, $scraped_contexts);
        $item_config['task_id'] = $task_id;
        if (empty($item_config['content_title'])) {
            continue;
        }

        // Unified scheduling helper
        $scheduled_gmt_time = ContentWritingModules\compute_item_schedule_gmt_logic($item_data, $task_config, $item_index, $generation_mode);
        if ($scheduled_gmt_time) {
            $item_config['scheduled_gmt_time'] = $scheduled_gmt_time;
        }

        $target_identifier = ContentWritingModules\generate_target_identifier_logic($item_data, $task_id, $index);
        if ($generation_mode !== 'bulk' && $generation_mode !== 'csv' && $generation_mode !== 'single') {
            if (ContentWritingModules\is_duplicate_topic_logic($task_id, $target_identifier)) {
                continue;
            }
        }

        if (ContentWritingModules\insert_topic_into_queue_logic($task_id, $target_identifier, $item_config)) {
            $queued_count++;
            $item_index++;
        }
    }

    return true;
}

/**
 * Queues items for a "Run Now" action on a comment reply task.
 * This is essentially the same as a scheduled trigger.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @param string|null $last_run_time The last time the task ran.
 * @return void
 */
function run_now_comment_reply_logic(int $task_id, array $task_config, ?string $last_run_time): void
{
    // The logic to queue comments is the same whether it's a scheduled run or a "Run Now" trigger.
    // It checks for comments created since the last run time.
    if (function_exists('\WPAICG\AutoGPT\Cron\EventProcessor\Trigger\trigger_comment_reply_task_logic')) {
        Trigger\trigger_comment_reply_task_logic($task_id, $task_config, null);
    }
}

/**
 * Queues items for a "Run Now" action on a content enhancement task.
 * This is the same logic as the scheduled trigger.
 *
 * @param int $task_id The ID of the task.
 * @param array $task_config The configuration of the task.
 * @return void
 */
function run_now_content_enhancement_logic(int $task_id, array $task_config): void
{
    // The logic to queue posts for enhancement is the same whether it's a scheduled run or a "Run Now" trigger.
    if (function_exists('\WPAICG\AutoGPT\Cron\EventProcessor\Trigger\trigger_content_enhancement_task_logic')) {
        // For "Run Now", we always pass null for last_run_time to get all posts matching criteria.
        Trigger\trigger_content_enhancement_task_logic($task_id, $task_config, null);
    }
}

/**
 * Finalizes the "Run Now" action by updating the task's last run time
 * and triggering the queue processor.
 *
 * @param int $task_id The ID of the task.
 * @return void
 */
function finalize_run_now_task_logic(int $task_id): void
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';

    // Update last_run_time for the main task
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table is necessary for this action.
    $wpdb->update(
        $tasks_table_name,
        ['last_run_time' => current_time('mysql', 1)],
        ['id' => $task_id],
        ['%s'],
        ['%d']
    );

    // Trigger main queue processing immediately by scheduling a one-off event
    if (class_exists(AIPKit_Automated_Task_Event_Processor::class)) {
        // Schedule a one-off event to start processing the queue almost immediately.
        // This decouples the potentially long-running queue processing from the AJAX request.
        wp_schedule_single_event(time() + 5, AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK);
    }
}
