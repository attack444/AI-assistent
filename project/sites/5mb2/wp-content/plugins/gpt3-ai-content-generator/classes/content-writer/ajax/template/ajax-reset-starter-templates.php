<?php

namespace WPAICG\ContentWriter\Ajax\Template;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for resetting starter templates.
 *
 * @param AIPKit_Content_Writer_Template_Ajax_Handler $handler
 * @return void
 */
function ajax_reset_starter_templates_logic(AIPKit_Content_Writer_Template_Ajax_Handler $handler): void
{
    // Permission check done in caller
    if (!$handler->get_template_manager()) {
        $handler->send_wp_error(new WP_Error('manager_missing', 'Template manager unavailable.'), 500);
        return;
    }

    $result = $handler->get_template_manager()->reset_starter_templates();
    if (is_wp_error($result)) {
        $handler->send_wp_error($result);
        return;
    }

    wp_send_json_success([
        'message' => __('Starter templates reset.', 'gpt3-ai-content-generator'),
        'template_ids' => $result,
    ]);
}
