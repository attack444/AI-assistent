<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Shared template variables are configured by wrapper partials.
$aipkit_post_settings_post_type_helper = $aipkit_post_settings_post_type_helper ?? __('Choose post type.', 'gpt3-ai-content-generator');
$aipkit_post_settings_include_author_login_attr = !empty($aipkit_post_settings_include_author_login_attr);
$aipkit_post_settings_include_seo_options = !isset($aipkit_post_settings_include_seo_options)
    || (bool) $aipkit_post_settings_include_seo_options;
?>

<div class="aipkit_model_settings_popover_header aipkit_cw_settings_sheet_header">
    <span class="aipkit_model_settings_popover_title"><?php esc_html_e('Post settings', 'gpt3-ai-content-generator'); ?></span>
</div>
<div class="aipkit_model_settings_popover_body aipkit_cw_settings_popover_body aipkit_cw_settings_sheet_body">
    <div class="aipkit_popover_options_list">
        <div class="aipkit_popover_option_row">
            <div class="aipkit_popover_option_main">
                <div class="aipkit_cw_settings_option_text">
                    <label class="aipkit_popover_option_label" for="<?php echo esc_attr($aipkit_post_settings_post_type_id); ?>">
                        <?php esc_html_e('Post Type', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <span class="aipkit_popover_option_helper">
                        <?php echo esc_html($aipkit_post_settings_post_type_helper); ?>
                    </span>
                </div>
                <select
                    id="<?php echo esc_attr($aipkit_post_settings_post_type_id); ?>"
                    name="post_type"
                    class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                >
                    <?php foreach ($aipkit_post_settings_post_types as $pt_slug => $pt_obj): ?>
                        <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($pt_slug, 'post'); ?>>
                            <?php echo esc_html($pt_obj->label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="aipkit_popover_option_row">
            <div class="aipkit_popover_option_main">
                <div class="aipkit_cw_settings_option_text">
                    <label class="aipkit_popover_option_label" for="<?php echo esc_attr($aipkit_post_settings_author_id); ?>">
                        <?php esc_html_e('Author', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <span class="aipkit_popover_option_helper">
                        <?php esc_html_e('Select the post author.', 'gpt3-ai-content-generator'); ?>
                    </span>
                </div>
                <select
                    id="<?php echo esc_attr($aipkit_post_settings_author_id); ?>"
                    name="post_author"
                    class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                >
                    <?php foreach ($aipkit_post_settings_users as $user): ?>
                        <option
                            value="<?php echo esc_attr($user->ID); ?>"
                            <?php if ($aipkit_post_settings_include_author_login_attr): ?>
                                data-login="<?php echo esc_attr($user->user_login); ?>"
                            <?php endif; ?>
                            <?php selected($user->ID, $aipkit_post_settings_current_user_id); ?>
                        >
                            <?php echo esc_html($user->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="aipkit_popover_option_row">
            <div class="aipkit_popover_option_main">
                <div class="aipkit_cw_settings_option_text">
                    <label class="aipkit_popover_option_label" for="<?php echo esc_attr($aipkit_post_settings_categories_id); ?>">
                        <?php esc_html_e('Categories', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <span class="aipkit_popover_option_helper">
                        <?php esc_html_e('Assign categories.', 'gpt3-ai-content-generator'); ?>
                    </span>
                </div>
                <div
                    class="aipkit_popover_multiselect aipkit_post_multiselect"
                    data-aipkit-categories-dropdown
                    data-placeholder="<?php echo esc_attr__('Select categories', 'gpt3-ai-content-generator'); ?>"
                    data-selected-label="<?php echo esc_attr__('selected', 'gpt3-ai-content-generator'); ?>"
                >
                    <button
                        type="button"
                        class="aipkit_popover_multiselect_btn aipkit_post_multiselect_btn aipkit_cw_blended_chevron_btn"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($aipkit_post_settings_categories_panel_id); ?>"
                    >
                        <span class="aipkit_popover_multiselect_label">
                            <?php esc_html_e('Select categories', 'gpt3-ai-content-generator'); ?>
                        </span>
                    </button>
                    <div
                        id="<?php echo esc_attr($aipkit_post_settings_categories_panel_id); ?>"
                        class="aipkit_popover_multiselect_panel"
                        role="menu"
                        hidden
                    >
                        <div class="aipkit_popover_multiselect_options"></div>
                    </div>
                    <select
                        id="<?php echo esc_attr($aipkit_post_settings_categories_id); ?>"
                        name="post_categories[]"
                        class="aipkit_popover_multiselect_select aipkit_autosave_trigger"
                        multiple
                        size="3"
                        hidden
                        aria-hidden="true"
                        tabindex="-1"
                    >
                        <?php foreach ($aipkit_post_settings_categories as $category): ?>
                            <option value="<?php echo esc_attr($category->term_id); ?>">
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($aipkit_post_settings_include_seo_options) : ?>
            <div class="aipkit_popover_option_row">
                <div class="aipkit_popover_option_main">
                    <div class="aipkit_cw_settings_option_text">
                        <label class="aipkit_popover_option_label" for="<?php echo esc_attr($aipkit_post_settings_toc_id); ?>">
                            <?php esc_html_e('Table of Contents', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <span class="aipkit_popover_option_helper">
                            <?php esc_html_e('Add navigation at the beginning.', 'gpt3-ai-content-generator'); ?>
                        </span>
                    </div>
                    <select
                        id="<?php echo esc_attr($aipkit_post_settings_toc_id); ?>"
                        name="generate_toc"
                        class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                    >
                        <option value="1"><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" selected><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>
            </div>

            <div class="aipkit_popover_option_row">
                <div class="aipkit_popover_option_main">
                    <div class="aipkit_cw_settings_option_text">
                        <label class="aipkit_popover_option_label" for="<?php echo esc_attr($aipkit_post_settings_slug_id); ?>">
                            <?php esc_html_e('Optimize URL', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <span class="aipkit_popover_option_helper">
                            <?php esc_html_e('Generate an SEO-friendly slug.', 'gpt3-ai-content-generator'); ?>
                        </span>
                    </div>
                    <select
                        id="<?php echo esc_attr($aipkit_post_settings_slug_id); ?>"
                        name="generate_seo_slug"
                        class="aipkit_post_settings_select aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                    >
                        <option value="1"><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" selected><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
