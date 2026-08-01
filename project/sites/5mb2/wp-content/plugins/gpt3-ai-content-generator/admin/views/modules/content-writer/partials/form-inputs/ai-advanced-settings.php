<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$reasoning_options = [
    'none' => __('None', 'gpt3-ai-content-generator'),
    'low' => __('Low', 'gpt3-ai-content-generator'),
    'medium' => __('Medium', 'gpt3-ai-content-generator'),
    'high' => __('High', 'gpt3-ai-content-generator'),
    'xhigh' => __('XHigh', 'gpt3-ai-content-generator'),
];
?>

<button
    type="button"
    class="aipkit_cw_settings_icon_trigger"
    id="aipkit_cw_ai_advanced_trigger"
    data-aipkit-popover-target="aipkit_cw_ai_advanced_popover"
    data-aipkit-popover-placement="left"
    aria-controls="aipkit_cw_ai_advanced_popover"
    aria-expanded="false"
    aria-label="<?php esc_attr_e('Model settings', 'gpt3-ai-content-generator'); ?>"
    title="<?php esc_attr_e('Model settings', 'gpt3-ai-content-generator'); ?>"
>
    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
</button>

<div class="aipkit_model_settings_popover aipkit_cw_settings_popover" id="aipkit_cw_ai_advanced_popover" aria-hidden="true">
    <div class="aipkit_model_settings_popover_panel aipkit_cw_settings_popover_panel aipkit_cw_ai_advanced_popover_panel" role="dialog" aria-label="<?php esc_attr_e('Model settings', 'gpt3-ai-content-generator'); ?>">
        <div class="aipkit_model_settings_popover_header aipkit_cw_settings_sheet_header">
            <span class="aipkit_model_settings_popover_title"><?php esc_html_e('Model settings', 'gpt3-ai-content-generator'); ?></span>
        </div>
        <div class="aipkit_model_settings_popover_body aipkit_cw_settings_popover_body aipkit_cw_settings_sheet_body">
            <div class="aipkit_popover_options_list">
                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <div class="aipkit_cw_settings_option_text">
                            <label class="aipkit_popover_option_label" for="aipkit_content_writer_temperature">
                                <?php esc_html_e('Temperature', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <span class="aipkit_popover_option_helper">
                                <?php esc_html_e('More varied writing.', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>
                        <input
                            type="number"
                            id="aipkit_content_writer_temperature"
                            name="ai_temperature"
                            class="aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_input aipkit_popover_option_input--compact aipkit_cw_ai_advanced_number"
                            min="0"
                            max="2"
                            step="0.1"
                            value="<?php echo esc_attr($default_temperature); ?>"
                            inputmode="decimal"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_cw_reasoning_effort_field" hidden>
                    <div class="aipkit_popover_option_main">
                        <div class="aipkit_cw_settings_option_text">
                            <label class="aipkit_popover_option_label" for="aipkit_content_writer_reasoning_effort">
                                <?php esc_html_e('Reasoning', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <span class="aipkit_popover_option_helper">
                                <?php esc_html_e('More effort for hard tasks.', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>
                        <select
                            id="aipkit_content_writer_reasoning_effort"
                            name="reasoning_effort"
                            class="aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                        >
                            <?php foreach ($reasoning_options as $reasoning_value => $reasoning_label) : ?>
                                <option value="<?php echo esc_attr($reasoning_value); ?>" <?php selected($reasoning_value, 'none'); ?>>
                                    <?php echo esc_html($reasoning_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
