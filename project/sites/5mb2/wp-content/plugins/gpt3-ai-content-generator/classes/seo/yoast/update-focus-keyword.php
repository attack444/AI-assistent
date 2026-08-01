<?php


namespace WPAICG\SEO\Yoast;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to update the Yoast SEO focus keyword for a post.
 *
 * @param int $post_id The ID of the post.
 * @param string $keyword The new focus keyword.
 * @return bool True on success, false on failure.
 */
function update_focus_keyword_logic(int $post_id, string $keyword): bool
{
    if (empty($post_id) || !is_string($keyword)) {
        return false;
    }
    $result = update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($keyword));
    return $result !== false;
}
