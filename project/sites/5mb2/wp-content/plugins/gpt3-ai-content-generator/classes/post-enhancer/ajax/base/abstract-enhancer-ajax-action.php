<?php


namespace WPAICG\PostEnhancer\Ajax\Base;

use WP_Error;
use WPAICG\AIPKit_Role_Manager;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public const CONTENT_WRITER_MODULE = 'content-writer';
    public const BULK_ASSISTANT_MODULE = 'bulk_assistant';
    public const ROW_ASSISTANT_MODULE = 'row_assistant';
    public const CLASSIC_EDITOR_ASSISTANT_MODULE = 'classic_editor_assistant';
    public const BLOCK_EDITOR_ASSISTANT_MODULE = 'block_editor_assistant';

    abstract public function handle(): void;

    /**
     * @param string|mixed[] $module_slugs
     * @return bool|\WP_Error
     */
    protected function check_permissions(string $nonce_action, $module_slugs = [])
    {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_key($_POST['_ajax_nonce']), $nonce_action)) {
            return new WP_Error('nonce_failure', __('Security check failed.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }
        $module_slugs = array_filter(array_map('sanitize_key', (array) $module_slugs));
        if (!empty($module_slugs) && !AIPKit_Role_Manager::user_can_access_any_module($module_slugs)) {
            return new WP_Error('permission_denied_module', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }
        return true;
    }

    /**
     * @return bool|\WP_Error
     */
    protected function check_row_assistant_permissions(\WP_Post $post)
    {
        return $this->check_post_utility_permission($post, self::ROW_ASSISTANT_MODULE);
    }

    /**
     * @return bool|\WP_Error
     */
    protected function check_content_update_permissions(\WP_Post $post)
    {
        $source = $this->get_enhancer_source();
        if ($source === 'content_writer') {
            return AIPKit_Role_Manager::user_can_access_module(self::CONTENT_WRITER_MODULE)
                ? true
                : new WP_Error('permission_denied_module', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }

        if ($source === 'post_enhancer') {
            return $this->check_post_utility_permission($post, self::BULK_ASSISTANT_MODULE);
        }

        return new WP_Error('invalid_enhancer_source', __('Invalid assistant request source.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    /**
     * @return bool|\WP_Error
     */
    protected function check_editor_context_permissions()
    {
        $context = $this->get_editor_context();
        if ($context === 'classic') {
            $module_slug = self::CLASSIC_EDITOR_ASSISTANT_MODULE;
        } elseif ($context === 'block') {
            $module_slug = self::BLOCK_EDITOR_ASSISTANT_MODULE;
        } else {
            return new WP_Error('invalid_editor_context', __('Invalid editor context.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }

        return AIPKit_Role_Manager::user_can_access_module($module_slug)
            ? true
            : new WP_Error('permission_denied_module', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    /**
     * @return bool|\WP_Error
     */
    private function check_post_utility_permission(\WP_Post $post, string $non_product_module_slug)
    {
        return AIPKit_Role_Manager::user_can_access_module($non_product_module_slug)
            ? true
            : new WP_Error('permission_denied_module', __('You do not have permission to use this feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }

    private function get_enhancer_source(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_permissions.
        return isset($_POST['enhancer_source']) ? sanitize_key(wp_unslash($_POST['enhancer_source'])) : '';
    }

    private function get_editor_context(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_permissions.
        return isset($_POST['editor_context']) ? sanitize_key(wp_unslash($_POST['editor_context'])) : '';
    }

    /**
     * @return \WP_Post|\WP_Error
     */
    protected function get_post()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce is checked in check_permissions.
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            return new WP_Error('missing_post_id', __('Missing post ID.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found.', 'gpt3-ai-content-generator'), ['status' => 404]);
        }
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('permission_denied_post', __('You do not have permission to edit this post.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }
        return $post;
    }

    protected function send_error_response(WP_Error $error): void
    {
        $status = is_array($error->get_error_data()) && isset($error->get_error_data()['status']) ? $error->get_error_data()['status'] : 400;
        wp_send_json_error(['message' => $error->get_error_message()], $status);
    }
}
