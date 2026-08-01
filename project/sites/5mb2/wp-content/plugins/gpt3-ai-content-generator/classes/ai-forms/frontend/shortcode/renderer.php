<?php

namespace WPAICG\AIForms\Frontend\Shortcode;

use WPAICG\AIForms\Admin\AIPKit_AI_Form_Admin_Setup;
use WPAICG\AIPKit_Providers;
use WP_Error;

// Require the helper for rendering individual fields
require_once __DIR__ . '/renderer/render-field.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Renders the full HTML structure for the AI form.
 *
 * @param array $form_data The form's configuration data.
 * @param string $unique_form_html_id The unique HTML ID for the form wrapper.
 * @param string $ajax_nonce The nonce for the AJAX submission.
 * @param string $theme The theme for the form ('light', 'dark', 'custom').
 * @param bool $show_provider Whether to show the provider dropdown.
 * @param bool $show_model Whether to show the model dropdown.
 * @param bool $show_save_button Whether to show the save button after generation.
 * @param bool $show_pdf_download Whether to show the PDF download button after generation.
 * @param bool $show_copy_button Whether to show the copy button after generation.
 * @param string $custom_css Custom CSS rules to apply for the 'custom' theme.
 * @param string $allowed_providers_str Comma-separated string of allowed providers.
 * @return string The rendered HTML string for the form.
 */
