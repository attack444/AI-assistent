<?php


namespace WPAICG\Includes\HookRegistrars;

use WPAICG\Admin\Assets\DashboardAssets;
use WPAICG\Admin\Assets\SettingsAssets;
use WPAICG\Admin\Assets\ChatAdminAssets;
use WPAICG\Admin\Assets\RoleManagerAssets;
use WPAICG\Admin\Assets\PostEnhancerAssets;
use WPAICG\Admin\Assets\ImageGeneratorAssets;
use WPAICG\Admin\Assets\AIPKit_Vector_Post_Processor_Assets;
use WPAICG\Vector\PostProcessor\AIPKit_Vector_Post_Processor_List_Screen;
use WPAICG\Admin\Assets\AIPKit_Autogpt_Assets;
use WPAICG\Admin\Assets\AIPKit_Content_Writer_Assets;
use WPAICG\Admin\Assets\AIPKit_AI_Forms_Assets;
use WPAICG\Admin\Assets\AIPKit_Woocommerce_Writer_Assets;



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Registers hooks for admin asset handlers.
 */
class Admin_Asset_Hooks_Registrar
{
    public static function register()
    {
        $dashboard_assets    = new DashboardAssets();
        $settings_assets     = new SettingsAssets();
        $chat_admin_assets   = new ChatAdminAssets();
        $role_manager_assets = new RoleManagerAssets();
        $post_enhancer_assets = new PostEnhancerAssets();
        $image_generator_assets = new ImageGeneratorAssets();
        $content_writer_assets = new AIPKit_Content_Writer_Assets();
        $vector_post_processor_assets = new AIPKit_Vector_Post_Processor_Assets();
        $vector_post_processor_list_screen = new AIPKit_Vector_Post_Processor_List_Screen();
        $autogpt_assets = class_exists(AIPKit_Autogpt_Assets::class) ? new AIPKit_Autogpt_Assets() : null;
        $ai_forms_assets = class_exists(AIPKit_AI_Forms_Assets::class) ? new AIPKit_AI_Forms_Assets() : null;
        $woocommerce_writer_assets = class_exists(AIPKit_Woocommerce_Writer_Assets::class)
            ? new AIPKit_Woocommerce_Writer_Assets()
            : null;


        $dashboard_assets->register_hooks();
        $settings_assets->register_hooks();
        $chat_admin_assets->register_hooks();
        $role_manager_assets->register_hooks();
        $post_enhancer_assets->register_hooks();
        $image_generator_assets->register_hooks();
        $content_writer_assets->register_hooks();
        $vector_post_processor_assets->register_hooks();
        $vector_post_processor_list_screen->register_hooks();
        if ($autogpt_assets) {
            $autogpt_assets->register_hooks();
        }
        if ($ai_forms_assets) {
            $ai_forms_assets->register_hooks();
        }
        if ($woocommerce_writer_assets) {
            $woocommerce_writer_assets->register_hooks();
        }
    }
}
