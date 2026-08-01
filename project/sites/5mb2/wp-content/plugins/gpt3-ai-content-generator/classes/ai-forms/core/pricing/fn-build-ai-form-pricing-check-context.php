<?php

namespace WPAICG\AIForms\Core\Pricing;

use WPAICG\Utils\AIPKit_Prompt_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists(__NAMESPACE__ . '\\build_ai_form_pricing_check_context_logic')) {
    /**
     * @param array<string, mixed> $form_config
     * @param array<string, mixed> $submitted_fields
     * @return array<string, mixed>
     */
    function build_ai_form_pricing_check_context_logic(
        int $form_id,
        array $form_config,
        array $submitted_fields = [],
        ?array $image_inputs = null
    ): array {
        $provider = sanitize_text_field((string) ($form_config['ai_provider'] ?? 'OpenAI'));
        $model = sanitize_text_field((string) ($form_config['ai_model'] ?? ''));
        $prompt_template = AIPKit_Prompt_Sanitizer::sanitize($form_config['prompt_template'] ?? '');
        $input_parts = [];

        if ($prompt_template !== '') {
            $input_parts[] = $prompt_template;
        }

        foreach ($submitted_fields as $field_key => $field_value) {
            if (in_array($field_key, ['ai_provider', 'ai_model'], true)) {
                continue;
            }

            if (is_array($field_value) || is_object($field_value)) {
                $field_value = wp_json_encode($field_value);
            }

            $field_value = trim((string) $field_value);
            if ($field_value === '') {
                continue;
            }

            $input_parts[] = sanitize_text_field((string) $field_key) . ': ' . $field_value;
        }

        $input_tokens = estimate_ai_form_text_tokens_logic(implode("\n", $input_parts));
        if (!empty($image_inputs)) {
            $input_tokens += count($image_inputs) * 768;
        }
        $output_tokens = isset($form_config['max_tokens']) ? absint($form_config['max_tokens']) : 0;
        if ($output_tokens <= 0) {
            $output_tokens = 512;
        }

        $total_tokens = max(1, $input_tokens + $output_tokens);

        return [
            'provider' => $provider,
            'model' => $model,
            'operation' => 'form_submit',
            'pricing_scope_type' => 'ai_form',
            'pricing_scope_id' => $form_id > 0 ? $form_id : null,
            'usage_data' => [
                'input_tokens' => $input_tokens,
                'output_tokens' => $output_tokens,
                'total_tokens' => $total_tokens,
            ],
            'fallback_units' => $total_tokens,
        ];
    }

    function estimate_ai_form_text_tokens_logic(string $text): int
    {
        $text = AIPKit_Prompt_Sanitizer::sanitize($text);
        if ($text === '') {
            return 0;
        }

        $character_count = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);

        return max(1, (int) ceil($character_count / 4));
    }
}
