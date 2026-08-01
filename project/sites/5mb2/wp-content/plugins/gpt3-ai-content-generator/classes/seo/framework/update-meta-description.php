<?php


namespace WPAICG\SEO\Framework;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to update The SEO Framework meta description for a post.
 *
 * @param int $post_id The ID of the post.
 * @param string $description The new meta description.
 * @return bool True on success, false on failure.
 */
function update_meta_description_logic(int $post_id, string $description): bool
{
    if (empty($post_id) || !is_string($description)) {
        return false;
    }
    // The SEO Framework uses _genesis_description.
    $result = update_post_meta($post_id, '_genesis_description', sanitize_text_field($description));
    return $result !== false;
}
