<?php


namespace WPAICG\AutoGPT\Ajax;

use WP_Error;

// Load all the new logic files
require_once __DIR__ . '/actions/run-now/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX request for running an automated task immediately by orchestrating
 * calls to modular logic functions.
 */
class AIPKit_Run_Automated_Task_Now_Action extends AIPKit_Automated_Task_Base_Ajax_Action
{
    public function handle_request()
    {

        // 1. Validate permissions and get the task data
        $task_or_error = Actions\RunNow\validate_task_and_permissions_logic($this);
        if (is_wp_error($task_or_error)) {
            $this->send_wp_error($task_or_error);
            return;
        }
        $task = $task_or_error;
        $task_id = (int)$task['id'];
        $task_config = json_decode($task['task_config'], true) ?: [];
        $task_type = $task['task_type'];

        // 2. Queue items based on task type
        switch (true) { // REVISED: Use switch(true) for clearer conditional logic
            case $task_type === 'content_indexing':
                Actions\RunNow\run_now_content_indexing_logic($task_id, $task_config);
                break;

            case strncmp($task_type, 'content_writing', strlen('content_writing')) === 0: // Covers 'content_writing' and 'content_writing_*'
                $result = Actions\RunNow\run_now_content_writing_logic($task_id, $task_config);
                if (is_wp_error($result)) {
                    $this->send_wp_error($result);
                    return;
                }
                break;

            case $task_type === 'community_reply_comments': // NEW CASE
                Actions\RunNow\run_now_comment_reply_logic($task_id, $task_config, $task['last_run_time']);
                break;

            case $task_type === 'enhance_existing_content':
                Actions\RunNow\run_now_content_enhancement_logic($task_id, $task_config);
                break;

            default:
                $this->send_wp_error(new WP_Error('unsupported_task_type_run_now', __('This task type does not support "Run Now".', 'gpt3-ai-content-generator')), 400);
                return;
        }

        // 3. Finalize the task run
        Actions\RunNow\finalize_run_now_task_logic($task_id);

        // 4. Send success response
        wp_send_json_success(['message' => __('Task run initiated. Check queue for progress.', 'gpt3-ai-content-generator')]);
    }
}
