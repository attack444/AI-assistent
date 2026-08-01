<?php


namespace WPAICG\Images\Manager;

use WPAICG\Images\AIPKit_Image_Manager;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

function get_image_settings_logic(AIPKit_Image_Manager $managerInstance): array
{
    $image_settings_cache = $managerInstance->get_image_settings_cache();
    if ($image_settings_cache === null) {
        if (class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
            $settings_cache = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        } else {
            $settings_cache = AIPKit_Image_Settings_Ajax_Handler::get_default_settings();
        }
        $managerInstance->set_image_settings_cache($settings_cache);
        return $settings_cache;
    }
    return $image_settings_cache;
}
