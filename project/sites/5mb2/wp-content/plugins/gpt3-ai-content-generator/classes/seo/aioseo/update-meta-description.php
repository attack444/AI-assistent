<?php


namespace WPAICG\SEO\AIOSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic to update the AIOSEO meta description for a post.
 * Saves to AIOSEO's custom post table and keeps the legacy post meta mirror for compatibility.
 *
 * @param int $post_id The ID of the post.
 * @param string $description The new meta description.
 * @return bool True on success, false on failure.
 */
function update_meta_description_logic(int $post_id, string $description): bool
{
    if (empty($post_id) || !is_string($description)) {
        return false;
    }

    $sanitized_description = sanitize_text_field($description);
    $meta_update_success = update_post_meta($post_id, '_aioseo_description', $sanitized_description) !== false;

    global $wpdb;
    $table_name = $wpdb->prefix . 'aioseo_posts';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct query to AIOSEO's custom table.
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name);
    if (!$table_exists) {
        return $meta_update_success;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to AIOSEO's custom table.
    $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE post_id = %d", $post_id));
    $timestamp = current_time('mysql', 1);

    if ($existing_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to AIOSEO's custom table.
        $result = $wpdb->update(
            $table_name,
            [
                'description' => $sanitized_description,
                'updated' => $timestamp,
            ],
            ['post_id' => $post_id],
            ['%s', '%s'],
            ['%d']
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct insert to AIOSEO's custom table.
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'description' => $sanitized_description,
                'created' => $timestamp,
                'updated' => $timestamp,
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    return $meta_update_success && $result !== false;
}
