<?php


namespace WPAICG\Shortcodes;

use WPAICG\Shortcodes\AIPKit_Token_Usage_Shortcode;
use WPAICG\Shortcodes\AIPKit_Image_Generator_Shortcode;
use WPAICG\Shortcodes\AIPKit_Semantic_Search_Shortcode;
use WPAICG\Includes\AIPKit_Shared_Assets_Manager;
use WPAICG\aipkit_dashboard;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Shortcodes_Manager
 * Registers shortcodes and handles their asset enqueueing using bundled files.
 */
class AIPKit_Shortcodes_Manager
{
    private $version;
    private $token_usage_shortcode = null;
    private $image_generator_shortcode = null;
    private $semantic_search_shortcode = null; // NEW
    private $is_token_management_active = true;
    private $is_image_generator_active = false;
    private $is_semantic_search_active = true; // NEW
    private $is_token_usage_css_enqueued = false;
    private $is_image_generator_css_enqueued = false;
    private $is_semantic_search_css_enqueued = false; // NEW

    public function __construct($version)
    {
        $this->version = $version;
        if (!class_exists('\\WPAICG\\aipkit_dashboard')) {
            $dashboard_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_dashboard.php';
            if (file_exists($dashboard_path)) {
                require_once $dashboard_path;
            }
        }
        if (class_exists('\\WPAICG\\aipkit_dashboard')) {
            $module_settings = aipkit_dashboard::get_module_settings();
            $this->is_image_generator_active = !empty($module_settings['image_generator']);
        }
    }

    public function init_hooks()
    {
        $this->load_dependencies();
        if ($this->is_token_management_active && $this->token_usage_shortcode) {
            add_shortcode('aipkit_token_usage', [$this->token_usage_shortcode, 'render_shortcode']);
            if (method_exists($this->token_usage_shortcode, 'init_hooks')) {
                $this->token_usage_shortcode->init_hooks();
            }
        }
        if ($this->is_image_generator_active && $this->image_generator_shortcode) {
            add_shortcode('aipkit_image_generator', [$this->image_generator_shortcode, 'render_shortcode']);
        }
        if ($this->is_semantic_search_active && $this->semantic_search_shortcode) {
            add_shortcode('aipkit_semantic_search', [$this->semantic_search_shortcode, 'render_shortcode']);
        }
        add_action('wp_enqueue_scripts', [$this, 'register_and_enqueue_assets']);
    }

    private function load_dependencies()
    {
        if ($this->is_token_management_active) {
            $token_usage_path = WPAICG_PLUGIN_DIR . 'classes/shortcodes/class-aipkit-token-usage-shortcode.php';
            if (file_exists($token_usage_path)) {
                require_once $token_usage_path;
                if (class_exists('\\WPAICG\\Shortcodes\\AIPKit_Token_Usage_Shortcode')) {
                    $this->token_usage_shortcode = new AIPKit_Token_Usage_Shortcode();
                }
            }
        }
        if ($this->is_image_generator_active) {
            $image_gen_path = WPAICG_PLUGIN_DIR . 'classes/shortcodes/class-aipkit-image-generator-shortcode.php';
            if (file_exists($image_gen_path)) {
                require_once $image_gen_path;
                if (class_exists('\\WPAICG\\Shortcodes\\AIPKit_Image_Generator_Shortcode')) {
                    $this->image_generator_shortcode = new AIPKit_Image_Generator_Shortcode();
                }
            }
        }
        if ($this->is_semantic_search_active) {
            $semantic_search_path = WPAICG_PLUGIN_DIR . 'classes/shortcodes/class-aipkit-semantic-search-shortcode.php';
            if (file_exists($semantic_search_path)) {
                require_once $semantic_search_path;
                if (class_exists('\\WPAICG\\Shortcodes\\AIPKit_Semantic_Search_Shortcode')) {
                    $this->semantic_search_shortcode = new AIPKit_Semantic_Search_Shortcode();
                }
            }
        }
    }

    public function register_and_enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        global $post;
        $content = is_a($post, 'WP_Post') ? $post->post_content : '';
        $dist_css_url = WPAICG_PLUGIN_URL . 'dist/css/';
        $dist_js_url = WPAICG_PLUGIN_URL . 'dist/js/';
        $public_image_generator_js_handle = 'aipkit-public-image-generator-js';
        $public_token_usage_js_handle = 'aipkit-public-token-usage-js';
        $public_semantic_search_js_handle = 'aipkit-public-semantic-search-js';

        if ($this->is_token_management_active && has_shortcode($content, 'aipkit_token_usage')) {
            $token_usage_css_handle = 'aipkit-public-token-usage';
            if (!wp_style_is($token_usage_css_handle, 'registered')) {
                wp_register_style($token_usage_css_handle, $dist_css_url . 'public-token-usage.bundle.css', [], $this->version);
            }
            if (!$this->is_token_usage_css_enqueued && !wp_style_is($token_usage_css_handle, 'enqueued')) {
                wp_enqueue_style($token_usage_css_handle);
                $this->is_token_usage_css_enqueued = true;
            }
            if (!wp_script_is($public_token_usage_js_handle, 'registered')) {
                wp_register_script($public_token_usage_js_handle, $dist_js_url . 'public-token-usage.bundle.js', [], $this->version, true);
            }
            if (!wp_script_is($public_token_usage_js_handle, 'enqueued')) {
                wp_enqueue_script($public_token_usage_js_handle);
            }
        }

