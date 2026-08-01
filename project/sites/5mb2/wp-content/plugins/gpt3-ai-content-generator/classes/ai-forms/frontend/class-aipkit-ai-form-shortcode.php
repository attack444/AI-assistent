<?php


namespace WPAICG\AIForms\Frontend;

use WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler;
use WPAICG\Includes\AIPKit_Shared_Assets_Manager;
use WP_Error;

// Load the new modular logic files
require_once __DIR__ . '/shortcode/validator.php';
require_once __DIR__ . '/shortcode/data-provider.php';
require_once __DIR__ . '/shortcode/renderer.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Orchestrates rendering the [aipkit_ai_form] shortcode.
 * Delegates logic to validator, data-provider, and renderer functions.
 */
class AIPKit_AI_Form_Shortcode
{
    private static $rendered_form_ids = [];
    private $form_storage;

    public function __construct()
    {
        if (class_exists(AIPKit_AI_Form_Storage::class)) {
            $this->form_storage = new AIPKit_AI_Form_Storage();
        } else {
            $this->form_storage = null;
        }
    }

    private function ensure_public_assets_registered(): void
    {
        $version = defined('WPAICG_VERSION') ? (string) WPAICG_VERSION : '1.0.0';

        if (class_exists(AIPKit_Shared_Assets_Manager::class)) {
            AIPKit_Shared_Assets_Manager::register($version);
        }

        if (!wp_style_is('aipkit-public-ai-forms', 'registered')) {
            wp_register_style(
                'aipkit-public-ai-forms',
                WPAICG_PLUGIN_URL . 'dist/css/public-ai-forms.bundle.css',
                [],
                $version
            );
        }

        if (!wp_script_is('aipkit-public-ai-forms-js', 'registered')) {
            wp_register_script(
                'aipkit-public-ai-forms-js',
                WPAICG_PLUGIN_URL . 'dist/js/public-ai-forms.bundle.js',
                ['wp-i18n'],
                $version,
                true
            );
        }
    }

    private function is_pro_plan_active(): bool
    {
        return class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
    }

