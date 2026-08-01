<?php

namespace WPAICG\Chat;

// Core classes instantiated in register_hooks
use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Chat\Frontend; // Keep this as the new Frontend\Assets is here
use WPAICG\Chat\Storage;
use WPAICG\Chat\Admin\Ajax;
use WPAICG\Core\Stream\Handler\SSEHandler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Require the new initializer method files
$initializer_methods_path = WPAICG_PLUGIN_DIR . 'classes/chat/initializer/';
require_once $initializer_methods_path . 'methods.php';


/**
 * Initializes the AIPKit Chat functionality by loading dependencies and registering hooks.
 * Logic for methods is now in separate files under the Initializer namespace.
 */
class Initializer
{
    /**
     * Ensure dependencies specific to Chat module hooks are loaded.
     * Note: This is largely redundant if Chat_Dependencies_Loader has already run.
     */
    public static function load_dependencies()
    {
        // Call the externalized logic functions
        Initializer\load_core_services_logic();
        Initializer\load_admin_setup_logic();
        Initializer\load_ajax_handlers_logic();
        Initializer\load_frontend_logic(); // This loads Frontend\Assets orchestrator
        Initializer\load_utils_logic();
        Initializer\load_sse_handler_logic();
    }

    /**
     * Register WordPress hooks conditionally.
     * Called by the main plugin class via Module_Initializer_Hooks_Registrar.
     */
    public static function register_hooks()
    {
        // self::load_dependencies(); // Dependencies should be loaded by AIPKit_Hook_Manager or earlier

        // Instantiate handlers needed for hook registration
        $admin_setup     = new AdminSetup();
        $shortcode       = new Frontend\Shortcode();
        $assets          = new Frontend\Assets();

        if (class_exists(Storage\LogCronManager::class)) {
            add_action(Storage\LogCronManager::HOOK_NAME, ['WPAICG\Chat\Storage\LogCronManager', 'run_pruning']);
        }

        // Core hooks (CPT, Shortcode, Assets) are needed on every request.
        Initializer\register_hooks_core_logic($admin_setup, $shortcode, $assets);

        if (!(is_admin() || wp_doing_ajax())) {
            return;
        }

        $sse_handler     = class_exists(SSEHandler::class) ? new SSEHandler() : null;

        // Instantiate specific Admin AJAX Handlers
        if (!class_exists('\\WPAICG\\Chat\\Admin\\Ajax\\BaseAjaxHandler')) {
            return;
        }
        $chatbot_ajax_handler = new Ajax\ChatbotAjaxHandler();
        $conversation_ajax_handler = new Ajax\ConversationAjaxHandler();
        $chatbot_image_ajax_handler = null;
        if (class_exists(\WPAICG\Chat\Admin\Ajax\ChatbotImageAjaxHandler::class)) {
            $chatbot_image_ajax_handler = new Ajax\ChatbotImageAjaxHandler();
        }

        // Call externalized hook registration logic
        if (is_admin() || wp_doing_ajax()) {
            Initializer\register_hooks_admin_ajax_logic(
                $chatbot_ajax_handler,
                $conversation_ajax_handler
            );
            // General AJAX hooks (frontend messages, speech, etc.) that also need admin context
            Initializer\register_hooks_general_ajax_logic(
                $conversation_ajax_handler,
                $chatbot_image_ajax_handler
            );
            // SSE AJAX hooks
            Initializer\register_hooks_sse_ajax_logic($sse_handler);
        }
    }
}
