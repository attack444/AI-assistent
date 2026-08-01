<?php

namespace WPAICG\SEO\RankMath;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to get the Rank Math focus keyword for a post.
 *
 * @param int $post_id The ID of the post.
 * @return string|null The focus keyword or null if not set.
 */
function get_focus_keyword_logic(int $post_id): ?string
{
    if (empty($post_id)) {
        return null;
    }
    $kw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
    return is_string($kw) && !empty($kw) ? $kw : null;
}