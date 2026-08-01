<?php

namespace WPAICG\Core\TokenManager\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_Usage_Normalizer
{
    /**
     * @param array<string, mixed> $usage_context
     * @return array<string, mixed>
     */
    public function normalize(array $usage_context = [], int $fallback_units = 0): array
    {
        $usage_data = isset($usage_context['usage_data']) && is_array($usage_context['usage_data'])
            ? $usage_context['usage_data']
            : [];

        $input_units = $this->extract_first_int($usage_data, ['input_tokens', 'prompt_tokens', 'input_units']);
        $output_units = $this->extract_first_int($usage_data, ['output_tokens', 'completion_tokens', 'output_units']);
        $total_units = $this->extract_first_int($usage_data, ['total_tokens', 'total_units', 'billable_units']);
        $unit_count = $this->extract_first_int($usage_data, ['unit_count', 'image_count', 'video_count', 'items']);
        $tool_usage = $this->extract_tool_usage($usage_data);
        $tool_units = $this->sum_numeric_tool_usage($tool_usage);

        if ($total_units <= 0 && ($input_units > 0 || $output_units > 0)) {
            $total_units = $input_units + $output_units;
        }

        if ($total_units <= 0) {
            $total_units = max(0, $fallback_units);
        }

        if ($unit_count <= 0 && $input_units === 0 && $output_units === 0) {
            $unit_count = $total_units;
        }

        return [
            'input_units' => $input_units,
            'output_units' => $output_units,
            'total_units' => $total_units,
            'unit_count' => $unit_count,
            'tool_units' => $tool_units,
            'tool_usage' => $tool_usage,
            'fallback_units' => max(0, $fallback_units),
            'raw_usage_data' => $usage_data,
        ];
    }

    /**
     * @param array<string, mixed> $usage_data
     * @param array<int, string> $keys
     */
    private function extract_first_int(array $usage_data, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($usage_data[$key]) && is_numeric($usage_data[$key])) {
                return max(0, (int) $usage_data[$key]);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $usage_data
     * @return array<string, mixed>
     */
    private function extract_tool_usage(array $usage_data): array
    {
        if (isset($usage_data['server_side_tool_usage']) && is_array($usage_data['server_side_tool_usage'])) {
            return $usage_data['server_side_tool_usage'];
        }

        if (isset($usage_data['tool_usage']) && is_array($usage_data['tool_usage'])) {
            return $usage_data['tool_usage'];
        }

        if (
            isset($usage_data['provider_raw']) &&
            is_array($usage_data['provider_raw']) &&
            isset($usage_data['provider_raw']['server_side_tool_usage']) &&
            is_array($usage_data['provider_raw']['server_side_tool_usage'])
        ) {
            return $usage_data['provider_raw']['server_side_tool_usage'];
        }

        return [];
    }

    /**
     * @param mixed $tool_usage
     */
    private function sum_numeric_tool_usage($tool_usage): int
    {
        if (is_numeric($tool_usage)) {
            return max(0, (int) $tool_usage);
        }
        if (!is_array($tool_usage)) {
            return 0;
        }

        $total = 0;
        foreach ($tool_usage as $value) {
            $total += $this->sum_numeric_tool_usage($value);
        }

        return $total;
    }
}
