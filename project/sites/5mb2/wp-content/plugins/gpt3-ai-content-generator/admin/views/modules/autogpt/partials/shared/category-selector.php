<?php

/**
 * Partial: AutoGPT - Category Selector
 * Renders a simple intent list for task creation.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$aipkit_autogpt_is_pro = !empty($is_pro);

$aipkit_autogpt_category_groups = [
    [
        'category' => 'content_creation',
        'family_label' => __('Create new content', 'gpt3-ai-content-generator'),
        'family_description' => __('Turn topics, feeds, URLs, or spreadsheets into WordPress posts.', 'gpt3-ai-content-generator'),
        'family_icon' => 'dashicons-edit-page',
        'items' => [
            [
                'slug' => 'content_creation',
                'task_type' => 'content_writing_bulk',
                'label' => __('Manual entry', 'gpt3-ai-content-generator'),
                'description' => __('Paste a list or edit topics in rows', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-edit-page',
            ],
            [
                'slug' => 'content_creation',
                'task_type' => 'content_writing_csv',
                'label' => __('A CSV file', 'gpt3-ai-content-generator'),
                'description' => __('Import a topic list', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-media-spreadsheet',
            ],
            [
                'slug' => 'content_creation',
                'task_type' => 'content_writing_rss',
                'label' => __('RSS feeds', 'gpt3-ai-content-generator'),
                'description' => __('Watch feeds for new topics', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-rss',
                'pro' => true,
            ],
            [
                'slug' => 'content_creation',
                'task_type' => 'content_writing_url',
                'label' => __('Web pages', 'gpt3-ai-content-generator'),
                'description' => __('Use URLs as sources', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-admin-links',
                'pro' => true,
            ],
            [
                'slug' => 'content_creation',
                'task_type' => 'content_writing_gsheets',
                'label' => __('Google Sheets', 'gpt3-ai-content-generator'),
                'description' => __('Sync from a spreadsheet', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-table-col-before',
                'pro' => true,
            ],
        ],
    ],
    [
        'category' => 'content_enhancement',
        'family_label' => __('Rewrite existing content', 'gpt3-ai-content-generator'),
        'family_description' => __('Automatically improve your existing content.', 'gpt3-ai-content-generator'),
        'family_icon' => 'dashicons-update',
        'direct_task_type' => 'enhance_existing_content',
        'pro' => true,
        'items' => [
            [
                'slug' => 'content_enhancement',
                'task_type' => 'enhance_existing_content',
                'label' => __('Rewrite Content', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-edit-large',
                'pro' => true,
            ],
        ],
    ],
    [
        'category' => 'knowledge_base',
        'family_label' => __('Build a knowledge base', 'gpt3-ai-content-generator'),
        'family_description' => __('Add WordPress content to your knowledge base and keep it updated.', 'gpt3-ai-content-generator'),
        'family_icon' => 'dashicons-database',
        'direct_task_type' => 'content_indexing',
        'items' => [
            [
                'slug' => 'knowledge_base',
                'task_type' => 'content_indexing',
                'label' => __('Content Indexing', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-database',
            ],
        ],
    ],
    [
        'category' => 'community_engagement',
        'family_label' => __('Reply to comments', 'gpt3-ai-content-generator'),
        'family_description' => __('Generate replies when new comments are posted.', 'gpt3-ai-content-generator'),
        'family_icon' => 'dashicons-admin-comments',
        'direct_task_type' => 'community_reply_comments',
        'items' => [
            [
                'slug' => 'community_engagement',
                'task_type' => 'community_reply_comments',
                'label' => __('Comment Replies', 'gpt3-ai-content-generator'),
                'icon' => 'dashicons-admin-comments',
            ],
        ],
    ],
];
?>
<div class="aipkit_cw_source_selector_wrapper aipkit_autogpt_intent_selector" data-aipkit-autogpt-category-selector data-template-ready="1" data-view="families">
    <div class="aipkit_autogpt_family_grid" data-aipkit-autogpt-family-grid>
        <?php foreach ($aipkit_autogpt_category_groups as $group) : ?>
            <div class="aipkit_autogpt_family_item" data-aipkit-autogpt-family-item="<?php echo esc_attr($group['category']); ?>">
                <button
                    type="button"
                    class="aipkit_autogpt_family_card"
                    data-aipkit-autogpt-family="<?php echo esc_attr($group['category']); ?>"
                    <?php if (!empty($group['direct_task_type'])) : ?>
                        data-aipkit-autogpt-direct-task-type="<?php echo esc_attr($group['direct_task_type']); ?>"
                    <?php else : ?>
                        data-aipkit-autogpt-default-task-type="content_writing_bulk"
                    <?php endif; ?>
                    aria-pressed="false"
                >
                    <span class="aipkit_autogpt_family_icon dashicons <?php echo esc_attr($group['family_icon']); ?>" aria-hidden="true"></span>
                    <span class="aipkit_autogpt_family_copy">
                        <span class="aipkit_autogpt_choice_title_row">
                            <span class="aipkit_autogpt_family_title"><?php echo esc_html($group['family_label']); ?></span>
                            <?php if (!empty($group['pro']) && !$aipkit_autogpt_is_pro) : ?>
                                <span class="aipkit_autogpt_pro_badge aipkit_pro_badge"><?php esc_html_e('Pro', 'gpt3-ai-content-generator'); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($group['family_description'])) : ?>
                            <span class="aipkit_autogpt_family_desc"><?php echo esc_html($group['family_description']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="aipkit_autogpt_family_check" aria-hidden="true"></span>
                </button>

                <div class="aipkit_autogpt_family_panel" data-aipkit-autogpt-family-slot="<?php echo esc_attr($group['category']); ?>" hidden>
                    <?php if (empty($group['direct_task_type'])) : ?>
                        <div class="aipkit_cw_mode_section aipkit_autogpt_task_choices" data-aipkit-autogpt-task-choices="<?php echo esc_attr($group['category']); ?>">
                            <div class="aipkit_autogpt_compact_choice_row">
                                <span class="aipkit_autogpt_compact_choice_label"><?php esc_html_e('Content source', 'gpt3-ai-content-generator'); ?></span>
                                <div class="aipkit_autogpt_compact_choices" role="group" aria-label="<?php esc_attr_e('Content source', 'gpt3-ai-content-generator'); ?>">
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_source_option" data-aipkit-entry-mode="manual" data-entry-view="batch" data-aipkit-batch-editor-toggle data-category="content_creation" data-task-type="content_writing_bulk" aria-pressed="false"><?php esc_html_e('Batch editor', 'gpt3-ai-content-generator'); ?></button>
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_quick_paste_option" data-entry-view="paste" data-aipkit-paste-topics-toggle data-category="content_creation" data-task-type="content_writing_bulk" aria-pressed="false" aria-expanded="false" aria-controls="aipkit_task_cw_paste_importer"><?php esc_html_e('Quick paste', 'gpt3-ai-content-generator'); ?></button>
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_source_option" data-aipkit-entry-mode="bulk" data-category="content_creation" data-task-type="content_writing_csv" aria-pressed="false"><?php esc_html_e('Import CSV', 'gpt3-ai-content-generator'); ?></button>
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_source_option" data-aipkit-source-group="rss" data-category="content_creation" data-task-type="content_writing_rss" aria-pressed="false" <?php echo !$aipkit_autogpt_is_pro ? 'title="' . esc_attr__('This is a Pro feature.', 'gpt3-ai-content-generator') . '"' : ''; ?>><?php esc_html_e('RSS feed', 'gpt3-ai-content-generator'); ?></button>
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_source_option" data-aipkit-source-group="url" data-category="content_creation" data-task-type="content_writing_url" aria-pressed="false" <?php echo !$aipkit_autogpt_is_pro ? 'title="' . esc_attr__('This is a Pro feature.', 'gpt3-ai-content-generator') . '"' : ''; ?>><?php esc_html_e('URL', 'gpt3-ai-content-generator'); ?></button>
                                    <button type="button" class="aipkit_cw_mode_card aipkit_autogpt_source_option" data-aipkit-source-group="spreadsheet" data-category="content_creation" data-task-type="content_writing_gsheets" aria-pressed="false" <?php echo !$aipkit_autogpt_is_pro ? 'title="' . esc_attr__('This is a Pro feature.', 'gpt3-ai-content-generator') . '"' : ''; ?>><?php esc_html_e('Spreadsheet', 'gpt3-ai-content-generator'); ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
