<?php

namespace WPAICG\Core\TokenManager\Ledger;

use WPAICG\Core\TokenManager\Constants\MetaKeysConstants;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Balance_Service
{
    private $ledger_repository;

    public function __construct(?AIPKit_Ledger_Repository $ledger_repository = null)
    {
        $this->ledger_repository = $ledger_repository;
    }

    public function get_current_balance(?int $user_id): int
    {
        if (!$user_id) {
            return 0;
        }

        $balance = get_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, true);

        return is_numeric($balance) ? max(0, (int) $balance) : 0;
    }

    public function set_current_balance(int $user_id, int $balance): int
    {
        $balance = max(0, $balance);
        update_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, $balance);

        return $balance;
    }

    public function apply_delta(int $user_id, int $delta): int
    {
        $current_balance = $this->get_current_balance($user_id);
        $new_balance = max(0, $current_balance + $delta);
        update_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, $new_balance);

        return $new_balance;
    }

    /**
     * @return array<string, int>
     */
    public function deduct_available_balance(int $user_id, int $requested_units): array
    {
        $requested_units = max(0, $requested_units);
        $balance_before = $this->get_current_balance($user_id);
        $deducted = min($balance_before, $requested_units);
        $balance_after = $balance_before - $deducted;

        update_user_meta($user_id, MetaKeysConstants::TOKEN_BALANCE_META_KEY, $balance_after);

        return [
            'balance_before' => $balance_before,
            'deducted' => $deducted,
            'balance_after' => $balance_after,
            'remaining' => max(0, $requested_units - $deducted),
        ];
    }

    public function get_ledger_backed_balance(int $user_id): int
    {
        if (!$this->ledger_repository || $user_id <= 0) {
            return 0;
        }

        return $this->ledger_repository->get_balance_total_for_user($user_id);
    }
}
