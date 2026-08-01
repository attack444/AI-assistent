<?php
/**
 * Partial: Content Enhancement Automated Task - Fields and instructions.
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_ce_prompt_items = \WPAICG\AutoGPT\Helpers\AIPKit_AutoGPT_Prompt_Definitions::get_content_enhancement_prompt_items();

foreach ($aipkit_ce_prompt_items as &$aipkit_ce_prompt_item) {
    if (!empty($aipkit_ce_prompt_item['toggle']['name'])) {
        $aipkit_ce_prompt_item['visibility_control'] = (string) $aipkit_ce_prompt_item['toggle']['name'];
    }
    $aipkit_ce_prompt_item['static_placeholders'] = true;
}
unset($aipkit_ce_prompt_item);
?>

<div
    class="aipkit_autogpt_content_fields"
    data-aipkit-inline-prompts="rewrite"
    data-aipkit-inline-prompt-layout="rows"
>
    <?php foreach ($aipkit_ce_prompt_items as $aipkit_ce_prompt_item) : ?>
        <?php
        $aipkit_ce_prompt_key = sanitize_key((string) ($aipkit_ce_prompt_item['key'] ?? ''));
        $aipkit_ce_prompt_label = (string) ($aipkit_ce_prompt_item['label'] ?? '');
        $aipkit_ce_prompt_toggle = isset($aipkit_ce_prompt_item['toggle']) && is_array($aipkit_ce_prompt_item['toggle'])
            ? $aipkit_ce_prompt_item['toggle']
            : [];
        $aipkit_ce_has_toggle = !empty($aipkit_ce_prompt_toggle['id']) && !empty($aipkit_ce_prompt_toggle['name']);
        $aipkit_ce_toggle_checked = !$aipkit_ce_has_toggle || !empty($aipkit_ce_prompt_toggle['checked']);
        $aipkit_ce_visibility_control = (string) ($aipkit_ce_prompt_item['visibility_control'] ?? '');
        $aipkit_ce_prompt_panel_id = 'aipkit_task_ce_' . $aipkit_ce_prompt_key . '_instruction_panel';
        if ($aipkit_ce_prompt_key === '' || $aipkit_ce_prompt_label === '') {
            continue;
        }
        ?>
        <div
            class="aipkit_autogpt_content_field"
            data-aipkit-content-field
            data-aipkit-prompt-key="<?php echo esc_attr($aipkit_ce_prompt_key); ?>"
            <?php echo $aipkit_ce_visibility_control !== '' ? ' data-aipkit-visibility-control="' . esc_attr($aipkit_ce_visibility_control) . '"' : ''; ?>
        >
            <div class="aipkit_autogpt_content_field_row">
                <span class="aipkit_autogpt_content_field_label_wrap">
                    <span class="aipkit_autogpt_content_field_label"><?php echo esc_html($aipkit_ce_prompt_label); ?></span>
                </span>
                <div class="aipkit_autogpt_content_field_controls">
                    <button
                        type="button"
                        class="aipkit_autogpt_content_prompt_trigger"
                        data-aipkit-row-prompt-toggle
                        aria-controls="aipkit_task_ce_instructions_modal"
                        aria-haspopup="dialog"
                        <?php echo $aipkit_ce_toggle_checked ? '' : ' hidden'; ?>
                    >
                        <span
                            data-aipkit-row-prompt-status
                            data-built-in-label="<?php esc_attr_e('Instructions', 'gpt3-ai-content-generator'); ?>"
                            data-custom-label="<?php esc_attr_e('Custom instructions', 'gpt3-ai-content-generator'); ?>"
                        ><?php esc_html_e('Instructions', 'gpt3-ai-content-generator'); ?></span>
                        <span class="dashicons dashicons-edit aipkit_autogpt_content_prompt_icon" aria-hidden="true"></span>
                    </button>

                    <?php if ($aipkit_ce_has_toggle) : ?>
                        <label class="aipkit_switch aipkit_autogpt_content_field_switch" aria-label="<?php
                        /* translators: %s: content field name. */
                        echo esc_attr(sprintf(__('Update %s', 'gpt3-ai-content-generator'), $aipkit_ce_prompt_label));
                        ?>">
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr((string) $aipkit_ce_prompt_toggle['id']); ?>"
                                name="<?php echo esc_attr((string) $aipkit_ce_prompt_toggle['name']); ?>"
                                value="1"
                                <?php checked(!empty($aipkit_ce_prompt_toggle['checked'])); ?>
                            >
                            <span class="aipkit_switch_slider"></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div
                id="<?php echo esc_attr($aipkit_ce_prompt_panel_id); ?>"
                class="aipkit_autogpt_content_prompt_panel"
                data-aipkit-row-prompt-panel
                hidden
            >
                <?php
                $aipkit_inline_prompt_items = [$aipkit_ce_prompt_item];
                include dirname(__DIR__) . '/content-writing/inline-prompt-editor-list.php';
                ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div
        id="aipkit_task_ce_instructions_modal"
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
            aria-labelledby="aipkit_task_ce_instructions_modal_title"
            aria-describedby="aipkit_task_ce_instructions_modal_description"
        >
            <div class="aipkit_autogpt_instructions_modal_header">
                <div class="aipkit_autogpt_instructions_modal_heading">
                    <h2 id="aipkit_task_ce_instructions_modal_title" data-aipkit-instructions-modal-title>
                        <?php esc_html_e('Instructions', 'gpt3-ai-content-generator'); ?>
                    </h2>
                    <p id="aipkit_task_ce_instructions_modal_description">
                        <?php esc_html_e('Tell AI how to rewrite this part of every post.', 'gpt3-ai-content-generator'); ?>
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
