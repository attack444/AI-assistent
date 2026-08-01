<?php

/**
 * AIPKit Role Manager Module - Admin View
 *
 * Allows administrators to assign module access permissions to different user roles.
 * @since NEXT_VERSION
 */

use WPAICG\AIPKit_Role_Manager; // Use the Role Manager class

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Fetch necessary data using the Role Manager class
$permission_groups = AIPKit_Role_Manager::get_permission_groups();
$roles       = AIPKit_Role_Manager::get_editable_roles();
$permissions = AIPKit_Role_Manager::get_role_permissions(); // Get saved permissions ['module_slug' => ['role1', 'role2']]

// Ensure roles are sorted logically (e.g., Admin first) - optional but nice UX
$role_order = ['administrator', 'editor', 'author', 'contributor', 'subscriber']; // Define desired order
$sorted_roles = [];
foreach ($role_order as $role_key) {
    if (isset($roles[$role_key])) {
        $sorted_roles[$role_key] = $roles[$role_key];
        unset($roles[$role_key]); // Remove from original array
    }
}
// Add any remaining roles (custom roles) alphabetically
ksort($roles);
$sorted_roles = array_merge($sorted_roles, $roles);

// Prepare nonce for saving
$nonce = wp_create_nonce('aipkit_role_manager_nonce');

?>
<div class="aipkit_container aipkit_role_manager_container" id="aipkit_role_manager_container">
    <div class="aipkit_container-header">
        <div class="aipkit_container-header-left">
            <div class="aipkit_role_manager_header_copy">
                <div class="aipkit_role_manager_header_title_row">
                    <div class="aipkit_container-title"><?php esc_html_e('Role Manager', 'gpt3-ai-content-generator'); ?></div>
                    <div id="aipkit_role_manager_messages" class="aipkit_settings_messages aipkit_role_manager_header_status">
                    </div>
                </div>
                <p class="aipkit_role_manager_header_hint"><?php esc_html_e('Control which WordPress roles can access each AI Puffer workspace and tool.', 'gpt3-ai-content-generator'); ?></p>
            </div>
        </div>
    </div>
    <div class="aipkit_container-body">
        <form id="aipkit_role_manager_form">
            <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($nonce); ?>">
            <div class="aipkit_role_manager_groups">
                <?php foreach ($permission_groups as $group_key => $group): ?>
                    <section class="aipkit_role_manager_group" aria-labelledby="aipkit_role_group_<?php echo esc_attr($group_key); ?>">
                        <div class="aipkit_role_manager_group_header">
                            <div>
                                <h3 class="aipkit_role_manager_group_title" id="aipkit_role_group_<?php echo esc_attr($group_key); ?>"><?php echo esc_html($group['label']); ?></h3>
                                <?php if (!empty($group['description'])): ?>
                                    <p class="aipkit_role_manager_group_desc"><?php echo esc_html($group['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="aipkit_data-table aipkit_role_manager_table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Access', 'gpt3-ai-content-generator'); ?></th>
                                    <?php foreach ($sorted_roles as $role_slug => $role_name): ?>
                                        <th class="aipkit_role_header"><?php echo esc_html(translate_user_role($role_name)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['modules'] as $module_slug => $module): ?>
                                    <tr>
                                        <td>
                                            <div class="aipkit_role_manager_module_name"><?php echo esc_html($module['label']); ?></div>
                                            <?php if (!empty($module['description'])): ?>
                                                <div class="aipkit_role_manager_module_desc"><?php echo esc_html($module['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($sorted_roles as $role_slug => $role_name):
                                            $allowed_roles_for_module = isset($permissions[$module_slug]) && is_array($permissions[$module_slug]) ? $permissions[$module_slug] : ['administrator'];
                                            $is_checked = in_array($role_slug, $allowed_roles_for_module, true);
                                            $is_disabled = ($role_slug === 'administrator');
                                            $checkbox_id = 'aipkit_perm_' . esc_attr($module_slug) . '_' . esc_attr($role_slug);
                                            $checkbox_name = 'permissions[' . esc_attr($module_slug) . '][' . esc_attr($role_slug) . ']';
                                        ?>
                                            <td class="aipkit_role_cell">
                                                <input
                                                    type="checkbox"
                                                    id="<?php echo esc_attr( $checkbox_id ); ?>"
                                                    name="<?php echo esc_attr( $checkbox_name ); ?>"
                                                    value="1"
                                                    <?php checked($is_checked); ?>
                                                    <?php disabled($is_disabled); ?>
                                                    <?php if ($is_disabled) : ?>
                                                    title="<?php esc_attr_e('Administrators always have access.', 'gpt3-ai-content-generator'); ?>"
                                                    <?php endif; ?>
                                                />
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>
