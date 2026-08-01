<?php

namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WPAICG\SEO\AIPKit_SEO_Helper;
use WP_Error;

class AIPKit_PostEnhancer_Update_Meta extends AIPKit_Post_Enhancer_Base_Ajax_Action {
    public function handle(): void {
        $permission_check = $this->check_permissions('aipkit_update_meta_nonce');
        if (is_wp_error($permission_check)) { $this->send_error_response($permission_check); return; }

        $post = $this->get_post();
        if (is_wp_error($post)) { $this->send_error_response($post); return; }
        $feature_permission = $this->check_row_assistant_permissions($post);
        if (is_wp_error($feature_permission)) { $this->send_error_response($feature_permission); return; }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_permissions.
        $new_meta_desc = isset($_POST['new_value']) ? sanitize_text_field(wp_unslash($_POST['new_value'])) : '';

        if (strlen($new_meta_desc) > 300) {
            $this->send_error_response(new WP_Error('meta_too_long', __('Meta description is too long.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        AIPKit_SEO_Helper::update_meta_description($post->ID, $new_meta_desc);
        wp_send_json_success(['message' => __('Meta description updated successfully.', 'gpt3-ai-content-generator')]);
    }
}
