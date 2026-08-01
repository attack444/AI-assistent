<?php

/**
 * Partial: Community Engagement Automated Task - Comment Reply Settings
 *
 * @since NEXT_VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_cc_prompt_items = \WPAICG\AutoGPT\Helpers\AIPKit_AutoGPT_Prompt_Definitions::get_comment_reply_prompt_items();
$aipkit_cc_reply_prompt_item = $aipkit_cc_prompt_items[0] ?? [];
if ($aipkit_cc_reply_prompt_item) {
    $aipkit_cc_reply_prompt_item['static_placeholders'] = true;
}
?>
<div
    id="aipkit_task_config_comment_reply_settings"
    data-aipkit-inline-prompts="comments"
    data-aipkit-inline-prompt-layout="rows"
>
    <div class="aipkit_cc_filter_rows">
        <div class="aipkit_cc_setting_row">
            <label class="aipkit_cc_field_label" for="aipkit_task_comment_reply_include_keywords"><?php esc_html_e('Only reply if comment contains', 'gpt3-ai-content-generator'); ?></label>
            <textarea id="aipkit_task_comment_reply_include_keywords" name="include_keywords" class="aipkit_form-input aipkit_cc_textarea" rows="1" placeholder="<?php esc_attr_e('e.g., question, help, how to', 'gpt3-ai-content-generator'); ?>"></textarea>
        </div>

        <div class="aipkit_cc_setting_row">
            <label class="aipkit_cc_field_label" for="aipkit_task_comment_reply_exclude_keywords"><?php esc_html_e('Do not reply if comment contains', 'gpt3-ai-content-generator'); ?></label>
            <textarea id="aipkit_task_comment_reply_exclude_keywords" name="exclude_keywords" class="aipkit_form-input aipkit_cc_textarea" rows="1" placeholder="<?php esc_attr_e('e.g., spam, offer, http', 'gpt3-ai-content-generator'); ?>"></textarea>
        </div>

        <?php if ($aipkit_cc_reply_prompt_item) : ?>
            <div
                class="aipkit_cc_setting_row aipkit_cc_setting_row--reply-prompt aipkit_autogpt_content_field"
                data-aipkit-content-field
                data-aipkit-prompt-key="reply"
                data-aipkit-instruction-label="<?php esc_attr_e('Reply Prompt', 'gpt3-ai-content-generator'); ?>"
            >
                <span class="aipkit_cc_field_label"><?php esc_html_e('Reply Prompt', 'gpt3-ai-content-generator'); ?></span>
                <button
                    type="button"
                    class="aipkit_autogpt_content_prompt_trigger"
                    data-aipkit-row-prompt-toggle
                    aria-controls="aipkit_task_cc_instructions_modal"
                    aria-haspopup="dialog"
                >
                    <span
                        data-aipkit-row-prompt-status
                        data-built-in-label="<?php esc_attr_e('Instructions', 'gpt3-ai-content-generator'); ?>"
                        data-custom-label="<?php esc_attr_e('Custom instructions', 'gpt3-ai-content-generator'); ?>"
                    ><?php esc_html_e('Instructions', 'gpt3-ai-content-generator'); ?></span>
                    <span class="dashicons dashicons-edit aipkit_autogpt_content_prompt_icon" aria-hidden="true"></span>
                </button>

                <div
                    id="aipkit_task_cc_reply_instruction_panel"
                    class="aipkit_autogpt_content_prompt_panel"
                    data-aipkit-row-prompt-panel
                    hidden
                >
                    <?php
                    $aipkit_inline_prompt_items = [$aipkit_cc_reply_prompt_item];
                    include dirname(__DIR__) . '/content-writing/inline-prompt-editor-list.php';
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div
        id="aipkit_task_cc_instructions_modal"
        class="aipkit_autogpt_instructions_modal"
        data-aipkit-instructions-modal
        data-title-template="<?php
        /* translators: %s: content field name. */
        echo esc_attr(__('%s instructions', 'gpt3-ai-content-generator'));
        ?>"
        aria-hidden="true"
        hidden
    >
        <div
            class="aipkit_autogpt_instructions_modal_panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="aipkit_task_cc_instructions_modal_title"
            aria-describedby="aipkit_task_cc_instructions_modal_description"
        >
            <div class="aipkit_autogpt_instructions_modal_header">
                <div class="aipkit_autogpt_instructions_modal_heading">
                    <h2 id="aipkit_task_cc_instructions_modal_title" data-aipkit-instructions-modal-title>
                        <?php esc_html_e('Reply Prompt instructions', 'gpt3-ai-content-generator'); ?>
                    </h2>
                    <p id="aipkit_task_cc_instructions_modal_description">
                        <?php esc_html_e('Tell AI how to reply to every matching comment.', 'gpt3-ai-content-generator'); ?>
                    </p>
                </div>
                <button
                    type="button"
                    class="aipkit_autogpt_instructions_modal_close"
                    data-aipkit-instructions-modal-close
                    aria-label="<?php esc_attr_e('Close instructions', 'gpt3-ai-content-generator'); ?>"
                >
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
            <div class="aipkit_autogpt_instructions_modal_body" data-aipkit-instructions-modal-body></div>
            <div class="aipkit_autogpt_instructions_modal_footer">
                <button type="button" class="aipkit_btn aipkit_btn-secondary" data-aipkit-instructions-modal-cancel>
                    <?php esc_html_e('Cancel', 'gpt3-ai-content-generator'); ?>
                </button>
                <button type="button" class="aipkit_btn aipkit_btn-primary" data-aipkit-instructions-modal-save>
                    <?php esc_html_e('Save', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
