<?php

/**
 * Partial: Community Engagement Automated Task - Source Settings
 * This is included in the main "Setup" step of the wizard.
 *
 * @since 2.2.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
// Variables from parent: $all_selectable_post_types

$reply_actions = [
    'approve' => __('Approve', 'gpt3-ai-content-generator'),
    'hold' => __('Hold for moderation', 'gpt3-ai-content-generator'),
];
?>
<div id="aipkit_task_cc_source_settings" class="aipkit_cc_source_panel">
    <div class="aipkit_cc_source_grid">
        <div class="aipkit_cc_setting_row aipkit_cc_setting_row--content-types">
            <label class="aipkit_cc_field_label" for="aipkit_task_comment_reply_post_types"><?php esc_html_e('Content types', 'gpt3-ai-content-generator'); ?></label>
            <select id="aipkit_task_comment_reply_post_types" name="post_types_for_comments[]" class="aipkit_form-input aipkit_cc_multi_select" data-aipkit-checklist-style="inline-checkboxes" multiple size="5">
                <?php foreach ($all_selectable_post_types as $slug => $pt_obj): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($pt_obj->label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="aipkit_cc_setting_row aipkit_cc_setting_row--reply-action">
            <label class="aipkit_cc_field_label" for="aipkit_task_comment_reply_action"><?php esc_html_e('Action on reply', 'gpt3-ai-content-generator'); ?></label>
            <select id="aipkit_task_comment_reply_action" name="reply_action" class="aipkit_form-input aipkit_cc_select" data-aipkit-segmented-select>
                <?php foreach ($reply_actions as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'hold'); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="aipkit_cc_setting_row aipkit_cc_setting_row--checkbox">
            <label id="aipkit_task_comment_reply_no_replies_label" class="aipkit_cc_field_label" for="aipkit_task_comment_reply_no_replies"><?php esc_html_e('Top-level comments only', 'gpt3-ai-content-generator'); ?></label>
            <input class="aipkit_cc_setting_checkbox" type="checkbox" id="aipkit_task_comment_reply_no_replies" name="no_reply_to_replies" value="1" aria-labelledby="aipkit_task_comment_reply_no_replies_label" checked>
        </div>
    </div>
</div>
