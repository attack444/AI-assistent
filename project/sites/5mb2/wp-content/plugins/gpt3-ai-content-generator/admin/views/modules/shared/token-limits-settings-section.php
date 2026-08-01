<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Shared view partial configured by parent templates.

$aipkit_token_limits_section_id_prefix = isset($aipkit_token_limits_section_id_prefix)
    ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $aipkit_token_limits_section_id_prefix)
    : '';
$aipkit_token_limits_field_id_prefix = isset($aipkit_token_limits_field_id_prefix)
    ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $aipkit_token_limits_field_id_prefix)
    : '';
$aipkit_token_limits_field_name_prefix = isset($aipkit_token_limits_field_name_prefix)
    ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $aipkit_token_limits_field_name_prefix)
    : '';
$aipkit_token_limits_reset_period_row_extra_class = isset($aipkit_token_limits_reset_period_row_extra_class)
    ? sanitize_html_class((string) $aipkit_token_limits_reset_period_row_extra_class)
    : '';
$aipkit_token_limits_reset_period_row_extra_class = $aipkit_token_limits_reset_period_row_extra_class !== ''
    ? ' ' . $aipkit_token_limits_reset_period_row_extra_class
    : '';
$aipkit_settings_section_is_modern = isset($aipkit_settings_section_variant)
    && $aipkit_settings_section_variant === 'ai-forms-modern';
$aipkit_settings_section_classes = $aipkit_settings_section_is_modern
    ? 'aipkit_ai_forms_settings_surface aipkit_ai_forms_settings_tab_panel'
    : 'aipkit_ai_forms_settings_block aipkit_settings_module_tab_panel';
$aipkit_settings_section_header_class = $aipkit_settings_section_is_modern
    ? 'aipkit_ai_forms_settings_surface_header'
    : 'aipkit_ai_forms_settings_block_header';
$aipkit_settings_section_title_class = $aipkit_settings_section_is_modern
    ? 'aipkit_ai_forms_settings_surface_title'
    : 'aipkit_ai_forms_settings_block_title';
$aipkit_settings_section_helper_class = $aipkit_settings_section_is_modern
    ? 'aipkit_ai_forms_settings_surface_helper'
    : 'aipkit_ai_forms_settings_block_helper';
$aipkit_settings_section_body_class = $aipkit_settings_section_is_modern
    ? 'aipkit_ai_forms_settings_surface_body'
    : 'aipkit_ai_forms_settings_block_body';

$aipkit_token_limits_id = static function (string $suffix) use ($aipkit_token_limits_field_id_prefix): string {
    return $aipkit_token_limits_field_id_prefix . '_' . $suffix;
};
$aipkit_token_limits_name = static function (string $suffix) use ($aipkit_token_limits_field_name_prefix): string {
    return $aipkit_token_limits_field_name_prefix . '_' . $suffix;
};
$aipkit_token_limits_default_label = static function (string $action_type): string {
    return \WPAICG\Chat\Storage\BotSettingsManager::get_token_limit_action_default_label($action_type);
};
?>
<section
    class="<?php echo esc_attr($aipkit_settings_section_classes); ?>"
    id="<?php echo esc_attr($aipkit_token_limits_section_id_prefix . '_limits'); ?>"
    role="tabpanel"
    aria-labelledby="<?php echo esc_attr($aipkit_token_limits_section_id_prefix . '_tab_limits'); ?>"
    data-aipkit-settings-module-tab-panel="limits"
