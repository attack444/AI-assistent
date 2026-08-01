<?php


namespace WPAICG\Includes;

// Ensure this file is only loaded by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Shared_Assets_Manager
 * Handles registering scripts shared across admin and public contexts.
 * REVISED: Only registers vendor scripts. Core utils are now part of main bundles.
 */
class AIPKit_Shared_Assets_Manager
{
    /**
     * Build a versioned plugin asset URL for lazy-loaded frontend bundles.
     *
     * @param string $relative_path
     * @return string
     */
    private static function get_versioned_asset_url(string $relative_path): string
    {
        $version = defined('WPAICG_VERSION') ? (string) WPAICG_VERSION : '1.0.0';

        return add_query_arg(
            'ver',
            rawurlencode($version),
            WPAICG_PLUGIN_URL . ltrim($relative_path, '/')
        );
    }

    /**
     * Return public asset URLs used by lazy frontend loaders.
     *
     * @return array<string, string>
     */
    public static function get_public_asset_urls(): array
    {
        $asset_urls = [
            'markdownIt' => self::get_versioned_asset_url('dist/vendor/js/markdown-it.min.js'),
            'markdownit' => self::get_versioned_asset_url('dist/vendor/js/markdown-it.min.js'),
            'chatSidebar' => self::get_versioned_asset_url('dist/js/public-chat-sidebar.bundle.js'),
            'chatStt' => self::get_versioned_asset_url('dist/js/public-chat-stt.bundle.js'),
            'chatUploads' => self::get_versioned_asset_url('dist/js/public-chat-uploads.bundle.js'),
            'chatTts' => self::get_versioned_asset_url('dist/js/public-chat-tts.bundle.js'),
            'chatStarters' => self::get_versioned_asset_url('dist/js/public-chat-starters.bundle.js'),
            'chatImageCommand' => self::get_versioned_asset_url('dist/js/public-chat-image-command.bundle.js'),
            'chatRealtime' => self::get_versioned_asset_url('dist/js/public-chat-realtime.bundle.js'),
            'chatPdf' => self::get_versioned_asset_url('dist/js/public-chat-pdf.bundle.js'),
        ];

        return $asset_urls;
    }

