<?php


namespace WPAICG\Images\Manager;

use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Images\AIPKit_Image_Storage_Helper;
use WPAICG\Images\AIPKit_Image_Manager;

if (!defined('ABSPATH')) {
    exit;
}

function constructor_logic(AIPKit_Image_Manager $managerInstance): void
{
    if (!class_exists(LogStorage::class)) {
        $log_storage_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_chat_log_storage.php';
        if (file_exists($log_storage_path)) {
            require_once $log_storage_path;
        }
    }
    if (class_exists(LogStorage::class)) {
        $managerInstance->set_log_storage(new LogStorage());
    } else {
        $managerInstance->set_log_storage(null);
    }

    if (!class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
        $settings_handler_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-settings-ajax-handler.php';
        if (file_exists($settings_handler_path)) {
            require_once $settings_handler_path;
        } else {
            return;
        }
    }
    if (class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
        $managerInstance->set_settings_ajax_handler(new AIPKit_Image_Settings_Ajax_Handler());
    } else {
        $managerInstance->set_settings_ajax_handler(null);
    }
    if (!class_exists(AIPKit_Image_Storage_Helper::class)) {
        $storage_helper_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-storage-helper.php';
        if (file_exists($storage_helper_path)) {
            require_once $storage_helper_path;
        }
    }

    if (!class_exists(\WPAICG\Core\TokenManager\AIPKit_Token_Manager::class)) {
        $token_manager_path = WPAICG_PLUGIN_DIR . 'classes/core/token-manager/AIPKit_Token_Manager.php';
        if (file_exists($token_manager_path)) {
            require_once $token_manager_path;
        }
    }
    if (class_exists(\WPAICG\Core\TokenManager\AIPKit_Token_Manager::class)) {
        $managerInstance->set_token_manager(new \WPAICG\Core\TokenManager\AIPKit_Token_Manager());
    } else {
        $managerInstance->set_token_manager(null);
    }
}
