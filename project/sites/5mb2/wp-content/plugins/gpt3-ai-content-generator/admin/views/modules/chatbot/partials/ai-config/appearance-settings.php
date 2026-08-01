<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bot_id = $initial_active_bot_id;
$bot_settings = $active_bot_settings;
$bot_name_value = isset($active_bot_name_value)
    ? (string) $active_bot_name_value
    : (($active_bot_post && isset($active_bot_post->post_title)) ? (string) $active_bot_post->post_title : '');
$saved_greeting_value = isset($saved_greeting)
    ? (string) $saved_greeting
    : (($active_bot_settings['greeting'] ?? ''));
$saved_subgreeting_value = isset($saved_subgreeting)
    ? (string) $saved_subgreeting
    : (($active_bot_settings['subgreeting'] ?? ''));
$saved_footer_text = $bot_settings['footer_text'] ?? '';
$saved_placeholder = $bot_settings['input_placeholder'] ?? __('Type your message...', 'gpt3-ai-content-generator');
$custom_typing_text = $bot_settings['custom_typing_text'] ?? '';
$retrieving_context_text = $bot_settings['retrieving_context_text'] ?? '';
?>
<div class="aipkit_popover_options_list aipkit_interface_options aipkit_display_settings_rows">
    <div
        class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_display_settings_row--chat-text"
        data-aipkit-inline-settings-row
        data-aipkit-static-inline-settings-row
    >
        <div class="aipkit_interface_feature_label">
            <span class="aipkit_display_settings_icon" aria-hidden="true">
                <span class="dashicons dashicons-editor-textcolor"></span>
            </span>
            <span class="aipkit_interface_feature_text">
                <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                    <?php esc_html_e('Chat text', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_interface_feature_hint">
                    <?php esc_html_e('Edit labels and messages visitors see.', 'gpt3-ai-content-generator'); ?>
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
                aria-controls="aipkit_display_chat_text_panel"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <div
            id="aipkit_display_chat_text_panel"
            class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel"
            hidden
        >
            <div class="aipkit_display_fields_grid aipkit_display_fields_grid--chat-text">
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text aipkit_interface_cell--identity aipkit_bot_name_group">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_name">
                            <?php esc_html_e('Chatbot name', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_name"
                            name="bot_name"
                            class="aipkit_form-input aipkit_bot_name_input aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($bot_name_value); ?>"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text aipkit_interface_cell--identity">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_greeting">
                            <?php esc_html_e('Greeting', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_greeting"
                            name="greeting"
                            class="aipkit_form-input aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($saved_greeting_value); ?>"
                            placeholder="<?php esc_attr_e('Hello there!', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text aipkit_interface_cell--identity">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_subgreeting">
                            <?php esc_html_e('Subgreeting', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_subgreeting"
                            name="subgreeting"
                            class="aipkit_form-input aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($saved_subgreeting_value); ?>"
                            placeholder="<?php esc_attr_e('How can I help you today?', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_input_placeholder">
                            <?php esc_html_e('Placeholder', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_input_placeholder"
                            name="input_placeholder"
                            class="aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($saved_placeholder); ?>"
                            placeholder="<?php esc_attr_e('Type your message...', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_footer_text">
                            <?php esc_html_e('Footer', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_footer_text"
                            name="footer_text"
                            class="aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($saved_footer_text); ?>"
                            placeholder="<?php esc_attr_e('Powered by AI', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_custom_typing_text">
                            <?php esc_html_e('Typing text', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_custom_typing_text"
                            name="custom_typing_text"
                            class="aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($custom_typing_text); ?>"
                            placeholder="<?php esc_attr_e('Thinking', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--text">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_retrieving_context_text">
                            <?php esc_html_e('Status text', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_retrieving_context_text"
                            name="retrieving_context_text"
                            class="aipkit_popover_option_input aipkit_popover_option_input--wide aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($retrieving_context_text); ?>"
                            placeholder="<?php esc_attr_e('Retrieving context...', 'gpt3-ai-content-generator'); ?>"
                            autocomplete="off"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            data-form-type="other"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
