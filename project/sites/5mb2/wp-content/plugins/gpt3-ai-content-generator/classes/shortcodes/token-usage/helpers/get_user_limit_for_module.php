<?php


namespace WPAICG\Shortcodes\TokenUsage\Helpers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic to determine the token limit for a logged-in user for a specific module.
 *
 * @param int $user_id The user ID.
 * @param array $module_token_settings The token management settings for the module.
 * @return int|null The token limit (int > 0), 0 if disabled, or null if unlimited.
 */
function get_user_limit_for_module_logic(int $user_id, array $module_token_settings): ?int
{
    $limit_mode = $module_token_settings['token_limit_mode'] ?? 'general';
    $limit = null;

    if ($limit_mode === 'general') {
        $limit = $module_token_settings['token_user_limit'] ?? null;
    } else { // role-based
        $user_data = get_userdata($user_id);
        $user_roles = $user_data ? (array) $user_data->roles : [];
        $role_limits_raw = $module_token_settings['token_role_limits'] ?? [];
        $role_limits = is_string($role_limits_raw) ? json_decode($role_limits_raw, true) : (is_array($role_limits_raw) ? $role_limits_raw : []);
        if (!is_array($role_limits)) {
            $role_limits = [];
        }

        if (empty($user_roles) || empty($role_limits)) {
            $limit = null;
        } else {
            $highest_limit = -1; // -1 indicates no specific limit found for user's roles
            foreach ($user_roles as $role) {
                if (isset($role_limits[$role])) {
                    $role_limit_value_raw = $role_limits[$role];
                    if ($role_limit_value_raw === null || $role_limit_value_raw === '') {
                        $highest_limit = null; // Explicitly unlimited for this role, overrides others
                        break;
                    }
                    if ($role_limit_value_raw === '0' || $role_limit_value_raw === 0) {
                        $highest_limit = max($highest_limit, 0);
                    } elseif (ctype_digit((string)$role_limit_value_raw)) {
                        $highest_limit = max($highest_limit, (int)$role_limit_value_raw);
                    }
                }
            }
            $limit = ($highest_limit === -1) ? null : $highest_limit;
        }
    }

    if ($limit === '') {
        $limit = null;
    } elseif (is_numeric($limit)) {
        $limit = (int) $limit;
    }

    return $limit;
}