>
    <div class="<?php echo esc_attr($aipkit_settings_section_header_class); ?>">
        <div>
            <h3 class="<?php echo esc_attr($aipkit_settings_section_title_class); ?>"><?php esc_html_e('Limits', 'gpt3-ai-content-generator'); ?></h3>
            <p class="<?php echo esc_attr($aipkit_settings_section_helper_class); ?>"><?php esc_html_e('Quotas and reset rules.', 'gpt3-ai-content-generator'); ?></p>
        </div>
    </div>
    <div class="<?php echo esc_attr($aipkit_settings_section_body_class); ?>">
        <div class="aipkit_ai_forms_settings_row">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('guest_limit')); ?>">
                <?php esc_html_e('Guest quota', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('0 disables guest access.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="number"
                id="<?php echo esc_attr($aipkit_token_limits_id('guest_limit')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('guest_limit')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
                value="<?php echo esc_attr($guest_limit_value); ?>"
                min="0"
                step="1"
                placeholder="<?php esc_attr_e('Unlimited', 'gpt3-ai-content-generator'); ?>"
            />
        </div>

        <div class="aipkit_ai_forms_settings_row">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_mode')); ?>">
                <?php esc_html_e('Quota mode', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('For logged-in users.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <select
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_mode')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_mode')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_token_limit_mode_select aipkit_autosave_trigger"
            >
                <option value="general" <?php selected($limit_mode, 'general'); ?>>
                    <?php esc_html_e('Same quota for all users', 'gpt3-ai-content-generator'); ?>
                </option>
                <option value="role_based" <?php selected($limit_mode, 'role_based'); ?>>
                    <?php esc_html_e('Role-based quotas', 'gpt3-ai-content-generator'); ?>
                </option>
            </select>
        </div>

        <div
            class="aipkit_ai_forms_settings_row aipkit_token_general_user_limit_field"
            <?php echo ($limit_mode === 'general') ? '' : 'hidden'; ?>
        >
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('user_limit')); ?>">
                <?php esc_html_e('User quota', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('0 disables logged-in users.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="number"
                id="<?php echo esc_attr($aipkit_token_limits_id('user_limit')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('user_limit')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
                value="<?php echo esc_attr($user_limit_value); ?>"
                min="0"
                step="1"
                placeholder="<?php esc_attr_e('Unlimited', 'gpt3-ai-content-generator'); ?>"
            />
        </div>

        <div class="aipkit_ai_forms_settings_row<?php echo esc_attr($aipkit_token_limits_reset_period_row_extra_class); ?>">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('reset_period')); ?>">
                <?php esc_html_e('Reset period', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('How often usage resets.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <select
                id="<?php echo esc_attr($aipkit_token_limits_id('reset_period')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('reset_period')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
            >
                <option value="never" <?php selected($reset_period, 'never'); ?>><?php esc_html_e('Never', 'gpt3-ai-content-generator'); ?></option>
                <option value="daily" <?php selected($reset_period, 'daily'); ?>><?php esc_html_e('Daily', 'gpt3-ai-content-generator'); ?></option>
                <option value="weekly" <?php selected($reset_period, 'weekly'); ?>><?php esc_html_e('Weekly', 'gpt3-ai-content-generator'); ?></option>
                <option value="monthly" <?php selected($reset_period, 'monthly'); ?>><?php esc_html_e('Monthly', 'gpt3-ai-content-generator'); ?></option>
            </select>
        </div>

        <div
            class="aipkit_ai_forms_settings_row aipkit_token_role_limits_container aipkit_limits_role_row"
            <?php echo ($limit_mode === 'role_based') ? '' : 'hidden'; ?>
        >
            <div class="aipkit_form-label">
                <?php esc_html_e('Role quotas', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Empty allows unlimited. 0 disables a role.', 'gpt3-ai-content-generator'); ?></span>
            </div>
            <div class="aipkit_popover_role_limits aipkit_ai_forms_role_limits">
                <?php
                $editable_roles = get_editable_roles();
                foreach ($editable_roles as $role_slug => $role_info) :
                    $role_name = translate_user_role($role_info['name']);
                    $role_limit = $role_limits[$role_slug] ?? null;
                    $role_limit_value = ($role_limit === null) ? '' : (string) $role_limit;
                    ?>
                    <div class="aipkit_popover_role_limit_row">
                        <span class="aipkit_popover_role_limit_label"><?php echo esc_html($role_name); ?></span>
                        <input
                            type="number"
                            id="<?php echo esc_attr($aipkit_token_limits_id('role_' . $role_slug)); ?>"
                            name="<?php echo esc_attr($aipkit_token_limits_name('role_limits')); ?>[<?php echo esc_attr($role_slug); ?>]"
                            class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
                            value="<?php echo esc_attr($role_limit_value); ?>"
                            min="0"
                            step="1"
                            placeholder="<?php esc_attr_e('Unlimited', 'gpt3-ai-content-generator'); ?>"
                        />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="aipkit_ai_forms_settings_row aipkit_limits_message_row">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_message')); ?>">
                <?php esc_html_e('Quota message', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Shown when the quota is reached.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_message')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_message')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                value="<?php echo esc_attr($limit_message); ?>"
                placeholder="<?php echo esc_attr($default_limit_message); ?>"
            />
        </div>

        <div class="aipkit_ai_forms_settings_row" data-aipkit-limit-action-row="primary">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_type')); ?>">
                <?php esc_html_e('Primary button', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Main quota action.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <select
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_type')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_primary_action_type')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
            >
                <?php foreach ($token_limit_action_options as $action_value => $action_label) : ?>
                    <option
                        value="<?php echo esc_attr($action_value); ?>"
                        data-default-label="<?php echo esc_attr($aipkit_token_limits_default_label($action_value)); ?>"
                        <?php selected($token_limit_primary_action_type, $action_value); ?>
                    >
                        <?php echo esc_html($action_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div
            class="aipkit_ai_forms_settings_row"
            data-aipkit-limit-action-dependent-for="primary"
            data-aipkit-limit-action-field="label"
            <?php if (!$primary_action_show_label) : ?>hidden<?php endif; ?>
        >
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_label')); ?>">
                <?php esc_html_e('Primary label', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Text shown on the primary button.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_label')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_primary_action_label')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                value="<?php echo esc_attr($token_limit_primary_action_label); ?>"
                placeholder="<?php echo esc_attr($aipkit_token_limits_default_label($token_limit_primary_action_type)); ?>"
            />
        </div>

        <div
            class="aipkit_ai_forms_settings_row"
            data-aipkit-limit-action-dependent-for="primary"
            data-aipkit-limit-action-field="url"
            <?php if (!$primary_action_show_url) : ?>hidden<?php endif; ?>
        >
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_url')); ?>">
                <?php esc_html_e('Primary URL', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Destination for the primary custom URL.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="url"
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_primary_action_url')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_primary_action_url')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                value="<?php echo esc_attr($token_limit_primary_action_url); ?>"
                placeholder="<?php esc_attr_e('https://example.com/account', 'gpt3-ai-content-generator'); ?>"
            />
        </div>

        <div class="aipkit_ai_forms_settings_row" data-aipkit-limit-action-row="secondary">
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_type')); ?>">
                <?php esc_html_e('Secondary button', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Optional quota action.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <select
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_type')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_secondary_action_type')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_autosave_trigger"
            >
                <?php foreach ($token_limit_action_options as $action_value => $action_label) : ?>
                    <option
                        value="<?php echo esc_attr($action_value); ?>"
                        data-default-label="<?php echo esc_attr($aipkit_token_limits_default_label($action_value)); ?>"
                        <?php selected($token_limit_secondary_action_type, $action_value); ?>
                    >
                        <?php echo esc_html($action_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div
            class="aipkit_ai_forms_settings_row"
            data-aipkit-limit-action-dependent-for="secondary"
            data-aipkit-limit-action-field="label"
            <?php if (!$secondary_action_show_label) : ?>hidden<?php endif; ?>
        >
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_label')); ?>">
                <?php esc_html_e('Secondary label', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Text shown on the secondary button.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_label')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_secondary_action_label')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                value="<?php echo esc_attr($token_limit_secondary_action_label); ?>"
                placeholder="<?php echo esc_attr($aipkit_token_limits_default_label($token_limit_secondary_action_type)); ?>"
            />
        </div>

        <div
            class="aipkit_ai_forms_settings_row"
            data-aipkit-limit-action-dependent-for="secondary"
            data-aipkit-limit-action-field="url"
            <?php if (!$secondary_action_show_url) : ?>hidden<?php endif; ?>
        >
            <label class="aipkit_form-label" for="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_url')); ?>">
                <?php esc_html_e('Secondary URL', 'gpt3-ai-content-generator'); ?>
                <span class="aipkit_form-label-helper"><?php esc_html_e('Secondary link URL.', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <input
                type="url"
                id="<?php echo esc_attr($aipkit_token_limits_id('limit_secondary_action_url')); ?>"
                name="<?php echo esc_attr($aipkit_token_limits_name('limit_secondary_action_url')); ?>"
                class="aipkit_form-input aipkit_ai_forms_settings_control aipkit_ai_forms_settings_control--wide aipkit_autosave_trigger"
                value="<?php echo esc_attr($token_limit_secondary_action_url); ?>"
                placeholder="<?php esc_attr_e('https://example.com/support', 'gpt3-ai-content-generator'); ?>"
            />
        </div>
    </div>
</section>
