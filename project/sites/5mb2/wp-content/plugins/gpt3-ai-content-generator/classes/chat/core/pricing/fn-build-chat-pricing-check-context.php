<?php

namespace WPAICG\Chat\Core\Pricing;

use WPAICG\Utils\AIPKit_Prompt_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

if (!function_exists(__NAMESPACE__ . '\\build_chat_pricing_check_context_logic')) {
    $determine_provider_model_path = dirname(__DIR__) . '/ai-service/determine_provider_model.php';
    if (file_exists($determine_provider_model_path)) {
        require_once $determine_provider_model_path;
    }

    /**
     * @param array<string, mixed> $bot_settings
     * @param array<int, mixed>|null $image_inputs
     * @return array<string, mixed>
     */
    function build_chat_pricing_check_context_logic(
        int $bot_id,
        array $bot_settings,
        string $user_message_text = '',
        ?array $image_inputs = null,
        ?string $model_override = null
    ): array {
        $provider_model = function_exists('\WPAICG\Chat\Core\AIService\determine_provider_model')
            ? \WPAICG\Chat\Core\AIService\determine_provider_model(null, $bot_settings)
            : [
                'provider' => $bot_settings['provider'] ?? 'OpenAI',
                'model' => $bot_settings['model'] ?? '',
            ];

        $provider = sanitize_text_field((string) ($provider_model['provider'] ?? 'OpenAI'));
        $model = sanitize_text_field((string) ($model_override ?: ($provider_model['model'] ?? '')));
        $system_instruction = AIPKit_Prompt_Sanitizer::sanitize($bot_settings['instructions'] ?? '');
        $input_tokens = estimate_text_token_count_logic($system_instruction . "\n\n" . $user_message_text);

        if (!empty($image_inputs)) {
            $input_tokens += count($image_inputs) * 768;
        }

        $max_completion_tokens = isset($bot_settings['max_completion_tokens'])
            ? absint($bot_settings['max_completion_tokens'])
            : 0;
        $output_tokens = $max_completion_tokens > 0 ? $max_completion_tokens : 512;

        if ($model_override !== null) {
            $output_tokens = min(max(64, $output_tokens), 512);
        }

        $total_tokens = max(1, $input_tokens + $output_tokens);

        $context = [
            'provider' => $provider,
            'model' => $model,
            'operation' => 'chat',
            'usage_data' => [
                'input_tokens' => $input_tokens,
                'output_tokens' => $output_tokens,
                'total_tokens' => $total_tokens,
            ],
            'fallback_units' => $total_tokens,
        ];

        return $context;
    }

    function estimate_text_token_count_logic(string $text): int
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
