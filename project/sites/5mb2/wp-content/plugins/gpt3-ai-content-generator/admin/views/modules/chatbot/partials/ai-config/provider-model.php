<?php

/**
 * Partial: AI Config - Provider and Model Selection
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Get saved Azure deployment name if applicable
$saved_azure_deployment = ($saved_provider === 'Azure') ? $saved_model : '';

$get_recommended_models = static function (string $provider): array {
    $models = \WPAICG\AIPKit_Providers::get_recommended_models($provider);
    return array_values(array_filter($models, static function ($model) {
        return is_array($model) && !empty($model['id']);
    }));
};

$recommended_openai = $get_recommended_models('OpenAI');
$recommended_openai_ids = array_column($recommended_openai, 'id');
$recommended_openai_lookup = array_fill_keys($recommended_openai_ids, true);

$recommended_openrouter = $get_recommended_models('OpenRouter');
$recommended_openrouter_ids = array_column($recommended_openrouter, 'id');
$recommended_openrouter_lookup = array_fill_keys($recommended_openrouter_ids, true);

$recommended_google = $get_recommended_models('Google');
$recommended_google_ids = array_column($recommended_google, 'id');
$recommended_google_lookup = array_fill_keys($recommended_google_ids, true);

$recommended_claude = $get_recommended_models('Claude');
$recommended_claude_ids = array_column($recommended_claude, 'id');
$recommended_claude_lookup = array_fill_keys($recommended_claude_ids, true);

if (!isset($providers) || !is_array($providers) || empty($providers)) {
    $providers = isset($allowed_main_providers) && is_array($allowed_main_providers)
        ? $allowed_main_providers
        : ['OpenAI', 'Google', 'Claude', 'OpenRouter', 'Azure', 'DeepSeek', 'xAI'];
}

$show_chatbot_selector = empty($is_next_layout) || !$is_next_layout;
$provider_select_options = class_exists('\\WPAICG\\AIPKit_Provider_Model_List_Builder')
    ? \WPAICG\AIPKit_Provider_Model_List_Builder::get_provider_options($providers, (bool) ($is_pro ?? false))
    : [];
$model_sync_timestamps = get_option('aipkit_model_sync_timestamps', []);
if (!is_array($model_sync_timestamps)) {
    $model_sync_timestamps = [];
}

$render_simple_model_field = static function (array $config) use ($bot_id, $saved_model, $saved_provider): void {
    $provider = (string) ($config['provider'] ?? '');
    $slug = (string) ($config['slug'] ?? '');
    $models = isset($config['models']) && is_array($config['models']) ? $config['models'] : [];
    if ($provider === '' || $slug === '') {
        return;
    }
    $field_id = 'aipkit_bot_' . $bot_id . '_' . $slug . '_model';
    $field_name = $slug . '_model';
    $found_current = false;
    ?>
        <div
            class="aipkit_chatbot_model_field"
            data-provider="<?php echo esc_attr($provider); ?>"
            style="display: <?php echo $saved_provider === $provider ? 'block' : 'none'; ?>;"
        >
             <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="<?php echo esc_attr($field_id); ?>"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="<?php echo esc_attr($field_id); ?>"
                    name="<?php echo esc_attr($field_name); ?>"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                    <?php if (!empty($models)) : ?>
                        <?php foreach ($models as $model) :
                            $model_id = $model['id'] ?? '';
                            $model_name = $model['name'] ?? $model_id;
                            if ($model_id === $saved_model) {
                                $found_current = true;
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr($model_id); ?>"
                                <?php selected($saved_model, $model_id); ?>
                            >
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!$found_current && !empty($saved_model) && $saved_provider === $provider) : ?>
                        <option value="<?php echo esc_attr($saved_model); ?>" selected><?php echo esc_html($saved_model); ?></option>
                    <?php elseif (empty($models) && !$found_current && empty($saved_model)) : ?>
                        <option value=""><?php esc_html_e('(Sync models in main AI Settings)', 'gpt3-ai-content-generator'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    <?php
};

?>
<div class="aipkit_form-row aipkit_form-row-align-bottom aipkit_builder_inline_row aipkit_chatbot_model_row">
    <?php if ($show_chatbot_selector) : ?>
        <div class="aipkit_form-group aipkit_form-col aipkit_chatbot_model_col aipkit_chatbot_model_col--bot">
            <label
                class="aipkit_form-label"
                for="aipkit_chatbot_builder_bot_select"
            >
                <span><?php esc_html_e('Chatbot', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <div class="aipkit_input-with-button">
                <select
                    id="aipkit_chatbot_builder_bot_select"
                    name="aipkit_chatbot_builder_bot_select"
                    class="aipkit_form-input aipkit_builder_bot_select_input"
                    <?php echo empty($all_bots_ordered_entries) ? 'disabled' : ''; ?>
                >
                    <?php if (empty($all_bots_ordered_entries)) : ?>
                        <option value="">
                            <?php esc_html_e('No chatbots yet', 'gpt3-ai-content-generator'); ?>
                        </option>
                    <?php else : ?>
                        <?php foreach ($all_bots_ordered_entries as $bot_entry) : ?>
                            <?php $bot_post = $bot_entry['post']; ?>
                            <option
                                value="<?php echo esc_attr($bot_post->ID); ?>"
                                <?php selected($bot_id, $bot_post->ID); ?>
                            >
                                <?php echo esc_html($bot_post->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <div class="aipkit_form-group aipkit_form-col aipkit_chatbot_model_col aipkit_chatbot_model_col--unified">
        <div class="aipkit_chatbot_model_label_row">
            <label
                class="aipkit_form-label"
                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_unified_model_trigger"
            >
                <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
            </label>
            <?php if (!empty($is_next_layout)) : ?>
            <button
                type="button"
                class="aipkit_chatbot_model_sync_btn"
                data-aipkit-chatbot-sync-models
                data-aipkit-syncing-label="<?php esc_attr_e('Syncing…', 'gpt3-ai-content-generator'); ?>"
                data-aipkit-model-sync-times="<?php echo esc_attr(wp_json_encode($model_sync_timestamps)); ?>"
                aria-label="<?php esc_attr_e('Sync models for the selected provider', 'gpt3-ai-content-generator'); ?>"
                aria-busy="false"
            >
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <span class="aipkit_btn-text"><?php esc_html_e('Sync models', 'gpt3-ai-content-generator'); ?></span>
            </button>
            <?php endif; ?>
        </div>
        <?php
        $aipkit_unified_model_selector_config = [
            'trigger_id' => 'aipkit_bot_' . $bot_id . '_unified_model_trigger',
            'initial_label' => $saved_model ?: __('Select model', 'gpt3-ai-content-generator'),
        ];
        include dirname(__DIR__, 3) . '/shared/unified-model-selector.php';
        ?>
        <?php if (!empty($is_next_layout)) : ?>
        <span
            class="aipkit_chatbot_model_last_synced"
            data-aipkit-model-last-synced
            aria-live="polite"
            hidden
        ></span>
        <?php endif; ?>
    </div>

    <div class="aipkit_model_state_controls" aria-hidden="true">
    <div class="aipkit_form-group aipkit_form-col aipkit_chatbot_model_col aipkit_chatbot_model_col--provider">
        <label
            class="aipkit_form-label"
            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_provider"
        >
            <?php esc_html_e('Engine', 'gpt3-ai-content-generator'); ?>
        </label>
                <select
            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_provider"
            name="provider"
            class="aipkit_form-input aipkit_chatbot_provider_select" <?php // JS targets this class?>
            data-aipkit-provider-notice-target="aipkit_provider_notice_chatbot"
            data-aipkit-model-state-select="1"
        >
            <?php if (empty($provider_select_options)) :
                foreach ($providers as $p_value) {
                    $p_value = (string) $p_value;
                    if ($p_value === '') {
                        continue;
                    }
                    $disabled = ($p_value === 'Ollama' && empty($is_pro));
                    $label = class_exists('\\WPAICG\\AIPKit_Providers')
                        ? \WPAICG\AIPKit_Providers::get_provider_display_name($p_value)
                        : ($p_value === 'Claude' ? __('Anthropic', 'gpt3-ai-content-generator') : $p_value);
                    $provider_select_options[] = [
                        'value' => $p_value,
                        'label' => $disabled ? __('Ollama (Pro)', 'gpt3-ai-content-generator') : $label,
                        'disabled' => $disabled,
                    ];
                }
            endif; ?>
            <?php foreach ($provider_select_options as $provider_option) :
                if (!is_array($provider_option)) {
                    continue;
                }
                $p_value = (string) ($provider_option['value'] ?? '');
                if ($p_value === '') {
                    continue;
                }
                $disabled = !empty($provider_option['disabled']);
                $label = (string) ($provider_option['label'] ?? $p_value);
            ?>
                <option
                    value="<?php echo esc_attr($p_value); ?>"
                    <?php selected($saved_provider, $p_value); ?> <?php echo $disabled ? 'disabled' : ''; ?>
                >
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
            </div>

    <div class="aipkit_form-group aipkit_form-col aipkit_chatbot_model_col aipkit_chatbot_model_col--model">
        <div
            class="aipkit_chatbot_model_field" <?php // JS targets this class?>
            data-provider="OpenAI"
            style="display: <?php echo $saved_provider === 'OpenAI' ? 'block' : 'none'; ?>;"
        >
            <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_model"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_model"
                    name="openai_model"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                    <?php
                     // $grouped_openai_models now only contains chat models (already filtered if applicable)
                     $foundCurrentOpenAI = false;
                    if (!empty($recommended_openai)) : ?>
                        <optgroup label="<?php echo esc_attr__('Recommended', 'gpt3-ai-content-generator'); ?>">
                            <?php foreach ($recommended_openai as $rec):
                                $rec_id = $rec['id'] ?? '';
                                $rec_name = $rec['name'] ?? $rec_id;
                                if (!$rec_id) {
                                    continue;
                                }
                                if ($rec_id === $saved_model) {
                                    $foundCurrentOpenAI = true;
                                }
                                ?>
                                <option
                                    value="<?php echo esc_attr($rec_id); ?>"
                                    <?php selected($saved_model, $rec_id); ?>
                                >
                                    <?php echo esc_html($rec_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif;
                    if (!empty($grouped_openai_models) && is_array($grouped_openai_models)): ?>
                        <?php foreach ($grouped_openai_models as $groupLabel => $groupItems): ?>
                            <optgroup label="<?php echo esc_attr($groupLabel); ?>">
                                <?php foreach ($groupItems as $m):
                                    $model_id   = $m['id'] ?? '';
                                    $model_name = $m['name'] ?? $model_id;
                                    if (!empty($recommended_openai_lookup[$model_id])) {
                                        continue;
                                    }
                                    if ($model_id === $saved_model) {
                                        $foundCurrentOpenAI = true;
                                    }
                                    ?>
                                    <option
                                        value="<?php echo esc_attr($model_id); ?>"
                                        <?php selected($saved_model, $model_id); ?>
                                    >
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php
                    // If saved model not found OR list is empty
                    // AND the saved model is NOT an OpenAI TTS model (as this dropdown is for CHAT models)
                    if (!$foundCurrentOpenAI && !empty($saved_model) && $saved_provider === 'OpenAI' && strpos($saved_model, 'tts-') !== 0) {
                        echo '<option value="' . esc_attr($saved_model) . '" selected>' . esc_html($saved_model) . '</option>';
                    } elseif (empty($grouped_openai_models) && empty($recommended_openai) && (!$foundCurrentOpenAI || empty($saved_model) || strpos($saved_model, 'tts-') === 0)) {
                        echo '<option value="">'.esc_html__('(Sync models in main AI Settings)', 'gpt3-ai-content-generator').'</option>';
                    }
?>
                </select>
            </div>
        </div>

        <div
            class="aipkit_chatbot_model_field"
            data-provider="OpenRouter"
            style="display: <?php echo $saved_provider === 'OpenRouter' ? 'block' : 'none'; ?>;"
        >
             <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_model"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_model"
                    name="openrouter_model"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                    <?php
$foundCurrentOR = false;
if (!empty($recommended_openrouter)) : ?>
                        <optgroup label="<?php echo esc_attr__('Recommended', 'gpt3-ai-content-generator'); ?>">
                            <?php foreach ($recommended_openrouter as $rec):
                                $rec_id = $rec['id'] ?? '';
                                $rec_name = $rec['name'] ?? $rec_id;
                                if (!$rec_id) {
                                    continue;
                                }
                                if ($rec_id === $saved_model) {
                                    $foundCurrentOR = true;
                                }
                                ?>
                                <option
                                    value="<?php echo esc_attr($rec_id); ?>"
                                    <?php selected($saved_model, $rec_id); ?>
                                >
                                    <?php echo esc_html($rec_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif;
if (!empty($openrouter_model_list)) {
    $grouped = [];
    foreach ($openrouter_model_list as $model) {
        if (!empty($model['id']) && !empty($model['name'])) {
            $parts  = explode('/', $model['id']);
            $prefix = strtolower(trim($parts[0]));
            if (!isset($grouped[$prefix])) {
                $grouped[$prefix] = [];
            }
            $grouped[$prefix][] = $model;
        }
    }
    ksort($grouped);
    foreach ($grouped as $prefix => $modelsInGroup): ?>
                            <optgroup label="<?php echo esc_attr(ucfirst($prefix)); ?>">
                                <?php
            usort($modelsInGroup, fn ($a, $b) => strcmp($a['name'], $b['name']));
        foreach ($modelsInGroup as $m):
            if (!empty($recommended_openrouter_lookup[$m['id'] ?? ''])) {
                continue;
            }
            if ($m['id'] === $saved_model) {
                $foundCurrentOR = true;
            } ?>
                                    <option
                                        value="<?php echo esc_attr($m['id']); ?>"
                                        <?php selected($saved_model, $m['id']); ?>
                                    >
                                        <?php echo esc_html($m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach;
}
if (!$foundCurrentOR && !empty($saved_model) && $saved_provider === 'OpenRouter') { ?>
                        <option value="<?php echo esc_attr($saved_model); ?>" selected><?php echo esc_html($saved_model); ?></option>
                    <?php } elseif (empty($openrouter_model_list) && empty($recommended_openrouter) && empty($saved_model)) { ?>
                        <option value=""><?php esc_html_e('(Sync models in main AI Settings)', 'gpt3-ai-content-generator'); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div
            class="aipkit_chatbot_model_field"
            data-provider="Google"
            style="display: <?php echo $saved_provider === 'Google' ? 'block' : 'none'; ?>;"
        >
             <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_model"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_model"
                    name="google_model"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                     <?php
$foundCurrentGoogle = false;
if (!empty($recommended_google)) : ?>
                        <optgroup label="<?php echo esc_attr__('Recommended', 'gpt3-ai-content-generator'); ?>">
                            <?php foreach ($recommended_google as $rec):
                                $rec_id = $rec['id'] ?? '';
                                $rec_name = $rec['name'] ?? $rec_id;
                                if (!$rec_id) {
                                    continue;
                                }
                                if ($rec_id === $saved_model || $saved_model === 'models/' . $rec_id) {
                                    $foundCurrentGoogle = true;
                                }
                                ?>
                                <option
                                    value="<?php echo esc_attr($rec_id); ?>"
                                    <?php selected($saved_model, $rec_id); ?>
                                >
                                    <?php echo esc_html($rec_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif;
if (!empty($google_model_list)): ?>
                        <?php if (!empty($recommended_google)) : ?>
                            <optgroup label="<?php echo esc_attr__('All models', 'gpt3-ai-content-generator'); ?>">
                        <?php endif; ?>
                        <?php foreach ($google_model_list as $gm):
                            $gId   = $gm['id'] ?? ($gm['name'] ?? '');
                            $gName = $gm['name'] ?? $gId;
                            $selectedValue = $gId;
                            $isSelected = ($saved_model === $selectedValue || $saved_model === 'models/'.$selectedValue);
                            if ($isSelected) {
                                $foundCurrentGoogle = true;
                            }
                            if (!empty($recommended_google_lookup[$selectedValue])) {
                                continue;
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr($selectedValue); ?>"
                                <?php echo $isSelected ? 'selected' : ''; ?>
                            >
                                <?php echo esc_html($gName); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!empty($recommended_google)) : ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php
                    if (!$foundCurrentGoogle && !empty($saved_model) && $saved_provider === 'Google'): ?>
                         <?php $displayModel = (strpos($saved_model, 'models/') === 0) ? (string) substr($saved_model, 7) : $saved_model; ?>
                        <option value="<?php echo esc_attr($saved_model); ?>" selected><?php echo esc_html($displayModel); ?></option>
                    <?php elseif (empty($google_model_list) && empty($recommended_google) && !$foundCurrentGoogle && empty($saved_model)): ?>
                        <option value=""><?php esc_html_e('(Sync models in main AI Settings)', 'gpt3-ai-content-generator'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div
            class="aipkit_chatbot_model_field"
            data-provider="Claude"
            style="display: <?php echo $saved_provider === 'Claude' ? 'block' : 'none'; ?>;"
        >
             <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_model"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_model"
                    name="claude_model"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                     <?php
$foundCurrentClaude = false;
if (!empty($recommended_claude)) : ?>
                        <optgroup label="<?php echo esc_attr__('Recommended', 'gpt3-ai-content-generator'); ?>">
                            <?php foreach ($recommended_claude as $rec):
                                $rec_id = $rec['id'] ?? '';
                                $rec_name = $rec['name'] ?? $rec_id;
                                if (!$rec_id) {
                                    continue;
                                }
                                if ($rec_id === $saved_model) {
                                    $foundCurrentClaude = true;
                                }
                                ?>
                                <option
                                    value="<?php echo esc_attr($rec_id); ?>"
                                    <?php selected($saved_model, $rec_id); ?>
                                >
                                    <?php echo esc_html($rec_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif;
if (!empty($claude_model_list)): ?>
                        <?php if (!empty($recommended_claude)) : ?>
                            <optgroup label="<?php echo esc_attr__('All models', 'gpt3-ai-content-generator'); ?>">
                        <?php endif; ?>
                        <?php foreach ($claude_model_list as $model):
                            $model_id = $model['id'] ?? '';
                            $model_name = $model['name'] ?? $model_id;
                            if (!$model_id || !empty($recommended_claude_lookup[$model_id])) {
                                continue;
                            }
                            if ($model_id === $saved_model) {
                                $foundCurrentClaude = true;
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr($model_id); ?>"
                                <?php selected($saved_model, $model_id); ?>
                            >
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!empty($recommended_claude)) : ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$foundCurrentClaude && !empty($saved_model) && $saved_provider === 'Claude'): ?>
                        <option value="<?php echo esc_attr($saved_model); ?>" selected><?php echo esc_html($saved_model); ?></option>
                    <?php elseif (empty($claude_model_list) && empty($recommended_claude) && !$foundCurrentClaude && empty($saved_model)): ?>
                        <option value=""><?php esc_html_e('(Sync models in main AI Settings)', 'gpt3-ai-content-generator'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div
            class="aipkit_chatbot_model_field"
            data-provider="Azure"
            style="display: <?php echo $saved_provider === 'Azure' ? 'block' : 'none'; ?>;"
        >
             <div class="aipkit_input-with-button aipkit_input-with-button--labels">
                <label
                    class="aipkit_form-label aipkit_form-label--status"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_azure_deployment"
                >
                    <span class="aipkit_model_label_text"><?php esc_html_e('Deployment', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_model_status_slot">
                        <span class="aipkit_model_sync_status" aria-live="polite"></span>
                    </span>
                </label>
                <select
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_azure_deployment"
                    name="azure_deployment"
                    class="aipkit_form-input"
                    data-aipkit-model-state-select="1"
                >
                    <?php
                    $foundOldAzure = false;
if (is_array($azure_deployment_list) && !empty($azure_deployment_list)) {
    foreach ($azure_deployment_list as $dep) {
        $dep_id   = $dep['id'] ?? '';
        $dep_name = $dep['name'] ?? $dep_id;
        $label = $dep_id;
        if (!empty($dep_name) && $dep_name !== $dep_id) {
            $label .= ' (model: ' . $dep_name . ')';
        }
        $selected = selected($saved_azure_deployment, $dep_id, false);
        if (!empty($selected)) {
            $foundOldAzure = true;
        }
        echo '<option value="' . esc_attr($dep_id) . '" ' . esc_attr($selected) . '>' . esc_html($label) . '</option>';
    }
}
if (!$foundOldAzure && !empty($saved_azure_deployment)) {
    echo '<option value="'.esc_attr($saved_azure_deployment).'" selected>'.esc_html($saved_azure_deployment . ($foundOldAzure === false && !empty($azure_deployment_list) ? '' : '')).'</option>';
} elseif (empty($saved_azure_deployment) && empty($azure_deployment_list)) {
    echo '<option value="">'.esc_html__('(Sync deployments in main AI Settings)', 'gpt3-ai-content-generator').'</option>';
}
?>
                </select>
            </div>
        </div>

        <?php
        $render_simple_model_field([
            'provider' => 'DeepSeek',
            'slug' => 'deepseek',
            'models' => $deepseek_model_list ?? [],
        ]);
        ?>

        <?php
        $render_simple_model_field([
            'provider' => 'xAI',
            'slug' => 'xai',
            'models' => $xai_model_list ?? [],
        ]);
        ?>

        <?php
        $render_simple_model_field([
            'provider' => 'Ollama',
            'slug' => 'ollama',
            'models' => $ollama_model_list ?? [],
        ]);
        ?>

    </div>
    </div>
</div>
