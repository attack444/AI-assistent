<?php


namespace WPAICG\AIForms\Frontend\Shortcode\Renderer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Renders the HTML for a single form field based on its configuration.
 *
 * @param array $element The configuration array for the form element.
 * @param int $form_id The ID of the parent form.
 * @return void Echos the HTML for the field.
 */
function render_field_logic(array $element, int $form_id): void
{
    $field_id_attr = 'aipkit_form_field_' . esc_attr($form_id) . '_' . esc_attr($element['fieldId']);
    $field_name_attr = 'aipkit_form_field[' . esc_attr($element['fieldId']) . ']';
    $is_required = !empty($element['required']);
    $required_attr = $is_required ? 'required' : '';
    $help_text = $element['helpText'] ?? '';

    switch ($element['type']) {
        case 'text-input':
        case 'textarea':
        case 'select':
            echo '<div class="aipkit_form-group">';
            echo '<label for="' . esc_attr($field_id_attr) . '" class="aipkit_form-label">';
            echo esc_html($element['label']);
            if (!empty($element['required'])) {
                echo ' <span class="aipkit-required-indicator" aria-hidden="true">*</span>';
            }
            echo '</label>';
            switch ($element['type']) {
                case 'text-input':
                    echo '<input type="text" id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name_attr) . '" class="aipkit_form-input" placeholder="' . esc_attr($element['placeholder'] ?? '') . '" ' . esc_attr($required_attr) . '>';
                    break;
                case 'textarea':
                    echo '<textarea id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name_attr) . '" class="aipkit_form-input" rows="4" placeholder="' . esc_attr($element['placeholder'] ?? '') . '" ' . esc_attr($required_attr) . '></textarea>';
                    break;
                case 'select':
                    echo '<select id="' . esc_attr($field_id_attr) . '" name="' . esc_attr($field_name_attr) . '" class="aipkit_form-input" ' . esc_attr($required_attr) . '>';
                    if (!empty($element['placeholder'])) {
                        echo '<option value="">' . esc_html($element['placeholder']) . '</option>';
                    }
                    if (!empty($element['options']) && is_array($element['options'])) {
                        foreach ($element['options'] as $option) {
                            echo '<option value="' . esc_attr($option['value']) . '">' . esc_html($option['text']) . '</option>';
                        }
                    }
                    echo '</select>';
                    break;
            }
            if (!empty($help_text)) {
                echo '<p class="aipkit_form-help">' . wp_kses_post($help_text) . '</p>';
            }
            echo '</div>'; // .aipkit_form-group
            break;

        case 'checkbox':
            echo '<fieldset class="aipkit_form-group aipkit-checkbox-group">';
            echo '<legend class="aipkit_form-label">' . esc_html($element['label']);
            if (!empty($element['required'])) {
                echo ' <span class="aipkit-required-indicator" aria-hidden="true">*</span>';
            }
            echo '</legend>';
            if (!empty($element['options']) && is_array($element['options'])) {
                foreach ($element['options'] as $index => $option) {
                    $checkbox_id   = esc_attr($field_id_attr . '_' . $index);
                    $checkbox_name = $field_name_attr . '[]'; // Corrected: Do not escape brackets in the name attribute.
                    $required_data_attr = $is_required ? ' data-is-required="true"' : '';
                    echo '<div class="aipkit-checkbox-item">';
                    echo '<input type="checkbox" id="' . $checkbox_id . '" name="' . $checkbox_name . '" value="' . esc_attr($option['value']) . '" class="aipkit_form-input-checkbox"' . $required_data_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reason: $checkbox_id and $checkbox_name are already escaped/safe at their definitions.
                    echo '<label for="' . $checkbox_id . '">' . esc_html($option['text']) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reason: $checkbox_id is already escaped at its definition.
                    echo '</div>';
                }
            }
            if (!empty($help_text)) {
                echo '<p class="aipkit_form-help">' . wp_kses_post($help_text) . '</p>';
            }
            echo '</fieldset>';
            break;

        case 'radio-button':
            echo '<fieldset class="aipkit_form-group aipkit-radio-group">';
            echo '<legend class="aipkit_form-label">' . esc_html($element['label']);
            if (!empty($element['required'])) {
                echo ' <span class="aipkit-required-indicator" aria-hidden="true">*</span>';
            }
            echo '</legend>';
            if (!empty($element['options']) && is_array($element['options'])) {
                foreach ($element['options'] as $index => $option) {
                    $radio_id = esc_attr($field_id_attr . '_' . $index);
                    echo '<div class="aipkit-radio-item">';
                    echo '<input type="radio" id="' . $radio_id . '" name="' . esc_attr($field_name_attr) . '" value="' . esc_attr($option['value']) . '" ' . esc_attr($required_attr) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reason: $radio_id is already escaped at its definition.
                    echo '<label for="' . $radio_id . '">' . esc_html($option['text']) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Reason: $radio_id is already escaped at its definition.
                    echo '</div>';
                }
            }
            if (!empty($help_text)) {
                echo '<p class="aipkit_form-help">' . wp_kses_post($help_text) . '</p>';
            }
            echo '</fieldset>';
            break;

        case 'file-upload':
            $is_pro = class_exists('\WPAICG\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
            if ($is_pro) {
                $upload_nonce = wp_create_nonce('aipkit_ai_form_upload_nonce');
                echo '<div class="aipkit_form-group aipkit_form_group-file-upload" data-nonce="' . esc_attr($upload_nonce) . '">';
                echo '<label for="' . esc_attr($field_id_attr) . '" class="aipkit_form-label">';
                echo esc_html($element['label']);
                if (!empty($element['required'])) {
                    echo ' <span class="aipkit-required-indicator" aria-hidden="true">*</span>';
                }
                echo '</label>';

                echo '<input type="file" id="' . esc_attr($field_id_attr) . '" class="aipkit_form-input aipkit-file-upload-input" ' . ($required_attr ? 'data-is-required="true"' : '') . ' data-field-id="' . esc_attr($element['fieldId']) . '" accept=".txt,.pdf">';

                echo '<input type="hidden" name="' . esc_attr($field_name_attr) . '" class="aipkit-file-hidden-content" ' . esc_attr($required_attr) . '>';

                echo '<div class="aipkit-file-status-wrapper" style="display:none;">';
                echo '<div class="aipkit-file-upload-status"></div>';
                echo '<button type="button" class="aipkit-file-remove-btn" style="display:none;" title="' . esc_attr__('Remove file', 'gpt3-ai-content-generator') . '">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-minus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l6 0" /></svg>';
                echo '</button>';
                echo '</div>';

                if (!empty($help_text)) {
                    echo '<p class="aipkit_form-help">' . wp_kses_post($help_text) . '</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="aipkit_form-group">';
                echo '<label class="aipkit_form-label">' . esc_html($element['label']) . '</label>';
                echo '<p class="aipkit_form-help"><em>' . esc_html__('File upload is a paid feature.', 'gpt3-ai-content-generator') . '</em></p>';
                echo '</div>';
            }
            break;

        case 'image-upload':
            $is_pro = class_exists('\WPAICG\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
            if ($is_pro) {
                echo '<div class="aipkit_form-group aipkit_form_group-image-upload">';
                echo '<label for="' . esc_attr($field_id_attr) . '" class="aipkit_form-label">';
                echo esc_html($element['label']);
                if (!empty($element['required'])) {
                    echo ' <span class="aipkit-required-indicator" aria-hidden="true">*</span>';
                }
                echo '</label>';

                echo '<input type="file" id="' . esc_attr($field_id_attr) . '" class="aipkit_form-input aipkit-image-upload-input" ' . ($required_attr ? 'data-is-required="true"' : '') . ' data-field-id="' . esc_attr($element['fieldId']) . '" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">';

                echo '<input type="hidden" name="' . esc_attr($field_name_attr) . '" class="aipkit-image-hidden-content" ' . esc_attr($required_attr) . '>';

                echo '<div class="aipkit-file-status-wrapper" style="display:none;">';
                echo '<div class="aipkit-file-upload-status"></div>';
                echo '<button type="button" class="aipkit-image-remove-btn" style="display:none;" title="' . esc_attr__('Remove image', 'gpt3-ai-content-generator') . '">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-minus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l6 0" /></svg>';
                echo '</button>';
                echo '</div>';

                if (!empty($help_text)) {
                    echo '<p class="aipkit_form-help">' . wp_kses_post($help_text) . '</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="aipkit_form-group">';
                echo '<label class="aipkit_form-label">' . esc_html($element['label']) . '</label>';
                echo '<p class="aipkit_form-help"><em>' . esc_html__('Image upload is a paid feature.', 'gpt3-ai-content-generator') . '</em></p>';
                echo '</div>';
            }
            break;
    }
}
