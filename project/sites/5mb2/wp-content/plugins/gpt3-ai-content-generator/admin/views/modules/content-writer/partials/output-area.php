<?php
// Redesigned with Choice Overload reduction and Chunking principles:
// - Progressive disclosure of meta fields
// - Visual chunking with clear boundaries
// - Prioritized action hierarchy
// - Streamlined, focused interface

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_cw_output_seo_profile = class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')
    ? \WPAICG\SEO\AIPKit_SEO_Helper::get_active_plugin_profile()
    : [
        'label' => __('AIPKit SEO', 'gpt3-ai-content-generator'),
        'logo_url' => '',
        'logo_initials' => 'AI',
    ];
$aipkit_cw_output_seo_profile_label = isset($aipkit_cw_output_seo_profile['label']) ? (string) $aipkit_cw_output_seo_profile['label'] : __('AIPKit SEO', 'gpt3-ai-content-generator');
$aipkit_cw_output_seo_profile_logo_url = isset($aipkit_cw_output_seo_profile['logo_url']) ? (string) $aipkit_cw_output_seo_profile['logo_url'] : '';
$aipkit_cw_output_seo_profile_logo_initials = isset($aipkit_cw_output_seo_profile['logo_initials']) ? (string) $aipkit_cw_output_seo_profile['logo_initials'] : 'SEO';
$aipkit_cw_output_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
?>
<div id="aipkit_cw_single_output_wrapper" class="aipkit_cw_output_wrapper" style="display: none;">
    <div class="aipkit_cw_output_workspace aipkit_cw_output_workspace--studio">
        <aside class="aipkit_cw_output_brief">
            <div class="aipkit_cw_studio_panel">
                <div class="aipkit_cw_studio_panel_header">
                    <span class="aipkit_cw_studio_panel_label"><?php esc_html_e('Brief', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_cw_studio_panel_hint"><?php esc_html_e('Live snapshot of the current single run', 'gpt3-ai-content-generator'); ?></span>
                </div>
                <dl class="aipkit_cw_studio_brief_list">
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Topic', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_topic_value"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Keywords', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_keywords_value"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Template', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_template_brief_value"><?php esc_html_e('Default', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_model_brief_value"><?php esc_html_e('Not selected', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Draft Settings', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_settings_value"><?php esc_html_e('Medium length', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                    <div class="aipkit_cw_studio_brief_row">
                        <dt><?php esc_html_e('Publish Target', 'gpt3-ai-content-generator'); ?></dt>
                        <dd id="aipkit_cw_single_preview_publish_brief_value"><?php esc_html_e('Draft', 'gpt3-ai-content-generator'); ?></dd>
                    </div>
                </dl>
            </div>
        </aside>

        <div class="aipkit_cw_output_main">
            <div class="aipkit_cw_output_chunk aipkit_cw_output_chunk--primary">
                <div class="aipkit_cw_chunk_header">
                    <div class="aipkit_cw_chunk_title_row">
                        <span class="aipkit_cw_chunk_label"><?php esc_html_e('Article', 'gpt3-ai-content-generator'); ?></span>
                    </div>
                    <div class="aipkit_cw_chunk_header_tools">
                        <select
                            id="aipkit_cw_draft_version_select"
                            class="aipkit_cw_draft_version_select"
                            aria-label="<?php esc_attr_e('Draft version', 'gpt3-ai-content-generator'); ?>"
                            hidden
                        ></select>
                        <span id="aipkit_cw_article_counter" class="aipkit_cw_chunk_counter" aria-live="polite" aria-atomic="true" hidden></span>
                    </div>
                </div>

                <div
                    id="aipkit_content_writer_output_display"
                    class="aipkit_cw_output_canvas"
                    data-canvas-state="empty"
                    data-canvas-has-content="false"
                >
                    <h2 id="aipkit_cw_generated_title_display" class="aipkit_cw_output_title" style="display: none;"></h2>

                    <div id="aipkit_cw_image_preview" class="aipkit_cw_image_preview" hidden>
                        <button
                            type="button"
                            id="aipkit_cw_inline_images_toggle"
                            class="aipkit_cw_inline_images_toggle"
                            aria-expanded="false"
                            aria-controls="aipkit_cw_inline_images_grid"
                            hidden
                        >
                            <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                            <span class="aipkit_cw_inline_images_label"><?php esc_html_e('Images', 'gpt3-ai-content-generator'); ?></span>
                            <span id="aipkit_cw_inline_images_count" class="aipkit_cw_inline_images_count">0</span>
                        </button>
                        <div id="aipkit_cw_inline_images_grid" class="aipkit_cw_inline_images_grid" hidden></div>
                    </div>

                    <div id="aipkit_cw_generated_content_area" class="aipkit_cw_output_body"></div>
                </div>
            </div>

        </div>

        <aside class="aipkit_cw_output_sidebar aipkit_cw_output_sidebar--inspector">
            <div
                id="aipkit_cw_status_display_container"
                class="aipkit_cw_output_sidebar_card aipkit_cw_output_sidebar_card--session"
                data-progress-title="<?php echo esc_attr__('Progress', 'gpt3-ai-content-generator'); ?>"
                data-actions-title="<?php echo esc_attr__('Actions', 'gpt3-ai-content-generator'); ?>"
            >
                <div class="aipkit_cw_output_sidebar_header aipkit_cw_output_sidebar_header--progress">
                    <div class="aipkit_cw_output_sidebar_header_copy">
                        <div id="aipkit_cw_session_card_title" class="aipkit_cw_output_sidebar_title"><?php esc_html_e('Progress', 'gpt3-ai-content-generator'); ?></div>
                    </div>
                </div>
                <?php include __DIR__ . '/generation-status-indicators.php'; ?>
                <div id="aipkit_cw_single_run_actions" class="aipkit_cw_single_run_actions aipkit_cw_single_run_actions--sidebar"></div>
                <div class="aipkit_content_writer_output_actions aipkit_cw_output_dock" style="display: none;">
                    <div class="aipkit_cw_output_dock_main">
                        <div class="aipkit_cw_output_actions_row">
                            <button type="button" id="aipkit_cw_save_as_post_btn" data-aipkit-cw-save-post-btn class="button button-primary aipkit_btn aipkit_btn-primary aipkit_cw_output_action_btn" disabled>
                                <span class="aipkit_btn-text"><?php esc_html_e('Save', 'gpt3-ai-content-generator'); ?></span>
                                <span class="aipkit_spinner" style="display:none;"></span>
                            </button>
                            <button type="button" id="aipkit_content_writer_copy_btn" class="button aipkit_btn aipkit_btn-secondary aipkit_cw_output_action_btn" disabled>
                                <span class="aipkit_btn-text"><?php esc_html_e('Copy', 'gpt3-ai-content-generator'); ?></span>
                            </button>
                            <button type="button" id="aipkit_content_writer_clear_btn" class="button aipkit_btn aipkit_cw_output_action_btn aipkit_cw_output_action_btn--reset" disabled>
                                <span class="aipkit_btn-text"><?php esc_html_e('Start Over', 'gpt3-ai-content-generator'); ?></span>
                            </button>
                        </div>
                    </div>
                    <div id="aipkit_cw_save_post_status" class="aipkit_cw_save_status" aria-live="polite"></div>
                </div>
            </div>

            <section id="aipkit_cw_meta_seo_panel" class="aipkit_cw_output_sidebar_card aipkit_cw_meta_group aipkit_cw_meta_group--seo-score" data-aipkit-meta-group-panel="seo" style="display: none;" hidden>
                    <div class="aipkit_cw_output_sidebar_header">
                        <div class="aipkit_cw_output_sidebar_header_copy">
                            <div class="aipkit_cw_output_sidebar_title aipkit_cw_output_sidebar_title--seo">
                                <span><?php esc_html_e('SEO', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="aipkit_cw_meta_group_fields">
                        <div
                            id="aipkit_cw_seo_audit_summary"
                            class="aipkit_cw_seo_audit_summary"
                            data-aipkit-meta-group="seo"
                            data-aipkit-seo-audit-state="idle"
                            aria-live="polite"
                            hidden
                        >
                            <div class="aipkit_cw_seo_audit_top">
                                <div
                                    class="aipkit_cw_seo_score_ring"
                                    data-aipkit-seo-score-ring
                                    style="--aipkit-cw-seo-score-angle: 0deg;"
                                    aria-label="<?php esc_attr_e('SEO score', 'gpt3-ai-content-generator'); ?>"
                                >
                                    <span class="aipkit_cw_seo_score_value" data-aipkit-seo-score-value>--</span>
                                    <span class="aipkit_cw_seo_score_total">/100</span>
                                </div>
                                <div class="aipkit_cw_seo_audit_copy">
                                    <div class="aipkit_cw_seo_audit_status_row">
                                        <span class="aipkit_cw_seo_audit_status" data-aipkit-seo-audit-status>
                                            <?php esc_html_e('SEO audit pending', 'gpt3-ai-content-generator'); ?>
                                        </span>
                                        <span
                                            class="aipkit_cw_seo_audit_profile"
                                            data-aipkit-seo-audit-profile
                                            data-aipkit-seo-profile-logo="<?php echo esc_url($aipkit_cw_output_seo_profile_logo_url); ?>"
                                            data-aipkit-seo-profile-initials="<?php echo esc_attr($aipkit_cw_output_seo_profile_logo_initials); ?>"
                                            data-aipkit-seo-profile-label="<?php echo esc_attr($aipkit_cw_output_seo_profile_label); ?>"
                                            hidden
                                        ></span>
                                    </div>
                                    <div class="aipkit_cw_seo_audit_meta">
                                        <span data-aipkit-seo-audit-target><?php esc_html_e('0 revisions', 'gpt3-ai-content-generator'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            id="aipkit_cw_smart_seo_locked_card"
                            class="aipkit_cw_smart_seo_locked_card"
                            data-aipkit-meta-group="seo"
                            aria-live="polite"
                            hidden
                        >
                            <div class="aipkit_cw_smart_seo_locked_top">
                                <div class="aipkit_cw_smart_seo_locked_copy">
                                    <p>
                                        <?php
                                        printf(
                                            /* translators: %s: active SEO plugin name. */
                                            esc_html__('AI Puffer integrates with %s and automatically refines content until it achieves a higher SEO score.', 'gpt3-ai-content-generator'),
                                            esc_html($aipkit_cw_output_seo_profile_label)
                                        );
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="aipkit_cw_smart_seo_locked_footer">
                                <span
                                    class="aipkit_cw_smart_seo_locked_profile"
                                    title="<?php echo esc_attr($aipkit_cw_output_seo_profile_label); ?>"
                                    aria-label="<?php echo esc_attr($aipkit_cw_output_seo_profile_label); ?>"
                                    role="img"
                                >
                                    <span class="aipkit_seo_profile_logo aipkit_seo_profile_logo--tiny" aria-hidden="true">
                                        <?php if ($aipkit_cw_output_seo_profile_logo_url !== '') : ?>
                                            <img src="<?php echo esc_url($aipkit_cw_output_seo_profile_logo_url); ?>" alt="">
                                    <?php else : ?>
                                        <span><?php echo esc_html($aipkit_cw_output_seo_profile_logo_initials); ?></span>
                                    <?php endif; ?>
                                    </span>
                                    <span><?php echo esc_html($aipkit_cw_output_seo_profile_label); ?></span>
                                </span>
                                <a
                                    class="aipkit_btn aipkit_btn-primary aipkit_cw_smart_seo_locked_btn aipkit_pro_upgrade_button"
                                    href="<?php echo esc_url($aipkit_cw_output_upgrade_url); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?>
                                </a>
                            </div>
                        </div>
                        <div
                            id="aipkit_cw_smart_seo_run_card"
                            class="aipkit_cw_smart_seo_run_card"
                            data-aipkit-meta-group="seo"
                            aria-live="polite"
                            hidden
                        >
                            <div class="aipkit_cw_smart_seo_locked_top">
                                <div class="aipkit_cw_smart_seo_locked_copy">
                                    <p>
                                        <?php
                                        printf(
                                            /* translators: %s: active SEO plugin name. */
                                            esc_html__('AI Puffer integrates with %s. Improve this draft with Smart SEO before saving.', 'gpt3-ai-content-generator'),
                                            esc_html($aipkit_cw_output_seo_profile_label)
                                        );
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="aipkit_cw_smart_seo_locked_footer">
                                <span
                                    class="aipkit_cw_smart_seo_locked_profile"
                                    title="<?php echo esc_attr($aipkit_cw_output_seo_profile_label); ?>"
                                    aria-label="<?php echo esc_attr($aipkit_cw_output_seo_profile_label); ?>"
                                    role="img"
                                >
                                    <span class="aipkit_seo_profile_logo aipkit_seo_profile_logo--tiny" aria-hidden="true">
                                        <?php if ($aipkit_cw_output_seo_profile_logo_url !== '') : ?>
                                            <img src="<?php echo esc_url($aipkit_cw_output_seo_profile_logo_url); ?>" alt="">
                                    <?php else : ?>
                                        <span><?php echo esc_html($aipkit_cw_output_seo_profile_logo_initials); ?></span>
                                    <?php endif; ?>
                                    </span>
                                    <span><?php echo esc_html($aipkit_cw_output_seo_profile_label); ?></span>
                                </span>
                                <button
                                    type="button"
                                    id="aipkit_cw_smart_seo_run_btn"
                                    class="aipkit_btn aipkit_btn-primary aipkit_cw_smart_seo_locked_btn"
                                >
                                    <span class="aipkit_btn-text"><?php esc_html_e('Improve SEO', 'gpt3-ai-content-generator'); ?></span>
                                    <span class="aipkit_spinner" style="display:none;"></span>
                                </button>
                            </div>
                        </div>
                    </div>
            </section>

            <div id="aipkit_cw_output_media_panel" class="aipkit_cw_output_sidebar_card aipkit_cw_output_sidebar_card--media" hidden>
                <div class="aipkit_cw_output_sidebar_header">
                    <div class="aipkit_cw_output_sidebar_header_copy">
                        <div class="aipkit_cw_output_sidebar_title"><?php esc_html_e('Media', 'gpt3-ai-content-generator'); ?></div>
                    </div>
                </div>
                <div id="aipkit_cw_featured_image" class="aipkit_cw_featured_image" hidden>
                    <div class="aipkit_cw_image_frame">
                        <div id="aipkit_cw_featured_image_loading" class="aipkit_cw_image_frame_loading" hidden aria-hidden="true">
                            <div class="aipkit_cw_image_frame_loading_art">
                                <span class="aipkit_cw_image_frame_loading_blur aipkit_cw_image_frame_loading_blur--one"></span>
                                <span class="aipkit_cw_image_frame_loading_blur aipkit_cw_image_frame_loading_blur--two"></span>
                            </div>
                        </div>
                        <img id="aipkit_cw_featured_image_img" alt="<?php esc_attr_e('Featured image preview', 'gpt3-ai-content-generator'); ?>" loading="lazy" decoding="async" hidden>
                    </div>
                </div>
            </div>

            <div id="aipkit_cw_meta_chunk" class="aipkit_cw_meta_cards" style="display: none;">

                <section id="aipkit_cw_generated_details_panel" class="aipkit_cw_output_sidebar_card aipkit_cw_meta_group aipkit_cw_meta_group--generated-details" data-aipkit-meta-group-panel="details" style="display: none;" hidden>
                    <div class="aipkit_cw_output_sidebar_header">
                        <div class="aipkit_cw_output_sidebar_header_copy">
                            <div class="aipkit_cw_output_sidebar_title"><?php esc_html_e('Post Metadata', 'gpt3-ai-content-generator'); ?></div>
                        </div>
                    </div>
                    <div class="aipkit_cw_meta_group_fields">
                        <div id="aipkit_cw_excerpt_output_wrapper" class="aipkit_cw_meta_field" data-aipkit-meta-group="details" style="display: none;">
                            <label class="aipkit_cw_meta_label" for="aipkit_cw_generated_excerpt">
                                <span class="dashicons dashicons-editor-quote aipkit_cw_meta_icon" aria-hidden="true"></span>
                                <?php esc_html_e('Excerpt', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <textarea id="aipkit_cw_generated_excerpt" name="generated_excerpt" class="aipkit_cw_meta_input aipkit_autosave_trigger aipkit_cw_generated_output_field" rows="2" placeholder="<?php esc_attr_e('Short summary for previews...', 'gpt3-ai-content-generator'); ?>"></textarea>
                        </div>

                        <div id="aipkit_cw_focus_keyword_output_wrapper" class="aipkit_cw_meta_field" data-aipkit-meta-group="details" style="display: none;">
                            <label class="aipkit_cw_meta_label" for="aipkit_cw_generated_focus_keyword">
                                <span class="dashicons dashicons-flag aipkit_cw_meta_icon" aria-hidden="true"></span>
                                <?php esc_html_e('Focus Keyword', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input type="text" id="aipkit_cw_generated_focus_keyword" name="focus_keyword" class="aipkit_cw_meta_input aipkit_autosave_trigger aipkit_cw_generated_output_field" placeholder="<?php esc_attr_e('Primary SEO keyword...', 'gpt3-ai-content-generator'); ?>">
                        </div>

                        <div id="aipkit_cw_meta_desc_output_wrapper" class="aipkit_cw_meta_field" data-aipkit-meta-group="details" style="display: none;">
                            <label class="aipkit_cw_meta_label" for="aipkit_cw_generated_meta_desc">
                                <span class="dashicons dashicons-search aipkit_cw_meta_icon" aria-hidden="true"></span>
                                <?php esc_html_e('Meta Description', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <textarea id="aipkit_cw_generated_meta_desc" name="meta_description" class="aipkit_cw_meta_input aipkit_autosave_trigger aipkit_cw_generated_output_field" rows="2" placeholder="<?php esc_attr_e('SEO description for search results...', 'gpt3-ai-content-generator'); ?>"></textarea>
                            <span class="aipkit_cw_meta_char_count" aria-live="polite"></span>
                        </div>

                        <div id="aipkit_cw_tags_output_wrapper" class="aipkit_cw_meta_field" data-aipkit-meta-group="details" style="display: none;">
                            <label class="aipkit_cw_meta_label" for="aipkit_cw_tags_chip_input">
                                <span class="dashicons dashicons-tag aipkit_cw_meta_icon" aria-hidden="true"></span>
                                <?php esc_html_e('Tags', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <div class="aipkit_cw_tags_editor" data-aipkit-cw-tags-editor>
                                <div id="aipkit_cw_tags_chip_list" class="aipkit_cw_tags_chip_list" aria-live="polite"></div>
                                <input
                                    type="text"
                                    id="aipkit_cw_tags_chip_input"
                                    class="aipkit_cw_tags_chip_input"
                                    placeholder="<?php esc_attr_e('Add a tag...', 'gpt3-ai-content-generator'); ?>"
                                    autocomplete="off"
                                >
                            </div>
                            <textarea id="aipkit_cw_generated_tags" name="generated_tags" class="aipkit_cw_meta_input aipkit_autosave_trigger aipkit_cw_generated_output_field aipkit_cw_tags_source_input" rows="1" placeholder="<?php esc_attr_e('Comma-separated tags...', 'gpt3-ai-content-generator'); ?>" aria-hidden="true" tabindex="-1"></textarea>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>
</div>
