<?php


namespace WPAICG\Images\Manager;

use WPAICG\Images\AIPKit_Image_Manager;

if (!defined('ABSPATH')) {
    exit;
}

function init_hooks_logic(AIPKit_Image_Manager $managerInstance): void
{
    add_action('wp_ajax_aipkit_generate_image', [$managerInstance, 'ajax_generate_image']);
    add_action('wp_ajax_nopriv_aipkit_generate_image', [$managerInstance, 'ajax_generate_image']);
    add_action('wp_ajax_aipkit_delete_generated_image', [$managerInstance, 'ajax_delete_generated_image']);
    add_action('wp_ajax_aipkit_load_more_image_history', [$managerInstance, 'ajax_load_more_image_history']);
    add_action('wp_ajax_aipkit_check_video_status', [$managerInstance, 'ajax_check_video_status']);
    add_action('wp_ajax_nopriv_aipkit_check_video_status', [$managerInstance, 'ajax_check_video_status']);

    $settings_ajax_handler = $managerInstance->get_settings_ajax_handler();
    if ($settings_ajax_handler && method_exists($settings_ajax_handler, 'ajax_save_image_settings')) {
        add_action('wp_ajax_aipkit_save_image_settings', [$settings_ajax_handler, 'ajax_save_image_settings']);
    }
}
