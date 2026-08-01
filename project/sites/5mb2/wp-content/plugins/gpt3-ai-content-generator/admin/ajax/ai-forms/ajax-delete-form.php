<?php

namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for deleting an AI form.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_delete_ai_form().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_delete_form_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $form_id = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
    if (empty($form_id)) {
        $handler_instance->send_wp_error(new WP_Error('id_required', __('Form ID is required for deletion.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $deleted = $form_storage->delete_form($form_id);
    if ($deleted) {
        $extra_messages = apply_filters('aipkit_ai_forms_after_delete_form_messages', [], $form_id, $form_storage);
        $message = __('Form deleted successfully.', 'gpt3-ai-content-generator');
        if (is_array($extra_messages) && !empty($extra_messages)) {
            $message .= ' ' . implode(' ', array_map('sanitize_text_field', $extra_messages));
        }
        wp_send_json_success(['message' => $message]);
    } else {
        $handler_instance->send_wp_error(new WP_Error('delete_failed', __('Failed to delete form.', 'gpt3-ai-content-generator')), 500);
    }
}
