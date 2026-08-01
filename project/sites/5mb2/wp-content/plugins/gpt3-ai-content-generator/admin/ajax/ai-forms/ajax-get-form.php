<?php

namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for fetching a single AI form's data.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_get_ai_form().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_get_form_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $form_id = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
    if (empty($form_id)) {
        $handler_instance->send_wp_error(new WP_Error('id_required', __('Form ID is required.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $form_data = $form_storage->get_form_data($form_id);
    if (is_wp_error($form_data)) {
        $handler_instance->send_wp_error($form_data);
    } else {
        $connected_apps = class_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes')
            && method_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes', 'get_ai_form_connected_apps_payload')
            ? \WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes::get_ai_form_connected_apps_payload($form_id)
            : [
                'count' => 0,
                'summary' => '',
                'recipes' => [],
            ];

        wp_send_json_success([
            'form' => $form_data,
            'connected_apps' => $connected_apps,
        ]);
    }
}
