<?php
/**
 * AIPKit Image Generator Module - Admin View
 * REVISED: Uses the same workspace tab structure as AI Forms.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="aipkit_container aipkit_module_image_generator" id="aipkit_image_generator_container">
    <div class="aipkit_container-header">
        <div class="aipkit_container-header-left">
            <div class="aipkit_image_generator_header_copy">
                <div class="aipkit_image_generator_header_title_row">
                    <div class="aipkit_container-title"><?php esc_html_e('Image Generator', 'gpt3-ai-content-generator'); ?></div>
                    <span id="aipkit_image_generator_status" class="aipkit_training_status aipkit_global_status_area" aria-live="polite"></span>
                </div>
                <p class="aipkit_image_generator_header_hint"><?php esc_html_e('Generate, edit, and preview image experiences with shortcode and frontend controls.', 'gpt3-ai-content-generator'); ?></p>
            </div>
        </div>
    </div>

    <div class="aipkit_container-body">
        <div class="aipkit_image_generator_workspace" id="aipkit_image_generator_workspace">
            <div class="aipkit_ai_forms_workspace_bar">
                <div class="aipkit_ai_forms_workspace_tabs" role="tablist" aria-label="<?php esc_attr_e('Image Generator sections', 'gpt3-ai-content-generator'); ?>">
                    <button
                        type="button"
                        id="aipkit_image_generator_generator_tab"
                        class="aipkit_ai_forms_workspace_tab aipkit_image_generator_workspace_tab is-active"
                        role="tab"
                        aria-selected="true"
                        aria-controls="aipkit_image_generator_preview_panel"
                        data-aipkit-image-generator-tab="generator"
                    >
                        <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                        <?php esc_html_e('Image Generator', 'gpt3-ai-content-generator'); ?>
                    </button>
                    <button
                        type="button"
                        id="aipkit_image_generator_settings_tab"
                        class="aipkit_ai_forms_workspace_tab aipkit_image_generator_workspace_tab"
                        role="tab"
                        aria-selected="false"
                        aria-controls="aipkit_image_generator_settings_panel"
                        data-aipkit-image-generator-tab="settings"
                    >
                        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                        <?php esc_html_e('Settings', 'gpt3-ai-content-generator'); ?>
                    </button>
                </div>
                <div class="aipkit_ai_forms_workspace_tools aipkit_image_generator_workspace_tools is-active" data-aipkit-image-generator-tools="generator">
                    <div class="aipkit_tabs_module_controls">
                        <div class="aipkit_image_generator_top_bar">
                            <div class="aipkit_top_bar_left_group">
                                <div class="aipkit_shortcode_display_top_wrapper">
                                    <button
                                        type="button"
                                        id="aipkit_image_generator_shortcode_snippet"
                                        class="aipkit_image_generator_shortcode_snippet"
                                        data-shortcode="[aipkit_image_generator mode=&quot;both&quot; default_mode=&quot;generate&quot;]"
                                        title="<?php echo esc_attr('[aipkit_image_generator mode="both" default_mode="generate"]'); ?>"
                                        aria-label="<?php esc_attr_e('Click to copy shortcode', 'gpt3-ai-content-generator'); ?>"
                                    >
                                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                        <span class="aipkit_image_generator_shortcode_text">[aipkit_image_generator mode="both" default_mode="generate"]</span>
                                    </button>
                                    <button type="button" id="aipkit_image_generator_shortcode_settings_toggle" class="aipkit_icon_btn" title="<?php esc_attr_e('Configure Shortcode Options', 'gpt3-ai-content-generator'); ?>" aria-expanded="false" aria-controls="aipkit_image_generator_shortcode_configurator">
                                        <span class="dashicons dashicons-admin-settings"></span>
                                    </button>
                                </div>
                            </div>
                            <button type="button" id="aipkit_theme_switcher_toggle_btn" class="aipkit_icon_btn" title="<?php esc_attr_e('Switch Preview Theme', 'gpt3-ai-content-generator'); ?>">
                                <span class="dashicons dashicons-lightbulb"></span>
                            </button>
                        </div>

                        <div
                            class="aipkit_shortcode_configurator aipkit_model_settings_popover_panel"
                            id="aipkit_image_generator_shortcode_configurator"
                            style="display: none;"
                            role="dialog"
                            aria-modal="false"
                            aria-labelledby="aipkit_image_generator_shortcode_title"
                        >
                            <div class="aipkit_model_settings_popover_header">
                                <span class="aipkit_model_settings_popover_title" id="aipkit_image_generator_shortcode_title">
                                    <?php esc_html_e('Shortcode Settings', 'gpt3-ai-content-generator'); ?>
                                </span>
                                <button
                                    type="button"
                                    class="aipkit_model_settings_popover_close"
                                    aria-label="<?php esc_attr_e('Close', 'gpt3-ai-content-generator'); ?>"
                                >
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="aipkit_model_settings_popover_body">
                                <div class="aipkit_popover_options_list">
                                    <div class="aipkit_popover_option_row">
                                        <div class="aipkit_popover_option_main">
                                            <span class="aipkit_popover_option_label"><?php esc_html_e('Show Provider Select', 'gpt3-ai-content-generator'); ?></span>
                                            <label class="aipkit_switch">
                                                <input type="checkbox" name="cfg_show_provider" class="aipkit_toggle_switch aipkit_config_input" value="1" checked>
                                                <span class="aipkit_switch_slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row">
                                        <div class="aipkit_popover_option_main">
                                            <span class="aipkit_popover_option_label"><?php esc_html_e('Show Model Select', 'gpt3-ai-content-generator'); ?></span>
                                            <label class="aipkit_switch">
                                                <input type="checkbox" name="cfg_show_model" class="aipkit_toggle_switch aipkit_config_input" value="1" checked>
                                                <span class="aipkit_switch_slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row">
                                        <div class="aipkit_popover_option_main">
                                            <label class="aipkit_popover_option_label" for="aipkit_image_generator_shortcode_mode"><?php esc_html_e('UI Mode', 'gpt3-ai-content-generator'); ?></label>
                                            <div class="aipkit_popover_option_actions">
                                                <select id="aipkit_image_generator_shortcode_mode" name="cfg_mode" class="aipkit_popover_option_select aipkit_config_input">
                                                    <option value="generate"><?php esc_html_e('Generate only', 'gpt3-ai-content-generator'); ?></option>
                                                    <option value="edit"><?php esc_html_e('Edit only', 'gpt3-ai-content-generator'); ?></option>
                                                    <option value="both" selected><?php esc_html_e('Generate + Edit', 'gpt3-ai-content-generator'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row aipkit_image_shortcode_default_mode_row" hidden>
                                        <div class="aipkit_popover_option_main">
                                            <label class="aipkit_popover_option_label" for="aipkit_image_generator_shortcode_default_mode"><?php esc_html_e('Default Mode', 'gpt3-ai-content-generator'); ?></label>
                                            <div class="aipkit_popover_option_actions">
                                                <select id="aipkit_image_generator_shortcode_default_mode" name="cfg_default_mode" class="aipkit_popover_option_select aipkit_config_input">
                                                    <option value="generate" selected><?php esc_html_e('Generate', 'gpt3-ai-content-generator'); ?></option>
                                                    <option value="edit"><?php esc_html_e('Edit', 'gpt3-ai-content-generator'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row aipkit_image_shortcode_mode_switch_row" hidden>
                                        <div class="aipkit_popover_option_main">
                                            <span class="aipkit_popover_option_label"><?php esc_html_e('Show Mode Switch', 'gpt3-ai-content-generator'); ?></span>
                                            <label class="aipkit_switch">
                                                <input type="checkbox" name="cfg_show_mode_switch" class="aipkit_toggle_switch aipkit_config_input" value="1" checked>
                                                <span class="aipkit_switch_slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row">
                                        <div class="aipkit_popover_option_main">
                                            <span class="aipkit_popover_option_label"><?php esc_html_e('Show User History (if logged in)', 'gpt3-ai-content-generator'); ?></span>
                                            <label class="aipkit_switch">
                                                <input type="checkbox" name="cfg_show_history" class="aipkit_toggle_switch aipkit_config_input" value="1">
                                                <span class="aipkit_switch_slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="aipkit_popover_option_row">
                                        <div class="aipkit_popover_option_main">
                                            <label class="aipkit_popover_option_label" for="aipkit_image_generator_shortcode_theme"><?php esc_html_e('Theme', 'gpt3-ai-content-generator'); ?></label>
                                            <div class="aipkit_popover_option_actions">
                                                <select id="aipkit_image_generator_shortcode_theme" name="cfg_theme" class="aipkit_popover_option_select aipkit_config_input">
                                                    <option value="light"><?php esc_html_e('Light', 'gpt3-ai-content-generator'); ?></option>
                                                    <option value="dark" selected><?php esc_html_e('Dark', 'gpt3-ai-content-generator'); ?></option>
                                                    <option value="custom"><?php esc_html_e('Custom CSS', 'gpt3-ai-content-generator'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="aipkit_popover_flyout_footer">
                                <span class="aipkit_popover_flyout_footer_text">
                                    <?php esc_html_e('Need help?', 'gpt3-ai-content-generator'); ?>
                                </span>
                                <a
                                    class="aipkit_popover_flyout_footer_link"
                                    href="https://docs.aipower.org/images#shortcode"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php esc_html_e('Documentation', 'gpt3-ai-content-generator'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="aipkit_image_generator_preview_panel" class="aipkit_ai_forms_workspace_panel aipkit_image_generator_workspace_panel is-active" role="tabpanel" aria-labelledby="aipkit_image_generator_generator_tab">
                <div class="aipkit_image_generator_admin_preview_wrapper">
                    <?php
                    echo do_shortcode('[aipkit_image_generator history="true" mode="both" default_mode="generate"]');
                    ?>
                </div>
            </div>

            <div id="aipkit_image_generator_settings_panel" class="aipkit_ai_forms_workspace_panel aipkit_ai_forms_settings_panel aipkit_image_generator_workspace_panel" role="tabpanel" aria-labelledby="aipkit_image_generator_settings_tab" hidden>
                <?php include __DIR__ . '/partials/settings-image-generator.php'; ?>
            </div>
        </div>
    </div>
</div>
