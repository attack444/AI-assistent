<?php


namespace WPAICG\ContentWriter\Ajax\Template;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Handles the logic for listing all templates for the current user.
*
* @param AIPKit_Content_Writer_Template_Ajax_Handler $handler
* @return void
*/
function ajax_list_templates_logic(AIPKit_Content_Writer_Template_Ajax_Handler $handler): void
{
    // Permission check done in caller
    if (!$handler->get_template_manager()) {
        $handler->send_wp_error(new WP_Error('manager_missing', 'Template manager unavailable.'), 500);
        return;
    }

    \WPAICG\ContentWriter\TemplateManagerMethods\ensure_starter_templates_exist_logic(
        $handler->get_template_manager()
    );
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $template_type = isset($_POST['template_type']) ? sanitize_key($_POST['template_type']) : 'content_writer';
    $templates = $handler->get_template_manager()->get_templates_for_user($template_type);
    wp_send_json_success(['templates' => $templates]);
}
