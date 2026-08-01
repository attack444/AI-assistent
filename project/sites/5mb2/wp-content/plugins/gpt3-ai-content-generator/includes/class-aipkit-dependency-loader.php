<?php


namespace WPAICG\Includes;

// Use statements for the new loader classes
use WPAICG\Includes\DependencyLoaders\Admin_Asset_Handlers_Loader;
use WPAICG\Includes\DependencyLoaders\Provider_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Core_Services_Loader;
use WPAICG\Includes\DependencyLoaders\Dashboard_Base_Classes_Loader;
use WPAICG\Includes\DependencyLoaders\Base_Ajax_Handlers_Loader;
use WPAICG\Includes\DependencyLoaders\Chat_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Speech_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Stt_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Rest_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Image_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Vector_Store_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Vector_Store_Ajax_Handlers_Loader;
use WPAICG\Includes\DependencyLoaders\Vector_Post_Processor_Classes_Loader;
use WPAICG\Includes\DependencyLoaders\Content_Writer_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Addon_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Post_Enhancer_Core_Loader;
use WPAICG\Includes\DependencyLoaders\Woocommerce_Writer_Loader;
use WPAICG\Includes\DependencyLoaders\Automated_Task_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Automated_Task_Ajax_Handlers_Loader;
use WPAICG\Includes\DependencyLoaders\Automated_Task_Cron_Helpers_Loader;
use WPAICG\Includes\DependencyLoaders\Hook_Registrars_Loader;
use WPAICG\Includes\DependencyLoaders\AI_Forms_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\Core_Moderation_Dependencies_Loader;
use WPAICG\Includes\DependencyLoaders\WP_AI_Client_Dependencies_Loader;


// Ensure this file is only loaded by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Dependency_Loader
 * Handles loading all necessary plugin dependencies.
 * Loads core plugin files and then delegates to specialized loader classes.
 */
class AIPKit_Dependency_Loader
{
    /**
     * Load all required dependencies for the plugin.
     */
    public static function load()
    {
        $admin_like_request = self::is_admin_like_request();
        $automation_request = self::is_automation_request();
        // Core Plugin Files (Loaded directly before specialized loaders)
        require_once WPAICG_PLUGIN_DIR . 'includes/class-wp-ai-content-generator-i18n.php';
        require_once WPAICG_PLUGIN_DIR . 'public/class-wp-ai-content-generator-public.php';
        require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-blocks-manager.php';
        require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-shortcodes-manager.php';
        require_once WPAICG_PLUGIN_DIR . 'includes/database-schema.php';
        require_once WPAICG_PLUGIN_DIR . 'classes/seo/seo-helper.php';
        require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-upload-utils.php';
        $toc_generator_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-toc-generator.php';
        if (file_exists($toc_generator_path)) {
            require_once $toc_generator_path;
        }
        $identifier_utils_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-identifier-utils.php';
        if (file_exists($identifier_utils_path)) {
            require_once $identifier_utils_path;
        }
        $prompt_sanitizer_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-prompt-sanitizer.php';
        if (file_exists($prompt_sanitizer_path)) {
            require_once $prompt_sanitizer_path;
        }
        $header_buttons_util_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-admin-header-action-buttons.php';
        if (file_exists($header_buttons_util_path)) {
            require_once $header_buttons_util_path;
        }
        $cors_manager_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-cors-manager.php';
        if (file_exists($cors_manager_path)) {
            require_once $cors_manager_path;
            // Initialize the CORS manager
            \WPAICG\Utils\AIPKit_CORS_Manager::init();
        }


        // --- Load the new specialized loader class files ---
        $loaders_path = WPAICG_PLUGIN_DIR . 'includes/dependency-loaders/';
        require_once $loaders_path . 'loaders.php';
        // --- END Load the new specialized loader class files ---

        // Call specialized loaders
        if ($admin_like_request) {
            Admin_Asset_Handlers_Loader::load();
        }
        Provider_Dependencies_Loader::load();
        Core_Services_Loader::load();
        Dashboard_Base_Classes_Loader::load();
        Base_Ajax_Handlers_Loader::load();
        Chat_Dependencies_Loader::load();
        Speech_Dependencies_Loader::load();
        Stt_Dependencies_Loader::load();
        // REST route registration happens during plugin bootstrap, before REST_REQUEST
        // is reliably defined for the current request.
        Rest_Dependencies_Loader::load();
        Image_Dependencies_Loader::load();
        Vector_Store_Dependencies_Loader::load();
        if ($admin_like_request) {
            Vector_Store_Ajax_Handlers_Loader::load();
            Vector_Post_Processor_Classes_Loader::load();
        }
        if ($admin_like_request || $automation_request) {
            Content_Writer_Dependencies_Loader::load();
        }
        Addon_Dependencies_Loader::load();
        if ($admin_like_request) {
            Post_Enhancer_Core_Loader::load();
        }
        Woocommerce_Writer_Loader::load();
        if ($admin_like_request || $automation_request) {
            Automated_Task_Dependencies_Loader::load();
            Automated_Task_Cron_Helpers_Loader::load();
        }
        if ($admin_like_request) {
            Automated_Task_Ajax_Handlers_Loader::load();
        }
        Hook_Registrars_Loader::load();
        AI_Forms_Dependencies_Loader::load();
        Core_Moderation_Dependencies_Loader::load();
        WP_AI_Client_Dependencies_Loader::load();
    }

    private static function is_admin_like_request(): bool
    {
        return is_admin() || wp_doing_ajax();
    }

    private static function is_automation_request(): bool
    {
        if (wp_doing_cron()) {
            return true;
        }

        return defined('WP_CLI') && WP_CLI;
    }

}
