<?php
/**
 * Partial: Native App Connections Settings Section
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$is_pro_plan = class_exists('\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
$app_connections_class = '\WPAICG\\Lib\\Integrations\\Apps\\AIPKit_App_Connections';

if (!$is_pro_plan || !class_exists($app_connections_class)) {
    return;
}

$app_connections = $app_connections_class::get_connections();
$supported_apps = $app_connections_class::get_supported_app_options();
$supported_auth_types = $app_connections_class::get_supported_auth_options();
$supported_auth_types_by_app = method_exists($app_connections_class, 'get_supported_auth_types_by_app')
    ? $app_connections_class::get_supported_auth_types_by_app()
    : [];
$diagnostics_class = '\WPAICG\\Lib\\Integrations\\Apps\\AIPKit_App_Connection_Diagnostics';
$app_field_definitions = method_exists($app_connections_class, 'get_app_field_definitions')
    ? $app_connections_class::get_app_field_definitions()
    : [];
$default_auth_types_by_app = method_exists($app_connections_class, 'get_default_auth_types_by_app')
    ? $app_connections_class::get_default_auth_types_by_app()
    : [];
$auth_type_app_map = [];
foreach ($supported_auth_types_by_app as $app_slug => $auth_types) {
    if (!is_array($auth_types)) {
        continue;
    }

    foreach ($auth_types as $auth_type) {
        $auth_key = sanitize_key((string) $auth_type);
        if ($auth_key === '') {
            continue;
        }

        if (!isset($auth_type_app_map[$auth_key])) {
            $auth_type_app_map[$auth_key] = [];
        }

        $auth_type_app_map[$auth_key][] = sanitize_key((string) $app_slug);
    }
}
$get_connection_field_value = static function (array $connection, string $group, string $key) use ($app_connections_class): string {
    if (method_exists($app_connections_class, 'get_connection_field_value')) {
        return (string) $app_connections_class::get_connection_field_value($connection, $group, $key);
    }

    $group_value = $connection[$group] ?? [];
    if (!is_array($group_value)) {
        return '';
    }

    return sanitize_text_field((string) ($group_value[$key] ?? ''));
};
$format_connection_secret_mask = static function (string $credential): string {
    if ($credential === '') {
        return '';
    }

    $fixed_mask = '••••••••••••';
    $credential_length = strlen($credential);
    if ($credential_length < 9) {
        return $fixed_mask;
    }

    $prefix_length = $credential_length >= 16 ? 8 : 3;
    $suffix_length = $credential_length >= 16 ? 4 : 3;

    return substr($credential, 0, $prefix_length) . $fixed_mask . substr($credential, -$suffix_length);
};

$render_app_connection = static function ($index, array $connection = []) use ($supported_apps, $supported_auth_types, $supported_auth_types_by_app, $auth_type_app_map, $diagnostics_class, $app_field_definitions, $default_auth_types_by_app, $get_connection_field_value, $format_connection_secret_mask): void {
    $connection_index = (string) $index;
    $connection_id = sanitize_text_field((string) ($connection['id'] ?? ''));
    $connection_name = (string) ($connection['name'] ?? '');
    $connection_app_slug = sanitize_key((string) ($connection['app_slug'] ?? 'slack'));
    if (!isset($supported_apps[$connection_app_slug])) {
        $connection_app_slug = 'slack';
    }

    $connection_auth_type = sanitize_key((string) ($connection['auth_type'] ?? 'webhook'));
    $allowed_auth_types = $supported_auth_types_by_app[$connection_app_slug] ?? array_keys($supported_auth_types);
    if (!isset($supported_auth_types[$connection_auth_type]) || !in_array($connection_auth_type, $allowed_auth_types, true)) {
        $connection_auth_type = sanitize_key((string) ($default_auth_types_by_app[$connection_app_slug] ?? ($allowed_auth_types[0] ?? 'webhook')));
    }

    $connection_status = sanitize_key((string) ($connection['status'] ?? 'draft'));
    if (!in_array($connection_status, ['draft', 'active', 'inactive', 'error', 'reauth_required'], true)) {
        $connection_status = 'draft';
    }

    $connection_enabled = !array_key_exists('is_enabled', $connection) || !empty($connection['is_enabled']);
    $connection_ui_state = class_exists($diagnostics_class) && method_exists($diagnostics_class, 'get_connection_ui_state')
        ? $diagnostics_class::get_connection_ui_state($connection)
        : [
            'status_key' => $connection_status,
            'status_label' => ucwords(str_replace('_', ' ', $connection_status)),
            'summary' => __('Connection details saved.', 'gpt3-ai-content-generator'),
            'is_testable' => false,
            'can_test' => false,
            'button_label' => __('Test connection', 'gpt3-ai-content-generator'),
        ];
    $status_key = sanitize_key((string) ($connection_ui_state['status_key'] ?? $connection_status));
    $status_label = sanitize_text_field((string) ($connection_ui_state['status_label'] ?? ucwords(str_replace('_', ' ', $connection_status))));
    $status_summary = sanitize_text_field((string) ($connection_ui_state['summary'] ?? ''));
    $is_testable = !empty($connection_ui_state['is_testable']);
    $can_test = !empty($connection_ui_state['can_test']);
    $test_button_label = sanitize_text_field((string) ($connection_ui_state['button_label'] ?? __('Test connection', 'gpt3-ai-content-generator')));
    $connection_summary_parts = [];
    if (isset($supported_apps[$connection_app_slug])) {
        $connection_summary_parts[] = (string) $supported_apps[$connection_app_slug];
    }
    if (isset($supported_auth_types[$connection_auth_type])) {
        $connection_summary_parts[] = (string) $supported_auth_types[$connection_auth_type];
    }
    $connection_summary = implode(' | ', array_filter($connection_summary_parts, static function ($value): bool {
        return is_string($value) && $value !== '';
    }));
    ?>
    <article class="aipkit_settings_app_connection" data-aipkit-app-connection data-connection-index="<?php echo esc_attr($connection_index); ?>">
        <input
            type="hidden"
            name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][id]"
            value="<?php echo esc_attr($connection_id); ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-app-connection-field="id"
        />
        <input
            type="hidden"
            name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][status]"
            value="<?php echo esc_attr($status_key); ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-app-connection-field="status"
        />

        <div class="aipkit_settings_app_connection_header">
            <div class="aipkit_settings_app_connection_heading">
                <strong class="aipkit_settings_app_connection_title" data-aipkit-app-connection-title>
                    <?php echo esc_html($connection_name !== '' ? $connection_name : __('Untitled connection', 'gpt3-ai-content-generator')); ?>
                </strong>
                <span class="aipkit_settings_app_connection_index" data-aipkit-app-connection-number></span>
                <span
                    class="aipkit_settings_app_connection_status aipkit_settings_app_connection_status--<?php echo esc_attr($status_key); ?>"
                    data-aipkit-app-connection-status-badge
                >
                    <?php echo esc_html($status_label); ?>
                </span>
            </div>
            <div class="aipkit_settings_app_connection_actions">
                <button
                    type="button"
                    class="button aipkit_btn aipkit_settings_card_toggle"
                    data-aipkit-app-connection-edit-toggle
                    aria-expanded="false"
                    aria-label="<?php esc_attr_e('Edit connection', 'gpt3-ai-content-generator'); ?>"
                >
                    <?php esc_html_e('Edit', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>
        </div>

        <p class="aipkit_settings_app_connection_summary" data-aipkit-app-connection-summary>
            <?php echo esc_html($connection_summary !== '' ? $connection_summary : __('Select an app and auth type.', 'gpt3-ai-content-generator')); ?>
        </p>

        <div class="aipkit_settings_app_connection_feedback">
            <p class="aipkit_settings_app_connection_feedback_text" data-aipkit-app-connection-feedback><?php echo esc_html($status_summary); ?></p>
        </div>

        <div class="aipkit_settings_app_connection_body" data-aipkit-app-connection-body hidden>
            <div class="aipkit_settings_app_connection_actions aipkit_settings_app_connection_actions--body">
                <label class="aipkit_settings_app_connection_toggle">
                    <span><?php esc_html_e('Enabled', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_switch">
                        <input
                            type="checkbox"
                            name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][is_enabled]"
                            value="1"
                            class="aipkit_autosave_trigger"
                            data-aipkit-app-connection-field="is_enabled"
                            <?php checked($connection_enabled); ?>
                        />
                        <span class="aipkit_switch_slider"></span>
                    </span>
                </label>
                <button type="button" class="button aipkit_btn aipkit_btn-danger aipkit_btn-danger--quiet" data-aipkit-remove-app-connection>
                    <?php esc_html_e('Remove connection', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>

            <div class="aipkit_settings_app_connection_fields">
                <label class="aipkit_settings_app_connection_field">
                    <span class="aipkit_settings_app_connection_field_label"><?php esc_html_e('Name', 'gpt3-ai-content-generator'); ?></span>
                    <input
                        type="text"
                        name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][name]"
                        value="<?php echo esc_attr($connection_name); ?>"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-app-connection-field="name"
                        placeholder="<?php esc_attr_e('Sales Slack', 'gpt3-ai-content-generator'); ?>"
                    />
                </label>
                <label class="aipkit_settings_app_connection_field">
                    <span class="aipkit_settings_app_connection_field_label"><?php esc_html_e('App', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][app_slug]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-app-connection-field="app_slug"
                        data-aipkit-app-connection-app-select
                    >
                        <?php foreach ($supported_apps as $app_slug => $app_label) : ?>
                            <option
                                value="<?php echo esc_attr($app_slug); ?>"
                                data-aipkit-default-auth-type="<?php echo esc_attr($default_auth_types_by_app[$app_slug] ?? 'webhook'); ?>"
                                <?php selected($connection_app_slug, $app_slug); ?>
                            >
                                <?php echo esc_html($app_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="aipkit_settings_app_connection_field">
                    <span class="aipkit_settings_app_connection_field_label"><?php esc_html_e('Auth type', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[connections][<?php echo esc_attr($connection_index); ?>][auth_type]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-app-connection-field="auth_type"
                        data-aipkit-app-connection-auth-select
                    >
                        <?php foreach ($supported_auth_types as $auth_type => $auth_label) : ?>
                            <?php
                            $auth_app_slugs = $auth_type_app_map[$auth_type] ?? [];
                            $auth_visible_for_app = in_array($connection_app_slug, $auth_app_slugs, true);
                            ?>
                            <option
                                value="<?php echo esc_attr($auth_type); ?>"
                                data-app-slugs="<?php echo esc_attr(implode(',', array_unique($auth_app_slugs))); ?>"
                                <?php if (!$auth_visible_for_app) : ?>hidden disabled<?php endif; ?>
                                <?php selected($connection_auth_type, $auth_type); ?>
                            >
                                <?php echo esc_html($auth_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <?php if (!empty($app_field_definitions)) : ?>
                <div class="aipkit_settings_app_connection_app_forms" data-aipkit-app-connection-forms>
                    <?php foreach ($app_field_definitions as $app_slug => $field_definitions) : ?>
                        <?php if (empty($field_definitions) || !is_array($field_definitions)) {
                            continue;
                        } ?>
                        <div
                            class="aipkit_settings_app_connection_app_form"
                            data-aipkit-app-connection-app-form="<?php echo esc_attr($app_slug); ?>"
                            <?php if ($connection_app_slug !== $app_slug) : ?>hidden<?php endif; ?>
                        >
                            <div class="aipkit_settings_app_connection_app_form_header">
                                <strong>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: app name */
                                            __('%s settings', 'gpt3-ai-content-generator'),
                                            $supported_apps[$app_slug] ?? ucfirst($app_slug)
                                        )
                                    );
                                    ?>
                                </strong>
                                <span><?php esc_html_e('Connection details', 'gpt3-ai-content-generator'); ?></span>
                            </div>
                            <div class="aipkit_settings_app_connection_app_form_grid">
                                <?php foreach ($field_definitions as $field_definition) : ?>
                                    <?php
                                    $field_group = sanitize_key((string) ($field_definition['group'] ?? ''));
                                    $field_key = sanitize_key((string) ($field_definition['key'] ?? ''));
                                    if ($field_group === '' || $field_key === '') {
                                        continue;
                                    }

                                    $field_label = (string) ($field_definition['label'] ?? $field_key);
                                    $field_type = sanitize_key((string) ($field_definition['type'] ?? 'text'));
                                    if (!in_array($field_type, ['text', 'password', 'url'], true)) {
                                        $field_type = 'text';
                                    }

                                    $field_placeholder = (string) ($field_definition['placeholder'] ?? '');
                                    $field_value = $get_connection_field_value($connection, $field_group, $field_key);
                                    $field_path = $field_group . '.' . $field_key;
                                    $field_id = 'aipkit_app_connection_' . sanitize_html_class($connection_index) . '_' . sanitize_html_class($app_slug) . '_' . sanitize_html_class($field_group) . '_' . sanitize_html_class($field_key);
                                    $is_secret_field = $field_type === 'password';
                                    $field_mask = $is_secret_field ? $format_connection_secret_mask($field_value) : '';
                                    $field_auth_types = isset($field_definition['auth_types']) && is_array($field_definition['auth_types'])
                                        ? array_values(array_filter(array_map('sanitize_key', $field_definition['auth_types'])))
                                        : [];
                                    $field_visible_for_auth = empty($field_auth_types) || in_array($connection_auth_type, $field_auth_types, true);
                                    ?>
                                    <div
                                        class="aipkit_settings_app_connection_field"
                                        data-aipkit-app-connection-field-wrapper
                                        data-aipkit-auth-types="<?php echo esc_attr(implode(',', $field_auth_types)); ?>"
                                        <?php if (!$field_visible_for_auth) : ?>hidden<?php endif; ?>
                                    >
                                        <label class="aipkit_settings_app_connection_field_label" for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field_label); ?></label>
                                        <div class="<?php echo $is_secret_field ? 'aipkit_settings_app_connection_secret_row' : ''; ?>">
                                            <div class="<?php echo $is_secret_field ? 'aipkit_settings_provider_credential_wrap' : ''; ?>">
                                                <input
                                                    type="<?php echo esc_attr($field_type); ?>"
                                                    id="<?php echo esc_attr($field_id); ?>"
                                                    name=""
                                                    value="<?php echo $is_secret_field ? '' : esc_attr($field_value); ?>"
                                                    class="aipkit_form-input aipkit_autosave_trigger<?php echo $is_secret_field ? ' aipkit_settings_provider_credential is-secret' : ''; ?><?php echo $field_mask !== '' ? ' is-visually-masked' : ''; ?>"
                                                    data-aipkit-app-connection-field="<?php echo esc_attr($field_path); ?>"
                                                    <?php if ($is_secret_field) : ?>data-aipkit-app-connection-secret<?php endif; ?>
                                                    <?php if ($is_secret_field) : ?>data-aipkit-app-connection-id="<?php echo esc_attr($connection_id); ?>" data-aipkit-has-credential="<?php echo $field_value !== '' ? 'true' : 'false'; ?>"<?php endif; ?>
                                                    placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                                    <?php if (!$field_visible_for_auth) : ?>disabled<?php endif; ?>
                                                    <?php if ($is_secret_field) : ?>autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other"<?php endif; ?>
                                                    <?php if ($field_mask !== '') : ?>readonly<?php endif; ?>
                                                />
                                                <?php if ($is_secret_field) : ?>
                                                    <span
                                                        class="aipkit_settings_provider_credential_mask"
                                                        data-aipkit-app-connection-secret-mask
                                                        aria-hidden="true"
                                                        <?php if ($field_mask === '') : ?>hidden<?php endif; ?>
                                                    ><?php echo esc_html($field_mask); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($is_secret_field) : ?>
                                                <button
                                                    type="button"
                                                    class="aipkit_settings_icon_button"
                                                    data-aipkit-app-connection-secret-reveal
                                                    data-reveal-label="<?php esc_attr_e('Reveal secret', 'gpt3-ai-content-generator'); ?>"
                                                    data-hide-label="<?php esc_attr_e('Hide secret', 'gpt3-ai-content-generator'); ?>"
                                                    aria-label="<?php esc_attr_e('Reveal secret', 'gpt3-ai-content-generator'); ?>"
                                                    title="<?php esc_attr_e('Reveal secret', 'gpt3-ai-content-generator'); ?>"
                                                >
                                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div
                                class="aipkit_settings_app_connection_test_row"
                                <?php if ($connection_app_slug !== $app_slug || !$is_testable) : ?>hidden<?php endif; ?>
                            >
                                <button
                                    type="button"
                                    class="button aipkit_btn"
                                    data-aipkit-test-app-connection
                                    data-app-slug="<?php echo esc_attr($app_slug); ?>"
                                    data-connection-id="<?php echo esc_attr($connection_id); ?>"
                                    <?php if ($connection_app_slug !== $app_slug || !$is_testable) : ?>hidden<?php endif; ?>
                                    <?php disabled(!$can_test); ?>
                                >
                                    <span class="aipkit_btn-text" data-aipkit-test-app-connection-label><?php echo esc_html($test_button_label); ?></span>
                                    <span class="aipkit_spinner"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>

<section id="aipkit_settings_app_connections_section">
    <div class="aipkit_form-group aipkit_settings_simple_row aipkit_settings_simple_row--app-connections" id="aipkit_settings_app_connections_row">
        <div class="aipkit_form-label">
            <?php esc_html_e('App connections', 'gpt3-ai-content-generator'); ?>
            <span class="aipkit_form-label-helper"><?php esc_html_e('Create reusable app connections.', 'gpt3-ai-content-generator'); ?></span>
        </div>
        <div class="aipkit_settings_app_connections_main">
            <div class="aipkit_settings_app_connections_toolbar">
                <button type="button" class="button button-secondary aipkit_btn" id="aipkit_add_app_connection_btn">
                    <?php esc_html_e('Add connection', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>
            <div class="aipkit_settings_app_connection_list" data-aipkit-app-connection-list>
                <?php if (!empty($app_connections)) : ?>
                    <?php foreach ($app_connections as $connection_index => $connection) : ?>
                        <?php $render_app_connection($connection_index, is_array($connection) ? $connection : []); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <template id="aipkit_app_connection_template">
        <?php $render_app_connection('__INDEX__'); ?>
    </template>
</section>
