<?php


namespace WPAICG\Core\TokenManager\Check;

use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\TokenManager\Constants\MetaKeysConstants;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler;
use WPAICG\Chat\Storage\BotSettingsManager; // For default constants
use WP_Error;
use function WPAICG\Core\TokenManager\Helpers\GetGuestQuotaIdentifiersLogic;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for checking token usage against limits for a given context (chat bot or module).
 * This function is called by the check_and_reset_tokens method in AIPKit_Token_Manager.
 *
 * @param AIPKit_Token_Manager $managerInstance The instance of AIPKit_Token_Manager.
 * @param int|null    $user_id               User ID, or null for guests.
 * @param string|null $session_id            Session ID for guests.
 * @param int|null    $context_id_or_bot_id  Bot ID for 'chat', or IMG_GEN_GUEST_CONTEXT_ID for 'image_generator'. Can be null for other modules.
 * @param string      $module_context        'chat', 'image_generator', or other module slug.
 * @return bool|WP_Error True if allowed, WP_Error if limit exceeded or other error.
 */
function CheckAndResetTokensLogic(
    AIPKit_Token_Manager $managerInstance,
    ?int $user_id,
    ?string $session_id,
    ?int $context_id_or_bot_id,
    string $module_context = 'chat',
    array $usage_context = []
) {
    global $wpdb;

    // If no balance, or a guest, proceed with the original periodic usage tracking logic.

    $fallback_units = isset($usage_context['fallback_units']) && is_numeric($usage_context['fallback_units'])
        ? max(0, (int) $usage_context['fallback_units'])
        : 0;
    $charge_estimate = $managerInstance->estimate_usage_charge($fallback_units, $module_context, $usage_context);
    $resolved_rule = is_array($charge_estimate['resolved_rule'] ?? null) ? $charge_estimate['resolved_rule'] : null;
    $use_pricing_estimate = !empty($resolved_rule);
    $required_units = max(0, (int) ($charge_estimate['required_units'] ?? $fallback_units));
    $balance_service = $managerInstance->get_balance_service();
    $available_balance = 0;

    if ($user_id) {
        $available_balance = ($balance_service && method_exists($balance_service, 'get_current_balance'))
            ? (int) $balance_service->get_current_balance($user_id)
            : max(0, (int) get_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, true));

        if (!$use_pricing_estimate && $available_balance > 0) {
            return true;
        }
    }

    // Validation
    if ($module_context === 'chat' && empty($context_id_or_bot_id)) {
        return new WP_Error('token_check_no_bot_id_logic', __('Bot ID missing for chat token check.', 'gpt3-ai-content-generator'));
    }
    if ($module_context === 'image_generator' && $context_id_or_bot_id === null) { // image_generator uses 0 for guests
        return new WP_Error('token_check_no_img_context_logic', __('Image Generator context ID missing for token check.', 'gpt3-ai-content-generator'));
    }
    if ($module_context === 'ai_forms' && $context_id_or_bot_id === null && !$user_id) { // ai_forms uses 1 for guests, null for users
        // This is a valid state for logged-in users, so only error if guest AND null
        return new WP_Error('token_check_no_aiforms_context_logic', __('AI Forms context ID missing for guest token check.', 'gpt3-ai-content-generator'));
    }

    $is_guest = !$user_id;
    $guest_identifiers = $is_guest ? GetGuestQuotaIdentifiersLogic($session_id) : [];
    if ($is_guest && empty($guest_identifiers)) {
        return new WP_Error('token_check_no_identifier_logic', __('User/Session ID missing for token check.', 'gpt3-ai-content-generator'));
    }

    $settings = [];
    $usage_key = '';
    $reset_key = '';
    $guest_context_table_id = is_numeric($context_id_or_bot_id) ? $context_id_or_bot_id : null;
    $guest_usage_rows = [];

    // Fetch settings based on module context
    if ($module_context === 'chat') {
        $bot_storage = $managerInstance->get_bot_storage();
        if (!$bot_storage) {
            return new WP_Error('init_error_chat_storage_logic', __('Token manager (bot storage) not initialized.', 'gpt3-ai-content-generator'));
        }
        if ($guest_context_table_id === null) { // Ensure bot_id is numeric for chat
            return new WP_Error('internal_error_chat_context_id_logic', __('Chat context requires a valid Bot ID for token check.', 'gpt3-ai-content-generator'));
        }
        $settings = $bot_storage->get_chatbot_settings($guest_context_table_id);
        $usage_key = MetaKeysConstants::CHAT_USAGE_META_KEY_PREFIX . $guest_context_table_id;
        $reset_key = MetaKeysConstants::CHAT_RESET_META_KEY_PREFIX . $guest_context_table_id;
    } elseif ($module_context === 'image_generator') {
        if (!class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
            return new WP_Error('init_error_img_settings_logic', __('Token manager (image settings) not initialized.', 'gpt3-ai-content-generator'));
        }
        $img_settings_all = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        $settings = $img_settings_all['token_management'] ?? [];
        $usage_key = MetaKeysConstants::IMG_USAGE_META_KEY;
        $reset_key = MetaKeysConstants::IMG_RESET_META_KEY;
        // guest_context_table_id for image generator is a constant (e.g., 0)
        $guest_context_table_id = GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
    } elseif ($module_context === 'ai_forms') {
        if (!class_exists(\WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler::class)) {
            return new WP_Error('init_error_aiforms_settings_logic', __('Token manager (AI Forms settings) not initialized.', 'gpt3-ai-content-generator'));
        }
        $aiforms_settings_all = \WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler::get_settings();
        $settings = $aiforms_settings_all['token_management'] ?? [];
        $usage_key = MetaKeysConstants::AIFORMS_USAGE_META_KEY;
        $reset_key = MetaKeysConstants::AIFORMS_RESET_META_KEY;
        $guest_context_table_id = GuestTableConstants::AI_FORMS_GUEST_CONTEXT_ID;
    } else {
        if ($context_id_or_bot_id === null) {
            return true;
        } // No specific limits for this generic module context if no ID
        return new WP_Error('invalid_module_context_for_tokens_logic', __('Invalid module context or ID for token check.', 'gpt3-ai-content-generator'));
    }

    if ($guest_context_table_id === null && $is_guest) {
        return true; // Or error, based on policy. For now, allow if this unlikely scenario happens.
    }

    // Determine limit and reset period
    $limit = null;
    $reset_period = $settings['token_reset_period'] ?? 'never';
    $default_limit_message = class_exists(BotSettingsManager::class)
        ? BotSettingsManager::get_default_token_limit_message()
        : __('You have reached your quota for this period.', 'gpt3-ai-content-generator');
    $limit_message_template = $settings['token_limit_message'] ?? '';
    $limit_message = !empty($limit_message_template) ? $limit_message_template : $default_limit_message;

    $current_usage = 0;
    $last_reset_time = 0;
    $guest_table_name = $wpdb->prefix . GuestTableConstants::GUEST_TABLE_NAME_SUFFIX;

    if ($is_guest) {
        $limit = $settings['token_guest_limit'] ?? null;
        if ($limit === 0 || (is_string($limit) && $limit === '0')) { // Check for string '0' too
            return new WP_Error('token_limit_exceeded_guest_logic', __('Access disabled for guests.', 'gpt3-ai-content-generator'));
        }
        if ($limit === null || $limit === '') {
            return true;
        } // Unlimited for this context

        if ($guest_context_table_id !== null) {
            foreach ($guest_identifiers as $guest_identifier) {
                $cache_key = "aipkit_guest_usage_{$guest_identifier}_{$guest_context_table_id}";
                $guest_row = wp_cache_get($cache_key, 'aipkit_token_usage');
                if (false === $guest_row) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query; unavoidable.
                    $guest_row = $wpdb->get_row($wpdb->prepare("SELECT tokens_used, last_reset_timestamp FROM {$guest_table_name} WHERE session_id = %s AND bot_id = %d",
                        $guest_identifier,
                        $guest_context_table_id
                    ), ARRAY_A);
                    wp_cache_set($cache_key, $guest_row, 'aipkit_token_usage', 300); // Cache for 5 minutes
                }

                $guest_usage_rows[$guest_identifier] = [
                    'current_usage' => $guest_row ? (int) $guest_row['tokens_used'] : 0,
                    'last_reset_time' => $guest_row ? (int) $guest_row['last_reset_timestamp'] : 0,
                ];

                $current_usage = max($current_usage, $guest_usage_rows[$guest_identifier]['current_usage']);
                $last_reset_time = max($last_reset_time, $guest_usage_rows[$guest_identifier]['last_reset_time']);
            }
        } else {
            return true; // Should have been caught earlier
        }
    } else { // Logged-in User
        $limit_mode = $settings['token_limit_mode'] ?? 'general';
        if ($limit_mode === 'general') {
            $limit = $settings['token_user_limit'] ?? null;
        } else { // Role-based
            $user_data = get_userdata($user_id);
            $user_roles = $user_data ? (array) $user_data->roles : [];
            $role_limits_raw = $settings['token_role_limits'] ?? [];
            $role_limits = is_string($role_limits_raw) ? json_decode($role_limits_raw, true) : (is_array($role_limits_raw) ? $role_limits_raw : []);
            if (!is_array($role_limits)) {
                $role_limits = [];
            }

            if (empty($user_roles) || empty($role_limits)) {
                $limit = null;
            } else {
                $highest_limit = -1;
                foreach ($user_roles as $role) {
                    if (isset($role_limits[$role])) {
                        $role_limit_value_raw = $role_limits[$role];
                        if ($role_limit_value_raw === null || $role_limit_value_raw === '') {
                            $highest_limit = null; // Explicitly unlimited for this role, overrides others
                            break;
                        }
                        if ($role_limit_value_raw === '0' || $role_limit_value_raw === 0) {
                            $highest_limit = max($highest_limit, 0); // Found a "disabled" (0) limit
                        } elseif (ctype_digit((string)$role_limit_value_raw)) {
                            $highest_limit = max($highest_limit, (int)$role_limit_value_raw);
                        }
                    }
                }
                $limit = ($highest_limit === -1) ? null : $highest_limit;
            }
        }
        if ($limit === 0 || (is_string($limit) && $limit === '0')) {
            return new WP_Error('token_limit_exceeded_user_logic', __('Access disabled for your account/role.', 'gpt3-ai-content-generator'));
        }
        if ($limit === null || $limit === '') {
            return true;
        } // Unlimited

        $current_usage = (int) get_user_meta($user_id, $usage_key, true);
        $last_reset_time = (int) get_user_meta($user_id, $reset_key, true);
    }

    $guest_reset_due = false;
    if ($is_guest && !empty($guest_usage_rows) && $reset_period !== 'never') {
        foreach ($guest_usage_rows as $guest_usage_row) {
            if (\WPAICG\Core\TokenManager\Reset\IsResetDueLogic((int) $guest_usage_row['last_reset_time'], $reset_period)) {
                $guest_reset_due = true;
                break;
            }
        }
    }

    // Fail-safe reset if due (not relying on cron exclusively for active users)
    if ($reset_period !== 'never' && ($limit !== null && $limit !== '')) { // Only reset if there's a limit
        if (($is_guest && $guest_reset_due) || (!$is_guest && \WPAICG\Core\TokenManager\Reset\IsResetDueLogic($last_reset_time, $reset_period))) {
            $log_context_str = $is_guest ? "Guest {$session_id}" : "User {$user_id}";
            $log_module_str = ($module_context === 'chat') ? "Bot {$context_id_or_bot_id}" : "Module {$module_context}";

            $current_usage = 0;
            $last_reset_time_new = time(); // Use a different var name for new reset time
            if ($is_guest && $guest_context_table_id !== null) {
                foreach ($guest_usage_rows as $guest_identifier => $guest_usage_row) {
                    if (!\WPAICG\Core\TokenManager\Reset\IsResetDueLogic((int) $guest_usage_row['last_reset_time'], $reset_period)) {
                        continue;
                    }

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Caching is not applicable for a write operation (REPLACE). Cache is invalidated after.
                    $wpdb->replace($guest_table_name, ['session_id' => $guest_identifier, 'bot_id' => $guest_context_table_id, 'tokens_used' => 0, 'last_reset_timestamp' => $last_reset_time_new, 'last_updated_at' => current_time('mysql', 1)], ['%s', '%d', '%d', '%d', '%s']);
                    $guest_usage_rows[$guest_identifier]['current_usage'] = 0;
                    $guest_usage_rows[$guest_identifier]['last_reset_time'] = $last_reset_time_new;

                    // Invalidate cache after write
                    $cache_key = "aipkit_guest_usage_{$guest_identifier}_{$guest_context_table_id}";
                    wp_cache_delete($cache_key, 'aipkit_token_usage');
                }
            } elseif (!$is_guest) {
                update_user_meta($user_id, $usage_key, 0);
                update_user_meta($user_id, $reset_key, $last_reset_time_new);
            }
        }
    }

    if ($is_guest && !empty($guest_usage_rows)) {
        $current_usage = 0;
        $last_reset_time = 0;
        foreach ($guest_usage_rows as $guest_usage_row) {
            $current_usage = max($current_usage, (int) $guest_usage_row['current_usage']);
            $last_reset_time = max($last_reset_time, (int) $guest_usage_row['last_reset_time']);
        }
    }

    $required_quota_units = 0;
    if ($use_pricing_estimate) {
        $required_quota_units = $required_units;
        if (!$is_guest && $available_balance > 0) {
            $required_quota_units = max(0, $required_units - $available_balance);
        }

        if (!$is_guest && $required_quota_units <= 0) {
            return true;
        }
    }

    // Final Limit Check
    if ($use_pricing_estimate && ($limit !== null && $limit !== '')) {
        $quota_service = $managerInstance->get_quota_service();
        $can_consume = $quota_service && method_exists($quota_service, 'can_consume')
            ? $quota_service->can_consume((int) $limit, $current_usage, $required_quota_units)
            : (($current_usage + $required_quota_units) <= (int) $limit);

        if (!$can_consume) {
            return build_token_limit_exceeded_error_logic($settings, $limit_message, $module_context, $guest_context_table_id);
        }
    } elseif (($limit !== null && $limit !== '') && $current_usage >= (int)$limit) {
        return build_token_limit_exceeded_error_logic($settings, $limit_message, $module_context, $guest_context_table_id);
    }

    return true; // Allowed
}

