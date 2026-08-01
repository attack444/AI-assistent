<?php


namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Image_Handler;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load the new modular logic files
$logic_path = __DIR__ . '/save-post/';
require_once $logic_path . 'methods.php';

$shared_path = __DIR__ . '/shared/';
require_once $shared_path . 'methods.php';


/**
 * Handles the AJAX action for saving generated content as a new WordPress post.
 * This class now acts as an orchestrator for modularized logic functions.
 */
class AIPKit_Content_Writer_Save_Post_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    /**
     * Handles the AJAX request for saving a post by orchestrating calls to modular functions.
     */
    public function handle()
    {
        // 1. Validate permissions
        $permission_check = SavePost\validate_permissions_logic($this);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // 2. Extract and sanitize all data from the POST request
        $post_data = SavePost\extract_post_data_logic();

        // 3. Validate the extracted data
        $validation_result = SavePost\validate_post_data_logic($post_data);
        if (is_wp_error($validation_result)) {
            $this->send_wp_error($validation_result);
            return;
        }

        $image_data = $post_data['image_data'] ?? null;
        $image_alignment = $post_data['image_alignment'] ?? 'none';
        $image_size = $post_data['image_size'] ?? 'large';

        $smart_seo_slug = !empty($post_data['smart_seo_slug']) ? sanitize_title((string) $post_data['smart_seo_slug']) : '';

        // 4. Prepare the initial post array for wp_insert_post
        $postarr = [
            'post_title'   => $post_data['post_title'],
            'post_content' => $post_data['post_content'],
            'post_type'    => $post_data['post_type'],
            'post_author'  => $post_data['post_author'],
            'post_status'  => $post_data['post_status'],
            'generate_toc' => $post_data['generate_toc'],
        ];
        if ($smart_seo_slug !== '') {
            $postarr['post_name'] = $smart_seo_slug;
        }

        // 5. Modify the post array for scheduling if needed
        SavePost\prepare_scheduled_post_logic($postarr, $post_data);

        // 6. Add categories to the post array if the post type is 'post'
        SavePost\prepare_categories_logic($postarr, $post_data);

        // 7. Insert the post into the database (this now handles image/toc injection)
        $post_id_result = SavePost\insert_post_logic($postarr, $post_data['excerpt'] ?? null, $image_data, $image_alignment, $image_size, $post_data['focus_keyword'] ?? '', $post_data);
        if (is_wp_error($post_id_result)) {
            $this->send_wp_error($post_id_result);
            return;
        }

        // 8. Assign taxonomies (like categories) to non-standard post types
        SavePost\assign_taxonomies_logic($post_id_result, $post_data);

        // 9. Save SEO Meta Description
        if (!empty($post_data['meta_description'])) {
            SavePost\save_seo_meta_logic($post_id_result, $post_data['meta_description']);
        }

        if (!empty($post_data['focus_keyword'])) {
            SavePost\save_seo_focus_keyword_logic($post_id_result, $post_data['focus_keyword']);
        }

        if (!empty($post_data['tags'])) {
            SavePost\set_post_tags_logic($post_id_result, $post_data['tags']);
        }

        if ($smart_seo_slug === '' && isset($post_data['generate_seo_slug']) && $post_data['generate_seo_slug'] === '1' && class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')) {
            \WPAICG\SEO\AIPKit_SEO_Helper::update_post_slug_for_seo($post_id_result);
        }

        Shared\maybe_update_gsheets_row_status_logic($post_data, 'Processed on');

        $post_status = get_post_status($post_id_result);
        $view_link = get_permalink($post_id_result);
        if (in_array($post_status, ['draft', 'pending', 'auto-draft'], true)) {
            $view_link = get_preview_post_link($post_id_result);
        }

        // 13. Send a success response
        wp_send_json_success([
            'message' => __('Post saved successfully!', 'gpt3-ai-content-generator'),
            'post_id' => $post_id_result,
            'edit_link' => get_edit_post_link($post_id_result, 'raw'),
            'view_link' => $view_link
        ]);
    }
}
