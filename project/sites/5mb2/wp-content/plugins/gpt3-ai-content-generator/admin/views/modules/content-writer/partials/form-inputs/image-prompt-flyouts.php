<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Shared view partial uses local template variables from parent view.

$render_image_prompt_placeholders = static function (string $prompt_type, array $placeholders): void {
    ?>
    <span
        class="aipkit_cw_prompt_editor_placeholders"
        data-prompt-type="<?php echo esc_attr($prompt_type); ?>"
        data-label="<?php esc_attr_e('Variables:', 'gpt3-ai-content-generator'); ?>"
        data-copy-title="<?php esc_attr_e('Click to copy', 'gpt3-ai-content-generator'); ?>"
    >
        <?php esc_html_e('Variables:', 'gpt3-ai-content-generator'); ?>
        <?php foreach ($placeholders as $placeholder) : ?>
            <code class="aipkit-placeholder" title="<?php esc_attr_e('Click to copy', 'gpt3-ai-content-generator'); ?>"><?php echo esc_html($placeholder); ?></code>
        <?php endforeach; ?>
    </span>
    <?php
};

$image_prompt_flyouts = [
    [
        'flyout_id' => 'aipkit_cw_image_prompt_flyout',
        'label' => __('Image Prompt', 'gpt3-ai-content-generator'),
        'select_id' => 'aipkit_cw_image_prompt_library',
        'target' => 'aipkit_cw_image_prompt',
        'name' => 'image_prompt',
        'rows' => 8,
        'placeholder' => __('e.g., A photo of a freshly baked chocolate cake on a wooden table.', 'gpt3-ai-content-generator'),
        'default_value' => $default_image_prompt,
        'library_key' => 'image',
        'prompt_type' => 'image',
        'placeholders' => ['{topic}', '{keywords}', '{excerpt}', '{post_title}'],
    ],
    [
        'flyout_id' => 'aipkit_cw_image_title_prompt_flyout',
        'label' => __('Image Title Prompt', 'gpt3-ai-content-generator'),
        'target' => 'aipkit_cw_image_title_prompt',
        'name' => 'image_title_prompt',
        'rows' => 6,
        'placeholder' => __('e.g., Golden retriever playing in a park.', 'gpt3-ai-content-generator'),
        'default_value' => $default_image_title_prompt,
        'library_key' => 'image_title',
        'library_update_key' => 'image_title_update',
        'prompt_type' => 'image_title',
        'placeholders' => ['{topic}', '{keywords}', '{post_title}', '{excerpt}', '{file_name}'],
    ],
    [
        'flyout_id' => 'aipkit_cw_image_alt_text_prompt_flyout',
        'label' => __('Alt Text Prompt', 'gpt3-ai-content-generator'),
        'target' => 'aipkit_cw_image_alt_text_prompt',
        'name' => 'image_alt_text_prompt',
        'rows' => 6,
        'placeholder' => __('e.g., Dog running through tall grass at sunset.', 'gpt3-ai-content-generator'),
        'default_value' => $default_image_alt_text_prompt,
        'library_key' => 'image_alt_text',
        'library_update_key' => 'image_alt_text_update',
        'prompt_type' => 'image_alt_text',
        'placeholders' => ['{topic}', '{keywords}', '{post_title}', '{excerpt}', '{file_name}'],
    ],
    [
        'flyout_id' => 'aipkit_cw_image_caption_prompt_flyout',
        'label' => __('Caption Prompt', 'gpt3-ai-content-generator'),
        'target' => 'aipkit_cw_image_caption_prompt',
        'name' => 'image_caption_prompt',
        'rows' => 6,
        'placeholder' => __('e.g., A quiet morning in the mountains.', 'gpt3-ai-content-generator'),
        'default_value' => $default_image_caption_prompt,
        'library_key' => 'image_caption',
        'library_update_key' => 'image_caption_update',
        'prompt_type' => 'image_caption',
        'placeholders' => ['{topic}', '{keywords}', '{post_title}', '{excerpt}', '{file_name}'],
    ],
    [
        'flyout_id' => 'aipkit_cw_image_description_prompt_flyout',
        'label' => __('Description Prompt', 'gpt3-ai-content-generator'),
        'target' => 'aipkit_cw_image_description_prompt',
        'name' => 'image_description_prompt',
        'rows' => 6,
        'placeholder' => __('e.g., A detailed scene of a cat lounging in sunlight.', 'gpt3-ai-content-generator'),
        'default_value' => $default_image_description_prompt,
        'library_key' => 'image_description',
        'library_update_key' => 'image_description_update',
        'prompt_type' => 'image_description',
        'placeholders' => ['{topic}', '{keywords}', '{post_title}', '{excerpt}', '{file_name}'],
    ],
    [
        'flyout_id' => 'aipkit_cw_featured_image_prompt_flyout',
        'label' => __('Featured Image Prompt', 'gpt3-ai-content-generator'),
        'select_id' => 'aipkit_cw_featured_image_prompt_library',
        'target' => 'aipkit_cw_featured_image_prompt',
        'name' => 'featured_image_prompt',
        'rows' => 8,
        'placeholder' => __('Leave blank to use the main image prompt.', 'gpt3-ai-content-generator'),
        'default_value' => $default_featured_image_prompt,
        'library_key' => 'featured_image',
        'prompt_type' => 'featured_image',
        'placeholders' => ['{topic}', '{post_title}', '{excerpt}', '{keywords}'],
    ],
];

