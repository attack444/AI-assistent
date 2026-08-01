<?php

/**
 * Partial: AI Form Editor - Conversation UI Configuration
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$upgrade_url = isset($upgrade_url) && !empty($upgrade_url)
    ? $upgrade_url
    : admin_url('admin.php?page=wpaicg-pricing');
?>
<div class="aipkit_popover_options_list aipkit_ai_form_conversation_ui_list">
    <?php if (!empty($is_pro)) : ?>
        <div class="aipkit_popover_option_row">
            <div class="aipkit_popover_option_main">
                <span class="aipkit_popover_option_label">
                    <?php esc_html_e('Enable Multi-Step', 'gpt3-ai-content-generator'); ?>
                </span>
                <div class="aipkit_popover_option_actions">
                    <label class="aipkit_switch">
                        <input
                            type="checkbox"
                            id="aipkit_ai_form_conversation_enabled"
                            class="aipkit_ai_form_conversation_enabled_toggle"
                            value="1"
                        >
                        <span class="aipkit_switch_slider"></span>
                    </label>
                </div>
            </div>
        </div>
        <div class="aipkit_ai_form_conversation_ui_settings">
            <div class="aipkit_popover_option_row">
                <div class="aipkit_popover_option_main aipkit_popover_option_main--stacked">
                    <p class="aipkit_form-help"><?php esc_html_e('Use the chat action in each layout toolbar to configure its step.', 'gpt3-ai-content-generator'); ?></p>
                </div>
            </div>
            <div class="aipkit_popover_option_row">
                <div class="aipkit_popover_option_main aipkit_popover_option_main--stacked">
                    <label class="aipkit_popover_option_label" for="aipkit_ai_form_conversation_ui_preset"><?php esc_html_e('Display Progress', 'gpt3-ai-content-generator'); ?></label>
                    <select id="aipkit_ai_form_conversation_ui_preset" name="conversation_ui_preset" class="aipkit_popover_option_select">
                        <option value="full"><?php esc_html_e('Full', 'gpt3-ai-content-generator'); ?></option>
                        <option value="compact"><?php esc_html_e('Compact', 'gpt3-ai-content-generator'); ?></option>
                        <option value="minimal"><?php esc_html_e('Minimal', 'gpt3-ai-content-generator'); ?></option>
                        <option value="none"><?php esc_html_e('None', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                    <p class="aipkit_form-help"><?php esc_html_e('Choose how much step info to show.', 'gpt3-ai-content-generator'); ?></p>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="aipkit_popover_option_row aipkit_ai_form_multistep_upgrade_notice">
            <div class="aipkit_popover_option_main aipkit_popover_option_main--stacked aipkit_ai_form_multistep_upgrade_inner">
                <div class="aipkit_ai_form_multistep_upgrade_copy">
                    <span class="aipkit_popover_option_label"><?php esc_html_e('Turn forms into guided step-by-step flows.', 'gpt3-ai-content-generator'); ?></span>
                    <p class="aipkit_form-help"><?php esc_html_e('Show one step at a time with navigation and conditional branching.', 'gpt3-ai-content-generator'); ?></p>
                </div>
                <a id="aipkit_ai_form_multistep_upgrade_btn" class="aipkit_btn aipkit_ai_form_upgrade_btn aipkit_pro_upgrade_button" href="<?php echo esc_url($upgrade_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
