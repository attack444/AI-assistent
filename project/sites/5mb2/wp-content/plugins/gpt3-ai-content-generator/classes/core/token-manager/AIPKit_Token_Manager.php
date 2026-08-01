<?php

namespace WPAICG\Core\TokenManager; // New Namespace

use WPAICG\Core\TokenManager\Constants\CronHookConstant; // For CRON_HOOK
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load method logic files (these will define functions in their respective sub-namespaces)
$base_path = __DIR__ . '/';
require_once $base_path . 'init/ConstructorLogic.php';
require_once $base_path . 'cron/ScheduleTokenResetEventLogic.php';
require_once $base_path . 'cron/UnscheduleTokenResetEventLogic.php';
require_once $base_path . 'reset/PerformTokenResetLogic.php';
require_once $base_path . 'reset/IsResetDueLogic.php';
require_once $base_path . 'check/CheckAndResetTokensLogic.php';
require_once $base_path . 'record/RecordTokenUsageLogic.php';
require_once $base_path . 'helpers/GetGuestQuotaIdentifiersLogic.php';
require_once $base_path . 'pricing/class-aipkit-price-resolver.php';
require_once $base_path . 'pricing/class-aipkit-usage-normalizer.php';
require_once $base_path . 'pricing/class-aipkit-charge-calculator.php';
require_once $base_path . 'ledger/class-aipkit-ledger-repository.php';
require_once $base_path . 'ledger/class-aipkit-balance-service.php';
require_once $base_path . 'limits/class-aipkit-quota-service.php';

// Load constants
require_once $base_path . 'constants/CronHookConstant.php';
require_once $base_path . 'constants/MetaKeysConstants.php';
require_once $base_path . 'constants/GuestTableConstants.php';


/**
 * AIPKit_Token_Manager (New Facade/Entry Point)
 * Handles token usage tracking, limits, and resets for different modules.
 * Delegates logic to namespaced functions.
 */
class AIPKit_Token_Manager {

    // --- Properties for dependencies (injected by ConstructorLogic) ---
    private $guest_table_name;
    private $bot_storage;
    private $price_resolver;
    private $usage_normalizer;
    private $charge_calculator;
    private $ledger_repository;
    private $balance_service;
    private $quota_service;
    // --- End Properties ---

    public function __construct() {
        // Call the constructor logic from the init sub-namespace
        Init\ConstructorLogic($this);
    }

    // --- Public static methods for cron scheduling ---
    public static function schedule_token_reset_event() {
        Cron\ScheduleTokenResetEventLogic(CronHookConstant::CRON_HOOK);
    }

    public static function unschedule_token_reset_event() {
        Cron\UnscheduleTokenResetEventLogic(CronHookConstant::CRON_HOOK);
    }
    // --- End cron scheduling ---

    // --- Public method for performing token reset ---
    public function perform_token_reset() {
        Reset\PerformTokenResetLogic($this);
    }
    // --- End perform token reset ---

    // --- Public static method for checking reset due ---
    public static function is_reset_due(int $last_reset_timestamp, string $period): bool {
        return Reset\IsResetDueLogic($last_reset_timestamp, $period);
    }
    // --- End checking reset due ---
    // --- Public methods for token checking and recording ---
    /**
     * @return bool|\WP_Error
     */
    public function check_and_reset_tokens(?int $user_id, ?string $session_id, ?int $context_id_or_bot_id, string $module_context = 'chat', array $usage_context = []) {
        return Check\CheckAndResetTokensLogic($this, $user_id, $session_id, $context_id_or_bot_id, $module_context, $usage_context);
    }

    public function record_token_usage(?int $user_id, ?string $session_id, ?int $context_id_or_bot_id, int $tokens_used, string $module_context = 'chat', array $usage_context = []) {
        Record\RecordTokenUsageLogic($this, $user_id, $session_id, $context_id_or_bot_id, $tokens_used, $module_context, $usage_context);
    }
    // --- End token checking and recording ---

    /**
     * Estimate the billable credits for a usage context without changing live behavior.
     *
     * @param int $fallback_units
     * @param string $module_context
     * @param array<string, mixed> $usage_context
     * @return array<string, mixed>
     */
    public function estimate_usage_charge(int $fallback_units, string $module_context = 'chat', array $usage_context = []): array {
        $normalized_usage = $this->usage_normalizer
            ? $this->usage_normalizer->normalize($usage_context, $fallback_units)
            : [
                'input_units' => 0,
                'output_units' => 0,
                'total_units' => max(0, $fallback_units),
                'unit_count' => max(0, $fallback_units),
                'fallback_units' => max(0, $fallback_units),
                'raw_usage_data' => [],
            ];

        $pricing_module = sanitize_key((string) ($usage_context['pricing_module'] ?? $module_context));
        if ($pricing_module === '') {
            $pricing_module = sanitize_key($module_context);
        }

        $resolved_rule = $this->price_resolver
            ? $this->price_resolver->resolve_rule($pricing_module, $usage_context)
            : null;

        if (!$this->charge_calculator) {
            return [
                'resolved_rule' => $resolved_rule,
                'billing_method' => is_array($resolved_rule) ? ($resolved_rule['billing_method'] ?? 'legacy_fallback') : 'legacy_fallback',
                'required_units' => max(0, $fallback_units),
                'billed_credits' => max(0, $fallback_units),
                'raw_charge' => (float) max(0, $fallback_units),
                'used_legacy_fallback' => true,
                'pricing_module' => $pricing_module,
                'normalized_usage' => $normalized_usage,
            ];
        }

        $charge = $this->charge_calculator->calculate($resolved_rule, $normalized_usage, $fallback_units);
        $charge['pricing_module'] = $pricing_module;

        return $charge;
    }


    // --- Getters for dependencies needed by logic functions (called via $this passed to them) ---
    public function get_guest_table_name(): string {
        return $this->guest_table_name;
    }

    public function get_bot_storage() { // Type hint can be added if BotStorage class is defined in a way it can be type-hinted here
        return $this->bot_storage;
    }

    public function get_price_resolver() {
        return $this->price_resolver;
    }

    public function get_usage_normalizer() {
        return $this->usage_normalizer;
    }

    public function get_charge_calculator() {
        return $this->charge_calculator;
    }

    public function get_ledger_repository() {
        return $this->ledger_repository;
    }

    public function get_balance_service() {
        return $this->balance_service;
    }

    public function get_quota_service() {
        return $this->quota_service;
    }
    // --- End Getters ---

    // --- Setters for dependencies (used by ConstructorLogic) ---
    public function set_guest_table_name(string $name): void {
        $this->guest_table_name = $name;
    }

    public function set_bot_storage($storage_instance): void { // Type hint can be added
        $this->bot_storage = $storage_instance;
    }

    public function set_price_resolver($resolver_instance): void {
        $this->price_resolver = $resolver_instance;
    }

    public function set_usage_normalizer($normalizer_instance): void {
        $this->usage_normalizer = $normalizer_instance;
    }

    public function set_charge_calculator($calculator_instance): void {
        $this->charge_calculator = $calculator_instance;
    }

    public function set_ledger_repository($repository_instance): void {
        $this->ledger_repository = $repository_instance;
    }

    public function set_balance_service($service_instance): void {
        $this->balance_service = $service_instance;
    }

    public function set_quota_service($service_instance): void {
        $this->quota_service = $service_instance;
    }
    // --- End Setters ---
}
