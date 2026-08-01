<?php


namespace WPAICG\ContentWriter\Prompt;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates a text summary from HTML content.
 */
class AIPKit_Content_Writer_Summarizer
{
    /**
     * Strips HTML and shortcodes from content and returns a word-trimmed summary.
     *
     * @param string $html_content The HTML content to summarize.
     * @param int $word_count The target word count for the summary.
     * @return string The text summary.
     */
    public static function summarize(string $html_content, int $word_count = 300): string
    {
        // Strip tags and shortcodes, then normalize whitespace
        $text = wp_strip_all_tags(strip_shortcodes($html_content));
        $text = preg_replace('/\s+/', ' ', $text);
        if (!$text) {
            return '';
        }
        // Use WordPress's built-in function to trim to a specific word count
        return wp_trim_words($text, $word_count, '...');
    }
}
