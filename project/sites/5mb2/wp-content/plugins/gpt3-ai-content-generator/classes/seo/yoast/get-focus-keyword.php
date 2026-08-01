<?php

namespace WPAICG\SEO\Yoast;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to get the Yoast SEO focus keyword for a post.
 *
 * @param int $post_id The ID of the post.
 * @return string|null The focus keyword or null if not set.
 */
function get_focus_keyword_logic(int $post_id): ?string
{
    if (empty($post_id)) {
        return null;
    }
    $kw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
    return is_string($kw) && !empty($kw) ? $kw : null;
}