function render_form_html_logic(
    array $form_data,
    string $unique_form_html_id,
    string $ajax_nonce,
    string $theme = 'light',
    bool $show_provider = true,
    bool $show_model = true,
    bool $show_save_button = false,
    bool $show_pdf_download = false,
    bool $show_copy_button = false,
    string $custom_css = '',
    string $allowed_providers_str = ''
): string {
    ob_start();
    $labels = $form_data['labels'] ?? [];
    $save_as_post_nonce = wp_create_nonce('aipkit_ai_form_save_as_post_nonce');
    $conversation_ui_preset = isset($form_data['conversation_ui_preset']) ? sanitize_key((string) $form_data['conversation_ui_preset']) : 'full';
    if (!in_array($conversation_ui_preset, ['full', 'compact', 'minimal', 'none'], true)) {
        $conversation_ui_preset = 'full';
    }
    ?>
    <div 
        class="aipkit-ai-form-wrapper aipkit-theme-<?php echo esc_attr($theme); ?>" 
        id="<?php echo esc_attr($unique_form_html_id); ?>" 
        data-form-id="<?php echo esc_attr($form_data['id']); ?>" 
        data-nonce="<?php echo esc_attr($ajax_nonce); ?>"
        data-show-provider="<?php echo $show_provider ? 'true' : 'false'; ?>"
        data-show-model="<?php echo $show_model ? 'true' : 'false'; ?>"
        data-show-save-button="<?php echo $show_save_button ? 'true' : 'false'; ?>"
        data-pdf-download-enabled="<?php echo $show_pdf_download ? 'true' : 'false'; ?>"
        data-show-copy-button="<?php echo $show_copy_button ? 'true' : 'false'; ?>"
        data-save-as-post-nonce="<?php echo esc_attr($save_as_post_nonce); ?>"
        data-aipkit-conversation-ui-preset="<?php echo esc_attr($conversation_ui_preset); ?>"
        <?php echo apply_filters('aipkit_ai_forms_frontend_wrapper_attributes', '', $form_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Extension attributes must be escaped by the provider. ?>
        <?php foreach ($labels as $key => $value) : ?>
            data-label-<?php echo esc_attr(str_replace('_', '-', $key)); ?>="<?php echo esc_attr($value); ?>"
        <?php endforeach; ?>
    >
        
        <?php if ($theme === 'custom' && !empty($custom_css)): ?>
            <style type="text/css">
                <?php echo wp_strip_all_tags($custom_css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reason: wp_strip_all_tags is used for sanitization. Escaping CSS would break it. ?>
            </style>
        <?php endif; ?>

        <?php if (!empty($form_data['title'])): ?>
            <h5 class="aipkit-ai-form-title"><?php echo esc_html($form_data['title']); ?></h5>
        <?php endif; ?>

        <form class="aipkit-ai-form-main" data-form-id="<?php echo esc_attr($form_data['id']); ?>">
            <?php if ($show_provider || $show_model) : ?>
                <div class="aipkit-ai-form-config-container">
                    <?php if ($show_provider) : ?>
                        <div class="aipkit_form-group">
                            <label class="aipkit_form-label" for="aipkit-aiform-provider-<?php echo esc_attr($form_data['id']); ?>"><?php echo esc_html($labels['provider_label']); ?></label>
                            <select id="aipkit-aiform-provider-<?php echo esc_attr($form_data['id']); ?>" name="aipkit_form_field[ai_provider]" class="aipkit_form-input aipkit_aiform_provider_select">
                                <?php
                                $all_providers = ['OpenAI', 'Google', 'Claude', 'OpenRouter', 'Azure', 'DeepSeek', 'xAI'];
                                if (
                                    class_exists('\\WPAICG\\aipkit_dashboard') &&
                                    \WPAICG\aipkit_dashboard::is_pro_plan()
                                ) {
                                    array_splice($all_providers, 5, 0, ['Ollama']); // before DeepSeek
                                }

                                $allowed_providers = !empty($allowed_providers_str) ? array_map('trim', explode(',', $allowed_providers_str)) : [];
                                $providers_to_show = !empty($allowed_providers) ? array_intersect($all_providers, $allowed_providers) : $all_providers;

                        foreach ($providers_to_show as $provider_name) {
                            echo '<option value="' . esc_attr($provider_name) . '"' . selected($form_data['ai_provider'], $provider_name, false) . '>' . esc_html($provider_name) . '</option>';
                        }
                        ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_model) : ?>
                         <div class="aipkit_form-group">
                            <label class="aipkit_form-label" for="aipkit-aiform-model-<?php echo esc_attr($form_data['id']); ?>"><?php echo esc_html($labels['model_label']); ?></label>
                            <select id="aipkit-aiform-model-<?php echo esc_attr($form_data['id']); ?>" name="aipkit_form_field[ai_model]" class="aipkit_form-input aipkit_aiform_model_select" data-provider="<?php echo esc_attr($form_data['ai_provider']); ?>" data-current-value="<?php echo esc_attr($form_data['ai_model']); ?>">
                                 <option value=""><?php esc_html_e('Loading models...', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                 <hr class="aipkit_hr">
            <?php endif; ?>

            <?php
            if (!empty($form_data['structure']) && is_array($form_data['structure'])) {
                $is_new_structure = isset($form_data['structure'][0]['type']) && $form_data['structure'][0]['type'] === 'layout-row';

                if ($is_new_structure) {
                    foreach ($form_data['structure'] as $row) {
                        if (!isset($row['columns']) || !is_array($row['columns'])) {
                            continue;
                        }
                        $conversation_step = isset($row['aipkitConversationStep']) && is_array($row['aipkitConversationStep'])
                            ? $row['aipkitConversationStep']
                            : [];
                        printf(
                            '<div class="aipkit-form-row" data-aipkit-conversation-enabled="%1$s" data-aipkit-step-title="%2$s" data-aipkit-step-description="%3$s" data-aipkit-condition-field-id="%4$s" data-aipkit-condition-operator="%5$s" data-aipkit-condition-value="%6$s">',
                            !empty($conversation_step['conversationEnabled']) ? '1' : '0',
                            esc_attr((string) ($conversation_step['title'] ?? '')),
                            esc_attr((string) ($conversation_step['description'] ?? '')),
                            esc_attr((string) ($conversation_step['conditionFieldId'] ?? '')),
                            esc_attr((string) ($conversation_step['conditionOperator'] ?? '')),
                            esc_attr((string) ($conversation_step['conditionValue'] ?? ''))
                        );
                        foreach ($row['columns'] as $column) {
                            if (!isset($column['elements']) || !is_array($column['elements'])) {
                                continue;
                            }
                            $width_style = isset($column['width']) ? 'flex-basis: ' . esc_attr($column['width']) . ';' : '';
                            echo '<div class="aipkit-form-column" style="' . esc_attr($width_style) . '">';
                            foreach ($column['elements'] as $element) {
                                \WPAICG\AIForms\Frontend\Shortcode\Renderer\render_field_logic($element, $form_data['id']);
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } else {
                    foreach ($form_data['structure'] as $element) {
                        \WPAICG\AIForms\Frontend\Shortcode\Renderer\render_field_logic($element, $form_data['id']);
                    }
                }
            } else {
                ?>
                <div class="aipkit_form-group">
                    <label for="aipkit_user_input_<?php echo esc_attr($form_data['id']); ?>" class="aipkit_form-label"><?php esc_html_e('Your Input:', 'gpt3-ai-content-generator'); ?></label>
                    <textarea
                        id="aipkit_user_input_<?php echo esc_attr($form_data['id']); ?>"
                        name="aipkit_form_field[user_input]"
                        class="aipkit_form-input"
                        rows="4"
                        placeholder="<?php esc_attr_e('Enter your text here...', 'gpt3-ai-content-generator'); ?>"
                    ></textarea>
                </div>
                <?php
            }
    ?>

            <button type="submit" class="aipkit_btn aipkit_btn-primary">
                <span class="aipkit_btn-text"><?php echo esc_html($labels['generate_button']); ?></span>
                <span class="aipkit_spinner" style="display:none; margin-left: 5px;"></span>
            </button>
        </form>

        <div class="aipkit-ai-form-results" style="display: none;">
             <?php // Content will be injected here by JS?>
        </div>
        <?php do_action('aipkit_ai_forms_frontend_after_results', $form_data); ?>
    </div>
    <?php
    return ob_get_clean();
}
