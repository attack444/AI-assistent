<?php
/**
 * Partial: Native App Recipes List Section
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$is_pro_plan = class_exists('\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
$recipes_class = '\WPAICG\\Lib\\Integrations\\Recipes\\AIPKit_Stored_Recipes';
$connections_class = '\WPAICG\\Lib\\Integrations\\Apps\\AIPKit_App_Connections';

if (
    !$is_pro_plan
    || !class_exists($recipes_class)
    || !class_exists($connections_class)
) {
    return;
}

$stored_recipes = $recipes_class::get_recipes();
$recipe_event_options = $recipes_class::get_event_options();
$recipe_action_options = $recipes_class::get_action_options();
$recipe_action_options_by_app = $recipes_class::get_action_options_by_app();
$recipe_template_definitions = $recipes_class::get_template_definitions();
$recipe_mapping_definitions = $recipes_class::get_mapping_ui_definitions();
$recipe_chatbot_scope_options = $recipes_class::get_chatbot_scope_options();
$recipe_ai_form_scope_options = method_exists($recipes_class, 'get_ai_form_scope_options')
    ? $recipes_class::get_ai_form_scope_options()
    : [];
$recipe_connections = $connections_class::get_connections();
$recipe_app_options = $connections_class::get_supported_app_options();
$recipe_validator_class = '\WPAICG\\Lib\\Integrations\\Recipes\\AIPKit_Recipe_Validator';
$recipe_validation_definitions = class_exists($recipe_validator_class) && method_exists($recipe_validator_class, 'get_ui_validation_definitions')
    ? $recipe_validator_class::get_ui_validation_definitions()
    : [];

$recipe_connection_options = [];
$recipe_connection_records = [];
foreach ($recipe_connections as $connection) {
    if (!is_array($connection)) {
        continue;
    }

    $connection_id = sanitize_text_field((string) ($connection['id'] ?? ''));
    if ($connection_id === '') {
        continue;
    }

    $connection_name = sanitize_text_field((string) ($connection['name'] ?? ''));
    $connection_app_slug = sanitize_key((string) ($connection['app_slug'] ?? ''));
    $connection_status = sanitize_key((string) ($connection['status'] ?? 'draft'));
    $connection_enabled = !array_key_exists('is_enabled', $connection) || !empty($connection['is_enabled']);
    $recipe_connection_options[$connection_id] = [
        'label' => $connection_name !== '' ? $connection_name : __('Untitled connection', 'gpt3-ai-content-generator'),
        'app_slug' => $connection_app_slug,
        'status' => $connection_status,
        'is_enabled' => $connection_enabled,
    ];
    $recipe_connection_records[$connection_id] = $connection;
}

$recipe_template_groups = [];
foreach ($recipe_template_definitions as $template_slug => $template_definition) {
    $template_app_slug = sanitize_key((string) ($template_definition['app_slug'] ?? ''));
    if ($template_app_slug === '') {
        $template_app_slug = 'other';
    }

    $group_label = $recipe_app_options[$template_app_slug] ?? __('Other', 'gpt3-ai-content-generator');
    if (!isset($recipe_template_groups[$group_label])) {
        $recipe_template_groups[$group_label] = [];
    }

    $recipe_template_groups[$group_label][$template_slug] = $template_definition;
}

$recipe_mapping_source_option_groups = [];
$source_groups_by_event = $recipe_mapping_definitions['source_groups_by_event'] ?? [];
if (is_array($source_groups_by_event) && !empty($source_groups_by_event)) {
    foreach ($source_groups_by_event as $event_name => $source_groups) {
        if (!is_array($source_groups)) {
            continue;
        }

        foreach ($source_groups as $group_definition) {
            if (!is_array($group_definition)) {
                continue;
            }

            $group_label = sanitize_text_field((string) ($group_definition['label'] ?? __('Other Sources', 'gpt3-ai-content-generator')));
            $group_key = $group_label !== '' ? $group_label : __('Other Sources', 'gpt3-ai-content-generator');
            $group_options = $group_definition['options'] ?? [];
            if (!is_array($group_options) || empty($group_options)) {
                continue;
            }

            if (!isset($recipe_mapping_source_option_groups[$group_key])) {
                $recipe_mapping_source_option_groups[$group_key] = [
                    'label' => $group_label,
                    'options' => [],
                ];
            }

            foreach ($group_options as $source_path => $source_label) {
                $source_key = (string) $source_path;
                if (!isset($recipe_mapping_source_option_groups[$group_key]['options'][$source_key])) {
                    $recipe_mapping_source_option_groups[$group_key]['options'][$source_key] = [
                        'label' => (string) $source_label,
                        'event_names' => [],
                    ];
                }

                $recipe_mapping_source_option_groups[$group_key]['options'][$source_key]['event_names'][] = (string) $event_name;
            }
        }
    }
}

if (empty($recipe_mapping_source_option_groups)) {
    $recipe_mapping_source_option_groups[__('Core sources', 'gpt3-ai-content-generator')] = [
        'label' => __('Core sources', 'gpt3-ai-content-generator'),
        'options' => [],
    ];

    foreach (($recipe_mapping_definitions['source_options_by_event'] ?? []) as $event_name => $source_options) {
        if (!is_array($source_options)) {
            continue;
        }

        foreach ($source_options as $source_path => $source_label) {
            $source_key = (string) $source_path;
            if (!isset($recipe_mapping_source_option_groups[__('Core sources', 'gpt3-ai-content-generator')]['options'][$source_key])) {
                $recipe_mapping_source_option_groups[__('Core sources', 'gpt3-ai-content-generator')]['options'][$source_key] = [
                    'label' => (string) $source_label,
                    'event_names' => [],
                ];
            }

            $recipe_mapping_source_option_groups[__('Core sources', 'gpt3-ai-content-generator')]['options'][$source_key]['event_names'][] = (string) $event_name;
        }
    }
}

$recipe_mapping_target_options_flat = [];
foreach (($recipe_mapping_definitions['target_options_by_action'] ?? []) as $action_slug => $target_options) {
    if (!is_array($target_options)) {
        continue;
    }

    foreach ($target_options as $target_field => $target_label) {
        $target_key = (string) $target_field;
        if (!isset($recipe_mapping_target_options_flat[$target_key])) {
            $recipe_mapping_target_options_flat[$target_key] = [
                'label' => (string) $target_label,
                'action_slugs' => [],
            ];
        }

        $recipe_mapping_target_options_flat[$target_key]['action_slugs'][] = (string) $action_slug;
    }
}

$render_recipe_mapping_row = static function (
    string $recipe_index,
    string $mapping_index,
    array $mapping_field = []
) use (
    $recipe_mapping_source_option_groups,
    $recipe_mapping_target_options_flat,
    $recipe_mapping_definitions
): void {
    $target_field = sanitize_key((string) ($mapping_field['target_field'] ?? ''));
    $source_path = sanitize_text_field((string) ($mapping_field['source_path'] ?? ''));
    $transform = sanitize_key((string) ($mapping_field['transform'] ?? ''));
    $fallback_value = is_scalar($mapping_field['fallback_value'] ?? '')
        ? (string) ($mapping_field['fallback_value'] ?? '')
        : '';
    $is_required = !empty($mapping_field['is_required']);
    ?>
    <div class="aipkit_settings_recipe_mapping_row" data-aipkit-recipe-mapping-row>
        <label class="aipkit_settings_recipe_mapping_field">
            <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Target field', 'gpt3-ai-content-generator'); ?></span>
            <select
                name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][mapping][fields][<?php echo esc_attr($mapping_index); ?>][target_field]"
                class="aipkit_form-input aipkit_autosave_trigger"
                data-aipkit-recipe-mapping-field="target_field"
                data-aipkit-recipe-mapping-target-select
            >
                <option value=""><?php esc_html_e('Select a target', 'gpt3-ai-content-generator'); ?></option>
                <?php foreach ($recipe_mapping_target_options_flat as $target_key => $target_data) : ?>
                    <option
                        value="<?php echo esc_attr($target_key); ?>"
                        data-action-slugs="<?php echo esc_attr(implode(',', array_unique($target_data['action_slugs']))); ?>"
                        <?php selected($target_field, $target_key); ?>
                    >
                        <?php echo esc_html((string) ($target_data['label'] ?? $target_key)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="aipkit_settings_recipe_mapping_field">
            <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Source field', 'gpt3-ai-content-generator'); ?></span>
            <select
                name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][mapping][fields][<?php echo esc_attr($mapping_index); ?>][source_path]"
                class="aipkit_form-input aipkit_autosave_trigger"
                data-aipkit-recipe-mapping-field="source_path"
                data-aipkit-recipe-mapping-source-select
            >
                <option value=""><?php esc_html_e('Select a source', 'gpt3-ai-content-generator'); ?></option>
                <?php foreach ($recipe_mapping_source_option_groups as $group_data) : ?>
                    <?php
                    $group_label = sanitize_text_field((string) ($group_data['label'] ?? ''));
                    $group_options = $group_data['options'] ?? [];
                    if (!is_array($group_options) || empty($group_options)) {
                        continue;
                    }
                    ?>
                    <optgroup label="<?php echo esc_attr($group_label); ?>">
                        <?php foreach ($group_options as $source_key => $source_data) : ?>
                            <option
                                value="<?php echo esc_attr((string) $source_key); ?>"
                                data-event-names="<?php echo esc_attr(implode(',', array_unique($source_data['event_names'] ?? []))); ?>"
                                <?php selected($source_path, (string) $source_key); ?>
                            >
                                <?php echo esc_html((string) ($source_data['label'] ?? $source_key)); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="aipkit_settings_recipe_mapping_field">
            <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Transform', 'gpt3-ai-content-generator'); ?></span>
            <select
                name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][mapping][fields][<?php echo esc_attr($mapping_index); ?>][transform]"
                class="aipkit_form-input aipkit_autosave_trigger"
                data-aipkit-recipe-mapping-field="transform"
            >
                <?php foreach (($recipe_mapping_definitions['transform_options'] ?? []) as $transform_key => $transform_label) : ?>
                    <option value="<?php echo esc_attr((string) $transform_key); ?>" <?php selected($transform, (string) $transform_key); ?>>
                        <?php echo esc_html((string) $transform_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="aipkit_settings_recipe_mapping_field">
            <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Fallback value', 'gpt3-ai-content-generator'); ?></span>
            <input
                type="text"
                name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][mapping][fields][<?php echo esc_attr($mapping_index); ?>][fallback_value]"
                value="<?php echo esc_attr($fallback_value); ?>"
                class="aipkit_form-input aipkit_autosave_trigger"
                data-aipkit-recipe-mapping-field="fallback_value"
                placeholder="<?php esc_attr_e('Optional fallback', 'gpt3-ai-content-generator'); ?>"
            />
        </label>
        <label class="aipkit_settings_recipe_mapping_toggle">
            <input
                type="checkbox"
                name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][mapping][fields][<?php echo esc_attr($mapping_index); ?>][is_required]"
                value="1"
                class="aipkit_autosave_trigger"
                data-aipkit-recipe-mapping-field="is_required"
                <?php checked($is_required); ?>
            />
            <span><?php esc_html_e('Required', 'gpt3-ai-content-generator'); ?></span>
        </label>
        <div class="aipkit_settings_recipe_mapping_actions">
            <button
                type="button"
                class="aipkit_settings_row_icon_button aipkit_settings_row_icon_button--danger"
                data-aipkit-remove-recipe-mapping
                aria-label="<?php esc_attr_e('Remove mapping', 'gpt3-ai-content-generator'); ?>"
                title="<?php esc_attr_e('Remove mapping', 'gpt3-ai-content-generator'); ?>"
            >
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    <?php
};

$render_recipe = static function (
    $index,
    array $recipe = []
) use (
    $recipe_connection_options,
    $recipe_event_options,
    $recipe_app_options,
    $recipe_action_options,
    $recipe_action_options_by_app,
    $recipe_chatbot_scope_options,
    $recipe_ai_form_scope_options,
    $recipe_template_definitions,
    $recipe_mapping_definitions,
    $recipe_connection_records,
    $recipe_validator_class,
    $render_recipe_mapping_row
): void {
    $recipe_index = (string) $index;
    $recipe_id = sanitize_text_field((string) ($recipe['id'] ?? ''));
    $recipe_name = (string) ($recipe['name'] ?? '');
    $recipe_template_slug = sanitize_key((string) ($recipe['template_slug'] ?? ''));
    $recipe_template_definition = $recipe_template_definitions[$recipe_template_slug] ?? [];
    if (
        $recipe_template_slug !== ''
        && $recipe_name === $recipe_template_slug
        && is_array($recipe_template_definition)
    ) {
        $recipe_name = sanitize_text_field(
            (string) (
                $recipe_template_definition['name']
                ?? $recipe_template_definition['label']
                ?? $recipe_name
            )
        );
    }
    $recipe_connection_id = sanitize_text_field((string) ($recipe['connection_id'] ?? ''));
    $recipe_app_slug = sanitize_key((string) ($recipe['app_slug'] ?? 'slack'));
    if (!isset($recipe_app_options[$recipe_app_slug])) {
        $recipe_app_slug = 'slack';
    }

    $recipe_event_name = sanitize_text_field((string) ($recipe['event_name'] ?? ''));
    if (!isset($recipe_event_options[$recipe_event_name]) && !empty($recipe_event_options)) {
        $recipe_event_name = (string) array_key_first($recipe_event_options);
    }

    $recipe_action_slug = sanitize_key((string) ($recipe['action_slug'] ?? ''));
    if (!isset($recipe_action_options[$recipe_action_slug])) {
        $defaults_for_app = $recipe_action_options_by_app[$recipe_app_slug] ?? [];
        $recipe_action_slug = (string) ($defaults_for_app[0] ?? array_key_first($recipe_action_options));
    }

    $recipe_status = sanitize_key((string) ($recipe['status'] ?? 'draft'));
    if (!in_array($recipe_status, ['draft', 'active', 'inactive', 'error'], true)) {
        $recipe_status = 'draft';
    }

    $recipe_enabled = isset($recipe['is_enabled']) && !empty($recipe['is_enabled']);
    $recipe_summary_connection = $recipe_connection_options[$recipe_connection_id]['label'] ?? __('No connection', 'gpt3-ai-content-generator');
    $recipe_summary_event = $recipe_event_name !== '' ? $recipe_event_name : __('No event', 'gpt3-ai-content-generator');
    $recipe_summary_action = $recipe_action_options[$recipe_action_slug] ?? __('No action', 'gpt3-ai-content-generator');
    $recipe_chatbot_scope_bot_ids = isset($recipe['filters']['chatbot']['bot_ids']) && is_array($recipe['filters']['chatbot']['bot_ids'])
        ? array_values(array_filter(array_map('absint', $recipe['filters']['chatbot']['bot_ids'])))
        : [];
    $recipe_chatbot_scope_bot_id = !empty($recipe_chatbot_scope_bot_ids)
        ? (string) ((int) $recipe_chatbot_scope_bot_ids[0])
        : '';
    $recipe_chatbot_scope_label = __('All Chatbots', 'gpt3-ai-content-generator');
    if (!empty($recipe_chatbot_scope_bot_ids)) {
        if (count($recipe_chatbot_scope_bot_ids) === 1) {
            $scope_bot_id = (int) $recipe_chatbot_scope_bot_ids[0];
            $recipe_chatbot_scope_label = $recipe_chatbot_scope_options[$scope_bot_id] ?? sprintf(
                /* translators: %d: chatbot post ID */
                __('Chatbot #%d', 'gpt3-ai-content-generator'),
                $scope_bot_id
            );
        } else {
            $recipe_chatbot_scope_label = sprintf(
                /* translators: %d: number of selected chatbots */
                __('%d Chatbots', 'gpt3-ai-content-generator'),
                count($recipe_chatbot_scope_bot_ids)
            );
        }
    }
    $recipe_ai_form_scope_form_ids = isset($recipe['filters']['ai_form']['form_ids']) && is_array($recipe['filters']['ai_form']['form_ids'])
        ? array_values(array_filter(array_map('absint', $recipe['filters']['ai_form']['form_ids'])))
        : [];
    $recipe_ai_form_scope_form_id = !empty($recipe_ai_form_scope_form_ids)
        ? (string) ((int) $recipe_ai_form_scope_form_ids[0])
        : '';
    $recipe_ai_form_scope_label = __('All AI Forms', 'gpt3-ai-content-generator');
    if (!empty($recipe_ai_form_scope_form_ids)) {
        if (count($recipe_ai_form_scope_form_ids) === 1) {
            $scope_form_id = (int) $recipe_ai_form_scope_form_ids[0];
            $recipe_ai_form_scope_label = $recipe_ai_form_scope_options[$scope_form_id] ?? sprintf(
                /* translators: %d: AI Form post ID */
                __('AI Form #%d', 'gpt3-ai-content-generator'),
                $scope_form_id
            );
        } else {
            $recipe_ai_form_scope_label = sprintf(
                /* translators: %d: number of selected AI Forms */
                __('%d AI Forms', 'gpt3-ai-content-generator'),
                count($recipe_ai_form_scope_form_ids)
            );
        }
    }
    $recipe_summary_parts = [
        $recipe_summary_connection,
        $recipe_summary_event,
        $recipe_summary_action,
    ];
    if (strpos($recipe_event_name, 'chatbot.') === 0) {
        $recipe_summary_parts[] = $recipe_chatbot_scope_label;
    } elseif ($recipe_event_name === 'form.submitted') {
        $recipe_summary_parts[] = $recipe_ai_form_scope_label;
    }
    $recipe_mapping_fields = [];
    if (isset($recipe['mapping']['fields']) && is_array($recipe['mapping']['fields'])) {
        $recipe_mapping_fields = array_values(array_filter($recipe['mapping']['fields'], 'is_array'));
    }
    $recipe_mapping_object_label = (string) (($recipe_mapping_definitions['event_object_types'][$recipe_event_name]['label'] ?? __('Event Record', 'gpt3-ai-content-generator')));
    $recipe_validation_connection = $recipe_connection_records[$recipe_connection_id] ?? null;
    $recipe_validation_state = class_exists($recipe_validator_class) && method_exists($recipe_validator_class, 'get_recipe_ui_state')
        ? $recipe_validator_class::get_recipe_ui_state($recipe, is_array($recipe_validation_connection) ? $recipe_validation_connection : null)
        : [
            'status_key' => 'warning',
            'summary' => __('Validation unavailable.', 'gpt3-ai-content-generator'),
        ];
    $recipe_validation_status = sanitize_key((string) ($recipe_validation_state['status_key'] ?? 'warning'));
    if (!in_array($recipe_validation_status, ['ready', 'warning', 'error', 'reauth_required'], true)) {
        $recipe_validation_status = 'warning';
    }
    $recipe_validation_summary = sanitize_text_field((string) ($recipe_validation_state['summary'] ?? __('Validation unavailable.', 'gpt3-ai-content-generator')));
    $recipe_validation_id = 'aipkit_recipe_validation_' . sanitize_html_class($recipe_index);
    $recipe_can_enable = $recipe_enabled || !in_array($recipe_validation_status, ['error', 'reauth_required'], true);
    $recipe_status_badge_labels = [
        'ready' => __('Ready', 'gpt3-ai-content-generator'),
        'warning' => __('Warning', 'gpt3-ai-content-generator'),
        'error' => __('Error', 'gpt3-ai-content-generator'),
        'reauth_required' => __('Reauth required', 'gpt3-ai-content-generator'),
    ];
    ?>
    <article class="aipkit_settings_recipe_card" data-aipkit-recipe-card data-recipe-index="<?php echo esc_attr($recipe_index); ?>">
        <input
            type="hidden"
            name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][id]"
            value="<?php echo esc_attr($recipe_id); ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-recipe-field="id"
        />
        <input
            type="hidden"
            name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][status]"
            value="<?php echo esc_attr($recipe_status); ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-recipe-field="status"
        />
        <input
            type="hidden"
            name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][is_enabled]"
            value="<?php echo $recipe_enabled ? '1' : '0'; ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-recipe-field="is_enabled"
        />
        <input
            type="hidden"
            name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][template_slug]"
            value="<?php echo esc_attr($recipe_template_slug); ?>"
            class="aipkit_autosave_trigger"
            data-aipkit-recipe-field="template_slug"
        />

        <div class="aipkit_settings_recipe_card_header">
            <div class="aipkit_settings_recipe_card_identity">
                <div class="aipkit_settings_recipe_card_heading">
                    <strong class="aipkit_settings_recipe_card_title" data-aipkit-recipe-title>
                        <?php echo esc_html($recipe_name !== '' ? $recipe_name : __('Untitled recipe', 'gpt3-ai-content-generator')); ?>
                    </strong>
                    <span class="aipkit_settings_recipe_card_index" data-aipkit-recipe-number></span>
                    <span class="aipkit_settings_recipe_status aipkit_settings_recipe_status--<?php echo esc_attr($recipe_validation_status); ?>" data-aipkit-recipe-status-badge>
                        <?php echo esc_html($recipe_status_badge_labels[$recipe_validation_status] ?? __('Warning', 'gpt3-ai-content-generator')); ?>
                    </span>
                    <span class="aipkit_settings_recipe_enabled_flag aipkit_settings_recipe_enabled_flag--<?php echo $recipe_enabled ? 'enabled' : 'disabled'; ?>" data-aipkit-recipe-enabled-flag>
                        <?php echo $recipe_enabled ? esc_html__('Enabled', 'gpt3-ai-content-generator') : esc_html__('Disabled', 'gpt3-ai-content-generator'); ?>
                    </span>
                </div>
                <code
                    class="aipkit_settings_recipe_technical_reference"
                    data-aipkit-recipe-technical-reference
                    <?php if ($recipe_template_slug === '') : ?>hidden<?php endif; ?>
                ><?php echo esc_html($recipe_template_slug); ?></code>
            </div>
            <div class="aipkit_settings_recipe_card_actions">
                <button
                    type="button"
                    class="button aipkit_btn aipkit_settings_card_toggle"
                    data-aipkit-recipe-edit-toggle
                    aria-expanded="false"
                    aria-label="<?php esc_attr_e('Edit recipe', 'gpt3-ai-content-generator'); ?>"
                >
                    <?php esc_html_e('Edit', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>
        </div>

        <p class="aipkit_settings_recipe_card_summary" data-aipkit-recipe-summary>
            <?php echo esc_html(implode(' | ', $recipe_summary_parts)); ?>
        </p>
        <p
            id="<?php echo esc_attr($recipe_validation_id); ?>"
            class="aipkit_settings_recipe_card_validation aipkit_settings_recipe_card_validation--<?php echo esc_attr($recipe_validation_status); ?>"
            data-aipkit-recipe-validation
            data-status-key="<?php echo esc_attr($recipe_validation_status); ?>"
        >
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <span data-aipkit-recipe-validation-text><?php echo esc_html($recipe_validation_summary); ?></span>
        </p>

        <div class="aipkit_settings_recipe_card_body" data-aipkit-recipe-body hidden>
            <div class="aipkit_settings_recipe_card_actions aipkit_settings_recipe_card_actions--body">
                <button
                    type="button"
                    class="button button-primary aipkit_btn aipkit_btn-primary"
                    data-aipkit-recipe-toggle-enabled
                    aria-describedby="<?php echo esc_attr($recipe_validation_id); ?>"
                    <?php disabled(!$recipe_can_enable); ?>
                    <?php if (!$recipe_can_enable) : ?>title="<?php echo esc_attr($recipe_validation_summary); ?>"<?php endif; ?>
                >
                    <?php echo $recipe_enabled ? esc_html__('Disable', 'gpt3-ai-content-generator') : esc_html__('Enable', 'gpt3-ai-content-generator'); ?>
                </button>
                <button type="button" class="button aipkit_btn" data-aipkit-recipe-duplicate>
                    <?php esc_html_e('Duplicate', 'gpt3-ai-content-generator'); ?>
                </button>
                <button type="button" class="button aipkit_btn aipkit_btn-danger aipkit_btn-danger--quiet" data-aipkit-remove-recipe>
                    <?php esc_html_e('Delete', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>

            <h4 class="aipkit_settings_recipe_section_label"><?php esc_html_e('Configuration', 'gpt3-ai-content-generator'); ?></h4>
            <div class="aipkit_settings_recipe_builder_flow">
                <label class="aipkit_settings_recipe_field aipkit_settings_recipe_field--step">
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Event', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Choose what should trigger this recipe.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][event_name]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-field="event_name"
                    >
                        <?php foreach ($recipe_event_options as $event_name => $event_label) : ?>
                            <option value="<?php echo esc_attr($event_name); ?>" <?php selected($recipe_event_name, $event_name); ?>>
                                <?php echo esc_html($event_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="aipkit_settings_recipe_field aipkit_settings_recipe_field--step">
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('App', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Choose where the event should go.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][app_slug]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-field="app_slug"
                    >
                        <?php foreach ($recipe_app_options as $app_slug => $app_label) : ?>
                            <option value="<?php echo esc_attr($app_slug); ?>" <?php selected($recipe_app_slug, $app_slug); ?>>
                                <?php echo esc_html($app_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="aipkit_settings_recipe_field aipkit_settings_recipe_field--step">
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Action', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Choose what the app should do.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][action_slug]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-field="action_slug"
                        data-aipkit-recipe-action-select
                    >
                        <?php foreach ($recipe_action_options as $action_slug => $action_label) : ?>
                            <?php
                            $action_app_slugs = [];
                            foreach ($recipe_action_options_by_app as $app_slug => $action_slugs) {
                                if (in_array($action_slug, $action_slugs, true)) {
                                    $action_app_slugs[] = $app_slug;
                                }
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr($action_slug); ?>"
                                data-app-slugs="<?php echo esc_attr(implode(',', $action_app_slugs)); ?>"
                                <?php selected($recipe_action_slug, $action_slug); ?>
                            >
                                <?php echo esc_html($action_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="aipkit_settings_recipe_field aipkit_settings_recipe_field--step">
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Connection', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Choose a connection for this app.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][connection_id]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-field="connection_id"
                        data-aipkit-recipe-connection-select
                    >
                        <option value=""><?php esc_html_e('Select a connection', 'gpt3-ai-content-generator'); ?></option>
                        <?php foreach ($recipe_connection_options as $connection_id => $connection_data) : ?>
                            <option
                                value="<?php echo esc_attr($connection_id); ?>"
                                data-app-slug="<?php echo esc_attr((string) ($connection_data['app_slug'] ?? '')); ?>"
                                data-connection-status="<?php echo esc_attr((string) ($connection_data['status'] ?? 'draft')); ?>"
                                data-is-enabled="<?php echo !empty($connection_data['is_enabled']) ? '1' : '0'; ?>"
                                <?php selected($recipe_connection_id, $connection_id); ?>
                            >
                                <?php echo esc_html((string) ($connection_data['label'] ?? $connection_id)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="aipkit_settings_recipe_fields">
                <label class="aipkit_settings_recipe_field aipkit_settings_recipe_field--wide">
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Name', 'gpt3-ai-content-generator'); ?></span>
                    <input
                        type="text"
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][name]"
                        value="<?php echo esc_attr($recipe_name); ?>"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-field="name"
                        placeholder="<?php esc_attr_e('Untitled recipe', 'gpt3-ai-content-generator'); ?>"
                    />
                </label>
                <label
                    class="aipkit_settings_recipe_field aipkit_settings_recipe_field--wide"
                    data-aipkit-recipe-chatbot-scope-row
                    <?php echo strpos($recipe_event_name, 'chatbot.') === 0 ? '' : 'hidden'; ?>
                >
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('Chatbot scope', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Optionally limit this recipe to one chatbot.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][filters][chatbot][bot_ids][0]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-chatbot-scope-select
                        <?php echo strpos($recipe_event_name, 'chatbot.') === 0 ? '' : 'disabled'; ?>
                    >
                        <option value=""><?php esc_html_e('All Chatbots', 'gpt3-ai-content-generator'); ?></option>
                        <?php foreach ($recipe_chatbot_scope_options as $chatbot_scope_bot_id => $chatbot_scope_label) : ?>
                            <option value="<?php echo esc_attr((string) $chatbot_scope_bot_id); ?>" <?php selected($recipe_chatbot_scope_bot_id, (string) $chatbot_scope_bot_id); ?>>
                                <?php echo esc_html((string) $chatbot_scope_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label
                    class="aipkit_settings_recipe_field aipkit_settings_recipe_field--wide"
                    data-aipkit-recipe-ai-form-scope-row
                    <?php echo $recipe_event_name === 'form.submitted' ? '' : 'hidden'; ?>
                >
                    <span class="aipkit_settings_recipe_field_label"><?php esc_html_e('AI form scope', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_recipe_field_hint"><?php esc_html_e('Optionally limit this recipe to one AI form.', 'gpt3-ai-content-generator'); ?></span>
                    <select
                        name="native_app_recipes[recipes][<?php echo esc_attr($recipe_index); ?>][filters][ai_form][form_ids][0]"
                        class="aipkit_form-input aipkit_autosave_trigger"
                        data-aipkit-recipe-ai-form-scope-select
                        <?php echo $recipe_event_name === 'form.submitted' ? '' : 'disabled'; ?>
                    >
                        <option value=""><?php esc_html_e('All AI Forms', 'gpt3-ai-content-generator'); ?></option>
                        <?php foreach ($recipe_ai_form_scope_options as $ai_form_scope_form_id => $ai_form_scope_label) : ?>
                            <option value="<?php echo esc_attr((string) $ai_form_scope_form_id); ?>" <?php selected($recipe_ai_form_scope_form_id, (string) $ai_form_scope_form_id); ?>>
                                <?php echo esc_html((string) $ai_form_scope_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="aipkit_settings_recipe_mapping">
                <div class="aipkit_settings_recipe_mapping_header">
                    <div class="aipkit_settings_recipe_mapping_heading">
                        <strong><?php esc_html_e('Field mapping', 'gpt3-ai-content-generator'); ?></strong>
                        <span data-aipkit-recipe-mapping-object-label>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: mapping object label */
                                    __('Map this %s into the destination app.', 'gpt3-ai-content-generator'),
                                    $recipe_mapping_object_label
                                )
                            );
                            ?>
                        </span>
                    </div>
                    <button type="button" class="button aipkit_btn" data-aipkit-add-recipe-mapping>
                        <?php esc_html_e('Add mapping', 'gpt3-ai-content-generator'); ?>
                    </button>
                </div>
                <div class="aipkit_settings_recipe_mapping_list" data-aipkit-recipe-mapping-list>
                    <?php foreach ($recipe_mapping_fields as $mapping_index => $mapping_field) : ?>
                        <?php $render_recipe_mapping_row($recipe_index, (string) $mapping_index, $mapping_field); ?>
                    <?php endforeach; ?>
                </div>
                <template data-aipkit-recipe-mapping-template>
                    <?php $render_recipe_mapping_row($recipe_index, '__MAP_INDEX__'); ?>
                </template>
            </div>
        </div>
    </article>
    <?php
};
?>

<section id="aipkit_settings_recipes_section">
    <div class="aipkit_form-group aipkit_settings_simple_row aipkit_settings_simple_row--recipes" id="aipkit_settings_recipes_row">
        <div class="aipkit_form-label">
            <?php esc_html_e('Recipes', 'gpt3-ai-content-generator'); ?>
            <span class="aipkit_form-label-helper"><?php esc_html_e('Create event-to-app recipes.', 'gpt3-ai-content-generator'); ?></span>
        </div>
        <div class="aipkit_settings_recipes_main">
            <div class="aipkit_settings_recipes_toolbar">
                <button type="button" class="button button-secondary aipkit_btn" id="aipkit_add_recipe_btn">
                    <?php esc_html_e('Add recipe', 'gpt3-ai-content-generator'); ?>
                </button>
                <?php if (!empty($recipe_template_groups)) : ?>
                    <div class="aipkit_settings_recipes_template_picker">
                        <select class="aipkit_form-input" id="aipkit_recipe_template_picker">
                            <option value=""><?php esc_html_e('Start with a template', 'gpt3-ai-content-generator'); ?></option>
                            <?php foreach ($recipe_template_groups as $group_label => $group_templates) : ?>
                                <optgroup label="<?php echo esc_attr($group_label); ?>">
                                    <?php foreach ($group_templates as $template_slug => $template_definition) : ?>
                                        <option value="<?php echo esc_attr((string) $template_slug); ?>">
                                            <?php echo esc_html((string) ($template_definition['label'] ?? $template_slug)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button
                            type="button"
                            class="button button-secondary aipkit_btn"
                            id="aipkit_add_recipe_template_btn"
                            disabled
                        >
                            <?php esc_html_e('Use template', 'gpt3-ai-content-generator'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="aipkit_settings_recipe_list" data-aipkit-recipe-list>
                <?php if (!empty($stored_recipes)) : ?>
                    <?php foreach ($stored_recipes as $recipe_index => $recipe) : ?>
                        <?php $render_recipe($recipe_index, is_array($recipe) ? $recipe : []); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <template id="aipkit_recipe_template">
        <?php $render_recipe('__INDEX__'); ?>
    </template>
    <script type="application/json" id="aipkit_recipe_template_definitions">
        <?php
        echo wp_json_encode(
            $recipe_template_definitions,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        ?>
    </script>
    <script type="application/json" id="aipkit_recipe_mapping_definitions">
        <?php
        echo wp_json_encode(
            $recipe_mapping_definitions,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        ?>
    </script>
    <script type="application/json" id="aipkit_recipe_validation_definitions">
        <?php
        echo wp_json_encode(
            $recipe_validation_definitions,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        ?>
    </script>
</section>
