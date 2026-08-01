<?php

namespace WPAICG\Core\Moderation;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_BannedWords_Checker
 *
 * Checks if the provided text contains any banned words.
 */
class AIPKit_BannedWords_Checker {

    /**
     * Checks text for banned words.
     *
     * @param string $text The text content to check.
     * @param array $banned_words_settings Associative array with 'words' (string) and 'message' (string).
     * @return WP_Error|null WP_Error if a banned word is found, null otherwise.
     */
    public static function check(string $text, array $banned_words_settings): ?WP_Error {
        if (!empty($banned_words_settings['words'])) {
            $banned_words_list = array_map('trim', explode(',', strtolower($banned_words_settings['words'])));
            // We no longer need to lowercase the text here, as the regex will be case-insensitive.
            
            foreach ($banned_words_list as $banned_word) {
                if (empty($banned_word)) {
                    continue;
                }
                if (preg_match('/\b' . preg_quote($banned_word, '/') . '\b/i', $text)) {
                    $banned_word_message = $banned_words_settings['message'] ?: __('Sorry, your message could not be sent as it contains prohibited words.', 'gpt3-ai-content-generator');
                    return new WP_Error('word_banned', $banned_word_message, ['status' => 400]); // Bad Request
                }
            }
        }
        return null;
    }
}