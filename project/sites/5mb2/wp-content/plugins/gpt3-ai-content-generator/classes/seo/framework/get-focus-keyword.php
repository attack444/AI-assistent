<?php

namespace WPAICG\SEO\Framework;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to get the focus keyword for a post with The SEO Framework.
 * The SEO Framework does not have this feature.
 *
 * @param int $post_id The ID of the post.
 * @return string|null Always returns null.
 */
function get_focus_keyword_logic(int $post_id): ?string
{
    // The SEO Framework does not have a concept of a focus keyword.
    return null;
}