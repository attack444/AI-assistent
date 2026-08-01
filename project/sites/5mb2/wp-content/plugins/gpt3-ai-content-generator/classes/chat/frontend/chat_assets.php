<?php

namespace WPAICG\Chat\Frontend;

// Use statements for the new Asset sub-component classes
use WPAICG\Chat\Frontend\Assets\AssetsHooks;
use WPAICG\Chat\Frontend\Assets\AssetsSiteWideChecker;
use WPAICG\Chat\Frontend\Assets\AssetsRequireFlags;
use WPAICG\Chat\Frontend\Assets\AssetsEnqueuer;
use WPAICG\Chat\Frontend\Assets\AssetsDependencyRegistrar;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Orchestrates frontend asset (CSS/JS) management for the Chatbot.
 * Delegates specific tasks to sub-component classes.
 * Holds static flags for asset requirements.
 */
class Assets {

    // --- Static Flags to track asset requirements ---
    public static $assets_registered = false;
    public static $shortcode_rendered = false;
    public static $site_wide_injection_needed = false;
    public static $copy_button_needed = false;
    public static $feedback_needed = false;
    public static $starters_needed = false;
    public static $sidebar_needed = false;
    public static $tts_needed = false;
    public static $consent_needed = false;
    public static $stt_needed = false;
    public static $image_gen_needed = false;
    public static $chat_image_upload_needed = false;
    public static $chat_file_upload_needed = false; 
    public static $realtime_voice_needed = false;
    public static $is_embed = false; // NEW: Flag for embed mode
    // --- End Static Flags ---

    private $hooks_handler;
    private $sitewide_checker_handler;
    private $enqueuer_handler;
    // DependencyRegistrar and RequireFlags only have static methods, no instantiation needed for them here.

    public function __construct() {
        require_once __DIR__ . '/assets/classes.php';

        // Instantiate handlers that are called via instance methods
        $this->hooks_handler = class_exists(AssetsHooks::class) ? new AssetsHooks($this) : null;
        $this->sitewide_checker_handler = class_exists(AssetsSiteWideChecker::class) ? new AssetsSiteWideChecker() : null;
        $this->enqueuer_handler = class_exists(AssetsEnqueuer::class) ? new AssetsEnqueuer() : null;
    }

    /**
     * Registers hooks. Delegates to AssetsHooks.
     */
    public function register_hooks() {
        if ($this->hooks_handler && method_exists($this->hooks_handler, 'register')) {
            $this->hooks_handler->register();
        }
    }

    /**
     * Checks for site-wide bot and sets relevant flags. Delegates to AssetsSiteWideChecker.
     * This method is public to be callable from the AssetsHooks context (template_redirect hook).
     */
    public function check_for_site_wide_bot_public_wrapper() {
        if ($this->sitewide_checker_handler && method_exists($this->sitewide_checker_handler, 'check')) {
            $this->sitewide_checker_handler->check();
        }
    }

    /**
     * Signals that assets are needed, potentially with specific feature flags.
     * Delegates to AssetsRequireFlags.
     *
     * @param bool $needs_pdf
     * @param bool $needs_copy
     * @param bool $needs_starters
     * @param bool $needs_sidebar
     * @param bool $needs_feedback
     * @param bool $needs_tts
     * @param bool $needs_stt
     * @param bool $needs_image_gen
     * @param bool $needs_chat_image_upload
     * @param bool $needs_chat_file_upload 
     * @param bool $needs_realtime_voice
     */
    public static function require_assets(
        bool $needs_pdf = false, bool $needs_copy = false, bool $needs_starters = false,
        bool $needs_sidebar = false, bool $needs_feedback = false, bool $needs_tts = false,
        bool $needs_stt = false, bool $needs_image_gen = false, bool $needs_chat_image_upload = false,
        bool $needs_chat_file_upload = false, bool $needs_realtime_voice = false
    ) {
        if (class_exists(AssetsRequireFlags::class) && method_exists(AssetsRequireFlags::class, 'set_flags')) {
            AssetsRequireFlags::set_flags(
                $needs_pdf, $needs_copy, $needs_starters, $needs_sidebar,
                $needs_feedback, $needs_tts, $needs_stt, $needs_image_gen, $needs_chat_image_upload,
                $needs_chat_file_upload, $needs_realtime_voice
            );
        }
    }

    /**
     * Registers and conditionally enqueues frontend assets. Delegates to AssetsEnqueuer.
     * This method is public to be callable from the AssetsHooks context (wp_enqueue_scripts hook).
     */
    public function register_and_enqueue_frontend_assets_public_wrapper() {
        if ($this->enqueuer_handler && method_exists($this->enqueuer_handler, 'process_assets')) {
            $this->enqueuer_handler->process_assets();
        }
    }

    /**
     * Registers all public chat JavaScript dependencies.
     * Delegates to AssetsDependencyRegistrar.
     */
    public static function register_public_chat_dependencies() {
        if (class_exists(AssetsDependencyRegistrar::class) && method_exists(AssetsDependencyRegistrar::class, 'register')) {
            AssetsDependencyRegistrar::register();
        }
    }
}
