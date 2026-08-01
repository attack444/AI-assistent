<?php

namespace WPAICG\AutoGPT\Ajax;

use WP_Error;
use WPAICG\AutoGPT\Ajax\Actions\SaveTask;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load the save-task action helpers.
require_once __DIR__ . '/actions/save-task/methods.php';


/**
* Handles AJAX request for saving an automated task by orchestrating calls
* to modular logic functions.
*/
class AIPKit_Save_Automated_Task_Action extends AIPKit_Automated_Task_Base_Ajax_Action
{
    public function handle_request()
    {
        // 1. Validate permissions and nonce first
        $permission_check = $this->check_module_access_permissions('autogpt', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // Nonce is verified, now we can process $_POST data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $post_data = wp_unslash($_POST);

        // 2. Validate the basic request parameters
        $validated_request = SaveTask\validate_task_request_logic($this, $post_data);
        if (is_wp_error($validated_request)) {
            $this->send_wp_error($validated_request);
            return;
        }
        $task_id = $validated_request['task_id'];
        $task_name = $validated_request['task_name'];
        $task_type = $validated_request['task_type'];
        $is_new_task = ($task_id === 0);

        // 3. Build task-specific configuration
        $task_config_or_error = null;
        if ($task_type === 'content_indexing') {
            $task_config_or_error = SaveTask\build_task_config_indexing_logic($post_data);
        } elseif (strncmp($task_type, 'content_writing', strlen('content_writing')) === 0) {
            $task_config_or_error = SaveTask\build_task_config_writing_logic($post_data);
        } elseif ($task_type === 'community_reply_comments') {
            $task_config_or_error = SaveTask\build_task_config_comment_reply_logic($post_data);
        } elseif ($task_type === 'enhance_existing_content') {
            $task_config_or_error = SaveTask\build_task_config_enhancement_logic($post_data);
        } else {
            $this->send_wp_error(new WP_Error('unsupported_task_type', __('The specified task type is not supported.', 'gpt3-ai-content-generator')), 400);
            return;
        }

        if (is_wp_error($task_config_or_error)) {
            $this->send_wp_error($task_config_or_error);
            return;
        }
        $task_config = $task_config_or_error;
        // Add task_type to config for later reference in cron jobs
        $task_config['task_type'] = $task_type;

        // 4. Get task status from POST
        $task_status = isset($post_data['task_status']) && in_array($post_data['task_status'], ['active', 'paused']) ? sanitize_key($post_data['task_status']) : 'active';

        // 5. Save the task to the database
        $saved_task_id_or_error = SaveTask\save_task_to_database_logic($task_name, $task_type, $task_config, $task_status, $task_id);
        if (is_wp_error($saved_task_id_or_error)) {
            $this->send_wp_error($saved_task_id_or_error);
            return;
        }
        $final_task_id = $saved_task_id_or_error;

        // 6. Finalize the save (scheduling, etc.)
        SaveTask\finalize_task_save_logic($final_task_id, $task_config, $task_status, $is_new_task);

        // 7. Send success response
        wp_send_json_success(['message' => __('Task saved successfully.', 'gpt3-ai-content-generator'), 'task_id' => $final_task_id]);
    }
}
