<?php
/**
 * Partial: AutoGPT Task Frequency
 *
 * Expected variables:
 * - $frequencies (array) Optional
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$frequencies = isset($frequencies) && is_array($frequencies) ? $frequencies : [];
$default_frequency = array_key_exists('daily', $frequencies)
    ? 'daily'
    : (array_key_first($frequencies) ?: '');
?>
<div class="aipkit_task_schedule_frequency">
    <div class="aipkit_cw_publishing_row aipkit_autogpt_question_row aipkit_task_schedule_frequency_row">
        <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_automated_task_frequency">
            <?php esc_html_e('How often should it run?', 'gpt3-ai-content-generator'); ?>
        </label>
        <div class="aipkit_cw_publishing_row_actions">
            <select id="aipkit_automated_task_frequency" name="task_frequency" class="aipkit_post_settings_select aipkit_form-input aipkit_cw_publishing_select aipkit_cw_blended_chevron_select aipkit_task_schedule_select aipkit_task_schedule_frequency_select">
            <?php foreach ($frequencies as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $default_frequency); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
