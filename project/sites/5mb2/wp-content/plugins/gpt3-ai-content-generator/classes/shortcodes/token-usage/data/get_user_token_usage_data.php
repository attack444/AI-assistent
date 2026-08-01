<?php


namespace WPAICG\Shortcodes\TokenUsage\Data;

use WPAICG\Chat\Storage\BotStorage;
use WPAICG\Core\TokenManager\Constants\MetaKeysConstants;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler;
use WPAICG\Chat\Admin\AdminSetup;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic to fetch and structure token usage data for the current user.
 *
 * @param \WPAICG\Shortcodes\AIPKit_Token_Usage_Shortcode $facade The facade instance.
 * @param int $user_id The ID of the current user.
 * @return array Structured usage data.
 */
function get_user_token_usage_data_logic(\WPAICG\Shortcodes\AIPKit_Token_Usage_Shortcode $facade, $user_id): array
{
    global $wpdb;
    $data = [
        'token_balance' => (int) get_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, true),
        'chat' => [],
        'image_generator' => [],
        'ai_forms' => [],
    ];

    // Ensure dependencies exist
    if (!class_exists('\\WPAICG\\Chat\\Storage\\BotStorage')) {
        return $data;
    }

    $usage_meta_prefix = MetaKeysConstants::CHAT_USAGE_META_KEY_PREFIX;

    // --- Caching for meta_keys query ---
    $cache_key = 'aipkit_token_usage_meta_keys_' . $user_id;
    $cache_group = 'aipkit_token_usage';
    $meta_keys = wp_cache_get($cache_key, $cache_group);

    if (false === $meta_keys) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary for fetching meta keys with a LIKE condition, which standard WP functions do not support. Caching is implemented.
        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
            $user_id,
            $wpdb->esc_like($usage_meta_prefix) . '%'
        ));
        wp_cache_set($cache_key, $meta_keys, $cache_group, MINUTE_IN_SECONDS); // Cache for 1 minute
    }
    // --- End Caching ---


    if (!empty($meta_keys)) {
        $bot_storage = new BotStorage();

        foreach ($meta_keys as $meta_key) {
            $bot_id = (int) str_replace($usage_meta_prefix, '', $meta_key);
            if ($bot_id > 0) {
                $bot_post = get_post($bot_id);
                if ($bot_post && $bot_post->post_type === AdminSetup::POST_TYPE) {
                    $used_tokens = (int) get_user_meta($user_id, $meta_key, true);
                    $settings = $bot_storage->get_chatbot_settings($bot_id);
                    $limit = $facade->get_user_limit_for_module($user_id, $settings);

                    if ($limit !== 0) {
                        $data['chat'][] = [
                           'module' => 'chat', // NEW
                           'context_id' => $bot_id, // NEW
                           /* translators: %d: Bot ID */
                           'title' => $bot_post->post_title ?: sprintf(__('Bot #%d', 'gpt3-ai-content-generator'), $bot_id),
                           'used' => $used_tokens,
                           'limit' => $limit,
                        ];
                    }
                }
            }
        }
    }

    if (class_exists('\\WPAICG\\Images\\AIPKit_Image_Settings_Ajax_Handler')) {
        $img_settings_all = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        $img_token_settings = $img_settings_all['token_management'] ?? [];
        $limit = $facade->get_user_limit_for_module($user_id, $img_token_settings);

        if ($limit !== 0) {
            $used_tokens = (int) get_user_meta($user_id, MetaKeysConstants::IMG_USAGE_META_KEY, true);
            if ($used_tokens > 0 || $limit !== null) {
                $data['image_generator'][] = [
                   'module' => 'image_generator', // NEW
                   'context_id' => 0, // NEW (0 for generic module context)
                   'title' => __('Image Generator', 'gpt3-ai-content-generator'),
                   'used' => $used_tokens,
                   'limit' => $limit,
                ];
            }
        }
    }

    if (class_exists('\\WPAICG\\AIForms\\Admin\\AIPKit_AI_Form_Settings_Ajax_Handler')) {
        $aiform_settings_all = AIPKit_AI_Form_Settings_Ajax_Handler::get_settings();
        $aiform_token_settings = $aiform_settings_all['token_management'] ?? [];
        $limit = $facade->get_user_limit_for_module($user_id, $aiform_token_settings);

        if ($limit !== 0) {
            $used_tokens = (int) get_user_meta($user_id, MetaKeysConstants::AIFORMS_USAGE_META_KEY, true);
            if ($used_tokens > 0 || $limit !== null) {
                $data['ai_forms'][] = [
                   'module' => 'ai_forms', // NEW
                   'context_id' => 1, // NEW (1 for generic module context)
                   'title' => __('AI Forms', 'gpt3-ai-content-generator'),
                   'used' => $used_tokens,
                   'limit' => $limit,
                ];
            }
        }
    }

    return $data;
}