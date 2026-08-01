<?php
/**
 * Inline post settings for the AutoGPT Finish step.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables come from the parent view.
$cw_available_post_types = isset($cw_available_post_types) && is_array($cw_available_post_types) ? $cw_available_post_types : [];
$cw_users_for_author = isset($cw_users_for_author) && is_array($cw_users_for_author) ? $cw_users_for_author : [];
$cw_wp_categories = isset($cw_wp_categories) && is_array($cw_wp_categories) ? $cw_wp_categories : [];
$cw_current_user_id = isset($cw_current_user_id) ? (int) $cw_current_user_id : 0;
?>

<div
    class="aipkit_autogpt_post_settings_inline"
    data-aipkit-autogpt-schedule-section="content_writing"
    hidden
>
    <div class="aipkit_cw_publishing_row aipkit_autogpt_question_row aipkit_task_post_setting_row">
        <div class="aipkit_cw_panel_label_wrap">
            <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_task_cw_post_type">
                <?php esc_html_e('Post Type', 'gpt3-ai-content-generator'); ?>
            </label>
        </div>
        <div class="aipkit_cw_publishing_row_actions">
            <select
                id="aipkit_task_cw_post_type"
                name="post_type"
                class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_cw_publishing_select aipkit_cw_blended_chevron_select"
            >
                <?php foreach ($cw_available_post_types as $pt_slug => $pt_obj) : ?>
                    <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($pt_slug, 'post'); ?>>
                        <?php echo esc_html($pt_obj->label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="aipkit_cw_publishing_row aipkit_autogpt_question_row aipkit_task_post_setting_row">
        <div class="aipkit_cw_panel_label_wrap">
            <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_task_cw_post_author">
                <?php esc_html_e('Author', 'gpt3-ai-content-generator'); ?>
            </label>
        </div>
        <div class="aipkit_cw_publishing_row_actions">
            <select
                id="aipkit_task_cw_post_author"
                name="post_author"
                class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_cw_publishing_select aipkit_cw_blended_chevron_select"
            >
                <?php foreach ($cw_users_for_author as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user->ID, $cw_current_user_id); ?>>
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="aipkit_cw_publishing_row aipkit_autogpt_question_row aipkit_task_post_setting_row">
        <div class="aipkit_cw_panel_label_wrap">
            <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_task_cw_post_categories">
                <?php esc_html_e('Categories', 'gpt3-ai-content-generator'); ?>
            </label>
        </div>
        <div class="aipkit_cw_publishing_row_actions">
            <div
                class="aipkit_popover_multiselect aipkit_post_multiselect aipkit_autogpt_finish_categories"
                data-aipkit-categories-dropdown
                data-placeholder="<?php echo esc_attr__('Select categories', 'gpt3-ai-content-generator'); ?>"
                data-selected-label="<?php echo esc_attr__('selected', 'gpt3-ai-content-generator'); ?>"
            >
                <button
                    type="button"
                    class="aipkit_popover_multiselect_btn aipkit_post_multiselect_btn aipkit_cw_blended_chevron_btn"
                    aria-expanded="false"
                    aria-controls="aipkit_task_cw_categories_panel"
                >
                    <span class="aipkit_popover_multiselect_label">
                        <?php esc_html_e('Select categories', 'gpt3-ai-content-generator'); ?>
                    </span>
                </button>
                <div
                    id="aipkit_task_cw_categories_panel"
                    class="aipkit_popover_multiselect_panel"
                    role="menu"
                    hidden
                >
                    <div class="aipkit_popover_multiselect_options"></div>
                </div>
                <select
                    id="aipkit_task_cw_post_categories"
                    name="post_categories[]"
                    class="aipkit_popover_multiselect_select aipkit_autosave_trigger"
                    multiple
                    size="3"
                    hidden
                    aria-hidden="true"
                    tabindex="-1"
                >
                    <?php foreach ($cw_wp_categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>
