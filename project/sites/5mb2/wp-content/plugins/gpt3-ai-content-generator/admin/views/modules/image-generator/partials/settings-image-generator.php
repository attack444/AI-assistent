<?php

/**
 * Partial: Image Generator Settings
 * Renders module-level settings for the Image Generator.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

use WPAICG\Chat\Storage\BotSettingsManager;
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;

$settings_data = AIPKit_Image_Settings_Ajax_Handler::get_settings();
$settings_nonce = wp_create_nonce('aipkit_image_generator_settings_nonce');

$token_settings = $settings_data['token_management'] ?? [];
$custom_css = $settings_data['common']['custom_css'] ?? '';
$frontend_display_settings = $settings_data['frontend_display'] ?? [];
$allowed_models_str = $frontend_display_settings['allowed_models'] ?? '';
$ui_text_settings = $settings_data['ui_text'] ?? [];
$ui_text_defaults = AIPKit_Image_Settings_Ajax_Handler::get_default_ui_text_settings();

$default_css_template = "/* --- AIPKit Image Generator Custom CSS Example --- */
.aipkit_image_generator_public_wrapper.aipkit-theme-custom {
    background-color: #f0f4f8;
    border: 1px solid #d1d9e4;
    color: #2c3e50;
}
.aipkit_image_generator_public_wrapper.aipkit-theme-custom .aipkit_image_generator_input_bar {
    background-color: #ffffff;
    border: 1px solid #d1d9e4;
}
.aipkit_image_generator_public_wrapper.aipkit-theme-custom .aipkit_btn-primary {
    background-color: #3498db;
    border-color: #2980b9;
}
";

$default_reset_period = BotSettingsManager::DEFAULT_TOKEN_RESET_PERIOD;
$default_limit_message = BotSettingsManager::get_default_token_limit_message();
$default_limit_mode = BotSettingsManager::DEFAULT_TOKEN_LIMIT_MODE;
$default_token_limit_actions = BotSettingsManager::get_default_token_limit_action_settings();
$token_limit_action_options = BotSettingsManager::get_token_limit_action_options();

$guest_limit = $token_settings['token_guest_limit'] ?? null;
$user_limit = $token_settings['token_user_limit'] ?? null;
$reset_period = $token_settings['token_reset_period'] ?? $default_reset_period;
$limit_message = $token_settings['token_limit_message'] ?? $default_limit_message;
$limit_mode = $token_settings['token_limit_mode'] ?? $default_limit_mode;
$token_limit_primary_action_type = $token_settings['token_limit_primary_action_type'] ?? $default_token_limit_actions['primary_type'];
$token_limit_primary_action_label = $token_settings['token_limit_primary_action_label'] ?? $default_token_limit_actions['primary_label'];
$token_limit_primary_action_url = $token_settings['token_limit_primary_action_url'] ?? $default_token_limit_actions['primary_url'];
$token_limit_secondary_action_type = $token_settings['token_limit_secondary_action_type'] ?? $default_token_limit_actions['secondary_type'];
$token_limit_secondary_action_label = $token_settings['token_limit_secondary_action_label'] ?? $default_token_limit_actions['secondary_label'];
$token_limit_secondary_action_url = $token_settings['token_limit_secondary_action_url'] ?? $default_token_limit_actions['secondary_url'];

$role_limits_raw = $token_settings['token_role_limits'] ?? [];
$role_limits = is_string($role_limits_raw) ? json_decode($role_limits_raw, true) : $role_limits_raw;
if (!is_array($role_limits)) {
    $role_limits = [];
}

$guest_limit_value = ($guest_limit === null) ? '' : (string) $guest_limit;
$user_limit_value = ($user_limit === null) ? '' : (string) $user_limit;
$primary_action_show_label = $token_limit_primary_action_type !== 'none';
$primary_action_show_url = $token_limit_primary_action_type === 'custom_url';
$secondary_action_show_label = $token_limit_secondary_action_type !== 'none';
$secondary_action_show_url = $token_limit_secondary_action_type === 'custom_url';

