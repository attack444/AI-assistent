<?php

namespace WPAICG\Chat\Initializer;

use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Chat\Frontend\Shortcode;
use WPAICG\Chat\Frontend\Assets;
use WPAICG\Chat\Admin\Ajax; // Namespace for AJAX Handlers
use WPAICG\Chat\Admin\Ajax\ConversationAjaxHandler;
use WPAICG\Chat\Admin\Ajax\ChatbotImageAjaxHandler; // Assuming this is the correct handler for chat_generate_image
use WPAICG\Core\Stream\Handler\SSEHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic for loading Core Chat Service dependencies.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_core_services_logic(): void {
    $base_path = WPAICG_PLUGIN_DIR . 'classes/chat/core/';
    $core_service_paths = [
        'ai_service.php' => \WPAICG\Chat\Core\AIService::class,
        'class-aipkit_content_aware.php' => \WPAICG\Chat\Core\AIPKit_Content_Aware::class,
    ];

    foreach ($core_service_paths as $file => $class_name) {
        $full_path = $base_path . $file;
        if (file_exists($full_path) && !class_exists($class_name)) {
            require_once $full_path;
        }
    }
}

/**
 * Logic for loading Chat Admin Setup dependencies.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_admin_setup_logic(): void {
    $base_path = WPAICG_PLUGIN_DIR . 'classes/chat/';
    $admin_setup_path = $base_path . 'admin/chat_admin_setup.php';

    if (file_exists($admin_setup_path) && !class_exists(\WPAICG\Chat\Admin\AdminSetup::class)) {
        require_once $admin_setup_path;
    }
}

/**
 * Logic for loading Chat AJAX Handler dependencies.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_ajax_handlers_logic(): void {
    $base_path = WPAICG_PLUGIN_DIR . 'classes/chat/';
    $ajax_handlers_paths = [
        'admin/ajax/chatbot_ajax_handler.php',
        'admin/ajax/conversation_ajax_handler.php',
        'admin/ajax/class-aipkit-chatbot-image-ajax-handler.php',
    ];

    // BaseAjaxHandler should be loaded by Base_Ajax_Handlers_Loader, not here directly.
    // But if it's a specific dependency for these handlers, ensure it's noted or handled.

    foreach ($ajax_handlers_paths as $handler_path_relative) {
        $full_path = $base_path . $handler_path_relative;
        $class_name_base = basename($handler_path_relative, '.php');
        // Basic class name derivation (might need adjustment for prefixed classes)
        $class_name_parts = explode('-', str_replace('class-aipkit-', '', $class_name_base));
        $class_name = '\\WPAICG\\Chat\\Admin\\Ajax\\';
        foreach ($class_name_parts as $part) {
            $class_name .= ucfirst($part);
        }
        // Specific class name for ChatbotImageAjaxHandler
        if ($handler_path_relative === 'admin/ajax/class-aipkit-chatbot-image-ajax-handler.php') {
            $class_name = '\\WPAICG\\Chat\\Admin\\Ajax\\ChatbotImageAjaxHandler';
        } elseif ($handler_path_relative === 'admin/ajax/chatbot_ajax_handler.php') {
             $class_name = '\\WPAICG\\Chat\\Admin\\Ajax\\ChatbotAjaxHandler';
        } elseif ($handler_path_relative === 'admin/ajax/conversation_ajax_handler.php') {
             $class_name = '\\WPAICG\\Chat\\Admin\\Ajax\\ConversationAjaxHandler';
        }


        if (file_exists($full_path) && !class_exists($class_name)) {
            require_once $full_path;
        }
    }
}

/**
 * Logic for loading Chat Frontend dependencies.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_frontend_logic(): void {
    $base_path = WPAICG_PLUGIN_DIR . 'classes/chat/frontend/';
    $frontend_paths = [
        'chat_assets.php' => \WPAICG\Chat\Frontend\Assets::class,
        'shortcode/shortcode_validator.php' => \WPAICG\Chat\Frontend\Shortcode\Validator::class,
        'shortcode/shortcode_dataprovider.php' => \WPAICG\Chat\Frontend\Shortcode\DataProvider::class,
        'shortcode/shortcode_featuremanager.php' => \WPAICG\Chat\Frontend\Shortcode\FeatureManager::class,
        'shortcode/shortcode_configurator.php' => \WPAICG\Chat\Frontend\Shortcode\Configurator::class,
        'shortcode/shortcode_renderer.php' => \WPAICG\Chat\Frontend\Shortcode\Renderer::class,
        'shortcode/shortcode_sitewidehandler.php' => \WPAICG\Chat\Frontend\Shortcode\SiteWideHandler::class,
        'chat_shortcode.php' => \WPAICG\Chat\Frontend\Shortcode::class,
    ];

    foreach ($frontend_paths as $file => $class_name) {
        $full_path = $base_path . $file;
        if (file_exists($full_path) && !class_exists($class_name)) {
            require_once $full_path;
        }
    }
}

/**
 * Logic for loading Chat Utility dependencies.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_utils_logic(): void {
    $svg_icons_path = WPAICG_PLUGIN_DIR . 'classes/chat/utils/class-aipkit-svg-icons.php';
    if (file_exists($svg_icons_path) && !class_exists(\WPAICG\Chat\Utils\AIPKit_SVG_Icons::class)) {
        require_once $svg_icons_path;
    }
}

/**
 * Logic for loading the Core SSE Handler dependency.
 * Called by WPAICG\Chat\Initializer::load_dependencies().
 */
