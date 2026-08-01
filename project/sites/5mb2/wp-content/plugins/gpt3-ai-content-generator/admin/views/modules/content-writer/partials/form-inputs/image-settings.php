<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

use WPAICG\ContentWriter\AIPKit_Content_Writer_Prompts;

$prompt_library = AIPKit_Content_Writer_Prompts::get_prompt_library();
$default_image_prompt = AIPKit_Content_Writer_Prompts::get_default_image_prompt();
$default_featured_image_prompt = AIPKit_Content_Writer_Prompts::get_default_featured_image_prompt();
$default_image_title_prompt = AIPKit_Content_Writer_Prompts::get_default_image_title_prompt();
$default_image_alt_text_prompt = AIPKit_Content_Writer_Prompts::get_default_image_alt_text_prompt();
$default_image_caption_prompt = AIPKit_Content_Writer_Prompts::get_default_image_caption_prompt();
$default_image_description_prompt = AIPKit_Content_Writer_Prompts::get_default_image_description_prompt();
$default_image_title_prompt_update = AIPKit_Content_Writer_Prompts::get_default_image_title_prompt_update();
$default_image_alt_text_prompt_update = AIPKit_Content_Writer_Prompts::get_default_image_alt_text_prompt_update();
$default_image_caption_prompt_update = AIPKit_Content_Writer_Prompts::get_default_image_caption_prompt_update();
$default_image_description_prompt_update = AIPKit_Content_Writer_Prompts::get_default_image_description_prompt_update();
$render_prompt_library_options = static function(array $options, string $mode = ''): void {
    foreach ($options as $option) {
        if (empty($option['label']) || empty($option['prompt'])) {
            continue;
        }
        echo '<option value="' . esc_attr($option['prompt']) . '"';
        if ($mode !== '') {
            echo ' data-aipkit-mode="' . esc_attr($mode) . '"';
        }
        echo '>' . esc_html($option['label']) . '</option>';
    }
};
?>

<input type="hidden" name="image_prompt_update" id="aipkit_cw_image_prompt_update" value="">
<input type="hidden" name="featured_image_prompt_update" id="aipkit_cw_featured_image_prompt_update" value="">
<input type="hidden" name="image_title_prompt_update" id="aipkit_cw_image_title_prompt_update" value="<?php echo esc_attr($default_image_title_prompt_update); ?>">
<input type="hidden" name="image_alt_text_prompt_update" id="aipkit_cw_image_alt_text_prompt_update" value="<?php echo esc_attr($default_image_alt_text_prompt_update); ?>">
<input type="hidden" name="image_caption_prompt_update" id="aipkit_cw_image_caption_prompt_update" value="<?php echo esc_attr($default_image_caption_prompt_update); ?>">
<input type="hidden" name="image_description_prompt_update" id="aipkit_cw_image_description_prompt_update" value="<?php echo esc_attr($default_image_description_prompt_update); ?>">

