<?php

namespace WPAICG\Chat\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic files
require_once __DIR__ . '/content-aware/get_content_snippet.php';
// is_suitable_page.php is loaded by get_content_snippet.php

/**
 * Handles fetching relevant page content for the chatbot's context (Modularized).
 */
class AIPKit_Content_Aware {

    const MAX_EXCERPT_LENGTH = 1500;

    public static function get_content_snippet(int $post_id): ?string {
        return ContentAware\get_content_snippet($post_id);
    }

    // is_suitable_page is now a namespaced function used by get_content_snippet
}