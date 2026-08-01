<?php
/**
 * Partial: Chatbot Custom Theme Flyout (Row-based)
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

use WPAICG\Chat\Storage\BotSettingsManager;

$custom_theme_defaults = BotSettingsManager::get_custom_theme_defaults();

$get_cts_val = function ($key) use ($bot_settings, $custom_theme_defaults) {
    $custom_settings = $bot_settings['custom_theme_settings'] ?? [];
    return $custom_settings[$key] ?? ($custom_theme_defaults[$key] ?? '');
};

$esc_cts_val_attr = function ($key) use ($get_cts_val) {
    return esc_attr($get_cts_val($key));
};

$font_families = [
    'System' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
    'Arial' => 'Arial, Helvetica, sans-serif',
    'Verdana' => 'Verdana, Geneva, sans-serif',
    'Tahoma' => 'Tahoma, Geneva, sans-serif',
    'Trebuchet MS' => '"Trebuchet MS", Helvetica, sans-serif',
    '"Times New Roman", Times, serif',
    'Georgia' => 'Georgia, serif',
    'Garamond' => 'Garamond, serif',
    '"Courier New", Courier, monospace',
    '"Brush Script MT", cursive',
];

?>

<div
    class="aipkit_popover_custom_theme_flyout"
    id="aipkit_custom_theme_flyout"
    aria-hidden="true"
    role="dialog"
    aria-label="<?php esc_attr_e('Custom theme', 'gpt3-ai-content-generator'); ?>"
>
    <div class="aipkit_popover_flyout_header">
        <span class="aipkit_popover_flyout_title">
            <?php esc_html_e('Custom theme', 'gpt3-ai-content-generator'); ?>
        </span>
        <button
            type="button"
            class="aipkit_popover_flyout_close aipkit_custom_theme_flyout_close"
            aria-label="<?php esc_attr_e('Close', 'gpt3-ai-content-generator'); ?>"
        >
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="aipkit_popover_flyout_body aipkit_popover_custom_theme_body">
        <div
            class="aipkit_custom_theme_settings_container"
            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_custom_theme_settings_container"
            data-defaults="<?php echo esc_attr(wp_json_encode($custom_theme_defaults)); ?>"
        >
            <div class="aipkit_popover_options_list">
                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_secondary_color_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Primary color', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="color"
                            id="cts_secondary_color_<?php echo esc_attr($bot_id); ?>"
                            name="custom_theme_settings[secondary_color]"
                            class="aipkit_form-input aipkit_color_picker_input"
                            value="<?php echo $esc_cts_val_attr('secondary_color'); // phpcs:ignore ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_primary_color_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Secondary color', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="color"
                            id="cts_primary_color_<?php echo esc_attr($bot_id); ?>"
                            name="custom_theme_settings[primary_color]"
                            class="aipkit_form-input aipkit_color_picker_input"
                            value="<?php echo $esc_cts_val_attr('primary_color'); // phpcs:ignore ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_bubble_border_radius_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Bubble radius (px)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_bubble_border_radius_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[bubble_border_radius]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="0"
                                max="50"
                                step="1"
                                value="<?php echo $esc_cts_val_attr('bubble_border_radius'); // phpcs:ignore ?>"
                            />
                            <span id="cts_bubble_border_radius_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_font_family_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Font family', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="cts_font_family_<?php echo esc_attr($bot_id); ?>"
                            name="custom_theme_settings[font_family]"
                            class="aipkit_popover_option_select"
                        >
                            <?php foreach ($font_families as $name => $stack) : ?>
                                <option value="<?php echo esc_attr($stack); ?>" <?php selected($get_cts_val('font_family'), $stack); ?>>
                                    <?php echo esc_html(is_string($name) ? $name : $stack); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="inherit" <?php selected($get_cts_val('font_family'), 'inherit'); ?>>
                                <?php esc_html_e('Inherit from page', 'gpt3-ai-content-generator'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_container_max_width_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Inline max width (px)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_container_max_width_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[container_max_width]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="200"
                                max="1200"
                                step="10"
                                value="<?php echo esc_attr($esc_cts_val_attr('container_max_width')); ?>"
                            />
                            <span id="cts_container_max_width_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_popup_width_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Popup width (px)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_popup_width_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[popup_width]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="200"
                                max="1000"
                                step="10"
                                value="<?php echo esc_attr($esc_cts_val_attr('popup_width')); ?>"
                            />
                            <span id="cts_popup_width_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_container_height_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Initial height (px)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_container_height_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[container_height]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="100"
                                max="1000"
                                step="10"
                                value="<?php echo esc_attr($esc_cts_val_attr('container_height')); ?>"
                            />
                            <span id="cts_container_height_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_container_min_height_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Min height (px)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_container_min_height_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[container_min_height]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="50"
                                max="800"
                                step="10"
                                value="<?php echo esc_attr($esc_cts_val_attr('container_min_height')); ?>"
                            />
                            <span id="cts_container_min_height_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="cts_container_max_height_<?php echo esc_attr($bot_id); ?>">
                            <?php esc_html_e('Max height (%)', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="cts_container_max_height_<?php echo esc_attr($bot_id); ?>"
                                name="custom_theme_settings[container_max_height]"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="10"
                                max="100"
                                step="1"
                                data-suffix="%"
                                value="<?php echo esc_attr($esc_cts_val_attr('container_max_height')); ?>"
                            />
                            <span id="cts_container_max_height_<?php echo esc_attr($bot_id); ?>_value" class="aipkit_popover_param_value"></span>
                        </div>
                    </div>
                </div>

                <details class="aipkit_popover_option_details" hidden>
                    <summary class="aipkit_popover_option_row aipkit_popover_option_row--section">
                        <div class="aipkit_popover_option_main">
                            <span class="aipkit_popover_option_section_title">
                                <?php esc_html_e('Advanced overrides', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>
                    </summary>
                    <div class="aipkit_popover_options_list">
                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <span class="aipkit_popover_option_helper">
                                    <?php esc_html_e('Override the palette for individual areas when needed.', 'gpt3-ai-content-generator'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <span class="aipkit_popover_option_label">
                                    <?php esc_html_e('Auto contrast text', 'gpt3-ai-content-generator'); ?>
                                </span>
                                <label class="aipkit_switch">
                                    <input
                                        type="checkbox"
                                        id="cts_auto_text_contrast_<?php echo esc_attr($bot_id); ?>"
                                        name="custom_theme_settings[auto_text_contrast]"
                                        class="aipkit_toggle_switch"
                                        value="1"
                                        <?php checked($get_cts_val('auto_text_contrast'), '1'); ?>
                                    >
                                    <span class="aipkit_switch_slider"></span>
                                </label>
                            </div>
                            <span class="aipkit_popover_option_helper">
                                <?php esc_html_e('Automatically adjust text on colored buttons and bubbles.', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_bot_bubble_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Bot bubble', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_bot_bubble_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[bot_bubble_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('bot_bubble_bg_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_user_bubble_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('User bubble', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_user_bubble_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[user_bubble_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('user_bubble_bg_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_messages_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Messages area', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_messages_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[messages_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('messages_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_container_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Container', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_container_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[container_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('container_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_container_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Container text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_container_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[container_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('container_text_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_container_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Container border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_container_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[container_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('container_border_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_header_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Header', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_header_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[header_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('header_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_header_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Header icons', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_header_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[header_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('header_text_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_header_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Header border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_header_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[header_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('header_border_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_bot_bubble_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Bot text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_bot_bubble_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[bot_bubble_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('bot_bubble_text_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_user_bubble_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('User text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_user_bubble_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[user_bubble_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('user_bubble_text_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_footer_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Footer', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_footer_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[footer_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('footer_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_footer_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Footer text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_footer_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[footer_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('footer_text_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_footer_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Footer border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_footer_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[footer_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('footer_border_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_input_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Input bar', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_input_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[input_area_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('input_area_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_input_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Input text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_input_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[input_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('input_text_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_input_wrapper_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Textarea', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_input_wrapper_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[input_wrapper_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('input_wrapper_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_input_wrapper_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Textarea border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_input_wrapper_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[input_wrapper_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('input_wrapper_border_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_send_button_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Send background', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_send_button_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[send_button_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('send_button_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_send_button_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Send icon', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_send_button_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[send_button_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('send_button_text_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_action_button_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Action background', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_action_button_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[action_button_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('action_button_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_action_button_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Action icon/text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_action_button_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[action_button_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('action_button_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_action_button_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Action border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_action_button_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[action_button_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('action_button_border_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_action_button_hover_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Action hover bg', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_action_button_hover_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[action_button_hover_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('action_button_hover_bg_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_action_button_hover_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Action hover text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_action_button_hover_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[action_button_hover_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo $esc_cts_val_attr('action_button_hover_color'); // phpcs:ignore ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_sidebar_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Sidebar background', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_sidebar_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[sidebar_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('sidebar_bg_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_sidebar_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Sidebar text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_sidebar_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[sidebar_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('sidebar_text_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_sidebar_border_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Sidebar border', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_sidebar_border_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[sidebar_border_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('sidebar_border_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_sidebar_active_bg_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Sidebar active bg', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_sidebar_active_bg_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[sidebar_active_bg_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('sidebar_active_bg_color')); ?>"
                                />
                            </div>
                        </div>

                        <div class="aipkit_popover_option_row">
                            <div class="aipkit_popover_option_main">
                                <label class="aipkit_popover_option_label" for="cts_sidebar_active_text_color_<?php echo esc_attr($bot_id); ?>">
                                    <?php esc_html_e('Sidebar active text', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <input
                                    type="color"
                                    id="cts_sidebar_active_text_color_<?php echo esc_attr($bot_id); ?>"
                                    name="custom_theme_settings[sidebar_active_text_color]"
                                    class="aipkit_form-input aipkit_color_picker_input"
                                    value="<?php echo esc_attr($esc_cts_val_attr('sidebar_active_text_color')); ?>"
                                />
                            </div>
                        </div>
                    </div>
                </details>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <span class="aipkit_popover_option_label">
                            <?php esc_html_e('Reset theme', 'gpt3-ai-content-generator'); ?>
                        </span>
                        <div class="aipkit_popover_option_actions">
                            <button
                                type="button"
                                id="aipkit_reset_custom_theme_btn_<?php echo esc_attr($bot_id); ?>"
                                class="aipkit_popover_option_btn aipkit_reset_custom_theme_btn"
                                data-bot-id="<?php echo esc_attr($bot_id); ?>"
                                title="<?php esc_attr_e('Reset all custom theme settings to their defaults.', 'gpt3-ai-content-generator'); ?>"
                            >
                                <?php esc_html_e('Reset', 'gpt3-ai-content-generator'); ?>
                            </button>
                            <span id="aipkit_reset_theme_status_<?php echo esc_attr($bot_id); ?>" class="aipkit_popover_status_inline"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="aipkit_popover_flyout_footer">
        <span class="aipkit_popover_flyout_footer_text">
            <?php esc_html_e('Need help? Read the docs.', 'gpt3-ai-content-generator'); ?>
        </span>
        <a
            class="aipkit_popover_flyout_footer_link"
            href="<?php echo esc_url('https://docs.aipower.org/chatbots#theme'); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <?php esc_html_e('Documentation', 'gpt3-ai-content-generator'); ?>
        </a>
    </div>
</div>