        $image_generator_present = $this->is_image_generator_active && (
            has_shortcode($content, 'aipkit_image_generator') || has_block('aipkit/image-generator', $content)
        );
        $force_load_image_gen = apply_filters('aipkit_enqueue_public_image_generator_assets', false);

        if ($image_generator_present || $force_load_image_gen) {
            $public_img_gen_css_handle = 'aipkit-public-image-generator-css';
            if (!wp_style_is($public_img_gen_css_handle, 'registered')) {
                wp_register_style($public_img_gen_css_handle, $dist_css_url . 'public-image-generator.bundle.css', [], $this->version);
            }
            if (!$this->is_image_generator_css_enqueued && !wp_style_is($public_img_gen_css_handle, 'enqueued')) {
                wp_enqueue_style($public_img_gen_css_handle);
                $this->is_image_generator_css_enqueued = true;
            }
            if (!wp_script_is($public_image_generator_js_handle, 'registered')) {
                wp_register_script($public_image_generator_js_handle, $dist_js_url . 'public-image-generator.bundle.js', [], $this->version, true);
            }
            if (!wp_script_is($public_image_generator_js_handle, 'enqueued')) {
                wp_enqueue_script($public_image_generator_js_handle);
            }
        }

        if ($this->is_semantic_search_active && has_shortcode($content, 'aipkit_semantic_search')) {
            $semantic_search_css_handle = 'aipkit-public-semantic-search';
            if (!wp_style_is($semantic_search_css_handle, 'registered')) {
                wp_register_style($semantic_search_css_handle, $dist_css_url . 'public-semantic-search.bundle.css', [], $this->version);
            }
            if (!$this->is_semantic_search_css_enqueued && !wp_style_is($semantic_search_css_handle, 'enqueued')) {
                wp_enqueue_style($semantic_search_css_handle);
                $this->is_semantic_search_css_enqueued = true;
            }
            if (!wp_script_is($public_semantic_search_js_handle, 'registered')) {
                wp_register_script($public_semantic_search_js_handle, $dist_js_url . 'public-semantic-search.bundle.js', [], $this->version, true);
            }
            if (!wp_script_is($public_semantic_search_js_handle, 'enqueued')) {
                wp_enqueue_script($public_semantic_search_js_handle);
            }
        }

        // --- START FIX: Localize data for Image Generator shortcode ---
        if (($image_generator_present || $force_load_image_gen) && wp_script_is($public_image_generator_js_handle, 'enqueued')) {
            static $image_gen_localized = false;
            if (!$image_gen_localized) {
                wp_localize_script(
                    $public_image_generator_js_handle,
                    'aipkit_image_generator_config_public',
                    AIPKit_Shared_Assets_Manager::get_public_image_generator_config()
                );
                $image_gen_localized = true;
            }
        }
        // --- END FIX ---

        // Localize data for each shortcode if present and script is enqueued
        if ($this->is_token_management_active && has_shortcode($content, 'aipkit_token_usage') && wp_script_is($public_token_usage_js_handle, 'enqueued')) {
            static $token_usage_localized = false;
            if (!$token_usage_localized) {
                wp_localize_script($public_token_usage_js_handle, 'aipkit_token_usage_config', [
                    'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce'   => wp_create_nonce('aipkit_token_usage_details_nonce'),
                    /* translators: %s is the name of the token, e.g. "OpenAI" */
                    'text' => ['loadingDetails' => __('Loading activity...', 'gpt3-ai-content-generator'), 'errorLoading' => __('Error loading activity.', 'gpt3-ai-content-generator'), 'close' => __('Close', 'gpt3-ai-content-generator'), 'usageDetailsTitle' => __('Usage Activity for %s', 'gpt3-ai-content-generator'), 'pageLabel' => __('Page', 'gpt3-ai-content-generator'), 'ofLabel' => __('of', 'gpt3-ai-content-generator'), 'previous' => __('Previous', 'gpt3-ai-content-generator'), 'next' => __('Next', 'gpt3-ai-content-generator'),]
                ]);
                $token_usage_localized = true;
            }
        }
        if ($this->is_semantic_search_active && has_shortcode($content, 'aipkit_semantic_search') && wp_script_is($public_semantic_search_js_handle, 'enqueued')) {
            static $semantic_search_localized = false;
            if (!$semantic_search_localized) {
                $opts = get_option('aipkit_options', []);
                $semantic_search_settings = $opts['semantic_search'] ?? [];
                wp_localize_script($public_semantic_search_js_handle, 'aipkit_semantic_search_config', [
                    'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('aipkit_semantic_search_nonce'),
                    'settings' => $semantic_search_settings,
                    'text' => ['searching' => __('Searching...', 'gpt3-ai-content-generator'), 'error' => __('An error occurred while searching.', 'gpt3-ai-content-generator'),]
                ]);
                $semantic_search_localized = true;
            }
        }
    }
}
