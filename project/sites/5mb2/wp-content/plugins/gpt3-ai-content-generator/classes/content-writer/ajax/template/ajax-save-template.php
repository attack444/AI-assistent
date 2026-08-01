<?php


namespace WPAICG\ContentWriter\Ajax\Template;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Handles the logic for saving or updating a content writer template.
*
* @param AIPKit_Content_Writer_Template_Ajax_Handler $handler
* @return void
*/
function ajax_save_template_logic(AIPKit_Content_Writer_Template_Ajax_Handler $handler): void
{
    // Permission check is done in the calling method
    if (!$handler->get_template_manager()) {
        $handler->send_wp_error(new WP_Error('manager_missing', 'Template manager unavailable.'), 500);
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $template_id = isset($_POST['template_id']) && !empty($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $template_name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $config_json = isset($_POST['config']) ? wp_kses_post(wp_unslash($_POST['config'])) : '{}';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $template_type = isset($_POST['template_type']) ? sanitize_key($_POST['template_type']) : 'content_writer';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $config = json_decode($config_json, true);

    if (empty($template_name)) {
        $handler->send_wp_error(new WP_Error('missing_name', 'Template name is required.'), 400);
        return;
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        $handler->send_wp_error(new WP_Error('invalid_config', 'Invalid template configuration data.'), 400);
        return;
    }

    $template_manager = $handler->get_template_manager();

    if ($template_id > 0) {
        $result = $template_manager->update_template($template_id, $template_name, $config);
    } else {
        $result = $template_manager->create_template($template_name, $config, $template_type);
    }

    if (is_wp_error($result)) {
        $handler->send_wp_error($result);
    } else {
        $new_template_id = ($template_id > 0) ? $template_id : $result;
        $message = ($template_id > 0) ? __('Template updated successfully.', 'gpt3-ai-content-generator') : __('Template saved successfully.', 'gpt3-ai-content-generator');

        $saved_template = $template_manager->get_template($new_template_id);
        $response_config = [];
        if (!is_wp_error($saved_template) && isset($saved_template['config'])) {
            $response_config = $saved_template['config'];
        } else {
            $response_config = $config;
            if (isset($response_config['post_categories']) && is_string($response_config['post_categories'])) {
                $response_config['post_categories'] = array_map('absint', array_filter(explode(',', $response_config['post_categories'])));
            } elseif (isset($response_config['post_categories']) && is_array($response_config['post_categories'])) {
                $response_config['post_categories'] = array_map('absint', $response_config['post_categories']);
            } else {
                $response_config['post_categories'] = [];
            }
        }

        wp_send_json_success([
        'message' => $message,
        'template_id' => $new_template_id,
        'template_name' => $template_name,
        'template_config' => $response_config
        ]);
    }
}
