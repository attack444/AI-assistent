<?php


namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for importing AI forms from a JSON file.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_import_ai_forms().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_import_forms_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing_import', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $forms_json = isset($_POST['forms_json']) ? wp_kses_post(wp_unslash($_POST['forms_json'])) : '{}';
    if (empty($forms_json)) {
        $handler_instance->send_wp_error(new WP_Error('no_data_import', __('No form data received for import.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $forms_to_import = json_decode($forms_json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($forms_to_import)) {
        $handler_instance->send_wp_error(new WP_Error('invalid_json_import', __('Invalid JSON data received for import.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $imported_count = 0;
    $failed_count = 0;
    $errors = [];
    $imported_records = [];

    foreach ($forms_to_import as $form_data) {
        $title = isset($form_data['title']) ? sanitize_text_field($form_data['title']) : 'Imported Form';

        // Sanitize settings before creating the form
        // This is a minimal sanitization; a more robust one could be implemented.
        $settings = $form_data;
        unset($settings['title'], $settings['id'], $settings['status']); // Remove fields not used in creation

        // The export file has 'structure' as an array, but the save function expects 'form_structure' as a JSON string.
        if (isset($settings['structure']) && is_array($settings['structure'])) {
            // Re-encode with flags to preserve Unicode characters, preventing them from becoming gibberish on some servers.
            $settings['form_structure'] = wp_json_encode($settings['structure'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            unset($settings['structure']); // Remove the old PHP array key
        }

        $settings = apply_filters('aipkit_ai_forms_import_form_settings_before_create', $settings, $form_data, $forms_to_import, $form_storage);

        // Append "(Imported)" to avoid direct title conflicts, making management easier.
        $new_title = $title . ' (Imported)';

        $result = $form_storage->create_form($new_title, $settings);

        if (is_wp_error($result)) {
            $failed_count++;
            /* translators: 1: The form title. 2: The specific error message. */
            $errors[] = sprintf(__('Failed to import form "%1$s": %2$s', 'gpt3-ai-content-generator'), esc_html($title), $result->get_error_message());
        } else {
            $imported_count++;
            $imported_records[] = [
                'new_id' => (int) $result,
                'source' => $form_data,
                'title' => $title,
            ];
        }
    }
    /* translators: %d is the number of forms imported */
    $message = sprintf(_n('%d form was imported successfully.', '%d forms were imported successfully.', $imported_count, 'gpt3-ai-content-generator'), $imported_count);

    if ($failed_count > 0) {
        /* translators: %d is the number of forms that failed to import */
        $message .= ' ' . sprintf(_n('%d form failed to import.','%d forms failed to import.',$failed_count,'gpt3-ai-content-generator'),$failed_count);
    }

    $extra_messages = apply_filters('aipkit_ai_forms_import_result_messages', [], $imported_records, $forms_to_import, $form_storage);
    if (is_array($extra_messages) && !empty($extra_messages)) {
        $message .= ' ' . implode(' ', array_map('sanitize_text_field', $extra_messages));
    }

    wp_send_json_success(['message' => $message, 'imported_count' => $imported_count, 'failed_count' => $failed_count]);
}
