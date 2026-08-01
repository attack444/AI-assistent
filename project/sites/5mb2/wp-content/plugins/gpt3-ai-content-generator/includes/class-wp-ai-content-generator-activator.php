<?php


namespace WPAICG;

use WPAICG\Chat\Storage\DefaultBotSetup;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache;
use WPAICG\AutoGPT\AIPKit_Automated_Task_Cron;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Template_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation. Also contains multisite setup logic.
 */
class WP_AI_Content_Generator_Activator
{
    /**
     * Main activation routine for single site or per-site activation.
     * REVISED: This method is now significantly leaner. It only handles tasks that
     * absolutely must run on first-time activation, like creating database tables.
     * Other setup tasks (default content, cron scheduling) are moved to the
     * `check_for_updates` hook which runs on `init` and is triggered by the version option.
     */
    public static function activate()
    {
        // Create database tables if they don't exist.
        self::setup_tables_for_blog();

        // Load the main plugin class to get access to constants.
        if (!class_exists(WP_AI_Content_Generator::class)) {
            require_once WPAICG_PLUGIN_DIR . 'includes/class-wp-ai-content-generator.php';
        }
        // Set the current version in the database. This is crucial for the `check_for_updates`
        // routine to correctly trigger on new installs and version changes.
        update_option(WP_AI_Content_Generator::DB_VERSION_OPTION, WPAICG_VERSION, 'no');
        update_option(WP_AI_Content_Generator::TOKEN_MANAGER_SCHEMA_VERSION_OPTION, WP_AI_Content_Generator::TOKEN_MANAGER_SCHEMA_VERSION, 'no');

        // --- MODIFICATION: Consolidate all one-time/update tasks here ---
        // This ensures that fresh installs and reactivations get all necessary setup routines.
        // The check_for_updates() hook will also call these, which is a safe redundancy for version updates.

        // Update Role Manager permissions, migrating old caps if necessary.
        $role_manager_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_role_manager.php';
        if (file_exists($role_manager_path)) {
            require_once $role_manager_path;
            if (class_exists('\\WPAICG\\AIPKit_Role_Manager')) {
                \WPAICG\AIPKit_Role_Manager::update_permissions_on_activation();
            }
        }

        // Ensure Default Chatbot exists.
        $default_bot_setup_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_default_bot_setup.php';
        if (file_exists($default_bot_setup_path)) {
            require_once $default_bot_setup_path;
            if (class_exists('\\WPAICG\\Chat\\Storage\\DefaultBotSetup')) {
                \WPAICG\Chat\Storage\DefaultBotSetup::ensure_default_chatbot();
            }
        }

        // Ensure Default Content Writer Template exists.
        $cw_template_manager_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-content-writer-template-manager.php';
        if (file_exists($cw_template_manager_path)) {
            require_once $cw_template_manager_path;
            if (class_exists('\\WPAICG\\ContentWriter\\AIPKit_Content_Writer_Template_Manager')) {
                \WPAICG\ContentWriter\AIPKit_Content_Writer_Template_Manager::ensure_default_template_exists();
            }
        }

        // Schedule cron jobs (methods are idempotent, so it's safe to run).
        if (class_exists(AIPKit_Token_Manager::class)) {
            AIPKit_Token_Manager::schedule_token_reset_event();
        }
        if (class_exists(AIPKit_SSE_Message_Cache::class)) {
            AIPKit_SSE_Message_Cache::schedule_cleanup_event();
        }
        if (class_exists(AIPKit_Automated_Task_Cron::class)) {
            AIPKit_Automated_Task_Cron::init();
        }
    }

    public static function setup_tables_for_blog($blog_id = null)
    {
        $switched = false;
        if (is_multisite() && $blog_id !== null && get_current_blog_id() !== $blog_id) {
            switch_to_blog($blog_id);
            $switched = true;
        }
        $db_schema_path = WPAICG_PLUGIN_DIR . 'includes/database-schema.php';
        if (!file_exists($db_schema_path)) {
            if ($switched) {
                restore_current_blog();
            }
            return;
        }
        require_once $db_schema_path;

        if (function_exists('aipkit_create_logs_table')) {
            aipkit_create_logs_table();
        }
        if (function_exists('aipkit_create_guest_token_usage_table')) {
            aipkit_create_guest_token_usage_table();
        }
        if (function_exists('aipkit_create_sse_message_cache_table')) {
            aipkit_create_sse_message_cache_table();
        }
        if (function_exists('aipkit_create_vector_data_source_table')) {
            aipkit_create_vector_data_source_table();
        }
        if (function_exists('aipkit_create_automated_tasks_table')) {
            aipkit_create_automated_tasks_table();
        }
        if (function_exists('aipkit_create_automated_task_queue_table')) {
            aipkit_create_automated_task_queue_table();
        }
        if (function_exists('aipkit_create_content_writer_templates_table')) {
            aipkit_create_content_writer_templates_table();
        }
        if (function_exists('aipkit_create_rss_history_table')) {
            aipkit_create_rss_history_table();
        }
        if (function_exists('aipkit_create_event_delivery_queue_table')) {
            aipkit_create_event_delivery_queue_table();
        }
        if (function_exists('aipkit_create_recipe_delivery_logs_table')) {
            aipkit_create_recipe_delivery_logs_table();
        }
        if (function_exists('aipkit_create_pricing_rules_table')) {
            aipkit_create_pricing_rules_table();
        }
        if (function_exists('aipkit_create_token_ledger_table')) {
            aipkit_create_token_ledger_table();
        }
        if ($switched) {
            restore_current_blog();
        }
    }

    public static function setup_new_blog($blog, $user_id)
    {
        $blog_id = is_object($blog) ? $blog->blog_id : (is_array($blog) ? $blog['blog_id'] : 0);
        if ($blog_id > 0) {
            self::setup_tables_for_blog($blog_id);
            switch_to_blog($blog_id);
            if (class_exists('\\WPAICG\\Chat\\Storage\\DefaultBotSetup')) {
                DefaultBotSetup::ensure_default_chatbot();
            }
            if (class_exists('\\WPAICG\\ContentWriter\\AIPKit_Content_Writer_Template_Manager')) {
                AIPKit_Content_Writer_Template_Manager::ensure_default_template_exists();
            }
            restore_current_blog();
        }
    }

}
