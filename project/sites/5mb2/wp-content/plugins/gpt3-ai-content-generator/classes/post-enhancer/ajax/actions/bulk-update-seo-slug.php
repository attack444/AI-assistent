<?php

// This handles updating SEO slug for a post during bulk enhancement

namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WPAICG\SEO\AIPKit_SEO_Helper;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AIPKit_PostEnhancer_Bulk_Update_SEO_Slug extends AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_permissions('aipkit_generate_title_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_error_response($permission_check);
            return;
        }

        $post = $this->get_post();
        if (is_wp_error($post)) {
            $this->send_error_response($post);
            return;
        }
        $feature_permission = $this->check_content_update_permissions($post);
        if (is_wp_error($feature_permission)) {
            $this->send_error_response($feature_permission);
            return;
        }

        $is_pro = class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
        $enhancer_source = isset($_POST['enhancer_source']) ? sanitize_key(wp_unslash($_POST['enhancer_source'])) : '';
        $bypass_pro_checks = ($enhancer_source === 'post_enhancer');

        if (!$is_pro && !$bypass_pro_checks) {
            $this->send_error_response(new WP_Error('pro_required', __('Updating URL slugs is available on Pro.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }

        // Check if action type is specified
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
        $action_type = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';
        if ($action_type !== 'update_slug') {
            $this->send_error_response(new WP_Error('invalid_action', __('Invalid action type.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        // Update SEO slug
        if (class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')) {
            $result = \WPAICG\SEO\AIPKit_SEO_Helper::update_post_slug_for_seo($post->ID);
            if ($result) {
                wp_send_json_success([
                    'message' => 'URL updated successfully',
                    'post_id' => $post->ID
                ]);
            } else {
                $this->send_error_response(new WP_Error('slug_update_failed', __('Failed to update URL.', 'gpt3-ai-content-generator'), ['status' => 500]));
            }
        } else {
            $this->send_error_response(new WP_Error('seo_helper_missing', __('SEO helper class not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
        }
    }
}
