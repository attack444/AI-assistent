<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
?>

<div class="aipkit_cw_publishing_panel aipkit_post_settings_redesigned">
    <div class="aipkit_post_settings_chunk aipkit_post_settings_chunk--publishing">
        <div class="aipkit_post_settings_chunk_body">
            <div class="aipkit_post_status_row aipkit_cw_publishing_row">
                <label class="aipkit_cw_panel_label" for="aipkit_content_writer_post_status">
                    <?php esc_html_e('Status', 'gpt3-ai-content-generator'); ?>
                </label>
                <div class="aipkit_cw_publishing_row_actions">
                    <select id="aipkit_content_writer_post_status" name="post_status" class="aipkit_post_settings_select aipkit_form-input aipkit_cw_publishing_select aipkit_cw_blended_chevron_select aipkit_autosave_trigger">
                        <?php foreach ($post_statuses as $status_val => $status_label): ?>
                            <option value="<?php echo esc_attr($status_val); ?>" <?php selected($status_val, 'draft'); ?>><?php echo esc_html($status_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="button"
                        class="aipkit_cw_settings_icon_trigger"
                        id="aipkit_cw_post_settings_trigger"
                        data-aipkit-popover-target="aipkit_cw_post_settings_popover"
                        data-aipkit-popover-placement="left"
                        aria-controls="aipkit_cw_post_settings_popover"
                        aria-expanded="false"
                        aria-label="<?php esc_attr_e('Post settings', 'gpt3-ai-content-generator'); ?>"
                        title="<?php esc_attr_e('Post settings', 'gpt3-ai-content-generator'); ?>"
                    >
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    </button>
                </div>
            </div>

            <div id="aipkit_cw_schedule_options_wrapper" class="aipkit_post_smart_schedule_container" hidden>
                <div class="aipkit_post_smart_schedule_header">
                    <span class="aipkit_cw_panel_label">
                        <?php esc_html_e('Publishing Schedule', 'gpt3-ai-content-generator'); ?>
                    </span>
                </div>
                <div class="aipkit_post_smart_schedule_options">
                    <label class="aipkit_post_schedule_radio">
                        <input type="radio" name="schedule_mode" value="immediate" checked>
                        <span class="aipkit_post_schedule_radio_text"><?php esc_html_e('Publish Immediately', 'gpt3-ai-content-generator'); ?></span>
                    </label>
                    <label class="aipkit_post_schedule_radio">
                        <input type="radio" name="schedule_mode" value="smart">
                        <span class="aipkit_post_schedule_radio_text"><?php esc_html_e('Smart Schedule', 'gpt3-ai-content-generator'); ?></span>
                    </label>
                    <label class="aipkit_post_schedule_radio aipkit_schedule_from_input_option">
                        <input type="radio" name="schedule_mode" value="from_input">
                        <span class="aipkit_post_schedule_radio_text"><?php esc_html_e('Use Dates from Input', 'gpt3-ai-content-generator'); ?></span>
                    </label>
                </div>

                <div id="aipkit_cw_smart_schedule_fields" class="aipkit_post_smart_schedule_fields" hidden>
                    <div class="aipkit_post_smart_schedule_field">
                        <label class="aipkit_cw_panel_label" for="aipkit_cw_smart_schedule_start_datetime">
                            <?php esc_html_e('Start Date/Time', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input type="datetime-local" id="aipkit_cw_smart_schedule_start_datetime" name="smart_schedule_start_datetime" class="aipkit_post_settings_input aipkit_form-input aipkit_cw_publishing_input">
                    </div>
                    <div class="aipkit_post_smart_schedule_field">
                        <label class="aipkit_cw_panel_label" for="aipkit_cw_smart_schedule_interval_value">
                            <?php esc_html_e('Publish one post every', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_post_smart_schedule_interval">
                            <input type="number" id="aipkit_cw_smart_schedule_interval_value" name="smart_schedule_interval_value" value="1" min="1" class="aipkit_post_settings_input aipkit_post_settings_input--number aipkit_form-input aipkit_cw_publishing_input aipkit_cw_publishing_input--number">
                            <select id="aipkit_cw_smart_schedule_interval_unit" name="smart_schedule_interval_unit" class="aipkit_post_settings_select aipkit_form-input aipkit_cw_publishing_select aipkit_cw_blended_chevron_select">
                                <option value="hours"><?php esc_html_e('Hours', 'gpt3-ai-content-generator'); ?></option>
                                <option value="days"><?php esc_html_e('Days', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <p class="aipkit_post_schedule_hint aipkit_schedule_from_input_help" hidden>
                    <?php esc_html_e('Append | YYYY-MM-DD HH:MM to each line or use the schedule column in Google Sheets.', 'gpt3-ai-content-generator'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="aipkit_model_settings_popover aipkit_cw_settings_popover" id="aipkit_cw_post_settings_popover" aria-hidden="true">
    <div class="aipkit_model_settings_popover_panel aipkit_cw_settings_popover_panel aipkit_cw_post_settings_popover_panel" role="dialog" aria-label="<?php esc_attr_e('Post settings', 'gpt3-ai-content-generator'); ?>">
        <?php include __DIR__ . '/post-settings.php'; ?>
    </div>
</div>
