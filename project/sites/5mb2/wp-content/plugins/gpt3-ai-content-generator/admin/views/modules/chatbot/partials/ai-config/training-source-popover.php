<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables and values passed by the parent view.
$aipkit_default_training_post_types = ['page', 'post'];
?>
<div
    id="aipkit_training_source_popover"
    class="aipkit_training_source_popover"
    data-aipkit-training-source-popover
    role="dialog"
    aria-label="<?php esc_attr_e('Add knowledge source', 'gpt3-ai-content-generator'); ?>"
    hidden
>
    <div class="aipkit_training_source_picker" data-aipkit-training-source-picker>
        <p class="aipkit_training_source_picker_label">
            <?php esc_html_e('What kind of source?', 'gpt3-ai-content-generator'); ?>
        </p>
        <?php
        $aipkit_training_source_picker_options = [
            'website' => [
                'icon' => 'dashicons-admin-site-alt3',
                'title' => __('From my website', 'gpt3-ai-content-generator'),
                'description' => __('Sync posts, pages, or products.', 'gpt3-ai-content-generator'),
            ],
            'qa' => [
                'icon' => 'dashicons-format-chat',
                'title' => __('Write a Q&A', 'gpt3-ai-content-generator'),
                'description' => __('Add a question and answer.', 'gpt3-ai-content-generator'),
            ],
            'text' => [
                'icon' => 'dashicons-media-text',
                'title' => __('Paste text', 'gpt3-ai-content-generator'),
                'description' => __('Add raw text content.', 'gpt3-ai-content-generator'),
            ],
            'files' => [
                'icon' => 'dashicons-paperclip',
                'title' => __('Upload files', 'gpt3-ai-content-generator'),
                'description' => __('PDF, TXT, or DOCX.', 'gpt3-ai-content-generator'),
            ],
        ];
        foreach ($aipkit_training_source_picker_options as $aipkit_training_source_key => $aipkit_training_source_option) :
            ?>
            <button
                type="button"
                class="aipkit_training_source_picker_option"
                data-aipkit-training-source-option="<?php echo esc_attr($aipkit_training_source_key); ?>"
            >
                <span class="aipkit_training_source_picker_icon dashicons <?php echo esc_attr($aipkit_training_source_option['icon']); ?>" aria-hidden="true"></span>
                <span class="aipkit_training_source_picker_copy">
                    <strong><?php echo esc_html($aipkit_training_source_option['title']); ?></strong>
                    <span><?php echo esc_html($aipkit_training_source_option['description']); ?></span>
                </span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="aipkit_training_source_form" data-aipkit-training-source-form hidden>
        <div class="aipkit_training_source_tabs" role="tablist" aria-label="<?php esc_attr_e('Knowledge source type', 'gpt3-ai-content-generator'); ?>">
            <?php
            $aipkit_training_source_tabs = [
                'website' => ['dashicons-admin-site-alt3', __('Website', 'gpt3-ai-content-generator')],
                'qa' => ['dashicons-format-chat', __('Q&A', 'gpt3-ai-content-generator')],
                'text' => ['dashicons-media-text', __('Text', 'gpt3-ai-content-generator')],
                'files' => ['dashicons-paperclip', __('Files', 'gpt3-ai-content-generator')],
            ];
            foreach ($aipkit_training_source_tabs as $aipkit_training_source_key => $aipkit_training_source_tab) :
                ?>
                <button
                    type="button"
                    class="aipkit_training_source_tab"
                    data-aipkit-training-source-option="<?php echo esc_attr($aipkit_training_source_key); ?>"
                    role="tab"
                    aria-selected="false"
                >
                    <span class="dashicons <?php echo esc_attr($aipkit_training_source_tab[0]); ?>" aria-hidden="true"></span>
                    <span><?php echo esc_html($aipkit_training_source_tab[1]); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <select
            id="aipkit_training_other_source_type"
            class="aipkit_form-input aipkit_training_other_source_select"
            data-aipkit-training-source-select
            aria-hidden="true"
            tabindex="-1"
        >
            <option value="qa"><?php esc_html_e('Q&A', 'gpt3-ai-content-generator'); ?></option>
            <option value="text"><?php esc_html_e('Text', 'gpt3-ai-content-generator'); ?></option>
            <option value="files"><?php esc_html_e('Files', 'gpt3-ai-content-generator'); ?></option>
        </select>

        <div class="aipkit_training_website_panel" data-aipkit-training-website-panel hidden>
            <div class="aipkit_training_source_form_heading">
                <strong><?php esc_html_e('Include content types', 'gpt3-ai-content-generator'); ?></strong>
            </div>
            <div id="aipkit_wp_content_bulk_panel" class="aipkit_training_site_field aipkit_training_site_field--menu">
                <div class="aipkit_training_site_dropdown" data-aipkit-training-types="bulk">
                    <div id="aipkit_training_types_menu_bulk" class="aipkit_training_site_dropdown_panel">
                        <div id="aipkit_vs_wp_types_checkboxes" class="aipkit_training_site_checks aipkit_training_site_checks--dropdown">
                            <?php foreach ($all_selectable_post_types as $post_type_slug => $post_type_obj) : ?>
                                <label class="aipkit_training_site_check" data-ptype="<?php echo esc_attr($post_type_slug); ?>">
                                    <input type="checkbox" class="aipkit_wp_type_cb" value="<?php echo esc_attr($post_type_slug); ?>" <?php checked(in_array($post_type_slug, $aipkit_default_training_post_types, true)); ?> />
                                    <span class="aipkit_training_site_check_label"><?php echo esc_html($post_type_obj->label); ?></span>
                                    <span class="aipkit_count_badge" data-count="-1"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <select id="aipkit_vs_wp_content_post_types" class="aipkit_training_site_hidden_select" multiple size="3">
                    <?php foreach ($all_selectable_post_types as $post_type_slug => $post_type_obj) : ?>
                        <option value="<?php echo esc_attr($post_type_slug); ?>" <?php selected(in_array($post_type_slug, $aipkit_default_training_post_types, true)); ?>>
                            <?php echo esc_html($post_type_obj->label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="aipkit_training_sheet_footer">
                <span class="aipkit_training_status" data-aipkit-training-status aria-live="polite"></span>
                <button
                    type="button"
                    class="aipkit_training_action_btn aipkit_training_decision_start"
                    data-training-action="add"
                    data-aipkit-training-start
                    data-aipkit-training-main-action
                >
                    <span class="aipkit_training_action_spinner" aria-hidden="true"></span>
                    <span class="aipkit_training_action_text"><?php esc_html_e('Sync', 'gpt3-ai-content-generator'); ?></span>
                </button>
            </div>
        </div>

        <div class="aipkit_builder_tab_panels aipkit_builder_tab_panels--training aipkit_training_other_panels" data-aipkit-training-other-panels hidden>
            <div class="aipkit_builder_tab_panel" data-aipkit-panel="qa" hidden>
                <div class="aipkit_builder_training_qa">
                    <div class="aipkit_training_field">
                        <div class="aipkit_training_field_heading">
                            <label class="aipkit_training_field_label" for="aipkit_training_qa_question"><?php esc_html_e('Question', 'gpt3-ai-content-generator'); ?></label>
                            <span class="aipkit_training_field_help"><?php esc_html_e('What visitors may ask.', 'gpt3-ai-content-generator'); ?></span>
                        </div>
                        <textarea id="aipkit_training_qa_question" class="aipkit_builder_textarea aipkit_training_textarea" rows="2" placeholder="<?php esc_attr_e('What is your return policy?', 'gpt3-ai-content-generator'); ?>"></textarea>
                        <div class="aipkit_training_common_questions">
                            <button type="button" class="aipkit_training_common_toggle" data-aipkit-common-questions-toggle aria-expanded="false" aria-controls="aipkit_training_common_questions_list">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <span class="aipkit_training_common_toggle_label"><?php esc_html_e('Add from common questions', 'gpt3-ai-content-generator'); ?></span>
                            </button>
                            <div id="aipkit_training_common_questions_list" class="aipkit_training_common_panel" data-aipkit-common-questions-panel hidden>
                                <?php
                                $aipkit_common_training_questions = [
                                    __('What is your return policy?', 'gpt3-ai-content-generator'),
                                    __('What is your refund policy?', 'gpt3-ai-content-generator'),
                                    __('What is your shipping info?', 'gpt3-ai-content-generator'),
                                    __('What is your warranty info?', 'gpt3-ai-content-generator'),
                                    __('What are your payment options?', 'gpt3-ai-content-generator'),
                                    __("What's your phone number?", 'gpt3-ai-content-generator'),
                                    __('What is your address?', 'gpt3-ai-content-generator'),
                                    __('What is your email?', 'gpt3-ai-content-generator'),
                                    __('What are your business hours?', 'gpt3-ai-content-generator'),
                                ];
                                foreach ($aipkit_common_training_questions as $aipkit_common_training_question) :
                                    ?>
                                    <button type="button" class="aipkit_training_common_question" data-aipkit-common-question="<?php echo esc_attr($aipkit_common_training_question); ?>">
                                        <?php echo esc_html($aipkit_common_training_question); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="aipkit_training_field">
                        <div class="aipkit_training_field_heading">
                            <label class="aipkit_training_field_label" for="aipkit_training_qa_answer"><?php esc_html_e('Answer', 'gpt3-ai-content-generator'); ?></label>
                            <span class="aipkit_training_field_help"><?php esc_html_e('The response your chatbot gives.', 'gpt3-ai-content-generator'); ?></span>
                        </div>
                        <textarea id="aipkit_training_qa_answer" class="aipkit_builder_textarea aipkit_training_textarea" rows="3" placeholder="<?php esc_attr_e('We offer refunds within 30 days of purchase.', 'gpt3-ai-content-generator'); ?>"></textarea>
                    </div>
                </div>
            </div>

            <div class="aipkit_builder_tab_panel" data-aipkit-panel="text" hidden>
                <div class="aipkit_training_field">
                    <div class="aipkit_training_field_heading">
                        <label class="aipkit_training_field_label" for="aipkit_training_text_input"><?php esc_html_e('Content', 'gpt3-ai-content-generator'); ?></label>
                    </div>
                    <textarea id="aipkit_training_text_input" name="training_text" class="aipkit_builder_textarea aipkit_training_textarea aipkit_training_text_input" rows="5" placeholder="<?php esc_attr_e('Paste any text you want the chatbot to know.', 'gpt3-ai-content-generator'); ?>"></textarea>
                </div>
            </div>

            <div class="aipkit_builder_tab_panel" data-aipkit-panel="files" hidden>
                <div class="aipkit_training_field">
                    <div class="aipkit_builder_dropzone aipkit_training_dropzone">
                        <div class="aipkit_builder_dropzone_inner">
                            <span class="dashicons dashicons-upload aipkit_training_dropzone_icon" aria-hidden="true"></span>
                            <div class="aipkit_training_dropzone_copy">
                                <strong><?php esc_html_e('Drop files or browse', 'gpt3-ai-content-generator'); ?></strong>
                                <span><?php esc_html_e('PDF, DOCX, TXT, MD, CSV, or JSON', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <?php if ($is_pro_plan) : ?>
                                <input id="aipkit_training_files_input" class="aipkit_training_files_input" type="file" multiple accept=".pdf,.docx,.txt,.md,.csv,.json" hidden>
                                <button type="button" class="aipkit_btn aipkit_btn-secondary aipkit_builder_action_btn aipkit_training_files_button"><?php esc_html_e('Browse', 'gpt3-ai-content-generator'); ?></button>
                            <?php else : ?>
                                <a class="aipkit_btn aipkit_btn-primary aipkit_builder_action_btn aipkit_training_files_button aipkit_pro_upgrade_button" href="<?php echo esc_url($pricing_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="aipkit_training_file_queue" data-aipkit-training-file-queue hidden>
                    <div class="aipkit_training_file_queue_header">
                        <span><?php esc_html_e('Selected files', 'gpt3-ai-content-generator'); ?></span>
                        <span class="aipkit_training_file_queue_count" data-aipkit-training-file-count aria-live="polite"></span>
                    </div>
                    <div
                        class="aipkit_training_file_list"
                        id="aipkit_training_file_list"
                        data-upgrade-url="<?php echo esc_url($pricing_url); ?>"
                        role="list"
                        aria-label="<?php esc_attr_e('Files selected for upload', 'gpt3-ai-content-generator'); ?>"
                    ></div>
                </div>
            </div>
        </div>

        <div class="aipkit_training_sheet_footer" data-aipkit-training-other-footer hidden>
            <span class="aipkit_training_status aipkit_training_sheet_status" data-aipkit-training-status aria-live="polite"></span>
            <button
                type="button"
                class="aipkit_training_action_btn aipkit_training_decision_start aipkit_training_sheet_start"
                data-training-action="add"
                data-aipkit-training-start
                data-aipkit-training-sheet-action
            >
                <span class="aipkit_training_action_spinner" aria-hidden="true"></span>
                <span class="aipkit_training_action_text"><?php esc_html_e('Add source', 'gpt3-ai-content-generator'); ?></span>
            </button>
        </div>
    </div>
    <div
        class="aipkit_training_discard_prompt"
        data-aipkit-training-discard-prompt
        role="alertdialog"
        aria-modal="false"
        aria-labelledby="aipkit_chatbot_training_discard_title"
        aria-describedby="aipkit_chatbot_training_discard_message"
        hidden
    >
        <div class="aipkit_training_discard_panel">
            <h3 class="aipkit_training_discard_title" id="aipkit_chatbot_training_discard_title">
                <?php esc_html_e('Discard this source?', 'gpt3-ai-content-generator'); ?>
            </h3>
            <p class="aipkit_training_discard_message" id="aipkit_chatbot_training_discard_message">
                <?php esc_html_e('Your source has not been added yet and will be lost.', 'gpt3-ai-content-generator'); ?>
            </p>
            <div class="aipkit_training_discard_actions">
                <button type="button" class="aipkit_btn aipkit_btn-secondary aipkit_training_discard_keep">
                    <?php esc_html_e('Keep editing', 'gpt3-ai-content-generator'); ?>
                </button>
                <button type="button" class="aipkit_btn aipkit_btn-danger aipkit_training_discard_confirm">
                    <?php esc_html_e('Discard', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
