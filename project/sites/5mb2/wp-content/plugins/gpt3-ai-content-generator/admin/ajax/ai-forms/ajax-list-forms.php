<?php

namespace WPAICG\Admin\Ajax\AIForms;

use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the logic for listing all AI forms with dynamic querying.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_list_ai_forms().
 * UPDATED: Handles pagination, search, and sorting parameters.
 * UPDATED: Optimized to prevent N+1 queries by fetching all post meta in a single query.
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_list_forms_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{
    $form_storage = $handler_instance->get_form_storage();
    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $post_data = wp_unslash($_POST);
    $paged = isset($post_data['page']) ? absint($post_data['page']) : 1;
    $per_page = isset($post_data['per_page']) ? absint($post_data['per_page']) : 10;
    $per_page = in_array($per_page, [10, 25, 50, 100], true) ? $per_page : 10;
    $search = isset($post_data['search']) ? sanitize_text_field($post_data['search']) : '';
    $sort_by = isset($post_data['sort_by']) ? sanitize_key($post_data['sort_by']) : 'modified';
    $sort_order_raw = isset($post_data['sort_order']) ? strtoupper(sanitize_key($post_data['sort_order'])) : 'DESC';
    $sort_order = in_array($sort_order_raw, ['ASC', 'DESC']) ? $sort_order_raw : 'ASC';
    $provider_filter = isset($post_data['filter_provider']) ? sanitize_text_field($post_data['filter_provider']) : 'all';

    // Whitelist sortable columns
    $allowed_sort_keys = ['id', 'title', 'provider', 'model', 'date', 'modified'];
    if (!in_array($sort_by, $allowed_sort_keys)) {
        $sort_by = 'title';
    }
    // WP_Query uses 'ID' instead of 'id'
    if ($sort_by === 'id') {
        $sort_by = 'ID';
    }


    $args = [
        'paged'          => $paged,
        'posts_per_page' => $per_page,
        'search'         => $search,
        'orderby'        => $sort_by,
        'order'          => $sort_order,
        'filter_provider' => $provider_filter,
    ];

    $result = $form_storage->get_forms_list($args);

    // The result from get_forms_list_logic is now an array with 'forms' and 'pagination' keys
    wp_send_json_success($result);
}
