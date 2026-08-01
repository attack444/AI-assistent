<?php

namespace WPAICG\Chat\Core\ContentAware;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for the is_suitable_page private static method of AIPKit_Content_Aware.
 *
 * @param int $post_id The post ID passed from the frontend.
 * @return bool True if the context is suitable, false otherwise.
 */
function is_suitable_page(int $post_id): bool {
     if ($post_id <= 0) {
        return false;
     }

     $post = get_post($post_id);
     if (!$post) {
         return false;
     }

     $front_page_id = (int) get_option('page_on_front');
     $posts_page_id = (int) get_option('page_for_posts');

     if ($post_id === $front_page_id || $post_id === $posts_page_id) {
        return false;
     }

     $allowed_post_types = apply_filters('aipkit_content_aware_post_types', ['post', 'page', 'product']);
     if (!in_array($post->post_type, $allowed_post_types, true)) {
        return false;
     }
     return true;
}