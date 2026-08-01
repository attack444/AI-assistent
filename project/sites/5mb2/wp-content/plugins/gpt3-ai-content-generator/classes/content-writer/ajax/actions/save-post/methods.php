<?php

namespace WPAICG\ContentWriter\Ajax\Actions\SavePost;

use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Save_Post_Action;
use WP_Error;
use WPAICG\Lib\Utils\AIPKit_Google_Credentials_Handler;
use WPAICG\ContentWriter\TemplateManagerMethods as CwTemplateMethods;
use WPAICG\Utils\AIPKit_TOC_Generator;
use WPAICG\ContentWriter\AIPKit_Image_Injector;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper;
use WPAICG\Images\AIPKit_Image_Storage_Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates nonce and module access permissions for saving a post.
 *
 * @param AIPKit_Content_Writer_Save_Post_Action $handler The handler instance.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function validate_permissions_logic(AIPKit_Content_Writer_Save_Post_Action $handler)
{
    return $handler->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
}

/**
 * Extracts and sanitizes post data from the $_POST superglobal.
 *
 * @return array The sanitized post data.
 */
function extract_post_data_logic(): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked by the calling handler in validate_permissions_logic().
    $raw_data = isset($_POST) ? wp_unslash($_POST) : [];

    $sanitized = [];
    $sanitized['post_title']   = isset($raw_data['post_title']) ? sanitize_text_field($raw_data['post_title']) : 'AI Generated Content';
    $sanitized['post_content'] = isset($raw_data['post_content']) ? wp_kses_post($raw_data['post_content']) : '';
    $sanitized['excerpt'] = isset($raw_data['generated_excerpt']) ? wp_kses_post($raw_data['generated_excerpt']) : ''; // ADDED
    $sanitized['tags'] = isset($raw_data['generated_tags']) ? sanitize_text_field($raw_data['generated_tags']) : '';
    $sanitized['meta_description'] = isset($raw_data['meta_description']) ? sanitize_textarea_field($raw_data['meta_description']) : '';
    $sanitized['focus_keyword'] = isset($raw_data['focus_keyword']) ? sanitize_text_field($raw_data['focus_keyword']) : '';
    $sanitized['post_type']    = isset($raw_data['post_type']) ? sanitize_key($raw_data['post_type']) : 'post';
    $sanitized['post_author']  = isset($raw_data['post_author']) ? absint($raw_data['post_author']) : get_current_user_id();
    $sanitized['post_status']  = isset($raw_data['post_status']) ? sanitize_key($raw_data['post_status']) : 'draft';
    $sanitized['cw_generation_mode'] = isset($raw_data['cw_generation_mode']) ? sanitize_key($raw_data['cw_generation_mode']) : 'task';
    $sanitized['schedule_date'] = isset($raw_data['post_schedule_date']) ? sanitize_text_field($raw_data['post_schedule_date']) : '';
    $sanitized['schedule_time'] = isset($raw_data['post_schedule_time']) ? sanitize_text_field($raw_data['post_schedule_time']) : '';
    $sanitized['generate_toc'] = isset($raw_data['generate_toc']) && $raw_data['generate_toc'] === '1' ? '1' : '0';
    $sanitized['generate_seo_slug'] = isset($raw_data['generate_seo_slug']) && $raw_data['generate_seo_slug'] === '1' ? '1' : '0'; // NEW
    $seo_score_profile = isset($raw_data['seo_score_profile']) ? sanitize_key((string) $raw_data['seo_score_profile']) : 'auto';
    $sanitized['seo_score_profile'] = in_array($seo_score_profile, ['auto', 'aipkit', 'yoast', 'rank_math', 'aioseo', 'framework'], true) ? $seo_score_profile : 'auto';
    $sanitized['seo_score_disabled_rules'] = class_exists('\\WPAICG\\ContentWriter\\SEO\\AIPKit_Content_Writer_SEO_Config')
        ? \WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config::sanitize_disabled_rules(
            $raw_data['seo_score_disabled_rules']
                ?? \WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config::default_disabled_rule_ids()
        )
        : '[]';
    $sanitized['smart_seo_slug'] = isset($raw_data['smart_seo_slug']) ? sanitize_title((string) $raw_data['smart_seo_slug']) : '';
    $sanitized['gsheets_sheet_id'] = isset($raw_data['gsheets_sheet_id']) ? sanitize_text_field($raw_data['gsheets_sheet_id']) : '';
    $sanitized['gsheets_row_index'] = isset($raw_data['gsheets_row_index']) ? absint($raw_data['gsheets_row_index']) : 0;
    $sanitized['gsheets_credentials'] = class_exists(AIPKit_Google_Credentials_Handler::class)
        ? AIPKit_Google_Credentials_Handler::process_credentials($raw_data['gsheets_credentials'] ?? null)
        : null;

    $category_ids_from_post = isset($raw_data['post_categories']) && is_array($raw_data['post_categories'])
        ? array_map('absint', $raw_data['post_categories'])
        : [];
    $sanitized['category_ids'] = array_filter($category_ids_from_post, function ($id) {
        return $id > 0;
    });

    $sanitized['image_data'] = null;
    if (isset($raw_data['image_data']) && $raw_data['image_data'] !== '') {
        if (is_array($raw_data['image_data'])) {
            $sanitized['image_data'] = $raw_data['image_data'];
        } elseif (is_string($raw_data['image_data'])) {
            $image_data_json = trim($raw_data['image_data']);
            if ($image_data_json !== '') {
                $decoded_image_data = json_decode($image_data_json, true);

                // Fallback for payloads that might still be over-escaped by transport.
                if (!is_array($decoded_image_data)) {
                    $decoded_image_data = json_decode(stripslashes($image_data_json), true);
                }

                if (is_array($decoded_image_data)) {
                    $sanitized['image_data'] = $decoded_image_data;
                }
            }
        }
    }
    $sanitized['image_alignment'] = isset($raw_data['image_alignment']) ? sanitize_key($raw_data['image_alignment']) : 'none';
    $sanitized['image_size'] = isset($raw_data['image_size']) ? sanitize_key($raw_data['image_size']) : 'large';

    return $sanitized;
}