<div class="aipkit_cw_image_section">
    <div class="aipkit_cw_image_hidden_fields" hidden aria-hidden="true">
        <input
            type="checkbox"
            id="aipkit_cw_generate_images_enabled"
            name="generate_images_enabled"
            class="aipkit_toggle_switch aipkit_cw_image_enable_toggle aipkit_autosave_trigger"
            value="1"
            tabindex="-1"
        >
        <input
            type="checkbox"
            id="aipkit_cw_generate_featured_image"
            name="generate_featured_image"
            class="aipkit_toggle_switch aipkit_autosave_trigger"
            value="1"
            tabindex="-1"
        >
        <select id="aipkit_cw_image_provider" name="image_provider" class="aipkit_autosave_trigger" tabindex="-1">
            <optgroup label="<?php echo esc_attr__('AI Providers', 'gpt3-ai-content-generator'); ?>">
                <option value="openai" selected>OpenAI</option>
                <option value="google">Google</option>
                <option value="openrouter">OpenRouter</option>
                <option value="azure">Azure</option>
                <option value="xai">xAI</option>
                <option value="replicate"><?php esc_html_e('Replicate', 'gpt3-ai-content-generator'); ?></option>
            </optgroup>
            <optgroup label="<?php echo esc_attr__('Stock Photos', 'gpt3-ai-content-generator'); ?>">
                <option value="pexels"><?php esc_html_e('Pexels', 'gpt3-ai-content-generator'); ?></option>
                <option value="pixabay"><?php esc_html_e('Pixabay', 'gpt3-ai-content-generator'); ?></option>
            </optgroup>
        </select>
        <select id="aipkit_cw_image_model" name="image_model" class="aipkit_autosave_trigger" tabindex="-1">
            <?php // Populated by JS ?>
        </select>
        <input
            type="hidden"
            id="aipkit_cw_image_provider_options"
            name="image_provider_options"
            class="aipkit_autosave_trigger"
            value="{}"
        >
    </div>

    <div class="aipkit_cw_image_row aipkit_cw_image_row--mode">
        <div class="aipkit_cw_panel_label_wrap">
            <label class="aipkit_cw_panel_label" for="aipkit_cw_image_mode_control">
                <?php esc_html_e('Images', 'gpt3-ai-content-generator'); ?>
            </label>
        </div>
        <div class="aipkit_cw_image_control">
            <select id="aipkit_cw_image_mode_control" class="aipkit_form-input aipkit_cw_blended_chevron_select">
                <option value="off"><?php esc_html_e('Off', 'gpt3-ai-content-generator'); ?></option>
                <option value="content"><?php esc_html_e('Content', 'gpt3-ai-content-generator'); ?></option>
                <option value="featured"><?php esc_html_e('Featured', 'gpt3-ai-content-generator'); ?></option>
                <option value="both"><?php esc_html_e('Content + Featured', 'gpt3-ai-content-generator'); ?></option>
            </select>
        </div>
    </div>

    <div class="aipkit_cw_image_row aipkit_cw_image_row--source" id="aipkit_cw_image_source_row" hidden>
        <div class="aipkit_cw_panel_label_wrap">
            <label class="aipkit_cw_panel_label" for="aipkit_content_writer_image_selection">
                <?php esc_html_e('Image source', 'gpt3-ai-content-generator'); ?>
            </label>
        </div>
        <div class="aipkit_cw_image_control aipkit_cw_image_control--selection">
            <div class="aipkit_cw_image_selection_inline">
                <select
                    id="aipkit_content_writer_image_selection"
                    class="aipkit_form-input"
                    data-aipkit-picker-title="<?php echo esc_attr__('Image source', 'gpt3-ai-content-generator'); ?>"
                >
                    <?php // Populated by JS ?>
                </select>
                <?php $aipkit_cw_image_display_settings_render_mode = 'trigger'; ?>
                <?php include __DIR__ . '/image-display-settings.php'; ?>
            </div>
        </div>
    </div>

    <div class="aipkit_cw_image_row aipkit_cw_image_row--settings" id="aipkit_cw_image_settings_row" hidden>
        <div class="aipkit_cw_panel_label_wrap">
            <span class="aipkit_cw_panel_label">
                <?php esc_html_e('Settings', 'gpt3-ai-content-generator'); ?>
            </span>
        </div>
        <div class="aipkit_cw_image_control aipkit_cw_image_control--settings">
            <button
                type="button"
                class="aipkit_cw_settings_icon_trigger"
                id="aipkit_cw_image_display_settings_trigger_fallback"
                data-aipkit-popover-target="aipkit_cw_image_display_settings_popover"
                data-aipkit-popover-placement="top"
                aria-controls="aipkit_cw_image_display_settings_popover"
                aria-expanded="false"
                aria-label="<?php esc_attr_e('Image settings', 'gpt3-ai-content-generator'); ?>"
                title="<?php esc_attr_e('Image settings', 'gpt3-ai-content-generator'); ?>"
            >
                <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</div>

<?php $aipkit_cw_image_display_settings_render_mode = 'popover'; ?>
<?php include __DIR__ . '/image-display-settings.php'; ?>
