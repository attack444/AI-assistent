<?php


namespace WPAICG\SEO\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to update the AIOSEO keyphrase for a post.
 * UPDATED: Saves the keyphrase to the dedicated `wp_aioseo_posts` table and also updates the `_aioseo_keywords` post meta for compatibility.
 *
 * @param int $post_id The ID of the post.
 * @param string $keyword The new focus keyphrase.
 * @return bool True on success, false on failure.
 */
function update_focus_keyword_logic(int $post_id, string $keyword): bool
{
    if (empty($post_id) || !is_string($keyword)) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'aioseo_posts';
    $sanitized_keyword = sanitize_text_field($keyword);

    // --- Prepare the data structure ---
    $keyphrases_data = [];
    $existing_row = null;

    // Check if the AIOSEO table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to a custom table. Caches will be invalidated.
    $table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);

    if ($table_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caches will be invalidated.
        $existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE post_id = %d", $post_id));
        if ($existing_row && !empty($existing_row->keyphrases)) {
            $keyphrases_data = json_decode($existing_row->keyphrases, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $keyphrases_data = []; // Reset if JSON is corrupt
            }
        }
    }

    // AIOSEO expects this structure. We only set the keyphrase, it calculates the rest.
    $keyphrases_data['focus']['keyphrase'] = $sanitized_keyword;
    if (!isset($keyphrases_data['focus']['analysis'])) {
        $keyphrases_data['focus']['analysis'] = new \stdClass();
    }
    if (!isset($keyphrases_data['additional'])) {
        $keyphrases_data['additional'] = [];
    }

    // --- Update the database table ---
    $db_update_success = true; // Assume success if table doesn't exist, as meta will be updated
    if ($table_exists) {
        $keyphrases_json = wp_json_encode($keyphrases_data);
        if (is_wp_error($keyphrases_json)) {
            return false;
        }

        if ($existing_row) {
            // Update existing row
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caches will be invalidated.
            $result = $wpdb->update(
                $table_name,
                ['keyphrases' => $keyphrases_json, 'updated' => current_time('mysql', 1)],
                ['post_id' => $post_id],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new row
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct insert to a custom table. Caches will be invalidated.
            $result = $wpdb->insert(
                $table_name,
                [
                    'post_id' => $post_id,
                    'keyphrases' => $keyphrases_json,
                    'created' => current_time('mysql', 1),
                    'updated' => current_time('mysql', 1),
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
        $db_update_success = ($result !== false);
    }

    // --- Also update post meta, which AIOSEO may use as a trigger ---
    $meta_update_success = (update_post_meta($post_id, '_aioseo_keywords', $keyphrases_data) !== false);

    return $db_update_success && $meta_update_success;
}