foreach ($image_prompt_flyouts as $flyout) :
    $label = (string) $flyout['label'];
    $target = (string) $flyout['target'];
    $select_id = (string) ($flyout['select_id'] ?? '');
    $library_key = (string) $flyout['library_key'];
    $library_update_key = (string) ($flyout['library_update_key'] ?? '');
    ?>
    <div class="aipkit_cw_prompt_flyout" id="<?php echo esc_attr((string) $flyout['flyout_id']); ?>" aria-hidden="true">
        <div class="aipkit_cw_prompt_panel aipkit_cw_prompt_panel--tall" role="dialog" aria-label="<?php echo esc_attr($label); ?>">
            <div class="aipkit_cw_prompt_editor">
                <div class="aipkit_cw_prompt_editor_toolbar">
                    <span class="aipkit_cw_prompt_editor_title"><?php echo esc_html($label); ?></span>
                    <select
                        <?php echo $select_id !== '' ? 'id="' . esc_attr($select_id) . '"' : ''; ?>
                        class="aipkit_cw_prompt_template_select aipkit_cw_prompt_library_select"
                        data-aipkit-prompt-target="<?php echo esc_attr($target); ?>"
                        title="<?php esc_attr_e('Load template', 'gpt3-ai-content-generator'); ?>"
                    >
                        <option value="<?php echo esc_attr((string) $flyout['default_value']); ?>"<?php echo $library_update_key !== '' ? ' data-aipkit-mode="both"' : ''; ?>><?php esc_html_e('Default', 'gpt3-ai-content-generator'); ?></option>
                        <?php
                        if ($library_update_key !== '') {
                            $render_prompt_library_options($prompt_library[$library_key] ?? [], 'create');
                            $render_prompt_library_options($prompt_library[$library_update_key] ?? [], 'update');
                        } else {
                            $render_prompt_library_options($prompt_library[$library_key] ?? []);
                        }
                        ?>
                    </select>
                </div>
                <textarea
                    id="<?php echo esc_attr($target); ?>"
                    name="<?php echo esc_attr((string) $flyout['name']); ?>"
                    class="aipkit_cw_prompt_editor_textarea aipkit_autosave_trigger"
                    rows="<?php echo esc_attr((string) $flyout['rows']); ?>"
                    placeholder="<?php echo esc_attr((string) $flyout['placeholder']); ?>"
                ></textarea>
                <div class="aipkit_cw_prompt_editor_footer">
                    <?php $render_image_prompt_placeholders((string) $flyout['prompt_type'], $flyout['placeholders']); ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
