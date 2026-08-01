<?php

namespace WPAICG\ContentWriter\Ajax\Template;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Handles the logic for deleting a template.
*
* @param AIPKit_Content_Writer_Template_Ajax_Handler $handler
* @return void
*/
function ajax_delete_template_logic(AIPKit_Content_Writer_Template_Ajax_Handler $handler): void
{
    // Permission check done in caller
    if (!$handler->get_template_manager()) {
        $handler->send_wp_error(new WP_Error('manager_missing', 'Template manager unavailable.'), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    if (empty($template_id)) {
        $handler->send_wp_error(new WP_Error('missing_id', 'Template ID is required.'), 400);
        return;
    }

    $result = $handler->get_template_manager()->delete_template($template_id);
    if (is_wp_error($result)) {
        $handler->send_wp_error($result);
    } else {
        $user_id = get_current_user_id();
        if ($user_id) {
            \WPAICG\ContentWriter\TemplateManagerMethods\remove_cw_starter_template_id_for_user($user_id, $template_id);
        }
        wp_send_json_success(['message' => __('Template deleted successfully.', 'gpt3-ai-content-generator')]);
    }
}
