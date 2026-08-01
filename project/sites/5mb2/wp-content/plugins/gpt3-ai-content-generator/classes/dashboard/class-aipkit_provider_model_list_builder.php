<?php

namespace WPAICG;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable builder for provider and model select option lists.
 *
 * Output shape is intentionally UI-agnostic so other modules can reuse it.
 */
class AIPKit_Provider_Model_List_Builder
{
    /**
     * Build provider options for select/radio controls.
     *
     * @param array $providers Ordered provider keys.
     * @param bool  $is_pro    Whether current license is pro.
     * @return array<int, array<string, mixed>>
     */
    public static function get_provider_options(array $providers, bool $is_pro): array
    {
        $options = [];

        foreach ($providers as $provider_key) {
            $provider_key = (string) $provider_key;
            if ($provider_key === '') {
                continue;
            }

            $disabled = ($provider_key === 'Ollama' && !$is_pro);
            $label = $disabled
                ? __('Ollama (Pro)', 'gpt3-ai-content-generator')
                : AIPKit_Providers::get_provider_display_name($provider_key);

            $options[] = [
                'value' => $provider_key,
                'label' => $label,
                'disabled' => $disabled,
            ];
        }

        return $options;
    }

    /**
     * Build grouped model options for a provider.
     *
     * @param string $provider_key Provider key (OpenAI, Google, ...).
     * @param string $current_model Current saved model/deployment.
     * @return array<string, mixed>
     */
    public static function get_model_options(string $provider_key, string $current_model = ''): array
    {
        $provider_key = trim($provider_key);
        $current_model = trim($current_model);

        $payload = [
            'groups' => [],
            'manual_option' => null,
            'has_selectable_options' => false,
            'empty_option_label' => __('(Sync to load models)', 'gpt3-ai-content-generator'),
        ];

        switch ($provider_key) {
            case 'OpenAI':
                return self::build_openai_model_options($current_model, $payload);

            case 'OpenRouter':
                return self::build_openrouter_model_options($current_model, $payload);

            case 'Google':
                return self::build_google_model_options($current_model, $payload);

            case 'Claude':
                return self::build_claude_model_options($current_model, $payload);

            case 'Azure':
                return self::build_azure_model_options($current_model, $payload);

            case 'DeepSeek':
                return self::build_deepseek_model_options($current_model, $payload);

            case 'xAI':
                return self::build_xai_model_options($current_model, $payload);

            case 'Ollama':
                return self::build_ollama_model_options($current_model, $payload);

            default:
                return $payload;
        }
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_openai_model_options(string $current_model, array $payload): array
    {
        $grouped_models = AIPKit_Providers::get_openai_models();
        $recommended = self::normalize_option_rows(AIPKit_Providers::get_recommended_models('OpenAI'));
        $recommended_lookup = array_fill_keys(array_column($recommended, 'value'), true);

        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options('OpenAI', $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        if (is_array($grouped_models)) {
            foreach ($grouped_models as $group_label => $group_items) {
                if (!is_array($group_items)) {
                    continue;
                }

                $group_options = [];
                foreach ($group_items as $model_row) {
                    $normalized = self::normalize_option_row($model_row);
                    if (!$normalized) {
                        continue;
                    }
                    if (isset($recommended_lookup[$normalized['value']])) {
                        continue;
                    }
                    $group_options[] = $normalized;
                }

                if (empty($group_options)) {
                    continue;
                }

                $group_options = self::mark_selected_options('OpenAI', $current_model, $group_options, $found_current);
                $groups[] = [
                    'label' => (string) $group_label,
                    'options' => $group_options,
                ];
            }
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '' && strpos($current_model, 'tts-') !== 0) {
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $current_model,
            ];
        }

        return $payload;
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_openrouter_model_options(string $current_model, array $payload): array
    {
        $model_list = AIPKit_Providers::get_openrouter_models();
        $recommended = self::normalize_option_rows(AIPKit_Providers::get_recommended_models('OpenRouter'));
        $recommended_lookup = array_fill_keys(array_column($recommended, 'value'), true);

        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options('OpenRouter', $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        $grouped = [];
        if (is_array($model_list)) {
            foreach ($model_list as $model_row) {
                $normalized = self::normalize_option_row($model_row);
                if (!$normalized) {
                    continue;
                }
                if (isset($recommended_lookup[$normalized['value']])) {
                    continue;
                }

                $parts = explode('/', $normalized['value']);
                $prefix = strtolower(trim($parts[0] ?? 'other'));
                if ($prefix === '') {
                    $prefix = 'other';
                }

                if (!isset($grouped[$prefix])) {
                    $grouped[$prefix] = [];
                }
                $grouped[$prefix][] = $normalized;
            }
        }

        if (!empty($grouped)) {
            ksort($grouped);
            foreach ($grouped as $prefix => $options) {
                usort($options, static function ($left, $right) {
                    return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
                });

                $options = self::mark_selected_options('OpenRouter', $current_model, $options, $found_current);
                $groups[] = [
                    'label' => ucfirst($prefix),
                    'options' => $options,
                ];
            }
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '') {
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $current_model,
            ];
        }

        return $payload;
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_google_model_options(string $current_model, array $payload): array
    {
        $model_list = AIPKit_Providers::get_google_models();
        $recommended_raw = self::normalize_option_rows(AIPKit_Providers::get_recommended_models('Google'));
        $recommended_lookup = [];
        $recommended = [];
        foreach ($recommended_raw as $recommended_option) {
            $recommended_value = self::normalize_google_model_id((string) ($recommended_option['value'] ?? ''));
            if ($recommended_value === '' || isset($recommended_lookup[$recommended_value])) {
                continue;
            }

            $recommended_lookup[$recommended_value] = true;
            $recommended[] = [
                'value' => $recommended_value,
                'label' => (string) ($recommended_option['label'] ?? $recommended_value),
            ];
        }

        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options('Google', $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        $all_models = [];
        if (is_array($model_list)) {
            foreach ($model_list as $model_row) {
                if (!is_array($model_row)) {
                    continue;
                }
                $model_id = self::normalize_google_model_id((string) ($model_row['id'] ?? ($model_row['name'] ?? '')));
                if ($model_id === '') {
                    continue;
                }
                if (isset($recommended_lookup[$model_id])) {
                    continue;
                }
                $all_models[] = [
                    'value' => $model_id,
                    'label' => (string) ($model_row['name'] ?? $model_id),
                ];
            }
        }

        if (!empty($all_models)) {
            $all_models = self::mark_selected_options('Google', $current_model, $all_models, $found_current);
            $groups[] = [
                'label' => !empty($recommended) ? __('All models', 'gpt3-ai-content-generator') : '',
                'options' => $all_models,
            ];
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '') {
            $display_model = self::normalize_google_model_id($current_model);
            if ($display_model === '') {
                $display_model = $current_model;
            }
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $display_model,
            ];
        }

        return $payload;
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_claude_model_options(string $current_model, array $payload): array
    {
        $model_list = AIPKit_Providers::get_claude_models();
        $recommended = self::normalize_option_rows(AIPKit_Providers::get_recommended_models('Claude'));
        $recommended_lookup = array_fill_keys(array_column($recommended, 'value'), true);

        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options('Claude', $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        $all_models = [];
        if (is_array($model_list)) {
            foreach ($model_list as $model_row) {
                $normalized = self::normalize_option_row($model_row);
                if (!$normalized) {
                    continue;
                }
                if (isset($recommended_lookup[$normalized['value']])) {
                    continue;
                }
                $all_models[] = $normalized;
            }
        }

        if (!empty($all_models)) {
            $all_models = self::mark_selected_options('Claude', $current_model, $all_models, $found_current);
            $groups[] = [
                'label' => !empty($recommended) ? __('All models', 'gpt3-ai-content-generator') : '',
                'options' => $all_models,
            ];
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '') {
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $current_model,
            ];
        }

        return $payload;
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_azure_model_options(string $current_model, array $payload): array
    {
        $deployment_list = AIPKit_Providers::get_azure_all_models_grouped();

        $flat_options = [];
        if (is_array($deployment_list)) {
            foreach ($deployment_list as $group_or_model) {
                if (!is_array($group_or_model)) {
                    continue;
                }
                if (isset($group_or_model['id'])) {
                    $normalized = self::normalize_azure_row($group_or_model);
                    if ($normalized) {
                        $flat_options[] = $normalized;
                    }
                    continue;
                }
                foreach ($group_or_model as $model_row) {
                    $normalized = self::normalize_azure_row($model_row);
                    if ($normalized) {
                        $flat_options[] = $normalized;
                    }
                }
            }
        }

        $recommended = self::normalize_option_rows(AIPKit_Providers::get_recommended_models('Azure'));
        if (empty($recommended) && !empty($flat_options)) {
            $recommended = self::fallback_recommended($flat_options, 3);
        }
        $recommended_lookup = array_fill_keys(array_column($recommended, 'value'), true);

        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options('Azure', $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        $is_grouped = false;
        if (is_array($deployment_list) && !empty($deployment_list)) {
            $first = reset($deployment_list);
            $is_grouped = is_array($first) && !isset($first['id']);
        }

        if ($is_grouped) {
            foreach ($deployment_list as $group_label => $group_models) {
                if (!is_array($group_models)) {
                    continue;
                }
                $options = [];
                foreach ($group_models as $model_row) {
                    $normalized = self::normalize_azure_row($model_row);
                    if (!$normalized) {
                        continue;
                    }
                    if (isset($recommended_lookup[$normalized['value']])) {
                        continue;
                    }
                    $options[] = $normalized;
                }
                if (empty($options)) {
                    continue;
                }
                $options = self::mark_selected_options('Azure', $current_model, $options, $found_current);
                $groups[] = [
                    'label' => (string) $group_label,
                    'options' => $options,
                ];
            }
        } elseif (!empty($flat_options)) {
            $all_options = [];
            foreach ($flat_options as $option) {
                if (isset($recommended_lookup[$option['value']])) {
                    continue;
                }
                $all_options[] = $option;
            }
            if (!empty($all_options)) {
                $all_options = self::mark_selected_options('Azure', $current_model, $all_options, $found_current);
                $groups[] = [
                    'label' => !empty($recommended) ? __('All models', 'gpt3-ai-content-generator') : '',
                    'options' => $all_options,
                ];
            }
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '') {
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $current_model,
            ];
        }

        return $payload;
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_deepseek_model_options(string $current_model, array $payload): array
    {
        return self::build_simple_recommended_model_options(
            'DeepSeek',
            self::normalize_option_rows(AIPKit_Providers::get_deepseek_models()),
            $current_model,
            $payload
        );
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_xai_model_options(string $current_model, array $payload): array
    {
        return self::build_simple_recommended_model_options(
            'xAI',
            self::normalize_option_rows(AIPKit_Providers::get_xai_models()),
            $current_model,
            $payload
        );
    }

    /**
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_ollama_model_options(string $current_model, array $payload): array
    {
        return self::build_simple_recommended_model_options(
            'Ollama',
            self::normalize_option_rows(AIPKit_Providers::get_ollama_models()),
            $current_model,
            $payload
        );
    }

    /**
     * @param string $provider_key
     * @param array  $model_list
     * @param string $current_model
     * @param array  $payload
     * @return array
     */
    private static function build_simple_recommended_model_options(string $provider_key, array $model_list, string $current_model, array $payload): array
    {
        $recommended = self::normalize_option_rows(AIPKit_Providers::get_recommended_models($provider_key));
        if (empty($recommended) && !empty($model_list)) {
            $recommended = self::fallback_recommended($model_list, 3);
        }

        $recommended_lookup = array_fill_keys(array_column($recommended, 'value'), true);
        $found_current = false;
        $groups = [];

        if (!empty($recommended)) {
            $recommended = self::mark_selected_options($provider_key, $current_model, $recommended, $found_current);
            $groups[] = [
                'label' => __('Recommended', 'gpt3-ai-content-generator'),
                'options' => $recommended,
            ];
        }

        $all_options = [];
        foreach ($model_list as $option) {
            if (isset($recommended_lookup[$option['value']])) {
                continue;
            }
            $all_options[] = $option;
        }

        if (!empty($all_options)) {
            $all_options = self::mark_selected_options($provider_key, $current_model, $all_options, $found_current);
            $groups[] = [
                'label' => !empty($recommended) ? __('All models', 'gpt3-ai-content-generator') : '',
                'options' => $all_options,
            ];
        }

        $payload['groups'] = $groups;
        $payload['has_selectable_options'] = self::has_options($groups);

        if (!$found_current && $current_model !== '') {
            $payload['manual_option'] = [
                'value' => $current_model,
                'label' => $current_model,
            ];
        }

        return $payload;
    }

    /**
     * @param mixed $model_row
     * @return array<string, string>|null
     */
    private static function normalize_option_row($model_row): ?array
    {
        if (!is_array($model_row)) {
            return null;
        }

        $value = (string) ($model_row['id'] ?? '');
        if ($value === '') {
            return null;
        }

        return [
            'value' => $value,
            'label' => (string) ($model_row['name'] ?? $value),
        ];
    }

    /**
     * @param mixed $model_row
     * @return array<string, string>|null
     */
    private static function normalize_azure_row($model_row): ?array
    {
        if (!is_array($model_row)) {
            return null;
        }

        $deployment_id = (string) ($model_row['id'] ?? '');
        if ($deployment_id === '') {
            return null;
        }

        $model_name = (string) ($model_row['name'] ?? $deployment_id);
        $label = $deployment_id;
        if ($model_name !== '' && $model_name !== $deployment_id) {
            $label .= ' (model: ' . $model_name . ')';
        }

        return [
            'value' => $deployment_id,
            'label' => $label,
        ];
    }

    /**
     * @param array $rows
     * @return array<int, array<string, string>>
     */
    private static function normalize_option_rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $option = self::normalize_option_row($row);
            if ($option) {
                $normalized[] = $option;
            }
        }
        return $normalized;
    }

    /**
     * @param string $provider_key
     * @param string $current_model
     * @param array  $options
     * @param bool   $found_current
     * @return array
     */
    private static function mark_selected_options(string $provider_key, string $current_model, array $options, bool &$found_current): array
    {
        foreach ($options as $index => $option) {
            $value = (string) ($option['value'] ?? '');
            $is_selected = self::is_selected($provider_key, $current_model, $value);
            if ($is_selected) {
                $found_current = true;
            }
            $options[$index]['selected'] = $is_selected;
        }

        return $options;
    }

    private static function is_selected(string $provider_key, string $current_model, string $value): bool
    {
        if ($current_model === '' || $value === '') {
            return false;
        }

        if ($provider_key === 'Google') {
            return self::normalize_google_model_id($current_model) === self::normalize_google_model_id($value);
        }

        return $current_model === $value;
    }

    private static function normalize_google_model_id(string $model_id): string
    {
        $model_id = trim($model_id);
        if ($model_id === '') {
            return '';
        }

        if (strpos($model_id, 'models/') === 0) {
            return (string) substr($model_id, 7);
        }

        return $model_id;
    }

    /**
     * @param array $groups
     * @return bool
     */
    private static function has_options(array $groups): bool
    {
        foreach ($groups as $group) {
            if (!empty($group['options']) && is_array($group['options'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $options
     * @param int   $limit
     * @return array
     */
    private static function fallback_recommended(array $options, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_slice(array_values($options), 0, $limit);
    }
}
