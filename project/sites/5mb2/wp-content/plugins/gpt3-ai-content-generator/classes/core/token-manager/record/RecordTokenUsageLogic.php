<?php


namespace WPAICG\Core\TokenManager\Record;

use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\TokenManager\Constants\MetaKeysConstants;
use WPAICG\Core\TokenManager\Constants\GuestTableConstants;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler;
use function WPAICG\Core\TokenManager\Helpers\GetGuestQuotaIdentifiersLogic;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for recording token usage for a given context (chat bot or module).
 * This function is called by the record_token_usage method in AIPKit_Token_Manager.
 *
 * @param AIPKit_Token_Manager $managerInstance The instance of AIPKit_Token_Manager.
 * @param int|null    $user_id               User ID, or null for guests.
 * @param string|null $session_id            Session ID for guests.
 * @param int|null    $context_id_or_bot_id  Bot ID for 'chat', IMG_GEN_GUEST_CONTEXT_ID for 'image_generator' guest table. Can be null for others.
 * @param int         $tokens_used           Number of tokens to record.
 * @param string      $module_context        'chat', 'image_generator', or other module slug.
 */
function RecordTokenUsageLogic(
    AIPKit_Token_Manager $managerInstance,
    ?int $user_id,
    ?string $session_id,
    ?int $context_id_or_bot_id,
    int $tokens_used,
    string $module_context = 'chat',
    array $usage_context = []
): void {
    global $wpdb;

    if ($tokens_used <= 0) {
        return;
    }

    $charge_estimate = $managerInstance->estimate_usage_charge($tokens_used, $module_context, $usage_context);
    $resolved_rule = is_array($charge_estimate['resolved_rule'] ?? null) ? $charge_estimate['resolved_rule'] : null;
    $billed_units = max(0, (int) ($charge_estimate['billed_credits'] ?? $tokens_used));
    $normalized_usage = is_array($charge_estimate['normalized_usage'] ?? null) ? $charge_estimate['normalized_usage'] : [];
    $provider = sanitize_text_field((string) ($usage_context['provider'] ?? ''));
    $model = sanitize_text_field((string) ($usage_context['model'] ?? ''));
    $operation = sanitize_text_field((string) ($usage_context['operation'] ?? ''));
    $pricing_module = sanitize_key((string) ($charge_estimate['pricing_module'] ?? ($usage_context['pricing_module'] ?? $module_context)));
    if ($operation === '') {
        if ($module_context === 'chat') {
            $operation = 'chat';
        } elseif ($module_context === 'ai_forms') {
            $operation = 'form_submit';
        } elseif ($module_context === 'image_generator') {
            $operation = 'generate';
        } else {
            $operation = 'usage';
        }
    }

    $tokens_left_to_deduct = $billed_units;
    $deducted_from_balance = 0;
    $balance_before = 0;
    $balance_after = 0;
    $ledger_repository = $managerInstance->get_ledger_repository();
    $balance_service = $managerInstance->get_balance_service();

    if ($user_id) {
        if ($balance_service && method_exists($balance_service, 'deduct_available_balance')) {
            $balance_result = $balance_service->deduct_available_balance($user_id, $tokens_left_to_deduct);
            $balance_before = (int) ($balance_result['balance_before'] ?? 0);
            $deducted_from_balance = (int) ($balance_result['deducted'] ?? 0);
            $balance_after = (int) ($balance_result['balance_after'] ?? 0);
            $tokens_left_to_deduct = (int) ($balance_result['remaining'] ?? $tokens_left_to_deduct);
        } else {
            $token_balance_raw = get_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, true);
            if (is_numeric($token_balance_raw) && (int)$token_balance_raw > 0) {
                $balance_before = (int) $token_balance_raw;
                $deducted_from_balance = min($balance_before, $tokens_left_to_deduct);
                $balance_after = $balance_before - $deducted_from_balance;
                update_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, $balance_after);
                $tokens_left_to_deduct -= $deducted_from_balance;
            } else {
                $balance_before = is_numeric($token_balance_raw) ? (int) $token_balance_raw : 0;
                $balance_after = $balance_before;
            }
        }
    }

    // Validation
    if ($module_context === 'chat' && empty($context_id_or_bot_id)) {
        return;
    }
    if ($module_context === 'image_generator' && $context_id_or_bot_id === null) {
        return;
    }
    if ($module_context === 'ai_forms' && $context_id_or_bot_id === null && !$user_id) {
        return;
    }

    $is_guest = !$user_id;
    $guest_identifiers = $is_guest ? GetGuestQuotaIdentifiersLogic($session_id) : [];
    if ($is_guest && empty($guest_identifiers)) {
        return;
    }

    $settings = [];
    $usage_key = '';
    $reset_key = '';
    $guest_context_table_id = is_numeric($context_id_or_bot_id) ? $context_id_or_bot_id : null;

    // Fetch settings based on module context
    if ($module_context === 'chat') {
        $bot_storage = $managerInstance->get_bot_storage();
        if (!$bot_storage) {
            return;
        }
        if ($guest_context_table_id === null) {
            return;
        }
        $settings = $bot_storage->get_chatbot_settings($guest_context_table_id);
        $usage_key = MetaKeysConstants::CHAT_USAGE_META_KEY_PREFIX . $guest_context_table_id;
        $reset_key = MetaKeysConstants::CHAT_RESET_META_KEY_PREFIX . $guest_context_table_id;
    } elseif ($module_context === 'image_generator') {
        if (!class_exists(AIPKit_Image_Settings_Ajax_Handler::class)) {
            return;
        }
        $img_settings_all = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        $settings = $img_settings_all['token_management'] ?? [];
        $usage_key = MetaKeysConstants::IMG_USAGE_META_KEY;
        $reset_key = MetaKeysConstants::IMG_RESET_META_KEY;
        $guest_context_table_id = GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
    } elseif ($module_context === 'ai_forms') {
        if (!class_exists(AIPKit_AI_Form_Settings_Ajax_Handler::class)) {
            return;
        }
        $aiforms_settings_all = AIPKit_AI_Form_Settings_Ajax_Handler::get_settings();
        $settings = $aiforms_settings_all['token_management'] ?? [];
        $usage_key = MetaKeysConstants::AIFORMS_USAGE_META_KEY;
        $reset_key = MetaKeysConstants::AIFORMS_RESET_META_KEY;
        $guest_context_table_id = GuestTableConstants::AI_FORMS_GUEST_CONTEXT_ID;
    } elseif ($module_context === 'wp_ai_client') {
        $settings = [];
        $guest_context_table_id = null;
    } else {
        return;
    }

    if ($guest_context_table_id === null && $is_guest && $module_context !== 'wp_ai_client') {
        return;
    }

    $reset_period = $settings['token_reset_period'] ?? 'never';
    $should_record = false;

    // Determine if tokens should be recorded based on limits
    if ($module_context === 'wp_ai_client') {
        $should_record = false;
    } elseif ($is_guest) {
        $limit = $settings['token_guest_limit'] ?? null;
        if ($limit === null || $limit === '' || (ctype_digit((string)$limit) && (int)$limit > 0)) {
            $should_record = true;
        } // Record if unlimited or limit > 0
    } else { // Logged-in User
        $limit_mode = $settings['token_limit_mode'] ?? 'general';
        $limit_value_source = ($limit_mode === 'general') ? ($settings['token_user_limit'] ?? null) : 'role_based';
        if ($limit_value_source === null || $limit_value_source === '' || $limit_value_source === 'role_based') {
            $should_record = true; // Record if unlimited or role-based (as roles might have limits)
        } elseif (ctype_digit((string)$limit_value_source) && (int)$limit_value_source > 0) {
            $should_record = true; // Record if general limit > 0
        }
    }

    if ($should_record && $tokens_left_to_deduct > 0) {
        $guest_table_name = $wpdb->prefix . GuestTableConstants::GUEST_TABLE_NAME_SUFFIX;
        $new_usage = 0;
        if ($is_guest && $guest_context_table_id !== null) {
            foreach ($guest_identifiers as $guest_identifier) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: Direct query to a custom table. Cache is invalidated after each write.
                $guest_row = $wpdb->get_row($wpdb->prepare("SELECT tokens_used, last_reset_timestamp FROM {$guest_table_name} WHERE session_id = %s AND bot_id = %d", $guest_identifier, $guest_context_table_id), ARRAY_A);
                $current_usage = $guest_row ? (int) $guest_row['tokens_used'] : 0;
                $last_reset = $guest_row ? (int) $guest_row['last_reset_timestamp'] : 0;
                $new_usage = $current_usage + $tokens_left_to_deduct; // Use remaining tokens
                if ($last_reset === 0 && $reset_period !== 'never') {
                    $last_reset = time();
                } // Set initial reset time if not set

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->replace($guest_table_name, ['session_id' => $guest_identifier, 'bot_id' => $guest_context_table_id, 'tokens_used' => $new_usage, 'last_reset_timestamp' => $last_reset, 'last_updated_at' => current_time('mysql', 1)], ['%s', '%d', '%d', '%d', '%s']);
                wp_cache_delete("aipkit_guest_usage_{$guest_identifier}_{$guest_context_table_id}", 'aipkit_token_usage');
            }
        } elseif (!$is_guest) {
            $current_usage = (int) get_user_meta($user_id, $usage_key, true);
            $new_usage = $current_usage + $tokens_left_to_deduct; // Use remaining tokens
            update_user_meta($user_id, $usage_key, $new_usage);
            if ($reset_period !== 'never') {
                if (!get_user_meta($user_id, $reset_key, true)) {
                    update_user_meta($user_id, $reset_key, time());
                } // Set initial reset time
            }
        }
        $log_context_str = $is_guest ? "Guest {$session_id}" : "User {$user_id}";
        $log_module_str = ($module_context === 'chat') ? "Bot {$context_id_or_bot_id}" : "Module {$module_context}";
    } else {
        $log_context_str = $is_guest ? "Guest {$session_id}" : "User {$user_id}";
        $log_module_str = ($module_context === 'chat') ? "Bot {$context_id_or_bot_id}" : "Module {$module_context}";
    }

    if ($ledger_repository && method_exists($ledger_repository, 'insert_entry')) {
        $context_type = $module_context === 'chat' ? 'chatbot' : 'module';
        $context_id = null;
        $reference_type = null;
        $reference_id = null;

        if ($module_context === 'chat' && is_numeric($context_id_or_bot_id)) {
            $context_id = absint($context_id_or_bot_id);
        } elseif ($module_context === 'image_generator') {
            $context_id = is_numeric($context_id_or_bot_id)
                ? absint($context_id_or_bot_id)
                : GuestTableConstants::IMG_GEN_GUEST_CONTEXT_ID;
        } elseif ($module_context === 'ai_forms') {
            $form_id = 0;
            if (!empty($usage_context['form_id']) && is_numeric($usage_context['form_id'])) {
                $form_id = absint($usage_context['form_id']);
            } elseif (
                ($usage_context['pricing_scope_type'] ?? '') === 'ai_form' &&
                !empty($usage_context['pricing_scope_id']) &&
                is_numeric($usage_context['pricing_scope_id'])
            ) {
                $form_id = absint($usage_context['pricing_scope_id']);
            }

            if ($form_id > 0) {
                $context_type = 'ai_form';
                $context_id = $form_id;
                $reference_type = 'ai_form';
                $reference_id = (string) $form_id;
            } else {
                $context_id = GuestTableConstants::AI_FORMS_GUEST_CONTEXT_ID;
            }
        }

        $ledger_repository->insert_entry([
            'user_id' => $user_id,
            'session_id' => $user_id ? null : $session_id,
            'module' => $module_context,
            'context_type' => $context_type,
            'context_id' => $context_id,
            'provider' => $provider !== '' ? $provider : null,
            'model' => $model !== '' ? $model : null,
            'operation' => $operation,
            'usage_input_units' => max(0, (int) ($normalized_usage['input_units'] ?? 0)),
            'usage_output_units' => max(0, (int) ($normalized_usage['output_units'] ?? 0)),
            'usage_total_units' => max(0, (int) ($normalized_usage['total_units'] ?? $tokens_used)),
            'credits_delta' => 0 - $deducted_from_balance,
            'entry_type' => 'usage',
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'meta' => [
                'legacy_total_units' => $tokens_used,
                'billed_credits' => $billed_units,
                'pricing_module' => $pricing_module !== '' ? $pricing_module : $module_context,
                'billing_method' => sanitize_key((string) ($charge_estimate['billing_method'] ?? 'legacy_fallback')),
                'raw_charge' => isset($charge_estimate['raw_charge']) ? (float) $charge_estimate['raw_charge'] : (float) $billed_units,
                'used_legacy_fallback' => !empty($charge_estimate['used_legacy_fallback']),
                'resolved_rule_id' => is_array($resolved_rule) && isset($resolved_rule['id']) ? absint($resolved_rule['id']) : null,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'balance_units' => $deducted_from_balance,
                'quota_units' => $tokens_left_to_deduct,
                'quota_recorded' => $should_record && $tokens_left_to_deduct > 0,
                'tool_units' => max(0, (int) ($normalized_usage['tool_units'] ?? 0)),
                'tool_usage' => isset($normalized_usage['tool_usage']) && is_array($normalized_usage['tool_usage'])
                    ? $normalized_usage['tool_usage']
                    : [],
                'raw_usage_data' => $normalized_usage['raw_usage_data'] ?? [],
            ],
        ]);
    }
}