/**
 * Builds the structured quota error payload returned to frontend clients.
 *
 * @param array<string, mixed> $settings
 */
function build_token_limit_exceeded_error_logic(
    array $settings,
    string $limit_message,
    string $module_context,
    ?int $context_id_or_bot_id
): WP_Error {
    $error_data = ['status' => 429];

    $quota_notice = build_quota_notice_logic($settings, $module_context, $context_id_or_bot_id, $limit_message);
    if (!empty($quota_notice)) {
        $error_data['quota_notice'] = $quota_notice;
    }

    return new WP_Error('token_limit_exceeded_final_logic', $limit_message, $error_data);
}

/**
 * Creates the quota recovery card payload for quota errors.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function build_quota_notice_logic(array $settings, string $module_context, ?int $context_id, string $limit_message): array
{
    if (!in_array($module_context, ['chat', 'image_generator', 'ai_forms'], true)) {
        return [];
    }

    if ($module_context === 'chat' && ($context_id === null || $context_id <= 0)) {
        return [];
    }

    $actions = [];
    $action_slots = [
        ['slot' => 'primary', 'variant' => 'primary'],
        ['slot' => 'secondary', 'variant' => 'secondary'],
    ];

    foreach ($action_slots as $action_slot) {
        $slot = $action_slot['slot'];
        $type = sanitize_key((string) ($settings["token_limit_{$slot}_action_type"] ?? 'none'));
        if ($type === '' || $type === 'none') {
            continue;
        }

        $label = trim((string) ($settings["token_limit_{$slot}_action_label"] ?? ''));
        if ($label === '' && class_exists(BotSettingsManager::class)) {
            $label = BotSettingsManager::get_token_limit_action_default_label($type);
        }

        $custom_url = trim((string) ($settings["token_limit_{$slot}_action_url"] ?? ''));
        $url = resolve_quota_notice_action_url_logic($type, $module_context, $context_id, $custom_url);
        if ($label === '' || $url === '') {
            continue;
        }

        $actions[] = [
            'label' => $label,
            'url' => $url,
            'variant' => $action_slot['variant'],
        ];
    }

    return [
        'type' => 'quota_notice',
        'message' => $limit_message,
        'actions' => $actions,
    ];
}

function resolve_quota_notice_action_url_logic(
    string $action_type,
    string $module_context,
    ?int $context_id,
    string $custom_url = ''
): string
{
    $dashboard_url = trim((string) get_option('aipkit_token_dashboard_page_url', ''));
    $buy_credits_url = trim((string) get_option('aipkit_token_shop_page_url', ''));

    if ($buy_credits_url === '' && function_exists('wc_get_page_id')) {
        $shop_page_id = wc_get_page_id('shop');
        if ($shop_page_id && $shop_page_id > 0) {
            $buy_credits_url = (string) get_permalink($shop_page_id);
        }
    }

    switch ($action_type) {
        case 'dashboard_usage':
            if ($dashboard_url === '') {
                return '';
            }
            $dashboard_context_id = resolve_quota_notice_dashboard_context_id_logic($module_context, $context_id);
            $query_args = [
                'aipkit_section' => 'usage',
                'aipkit_module' => $module_context,
            ];
            if ($dashboard_context_id !== null) {
                $query_args['aipkit_context_id'] = $dashboard_context_id;
            }
            return build_dashboard_quota_notice_url_logic(
                $dashboard_url,
                $query_args,
                'aipkit_customer_dashboard_usage'
            );

        case 'dashboard_credits':
            if ($dashboard_url === '') {
                return '';
            }
            return build_dashboard_quota_notice_url_logic(
                $dashboard_url,
                ['aipkit_section' => 'credits'],
                'aipkit_customer_dashboard_credits'
            );

        case 'dashboard_purchases':
            if ($dashboard_url === '') {
                return '';
            }
            return build_dashboard_quota_notice_url_logic(
                $dashboard_url,
                ['aipkit_section' => 'purchases'],
                'aipkit_purchase_history_details'
            );

        case 'buy_credits':
            return $buy_credits_url !== '' ? esc_url_raw($buy_credits_url) : '';

        case 'custom_url':
            return $custom_url !== '' ? esc_url_raw($custom_url) : '';

        case 'none':
        default:
            return '';
    }
}

function resolve_quota_notice_dashboard_context_id_logic(string $module_context, ?int $context_id): ?int
{
    if ($module_context === 'chat') {
        return ($context_id !== null && $context_id > 0) ? $context_id : null;
    }

    if ($module_context === 'image_generator') {
        return GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
    }

    if ($module_context === 'ai_forms') {
        return GuestTableConstants::AI_FORMS_GUEST_CONTEXT_ID;
    }

    return $context_id;
}

/**
 * @param array<string, string|int> $query_args
 */
function build_dashboard_quota_notice_url_logic(string $base_url, array $query_args, string $fragment = ''): string
{
    if ($base_url === '') {
        return '';
    }

    $url = add_query_arg($query_args, $base_url);
    if ($fragment !== '') {
        $url .= '#' . ltrim($fragment, '#');
    }

    return esc_url_raw($url);
}
