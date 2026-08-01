<?php

namespace WPAICG\Shortcodes;

use WPAICG\aipkit_dashboard; // To check module status
use WPAICG\AIPKit_Role_Manager; // To check permissions
use WPAICG\AIPKit_Providers; // To get default provider if needed
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler; // Use settings handler
use WP_Query; // For image history

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Image_Generator_Shortcode
 *
 * Handles the rendering of the [aipkit_image_generator] shortcode.
 * REVISED: Adjusted permission check to allow rendering for guests.
 *          AJAX handler will enforce usage limits/restrictions.
 * UPDATED: Added history attribute and rendering logic.
 */
class AIPKit_Image_Generator_Shortcode
{
    private static $current_atts = [];
    /**
     * Render the shortcode output.
     *
     * @param array $atts Shortcode attributes.
     *        Supported attributes:
     *        - show_provider (bool|string 'true'/'false', default true)
     *        - show_model (bool|string 'true'/'false', default true)
     *        - provider (string 'openai', 'azure' etc. - presets the provider)
     *        - model (string 'gpt-image-2', etc. - presets the model)
     *        - size (string '1024x1024' etc. - presets the size)
     *        - number (int 1-4 - presets the number)
     *        - theme (string 'light', 'dark', 'custom', default 'dark')
     *        - history (bool|string 'true'/'false', default 'false')
     *        - mode (string 'generate'|'edit'|'both', default 'generate')
     *        - default_mode (string 'generate'|'edit', default 'generate', used when mode='both')
     *        - show_mode_switch (bool|string 'true'/'false', default 'true', used when mode='both')
     *
     * @return string HTML output.
     */
    public function render_shortcode($atts = [])
    {
        // 0. Store attributes for localization
        self::$current_atts = shortcode_atts([
            'allowed_models' => null,
        ], $atts, 'aipkit_image_generator');

        // 1. Check if the main module is active
        $module_settings = aipkit_dashboard::get_module_settings();
        if (empty($module_settings['image_generator'])) {
            if (AIPKit_Role_Manager::user_can_view_admin_notices()) {
                return '<p style="color:orange;"><em>[' . esc_html__('AIPKit Image Generator Shortcode: Module is disabled in settings.', 'gpt3-ai-content-generator') . ']</em></p>';
            }
            return '';
        }

        // --- 2.5 Get Default Image Settings ---
        $image_gen_settings = AIPKit_Image_Settings_Ajax_Handler::get_settings();
        $frontend_display_settings = $image_gen_settings['frontend_display'] ?? [];
        $ui_text_settings = $image_gen_settings['ui_text'] ?? [];
        $allowed_models_from_settings = $frontend_display_settings['allowed_models'] ?? '';

        // Prioritize shortcode attribute over global settings
        $final_allowed_models_str = self::$current_atts['allowed_models'] ?? $allowed_models_from_settings;
        // --- End Get Settings ---

        // 3. Parse Attributes
        $default_atts = [
            'show_provider' => 'true',
            'show_model'    => 'true',
            'provider'      => 'openai',
            'model'         => 'gpt-image-2',
            'size'          => '1024x1024',
            'number'        => 1,
            'theme'         => 'dark',
            'history'       => 'false',
            'mode'          => 'generate',
            'default_mode'  => 'generate',
            'show_mode_switch' => 'true',
        ];
        $atts = shortcode_atts($default_atts, $atts, 'aipkit_image_generator');

        $show_provider = filter_var($atts['show_provider'], FILTER_VALIDATE_BOOLEAN);
        $show_model    = filter_var($atts['show_model'], FILTER_VALIDATE_BOOLEAN);
        $show_history  = filter_var($atts['history'], FILTER_VALIDATE_BOOLEAN); // NEW

        $mode = sanitize_key($atts['mode'] ?? 'generate');
        $allowed_modes = ['generate', 'edit', 'both'];
        if (!in_array($mode, $allowed_modes, true)) {
            $mode = 'generate';
        }

        $default_mode = sanitize_key($atts['default_mode'] ?? 'generate');
        $allowed_default_modes = ['generate', 'edit'];
        if (!in_array($default_mode, $allowed_default_modes, true)) {
            $default_mode = 'generate';
        }

        $show_mode_switch = filter_var($atts['show_mode_switch'], FILTER_VALIDATE_BOOLEAN);
        if ($mode !== 'both') {
            $default_mode = $mode;
            $show_mode_switch = false;
        }

        $preset_provider_from_att = !empty($atts['provider']) ? strtolower(sanitize_text_field($atts['provider'])) : null;
        $preset_model    = !empty($atts['model']) ? sanitize_text_field($atts['model']) : null;
        $preset_size     = !empty($atts['size']) ? sanitize_text_field($atts['size']) : null;
        $preset_number   = !empty($atts['number']) ? absint($atts['number']) : null;
        $valid_themes = ['light', 'dark', 'custom'];
        $theme = isset($atts['theme']) && in_array(strtolower($atts['theme']), $valid_themes, true)
                 ? strtolower($atts['theme'])
                 : 'dark';

        // --- 4. Determine Final Values ---
        $final_provider_key = $preset_provider_from_att ?? 'openai';
        switch ($final_provider_key) {
            case 'openai':
                $final_provider_normalized = 'OpenAI';
                break;
            case 'openrouter':
                $final_provider_normalized = 'OpenRouter';
                break;
            case 'azure':
                $final_provider_normalized = 'Azure';
                break;
            case 'google':
                $final_provider_normalized = 'Google';
                break;
            case 'xai':
                $final_provider_normalized = 'xAI';
                break;
            case 'replicate':
                $final_provider_normalized = 'Replicate';
                break;
            default:
                $final_provider_normalized = 'OpenAI';
                break;
        }
        $final_model = $preset_model;
        $final_size = $preset_size;
        $final_number = $preset_number;
        // --- End Determine Final Values ---

        // 5. Signal assets needed
        add_filter('aipkit_enqueue_public_image_generator_assets', '__return_true');

        // 6. Prepare data for the view
        $view_data = [
            'nonce' => wp_create_nonce('aipkit_image_generator_nonce'),
            'show_provider' => $show_provider,
            'show_model'    => $show_model,
            'preset_provider' => $preset_provider_from_att ? $final_provider_normalized : null,
            'preset_model'    => $preset_model,
            'preset_size'     => $preset_size,
            'preset_number'   => $preset_number,
            'final_provider' => $final_provider_normalized,
            'final_model'    => $final_model,
            'final_size'     => $final_size,
            'final_number'   => $final_number,
            'theme'          => $theme,
            'show_history'   => $show_history, // NEW: Pass to view
            'image_history_html' => ($show_history && is_user_logged_in()) ? $this->render_image_history($mode) : '', // NEW: Render history HTML
            'allowed_models' => $final_allowed_models_str,
            'mode'           => $mode,
            'default_mode'   => $default_mode,
            'show_mode_switch' => $show_mode_switch,
            'ui_text'        => $ui_text_settings,
        ];

        // 7. Include the partial view
        ob_start();
        extract($view_data);
        $view_path = WPAICG_PLUGIN_DIR . 'public/views/shortcodes/image-generator.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<p style="color:red;">Image Generator UI cannot be loaded.</p>';
        }
        return ob_get_clean();
    }

    public static function get_current_attributes()
    {
        return self::$current_atts;
    }

    public static function build_history_query_args(int $user_id, int $page = 1): array
    {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'author'         => $user_id,
            'posts_per_page' => 20,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- History must filter plugin-generated media by stored generation flags.
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_aipkit_generated_image',
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'key'     => '_aipkit_generated_video',
                    'value'   => '1',
                    'compare' => '=',
                ]
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($page > 1) {
            $args['paged'] = $page;
        }

        return $args;
    }

    public static function render_history_item(int $attachment_id, bool $allow_edit_action): string
    {
        $full_url = wp_get_attachment_url($attachment_id);
        $is_video = get_post_meta($attachment_id, '_aipkit_generated_video', true) === '1';
        $is_image = get_post_meta($attachment_id, '_aipkit_generated_image', true) === '1';

        ob_start();
        if ($is_video) {
            $prompt = get_post_meta($attachment_id, '_aipkit_video_prompt', true);
            $provider = get_post_meta($attachment_id, '_aipkit_video_provider', true);
            $model = get_post_meta($attachment_id, '_aipkit_video_model', true);
            $size = get_post_meta($attachment_id, '_aipkit_video_size', true);
            $duration = get_post_meta($attachment_id, '_aipkit_video_duration', true);
            /* translators: %d: duration in seconds */
            $duration_display = $duration ? sprintf(__('Duration: %ds', 'gpt3-ai-content-generator'), intval($duration)) : '';
            ?>
            <div class="aipkit-image-history-item aipkit-video-history-item">
                <div class="aipkit-video-preview">
                    <video controls preload="metadata" style="width: 100%; max-width: 200px; height: auto;">
                        <source src="<?php echo esc_url($full_url); ?>" type="video/mp4">
                        <?php esc_html_e('Your browser does not support the video tag.', 'gpt3-ai-content-generator'); ?>
                    </video>
                    <div class="aipkit-video-overlay">
                        <span class="aipkit-media-type-badge"><?php esc_html_e('VIDEO', 'gpt3-ai-content-generator'); ?></span>
                    </div>
                </div>
                <button type="button" class="aipkit-image-history-delete-btn" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" title="<?php esc_attr_e('Delete Video', 'gpt3-ai-content-generator'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
                <div class="aipkit-image-history-info">
                    <?php if ($prompt): ?>
                        <p class="aipkit-image-history-prompt" title="<?php echo esc_attr($prompt); ?>">
                            <strong><?php esc_html_e('Prompt:', 'gpt3-ai-content-generator'); ?></strong> <?php echo esc_html(wp_trim_words($prompt, 10, '...')); ?>
                        </p>
                    <?php endif; ?>
                    <p class="aipkit-image-history-meta">
                        <?php
                        $meta_parts = array_filter([$provider, $model, $size, $duration_display]);
                        echo esc_html(implode(' / ', $meta_parts));
                        ?>
                    </p>
                </div>
            </div>
            <?php
        } elseif ($is_image) {
            $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $prompt = get_post_meta($attachment_id, '_aipkit_image_prompt', true);
            $provider = get_post_meta($attachment_id, '_aipkit_image_provider', true);
            $model = get_post_meta($attachment_id, '_aipkit_image_model', true);
            $size = get_post_meta($attachment_id, '_aipkit_image_size', true);
            $image_url_path = wp_parse_url((string) $full_url, PHP_URL_PATH);
            $image_file_name = is_string($image_url_path) && $image_url_path !== ''
                ? sanitize_file_name(wp_basename($image_url_path))
                : 'image-' . $attachment_id . '.png';
            ?>
            <div class="aipkit-image-history-item">
                <a href="<?php echo esc_url($full_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Reason: The image source is correctly retrieved using a WordPress function. ?>
                    <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($prompt ?: 'Image'); ?>">
                    <div class="aipkit-image-overlay">
                        <span class="aipkit-media-type-badge"><?php esc_html_e('IMAGE', 'gpt3-ai-content-generator'); ?></span>
                    </div>
                </a>
                <?php if ($allow_edit_action): ?>
                    <button
                        type="button"
                        class="aipkit-image-history-edit-btn"
                        data-image-url="<?php echo esc_url($full_url); ?>"
                        data-image-name="<?php echo esc_attr($image_file_name); ?>"
                        title="<?php esc_attr_e('Edit Image', 'gpt3-ai-content-generator'); ?>"
                        aria-label="<?php esc_attr_e('Edit Image', 'gpt3-ai-content-generator'); ?>"
                    >
                        <span class="dashicons dashicons-edit"></span>
                        <span class="aipkit_spinner" hidden></span>
                    </button>
                <?php endif; ?>
                <button type="button" class="aipkit-image-history-delete-btn" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" title="<?php esc_attr_e('Delete Image', 'gpt3-ai-content-generator'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
                <div class="aipkit-image-history-info">
                    <?php if ($prompt): ?>
                        <p class="aipkit-image-history-prompt" title="<?php echo esc_attr($prompt); ?>">
                            <strong><?php esc_html_e('Prompt:', 'gpt3-ai-content-generator'); ?></strong> <?php echo esc_html(wp_trim_words($prompt, 10, '...')); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($model): ?>
                         <p class="aipkit-image-history-meta">
                            <?php echo esc_html($provider . ' / ' . $model . ' / ' . $size); ?>
                         </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Renders the HTML for the user's image generation history.
     *
     * @return string HTML for the image history section.
     */
    private function render_image_history(string $shortcode_mode = 'generate'): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $allow_edit_action = in_array($shortcode_mode, ['edit', 'both'], true);

        $user_id = get_current_user_id();
        $query = new WP_Query(self::build_history_query_args($user_id));

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return '';
        }

        ob_start();
        ?>
        <div class="aipkit-image-history-grid">
            <?php while ($query->have_posts()) : $query->the_post();
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_history_item() returns plugin-generated markup with escaped dynamic values.
                echo self::render_history_item((int) get_the_ID(), $allow_edit_action);
            endwhile; ?>
        </div>
        <?php if ($query->max_num_pages > 1): ?>
            <div class="aipkit-image-history-load-more-container">
                <button type="button" class="aipkit_image_generator_btn aipkit_image_generator_btn_secondary aipkit_image_generator_btn_icon aipkit-image-history-load-more-btn" title="<?php esc_attr_e('Load More', 'gpt3-ai-content-generator'); ?>" data-current-page="1" data-max-pages="<?php echo esc_attr($query->max_num_pages); ?>">
                    <span class="aipkit_btn-icon-content dashicons dashicons-update-alt"></span>
                    <span class="aipkit_spinner" style="display: none;"></span>
                </button>
            </div>
        <?php endif; ?>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}
