<?php


namespace WPAICG\SEO\RankMath;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to update the Rank Math focus keyword(s) for a post.
 *
 * @param int $post_id The ID of the post.
 * @param string $keyword The new focus keyword or comma-separated keywords.
 * @return bool True on success, false on failure.
 */
function update_focus_keyword_logic(int $post_id, string $keyword): bool
{
    if (empty($post_id) || !is_string($keyword)) {
        return false;
    }
    $result = update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($keyword));
    return $result !== false;
}
