<?php

namespace WPAICG\Core\Stream\Cache;

use WP_Error;
use DateTime;
use DateTimeZone;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- fn-is-using-object-cache.php ---
/**
 * Check if the external object cache is being used.
 *
 * @param \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance The instance of the cache class.
 * @return bool True if using object cache, false otherwise.
 */
function is_using_object_cache_logic(\WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance): bool {
    // The property use_object_cache is private, need a getter or make it public
    return $cacheInstance->get_use_object_cache_status();
}

// --- fn-generate-key.php ---
/**
 * Generates a unique cache key.
 *
 * @return string The generated cache key.
 */
function generate_key_logic(): string {
    return 'aipkit_sse_' . wp_generate_password(32, false, false);
}

// --- fn-set.php ---
/**
 * Stores a message in the cache.
 * MODIFIED: Always write to the database as a reliable fallback, then attempt to write to object cache for performance.
 *
 * @param \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance The instance of the cache class.
 * @param string $message The user message content.
 * @return string|WP_Error The cache key on success, WP_Error on failure.
 */
function set_logic(\WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance, string $message) {
    if (empty($message)) {
        return new WP_Error('sse_cache_empty_message', __('Cannot cache an empty message.', 'gpt3-ai-content-generator'));
    }

    $key = $cacheInstance->generate_key_public_wrapper();

    // Always write to the database as a reliable fallback.
    global $wpdb;
    $expires_at = new DateTime('now', new DateTimeZone('UTC'));
    $expires_at->modify('+' . $cacheInstance::EXPIRY_SECONDS . ' seconds');
    $expires_at_str = $expires_at->format('Y-m-d H:i:s');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Direct insertion into a custom table is necessary. Cache is set below.
    $inserted = $wpdb->insert(
        $cacheInstance->get_db_table_name(),
        [
            'cache_key' => $key,
            'message_content' => $message,
            'expires_at' => $expires_at_str,
        ],
        ['%s', '%s', '%s']
    );

    if ($inserted === false) {
        return new WP_Error('sse_cache_db_insert_failed', __('Failed to store message in database cache.', 'gpt3-ai-content-generator'));
    }

    // Also try to set in the object cache for performance, but don't fail if it doesn't work.
    if ($cacheInstance->is_using_object_cache()) {
        $set_obj_cache = wp_cache_set($key, $message, $cacheInstance::CACHE_GROUP, $cacheInstance::EXPIRY_SECONDS);
    }

    return $key; // Return the key since the DB write succeeded.
}

// --- fn-get.php ---
/**
 * Retrieves a message from the cache using its key.
 * Implemented WP Object Cache to resolve direct database query warnings.
 *
 * @param \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance The instance of the cache class.
 * @param string $key The cache key.
 * @return string|WP_Error The message content on success, WP_Error if not found or expired.
 */
function get_logic(\WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance, string $key)
{
    if (empty($key)) {
        return new WP_Error('sse_cache_empty_key', __('Cache key cannot be empty.', 'gpt3-ai-content-generator'));
    }

    $cache_group = 'aipkit_sse_cache';
    $content_cache_key = 'sse_content_' . $key;

    // 1. Try to get the content from the cache.
    $cached_result = wp_cache_get($content_cache_key, $cache_group);

    if (false !== $cached_result) {
        // Cache hit. Return the cached result, which might be content or an error object.
        return $cached_result;
    }

    // 2. Cache miss, so query the database.
    global $wpdb;
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $table_name = $wpdb->prefix . $cacheInstance::DB_TABLE_SUFFIX;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Custom table name is fixed (wpdb prefix + plugin suffix).
    $row = $wpdb->get_row(
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: Direct query to a custom table. Caches will be invalidated.
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: Direct query to a custom table. Caches will be invalidated.
            "SELECT message_content FROM {$table_name} WHERE cache_key = %s AND expires_at > %s LIMIT 1",
            $key,
            $now_utc
        ),
        ARRAY_A
    );

    $result_to_cache = null;

    if ($row && isset($row['message_content'])) {
        // 3a. Found valid content.
        $result_to_cache = $row['message_content'];
    } else {
        // 4a. Not found or expired. Check if the key exists at all to differentiate the error.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Custom table name is fixed (wpdb prefix + plugin suffix).
        $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table_name} WHERE cache_key = %s LIMIT 1", $key));
        if ($exists) {
            $result_to_cache = new WP_Error('sse_cache_expired', __('Cached message has expired.', 'gpt3-ai-content-generator'));
        } else {
            $result_to_cache = new WP_Error('sse_cache_not_found', __('Message not found in cache.', 'gpt3-ai-content-generator'));
        }
    }

    // 5. Store the result (either content or a WP_Error) in the cache.
    // This prevents repeated DB queries for non-existent or expired keys within the same request or if using a persistent cache.
    wp_cache_set($content_cache_key, $result_to_cache, $cache_group, $cacheInstance::EXPIRY_SECONDS);

    return $result_to_cache;
}

