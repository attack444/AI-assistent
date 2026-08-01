<?php

namespace WPAICG\Core\TokenManager\Limits;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Quota_Service
{
    /**
     * @return array<string, int|bool|null>
     */
    public function evaluate(?int $limit, int $current_usage, int $required_units = 0): array
    {
        $current_usage = max(0, $current_usage);
        $required_units = max(0, $required_units);

        if ($limit === null) {
            return [
                'has_limit' => false,
                'allowed' => true,
                'limit' => null,
                'current_usage' => $current_usage,
                'required_units' => $required_units,
                'projected_usage' => $current_usage + $required_units,
                'remaining' => null,
            ];
        }

        $limit = max(0, $limit);
        $projected_usage = $current_usage + $required_units;

        return [
            'has_limit' => true,
            'allowed' => $projected_usage <= $limit,
            'limit' => $limit,
            'current_usage' => $current_usage,
            'required_units' => $required_units,
            'projected_usage' => $projected_usage,
            'remaining' => max(0, $limit - $current_usage),
        ];
    }

    public function can_consume(?int $limit, int $current_usage, int $required_units = 0): bool
    {
        $evaluation = $this->evaluate($limit, $current_usage, $required_units);

        return !empty($evaluation['allowed']);
    }
}
