<?php

namespace WPAICG\Chat\Core\ContentAware;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This file intentionally uses core WordPress hook names.

// Require helper for is_suitable_page if not already loaded by the class wrapper
require_once __DIR__ . '/is_suitable_page.php';


/**
 * Logic for the get_content_snippet static method of AIPKit_Content_Aware.
 *
 * @param int $post_id The ID of the post/page.
 * @return string|null The formatted content snippet or null if not applicable/found.
 */
function get_content_snippet(int $post_id): ?string {
    // is_suitable_page is now a namespaced function
    if (!is_suitable_page($post_id)) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_status, ['publish', 'private'])) {
        return null;
    }

    $content = '';
    if (has_excerpt($post)) {
        $content = trim(get_the_excerpt($post));
    }

    if (empty($content) && !empty($post->post_content)) {
         $content_raw = $post->post_content;
         $content_filtered = apply_filters('the_content', $content_raw);
         $content_stripped = wp_strip_all_tags(strip_shortcodes($content_filtered));
         $content = trim(preg_replace('/\s+/', ' ', $content_stripped));
         // MAX_EXCERPT_LENGTH is a constant in the class, access it via class name
         $content = mb_substr($content, 0, \WPAICG\Chat\Core\AIPKit_Content_Aware::MAX_EXCERPT_LENGTH);
    }

    if (empty($content)) {
        return null;
    }

    return sprintf(
        "## Current Page Content Snippet:\n%s\n##\n",
        $content
    );
}