    public static function get_public_image_generator_config(): array
    {
        if (!class_exists('\\WPAICG\\AIPKit_Providers')) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            }
        }
        if (!class_exists('\\WPAICG\\Images\\AIPKit_Image_Settings_Ajax_Handler')) {
            $settings_handler_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-settings-ajax-handler.php';
            if (file_exists($settings_handler_path)) {
                require_once $settings_handler_path;
            }
        }

        $ui_text_settings = [];
        if (class_exists('\\WPAICG\\Images\\AIPKit_Image_Settings_Ajax_Handler')) {
            $all_image_settings = \WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler::get_settings();
            $ui_text_settings = $all_image_settings['ui_text'] ?? [];
        }
        $get_ui_text = static function (string $key, string $default) use ($ui_text_settings): string {
            if (!isset($ui_text_settings[$key])) {
                return $default;
            }
            $value = sanitize_text_field((string) $ui_text_settings[$key]);
            return $value !== '' ? $value : $default;
        };
        $providers_available = class_exists('\\WPAICG\\AIPKit_Providers');

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'text' => [
                'generating' => __('Generating...', 'gpt3-ai-content-generator'),
                'editing' => __('Editing...', 'gpt3-ai-content-generator'),
                'error' => __('Error generating image.', 'gpt3-ai-content-generator'),
                'generateButton' => $get_ui_text('generate_label', __('Generate', 'gpt3-ai-content-generator')),
                'noPrompt' => __('Please enter a prompt.', 'gpt3-ai-content-generator'),
                'initialPlaceholder' => $get_ui_text('results_empty', __('Generated images will appear here.', 'gpt3-ai-content-generator')),
                'viewFullImage' => __('Click to view full image', 'gpt3-ai-content-generator'),
                'viewFullVideo' => __('Click to view full video', 'gpt3-ai-content-generator'),
                'openrouterModelUnsupported' => __('Selected OpenRouter model does not support image generation.', 'gpt3-ai-content-generator'),
                'editUploadRequired' => __('Please upload an image to edit.', 'gpt3-ai-content-generator'),
                'editProviderUnsupported' => __('Image editing is currently supported only for Google, OpenAI, OpenRouter, and xAI providers.', 'gpt3-ai-content-generator'),
                'editModelUnsupported' => __('Selected model does not support image editing.', 'gpt3-ai-content-generator'),
                'editInvalidFileType' => __('Invalid image type. Allowed types: JPG, PNG, WEBP, GIF.', 'gpt3-ai-content-generator'),
                'xaiEditInvalidFileType' => __('xAI image editing supports JPG and PNG source images only.', 'gpt3-ai-content-generator'),
                'xaiEditUploadMeta' => __('JPG or PNG up to 10MB', 'gpt3-ai-content-generator'),
                'xaiEditUploadHint' => __('xAI image editing supports JPG and PNG source images only.', 'gpt3-ai-content-generator'),
                'editFileTooLarge' => __('Source image is too large. Maximum allowed size is 10MB.', 'gpt3-ai-content-generator'),
                'editDropUsePicker' => __('Could not attach dropped file automatically. Click to choose file.', 'gpt3-ai-content-generator'),
                'editHistoryLoadFailed' => __('Could not load the selected image for editing.', 'gpt3-ai-content-generator'),
                'editHistoryUnavailable' => __('Image editing is not available in the current setup.', 'gpt3-ai-content-generator'),
                'editHistoryLoaded' => __('Source image loaded. Describe your edits and click Edit Image.', 'gpt3-ai-content-generator'),
                'noEditCapableModels' => __('(No edit-capable models available)', 'gpt3-ai-content-generator'),
                'noOpenRouterImageModels' => __('(No image-capable OpenRouter models found)', 'gpt3-ai-content-generator'),
                'noXAIImageModels' => __('(No xAI image models found)', 'gpt3-ai-content-generator'),
                'noModelsAvailable' => __('(No models available)', 'gpt3-ai-content-generator'),
                'imageModelsGroup' => __('Image Models', 'gpt3-ai-content-generator'),
                'videoModelsGroup' => __('Video Models', 'gpt3-ai-content-generator'),
                'configurationMissing' => __('Error: Configuration missing.', 'gpt3-ai-content-generator'),
                'coreUiMissing' => __('Error: Core UI elements missing.', 'gpt3-ai-content-generator'),
                'missingRequiredSettings' => __('Error: Missing required image generation settings.', 'gpt3-ai-content-generator'),
                'noVideoDataFound' => __('Error: No video data found.', 'gpt3-ai-content-generator'),
                'noImageDataFound' => __('Error: No image data found.', 'gpt3-ai-content-generator'),
                'deleteConfigMissing' => __('Error: Cannot delete image. Configuration missing.', 'gpt3-ai-content-generator'),
                'deleteImageErrorPrefix' => __('Error deleting image:', 'gpt3-ai-content-generator'),
                'revisedPromptPrefix' => __('Revised:', 'gpt3-ai-content-generator'),
                'generatingVideo' => __('Generating Video...', 'gpt3-ai-content-generator'),
                'videoGenerationInProgress' => __('Video generation in progress...', 'gpt3-ai-content-generator'),
                'generatingVideoProgress' => __('Generating video...', 'gpt3-ai-content-generator'),
                'videoGenerationTimedOut' => __('Video generation timed out. Please try again.', 'gpt3-ai-content-generator'),
                'videoGenerationFailed' => __('Video generation failed:', 'gpt3-ai-content-generator'),
            ],
            'edit_upload_max_bytes' => 10 * 1024 * 1024,
            'edit_upload_allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'xai_edit_upload_allowed_mime_types' => ['image/jpeg', 'image/png'],
            'openai_models' => $providers_available ? \WPAICG\AIPKit_Providers::get_openai_image_models() : [],
            'azure_models' => $providers_available ? \WPAICG\AIPKit_Providers::get_azure_image_models() : [],
            'google_models' => [
                'image' => $providers_available ? \WPAICG\AIPKit_Providers::get_google_image_models() : [],
                'video' => $providers_available ? \WPAICG\AIPKit_Providers::get_google_video_models() : [],
            ],
            'openrouter_image_models' => $providers_available ? \WPAICG\AIPKit_Providers::get_openrouter_image_models() : [],
            'xai_image_models' => $providers_available ? \WPAICG\AIPKit_Providers::get_xai_image_models() : [],
            'replicate_models' => $providers_available ? \WPAICG\AIPKit_Providers::get_replicate_models() : [],
        ];
    }

    /**
     * Register scripts shared across admin and public contexts.
     *
     * @param string $plugin_version The current plugin version.
     */
    public static function register(string $plugin_version)
    {
        // Vendor JS files are copied to dist/vendor/js/ by esbuild
        $vendor_js_url = WPAICG_PLUGIN_URL . 'dist/vendor/js/';

        // Markdown-it (copied by esbuild)
        $markdownit_url = $vendor_js_url . 'markdown-it.min.js';
        if (!wp_script_is('aipkit_markdown-it', 'registered')) {
            wp_register_script('aipkit_markdown-it', $markdownit_url, [], '14.1.0', true); // Assuming version from previous config
        }

        // Note: Core utility scripts like btn-utils, html-escaper, date-utils
        // are now imported directly into admin-main.js or public-main.js and bundled.
        // They are no longer registered as separate handles here.
    }

    /**
     * Expose shared public asset URLs to frontend bundles that lazy-load vendor scripts.
     *
     * @param string $handle The registered script handle that should receive the config.
     */
    public static function attach_public_asset_urls(string $handle): void
    {
        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        static $attached_handles = [];
        if (isset($attached_handles[$handle])) {
            return;
        }

        $asset_urls = self::get_public_asset_urls();

        wp_add_inline_script(
            $handle,
            'window.aipkitPublicAssetUrls = Object.assign({}, window.aipkitPublicAssetUrls || {}, ' . wp_json_encode($asset_urls) . ');',
            'before'
        );

        $attached_handles[$handle] = true;
    }
}
