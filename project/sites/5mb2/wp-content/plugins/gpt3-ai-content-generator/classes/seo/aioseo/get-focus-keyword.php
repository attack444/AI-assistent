<?php


namespace WPAICG\SEO\AIOSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to get the AIOSEO keyphrase for a post.
 * UPDATED: Reads from the dedicated `wp_aioseo_posts` table with a fallback to post meta.
 *
 * @param int $post_id The ID of the post.
 * @return string|null The focus keyphrase or null if not set.
 */
function get_focus_keyword_logic(int $post_id): ?string
{
    if (empty($post_id)) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'aioseo_posts';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: This is a one-time read operation.
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: This is a one-time read operation.
        $keyphrases_json = $wpdb->get_var($wpdb->prepare("SELECT keyphrases FROM {$table_name} WHERE post_id = %d", $post_id));
        if (!empty($keyphrases_json)) {
            $keyphrases_data = json_decode($keyphrases_json, true);
            if (is_array($keyphrases_data) && !empty($keyphrases_data['focus']['keyphrase'])) {
                return $keyphrases_data['focus']['keyphrase'];
            }
        }
    }

    // Fallback to checking post meta if table read fails or yields no result
    $keywords_data_meta = get_post_meta($post_id, '_aioseo_keywords', true);
    if (is_array($keywords_data_meta) && !empty($keywords_data_meta['focus']['keyphrase'])) {
        return $keywords_data_meta['focus']['keyphrase'];
    }
    // Handle case where meta is a JSON string
    if (is_string($keywords_data_meta)) {
        $keywords_data_decoded = json_decode($keywords_data_meta, true);
        if (is_array($keywords_data_decoded) && !empty($keywords_data_decoded['focus']['keyphrase'])) {
            return $keywords_data_decoded['focus']['keyphrase'];
        }
    }

    return null;
}
