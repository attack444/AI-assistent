<?php


namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for duplicating an AI form.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_duplicate_ai_form().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_duplicate_form_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $form_id_to_duplicate = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
    if (empty($form_id_to_duplicate)) {
        $handler_instance->send_wp_error(new WP_Error('id_required', __('Form ID is required for duplication.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    // Get all data from the original form
    $original_form_data = $form_storage->get_form_data($form_id_to_duplicate);
    if (is_wp_error($original_form_data)) {
        $handler_instance->send_wp_error($original_form_data);
        return;
    }

    // The get_form_data() returns 'structure' as a PHP array, but the save function expects 'form_structure' as a JSON string.
    if (isset($original_form_data['structure']) && is_array($original_form_data['structure'])) {
        // Re-encode with flags to preserve Unicode characters, preventing them from becoming gibberish on some servers.
        $original_form_data['form_structure'] = wp_json_encode($original_form_data['structure'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        unset($original_form_data['structure']); // Remove the old PHP array key
    }

    // Prepare data for the new form
    $new_title = $original_form_data['title'] . ' (Copy)';

    // The get_form_data() result is now compatible with the settings array needed by save_form_settings(),
    // which is called by create_form().
    $result = $form_storage->create_form($new_title, $original_form_data);

    if (is_wp_error($result)) {
        $handler_instance->send_wp_error($result);
    } else {
        wp_send_json_success(['message' => __('Form duplicated successfully.', 'gpt3-ai-content-generator'), 'new_form_id' => $result]);
    }
}