$get_ui_text_value = static function (string $key) use ($ui_text_settings, $ui_text_defaults): string {
    $value = isset($ui_text_settings[$key]) ? (string) $ui_text_settings[$key] : '';
    if ($value === '' && isset($ui_text_defaults[$key])) {
        return (string) $ui_text_defaults[$key];
    }
    return $value;
};

$ui_text_fields = [
    [
        'id' => 'aipkit_image_ui_text_generate_label',
        'name' => 'ui_text_generate_label',
        'key' => 'generate_label',
        'label' => __('Generate button', 'gpt3-ai-content-generator'),
        'helper' => __('Generate action label.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_edit_label',
        'name' => 'ui_text_edit_label',
        'key' => 'edit_label',
        'label' => __('Edit button', 'gpt3-ai-content-generator'),
        'helper' => __('Edit action label.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_mode_generate_label',
        'name' => 'ui_text_mode_generate_label',
        'key' => 'mode_generate_label',
        'label' => __('Generate tab', 'gpt3-ai-content-generator'),
        'helper' => __('Generate mode label.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_mode_edit_label',
        'name' => 'ui_text_mode_edit_label',
        'key' => 'mode_edit_label',
        'label' => __('Edit tab', 'gpt3-ai-content-generator'),
        'helper' => __('Edit mode label.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_generate_placeholder',
        'name' => 'ui_text_generate_placeholder',
        'key' => 'generate_placeholder',
        'label' => __('Generate placeholder', 'gpt3-ai-content-generator'),
        'helper' => __('Prompt field hint.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_edit_placeholder',
        'name' => 'ui_text_edit_placeholder',
        'key' => 'edit_placeholder',
        'label' => __('Edit placeholder', 'gpt3-ai-content-generator'),
        'helper' => __('Edit prompt hint.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_source_image_label',
        'name' => 'ui_text_source_image_label',
        'key' => 'source_image_label',
        'label' => __('Source image', 'gpt3-ai-content-generator'),
        'helper' => __('Upload field label.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_upload_dropzone_title',
        'name' => 'ui_text_upload_dropzone_title',
        'key' => 'upload_dropzone_title',
        'label' => __('Upload title', 'gpt3-ai-content-generator'),
        'helper' => __('Dropzone title.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_upload_dropzone_meta',
        'name' => 'ui_text_upload_dropzone_meta',
        'key' => 'upload_dropzone_meta',
        'label' => __('Upload meta', 'gpt3-ai-content-generator'),
        'helper' => __('Dropzone helper line.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_upload_hint',
        'name' => 'ui_text_upload_hint',
        'key' => 'upload_hint',
        'label' => __('Upload helper', 'gpt3-ai-content-generator'),
        'helper' => __('Upload guidance text.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_history_title',
        'name' => 'ui_text_history_title',
        'key' => 'history_title',
        'label' => __('History title', 'gpt3-ai-content-generator'),
        'helper' => __('User history heading.', 'gpt3-ai-content-generator'),
    ],
    [
        'id' => 'aipkit_image_ui_text_results_empty',
        'name' => 'ui_text_results_empty',
        'key' => 'results_empty',
        'label' => __('Empty results', 'gpt3-ai-content-generator'),
        'helper' => __('Shown before results.', 'gpt3-ai-content-generator'),
    ],
];
?>
<form id="aipkit_image_generator_settings_form" class="aipkit_ai_forms_settings_form aipkit_image_generator_settings_form" onsubmit="return false;">
    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($settings_nonce); ?>">
    <div class="aipkit_ai_forms_settings_page" data-aipkit-settings-module-tab-scope="image-generator">
        <div class="aipkit_settings_module_tabs" role="tablist" aria-label="<?php esc_attr_e('Image Generator settings', 'gpt3-ai-content-generator'); ?>" data-aipkit-settings-module-tabs="image-generator">
            <button
                type="button"
                class="aipkit_settings_module_tab aipkit_active"
                id="aipkit_image_generator_settings_section_tab_limits"
                role="tab"
                aria-selected="true"
                aria-controls="aipkit_image_generator_settings_section_limits"
                data-aipkit-settings-module-tab="limits"
            >
                <?php esc_html_e('Limits', 'gpt3-ai-content-generator'); ?>
            </button>
            <button
                type="button"
                class="aipkit_settings_module_tab"
                id="aipkit_image_generator_settings_section_tab_ui_text"
                role="tab"
                aria-selected="false"
                aria-controls="aipkit_image_generator_settings_section_ui_text"
                data-aipkit-settings-module-tab="ui-text"
                tabindex="-1"
            >
                <?php esc_html_e('UI Text', 'gpt3-ai-content-generator'); ?>
            </button>
            <button
                type="button"
                class="aipkit_settings_module_tab"
                id="aipkit_image_generator_settings_section_tab_custom_css"
                role="tab"
                aria-selected="false"
                aria-controls="aipkit_image_generator_settings_section_custom_css"
                data-aipkit-settings-module-tab="custom-css"
                tabindex="-1"
            >
                <?php esc_html_e('Custom CSS', 'gpt3-ai-content-generator'); ?>
            </button>
            <button
                type="button"
                class="aipkit_settings_module_tab"
                id="aipkit_image_generator_settings_section_tab_frontend_models"
                role="tab"
                aria-selected="false"
                aria-controls="aipkit_image_generator_settings_section_frontend_models"
                data-aipkit-settings-module-tab="frontend-models"
                tabindex="-1"
            >
                <?php esc_html_e('Frontend Models', 'gpt3-ai-content-generator'); ?>
            </button>
        </div>
        <?php
        $aipkit_token_limits_section_id_prefix = 'aipkit_image_generator_settings_section';
        $aipkit_token_limits_field_id_prefix = 'aipkit_image_token';
        $aipkit_token_limits_field_name_prefix = 'image_token';
        $aipkit_token_limits_reset_period_row_extra_class = 'aipkit_token_reset_period_row';
        include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/token-limits-settings-section.php';
        ?>

        <section
            class="aipkit_ai_forms_settings_block aipkit_settings_module_tab_panel"
            id="aipkit_image_generator_settings_section_ui_text"
            role="tabpanel"
            aria-labelledby="aipkit_image_generator_settings_section_tab_ui_text"
            data-aipkit-settings-module-tab-panel="ui-text"
            hidden
        >
            <div class="aipkit_ai_forms_settings_block_header">
                <div>
                    <h3 class="aipkit_ai_forms_settings_block_title"><?php esc_html_e('UI text', 'gpt3-ai-content-generator'); ?></h3>
                    <p class="aipkit_ai_forms_settings_block_helper"><?php esc_html_e('Frontend labels and placeholders.', 'gpt3-ai-content-generator'); ?></p>
                </div>
            </div>
            <div class="aipkit_ai_forms_settings_block_body">
                <?php foreach ($ui_text_fields as $field) : ?>
                    <div class="aipkit_ai_forms_settings_row">
                        <label class="aipkit_form-label" for="<?php echo esc_attr($field['id']); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <span class="aipkit_form-label-helper"><?php echo esc_html($field['helper']); ?></span>
                        </label>
                        <input
                            type="text"
                            id="<?php echo esc_attr($field['id']); ?>"
                            name="<?php echo esc_attr($field['name']); ?>"
                            class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                            value="<?php echo esc_attr($get_ui_text_value($field['key'])); ?>"
                            placeholder="<?php echo esc_attr($ui_text_defaults[$field['key']] ?? ''); ?>"
                        />
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php
        $aipkit_custom_css_section_id_prefix = 'aipkit_image_generator_settings_section';
        $aipkit_custom_css_field_id = 'aipkit_image_generator_custom_css';
        $aipkit_custom_css_header_helper = __('Theme overrides for Custom image theme.', 'gpt3-ai-content-generator');
        $aipkit_custom_css_label_helper = __('Applies to the Custom theme.', 'gpt3-ai-content-generator');
        include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/custom-css-settings-section.php';
        ?>

        <?php
        $aipkit_frontend_models_section_id_prefix = 'aipkit_image_generator_settings_section';
        $aipkit_frontend_models_textarea_id = 'aipkit_image_gen_frontend_models';
        $aipkit_frontend_models_providers_textarea_id = '';
        $aipkit_frontend_models_selector_id = 'aipkit_image_gen_models_selector';
        $aipkit_frontend_models_empty_all_selected = $allowed_models_str === '';
        include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/frontend-models-settings-section.php';
        ?>
    </div>
</form>
