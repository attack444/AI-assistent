<?php

/**
 * Partial: Content Indexing Automated Task - Source Settings
 * @since 2.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
// Variables from parent: $all_selectable_post_types
?>
<div id="aipkit_task_ci_source_settings" class="aipkit_ci_source_panel">
    <div class="aipkit_ci_source_grid">
        <section class="aipkit_ci_card aipkit_ci_card--destination">
            <?php include __DIR__ . '/knowledge-base-settings.php'; ?>
        </section>

        <section class="aipkit_ci_card aipkit_ci_card--scope aipkit_ci_filter_row">
            <div class="aipkit_ci_card_header">
                <label class="aipkit_ci_target_label" for="aipkit_task_content_indexing_post_types"><?php esc_html_e('Content types', 'gpt3-ai-content-generator'); ?></label>
            </div>
            <select id="aipkit_task_content_indexing_post_types" name="post_types[]" class="aipkit_form-input aipkit_ci_multi_select" data-aipkit-checklist-style="inline-checkboxes" multiple size="5">
                <?php foreach ($all_selectable_post_types as $slug => $pt_obj): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($pt_obj->label); ?></option>
                <?php endforeach; ?>
            </select>
        </section>
    </div>

    <section class="aipkit_ci_card aipkit_ci_card--behavior">
        <div class="aipkit_ci_option_list">
            <div class="aipkit_ci_option_card">
                <label class="aipkit_ci_option_copy" for="aipkit_task_content_indexing_index_existing">
                    <span class="aipkit_ci_option_title"><?php esc_html_e('Queue all existing content now', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_ci_option_desc"><?php esc_html_e('Builds the initial knowledge base, then turns off automatically.', 'gpt3-ai-content-generator'); ?></span>
                </label>
                <label class="aipkit_switch aipkit_ci_option_switch" for="aipkit_task_content_indexing_index_existing">
                    <input type="checkbox" name="index_existing_now_flag" id="aipkit_task_content_indexing_index_existing" value="1" checked>
                    <span class="aipkit_switch_slider"></span>
                </label>
            </div>

            <div class="aipkit_ci_option_card">
                <label class="aipkit_ci_option_copy" for="aipkit_task_content_indexing_only_new_updated">
                    <span class="aipkit_ci_option_title"><?php esc_html_e('Auto-index new and updated content', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_ci_option_desc"><?php esc_html_e('Keeps the knowledge base current as content changes.', 'gpt3-ai-content-generator'); ?></span>
                </label>
                <label class="aipkit_switch aipkit_ci_option_switch" for="aipkit_task_content_indexing_only_new_updated">
                    <input type="checkbox" name="only_new_updated_flag" id="aipkit_task_content_indexing_only_new_updated" value="1" checked>
                    <span class="aipkit_switch_slider"></span>
                </label>
            </div>
        </div>
    </section>
</div>
