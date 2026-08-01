<?php

namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Admin_Setup;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deletes only the AI Forms explicitly supplied by the user.
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_delete_selected_forms_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();
    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $raw_form_ids = isset($_POST['form_ids']) ? sanitize_text_field(wp_unslash($_POST['form_ids'])) : '';
    $form_ids = json_decode($raw_form_ids, true);
    $form_ids = is_array($form_ids)
        ? array_values(array_unique(array_filter(array_map('absint', $form_ids))))
        : [];

    if (empty($form_ids)) {
        $handler_instance->send_wp_error(new WP_Error('form_ids_required', __('Select at least one form to delete.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $deleted_ids = [];
    $failed_ids = [];
    foreach ($form_ids as $form_id) {
        if (get_post_type($form_id) !== AIPKit_AI_Form_Admin_Setup::POST_TYPE) {
            $failed_ids[] = $form_id;
            continue;
        }

        if (!$form_storage->delete_form($form_id)) {
            $failed_ids[] = $form_id;
            continue;
        }

        apply_filters('aipkit_ai_forms_after_delete_form_messages', [], $form_id, $form_storage);
        $deleted_ids[] = $form_id;
    }

    if (empty($deleted_ids)) {
        $handler_instance->send_wp_error(new WP_Error('delete_selected_failed', __('The selected forms could not be deleted.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    $error_message = '';
    if (!empty($failed_ids)) {
        $error_message = sprintf(
            /* translators: %d is the number of forms that could not be deleted. */
            _n('%d form could not be deleted.', '%d forms could not be deleted.', count($failed_ids), 'gpt3-ai-content-generator'),
            count($failed_ids)
        );
    }

    wp_send_json_success([
        'error_message' => $error_message,
        'deleted_ids' => $deleted_ids,
        'failed_ids' => $failed_ids,
    ]);
}
