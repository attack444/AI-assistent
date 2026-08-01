<?php


namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Admin_Setup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends an AI Forms export for the supplied validated scope.
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function send_ai_forms_export(
    AIPKit_AI_Form_Ajax_Handler $handler_instance,
    array $form_ids,
    string $scope
): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    $form_ids = array_values(array_unique(array_filter(array_map('absint', $form_ids))));

    if (empty($form_ids)) {
        wp_send_json_error(['message' => __('No forms found to export.', 'gpt3-ai-content-generator')], 404);
        return;
    }

    $exported_forms = [];
    foreach ($form_ids as $form_id) {
        if (get_post_type($form_id) !== AIPKit_AI_Form_Admin_Setup::POST_TYPE) {
            continue;
        }
        $form_data = $form_storage->get_form_data($form_id);
        if (!is_wp_error($form_data)) {
            $form_data = apply_filters('aipkit_ai_forms_prepare_form_export_data', $form_data, [
                'scope' => $scope,
                'form_id' => (int) $form_id,
                'form_ids' => array_map('intval', $form_ids),
            ]);
            // Remove keys that are not needed for export/import
            unset($form_data['id']);
            unset($form_data['status']);
            $exported_forms[] = $form_data;
        }
    }

    if (empty($exported_forms)) {
        wp_send_json_error(['message' => __('No valid forms found to export.', 'gpt3-ai-content-generator')], 404);
        return;
    }

    wp_send_json_success(['forms' => $exported_forms]);
}

/**
 * Handles the logic for exporting all AI forms.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_export_all_ai_forms().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_export_all_forms_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    $all_forms_list = $form_storage->get_forms_list(['posts_per_page' => -1]);
    send_ai_forms_export(
        $handler_instance,
        wp_list_pluck($all_forms_list['forms'], 'id'),
        'all'
    );
}

/**
 * Handles the logic for exporting selected AI forms.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_export_selected_ai_forms().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_export_selected_forms_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $raw_form_ids = isset($_POST['form_ids']) ? sanitize_text_field(wp_unslash($_POST['form_ids'])) : '';
    $form_ids = json_decode($raw_form_ids, true);
    if (!is_array($form_ids) || empty($form_ids)) {
        $handler_instance->send_wp_error(new WP_Error('form_ids_required', __('Select at least one form to export.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    send_ai_forms_export($handler_instance, $form_ids, 'selected');
}
