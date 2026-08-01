<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aipkit_popup_default_icon_url = esc_url((defined('WPAICG_PLUGIN_URL') ? WPAICG_PLUGIN_URL : plugin_dir_url(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) . 'public/images/icon.svg');
$aipkit_validate_url = static function ($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return false;
    }
    if (function_exists('wp_http_validate_url')) {
        return (bool) wp_http_validate_url($url);
    }
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
};
$aipkit_popup_custom_icon_url_value = '';
if ($popup_icon_type === 'custom') {
    $popup_icon_candidate = trim((string)$popup_icon_value);
    if ($aipkit_validate_url($popup_icon_candidate)) {
        $aipkit_popup_custom_icon_url_value = $popup_icon_candidate;
    } else {
        $aipkit_popup_custom_icon_url_value = $aipkit_popup_default_icon_url;
    }
}

$aipkit_header_avatar_custom_url_value = '';
if ($saved_header_avatar_type === 'custom') {
    $header_avatar_candidate = trim((string)$saved_header_avatar_url);
    if ($header_avatar_candidate === '' && !empty($saved_header_avatar_value)) {
        $header_avatar_candidate = trim((string)$saved_header_avatar_value);
    }
    if ($aipkit_validate_url($header_avatar_candidate)) {
        $aipkit_header_avatar_custom_url_value = $header_avatar_candidate;
    } else {
        $aipkit_header_avatar_custom_url_value = $aipkit_popup_default_icon_url;
    }
}

