<?php

namespace WPAICG\Core\TokenManager\Init;

use WPAICG\Chat\Storage\BotStorage;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\TokenManager\Pricing\AIPKit_Price_Resolver;
use WPAICG\Core\TokenManager\Pricing\AIPKit_Usage_Normalizer;
use WPAICG\Core\TokenManager\Pricing\AIPKit_Charge_Calculator;
use WPAICG\Core\TokenManager\Ledger\AIPKit_Ledger_Repository;
use WPAICG\Core\TokenManager\Ledger\AIPKit_Balance_Service;
use WPAICG\Core\TokenManager\Limits\AIPKit_Quota_Service;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for the AIPKit_Token_Manager constructor.
 * Initializes properties and ensures dependencies are loaded.
 *
 * @param AIPKit_Token_Manager $managerInstance The instance of AIPKit_Token_Manager.
 */
function ConstructorLogic(AIPKit_Token_Manager $managerInstance): void {
    global $wpdb;
    $managerInstance->set_guest_table_name($wpdb->prefix . GuestTableConstants::GUEST_TABLE_NAME_SUFFIX);

    // Initialize BotStorage dependency
    if (!class_exists(BotStorage::class) && !defined('AIPKIT_TESTING_ENV')) {
        $bot_storage_path = WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_chat_bot_storage.php';
        if (file_exists($bot_storage_path)) {
            require_once $bot_storage_path;
        } else {
            $managerInstance->set_bot_storage(null);
            // return; // Early return if critical dependency is missing
        }
    }
    if (class_exists(BotStorage::class)) {
        $managerInstance->set_bot_storage(new BotStorage());
    } else {
        $managerInstance->set_bot_storage(null);
    }


    // Ensure AIPKit_Image_Settings_Ajax_Handler is loaded as it's used by PerformTokenResetLogic
    // This is more of a check; actual loading should be handled by the main plugin dependency loader.
    if (!class_exists(AIPKit_Image_Settings_Ajax_Handler::class) && !defined('AIPKIT_TESTING_ENV')) {
         $image_settings_handler_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-settings-ajax-handler.php';
         if (file_exists($image_settings_handler_path)) {
             require_once $image_settings_handler_path;
         }
    }

    $price_resolver = new AIPKit_Price_Resolver();
    $usage_normalizer = new AIPKit_Usage_Normalizer();
    $charge_calculator = new AIPKit_Charge_Calculator();
    $ledger_repository = new AIPKit_Ledger_Repository();
    $balance_service = new AIPKit_Balance_Service($ledger_repository);
    $quota_service = new AIPKit_Quota_Service();

    $managerInstance->set_price_resolver($price_resolver);
    $managerInstance->set_usage_normalizer($usage_normalizer);
    $managerInstance->set_charge_calculator($charge_calculator);
    $managerInstance->set_ledger_repository($ledger_repository);
    $managerInstance->set_balance_service($balance_service);
    $managerInstance->set_quota_service($quota_service);
}