function load_sse_handler_logic(): void {
    $sse_handler_path = WPAICG_PLUGIN_DIR . 'classes/core/stream/handler/class-sse-handler.php';
    if (file_exists($sse_handler_path) && !class_exists(\WPAICG\Core\Stream\Handler\SSEHandler::class)) {
        require_once $sse_handler_path;
    }
}

/**
 * Logic for registering core Chat module hooks (CPT, shortcode, assets).
 * Called by WPAICG\Chat\Initializer::register_hooks().
 *
 * @param AdminSetup $admin_setup
 * @param Shortcode $shortcode
 * @param Assets $assets
 * @return void
 */
function register_hooks_core_logic(
    AdminSetup $admin_setup,
    Shortcode $shortcode,
    Assets $assets
): void {
    add_action('init', [$admin_setup, 'register_chatbot_post_type']);
    add_shortcode('aipkit_chatbot', [$shortcode, 'render_chatbot_shortcode']);
    $assets->register_hooks(); // This internally adds 'wp_enqueue_scripts' and 'template_redirect'
}

/**
 * Logic for registering admin-specific AJAX hooks for the Chat module.
 * Called by WPAICG\Chat\Initializer::register_hooks().
 *
 * @param Ajax\ChatbotAjaxHandler $chatbot_ajax_handler
 * @param Ajax\ConversationAjaxHandler $conversation_ajax_handler
 * @return void
 */
function register_hooks_admin_ajax_logic(
    Ajax\ChatbotAjaxHandler $chatbot_ajax_handler,
    Ajax\ConversationAjaxHandler $conversation_ajax_handler
): void {
    add_action('wp_ajax_aipkit_create_chatbot', [$chatbot_ajax_handler, 'ajax_create_chatbot']);
    add_action('wp_ajax_aipkit_save_chatbot_settings', [$chatbot_ajax_handler, 'ajax_save_chatbot_settings']);
    add_action('wp_ajax_aipkit_delete_chatbot', [$chatbot_ajax_handler, 'ajax_delete_chatbot']);
    add_action('wp_ajax_aipkit_duplicate_chatbot', [$chatbot_ajax_handler, 'ajax_duplicate_chatbot']);
    add_action('wp_ajax_aipkit_get_chatbot_shortcode', [$chatbot_ajax_handler, 'ajax_get_chatbot_shortcode']);
    add_action('wp_ajax_aipkit_reset_chatbot_settings', [$chatbot_ajax_handler, 'ajax_reset_chatbot_settings']);
    add_action('wp_ajax_aipkit_rename_chatbot', [$chatbot_ajax_handler, 'ajax_rename_chatbot']);
    add_action('wp_ajax_aipkit_update_chatbot_instructions', [$chatbot_ajax_handler, 'ajax_update_chatbot_instructions']);
    add_action('wp_ajax_aipkit_update_chatbot_model_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_model_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_ai_parameters', [$chatbot_ajax_handler, 'ajax_update_chatbot_ai_parameters']);
    add_action('wp_ajax_aipkit_update_chatbot_conversation_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_conversation_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_style_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_style_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_web_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_web_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_context_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_context_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_token_limits', [$chatbot_ajax_handler, 'ajax_update_chatbot_token_limits']);
    add_action('wp_ajax_aipkit_update_chatbot_image_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_image_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_file_upload_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_file_upload_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_audio_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_audio_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_popup_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_popup_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_deploy_settings', [$chatbot_ajax_handler, 'ajax_update_chatbot_deploy_settings']);
    add_action('wp_ajax_aipkit_update_chatbot_triggers', [$chatbot_ajax_handler, 'ajax_update_chatbot_triggers']);
    add_action('wp_ajax_aipkit_get_chatbot_training_source_count', [$chatbot_ajax_handler, 'ajax_get_chatbot_training_source_count']);
    add_action('wp_ajax_aipkit_get_chatbot_training_status', [$chatbot_ajax_handler, 'ajax_get_chatbot_training_status']);
    add_action('wp_ajax_aipkit_stop_chatbot_training', [$chatbot_ajax_handler, 'ajax_stop_chatbot_training']);
    add_action('wp_ajax_aipkit_get_chatbot_training_sources', [$chatbot_ajax_handler, 'ajax_get_chatbot_training_sources']);
    add_action('wp_ajax_aipkit_get_chatbot_switch_state', [$chatbot_ajax_handler, 'ajax_get_chatbot_switch_state']);
}

