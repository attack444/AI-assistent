<?php


namespace WPAICG;

// --- Load Core Helper Classes FIRST ---
require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-dependency-loader.php';
require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-hook-manager.php';
require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-module-initializer.php';
require_once WPAICG_PLUGIN_DIR . 'includes/class-aipkit-shared-assets-manager.php';
// --- END Load Core Helper Classes FIRST ---

// --- Use statements for NEW Core Helper Classes ---
use WPAICG\Includes\AIPKit_Dependency_Loader;
use WPAICG\Includes\AIPKit_Hook_Manager;
use WPAICG\Includes\AIPKit_Module_Initializer;
use WPAICG\Includes\AIPKit_Shared_Assets_Manager;


// --- Core Plugin Includes ---
require_once WPAICG_PLUGIN_DIR . 'includes/class-wp-ai-content-generator-activator.php';
require_once WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_role_manager.php'; // Needed for update check

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class. Bootstrapper.
 */
class WP_AI_Content_Generator
{
    private static $instance = null;
    private $version;
    private $plugin_name;
    public const DB_VERSION_OPTION = 'aipkit_plugin_version'; // Option to store current DB version
    public const TOKEN_MANAGER_SCHEMA_VERSION_OPTION = 'aipkit_token_manager_schema_version';
    public const TOKEN_MANAGER_SCHEMA_VERSION = '4';
    private const INSTALL_INTEGRITY_TRANSIENT = 'aipkit_install_integrity_checked';

