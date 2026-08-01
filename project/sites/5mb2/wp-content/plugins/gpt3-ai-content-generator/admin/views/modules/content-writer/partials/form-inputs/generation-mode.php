<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
// Variables from loader-vars.php: $is_pro
$render_cw_bulk_row = static function () use ($wp_categories, $users_for_author, $available_post_types) {
    ?>
    <div class="aipkit_cw_bulk_row" data-aipkit-bulk-row>
        <div class="aipkit_cw_bulk_row_main">
            <label class="aipkit_cw_bulk_field aipkit_cw_bulk_field--topic">
                <input type="text" class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_input--topic aipkit_autosave_trigger" data-bulk-field="topic" placeholder="<?php esc_attr_e('Enter topic...', 'gpt3-ai-content-generator'); ?>" aria-label="<?php esc_attr_e('Topic', 'gpt3-ai-content-generator'); ?>">
            </label>
            <label class="aipkit_cw_bulk_field aipkit_cw_bulk_field--keywords-inline">
                <input type="text" class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_input--keywords-inline aipkit_autosave_trigger" data-bulk-field="keywords" placeholder="<?php esc_attr_e('Enter keywords', 'gpt3-ai-content-generator'); ?>" aria-label="<?php esc_attr_e('Keywords', 'gpt3-ai-content-generator'); ?>">
            </label>
            <div class="aipkit_cw_bulk_row_actions">
                <button
                    type="button"
                    class="aipkit_cw_bulk_toggle_row_details"
                    aria-expanded="false"
                    aria-label="<?php esc_attr_e('Advanced fields', 'gpt3-ai-content-generator'); ?>"
                    title="<?php esc_attr_e('Advanced fields', 'gpt3-ai-content-generator'); ?>"
                >
                    <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                </button>
                <button type="button" class="aipkit_cw_bulk_remove_row" aria-label="<?php esc_attr_e('Remove row', 'gpt3-ai-content-generator'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <div class="aipkit_cw_bulk_row_details">
            <label class="aipkit_cw_bulk_detail_field">
                <span class="aipkit_cw_bulk_detail_label"><?php esc_html_e('Category', 'gpt3-ai-content-generator'); ?></span>
                <select class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_detail aipkit_autosave_trigger" data-bulk-field="category" aria-label="<?php esc_attr_e('Category', 'gpt3-ai-content-generator'); ?>">
                    <option value=""><?php esc_html_e('Category', 'gpt3-ai-content-generator'); ?></option>
                    <?php foreach ($wp_categories as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="aipkit_cw_bulk_detail_field">
                <span class="aipkit_cw_bulk_detail_label"><?php esc_html_e('Author', 'gpt3-ai-content-generator'); ?></span>
                <select class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_detail aipkit_autosave_trigger" data-bulk-field="author" aria-label="<?php esc_attr_e('Author', 'gpt3-ai-content-generator'); ?>">
                    <option value=""><?php esc_html_e('Author', 'gpt3-ai-content-generator'); ?></option>
                    <?php foreach ($users_for_author as $user): ?>
                        <option value="<?php echo esc_attr($user->user_login); ?>" data-user-id="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="aipkit_cw_bulk_detail_field">
                <span class="aipkit_cw_bulk_detail_label"><?php esc_html_e('Post Type', 'gpt3-ai-content-generator'); ?></span>
                <select class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_detail aipkit_autosave_trigger" data-bulk-field="type" aria-label="<?php esc_attr_e('Post Type', 'gpt3-ai-content-generator'); ?>">
                    <option value=""><?php esc_html_e('Post', 'gpt3-ai-content-generator'); ?></option>
                    <?php foreach ($available_post_types as $pt_slug => $pt_obj): ?>
                        <option value="<?php echo esc_attr($pt_slug); ?>"><?php echo esc_html($pt_obj->label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="aipkit_cw_bulk_detail_field">
                <span class="aipkit_cw_bulk_detail_label"><?php esc_html_e('Schedule', 'gpt3-ai-content-generator'); ?></span>
                <input type="datetime-local" class="aipkit_form-input aipkit_cw_bulk_input aipkit_cw_bulk_detail aipkit_autosave_trigger" data-bulk-field="schedule" aria-label="<?php esc_attr_e('Schedule', 'gpt3-ai-content-generator'); ?>">
            </label>
        </div>
    </div>
    <?php
};
?>
<div class="aipkit_cw_mode_container" data-template-ready="0">
    <input type="hidden" id="aipkit_content_writer_title" name="content_title" value="" class="aipkit_autosave_trigger">
    
    <div class="aipkit_cw_mode_panel">
        <div class="aipkit_cw_tab_content_container">
            <div class="aipkit_cw_tab_content aipkit_active" data-pane="task">
                <div class="aipkit_cw_bulk_source_panel" data-aipkit-bulk-source-panel="task">
                    <div class="aipkit_cw_task_entry_shell">
                        <div class="aipkit_cw_task_entry_header">
                            <div class="aipkit_cw_task_entry_top">
                                <h3 class="aipkit_cw_task_entry_title"><?php esc_html_e('Start writing', 'gpt3-ai-content-generator'); ?></h3>
                                <div class="aipkit_cw_task_entry_switch" role="group" aria-label="<?php esc_attr_e('Manual entry layout', 'gpt3-ai-content-generator'); ?>">
                                    <button type="button" class="aipkit_cw_task_entry_switch_btn is-active" data-aipkit-task-entry-tab="single" aria-pressed="true">
                                        <?php esc_html_e('Single', 'gpt3-ai-content-generator'); ?>
                                    </button>
                                    <button type="button" class="aipkit_cw_task_entry_switch_btn" data-aipkit-task-entry-tab="batch" aria-pressed="false">
                                        <?php esc_html_e('Batch Editor', 'gpt3-ai-content-generator'); ?>
                                        <span class="aipkit_cw_task_entry_switch_count" data-aipkit-task-entry-batch-count hidden>0</span>
                                    </button>
                                    <button type="button" class="aipkit_cw_task_entry_switch_btn" data-aipkit-task-entry-tab="paste" aria-pressed="false">
                                        <?php esc_html_e('Quick Paste', 'gpt3-ai-content-generator'); ?>
                                    </button>
                                </div>
                            </div>
                            <p class="aipkit_cw_task_entry_desc" data-aipkit-task-entry-mode-desc>
                                <?php esc_html_e('Focus on one article with a single topic and keyword set.', 'gpt3-ai-content-generator'); ?>
                            </p>
                        </div>

                        <p class="aipkit_cw_task_entry_notice" data-aipkit-task-entry-note hidden>
                            <?php esc_html_e('Multiple topics detected. Switch to Batch Editor or Quick Paste until the queue is back to one line.', 'gpt3-ai-content-generator'); ?>
                        </p>

                        <div class="aipkit_cw_task_entry_panel aipkit_cw_task_entry_panel--single" data-aipkit-task-entry-panel="single">
                            <div class="aipkit_cw_single_compose_card">
                                <label class="aipkit_cw_single_compose_field aipkit_cw_single_compose_field--topic" for="aipkit_cw_single_compose_topic">
                                    <span class="aipkit_cw_single_compose_label"><?php esc_html_e('Topic', 'gpt3-ai-content-generator'); ?></span>
                                    <textarea id="aipkit_cw_single_compose_topic" class="aipkit_form-input aipkit_cw_single_compose_input aipkit_cw_single_compose_input--topic" rows="3" placeholder="<?php esc_attr_e('Describe the article you want to generate...', 'gpt3-ai-content-generator'); ?>"></textarea>
                                </label>

                                <label class="aipkit_cw_single_compose_field" for="aipkit_cw_single_compose_keywords">
                                    <span class="aipkit_cw_single_compose_label"><?php esc_html_e('Keywords', 'gpt3-ai-content-generator'); ?></span>
                                    <input type="text" id="aipkit_cw_single_compose_keywords" class="aipkit_form-input aipkit_cw_single_compose_input" placeholder="<?php esc_attr_e('Primary keywords, separated by commas', 'gpt3-ai-content-generator'); ?>">
                                </label>
                            </div>
                        </div>

                        <div class="aipkit_cw_task_entry_panel aipkit_cw_task_entry_panel--batch" data-aipkit-task-entry-panel="batch" hidden>
                            <div class="aipkit_cw_bulk_editor" data-aipkit-bulk-editor>
                                <div class="aipkit_cw_bulk_rows" data-aipkit-bulk-rows>
                                    <?php for ($i = 0; $i < 3; $i++): ?>
                                        <?php $render_cw_bulk_row(); ?>
                                    <?php endfor; ?>
                                </div>
                                <template id="aipkit_cw_bulk_row_template">
                                    <?php $render_cw_bulk_row(); ?>
                                </template>
                            </div>
                        </div>

                        <div class="aipkit_cw_task_entry_panel aipkit_cw_task_entry_panel--paste" data-aipkit-task-entry-panel="paste" hidden>
                            <div class="aipkit_cw_paste_panel">
                                <div class="aipkit_cw_paste_field aipkit_cw_single_compose_field">
                                    <label class="aipkit_cw_single_compose_label" for="aipkit_cw_bulk_topics"><?php esc_html_e('Topics', 'gpt3-ai-content-generator'); ?></label>
                                    <textarea id="aipkit_cw_bulk_topics" name="content_title_bulk" class="aipkit_form-input aipkit_autosave_trigger aipkit_cw_paste_textarea" rows="6" placeholder="<?php esc_attr_e("How to frost cupcakes | frosting, dessert", 'gpt3-ai-content-generator'); ?>"></textarea>
                                </div>
                                <div class="aipkit_cw_paste_footer">
                                    <div class="aipkit_csv_help_content">
                                        <div class="aipkit_csv_columns_row">
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Topic', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                            <span class="aipkit_csv_column_divider">→</span>
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Keywords', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                            <span class="aipkit_csv_column_divider">→</span>
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Category ID', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                            <span class="aipkit_csv_column_divider">→</span>
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Author Login', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                            <span class="aipkit_csv_column_divider">→</span>
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Post Type', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                            <span class="aipkit_csv_column_divider">→</span>
                                            <div class="aipkit_csv_column_chip">
                                                <span class="aipkit_csv_column_label"><?php esc_html_e('Schedule', 'gpt3-ai-content-generator'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="aipkit_cw_tab_content" data-pane="csv">
                <div class="aipkit_csv_import_container aipkit_cw_source_mode_shell aipkit_cw_source_mode_shell--csv">
                    <div class="aipkit_cw_source_mode_header">
                        <h3 class="aipkit_cw_source_mode_title"><?php esc_html_e('Import CSV', 'gpt3-ai-content-generator'); ?></h3>
                        <p class="aipkit_cw_source_mode_desc"><?php esc_html_e('Upload a CSV of topics and optional metadata to generate content in bulk.', 'gpt3-ai-content-generator'); ?></p>
                    </div>

                    <div class="aipkit_cw_source_mode_stage">
                        <div class="aipkit_csv_upload_zone" data-csv-upload-zone>
                            <label for="aipkit_cw_csv_file_input" class="aipkit_csv_dropzone">
                                <span class="aipkit_csv_dropzone_icon" aria-hidden="true">
                                    <span class="dashicons dashicons-upload"></span>
                                </span>
                                <span class="aipkit_csv_dropzone_text">
                                    <span class="aipkit_csv_dropzone_primary"><?php esc_html_e('Drop your CSV file here', 'gpt3-ai-content-generator'); ?></span>
                                    <span class="aipkit_csv_dropzone_secondary"><?php esc_html_e('or click to browse', 'gpt3-ai-content-generator'); ?></span>
                                </span>
                                <input
                                    type="file"
                                    id="aipkit_cw_csv_file_input"
                                    name="csv_file_input"
                                    class="aipkit_csv_file_input_hidden"
                                    accept=".csv, text/csv"
                                >
                            </label>
                        </div>

                        <div class="aipkit_csv_status_container" id="aipkit_cw_csv_status_container" data-csv-status hidden>
                            <div class="aipkit_csv_status_card" data-csv-status-card>
                                <div class="aipkit_csv_status_icon" data-csv-status-icon>
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                </div>
                                <div class="aipkit_csv_status_content">
                                    <span class="aipkit_csv_file_name" data-csv-file-name></span>
                                    <span id="aipkit_cw_csv_analysis_results" class="aipkit_csv_analysis_results" data-csv-message></span>
                                </div>
                                <button type="button" class="aipkit_csv_clear_btn" data-csv-clear aria-label="<?php esc_attr_e('Remove file', 'gpt3-ai-content-generator'); ?>">
                                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <textarea name="content_title_csv" id="aipkit_cw_csv_data_holder" class="aipkit_csv_data_holder" style="display: none;" readonly></textarea>

                    <div class="aipkit_cw_source_mode_footer aipkit_csv_help_content" data-csv-help>
                        <div class="aipkit_cw_source_mode_footer_label"><?php esc_html_e('Expected columns', 'gpt3-ai-content-generator'); ?></div>
                        <div class="aipkit_csv_columns_row">
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Topic', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <span class="aipkit_csv_column_divider">→</span>
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Keywords', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <span class="aipkit_csv_column_divider">→</span>
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Category', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <span class="aipkit_csv_column_divider">→</span>
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Author Login', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <span class="aipkit_csv_column_divider">→</span>
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Post Type', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <span class="aipkit_csv_column_divider">→</span>
                            <div class="aipkit_csv_column_chip">
                                <span class="aipkit_csv_column_label"><?php esc_html_e('Schedule', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <a
                                href="https://docs.google.com/spreadsheets/d/1WOnO_UKkbRCoyjRxQnDDTy0i-RsnrY_MDKD3Ks09JJk/edit?usp=sharing"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="aipkit_csv_sample_link"
                            >
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <?php esc_html_e('Download sample CSV', 'gpt3-ai-content-generator'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="aipkit_cw_tab_content" data-pane="rss">
                <?php include __DIR__ . '/mode-rss.php'; ?>
            </div>
            <div class="aipkit_cw_tab_content" data-pane="url">
                <?php include __DIR__ . '/mode-url.php'; ?>
            </div>
            <div class="aipkit_cw_tab_content" data-pane="gsheets">
                <?php include __DIR__ . '/mode-gsheets.php'; ?>
            </div>
            <div class="aipkit_cw_tab_content" data-pane="existing">
                <div class="aipkit_cw_existing_panel" data-aipkit-existing-panel>
                    <div class="aipkit_cw_existing_controls">
                        <div class="aipkit_cw_existing_filters" data-aipkit-existing-filter="type">
                            <label class="aipkit_form-label" for="aipkit_cw_existing_post_type"><?php esc_html_e('Type', 'gpt3-ai-content-generator'); ?></label>
                            <select id="aipkit_cw_existing_post_type" class="aipkit_form-input">
                                <option value=""><?php esc_html_e('All types', 'gpt3-ai-content-generator'); ?></option>
                                <?php foreach ($available_post_types as $pt_slug => $pt_obj): ?>
                                    <option value="<?php echo esc_attr($pt_slug); ?>"><?php echo esc_html($pt_obj->label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aipkit_cw_existing_filters" data-aipkit-existing-filter="status">
                            <label class="aipkit_form-label" for="aipkit_cw_existing_post_status"><?php esc_html_e('Status', 'gpt3-ai-content-generator'); ?></label>
                            <select id="aipkit_cw_existing_post_status" class="aipkit_form-input">
                                <option value=""><?php esc_html_e('Any status', 'gpt3-ai-content-generator'); ?></option>
                                <option value="publish"><?php esc_html_e('Published', 'gpt3-ai-content-generator'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'gpt3-ai-content-generator'); ?></option>
                                <option value="pending"><?php esc_html_e('Pending', 'gpt3-ai-content-generator'); ?></option>
                                <option value="future"><?php esc_html_e('Scheduled', 'gpt3-ai-content-generator'); ?></option>
                                <option value="private"><?php esc_html_e('Private', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                        <div class="aipkit_cw_existing_filters" data-aipkit-existing-filter="media">
                            <label class="aipkit_form-label" for="aipkit_cw_existing_media_filter"><?php esc_html_e('Media', 'gpt3-ai-content-generator'); ?></label>
                            <select id="aipkit_cw_existing_media_filter" class="aipkit_form-input">
                                <option value=""><?php esc_html_e('All media items', 'gpt3-ai-content-generator'); ?></option>
                                <option value="image"><?php esc_html_e('Images', 'gpt3-ai-content-generator'); ?></option>
                                <option value="detached"><?php esc_html_e('Unattached', 'gpt3-ai-content-generator'); ?></option>
                                <option value="mine"><?php esc_html_e('Mine', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                        <div class="aipkit_cw_existing_search">
                            <label class="aipkit_form-label" for="aipkit_cw_existing_search"><?php esc_html_e('Search', 'gpt3-ai-content-generator'); ?></label>
                            <input
                                type="search"
                                id="aipkit_cw_existing_search"
                                class="aipkit_form-input"
                                placeholder="<?php esc_attr_e('Search by title...', 'gpt3-ai-content-generator'); ?>"
                            >
                        </div>
                    </div>

                    <div class="aipkit_cw_existing_list">
                        <div class="aipkit_cw_existing_table_wrap">
                            <table class="aipkit_cw_existing_table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="aipkit_cw_existing_col_check">
                                            <label class="screen-reader-text" for="aipkit_cw_existing_select_all"><?php esc_html_e('Select all', 'gpt3-ai-content-generator'); ?></label>
                                            <input type="checkbox" id="aipkit_cw_existing_select_all">
                                        </th>
                                        <th scope="col" class="aipkit_cw_existing_col_title"><?php esc_html_e('Title', 'gpt3-ai-content-generator'); ?></th>
                                        <th scope="col" class="aipkit_cw_existing_col_type"><?php esc_html_e('Type', 'gpt3-ai-content-generator'); ?></th>
                                        <th scope="col" class="aipkit_cw_existing_col_status"><?php esc_html_e('Status', 'gpt3-ai-content-generator'); ?></th>
                                        <th scope="col" class="aipkit_cw_existing_col_alt"><?php esc_html_e('Alt', 'gpt3-ai-content-generator'); ?></th>
                                        <th scope="col" class="aipkit_cw_existing_col_caption"><?php esc_html_e('Caption', 'gpt3-ai-content-generator'); ?></th>
                                        <th scope="col" class="aipkit_cw_existing_col_description"><?php esc_html_e('Description', 'gpt3-ai-content-generator'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aipkit_cw_existing_posts_body">
                                    <tr class="aipkit_cw_existing_empty">
                                        <td colspan="7"><?php esc_html_e('Select filters to load posts.', 'gpt3-ai-content-generator'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="aipkit_cw_existing_pagination" id="aipkit_cw_existing_pagination">
                            <div class="aipkit_cw_existing_page_size">
                                <label class="screen-reader-text" for="aipkit_cw_existing_per_page"><?php esc_html_e('Rows per page', 'gpt3-ai-content-generator'); ?></label>
                                <select id="aipkit_cw_existing_per_page" class="aipkit_form-input">
                                    <option value="10"><?php esc_html_e('10', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="25"><?php esc_html_e('25', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="50"><?php esc_html_e('50', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="100"><?php esc_html_e('100', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="200"><?php esc_html_e('200', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="500"><?php esc_html_e('500', 'gpt3-ai-content-generator'); ?></option>
                                    <option value="1000"><?php esc_html_e('1000', 'gpt3-ai-content-generator'); ?></option>
                                </select>
                            </div>
                            <div class="aipkit_cw_existing_page_nav">
                                <button type="button" class="button button-secondary aipkit_btn aipkit_btn-secondary aipkit_cw_button_match" id="aipkit_cw_existing_page_prev" disabled>
                                    <?php esc_html_e('Previous', 'gpt3-ai-content-generator'); ?>
                                </button>
                                <span class="aipkit_cw_existing_page_status" id="aipkit_cw_existing_page_status"><?php esc_html_e('Page 1 of 1', 'gpt3-ai-content-generator'); ?></span>
                                <button type="button" class="button button-secondary aipkit_btn aipkit_btn-secondary aipkit_cw_button_match" id="aipkit_cw_existing_page_next" disabled>
                                    <?php esc_html_e('Next', 'gpt3-ai-content-generator'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="aipkit_cw_existing_footer">
                        <span class="aipkit_cw_existing_selected" id="aipkit_cw_existing_selected_count"><?php esc_html_e('0 selected', 'gpt3-ai-content-generator'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="aipkit_cw_action_dock aipkit_cw_action_dock--dock">
            <div class="aipkit_cw_action_dock_primary">
                <span id="aipkit_cw_action_validation" class="aipkit_cw_action_validation" aria-live="polite"></span>
            </div>
            <div class="aipkit_cw_action_dock_actions">
                <select id="aipkit_cw_task_frequency" name="task_frequency" class="aipkit_cw_task_frequency" aria-hidden="true" tabindex="-1" hidden>
                    <?php foreach ($task_frequencies as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'daily'); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="aipkit_cw_action_shell" data-aipkit-cw-primary-action="generate">
                    <button type="button" id="aipkit_content_writer_generate_btn" class="aipkit_cw_action_primary">
                        <span class="aipkit_cw_action_timer" aria-hidden="true" hidden></span>
                        <span class="dashicons dashicons-rss aipkit_cw_action_icon" aria-hidden="true" hidden></span>
                        <span class="aipkit_btn-text"><?php esc_html_e('Generate', 'gpt3-ai-content-generator'); ?></span>
                        <span class="aipkit_cw_action_suffix" hidden></span>
                        <span class="aipkit_spinner" style="display:none;"></span>
                    </button>
                    <button
                        type="button"
                        class="aipkit_cw_action_disclosure"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="aipkit_cw_action_menu"
                    >
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('More actions', 'gpt3-ai-content-generator'); ?></span>
                    </button>
                    <div id="aipkit_cw_action_menu" class="aipkit_cw_action_menu" role="menu" hidden>
                        <div class="aipkit_cw_action_menu_panel" data-menu-panel="actions">
                            <button type="button" class="aipkit_cw_action_menu_option is-active" data-action="generate" role="menuitemradio" aria-checked="true">
                                <?php esc_html_e('Generate', 'gpt3-ai-content-generator'); ?>
                            </button>
                            <button type="button" class="aipkit_cw_action_menu_option" data-action="create_task" role="menuitemradio" aria-checked="false">
                                <?php esc_html_e('Create Task', 'gpt3-ai-content-generator'); ?>
                            </button>
                        </div>
                        <div class="aipkit_cw_action_menu_panel" data-menu-panel="intervals" hidden>
                            <button type="button" class="aipkit_cw_action_menu_back" data-menu-back>
                                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                <?php esc_html_e('Back', 'gpt3-ai-content-generator'); ?>
                            </button>
                            <?php foreach ($task_frequencies as $value => $label): ?>
                                <button type="button" class="aipkit_cw_action_menu_option" data-interval="<?php echo esc_attr($value); ?>" role="menuitemradio" aria-checked="<?php echo $value === 'daily' ? 'true' : 'false'; ?>">
                                    <?php echo esc_html($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
