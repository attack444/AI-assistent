<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load shared variables used by the partials
require_once __DIR__ . '/form-inputs/loader-vars.php';

$content_length_options = [
    'short' => __('Short', 'gpt3-ai-content-generator'),
    'medium' => __('Medium', 'gpt3-ai-content-generator'),
    'long' => __('Long', 'gpt3-ai-content-generator'),
];

?>
<div class="aipkit_cw_inspector_stack">
    <section class="aipkit_cw_inspector_card aipkit_cw_inspector_card--run">
        <div class="aipkit_cw_inspector_card_header">
            <div class="aipkit_cw_inspector_card_header_copy">
                <div class="aipkit_cw_inspector_card_title">
                    <?php esc_html_e('General', 'gpt3-ai-content-generator'); ?>
                </div>
            </div>
        </div>
        <div class="aipkit_cw_inspector_card_body aipkit_cw_inspector_card_body--run">
            <?php include __DIR__ . '/template-controls.php'; ?>
            <?php include __DIR__ . '/form-inputs/ai-settings.php'; ?>
            <div class="aipkit_cw_ai_row">
                <div class="aipkit_cw_panel_label_wrap">
                    <label class="aipkit_cw_panel_label" for="aipkit_content_writer_content_length">
                        <?php esc_html_e('Length', 'gpt3-ai-content-generator'); ?>
                    </label>
                </div>
                <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
                    <select
                        id="aipkit_content_writer_content_length"
                        name="content_length"
                        class="aipkit_autosave_trigger aipkit_form-input aipkit_cw_blended_chevron_select"
                    >
                        <?php foreach ($content_length_options as $content_length_value => $content_length_label) : ?>
                            <option value="<?php echo esc_attr($content_length_value); ?>" <?php selected($content_length_value, 'medium'); ?>>
                                <?php echo esc_html($content_length_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php include __DIR__ . '/form-inputs/publishing-settings.php'; ?>
            <?php include __DIR__ . '/form-inputs/seo-settings.php'; ?>
            <?php include __DIR__ . '/form-inputs/prompts-settings.php'; ?>
        </div>
    </section>

    <section class="aipkit_cw_inspector_card aipkit_cw_inspector_card--media">
        <div class="aipkit_cw_inspector_card_header">
            <div class="aipkit_cw_inspector_card_header_copy">
                <div class="aipkit_cw_inspector_card_title">
                    <?php esc_html_e('Media', 'gpt3-ai-content-generator'); ?>
                </div>
            </div>
        </div>
        <div class="aipkit_cw_inspector_card_body aipkit_cw_inspector_card_body--media">
            <?php include __DIR__ . '/form-inputs/image-settings.php'; ?>
        </div>
    </section>
</div>

<?php include __DIR__ . '/form-inputs/image-prompt-flyouts.php'; ?>
