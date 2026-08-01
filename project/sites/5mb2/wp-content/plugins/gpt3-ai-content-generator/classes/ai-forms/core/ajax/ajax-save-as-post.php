<?php


namespace WPAICG\AIForms\Core\Ajax;

use WPAICG\AIForms\Core\AIPKit_AI_Form_Processor;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for saving generated AI Form content as a new WordPress post.
 * Called by AIPKit_AI_Form_Processor::ajax_save_as_post().
 *
 * @param AIPKit_AI_Form_Processor $processorInstance The instance of the processor class.
 * @return void
 */
function save_as_post_logic(AIPKit_AI_Form_Processor $processorInstance): void
{
    // 1. Security & Permission Checks
    check_ajax_referer('aipkit_ai_form_save_as_post_nonce', '_ajax_nonce');
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('You do not have permission to create posts.', 'gpt3-ai-content-generator')], 403);
        return;
    }

    // 2. Get and Sanitize Data
    $post_title   = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : 'AI Form Result';
    $post_content = isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '';

    if (empty($post_title)) {
        wp_send_json_error(['message' => __('Post title cannot be empty.', 'gpt3-ai-content-generator')], 400);
        return;
    }
    if (empty($post_content)) {
        wp_send_json_error(['message' => __('Post content cannot be empty.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    // 3. Create the post
    $postarr = [
        'post_title'   => $post_title,
        'post_content' => $post_content,
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
    ];

    $post_id = wp_insert_post($postarr, true);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => __('Failed to save post:', 'gpt3-ai-content-generator') . ' ' . $post_id->get_error_message()], 500);
        return;
    }

    // 4. Send Success Response
    wp_send_json_success([
        'message' => __('Post saved as draft!', 'gpt3-ai-content-generator'),
        'post_id' => $post_id,
        'edit_link' => get_edit_post_link($post_id, 'raw'),
    ]);
}