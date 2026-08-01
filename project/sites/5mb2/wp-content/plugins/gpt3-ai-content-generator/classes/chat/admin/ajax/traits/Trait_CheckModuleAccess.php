<?php

namespace WPAICG\Chat\Admin\Ajax\Traits;

use WP_Error;
use WPAICG\AIPKit_Role_Manager; // Import Role Manager

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

trait Trait_CheckModuleAccess {
    /**
     * Helper to check nonce and module-specific access via Role Manager.
     * Use this for actions within a module that don't require full admin rights.
     *
     * @param string $module_slug The slug of the module to check access for.
     * @param string $nonce_action The nonce action string. Defaults to 'aipkit_nonce'.
     * @return bool|WP_Error True if permissions are valid, WP_Error otherwise.
     */
    protected function check_module_access_permissions(string $module_slug, string $nonce_action = 'aipkit_nonce') {
        // 1. Check nonce first
        if (!check_ajax_referer($nonce_action, '_ajax_nonce', false)) {
            return new WP_Error('nonce_failure', __('Security check failed (nonce).', 'gpt3-ai-content-generator'), ['status' => 403]);
        }

        // 2. Check if the user can access the specified module
        if (!AIPKit_Role_Manager::user_can_access_module($module_slug)) {
             return new WP_Error('permission_denied', __('You do not have permission to perform this action.', 'gpt3-ai-content-generator'), ['status' => 403]);
        }

        // 3. If both checks pass
        return true;
    }
}