    private function register_pro_public_asset(string $type, string $handle, string $relative_path, array $deps = []): void
    {
        $full_path = WPAICG_PLUGIN_DIR . ltrim($relative_path, '/');
        if (!file_exists($full_path)) {
            return;
        }

        $version = (string) filemtime($full_path);
        $asset_url = WPAICG_PLUGIN_URL . ltrim($relative_path, '/');

        if ($type === 'style') {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $asset_url, $deps, $version);
            }
            return;
        }

        if (!wp_script_is($handle, 'registered')) {
            wp_register_script($handle, $asset_url, $deps, $version, true);
        }
    }

    private function is_script_localized(string $handle, string $object_name): bool
    {
        $scripts = wp_scripts();
        if (!$scripts) {
            return false;
        }

        $data = $scripts->get_data($handle, 'data');
        return is_string($data) && strpos($data, "var {$object_name} =") !== false;
    }

    private function enqueue_public_assets(array $frontend_display_settings): void
    {
        $this->ensure_public_assets_registered();

        if (!wp_style_is('aipkit-public-ai-forms', 'enqueued')) {
            wp_enqueue_style('aipkit-public-ai-forms');
        }

        if (!wp_script_is('aipkit-public-ai-forms-js', 'enqueued')) {
            wp_enqueue_script('aipkit-public-ai-forms-js');
            wp_set_script_translations('aipkit-public-ai-forms-js', 'gpt3-ai-content-generator', WPAICG_PLUGIN_DIR . 'languages');
        }
        if (class_exists(AIPKit_Shared_Assets_Manager::class)) {
            AIPKit_Shared_Assets_Manager::attach_public_asset_urls('aipkit-public-ai-forms-js');
        }

        if (!$this->is_script_localized('aipkit-public-ai-forms-js', 'aipkit_ai_forms_public_config')) {
            wp_localize_script('aipkit-public-ai-forms-js', 'aipkit_ai_forms_public_config', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajaxNonce' => wp_create_nonce('aipkit_frontend_chat_nonce'),
                'is_user_logged_in' => is_user_logged_in(),
                'is_pro_plan' => class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan(),
                'allowed_models' => $frontend_display_settings['allowed_models'] ?? '',
                'text' => [
                    'processing' => __('Processing...', 'gpt3-ai-content-generator'),
                    'error' => __('An error occurred.', 'gpt3-ai-content-generator'),
                    'saveAsPost' => __('Save', 'gpt3-ai-content-generator'),
                ],
            ]);
        }

        if (
            !$this->is_script_localized('aipkit-public-ai-forms-js', 'aipkit_ai_forms_models') &&
            class_exists('\\WPAICG\\AIPKit_Providers')
        ) {
            $all_models = [
                'openai' => \WPAICG\AIPKit_Providers::get_openai_models(),
                'google' => \WPAICG\AIPKit_Providers::get_google_models(),
                'claude' => \WPAICG\AIPKit_Providers::get_claude_models(),
                'openrouter' => \WPAICG\AIPKit_Providers::get_openrouter_models(),
                'azure' => \WPAICG\AIPKit_Providers::get_azure_deployments(),
            ];

            if (
                class_exists('\\WPAICG\\aipkit_dashboard') &&
                \WPAICG\aipkit_dashboard::is_pro_plan()
            ) {
                $all_models['ollama'] = \WPAICG\AIPKit_Providers::get_ollama_models();
            }

            $all_models['deepseek'] = \WPAICG\AIPKit_Providers::get_deepseek_models();
            $all_models['xai'] = \WPAICG\AIPKit_Providers::get_xai_models();
            wp_localize_script('aipkit-public-ai-forms-js', 'aipkit_ai_forms_models', $all_models);
        }
    }

    private function enqueue_pro_ai_forms_public_assets(bool $include_pdf_download = false): void
    {
        if (!$this->is_pro_plan_active()) {
            return;
        }

        $this->register_pro_public_asset(
            'style',
            'aipkit-lib-ai-forms-public-css',
            'lib/css/ai-forms/conversational-form.css',
            ['aipkit-public-ai-forms']
        );
        $this->register_pro_public_asset(
            'script',
            'aipkit-lib-ai-forms-conversation',
            'lib/js/ai-forms/conversational-form.js',
            ['wp-i18n', 'aipkit-public-ai-forms-js']
        );
        $this->register_pro_public_asset(
            'script',
            'aipkit-lib-ai-forms-workflow',
            'lib/js/ai-forms/workflow-runtime.js',
            ['wp-i18n', 'aipkit-public-ai-forms-js']
        );

        if (wp_style_is('aipkit-lib-ai-forms-public-css', 'registered') && !wp_style_is('aipkit-lib-ai-forms-public-css', 'enqueued')) {
            wp_enqueue_style('aipkit-lib-ai-forms-public-css');
        }

        if (wp_script_is('aipkit-lib-ai-forms-conversation', 'registered') && !wp_script_is('aipkit-lib-ai-forms-conversation', 'enqueued')) {
            wp_enqueue_script('aipkit-lib-ai-forms-conversation');
        }

        if (wp_script_is('aipkit-lib-ai-forms-workflow', 'registered') && !wp_script_is('aipkit-lib-ai-forms-workflow', 'enqueued')) {
            wp_enqueue_script('aipkit-lib-ai-forms-workflow');
        }

        if (!$include_pdf_download) {
            return;
        }

        $this->register_pro_public_asset(
            'script',
            'aipkit-lib-ai-forms-download-pdf',
            'lib/js/ai-forms/download/download-as-pdf.js',
            ['aipkit-public-ai-forms-js']
        );

        if (wp_script_is('aipkit-lib-ai-forms-download-pdf', 'registered') && !wp_script_is('aipkit-lib-ai-forms-download-pdf', 'enqueued')) {
            wp_enqueue_script('aipkit-lib-ai-forms-download-pdf');
        }
    }

    private function get_late_style_handles(): array
    {
        $handles = ['aipkit-public-ai-forms'];

        if ($this->is_pro_plan_active()) {
            $handles[] = 'aipkit-lib-ai-forms-public-css';
        }

        return $handles;
    }

    private function get_late_script_handles(bool $include_pdf_download = false): array
    {
        $handles = ['aipkit-public-ai-forms-js'];

        if ($this->is_pro_plan_active()) {
            $handles[] = 'aipkit-lib-ai-forms-conversation';
            $handles[] = 'aipkit-lib-ai-forms-workflow';

            if ($include_pdf_download) {
                $handles[] = 'aipkit-lib-ai-forms-download-pdf';
            }
        }

        return $handles;
    }

    private function capture_printed_styles(array $handles): string
    {
        if (empty($handles)) {
            return '';
        }

        if (!did_action('wp_print_styles') && !did_action('wp_head')) {
            return '';
        }

        ob_start();
        wp_styles()->do_items($handles);
        return trim((string) ob_get_clean());
    }

    private function capture_printed_scripts(array $handles): string
    {
        if (empty($handles)) {
            return '';
        }

        if (!did_action('wp_print_footer_scripts') && !did_action('wp_footer')) {
            return '';
        }

        ob_start();
        wp_scripts()->do_items($handles);
        return trim((string) ob_get_clean());
    }

    private function render_late_asset_fallbacks(bool $include_pdf_download = false): array
    {
        return [
            'styles' => $this->capture_printed_styles($this->get_late_style_handles()),
            'scripts' => $this->capture_printed_scripts($this->get_late_script_handles($include_pdf_download)),
        ];
    }

    /**
     * Render the shortcode output.
     *
     * @param array $atts Shortcode attributes, expecting 'id' and optional 'theme', 'save_button'.
     * @return string HTML output for the AI form or an error message.
     */
    public function render_shortcode($atts)
    {
        if (!$this->form_storage) {
            return $this->handle_error(new WP_Error('storage_missing', '[AIPKit AI Form Error: Storage component missing.]'));
        }

        // Parse attributes with defaults
        $default_atts = [
            'id'    => 0,
            'theme' => 'light',
            'show_provider' => 'false',
            'show_model'    => 'false',
            'save_button'   => 'false',
            'pdf_download'  => 'false',
            'copy_button'   => 'false',
        ];
        $atts = shortcode_atts($default_atts, $atts, 'aipkit_ai_form');

        // Validate theme attribute
        $valid_themes = ['light', 'dark', 'custom'];
        $theme = in_array($atts['theme'], $valid_themes, true) ? $atts['theme'] : 'light';

        // Parse boolean flags for new attributes
        $show_provider = filter_var($atts['show_provider'], FILTER_VALIDATE_BOOLEAN);
        $show_model = filter_var($atts['show_model'], FILTER_VALIDATE_BOOLEAN);
        $show_save_button = filter_var($atts['save_button'], FILTER_VALIDATE_BOOLEAN);
        $show_pdf_download = filter_var($atts['pdf_download'], FILTER_VALIDATE_BOOLEAN) && $this->is_pro_plan_active();
        $show_copy_button = filter_var($atts['copy_button'], FILTER_VALIDATE_BOOLEAN);

        // 1. Validate ID Attribute
        $validation_result = Shortcode\validate_atts_logic($atts, self::$rendered_form_ids);
        if (is_wp_error($validation_result)) {
            return $this->handle_error($validation_result);
        }
        $form_id = $validation_result;

        // 2. Get Form Data
        $form_data = Shortcode\get_form_data_logic($this->form_storage, $form_id);
        if (is_wp_error($form_data)) {
            return $this->handle_error($form_data);
        }

        $frontend_display_settings = [];
        if (class_exists(AIPKit_AI_Form_Settings_Ajax_Handler::class)) {
            $all_settings = AIPKit_AI_Form_Settings_Ajax_Handler::get_settings();
            $frontend_display_settings = $all_settings['frontend_display'] ?? [];
        }
        $allowed_providers_str = $frontend_display_settings['allowed_providers'] ?? '';
        $this->enqueue_public_assets($frontend_display_settings);
        $this->enqueue_pro_ai_forms_public_assets($show_pdf_download);
        $late_asset_fallbacks = $this->render_late_asset_fallbacks($show_pdf_download);

        // 5. Mark as rendered
        self::$rendered_form_ids[$form_id] = true;

        $custom_css = '';
        if ($theme === 'custom' && class_exists(AIPKit_AI_Form_Settings_Ajax_Handler::class)) {
            $settings = AIPKit_AI_Form_Settings_Ajax_Handler::get_settings();
            $custom_css = $settings['custom_theme']['custom_css'] ?? '';
        }

        // 6. Prepare data for the renderer
        $unique_form_html_id = 'aipkit-ai-form-' . esc_attr($form_id);
        $ajax_nonce = wp_create_nonce('aipkit_process_ai_form_' . $form_id);

        // 7. Render HTML, passing the new theme and display flags
        $form_html = Shortcode\render_form_html_logic($form_data, $unique_form_html_id, $ajax_nonce, $theme, $show_provider, $show_model, $show_save_button, $show_pdf_download, $show_copy_button, $custom_css, $allowed_providers_str);

        return implode("\n", array_filter([
            $late_asset_fallbacks['styles'] ?? '',
            $form_html,
            $late_asset_fallbacks['scripts'] ?? '',
        ]));
    }

    /**
     * Handles rendering errors, showing messages to admins only.
     *
     * @param WP_Error $error The error object.
     * @return string HTML error message or empty string.
     */
    private function handle_error(WP_Error $error): string
    {
        if (\WPAICG\AIPKit_Role_Manager::user_can_view_admin_notices()) {
            $message = $error->get_error_message();
            $code = $error->get_error_code();
            return '<p style="color:' . ($code === 'already_rendered' ? 'orange' : 'red') . '; font-style: italic; margin: 1em 0;">' . esc_html($message) . '</p>';
        }
        return ''; // Silently fail for regular users
    }
}