/**
 * Logic for registering general AJAX hooks for the Chat module (frontend and admin).
 * Called by WPAICG\Chat\Initializer::register_hooks().
 *
 * @param ConversationAjaxHandler $conversation_ajax_handler
 * @param ChatbotImageAjaxHandler|null $chatbot_image_ajax_handler
 * @return void
 */
function register_hooks_general_ajax_logic(
    ConversationAjaxHandler $conversation_ajax_handler,
    ?ChatbotImageAjaxHandler $chatbot_image_ajax_handler
): void {
    // Ensure the nonce refresh function is available
    $nonce_refresh_path = WPAICG_PLUGIN_DIR . 'classes/chat/frontend/ajax/fn-ajax-get-frontend-chat-nonce.php';
    if (file_exists($nonce_refresh_path) && !function_exists('WPAICG\\Chat\\Frontend\\Ajax\\ajax_get_frontend_chat_nonce_logic')) {
        require_once $nonce_refresh_path;
    }
    // Hooks handled by ConversationAjaxHandler (potentially frontend & admin)
    add_action('wp_ajax_aipkit_get_conversations_list', [$conversation_ajax_handler, 'ajax_get_conversations_list']);
    add_action('wp_ajax_nopriv_aipkit_get_conversations_list', [$conversation_ajax_handler, 'ajax_get_conversations_list']);
    add_action('wp_ajax_aipkit_get_conversation_history', [$conversation_ajax_handler, 'ajax_get_conversation_history']);
    add_action('wp_ajax_nopriv_aipkit_get_conversation_history', [$conversation_ajax_handler, 'ajax_get_conversation_history']);
    add_action('wp_ajax_aipkit_store_feedback', [$conversation_ajax_handler, 'ajax_store_feedback']);
    add_action('wp_ajax_nopriv_aipkit_store_feedback', [$conversation_ajax_handler, 'ajax_store_feedback']);
    add_action('wp_ajax_aipkit_generate_speech', [$conversation_ajax_handler, 'ajax_generate_speech']);
    add_action('wp_ajax_nopriv_aipkit_generate_speech', [$conversation_ajax_handler, 'ajax_generate_speech']);
    add_action('wp_ajax_aipkit_delete_single_conversation', [$conversation_ajax_handler, 'ajax_delete_single_conversation']);
    add_action('wp_ajax_nopriv_aipkit_delete_single_conversation', [$conversation_ajax_handler, 'ajax_delete_single_conversation']);

    // Hooks for image generation within chat
    if ($chatbot_image_ajax_handler) {
         add_action('wp_ajax_aipkit_chat_generate_image', [$chatbot_image_ajax_handler, 'ajax_chat_generate_image']);
         add_action('wp_ajax_nopriv_aipkit_chat_generate_image', [$chatbot_image_ajax_handler, 'ajax_chat_generate_image']);
    }

    // Frontend utility: Get fresh nonce for chat actions (anonymous allowed)
    if (function_exists('WPAICG\\Chat\\Frontend\\Ajax\\ajax_get_frontend_chat_nonce_logic')) {
        add_action('wp_ajax_aipkit_get_frontend_chat_nonce', 'WPAICG\\Chat\\Frontend\\Ajax\\ajax_get_frontend_chat_nonce_logic');
        add_action('wp_ajax_nopriv_aipkit_get_frontend_chat_nonce', 'WPAICG\\Chat\\Frontend\\Ajax\\ajax_get_frontend_chat_nonce_logic');
    }
}

/**
 * Logic for registering SSE-specific AJAX hooks for the Chat module.
 * Called by WPAICG\Chat\Initializer::register_hooks().
 *
 * @param SSEHandler|null $sse_handler
 * @return void
 */
function register_hooks_sse_ajax_logic(?SSEHandler $sse_handler): void {
    if ($sse_handler) {
        add_action('wp_ajax_aipkit_cache_sse_message', [$sse_handler, 'ajax_cache_sse_message']);
        add_action('wp_ajax_nopriv_aipkit_cache_sse_message', [$sse_handler, 'ajax_cache_sse_message']);
        add_action('wp_ajax_aipkit_frontend_chat_stream', [$sse_handler, 'ajax_frontend_chat_stream']);
        add_action('wp_ajax_nopriv_aipkit_frontend_chat_stream', [$sse_handler, 'ajax_frontend_chat_stream']);
    }
}
