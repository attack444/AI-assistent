<?php

namespace WPAICG\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Centralized helper for OpenAI reasoning effort normalization.
 * - Maps deprecated "minimal" to "low"
 * - Prevents invalid efforts from being sent
 * - Handles model-specific support rules
 */
class AIPKit_OpenAI_Reasoning
{
    /**
     * Sanitize a raw reasoning effort value.
     * Maps "minimal" -> "low" and only allows supported strings.
     *
     * @param mixed $effort Raw effort value.
     * @return string Sanitized effort or empty string if invalid.
     */
    public static function sanitize_effort($effort): string
    {
        if (!is_string($effort)) {
            return '';
        }
        $value = sanitize_key($effort);
        if ($value === 'minimal') {
            $value = 'low';
        }
        $allowed = ['none', 'low', 'medium', 'high', 'xhigh'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * Returns true if the model family supports reasoning controls.
     */
    public static function supports_reasoning(string $model): bool
    {
        $model_lower = strtolower($model);
        if (self::is_chat_variant($model_lower)) {
            return false;
        }
        return strpos($model_lower, 'gpt-5') !== false
            || strpos($model_lower, 'o1') !== false
            || strpos($model_lower, 'o3') !== false
            || strpos($model_lower, 'o4') !== false;
    }

    /**
     * Returns true when sampling controls (temperature/top_p/penalties) are supported.
     *
     * GPT-5 chat variants (e.g. gpt-5.2-chat-latest) do not accept temperature.
     * Reasoning-capable models also require these controls to be omitted.
     */
    public static function supports_sampling_controls(string $model): bool
    {
        $model_lower = strtolower($model);

        if (self::supports_reasoning($model_lower)) {
            return false;
        }

        if (strpos($model_lower, 'gpt-5') !== false && self::is_chat_variant($model_lower)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize the reasoning effort for a given model.
     * Returns empty string when unsupported or invalid (so caller can omit).
     *
     * @param string $model Selected model name.
     * @param mixed  $effort Raw effort value.
     * @return string Normalized effort or empty string.
     */
    public static function normalize_effort_for_model(string $model, $effort): string
    {
        $model_lower = strtolower($model);
        if (!self::supports_reasoning($model_lower)) {
            return '';
        }

        $effort = self::sanitize_effort($effort);
        if ($effort === '') {
            return '';
        }

        $allowed = self::get_allowed_efforts($model_lower);
        if (!in_array($effort, $allowed, true)) {
            return '';
        }

        return $effort;
    }

    /**
     * Get allowed efforts for a specific model.
     */
    private static function get_allowed_efforts(string $model_lower): array
    {
        return self::get_reasoning_rule($model_lower)['allowed'];
    }

    /**
     * Resolve the current reasoning rule for the given model family.
     *
     * The plugin exposes the effort values it can safely handle today. We do
     * not surface OpenAI's legacy "minimal" level in the UI, so GPT-5 family
     * models are restricted to low/medium/high here even though the base model
     * supports "minimal" as well.
     *
     * @return array{allowed: string[], default: string}
     */
    private static function get_reasoning_rule(string $model_lower): array
    {
        if (self::is_gpt_5_pro($model_lower)) {
            if (self::is_post_gpt_5_1_pro($model_lower)) {
                return [
                    'allowed' => ['medium', 'high', 'xhigh'],
                    'default' => 'medium',
                ];
            }

            return [
                'allowed' => ['high'],
                'default' => 'high',
            ];
        }

        if (self::is_codex_model($model_lower)) {
            if (self::is_post_gpt_5_1_codex($model_lower)) {
                return [
                    'allowed' => ['low', 'medium', 'high', 'xhigh'],
                    'default' => 'medium',
                ];
            }

            return [
                'allowed' => ['low', 'medium', 'high'],
                'default' => 'medium',
            ];
        }

        if (self::is_gpt_5_1($model_lower)) {
            return [
                'allowed' => ['none', 'low', 'medium', 'high'],
                'default' => 'none',
            ];
        }

        if (self::is_post_gpt_5_1($model_lower)) {
            return [
                'allowed' => ['none', 'low', 'medium', 'high', 'xhigh'],
                'default' => 'none',
            ];
        }

        // Pre gpt-5.1 (gpt-5, o-series). "minimal" is mapped to "low".
        return [
            'allowed' => ['low', 'medium', 'high'],
            'default' => 'medium',
        ];
    }

    private static function is_gpt_5_pro(string $model_lower): bool
    {
        return (bool) preg_match('/gpt-5(\.\d+)?-pro/', $model_lower);
    }

    private static function is_gpt_5_1(string $model_lower): bool
    {
        return strpos($model_lower, 'gpt-5.1') !== false;
    }

    /**
     * Detects models after gpt-5.1 for xhigh support (e.g., gpt-5.2+, gpt-6+).
     */
    private static function is_post_gpt_5_1(string $model_lower): bool
    {
        if (preg_match('/gpt-5\.(\d+)/', $model_lower, $matches)) {
            return ((int) $matches[1]) >= 2;
        }
        if (preg_match('/gpt-(\d+)/', $model_lower, $matches)) {
            return ((int) $matches[1]) >= 6;
        }
        return false;
    }

    private static function is_post_gpt_5_1_pro(string $model_lower): bool
    {
        if (!self::is_gpt_5_pro($model_lower)) {
            return false;
        }

        if (preg_match('/gpt-5\.(\d+)-pro/', $model_lower, $matches)) {
            return ((int) $matches[1]) >= 2;
        }

        if (preg_match('/gpt-(\d+)-pro/', $model_lower, $matches)) {
            return ((int) $matches[1]) >= 6;
        }

        return false;
    }

    private static function is_codex_model(string $model_lower): bool
    {
        return strpos($model_lower, 'codex') !== false;
    }

    private static function is_post_gpt_5_1_codex(string $model_lower): bool
    {
        return self::is_codex_model($model_lower) && self::is_post_gpt_5_1($model_lower);
    }

    /**
     * Detects chat-only variants that do not support reasoning controls.
     * Examples: gpt-5-chat-latest, gpt-5.1-chat-latest, gpt-5.2-chat-latest.
     */
    private static function is_chat_variant(string $model_lower): bool
    {
        return (bool) preg_match('/(?:^|[-_])chat(?:[-_]|$)/', $model_lower);
    }
}
