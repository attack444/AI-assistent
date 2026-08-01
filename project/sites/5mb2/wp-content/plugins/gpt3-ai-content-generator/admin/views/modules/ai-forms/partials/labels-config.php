<?php

/**
 * Partial: AI Form Editor - Labels & Text Configuration
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="aipkit_popover_options_list aipkit_ai_form_labels_list">
    <section class="aipkit_ai_form_labels_group" aria-labelledby="aipkit_ai_form_labels_buttons_title">
        <h6 class="aipkit_ai_form_labels_group_title" id="aipkit_ai_form_labels_buttons_title">
            <?php esc_html_e('Buttons', 'gpt3-ai-content-generator'); ?>
        </h6>
        <div class="aipkit_ai_form_labels_group_fields">
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_generate_button"><?php esc_html_e('Generate', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_generate_button" name="labels[generate_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Generate', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_stop_button"><?php esc_html_e('Stop', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_stop_button" name="labels[stop_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Stop', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_download_button"><?php esc_html_e('Download', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_download_button" name="labels[download_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Download', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_save_button"><?php esc_html_e('Save', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_save_button" name="labels[save_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Save', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_copy_button"><?php esc_html_e('Copy', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_copy_button" name="labels[copy_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Copy', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
        </div>
    </section>

    <section class="aipkit_ai_form_labels_group" aria-labelledby="aipkit_ai_form_labels_provider_title">
        <h6 class="aipkit_ai_form_labels_group_title" id="aipkit_ai_form_labels_provider_title">
            <?php esc_html_e('Provider display', 'gpt3-ai-content-generator'); ?>
        </h6>
        <div class="aipkit_ai_form_labels_group_fields">
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_provider_label"><?php esc_html_e('Engine', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_provider_label" name="labels[provider_label]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Engine', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                <div class="aipkit_popover_option_main">
                    <label class="aipkit_popover_option_label" for="aif_label_model_label"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></label>
                    <input type="text" id="aif_label_model_label" name="labels[model_label]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Model', 'gpt3-ai-content-generator'); ?>">
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($is_pro)): ?>
        <section
            class="aipkit_ai_form_labels_group aipkit_ai_form_labels_group--multistep"
            data-aipkit-ai-form-labels-group="multistep"
            aria-labelledby="aipkit_ai_form_labels_multistep_title"
            hidden
        >
            <h6 class="aipkit_ai_form_labels_group_title" id="aipkit_ai_form_labels_multistep_title">
                <?php esc_html_e('Multi-Step navigation', 'gpt3-ai-content-generator'); ?>
            </h6>
            <div class="aipkit_ai_form_labels_group_fields">
                <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aif_label_conversation_back_button"><?php esc_html_e('Back', 'gpt3-ai-content-generator'); ?></label>
                        <input type="text" id="aif_label_conversation_back_button" name="labels[conversation_back_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Back', 'gpt3-ai-content-generator'); ?>">
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--inline">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aif_label_conversation_next_button"><?php esc_html_e('Next', 'gpt3-ai-content-generator'); ?></label>
                        <input type="text" id="aif_label_conversation_next_button" name="labels[conversation_next_button]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Next', 'gpt3-ai-content-generator'); ?>">
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--stacked">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aif_label_conversation_step_title"><?php esc_html_e('Step title', 'gpt3-ai-content-generator'); ?></label>
                        <input type="text" id="aif_label_conversation_step_title" name="labels[conversation_step_title]" class="aipkit_popover_option_input aipkit_ai_form_label_input--token" placeholder="<?php esc_attr_e('Step {number}', 'gpt3-ai-content-generator'); ?>">
                    </div>
                </div>
                <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--stacked">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aif_label_conversation_step_progress"><?php esc_html_e('Step progress', 'gpt3-ai-content-generator'); ?></label>
                        <input type="text" id="aif_label_conversation_step_progress" name="labels[conversation_step_progress]" class="aipkit_popover_option_input aipkit_ai_form_label_input--token" placeholder="<?php esc_attr_e('Step {current} of {total}', 'gpt3-ai-content-generator'); ?>">
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($is_pro)): ?>
        <section class="aipkit_ai_form_labels_group" aria-labelledby="aipkit_ai_form_labels_messages_title">
            <h6 class="aipkit_ai_form_labels_group_title" id="aipkit_ai_form_labels_messages_title">
                <?php esc_html_e('Messages', 'gpt3-ai-content-generator'); ?>
            </h6>
            <div class="aipkit_ai_form_labels_group_fields">
                <div class="aipkit_popover_option_row aipkit_ai_form_label_field aipkit_ai_form_label_field--stacked">
                    <div class="aipkit_popover_option_main">
                        <label class="aipkit_popover_option_label" for="aif_label_conversation_validation_message"><?php esc_html_e('Validation msg', 'gpt3-ai-content-generator'); ?></label>
                        <input type="text" id="aif_label_conversation_validation_message" name="labels[conversation_validation_message]" class="aipkit_popover_option_input" placeholder="<?php esc_attr_e('Please complete this step before continuing.', 'gpt3-ai-content-generator'); ?>">
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
