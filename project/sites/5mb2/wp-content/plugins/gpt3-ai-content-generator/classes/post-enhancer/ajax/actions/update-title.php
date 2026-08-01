<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WP_Error;

class AIPKit_PostEnhancer_Update_Title extends AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_permissions('aipkit_update_title_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_error_response($permission_check);
            return;
        }

        $post = $this->get_post();
        if (is_wp_error($post)) {
            $this->send_error_response($post);
            return;
        }
        $feature_permission = $this->check_row_assistant_permissions($post);
        if (is_wp_error($feature_permission)) {
            $this->send_error_response($feature_permission);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_permissions.
        $new_title = isset($_POST['new_value']) ? sanitize_text_field(wp_unslash($_POST['new_value'])) : '';
        if (empty($new_title)) {
            $this->send_error_response(new WP_Error('empty_title', __('New title cannot be empty.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        $update_result = wp_update_post(['ID' => $post->ID, 'post_title' => $new_title], true);
        if (is_wp_error($update_result)) {
            wp_send_json_error(['message' => 'Failed to update post title: ' . $update_result->get_error_message()], 500);
        } else {
            wp_send_json_success(['message' => __('Post title updated successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}
