<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$is_pro = class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
?>

<select
    id="aipkit_content_writer_provider"
    name="ai_provider"
    class="aipkit_autosave_trigger"
    data-aipkit-provider-notice-target="aipkit_provider_notice_content_writer"
    data-aipkit-provider-notice-defer="1"
    hidden
    aria-hidden="true"
    tabindex="-1"
>
    <?php
    if (!empty($providers_for_select) && is_array($providers_for_select)) {
        foreach ($providers_for_select as $provider_name) {
            $provider_value = strtolower($provider_name);
            $provider_disabled = ($provider_name === 'Ollama' && !$is_pro);
            $provider_display_name = class_exists('\\WPAICG\\AIPKit_Providers')
                ? \WPAICG\AIPKit_Providers::get_provider_display_name((string) $provider_name)
                : ((string) $provider_name === 'Claude' ? __('Anthropic', 'gpt3-ai-content-generator') : (string) $provider_name);
            $provider_label = $provider_disabled
                ? __('Ollama (Pro)', 'gpt3-ai-content-generator')
                : $provider_display_name;
            ?>
            <option
                value="<?php echo esc_attr($provider_value); ?>"
                <?php selected($default_provider, $provider_value); ?>
                <?php echo $provider_disabled ? 'disabled' : ''; ?>
            >
                <?php echo esc_html($provider_label); ?>
            </option>
            <?php
        }
    }
    ?>
</select>

<input
    type="hidden"
    id="aipkit_content_writer_model"
    name="ai_model"
    class="aipkit_autosave_trigger"
    value=""
>

<div class="aipkit_cw_ai_row">
    <div class="aipkit_cw_panel_label_wrap">
        <label class="aipkit_cw_panel_label" for="aipkit_content_writer_ai_selection">
            <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
        </label>
    </div>
    <div class="aipkit_cw_ai_control aipkit_cw_ai_control--model">
        <select
            id="aipkit_content_writer_ai_selection"
            class="aipkit_form-input"
            data-aipkit-picker-title="<?php esc_attr_e('Model', 'gpt3-ai-content-generator'); ?>"
        >
            <option value=""><?php esc_html_e('Loading models...', 'gpt3-ai-content-generator'); ?></option>
        </select>
        <?php include __DIR__ . '/ai-advanced-settings.php'; ?>
    </div>
</div>
