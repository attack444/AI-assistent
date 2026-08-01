<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WPAICG\SEO\AIPKit_SEO_Helper;
use WP_Error;

class AIPKit_PostEnhancer_Update_Tags extends AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_permissions('aipkit_update_tags_nonce');
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
        $new_tags = isset($_POST['new_value']) ? sanitize_text_field(wp_unslash($_POST['new_value'])) : '';

        // Use the SEO helper to correctly set tags for any post type
        $result = AIPKit_SEO_Helper::update_tags($post->ID, $new_tags);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to update post tags.'], 500);
        } else {
            wp_send_json_success(['message' => __('Post tags updated successfully.', 'gpt3-ai-content-generator')]);
        }
    }
}
