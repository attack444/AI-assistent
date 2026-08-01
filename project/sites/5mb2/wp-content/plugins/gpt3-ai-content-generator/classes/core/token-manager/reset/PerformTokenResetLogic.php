<?php


namespace WPAICG\Core\TokenManager\Reset;

use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\TokenManager\Constants\MetaKeysConstants;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler; // For Image Generator settings

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for performing the token reset for chatbots AND the image generator module.
 * This function is called by the perform_token_reset method in AIPKit_Token_Manager.
 *
 * @param AIPKit_Token_Manager $managerInstance The instance of AIPKit_Token_Manager.
 */
function PerformTokenResetLogic(AIPKit_Token_Manager $managerInstance): void
{
    global $wpdb;
    $current_time = time();
    $current_day_of_week = wp_date('w', $current_time); // 0 (for Sunday) through 6 (for Saturday)
    $current_day_of_month = wp_date('j', $current_time);

    // --- 1. Chatbot Token Reset ---
    $bot_storage = $managerInstance->get_bot_storage();
    if ($bot_storage) {
        $all_chatbots = $bot_storage->get_chatbots(); // Assumes get_chatbots() returns array of WP_Post
        $users_reset_chat = 0;
        $guests_reset_chat = 0;

        if (!empty($all_chatbots)) {
            foreach ($all_chatbots as $bot_post) {
                $bot_id = $bot_post->ID;
                $settings = $bot_storage->get_chatbot_settings($bot_id);
                $reset_period = $settings['token_reset_period'] ?? 'never';

                if ($reset_period === 'never') {
                    continue;
                }

                $reset_needed_for_cron = false;
                if ($reset_period === 'daily') {
                    $reset_needed_for_cron = true;
                } elseif ($reset_period === 'weekly' && $current_day_of_week == get_option('start_of_week', 1)) {
                    $reset_needed_for_cron = true;
                } elseif ($reset_period === 'monthly' && $current_day_of_month == 1) {
                    $reset_needed_for_cron = true;
                }


                if ($reset_needed_for_cron) {
                    $meta_key_usage = MetaKeysConstants::CHAT_USAGE_META_KEY_PREFIX . $bot_id;
                    $meta_key_reset = MetaKeysConstants::CHAT_RESET_META_KEY_PREFIX . $bot_id;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reason: Bulk deletion for a cron job. More efficient than individual API calls. Caching is not applicable here.
                    $deleted_user_usage_meta = $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key_usage], ['%s']);
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reason: Bulk deletion for a cron job. More efficient than individual API calls. Caching is not applicable here.
                    $deleted_user_reset_meta = $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key_reset], ['%s']);

                    if ($deleted_user_usage_meta !== false) {
                        $users_reset_chat += $deleted_user_usage_meta;
                    } // Count affected rows (approx users)

                    $guest_table_name = $managerInstance->get_guest_table_name();
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Bulk deletion on a custom table for a cron job. Caching is not applicable.
                    $deleted_guests = $wpdb->delete($guest_table_name, ['bot_id' => $bot_id], ['%d']);
                    if ($deleted_guests !== false) {
                        $guests_reset_chat += $deleted_guests;
                    }
                }
            }
        }
    }

    // --- 2. Image Generator Token Reset ---
    if (class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
        $img_settings_all = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        $img_token_settings = $img_settings_all['token_management'] ?? [];
        $img_reset_period = $img_token_settings['token_reset_period'] ?? 'never';
        $users_reset_img = 0;
        $guests_reset_img = 0;

        if ($img_reset_period !== 'never') {
            $img_reset_needed_for_cron = false;
            if ($img_reset_period === 'daily') {
                $img_reset_needed_for_cron = true;
            } elseif ($img_reset_period === 'weekly' && $current_day_of_week == get_option('start_of_week', 1)) {
                $img_reset_needed_for_cron = true;
            } elseif ($img_reset_period === 'monthly' && $current_day_of_month == 1) {
                $img_reset_needed_for_cron = true;
            }

            if ($img_reset_needed_for_cron) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reason: Bulk deletion for a cron job. More efficient than individual API calls. Caching is not applicable here.
                $deleted_user_img_usage_meta = $wpdb->delete($wpdb->usermeta, ['meta_key' => MetaKeysConstants::IMG_USAGE_META_KEY], ['%s']);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reason: Bulk deletion for a cron job. More efficient than individual API calls. Caching is not applicable here.
                $deleted_user_img_reset_meta = $wpdb->delete($wpdb->usermeta, ['meta_key' => MetaKeysConstants::IMG_RESET_META_KEY], ['%s']);
                if ($deleted_user_img_usage_meta !== false) {
                    $users_reset_img += $deleted_user_img_usage_meta;
                }

                $guest_table_name = $managerInstance->get_guest_table_name();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Bulk deletion on a custom table for a cron job. Caching is not applicable.
                $deleted_img_guests = $wpdb->delete($guest_table_name, ['bot_id' => GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID], ['%d']);
                if ($deleted_img_guests !== false) {
                    $guests_reset_img += $deleted_img_guests;
                }
            }
        }
    }
}