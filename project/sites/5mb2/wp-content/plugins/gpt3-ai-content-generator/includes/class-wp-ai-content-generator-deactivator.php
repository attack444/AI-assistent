<?php


namespace WPAICG;

use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\AIPKit_Event_Queue_Worker;
use WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor;
use WPAICG\Chat\Storage\LogCronManager; // NEW: For unscheduling
use WPAICG\Lib\Integrations\Logs\AIPKit_Recipe_Delivery_Log_Maintenance;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin deactivation.
 */
class WP_AI_Content_Generator_Deactivator
{
    public static function deactivate()
    {
        if (class_exists('\\WPAICG\\Core\\TokenManager\\AIPKit_Token_Manager')) {
            AIPKit_Token_Manager::unschedule_token_reset_event();
        }

        if (class_exists('\\WPAICG\\Core\\Stream\\Cache\\AIPKit_SSE_Message_Cache')) {
            AIPKit_SSE_Message_Cache::unschedule_cleanup_event();
        }

        if (class_exists('\\WPAICG\\Chat\\Storage\\LogCronManager')) {
            LogCronManager::unschedule_event();
        }

        $recipe_log_maintenance_path = WPAICG_PLUGIN_DIR . 'lib/integrations/logs/class-aipkit-recipe-delivery-log-maintenance.php';
        if (file_exists($recipe_log_maintenance_path) && !class_exists(\WPAICG\Lib\Integrations\Logs\AIPKit_Recipe_Delivery_Log_Maintenance::class)) {
            require_once $recipe_log_maintenance_path;
        }
        if (class_exists('\\WPAICG\\Lib\\Integrations\\Logs\\AIPKit_Recipe_Delivery_Log_Maintenance')) {
            AIPKit_Recipe_Delivery_Log_Maintenance::unschedule_cleanup();
        }

        $event_queue_worker_path = WPAICG_PLUGIN_DIR . 'classes/core/class-aipkit-event-queue-worker.php';
        if (file_exists($event_queue_worker_path) && !class_exists(\WPAICG\Core\AIPKit_Event_Queue_Worker::class)) {
            require_once $event_queue_worker_path;
        }
        if (class_exists('\\WPAICG\\Core\\AIPKit_Event_Queue_Worker')) {
            AIPKit_Event_Queue_Worker::unschedule_cron();
        }

        $automated_task_scheduler_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/class-aipkit-automated-task-scheduler.php';
        $automated_task_event_processor_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/class-aipkit-automated-task-event-processor.php';

        if (file_exists($automated_task_scheduler_path) && !class_exists(\WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler::class)) {
            require_once $automated_task_scheduler_path;
        }
        if (file_exists($automated_task_event_processor_path) && !class_exists(\WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Event_Processor::class)) {
            require_once $automated_task_event_processor_path;
        }

        if (class_exists('\\WPAICG\\AutoGPT\\Cron\\AIPKit_Automated_Task_Scheduler') && class_exists('\\WPAICG\\AutoGPT\\Cron\\AIPKit_Automated_Task_Event_Processor')) {
            AIPKit_Automated_Task_Scheduler::clear_all_task_events();
            wp_clear_scheduled_hook(AIPKit_Automated_Task_Event_Processor::MAIN_CRON_HOOK);
        }
    }
}
