<?php


namespace WPAICG\Core\Stream\Cache;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic files
require_once __DIR__ . '/methods.php';

/**
 * AIPKit_SSE_Message_Cache
 *
 * Handles caching large user messages temporarily for SSE requests
 * to avoid "414 Request-URI Too Large" errors.
 * Uses WP Object Cache if available, otherwise falls back to a custom DB table.
 */
class AIPKit_SSE_Message_Cache
{
    public const CACHE_GROUP = 'default'; // Use default group for better compatibility with external object caches
    public const DB_TABLE_SUFFIX = 'aipkit_sse_message_cache';
    public const EXPIRY_SECONDS = 60; // Messages expire after 60 seconds
    public const CLEANUP_CRON_HOOK = 'aipkit_cleanup_sse_cache';

    private $use_object_cache;
    private $db_table_name;

    public function __construct()
    {
        global $wpdb;
        $this->use_object_cache = wp_using_ext_object_cache();
        $this->db_table_name = $wpdb->prefix . self::DB_TABLE_SUFFIX;
    }

    public function is_using_object_cache(): bool
    {
        return is_using_object_cache_logic($this);
    }

    // Public wrapper for the private generate_key logic
    public function generate_key_public_wrapper(): string
    {
        return generate_key_logic();
    }

    /**
     * @return string|\WP_Error
     */
    public function set(string $message)
    {
        return set_logic($this, $message);
    }

    /**
     * @return string|\WP_Error
     */
    public function get(string $key)
    {
        return get_logic($this, $key);
    }

    public function delete(string $key): bool
    {
        return delete_logic($this, $key);
    }

    public static function schedule_cleanup_event()
    {
        schedule_cleanup_event_logic(self::CLEANUP_CRON_HOOK);
    }

    public static function unschedule_cleanup_event()
    {
        unschedule_cleanup_event_logic(self::CLEANUP_CRON_HOOK);
    }

    // Static wrapper for non-static logic to be used in cron
    public static function run_db_cleanup_static_wrapper()
    {
        run_db_cleanup_logic();
    }

    // Getters for private properties
    public function get_use_object_cache_status(): bool
    {
        return (bool) $this->use_object_cache;
    }
    public function get_db_table_name(): string
    {
        return $this->db_table_name;
    }
}

// Add action for the cron callback immediately after class definition
add_action(AIPKit_SSE_Message_Cache::CLEANUP_CRON_HOOK, ['WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache', 'run_db_cleanup_static_wrapper']);
