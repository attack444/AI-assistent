<?php

namespace WPAICG\Utils;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Identifier_Utils
 * Utility class for generating unique identifiers.
 */
class AIPKit_Identifier_Utils {

    /**
     * Generates a unique identifier string suitable for contexts like
     * OpenAI Vector Store names, Pinecone namespaces, or Qdrant metadata.
     *
     * The generated identifier will be sanitized to be safe for such uses.
     *
     * @param int|null $user_id The user ID, if applicable.
     * @param string|null $session_id The session ID, if applicable (for guests).
     * @param string $prefix A prefix for the identifier (e.g., 'aipkit_chat_file_ctx').
     * @param int $random_length The length of the random suffix.
     * @return string A unique identifier string.
     */
    public static function generate_chat_context_identifier(
        ?int $user_id,
        ?string $session_id,
        string $prefix = 'aipkit_ctx',
        int $random_length = 8
    ): string {
        $identifier_base = '';

        if ($user_id && $user_id > 0) {
            $identifier_base = "u{$user_id}";
        } elseif (!empty($session_id)) {
            // Sanitize session_id: remove non-alphanumeric and limit length to avoid overly long identifiers
            $sanitized_session_id = preg_replace('/[^a-zA-Z0-9]/', '', $session_id);
            $sanitized_session_id = substr($sanitized_session_id, 0, 20); // Limit length of session part
            $identifier_base = "g_{$sanitized_session_id}";
        } else {
            $identifier_base = "unk"; // Unknown user/session
        }

        $timestamp = time();
        // Generate a short, URL-safe random string
        $random_suffix_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $random_suffix = '';
        for ($i = 0; $i < $random_length; $i++) {
            $random_suffix .= $random_suffix_chars[wp_rand(0, strlen($random_suffix_chars) - 1)];
        }

        // Combine and sanitize for typical identifier constraints (alphanumeric, underscores, hyphens, limited length)
        $raw_identifier = strtolower("{$prefix}_{$identifier_base}_{$timestamp}_{$random_suffix}");
        
        // Further sanitize for stricter environments if needed (e.g., Pinecone namespaces have length/char limits)
        // For now, sanitize_key is a good general approach.
        $final_identifier = sanitize_key($raw_identifier);
        
        // Some systems might have length limits (e.g., OpenAI Vector Store name limit is typically around 64 chars)
        // Pinecone namespace is 64 chars.
        // It's good to be mindful of this, though sanitize_key doesn't enforce length.
        // If a strict length limit is needed, it should be applied here.
        // For example, substr($final_identifier, 0, 60);

        return $final_identifier;
    }
}