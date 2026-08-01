<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Admin\AdminSetup; // Keep for POST_TYPE constant access in logic files if needed

// Load method logic files
$sitewide_logic_path = __DIR__ . '/sitewide/';
require_once $sitewide_logic_path . 'get-site-wide-bot-id.php';
require_once $sitewide_logic_path . 'ensure-site-wide-uniqueness.php';
require_once $sitewide_logic_path . 'clear-site-wide-cache.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Manages the site-wide chatbot setting, including uniqueness and caching.
 * Methods now delegate to namespaced functions.
 */
class SiteWideBotManager
{
    public const SITE_WIDE_BOT_CACHE_KEY = 'aipkit_site_wide_bot_id';

    /**
     * Gets the ID of the bot configured for site-wide injection.
     * Uses WP Object Cache and Transients for performance.
     *
     * @param bool $force_refresh Set to true to bypass cache and query DB directly.
     * @return int|null The ID of the site-wide bot, or null if none is set.
     */
    public function get_site_wide_bot_id($force_refresh = false): ?int
    {
        return SiteWide\get_site_wide_bot_id_logic($force_refresh);
    }

    /**
     * Ensures only one bot is set as site-wide enabled.
     * Called BEFORE updating the target bot's site-wide meta.
     *
     * @param int $target_bot_id The ID of the bot being potentially enabled.
     * @param bool $is_enabling Whether the target bot is being enabled for site-wide use.
     * @return bool True if the cache should be cleared after the operation.
     */
    public function ensure_site_wide_uniqueness(int $target_bot_id, bool $is_enabling): bool
    {
        return SiteWide\ensure_site_wide_uniqueness_logic($target_bot_id, $is_enabling, $this);
    }

    /**
     * Clears the site-wide bot ID cache.
     */
    public function clear_site_wide_cache(): void
    {
        SiteWide\clear_site_wide_cache_logic();
    }
}
