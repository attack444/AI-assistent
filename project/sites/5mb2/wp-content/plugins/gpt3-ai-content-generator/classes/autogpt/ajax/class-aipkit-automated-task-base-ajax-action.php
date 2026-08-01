<?php

namespace WPAICG\AutoGPT\Ajax;

use WPAICG\Dashboard\Ajax\BaseDashboardAjaxHandler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Base class for AutoGPT Automated Task AJAX actions.
 */
abstract class AIPKit_Automated_Task_Base_Ajax_Action extends BaseDashboardAjaxHandler {

    protected $tasks_table_name;
    protected $queue_table_name;
    const NONCE_ACTION = 'aipkit_automated_tasks_manage_nonce'; // Consistent nonce for all task management actions

    public function __construct() {
        global $wpdb;
        $this->tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
        $this->queue_table_name = $wpdb->prefix . 'aipkit_automated_task_queue';
    }

    /**
     * Abstract method to be implemented by child classes to handle the specific AJAX request.
     */
    abstract public function handle_request();
}