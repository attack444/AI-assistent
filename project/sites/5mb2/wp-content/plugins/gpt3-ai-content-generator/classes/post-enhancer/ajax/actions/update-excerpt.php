<?php

namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WP_Error;

class AIPKit_PostEnhancer_Update_Excerpt extends AIPKit_Post_Enhancer_Base_Ajax_Action {
    public function handle(): void {
        $permission_check = $this->check_permissions('aipkit_update_excerpt_nonce');
        if (is_wp_error($permission_check)) { $this->send_error_response($permission_check); return; }

        $post = $this->get_post();
        if (is_wp_error($post)) { $this->send_error_response($post); return; }
        $feature_permission = $this->check_row_assistant_permissions($post);
        if (is_wp_error($feature_permission)) { $this->send_error_response($feature_permission); return; }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce verification is handled in the base class.
        $new_excerpt = isset($_POST['new_value']) ? wp_kses_post(wp_unslash($_POST['new_value'])) : '';
        
        $update_result = wp_update_post(['ID' => $post->ID, 'post_excerpt' => $new_excerpt], true);
        if (is_wp_error($update_result)) {
            wp_send_json_error(['message' => 'Failed to update post excerpt: ' . $update_result->get_error_message()], 500);
        } else {
            wp_send_json_success(['message' => __('Post excerpt updated successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}
