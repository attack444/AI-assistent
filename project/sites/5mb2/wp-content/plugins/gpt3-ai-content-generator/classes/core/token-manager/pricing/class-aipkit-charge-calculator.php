<?php

namespace WPAICG\Core\TokenManager\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Charge_Calculator
{
    /**
     * @param array<string, mixed>|null $resolved_rule
     * @param array<string, mixed> $normalized_usage
     * @return array<string, mixed>
     */
    public function calculate(?array $resolved_rule, array $normalized_usage, int $fallback_units = 0): array
    {
        $fallback_units = max(0, $fallback_units);

        if (!is_array($resolved_rule) || empty($resolved_rule)) {
            $required_units = max(0, (int) ($normalized_usage['total_units'] ?? $fallback_units));

            return [
                'resolved_rule' => null,
                'billing_method' => 'legacy_fallback',
                'required_units' => $required_units,
                'billed_credits' => $required_units,
                'raw_charge' => (float) $required_units,
                'used_legacy_fallback' => true,
                'normalized_usage' => $normalized_usage,
            ];
        }

        $billing_method = (string) ($resolved_rule['billing_method'] ?? 'legacy_fallback');
        $input_units = max(0, (int) ($normalized_usage['input_units'] ?? 0));
        $output_units = max(0, (int) ($normalized_usage['output_units'] ?? 0));
        $total_units = max(0, (int) ($normalized_usage['total_units'] ?? $fallback_units));
        $unit_count = max(0, (int) ($normalized_usage['unit_count'] ?? 0));

        $raw_charge = 0.0;

        switch ($billing_method) {
            case 'per_1k_tokens':
                if ($input_units === 0 && $output_units === 0 && $total_units > 0) {
                    $input_units = $total_units;
                }

                $input_rate = is_numeric($resolved_rule['input_rate'] ?? null) ? (float) $resolved_rule['input_rate'] : 0.0;
                $output_rate = is_numeric($resolved_rule['output_rate'] ?? null) ? (float) $resolved_rule['output_rate'] : 0.0;
                $raw_charge = ($input_units / 1000) * $input_rate;
                $raw_charge += ($output_units / 1000) * $output_rate;
                break;

            case 'flat':
                $raw_charge = is_numeric($resolved_rule['unit_rate'] ?? null) ? (float) $resolved_rule['unit_rate'] : (float) $total_units;
                break;

            case 'per_image':
            case 'per_video':
                $count = $unit_count > 0 ? $unit_count : max(1, $total_units);
                $unit_rate = is_numeric($resolved_rule['unit_rate'] ?? null) ? (float) $resolved_rule['unit_rate'] : 0.0;
                $raw_charge = $count * $unit_rate;
                break;

            default:
                $raw_charge = (float) $total_units;
                $billing_method = 'legacy_fallback';
                break;
        }

        $required_units = max(0, (int) ceil($raw_charge));

        return [
            'resolved_rule' => $resolved_rule,
            'billing_method' => $billing_method,
            'required_units' => $required_units,
            'billed_credits' => $required_units,
            'raw_charge' => $raw_charge,
            'used_legacy_fallback' => $billing_method === 'legacy_fallback',
            'normalized_usage' => $normalized_usage,
        ];
    }
}
