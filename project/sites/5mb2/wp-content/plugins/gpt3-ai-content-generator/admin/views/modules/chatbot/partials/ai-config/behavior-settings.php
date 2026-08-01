<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bot_id = $initial_active_bot_id;
?>
<div class="aipkit_popover_options_list aipkit_behavior_compact_options aipkit_behavior_compact_options--general">
    <div class="aipkit_chatbot_settings_section_heading">
        <?php esc_html_e('Conversation', 'gpt3-ai-content-generator'); ?>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--chat-options"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-admin-comments"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Chat options', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_interface_feature_hint">
                    <?php esc_html_e('Choose what visitors can do in chat.', 'gpt3-ai-content-generator'); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_chat_options_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_chat_options_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel"
            hidden
        >
            <?php include __DIR__ . '/interface-feature-settings.php'; ?>
        </div>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--knowledge"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-search"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Knowledge', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_interface_feature_hint">
                    <?php esc_html_e('Choose how this chatbot uses your content.', 'gpt3-ai-content-generator'); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_knowledge_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_knowledge_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel"
            hidden
        >
            <div class="aipkit_general_knowledge_section aipkit_settings_panel_body" data-aipkit-settings-panel="context">
                <?php include __DIR__ . '/context-settings.php'; ?>
            </div>
        </div>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--capabilities"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-admin-tools"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Capabilities', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_interface_feature_hint">
                    <?php esc_html_e('Enable file, web, image, and voice features.', 'gpt3-ai-content-generator'); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_capabilities_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_capabilities_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel"
            hidden
        >
            <div class="aipkit_general_capabilities_section aipkit_settings_panel_body" data-aipkit-settings-panel="tools">
                <?php include __DIR__ . '/tools-settings.php'; ?>
            </div>
        </div>
    </div>
    <div class="aipkit_chatbot_settings_section_heading">
        <?php esc_html_e('AI behavior', 'gpt3-ai-content-generator'); ?>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--model"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-admin-generic"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_interface_feature_hint">
                    <?php esc_html_e('Adjust response style, memory, and reasoning.', 'gpt3-ai-content-generator'); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_model_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_model_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel aipkit_general_model_section_panel"
            hidden
        >
            <div class="aipkit_builder_field aipkit_chatbot_response_settings">
                <?php include __DIR__ . '/model-settings.php'; ?>
            </div>
        </div>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--limits"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-chart-bar"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Limits', 'gpt3-ai-content-generator'); ?>
                </span>
                <span
                    class="aipkit_interface_feature_hint"
                    data-aipkit-limits-section-summary
                    data-default-summary="<?php echo esc_attr($limits_summary_fallback ?? __('Set visitor message limits.', 'gpt3-ai-content-generator')); ?>"
                >
                    <?php echo esc_html($limits_summary_text ?? __('Set visitor message limits.', 'gpt3-ai-content-generator')); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_limits_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_limits_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel aipkit_general_limits_section_panel"
            hidden
        >
            <div class="aipkit_general_limits_section aipkit_settings_panel_body" data-aipkit-settings-panel="limits">
                <?php include __DIR__ . '/limits-settings.php'; ?>
            </div>
        </div>
    </div>
    <div class="aipkit_chatbot_settings_section_heading">
        <?php esc_html_e('Automations', 'gpt3-ai-content-generator'); ?>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--apps"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-share"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Apps', 'gpt3-ai-content-generator'); ?>
                    <?php if (!$is_pro_plan) : ?>
                        <span class="aipkit_general_settings_section_badge aipkit_paid_feature_badge aipkit_pro_badge"><?php esc_html_e('Pro', 'gpt3-ai-content-generator'); ?></span>
                    <?php endif; ?>
                </span>
                <span
                    class="aipkit_interface_feature_hint"
                    data-aipkit-connected-apps-section-summary
                    data-default-summary="<?php esc_attr_e('Send chat activity to your tools.', 'gpt3-ai-content-generator'); ?>"
                >
                    <?php echo esc_html($connected_apps_summary_text ?: __('Send chat activity to your tools.', 'gpt3-ai-content-generator')); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_apps_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_apps_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel aipkit_general_apps_section_panel"
            hidden
        >
            <?php include __DIR__ . '/connected-apps-settings.php'; ?>
        </div>
    </div>
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_general_settings_section_row aipkit_general_settings_section_row--rules"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-controls-repeat"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Rules', 'gpt3-ai-content-generator'); ?>
                    <?php if (!$is_pro_plan) : ?>
                        <span class="aipkit_general_settings_section_badge aipkit_paid_feature_badge aipkit_pro_badge"><?php esc_html_e('Pro', 'gpt3-ai-content-generator'); ?></span>
                    <?php endif; ?>
                </span>
                <span
                    class="aipkit_interface_feature_hint"
                    data-aipkit-rules-section-summary
                    data-default-summary="<?php esc_attr_e('Automate replies and actions.', 'gpt3-ai-content-generator'); ?>"
                >
                    <?php echo esc_html($rules_summary_text ?: __('Automate replies and actions.', 'gpt3-ai-content-generator')); ?>
                </span>
            </span>
        </div>
        <div class="aipkit_interface_feature_action">
            <button
                type="button"
                class="aipkit_popover_option_btn aipkit_display_settings_toggle aipkit_interface_feature_expand_btn"
                data-aipkit-inline-settings-toggle
                data-aipkit-static-inline-settings-toggle
                aria-expanded="false"
                aria-controls="aipkit_general_rules_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_general_rules_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel aipkit_general_settings_section_panel aipkit_general_rules_section_panel"
            hidden
        >
            <button
                type="button"
                class="aipkit_chatbot_settings_action_btn aipkit_general_settings_manage_btn aipkit_builder_sheet_trigger"
                data-sheet-title="<?php esc_attr_e('Rules', 'gpt3-ai-content-generator'); ?>"
                data-sheet-description="<?php esc_attr_e('Create and manage rule-based automations for this chatbot.', 'gpt3-ai-content-generator'); ?>"
                data-sheet-content="triggers"
            >
                <?php esc_html_e('Manage rules', 'gpt3-ai-content-generator'); ?>
            </button>
        </div>
    </div>
</div>