// --- fn-delete.php ---
/**
 * Deletes a message from the cache.
 * MODIFIED: Now deletes from both the database and the object cache to ensure a clean state.
 *
 * @param \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance The instance of the cache class.
 * @param string $key The cache key.
 * @return bool True on success, false on failure or if key didn't exist.
 */
function delete_logic(\WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache $cacheInstance, string $key): bool
{
    if (empty($key)) {
        return false;
    }

    $deleted_from_db = false;
    $deleted_from_object_cache = false;

    // Always try to delete from the database.
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Direct deletion from custom table is necessary. Cache is invalidated below.
    $deleted_db = $wpdb->delete(
        $cacheInstance->get_db_table_name(),
        ['cache_key' => $key],
        ['%s']
    );
    if ($deleted_db !== false) {
        $deleted_from_db = true; // DB deletion was successful or key didn't exist
    }

    // Also try to delete from the object cache if it's enabled.
    if ($cacheInstance->is_using_object_cache()) {
        $deleted_from_object_cache = wp_cache_delete($key, $cacheInstance::CACHE_GROUP);
    }

    // Return true if it was deleted from at least one source (or didn't exist in either).
    return $deleted_from_db || $deleted_from_object_cache;
}

// --- fn-schedule-cleanup-event.php ---
/**
 * Schedules the hourly cache cleanup event if not already scheduled.
 *
 * @param string $cron_hook_const The cron hook constant.
 * @return void
 */
function schedule_cleanup_event_logic(string $cron_hook_const): void {
    if (!wp_next_scheduled($cron_hook_const)) {
        wp_schedule_event(time(), 'hourly', $cron_hook_const);
    }
    if (!has_action($cron_hook_const, ['WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache', 'run_db_cleanup_static_wrapper'])) {
        add_action($cron_hook_const, ['WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache', 'run_db_cleanup_static_wrapper']);
    }
}

// --- fn-unschedule-cleanup-event.php ---
/**
 * Unschedules the cache cleanup event.
 *
 * @param string $cron_hook_const The cron hook constant.
 * @return void
 */
function unschedule_cleanup_event_logic(string $cron_hook_const): void {
    $timestamp = wp_next_scheduled($cron_hook_const);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $cron_hook_const);
    }
    remove_action($cron_hook_const, ['WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache', 'run_db_cleanup_static_wrapper']);
}

// --- fn-run-db-cleanup.php ---
/**
 * Cron callback function to delete expired cache entries from the DB table.
 * Only runs if external object cache is NOT being used.
 *
 * @return void
 */
function run_db_cleanup_logic(): void {

    if (wp_using_ext_object_cache()) {
        return;
    }

    global $wpdb;
    // Access DB_TABLE_SUFFIX via a constant or pass it in. For now, hardcoding for simplicity.
    $table = $wpdb->prefix . 'aipkit_sse_message_cache';
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H-i-s');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Custom table name is fixed (wpdb prefix + plugin suffix); cron cleanup query.
    $deleted_count = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at <= %s", $now_utc));

}