$aipkit_popup_auto_open_options = [
    0 => __('Off', 'gpt3-ai-content-generator'),
    3 => __('3 sec', 'gpt3-ai-content-generator'),
    5 => __('5 sec', 'gpt3-ai-content-generator'),
    10 => __('10 sec', 'gpt3-ai-content-generator'),
    15 => __('15 sec', 'gpt3-ai-content-generator'),
    30 => __('30 sec', 'gpt3-ai-content-generator'),
    60 => __('60 sec', 'gpt3-ai-content-generator'),
];
$aipkit_current_popup_delay = absint($popup_delay);
?>
<div class="aipkit_interface_section aipkit_interface_section--popup">
    <div class="aipkit_interface_popup_settings" id="aipkit_builder_popup_settings_panel">
        <div class="aipkit_popup_visual_state" hidden aria-hidden="true">
            <?php foreach ($popup_icons as $icon_key => $svg_html) : ?>
                <?php
                $radio_id = 'aipkit_bot_' . absint($bot_id) . '_popup_icon_deploy_' . sanitize_key($icon_key);
                $icon_checked = ($popup_icon_type !== 'custom' && $popup_icon_value === $icon_key);
                ?>
                <label class="aipkit_option_card" for="<?php echo esc_attr($radio_id); ?>">
                    <input
                        type="radio"
                        id="<?php echo esc_attr($radio_id); ?>"
                        name="popup_icon_default"
                        value="<?php echo esc_attr($icon_key); ?>"
                        <?php checked($icon_checked); ?>
                    />
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $svg_html;
                    ?>
                </label>
            <?php endforeach; ?>
            <?php $popup_custom_radio_id = 'aipkit_bot_' . absint($bot_id) . '_popup_icon_deploy_custom'; ?>
            <label class="aipkit_option_card aipkit_option_card--custom-url" for="<?php echo esc_attr($popup_custom_radio_id); ?>">
                <input
                    type="radio"
                    id="<?php echo esc_attr($popup_custom_radio_id); ?>"
                    name="popup_icon_default"
                    value="__custom__"
                    <?php checked($popup_icon_type, 'custom'); ?>
                />
                <span><?php esc_html_e('Custom', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <div class="aipkit_popup_icon_custom_input_container" <?php echo ($popup_icon_type === 'custom') ? '' : 'hidden'; ?>>
                <input
                    type="url"
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_custom_url_deploy"
                    name="popup_icon_custom_url"
                    data-default-url="<?php echo esc_url($aipkit_popup_default_icon_url); ?>"
                    value="<?php echo ($popup_icon_type === 'custom') ? esc_url($aipkit_popup_custom_icon_url_value) : ''; ?>"
                />
            </div>
            <?php foreach ($popup_icons as $icon_key => $svg_html) : ?>
                <?php
                $radio_id = 'aipkit_bot_' . absint($bot_id) . '_header_avatar_icon_deploy_' . sanitize_key($icon_key);
                $icon_checked = ($saved_header_avatar_type !== 'custom' && $saved_header_avatar_value === $icon_key);
                ?>
                <label class="aipkit_option_card" for="<?php echo esc_attr($radio_id); ?>">
                    <input
                        type="radio"
                        id="<?php echo esc_attr($radio_id); ?>"
                        name="header_avatar_default"
                        value="<?php echo esc_attr($icon_key); ?>"
                        <?php checked($icon_checked); ?>
                    />
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $svg_html;
                    ?>
                </label>
            <?php endforeach; ?>
            <?php $header_custom_radio_id = 'aipkit_bot_' . absint($bot_id) . '_header_avatar_icon_deploy_custom'; ?>
            <label class="aipkit_option_card aipkit_option_card--custom-url" for="<?php echo esc_attr($header_custom_radio_id); ?>">
                <input
                    type="radio"
                    id="<?php echo esc_attr($header_custom_radio_id); ?>"
                    name="header_avatar_default"
                    value="__custom__"
                    <?php checked($saved_header_avatar_type, 'custom'); ?>
                />
                <span><?php esc_html_e('Custom', 'gpt3-ai-content-generator'); ?></span>
            </label>
            <div class="aipkit_header_avatar_custom_input_container" <?php echo ($saved_header_avatar_type === 'custom') ? '' : 'hidden'; ?>>
                <input
                    type="url"
                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_header_avatar_url_deploy"
                    name="header_avatar_url"
                    data-default-url="<?php echo esc_url($aipkit_popup_default_icon_url); ?>"
                    value="<?php echo ($saved_header_avatar_type === 'custom') ? esc_url($aipkit_header_avatar_custom_url_value) : ''; ?>"
                />
            </div>
            <select
                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_enabled"
                name="popup_label_enabled"
                class="aipkit_popover_option_select aipkit_popover_option_input--framed aipkit_popup_hint_toggle_switch aipkit_popup_hint_toggle_select"
            >
                <option value="1" <?php selected($popup_label_enabled, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                <option value="0" <?php selected($popup_label_enabled, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
            </select>
        </div>
        <div class="aipkit_interface_feature_rows aipkit_display_settings_rows aipkit_display_popup_rows">
            <div
                class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_display_settings_row--launcher"
                data-aipkit-inline-settings-row
                data-aipkit-static-inline-settings-row
            >
                <div class="aipkit_interface_feature_label">
                    <span class="aipkit_display_settings_icon" aria-hidden="true">
                        <span class="dashicons dashicons-admin-comments"></span>
                    </span>
                    <span class="aipkit_interface_feature_text">
                        <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                            <?php esc_html_e('Popup', 'gpt3-ai-content-generator'); ?>
                        </span>
                        <span class="aipkit_interface_feature_hint">
                            <?php esc_html_e('Set position, size, and auto-open behavior.', 'gpt3-ai-content-generator'); ?>
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
                        aria-controls="aipkit_display_launcher_panel"
                    >
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    </button>
                </div>
                <div
                    id="aipkit_display_launcher_panel"
                    class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel"
                    hidden
                >
                    <div class="aipkit_interface_popup_grid aipkit_display_fields_grid aipkit_display_fields_grid--launcher">
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_position">
                                    <?php esc_html_e('Position', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_position"
                                    name="popup_position"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="bottom-right" <?php selected($popup_position, 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="bottom-left" <?php selected($popup_position, 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="top-right" <?php selected($popup_position, 'top-right'); ?>><?php esc_html_e('Top Right', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="top-left" <?php selected($popup_position, 'top-left'); ?>><?php esc_html_e('Top Left', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_style">
                                    <?php esc_html_e('Icon style', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_style"
                                    name="popup_icon_style"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="circle" <?php selected($popup_icon_style, 'circle'); ?>><?php esc_html_e('Circle', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="square" <?php selected($popup_icon_style, 'square'); ?>><?php esc_html_e('Square', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="none" <?php selected($popup_icon_style, 'none'); ?>><?php esc_html_e('Original', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_size">
                                    <?php esc_html_e('Size', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_size"
                                    name="popup_icon_size"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="small" <?php selected($popup_icon_size, 'small'); ?>><?php esc_html_e('Small', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="medium" <?php selected($popup_icon_size, 'medium'); ?>><?php esc_html_e('Medium', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="large" <?php selected($popup_icon_size, 'large'); ?>><?php esc_html_e('Large', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="xlarge" <?php selected($popup_icon_size, 'xlarge'); ?>><?php esc_html_e('X-Large', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_delay">
                                    <?php esc_html_e('Auto-open', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_delay"
                                    name="popup_delay"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <?php if (!array_key_exists($aipkit_current_popup_delay, $aipkit_popup_auto_open_options)) : ?>
                                        <option value="<?php echo esc_attr($aipkit_current_popup_delay); ?>" selected="selected">
                                            <?php
                                            printf(
                                                /* translators: %d: number of seconds */
                                                esc_html__('%d sec', 'gpt3-ai-content-generator'),
                                                absint($aipkit_current_popup_delay)
                                            );
                                            ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach ($aipkit_popup_auto_open_options as $delay_value => $delay_label) : ?>
                                        <option value="<?php echo esc_attr((string) $delay_value); ?>" <?php selected($aipkit_current_popup_delay, $delay_value); ?>>
                                            <?php echo esc_html($delay_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_header_online_text">
                                    <?php esc_html_e('Online text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="text"
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_header_online_text"
                                    name="header_online_text"
                                    class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                    value="<?php echo esc_attr($saved_header_online_text); ?>"
                                    placeholder="<?php esc_attr_e('Online', 'gpt3-ai-content-generator'); ?>"
                                    autocomplete="off"
                                    data-lpignore="true"
                                    data-1p-ignore="true"
                                    data-form-type="other"
                                />
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup aipkit_interface_cell--custom-icon">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_custom_url_visible">
                                    <?php esc_html_e('Custom icon URL', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="url"
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_icon_custom_url_visible"
                                    class="aipkit_popover_option_input aipkit_popover_option_input--framed aipkit_popup_icon_custom_url_display"
                                    data-aipkit-popup-icon-url-display
                                    value="<?php echo ($popup_icon_type === 'custom') ? esc_url($aipkit_popup_custom_icon_url_value) : ''; ?>"
                                    placeholder="<?php esc_attr_e('https://example.com/icon.png', 'gpt3-ai-content-generator'); ?>"
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
            <div
                class="aipkit_interface_feature_row aipkit_interface_feature_row--expandable aipkit_display_settings_row aipkit_display_settings_row--welcome aipkit_popup_hint_behavior_row<?php echo ($popup_label_enabled === '1') ? '' : ' is-disabled'; ?>"
                data-aipkit-inline-settings-row
                data-aipkit-static-inline-settings-row
            >
                <div class="aipkit_interface_feature_label">
                    <span class="aipkit_display_settings_icon" aria-hidden="true">
                        <span class="dashicons dashicons-megaphone"></span>
                    </span>
                    <span class="aipkit_interface_feature_text">
                        <span class="aipkit_interface_feature_title aipkit_popover_option_label">
                            <?php esc_html_e('Welcome message', 'gpt3-ai-content-generator'); ?>
                        </span>
                        <span class="aipkit_interface_feature_hint aipkit_popup_hint_enabled_copy" <?php echo ($popup_label_enabled === '1') ? '' : 'hidden'; ?>>
                            <?php esc_html_e('Control when the message appears.', 'gpt3-ai-content-generator'); ?>
                        </span>
                        <span class="aipkit_interface_feature_hint aipkit_popup_hint_disabled_copy" <?php echo ($popup_label_enabled === '1') ? 'hidden' : ''; ?>>
                            <?php esc_html_e('Turn it on in the main screen first.', 'gpt3-ai-content-generator'); ?>
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
                        aria-controls="aipkit_display_welcome_panel"
                        <?php disabled($popup_label_enabled !== '1'); ?>
                    >
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    </button>
                </div>
                <div
                    id="aipkit_display_welcome_panel"
                    class="aipkit_interface_feature_inline_panel aipkit_display_inline_panel"
                    hidden
                >
                    <div class="aipkit_interface_popup_grid aipkit_display_fields_grid aipkit_display_fields_grid--welcome aipkit_popup_hint_conditional_row" <?php echo ($popup_label_enabled === '1') ? '' : 'hidden'; ?>>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_mode_deploy">
                                    <?php esc_html_e('When to show', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_mode_deploy"
                                    name="popup_label_mode"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="on_delay" <?php selected($popup_label_mode, 'on_delay'); ?>><?php esc_html_e('After delay', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="always" <?php selected($popup_label_mode, 'always'); ?>><?php esc_html_e('Immediately', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="until_open" <?php selected($popup_label_mode, 'until_open'); ?>><?php esc_html_e('Until opened', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="until_dismissed" <?php selected($popup_label_mode, 'until_dismissed'); ?>><?php esc_html_e('Until closed', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_frequency_deploy">
                                    <?php esc_html_e('Repeat', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_frequency_deploy"
                                    name="popup_label_frequency"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="once_per_visitor" <?php selected($popup_label_frequency, 'once_per_visitor'); ?>><?php esc_html_e('Once per visitor', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="once_per_session" <?php selected($popup_label_frequency, 'once_per_session'); ?>><?php esc_html_e('Once per session', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="always" <?php selected($popup_label_frequency, 'always'); ?>><?php esc_html_e('Every time', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_size_deploy">
                                    <?php esc_html_e('Size', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_size_deploy"
                                    name="popup_label_size"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="small" <?php selected($popup_label_size, 'small'); ?>><?php esc_html_e('Small', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="medium" <?php selected($popup_label_size, 'medium'); ?>><?php esc_html_e('Medium', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="large" <?php selected($popup_label_size, 'large'); ?>><?php esc_html_e('Large', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="xlarge" <?php selected($popup_label_size, 'xlarge'); ?>><?php esc_html_e('Extra large', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_delay_seconds_deploy">
                                    <?php esc_html_e('Delay (sec)', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="number"
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_delay_seconds_deploy"
                                    name="popup_label_delay_seconds"
                                    class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                    min="0"
                                    step="1"
                                    value="<?php echo esc_attr($popup_label_delay_seconds); ?>"
                                >
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_auto_hide_seconds_deploy">
                                    <?php esc_html_e('Auto-hide (sec)', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="number"
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_auto_hide_seconds_deploy"
                                    name="popup_label_auto_hide_seconds"
                                    class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                    min="0"
                                    step="1"
                                    value="<?php echo esc_attr($popup_label_auto_hide_seconds); ?>"
                                >
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_dismissible_deploy">
                                    <?php esc_html_e('Closable', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_dismissible_deploy"
                                    name="popup_label_dismissible"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="1" <?php selected($popup_label_dismissible, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="0" <?php selected($popup_label_dismissible, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_show_on_desktop_deploy">
                                    <?php esc_html_e('Desktop', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_show_on_desktop_deploy"
                                    name="popup_label_show_on_desktop"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="1" <?php selected($popup_label_show_on_desktop, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="0" <?php selected($popup_label_show_on_desktop, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_show_on_mobile_deploy">
                                    <?php esc_html_e('Mobile', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <select
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_show_on_mobile_deploy"
                                    name="popup_label_show_on_mobile"
                                    class="aipkit_popover_option_select aipkit_popover_option_input--framed"
                                >
                                    <option value="1" <?php selected($popup_label_show_on_mobile, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="0" <?php selected($popup_label_show_on_mobile, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="aipkit_popover_option_row aipkit_interface_cell aipkit_interface_cell--popup">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label aipkit_interface_popup_inline_label" for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_version_deploy">
                                    <?php esc_html_e('Version', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="text"
                                    id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_popup_label_version_deploy"
                                    name="popup_label_version"
                                    class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                    value="<?php echo esc_attr($popup_label_version); ?>"
                                    placeholder="v1"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