    public static function get_instance(): WP_AI_Content_Generator
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->version = defined('WPAICG_VERSION') ? WPAICG_VERSION : '1.9.15';
        $this->plugin_name = 'gpt3-ai-content-generator';
    }

    /**
     * Run the plugin setup.
     * Load dependencies, define hooks, initialize modules, and ensure DB tables exist.
     */
    public function run()
    {
        // Load all dependencies using the new loader class
        AIPKit_Dependency_Loader::load();

        // Register shared assets (moved to a separate manager, called on init)
        add_action('init', [$this, 'register_shared_assets'], 0);

        // Check for plugin updates (version change)
        add_action('init', [$this, 'check_for_updates'], 10);

        // Define hooks using the new hook manager
        AIPKit_Hook_Manager::register_hooks($this->version);

        // Initialize modules using the new module initializer
        AIPKit_Module_Initializer::init($this->version);
    }

    /**
     * Register shared assets via the SharedAssetsManager.
     * Hooked to 'init' with priority 0.
     */
    public function register_shared_assets()
    {
        AIPKit_Shared_Assets_Manager::register($this->version);
    }

    /**
     * Check for plugin updates (e.g., version change) and run necessary routines.
     * Now runs on 'init' action hook, after i18n is loaded.
     */
    public function check_for_updates()
    {
        if (!$this->should_run_update_check()) {
            return;
        }

        $current_version = $this->version;
        $saved_version = get_option(self::DB_VERSION_OPTION);
        $saved_token_manager_schema_version = get_option(self::TOKEN_MANAGER_SCHEMA_VERSION_OPTION);

        $version_needs_update = version_compare((string) $saved_version, $current_version, '<');
        $token_manager_schema_needs_update = version_compare(
            (string) $saved_token_manager_schema_version,
            self::TOKEN_MANAGER_SCHEMA_VERSION,
            '<'
        );

        $tables_are_missing = false;
        $vector_index_missing = false;

        if ($version_needs_update || $token_manager_schema_needs_update || $this->should_run_install_integrity_check()) {
            $tables_are_missing = $this->are_plugin_tables_missing();
            $vector_index_missing = $this->is_vector_data_source_index_missing();
        }

        if (!$version_needs_update && !$tables_are_missing && !$vector_index_missing && !$token_manager_schema_needs_update) {
            set_transient(self::INSTALL_INTEGRITY_TRANSIENT, '1', DAY_IN_SECONDS);
            return;
        }

        delete_transient(self::INSTALL_INTEGRITY_TRANSIENT);

        $this->clear_external_caches();

        // Run DB table setup on version change to apply any schema updates.
        WP_AI_Content_Generator_Activator::setup_tables_for_blog();
        $this->cleanup_legacy_chatbot_pricing_overrides();

        // Ensure Role Manager Permissions are Updated/Initialized
        if (class_exists('\\WPAICG\\AIPKit_Role_Manager')) {
            \WPAICG\AIPKit_Role_Manager::update_permissions_on_activation();
        }

        // Ensure Default Chatbot exists
        if (class_exists('\\WPAICG\\Chat\\Storage\\DefaultBotSetup')) {
            \WPAICG\Chat\Storage\DefaultBotSetup::ensure_default_chatbot();
        }

        // Ensure Default Content Writer Template exists
        if (class_exists('\\WPAICG\\ContentWriter\\AIPKit_Content_Writer_Template_Manager')) {
            \WPAICG\ContentWriter\AIPKit_Content_Writer_Template_Manager::ensure_default_template_exists();
        }

        // Ensure Cron Jobs are scheduled
        if (class_exists('\\WPAICG\\Core\\TokenManager\\AIPKit_Token_Manager')) {
            \WPAICG\Core\TokenManager\AIPKit_Token_Manager::schedule_token_reset_event();
        }
        if (class_exists('\\WPAICG\\Core\\Stream\\Cache\\AIPKit_SSE_Message_Cache')) {
            \WPAICG\Core\Stream\Cache\AIPKit_SSE_Message_Cache::schedule_cleanup_event();
        }
        if (class_exists('\\WPAICG\\AutoGPT\\AIPKit_Automated_Task_Cron')) {
            \WPAICG\AutoGPT\AIPKit_Automated_Task_Cron::init();
        }

        // Update the stored version
        update_option(self::DB_VERSION_OPTION, $current_version, 'no'); // Use autoload 'no'
        update_option(self::TOKEN_MANAGER_SCHEMA_VERSION_OPTION, self::TOKEN_MANAGER_SCHEMA_VERSION, 'no');
        set_transient(self::INSTALL_INTEGRITY_TRANSIENT, '1', DAY_IN_SECONDS);
    }

    private function should_run_update_check(): bool
    {
        if (function_exists('wp_installing') && wp_installing()) {
            return true;
        }

        if (is_admin() || wp_doing_cron()) {
            return true;
        }

        return defined('WP_CLI') && WP_CLI;
    }

    private function should_run_install_integrity_check(): bool
    {
        return false === get_transient(self::INSTALL_INTEGRITY_TRANSIENT);
    }

    private function cleanup_legacy_chatbot_pricing_overrides(): void
    {
        global $wpdb;

        $pricing_rules_table = $wpdb->prefix . 'aipkit_pricing_rules';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time plugin migration cleanup.
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pricing_rules_table));
        if ($table_exists === $pricing_rules_table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- One-time plugin migration cleanup against a plugin-owned table.
            $wpdb->query($wpdb->prepare("DELETE FROM {$pricing_rules_table} WHERE scope_type IN (%s, %s)", 'chatbot', 'bot'));
        }

        delete_post_meta_by_key('_aipkit_token_pricing_mode');
    }

    /**
     * NEW: Helper function to check if any of our custom tables are missing.
     * This adds robustness to the update process.
     * @return bool True if one or more tables are missing.
     */
    private function are_plugin_tables_missing(): bool
    {
        global $wpdb;
        $required_tables = [
            'aipkit_chat_logs',
            'aipkit_guest_token_usage',
            'aipkit_sse_message_cache',
            'aipkit_vector_data_source',
            'aipkit_automated_tasks',
            'aipkit_automated_task_queue',
            'aipkit_content_writer_templates',
            'aipkit_rss_history',
            'aipkit_event_delivery_queue',
            'aipkit_recipe_delivery_logs',
            'aipkit_pricing_rules',
            'aipkit_token_ledger',
        ];

        foreach ($required_tables as $table_suffix) {
            $table_name = $wpdb->prefix . $table_suffix;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Necessary check for table existence during plugin initialization/update.
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
                return true; // Found a missing table
            }
        }
        return false;
    }

    /**
     * Checks whether the vector data source composite index exists.
     * @return bool True if the index is missing.
     */
    private function is_vector_data_source_index_missing(): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipkit_vector_data_source';
        $index_name = 'provider_store_time';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Table existence check.
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($table_exists !== $table_name) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name is derived from $wpdb->prefix and the index name is prepared.
        $index_rows = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $index_name));

        return empty($index_rows);
    }

    /**
     * Clears caches after a plugin update.
     */
    private function clear_external_caches()
    {
        if (false === apply_filters('aipkit_auto_clear_caches_on_update', true)) {
            return;
        }

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        wp_cache_flush();
    }


    public function get_plugin_name(): string
    {
        return $this->plugin_name;
    }
    public function get_version(): string
    {
        return $this->version;
    }

} // End class