/**
 * Validates the sanitized post data.
 *
 * @param array $data The sanitized post data.
 * @return true|WP_Error True if data is valid, WP_Error otherwise.
 */
function validate_post_data_logic(array $data)
{
    if (empty($data['post_title'])) {
        return new WP_Error('missing_title', __('Post title cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (empty($data['post_content'])) {
        return new WP_Error('missing_content', __('Post content cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (!post_type_exists($data['post_type'])) {
        return new WP_Error('invalid_post_type', __('Invalid post type specified.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (!user_can($data['post_author'], 'edit_posts') || !user_can($data['post_author'], get_post_type_object($data['post_type'])->cap->create_posts)) {
        return new WP_Error('invalid_author', __('Selected author does not have permission to create this post type.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }
    $valid_statuses = ['draft', 'publish', 'pending', 'private'];
    if (!in_array($data['post_status'], $valid_statuses, true)) {
        // While the data extractor defaults this, a validation step is still good practice.
        return new WP_Error('invalid_status', __('Invalid post status specified.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    return true;
}

/**
 * Modifies the post array for scheduling if a future date/time is provided for a 'publish' status.
 *
 * @param array &$postarr Reference to the post array for wp_insert_post.
 * @param array $data The sanitized post data.
 * @return void
 */
function prepare_scheduled_post_logic(array &$postarr, array $data): void
{
    if ($data['post_status'] === 'publish' && !empty($data['schedule_date']) && !empty($data['schedule_time'])) {
        $schedule_datetime_str = $data['schedule_date'] . ' ' . $data['schedule_time'] . ':00';
        $schedule_timestamp_gmt = get_gmt_from_date($schedule_datetime_str);
        $current_timestamp_gmt = current_time('timestamp', true);

        if (strtotime($schedule_timestamp_gmt) > $current_timestamp_gmt) {
            $postarr['post_status'] = 'future';
            $postarr['post_date'] = get_date_from_gmt($schedule_timestamp_gmt, 'Y-m-d H:i:s');
            $postarr['post_date_gmt'] = $schedule_timestamp_gmt;
        }
    }
}

/**
 * Adds category information to the post array for standard 'post' types.
 *
 * @param array &$postarr Reference to the post array for wp_insert_post.
 * @param array $data The sanitized post data containing 'category_ids'.
 * @return void
 */
function prepare_categories_logic(array &$postarr, array $data): void
{
    if (!empty($data['category_ids'])) {
        if ($data['post_type'] === 'post') {
            $postarr['post_category'] = $data['category_ids'];
        }
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Ensure dependencies are loaded
if (!function_exists('WPAICG\ContentWriter\TemplateManagerMethods\calculate_schedule_datetime_logic')) {
    $path = WPAICG_PLUGIN_DIR . 'classes/content-writer/template-manager/methods.php';
    if (file_exists($path)) {
        require_once $path;
    }
}
if (!class_exists('\WPAICG\Utils\AIPKit_TOC_Generator')) {
    $toc_generator_path = WPAICG_PLUGIN_DIR . 'includes/utils/class-aipkit-toc-generator.php';
    if (file_exists($toc_generator_path)) {
        require_once $toc_generator_path;
    }
}
if (!class_exists('\WPAICG\SEO\AIPKit_SEO_Helper')) {
    $seo_helper_path = WPAICG_PLUGIN_DIR . 'classes/seo/seo-helper.php';
    if (file_exists($seo_helper_path)) {
        require_once $seo_helper_path;
    }
}
if (!class_exists(AIPKit_Image_Injector::class)) {
    $injector_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-image-injector.php';
    if (file_exists($injector_path)) {
        require_once $injector_path;
    }
}
if (!class_exists(AIPKit_Image_Storage_Helper::class)) {
    $storage_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-storage-helper.php';
    if (file_exists($storage_path)) {
        require_once $storage_path;
    }
}
if (!class_exists(AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper::class)) {
    $image_alt_helper_path = WPAICG_LIB_DIR . 'content-writer/seo/class-aipkit-content-writer-smart-seo-image-alt-helper.php';
    if (file_exists($image_alt_helper_path)) {
        require_once $image_alt_helper_path;
    }
}

/**
 * Inserts the generated content as a new post.
 *
 * @param array      $postarr           The final prepared post array.
 * @param string|null $excerpt           Optional post excerpt.
 * @param array|null $image_data        Optional data for generated images.
 * @param string     $image_alignment   Optional alignment for injected images.
 * @param string     $image_size        Optional display size for injected images.
 * @param string|null $focus_keyword     Optional focus keyword for SEO-aware image alt fallback.
 * @param array       $seo_context       Optional Smart SEO profile and disabled rule context.
 * @return int|WP_Error The new post ID or a WP_Error on failure.
 */
function insert_post_logic(array $postarr, ?string $excerpt = null, ?array $image_data = null, string $image_alignment = 'none', string $image_size = 'large', ?string $focus_keyword = null, array $seo_context = [])
{
    if (class_exists(\WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::class)) {
        $postarr['post_title'] = \WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::clean_title(
            (string) ($postarr['post_title'] ?? ''),
            (string) $focus_keyword
        );
    }

    $html_content = $postarr['post_content'];
    if (class_exists(\WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::class)) {
        $html_content = \WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::clean_article_content((string) $html_content, (string) $focus_keyword);
    }

    $html_content = \WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner::convert_basic_markdown_to_html((string) $html_content);
    $postarr['post_content'] = $html_content;

    if (is_array($image_data) && class_exists(AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper::class)) {
        AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper::maybe_prepare_rank_math_image_alt($image_data, (string) $focus_keyword, $seo_context);
    }

    if (!empty($image_data['in_content_images']) && class_exists(AIPKit_Image_Storage_Helper::class)) {
        $normalized_images = [];
        foreach ($image_data['in_content_images'] as $image_item) {
            $image_alt = class_exists(AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper::class)
                ? AIPKit_Content_Writer_Smart_SEO_Image_Alt_Helper::resolve_image_alt_text($image_item)
                : '';
            if (empty($image_item['attachment_id'])) {
                $fallback_url = $image_item['media_library_url'] ?? ($image_item['url'] ?? ($image_item['src'] ?? ($image_item['image_url'] ?? null)));
                if (!empty($fallback_url)) {
                    $image_payload = ['url' => $fallback_url];
                    if ($image_alt !== '') {
                        $image_payload['alt'] = $image_alt;
                    }
                    $attachment_id = AIPKit_Image_Storage_Helper::save_image_to_media_library(
                        $image_payload,
                        $postarr['post_title'],
                        [],
                        absint($postarr['post_author'])
                    );
                    if (!is_wp_error($attachment_id) && $attachment_id) {
                        $image_item['attachment_id'] = $attachment_id;
                        $image_item['media_library_url'] = wp_get_attachment_url($attachment_id);
                    }
                }
            }
            if (!empty($image_item['attachment_id']) && $image_alt !== '') {
                $attachment_id = absint($image_item['attachment_id']);
                if ($attachment_id > 0 && trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) === '') {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_alt);
                    update_post_meta($attachment_id, '_aipkit_image_alt_text', $image_alt);
                }
            }
            $normalized_images[] = $image_item;
        }
        $image_data['in_content_images'] = $normalized_images;
    }
    if (empty($image_data['featured_image_id']) && !empty($image_data['featured_image_url']) && class_exists(AIPKit_Image_Storage_Helper::class)) {
        $featured_attachment_id = AIPKit_Image_Storage_Helper::save_image_to_media_library(
            ['url' => $image_data['featured_image_url']],
            $postarr['post_title'],
            [],
            absint($postarr['post_author'])
        );
        if (!is_wp_error($featured_attachment_id) && $featured_attachment_id) {
            $image_data['featured_image_id'] = $featured_attachment_id;
        }
    }

    if (!empty($image_data['in_content_images']) && class_exists(AIPKit_Image_Injector::class)) {
        $image_injector = new AIPKit_Image_Injector();
        $postarr['post_content'] = $image_injector->inject_images(
            $postarr['post_content'],
            $image_data['in_content_images'],
            $image_data['placement_settings']['placement'] ?? 'after_first_h2',
            absint($image_data['placement_settings']['param_x'] ?? 2),
            $image_alignment,
            $image_size
        );
    }

    // Generate ToC after images have been placed
    if (isset($postarr['generate_toc']) && $postarr['generate_toc'] === '1' && class_exists(AIPKit_TOC_Generator::class)) {
        $toc_result = AIPKit_TOC_Generator::generate($postarr['post_content'], [
            'rank_math_compatible' => class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')
                && sanitize_key((string) (\WPAICG\SEO\AIPKit_SEO_Helper::get_active_plugin_profile()['profile'] ?? '')) === 'rank_math',
        ]);
        if (!empty($toc_result['toc'])) {
            // Prepend ToC to the modified content
            $postarr['post_content'] = $toc_result['toc'] . $toc_result['content'];
        }
    }
    // Unset the custom key before passing to wp_insert_post
    unset($postarr['generate_toc']);

    // Add excerpt if provided
    if (!empty($excerpt)) {
        $postarr['post_excerpt'] = $excerpt;
    }

    $post_id_or_error = wp_insert_post($postarr, true);

    if (is_wp_error($post_id_or_error)) {
        return $post_id_or_error;
    }

    if (!empty($image_data['featured_image_id'])) {
        set_post_thumbnail($post_id_or_error, $image_data['featured_image_id']);
    }

    return $post_id_or_error;
}

/**
 * Assigns taxonomies (like categories) to non-standard post types after creation.
 *
 * @param int $post_id The ID of the newly created post.
 * @param array $data The sanitized post data containing 'post_type' and 'category_ids'.
 * @return void
 */
function assign_taxonomies_logic(int $post_id, array $data): void
{
    if ($data['post_type'] !== 'post' && !empty($data['category_ids'])) {
        $taxonomy = 'category'; // Hardcoded for now, could be made dynamic if needed
        if (is_object_in_taxonomy($data['post_type'], $taxonomy)) {
            wp_set_post_terms($post_id, $data['category_ids'], $taxonomy);
        }
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Ensure the helper function is available. It is loaded by the main Dependency Loader.
if (!class_exists('\WPAICG\SEO\AIPKit_SEO_Helper')) {
    $seo_helper_path = WPAICG_PLUGIN_DIR . 'classes/seo/seo-helper.php';
    if (file_exists($seo_helper_path)) {
        require_once $seo_helper_path;
    }
}

/**
 * Saves the SEO meta description for a given post.
 *
 * @param int    $post_id The ID of the post.
 * @param string $meta_description The meta description to save.
 * @return void
 */
function save_seo_meta_logic(int $post_id, string $meta_description): void
{
    if ($post_id > 0 && !empty($meta_description) && class_exists('\WPAICG\SEO\AIPKit_SEO_Helper')) {
        \WPAICG\SEO\AIPKit_SEO_Helper::update_meta_description($post_id, $meta_description);
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Ensure the helper function is available. It is loaded by the main Dependency Loader.
if (!class_exists('\WPAICG\SEO\AIPKit_SEO_Helper')) {
    $seo_helper_path = WPAICG_PLUGIN_DIR . 'classes/seo/seo-helper.php';
    if (file_exists($seo_helper_path)) {
        require_once $seo_helper_path;
    }
}

/**
 * Saves the SEO focus keyword for a given post.
 *
 * @param int    $post_id The ID of the post.
 * @param string $focus_keyword The focus keyword to save.
 * @return void
 */
function save_seo_focus_keyword_logic(int $post_id, string $focus_keyword): void
{
    if ($post_id > 0 && !empty($focus_keyword) && class_exists('\WPAICG\SEO\AIPKit_SEO_Helper')) {
        \WPAICG\SEO\AIPKit_SEO_Helper::update_focus_keyword($post_id, $focus_keyword);
    }
}

/**
 * Sets the tags for a given post.
 *
 * @param int    $post_id The ID of the post.
 * @param string $tags A comma-separated string of tags.
 * @return void
 */
function set_post_tags_logic(int $post_id, string $tags): void
{
    if ($post_id > 0 && !empty($tags)) {
        // wp_set_post_tags handles creating tags that don't exist
        // and sanitizing them. It accepts a string or an array.
        wp_set_post_tags($post_id, $tags, false);
    }
}
