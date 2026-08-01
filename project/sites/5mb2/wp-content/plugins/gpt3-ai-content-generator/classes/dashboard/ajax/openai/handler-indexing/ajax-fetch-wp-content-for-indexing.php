<?php

namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerIndexing;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler;
use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for fetching WordPress content for indexing.
 * Called by AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler::ajax_fetch_wp_content_for_indexing().
 *
 * @param AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_fetch_wp_content_for_indexing_logic(AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler $handler_instance): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_types    = isset($post_data['post_types']) && is_array($post_data['post_types']) ? array_map('sanitize_key', $post_data['post_types']) : ['post'];
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_status   = isset($post_data['post_status']) ? sanitize_key($post_data['post_status']) : 'publish';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $paged         = isset($post_data['paged']) ? absint($post_data['paged']) : 1;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $target_store_id = isset($post_data['target_store_id']) ? sanitize_text_field($post_data['target_store_id']) : null;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $posts_per_page = 15;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $get_content_for_ids = isset($post_data['get_content_for_ids']) && is_array($post_data['get_content_for_ids']) ? array_map('absint', $post_data['get_content_for_ids']) : null;

    $args = [
        'post_type'      => $post_types,
        'post_status'    => ($post_status === 'any') ? ['publish', 'draft', 'pending', 'private'] : $post_status,
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ];

    if (!empty($get_content_for_ids)) {
        $args['post__in'] = $get_content_for_ids;
        $args['posts_per_page'] = -1;
        $args['paged'] = 1;
        $args['orderby'] = 'post__in';
        if (count(array_diff(get_post_types(['public' => true]), $post_types)) > 0 || count(array_diff($post_types, get_post_types(['public' => true]))) > 0) {
             $args['post_type'] = get_post_types();
        }
        $args['post_status'] = 'any';
    }

    $query = new WP_Query($args);
    $posts_data = [];
    $posts_content_map = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $post_type_obj = get_post_type_object(get_post_type());
            $is_indexed = false;
            if ($target_store_id) {
                $is_indexed = (bool) get_post_meta($post_id, '_aipkit_indexed_to_vs_' . sanitize_key($target_store_id), true);
            }

            $post_item = [
                'id'         => $post_id,
                'title'      => get_the_title(),
                'type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type(),
                'edit_link'  => get_edit_post_link($post_id),
                'is_already_indexed' => $is_indexed,
            ];

            if (!empty($get_content_for_ids) && in_array($post_id, $get_content_for_ids)) {
                $openai_post_processor = $handler_instance->get_openai_post_processor();
                if ($openai_post_processor && method_exists($openai_post_processor, 'get_post_content_as_string')) {
                    $content_string_or_error = $openai_post_processor->get_post_content_as_string($post_id); // Use public method
                    if (!is_wp_error($content_string_or_error)) {
                        $posts_content_map[$post_id] = [
                            'content' => $content_string_or_error,
                            'title' => $post_item['title'],
                            'type_label' => $post_item['type_label'],
                            'edit_link' => $post_item['edit_link']
                        ];
                    } else {
                        $posts_content_map[$post_id] = ['content' => 'Error: Could not retrieve content.', 'title' => $post_item['title']];
                    }
                } else {
                    $posts_content_map[$post_id] = ['content' => 'Error: Content processing component missing.', 'title' => $post_item['title']];
                }
            }
            $posts_data[] = $post_item;
        }
        wp_reset_postdata();
    }

    $response_data = [
        'posts' => $posts_data,
        'pagination' => [
            'total_posts'  => (int) $query->found_posts,
            'total_pages'  => (int) $query->max_num_pages,
            'current_page' => (int) $paged,
        ]
    ];
    if (!empty($get_content_for_ids)) {
        $response_data['posts_content'] = $posts_content_map;
    }
    wp_send_json_success($response_data);
}