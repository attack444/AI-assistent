<?php
/**
 * AI provider cards and provider-specific settings modals.
 *
 * The provider schema below is the single source of truth for both surfaces.
 */

use WPAICG\Core\Moderation\AIPKit_Global_Security_Settings;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local schema and renderer helpers.

$aipkit_provider_data = [
    'OpenAI' => $openai_data,
    'Google' => $google_data,
    'Claude' => $claude_data,
    'OpenRouter' => $openrouter_data,
    'Azure' => $azure_data,
    'Ollama' => $ollama_data,
    'DeepSeek' => $deepseek_data,
    'xAI' => $xai_data,
];

$aipkit_provider_defaults = [
    'OpenAI' => $openai_defaults,
    'Google' => $google_defaults,
    'Claude' => $claude_defaults,
    'OpenRouter' => $openrouter_defaults,
    'Azure' => $azure_defaults,
    'Ollama' => $ollama_defaults,
    'DeepSeek' => $deepseek_defaults,
    'xAI' => $xai_defaults,
];

$aipkit_security_settings = class_exists(AIPKit_Global_Security_Settings::class)
    ? AIPKit_Global_Security_Settings::get_settings()
    : [];
$aipkit_moderation_enabled = (string) ($aipkit_security_settings['openai_moderation_enabled'] ?? '0');
$aipkit_moderation_message = (string) ($aipkit_security_settings['openai_moderation_message']
    ?? __('Your message was flagged by the moderation system and could not be sent.', 'gpt3-ai-content-generator'));
$aipkit_model_sync_timestamps = get_option('aipkit_model_sync_timestamps', []);
$aipkit_model_sync_timestamps = is_array($aipkit_model_sync_timestamps) ? $aipkit_model_sync_timestamps : [];

$aipkit_common_sampling_fields = [
    [
        'id' => 'temperature',
        'type' => 'number',
        'name' => '',
        'global_name' => 'temperature',
        'label' => __('Temperature', 'gpt3-ai-content-generator'),
        'description' => __('Creativity level.', 'gpt3-ai-content-generator'),
        'value' => $temperature,
        'min' => '0',
        'max' => '2',
        'step' => '0.1',
    ],
    [
        'id' => 'top_p',
        'type' => 'number',
        'name' => '',
        'global_name' => 'top_p',
        'label' => __('Top P', 'gpt3-ai-content-generator'),
        'description' => __('Sampling diversity.', 'gpt3-ai-content-generator'),
        'value' => $top_p,
        'min' => '0',
        'max' => '1',
        'step' => '0.01',
    ],
];

$aipkit_endpoint_fields = static function (string $provider, string $slug) use ($aipkit_provider_data, $aipkit_provider_defaults): array {
    $data = $aipkit_provider_data[$provider] ?? [];
    $defaults = $aipkit_provider_defaults[$provider] ?? [];

    return [
        [
            'id' => 'base_url',
            'type' => 'text',
            'name' => $slug . '_base_url',
            'label' => __('Base URL', 'gpt3-ai-content-generator'),
            'description' => __('Custom API endpoint.', 'gpt3-ai-content-generator'),
            'value' => (string) ($data['base_url'] ?? ''),
            'default' => (string) ($defaults['base_url'] ?? ''),
            'monospace' => true,
            'reset' => true,
        ],
        [
            'id' => 'api_version',
            'type' => 'text',
            'name' => $slug . '_api_version',
            'label' => __('API version', 'gpt3-ai-content-generator'),
            'description' => __('Endpoint version.', 'gpt3-ai-content-generator'),
            'value' => (string) ($data['api_version'] ?? ''),
            'default' => (string) ($defaults['api_version'] ?? ''),
            'monospace' => true,
            'reset' => true,
        ],
    ];
};

$aipkit_build_model_field = static function (string $provider, string $slug) use ($aipkit_provider_data): array {
    return [
        'id' => 'model',
        'type' => 'model',
        'name' => $provider === 'Azure' ? 'azure_deployment' : $slug . '_model',
        'label' => $provider === 'Azure'
            ? __('Default deployment', 'gpt3-ai-content-generator')
            : __('Default model', 'gpt3-ai-content-generator'),
        'description' => $provider === 'Azure'
            ? __('Deployment used by default.', 'gpt3-ai-content-generator')
            : __('Model used by default.', 'gpt3-ai-content-generator'),
        'value' => (string) (($aipkit_provider_data[$provider] ?? [])['model'] ?? ''),
    ];
};

$aipkit_provider_configs = [
    'OpenAI' => [
        'slug' => 'openai',
        'display_name' => __('OpenAI', 'gpt3-ai-content-generator'),
        'icon' => 'openai.svg',
        'accent' => '#10a37f',
        'credential_key' => 'api_key',
        'credential_name' => 'openai_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your OpenAI API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://platform.openai.com/api-keys',
        'key_link' => __('Get your OpenAI API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('OpenAI', 'openai')],
            $aipkit_endpoint_fields('OpenAI', 'openai'),
            $is_pro ? [[
                'id' => 'expiration_policy',
                'type' => 'number',
                'name' => 'openai_expiration_policy',
                'label' => __('File expiration', 'gpt3-ai-content-generator'),
                'description' => __('Vector store retention period.', 'gpt3-ai-content-generator'),
                'value' => (string) ($openai_data['expiration_policy'] ?? 7),
                'min' => '1',
                'max' => '365',
                'step' => '1',
                'suffix' => __('days', 'gpt3-ai-content-generator'),
            ]] : [],
            [
                [
                    'id' => 'store_conversation',
                    'type' => 'toggle',
                    'name' => 'openai_store_conversation',
                    'label' => __('Store conversation', 'gpt3-ai-content-generator'),
                    'description' => __('Save chat history on OpenAI server.', 'gpt3-ai-content-generator'),
                    'value' => (string) ($openai_data['store_conversation'] ?? '0'),
                ],
                [
                    'id' => 'moderation',
                    'type' => 'toggle',
                    'name' => 'security[openai_moderation_enabled]',
                    'label' => __('Moderation', 'gpt3-ai-content-generator'),
                    'description' => __('Moderate user input.', 'gpt3-ai-content-generator'),
                    'value' => $aipkit_moderation_enabled,
                    'controls' => 'aipkit_settings_openai_moderation_message_row',
                ],
                [
                    'id' => 'moderation_message',
                    'type' => 'text',
                    'name' => 'security[openai_moderation_message]',
                    'label' => __('Moderation message', 'gpt3-ai-content-generator'),
                    'description' => __('Shown when a message is blocked.', 'gpt3-ai-content-generator'),
                    'value' => $aipkit_moderation_message,
                    'row_id' => 'aipkit_settings_openai_moderation_message_row',
                    'hidden' => $aipkit_moderation_enabled !== '1',
                ],
            ]
        ),
    ],
    'Claude' => [
        'slug' => 'claude',
        'display_name' => __('Anthropic', 'gpt3-ai-content-generator'),
        'icon' => 'anthropic.svg',
        'accent' => '#d97757',
        'credential_key' => 'api_key',
        'credential_name' => 'claude_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your Anthropic API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://console.anthropic.com/settings/keys',
        'key_link' => __('Get your Anthropic API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('Claude', 'claude')],
            $aipkit_endpoint_fields('Claude', 'claude')
        ),
    ],
    'Google' => [
        'slug' => 'google',
        'display_name' => __('Google', 'gpt3-ai-content-generator'),
        'icon' => 'google.svg',
        'accent' => '#4285f4',
        'credential_key' => 'api_key',
        'credential_name' => 'google_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your Google AI API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://aistudio.google.com/app/apikey',
        'key_link' => __('Get your Google AI API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('Google', 'google')],
            $aipkit_endpoint_fields('Google', 'google')
        ),
    ],
    'OpenRouter' => [
        'slug' => 'openrouter',
        'display_name' => __('OpenRouter', 'gpt3-ai-content-generator'),
        'icon' => 'openrouter.svg',
        'accent' => '#6366f1',
        'credential_key' => 'api_key',
        'credential_name' => 'openrouter_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your OpenRouter API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://openrouter.ai/settings/keys',
        'key_link' => __('Get your OpenRouter API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('OpenRouter', 'openrouter')],
            $aipkit_endpoint_fields('OpenRouter', 'openrouter')
        ),
    ],
    'Azure' => [
        'slug' => 'azure',
        'display_name' => __('Azure AI', 'gpt3-ai-content-generator'),
        'icon' => 'azure.svg',
        'accent' => '#0078d4',
        'credential_key' => 'api_key',
        'credential_name' => 'azure_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your Azure AI API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://ai.azure.com/',
        'key_link' => __('Open Azure AI Foundry', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('Azure', 'azure')],
            [
                [
                    'id' => 'endpoint',
                    'type' => 'url',
                    'name' => 'azure_endpoint',
                    'label' => __('Endpoint URL', 'gpt3-ai-content-generator'),
                    'description' => __('Azure resource endpoint.', 'gpt3-ai-content-generator'),
                    'value' => (string) ($azure_data['endpoint'] ?? ''),
                    'default' => '',
                    'monospace' => true,
                    'reset' => true,
                ],
                [
                    'id' => 'authoring_version',
                    'type' => 'text',
                    'name' => 'azure_api_version_authoring',
                    'label' => __('Authoring API version', 'gpt3-ai-content-generator'),
                    'description' => __('Deployment management version.', 'gpt3-ai-content-generator'),
                    'value' => (string) ($azure_data['api_version_authoring'] ?? ''),
                    'default' => (string) ($azure_defaults['api_version_authoring'] ?? ''),
                    'monospace' => true,
                    'reset' => true,
                ],
                [
                    'id' => 'inference_version',
                    'type' => 'text',
                    'name' => 'azure_api_version_inference',
                    'label' => __('Inference API version', 'gpt3-ai-content-generator'),
                    'description' => __('Model request version.', 'gpt3-ai-content-generator'),
                    'value' => (string) ($azure_data['api_version_inference'] ?? ''),
                    'default' => (string) ($azure_defaults['api_version_inference'] ?? ''),
                    'monospace' => true,
                    'reset' => true,
                ],
                [
                    'id' => 'images_version',
                    'type' => 'text',
                    'name' => 'azure_api_version_images',
                    'label' => __('Images API version', 'gpt3-ai-content-generator'),
                    'description' => __('Image request version.', 'gpt3-ai-content-generator'),
                    'value' => (string) ($azure_data['api_version_images'] ?? ''),
                    'default' => (string) ($azure_defaults['api_version_images'] ?? ''),
                    'monospace' => true,
                    'reset' => true,
                ],
            ]
        ),
    ],
    'Ollama' => [
        'slug' => 'ollama',
        'display_name' => __('Ollama', 'gpt3-ai-content-generator'),
        'icon' => 'ollama.svg',
        'accent' => '#111827',
        'credential_key' => 'base_url',
        'credential_name' => 'ollama_base_url',
        'credential_type' => 'url',
        'credential_placeholder' => __('Enter your Ollama server URL', 'gpt3-ai-content-generator'),
        'key_url' => 'https://ollama.com/download',
        'key_link' => __('Get Ollama', 'gpt3-ai-content-generator'),
        'fields' => [$aipkit_build_model_field('Ollama', 'ollama')],
        'requires_pro' => true,
        'pro_description' => __('Run open-source models locally with Ollama — no API costs and no data leaving your server.', 'gpt3-ai-content-generator'),
    ],
    'DeepSeek' => [
        'slug' => 'deepseek',
        'display_name' => __('DeepSeek', 'gpt3-ai-content-generator'),
        'icon' => 'deepseek.svg',
        'accent' => '#4d6bfe',
        'credential_key' => 'api_key',
        'credential_name' => 'deepseek_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your DeepSeek API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://platform.deepseek.com/api_keys',
        'key_link' => __('Get your DeepSeek API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('DeepSeek', 'deepseek')],
            $aipkit_endpoint_fields('DeepSeek', 'deepseek')
        ),
    ],
    'xAI' => [
        'slug' => 'xai',
        'display_name' => __('xAI', 'gpt3-ai-content-generator'),
        'icon' => 'xai.svg',
        'accent' => '#111827',
        'credential_key' => 'api_key',
        'credential_name' => 'xai_api_key',
        'credential_type' => 'password',
        'credential_placeholder' => __('Paste your xAI API key', 'gpt3-ai-content-generator'),
        'key_url' => 'https://console.x.ai/team/default/api-keys',
        'key_link' => __('Get your xAI API key', 'gpt3-ai-content-generator'),
        'fields' => array_merge(
            [$aipkit_build_model_field('xAI', 'xai')],
            $aipkit_endpoint_fields('xAI', 'xai')
        ),
    ],
];

$aipkit_safety_categories = [
    'HARM_CATEGORY_HARASSMENT' => __('Harassment', 'gpt3-ai-content-generator'),
    'HARM_CATEGORY_HATE_SPEECH' => __('Hate speech', 'gpt3-ai-content-generator'),
    'HARM_CATEGORY_SEXUALLY_EXPLICIT' => __('Sexually explicit', 'gpt3-ai-content-generator'),
    'HARM_CATEGORY_DANGEROUS_CONTENT' => __('Dangerous content', 'gpt3-ai-content-generator'),
    'HARM_CATEGORY_CIVIC_INTEGRITY' => __('Civic integrity', 'gpt3-ai-content-generator'),
];
$aipkit_safety_options = [
    'BLOCK_NONE' => __('Block none', 'gpt3-ai-content-generator'),
    'BLOCK_LOW_AND_ABOVE' => __('Block few', 'gpt3-ai-content-generator'),
    'BLOCK_MEDIUM_AND_ABOVE' => __('Block some', 'gpt3-ai-content-generator'),
    'BLOCK_ONLY_HIGH' => __('Block most', 'gpt3-ai-content-generator'),
];
foreach ($aipkit_safety_categories as $aipkit_category_key => $aipkit_category_label) {
    $aipkit_short_category = strtolower(str_replace('HARM_CATEGORY_', '', $aipkit_category_key));
    $aipkit_provider_configs['Google']['fields'][] = [
        'id' => 'safety_' . $aipkit_short_category,
        'type' => 'select',
        'name' => 'safety_' . $aipkit_short_category,
        'label' => $aipkit_category_label,
        'description' => __('Safety threshold.', 'gpt3-ai-content-generator'),
        'value' => (string) ($category_thresholds[$aipkit_category_key] ?? 'BLOCK_NONE'),
        'options' => $aipkit_safety_options,
    ];
}

$aipkit_render_model_options = static function (string $provider, string $current_model): void {
    $payload = [
        'groups' => [],
        'manual_option' => null,
        'has_selectable_options' => false,
        'empty_option_label' => __('Sync to load models', 'gpt3-ai-content-generator'),
    ];

    if (class_exists('\\WPAICG\\AIPKit_Provider_Model_List_Builder')) {
        $payload = \WPAICG\AIPKit_Provider_Model_List_Builder::get_model_options($provider, $current_model);
    }

    foreach ((array) ($payload['groups'] ?? []) as $group) {
        if (!is_array($group) || empty($group['options'])) {
            continue;
        }
        $group_label = (string) ($group['label'] ?? '');
        if ($group_label !== '') {
            echo '<optgroup label="' . esc_attr($group_label) . '">';
        }
        foreach ((array) $group['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }
            $value = (string) ($option['value'] ?? '');
            if ($value === '') {
                continue;
            }
            echo '<option value="' . esc_attr($value) . '" ' . selected(!empty($option['selected']), true, false) . '>'
                . esc_html((string) ($option['label'] ?? $value)) . '</option>';
        }
        if ($group_label !== '') {
            echo '</optgroup>';
        }
    }

    $manual_option = is_array($payload['manual_option'] ?? null) ? $payload['manual_option'] : null;
    if ($manual_option && (string) ($manual_option['value'] ?? '') !== '') {
        $manual_value = (string) $manual_option['value'];
        echo '<option value="' . esc_attr($manual_value) . '" selected>'
            . esc_html((string) ($manual_option['label'] ?? $manual_value)) . '</option>';
    }

    if (empty($payload['has_selectable_options']) && !$manual_option) {
        echo '<option value="">' . esc_html((string) ($payload['empty_option_label'] ?? __('Sync to load models', 'gpt3-ai-content-generator'))) . '</option>';
    }
};

$aipkit_format_credential_mask = static function (string $credential): string {
    $length = strlen($credential);
    $fixed_mask = str_repeat('•', 12);

    if ($length < 9) {
        return $fixed_mask;
    }

    $prefix_length = $length >= 16 ? 8 : 3;
    $suffix_length = $length >= 16 ? 4 : 3;

    return substr($credential, 0, $prefix_length)
        . $fixed_mask
        . substr($credential, -$suffix_length);
};

$aipkit_get_model_field = static function (array $config): ?array {
    foreach ((array) ($config['fields'] ?? []) as $field) {
        if (($field['type'] ?? '') === 'model') {
            return $field;
        }
    }

    return null;
};

$aipkit_get_advanced_fields = static function (array $config) use ($aipkit_common_sampling_fields): array {
    $provider_fields = array_values(array_filter(
        (array) ($config['fields'] ?? []),
        static fn(array $field): bool => ($field['type'] ?? '') !== 'model'
    ));

    return array_merge($aipkit_common_sampling_fields, $provider_fields);
};
?>

<div
    class="aipkit_settings_provider_cards"
    data-aipkit-current-provider="<?php echo esc_attr((string) $current_provider); ?>"
>
    <input
        type="hidden"
        name="provider"
        value="<?php echo esc_attr((string) $current_provider); ?>"
        data-aipkit-default-provider-input
    />
    <input
        type="hidden"
        name="temperature"
        value="<?php echo esc_attr((string) $temperature); ?>"
        data-aipkit-global-setting-source="temperature"
    />
    <input
        type="hidden"
        name="top_p"
        value="<?php echo esc_attr((string) $top_p); ?>"
        data-aipkit-global-setting-source="top_p"
    />
    <?php foreach ($aipkit_provider_configs as $aipkit_provider => $aipkit_config) :
        $aipkit_slug = (string) $aipkit_config['slug'];
        $aipkit_data = $aipkit_provider_data[$aipkit_provider] ?? [];
        $aipkit_credential = (string) ($aipkit_data[$aipkit_config['credential_key']] ?? '');
        $aipkit_connected = $aipkit_credential !== '';
        $aipkit_locked = !empty($aipkit_config['requires_pro']) && !$is_pro;
        $aipkit_can_be_default = !$aipkit_locked && in_array($aipkit_provider, (array) $main_provider_allowlist, true);
        $aipkit_is_default = $aipkit_can_be_default && $current_provider === $aipkit_provider;
        $aipkit_is_secret = $aipkit_config['credential_type'] === 'password';
        $aipkit_credential_mask = $aipkit_is_secret && $aipkit_connected
            ? $aipkit_format_credential_mask($aipkit_credential)
            : '';
        $aipkit_credential_input_name = $aipkit_is_secret && $aipkit_connected
            ? ''
            : (string) $aipkit_config['credential_name'];
        $aipkit_credential_input_value = $aipkit_is_secret && $aipkit_connected
            ? ''
            : $aipkit_credential;
        $aipkit_model_field = $aipkit_get_model_field($aipkit_config);
        $aipkit_advanced_fields = $aipkit_get_advanced_fields($aipkit_config);
        $aipkit_has_advanced_settings = !empty($aipkit_advanced_fields);
        ?>
        <article
            id="aipkit_settings_provider_card_<?php echo esc_attr(sanitize_title($aipkit_provider)); ?>"
            class="aipkit_settings_provider_card<?php echo $aipkit_locked ? ' is-locked' : ''; ?>"
            data-aipkit-provider-card="<?php echo esc_attr($aipkit_provider); ?>"
            data-aipkit-provider-connected="<?php echo $aipkit_connected ? 'true' : 'false'; ?>"
            style="--aipkit-provider-accent: <?php echo esc_attr((string) $aipkit_config['accent']); ?>;"
        >
            <?php if ($aipkit_locked) : ?>
                <div class="aipkit_settings_provider_upgrade_header">
                    <span class="aipkit_settings_provider_upgrade_logo" aria-hidden="true">
                        <img src="<?php echo esc_url(WPAICG_PLUGIN_URL . 'admin/images/providers/' . $aipkit_config['icon']); ?>" alt="" />
                        <span class="aipkit_settings_provider_upgrade_lock">
                            <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        </span>
                    </span>
                    <div class="aipkit_settings_provider_upgrade_title">
                        <h4 class="aipkit_settings_provider_name"><?php echo esc_html((string) $aipkit_config['display_name']); ?></h4>
                        <span class="aipkit_settings_provider_status aipkit_settings_provider_status--pro aipkit_pro_badge"><?php esc_html_e('Pro', 'gpt3-ai-content-generator'); ?></span>
                    </div>
                </div>
                <p class="aipkit_settings_provider_upgrade_description">
                    <?php echo esc_html((string) ($aipkit_config['pro_description'] ?? __('Available with AI Puffer Pro.', 'gpt3-ai-content-generator'))); ?>
                </p>
                <a
                    class="aipkit_btn aipkit_settings_provider_upgrade_cta aipkit_pro_upgrade_button"
                    href="<?php echo esc_url(admin_url('admin.php?page=wpaicg-pricing')); ?>"
                >
                    <?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?>
                </a>
            <?php else : ?>
            <div class="aipkit_settings_provider_card_header">
                <span class="aipkit_settings_provider_logo" aria-hidden="true">
                    <img src="<?php echo esc_url(WPAICG_PLUGIN_URL . 'admin/images/providers/' . $aipkit_config['icon']); ?>" alt="" />
                </span>
                <h4 class="aipkit_settings_provider_name"><?php echo esc_html((string) $aipkit_config['display_name']); ?></h4>
                <span class="aipkit_settings_provider_status aipkit_settings_provider_status--connected" <?php echo $aipkit_connected ? '' : 'hidden'; ?>><?php esc_html_e('Connected', 'gpt3-ai-content-generator'); ?></span>
                <span class="aipkit_settings_provider_status aipkit_settings_provider_status--disconnected" <?php echo $aipkit_connected ? 'hidden' : ''; ?>><?php esc_html_e('Not connected', 'gpt3-ai-content-generator'); ?></span>
                <span class="aipkit_settings_provider_status aipkit_settings_provider_status--invalid" data-aipkit-provider-invalid-status hidden><?php esc_html_e('Invalid key', 'gpt3-ai-content-generator'); ?></span>
                <span class="aipkit_settings_provider_status aipkit_settings_provider_status--sync-error" data-aipkit-provider-sync-error-status hidden><?php esc_html_e('Sync failed', 'gpt3-ai-content-generator'); ?></span>
                <?php if ($aipkit_can_be_default) : ?>
                    <span
                        class="aipkit_settings_provider_status aipkit_settings_provider_status--default"
                        data-aipkit-provider-default-status
                        <?php echo $aipkit_is_default ? '' : 'hidden'; ?>
                    ><?php esc_html_e('Default', 'gpt3-ai-content-generator'); ?></span>
                    <button
                        type="button"
                        class="aipkit_settings_provider_default_action"
                        data-aipkit-provider-set-default="<?php echo esc_attr($aipkit_provider); ?>"
                        <?php echo $aipkit_is_default ? 'hidden' : ''; ?>
                    >
                        <?php esc_html_e('Set default', 'gpt3-ai-content-generator'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="aipkit_settings_provider_credential_row">
                <div class="aipkit_settings_provider_credential_wrap">
                    <input
                        type="<?php echo esc_attr((string) $aipkit_config['credential_type']); ?>"
                        id="aipkit_settings_<?php echo esc_attr($aipkit_slug); ?>_credential"
                        name="<?php echo esc_attr($aipkit_credential_input_name); ?>"
                        class="aipkit_form-input aipkit_autosave_trigger aipkit_settings_provider_credential<?php echo $aipkit_is_secret ? ' is-secret' : ''; ?><?php echo $aipkit_credential_mask !== '' ? ' is-visually-masked' : ''; ?>"
                        value="<?php echo esc_attr($aipkit_credential_input_value); ?>"
                        placeholder="<?php echo esc_attr((string) $aipkit_config['credential_placeholder']); ?>"
                        data-aipkit-provider-credential="<?php echo esc_attr($aipkit_provider); ?>"
                        data-aipkit-credential-name="<?php echo esc_attr((string) $aipkit_config['credential_name']); ?>"
                        data-aipkit-has-credential="<?php echo $aipkit_connected ? 'true' : 'false'; ?>"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        <?php echo $aipkit_credential_mask !== '' ? 'readonly' : ''; ?>
                    />
                    <?php if ($aipkit_is_secret) : ?>
                        <span
                            class="aipkit_settings_provider_credential_mask"
                            data-aipkit-provider-credential-mask
                            aria-hidden="true"
                            <?php echo $aipkit_credential_mask === '' ? 'hidden' : ''; ?>
                        ><?php echo esc_html($aipkit_credential_mask); ?></span>
                    <?php endif; ?>
                </div>

                <div class="aipkit_settings_provider_connected_actions" <?php echo $aipkit_connected ? '' : 'hidden'; ?>>
                    <?php if ($aipkit_config['credential_type'] === 'password') : ?>
                        <button
                            type="button"
                            class="aipkit_settings_icon_button"
                            data-aipkit-provider-reveal
                            data-reveal-label="<?php esc_attr_e('Reveal API key', 'gpt3-ai-content-generator'); ?>"
                            data-hide-label="<?php esc_attr_e('Hide API key', 'gpt3-ai-content-generator'); ?>"
                            aria-label="<?php esc_attr_e('Reveal API key', 'gpt3-ai-content-generator'); ?>"
                            title="<?php esc_attr_e('Reveal API key', 'gpt3-ai-content-generator'); ?>"
                        >
                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($aipkit_has_advanced_settings) : ?>
                        <button
                            type="button"
                            class="aipkit_settings_icon_button"
                            data-aipkit-provider-settings-open="<?php echo esc_attr($aipkit_provider); ?>"
                            <?php /* translators: %s: AI provider display name. */ ?>
                            aria-label="<?php echo esc_attr(sprintf(__('Open %s settings', 'gpt3-ai-content-generator'), (string) $aipkit_config['display_name'])); ?>"
                            title="<?php esc_attr_e('Advanced settings', 'gpt3-ai-content-generator'); ?>"
                        >
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </div>
                <button
                    type="button"
                    class="aipkit_btn aipkit_btn-primary aipkit_settings_provider_connect"
                    data-aipkit-provider-connect="<?php echo esc_attr($aipkit_provider); ?>"
                    <?php echo $aipkit_connected ? 'hidden' : ''; ?>
                >
                    <?php esc_html_e('Connect', 'gpt3-ai-content-generator'); ?>
                </button>
            </div>

            <div
                class="aipkit_settings_provider_error"
                data-aipkit-provider-error
                <?php /* translators: %s: AI provider display name. */ ?>
                data-invalid-message="<?php echo esc_attr(sprintf(__('That key was rejected by %s. Check the key or generate a new one, then reconnect.', 'gpt3-ai-content-generator'), (string) $aipkit_config['display_name'])); ?>"
                <?php /* translators: %s: AI provider display name. */ ?>
                data-sync-message="<?php echo esc_attr(sprintf(__('We could not sync %s. Check the connection settings and try again.', 'gpt3-ai-content-generator'), (string) $aipkit_config['display_name'])); ?>"
                role="alert"
                hidden
            >
                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                <div class="aipkit_settings_provider_error_content">
                    <p data-aipkit-provider-error-message></p>
                    <details class="aipkit_settings_provider_error_details" data-aipkit-provider-error-details hidden>
                        <summary><?php esc_html_e('View details', 'gpt3-ai-content-generator'); ?></summary>
                        <p data-aipkit-provider-error-technical></p>
                    </details>
                </div>
            </div>

            <?php if ($aipkit_model_field) :
                $aipkit_model_field_id = 'aipkit_' . $aipkit_slug . '_' . ($aipkit_provider === 'Azure' ? 'deployment' : 'model');
                ?>
                <div
                    class="aipkit_settings_provider_model_block"
                    data-aipkit-provider-model-block
                    <?php echo $aipkit_connected ? '' : 'hidden'; ?>
                >
                    <label
                        class="aipkit_settings_provider_model_label"
                        for="<?php echo esc_attr($aipkit_model_field_id); ?>_trigger"
                    >
                        <?php echo esc_html((string) $aipkit_model_field['label']); ?>
                    </label>
                    <div class="aipkit_settings_provider_model_control">
                        <div class="aipkit_settings_provider_model_select_row">
                            <select
                                id="<?php echo esc_attr($aipkit_model_field_id); ?>"
                                name="<?php echo esc_attr((string) $aipkit_model_field['name']); ?>"
                                class="aipkit_settings_provider_model_select aipkit_autosave_trigger"
                                data-aipkit-settings-provider-model="<?php echo esc_attr($aipkit_provider); ?>"
                                hidden
                                aria-hidden="true"
                                tabindex="-1"
                            >
                                <?php $aipkit_render_model_options($aipkit_provider, (string) $aipkit_model_field['value']); ?>
                            </select>
                            <?php
                            $aipkit_unified_model_selector_config = [
                                'trigger_id' => $aipkit_model_field_id . '_trigger',
                                'initial_label' => (string) $aipkit_model_field['value'] !== ''
                                    ? (string) $aipkit_model_field['value']
                                    : __('Select model', 'gpt3-ai-content-generator'),
                                'source_id' => $aipkit_model_field_id,
                                'class_name' => 'aipkit_settings_provider_unified_model_selector',
                            ];
                            include dirname(__DIR__, 2) . '/shared/unified-model-selector.php';
                            unset($aipkit_unified_model_selector_config);
                            ?>
                            <button
                                type="button"
                                id="aipkit_sync_<?php echo esc_attr($aipkit_slug); ?>_models"
                                class="aipkit_sync_btn aipkit_settings_compact_sync_btn"
                                data-provider="<?php echo esc_attr($aipkit_provider); ?>"
                                aria-label="<?php esc_attr_e('Sync models', 'gpt3-ai-content-generator'); ?>"
                                title="<?php esc_attr_e('Sync models', 'gpt3-ai-content-generator'); ?>"
                                aria-busy="false"
                            >
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                            </button>
                        </div>
                        <span
                            class="aipkit_settings_provider_model_last_synced"
                            data-aipkit-provider-last-synced="<?php echo esc_attr($aipkit_provider); ?>"
                            data-synced-at="<?php echo esc_attr((string) ($aipkit_model_sync_timestamps[$aipkit_provider] ?? '')); ?>"
                            aria-live="polite"
                            hidden
                        ></span>
                    </div>
                </div>
            <?php endif; ?>

            <a
                class="aipkit_settings_provider_key_link"
                href="<?php echo esc_url((string) $aipkit_config['key_url']); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <?php echo esc_html((string) $aipkit_config['key_link']); ?> <span aria-hidden="true">↗</span>
            </a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php foreach ($aipkit_provider_configs as $aipkit_provider => $aipkit_config) :
    if (!empty($aipkit_config['requires_pro']) && !$is_pro) {
        continue;
    }
    $aipkit_advanced_fields = $aipkit_get_advanced_fields($aipkit_config);
    if (empty($aipkit_advanced_fields)) {
        continue;
    }
    $aipkit_slug = (string) $aipkit_config['slug'];
    $aipkit_modal_title_id = 'aipkit_settings_' . $aipkit_slug . '_modal_title';
    $aipkit_section_labels = [
        'generation' => __('Generation', 'gpt3-ai-content-generator'),
        'endpoint' => __('Endpoint', 'gpt3-ai-content-generator'),
        'retention' => __('Retention and privacy', 'gpt3-ai-content-generator'),
        'safety' => __('Safety', 'gpt3-ai-content-generator'),
        'general' => __('Settings', 'gpt3-ai-content-generator'),
    ];
    $aipkit_grouped_fields = [];
    foreach ($aipkit_advanced_fields as $aipkit_config_field) {
        $aipkit_config_field_id = (string) ($aipkit_config_field['id'] ?? '');

        if (in_array($aipkit_config_field_id, ['temperature', 'top_p'], true)) {
            $aipkit_section_key = 'generation';
        } elseif (
            in_array($aipkit_config_field_id, ['base_url', 'api_version', 'endpoint', 'authoring_version', 'inference_version', 'images_version'], true)
        ) {
            $aipkit_section_key = 'endpoint';
        } elseif (
            in_array($aipkit_config_field_id, ['expiration_policy', 'store_conversation', 'moderation', 'moderation_message'], true)
        ) {
            $aipkit_section_key = 'retention';
        } elseif (strpos($aipkit_config_field_id, 'safety_') === 0) {
            $aipkit_section_key = 'safety';
        } else {
            $aipkit_section_key = 'general';
        }

        $aipkit_grouped_fields[$aipkit_section_key][] = $aipkit_config_field;
    }
    ?>
    <div
        class="aipkit-modal-overlay aipkit_settings_provider_modal"
        id="aipkit_settings_<?php echo esc_attr($aipkit_slug); ?>_modal"
        data-aipkit-provider-modal="<?php echo esc_attr($aipkit_provider); ?>"
        aria-hidden="true"
    >
        <div
            class="aipkit-modal-content aipkit-modal-shell aipkit_settings_provider_modal_content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="<?php echo esc_attr($aipkit_modal_title_id); ?>"
        >
            <div class="aipkit-modal-header aipkit-modal-shell-header aipkit_settings_provider_modal_header">
                <div class="aipkit-modal-shell-intro">
                    <h2 class="aipkit-modal-shell-title" id="<?php echo esc_attr($aipkit_modal_title_id); ?>">
                        <?php /* translators: %s: AI provider display name. */ ?>
                        <?php echo esc_html(sprintf(__('%s settings', 'gpt3-ai-content-generator'), (string) $aipkit_config['display_name'])); ?>
                    </h2>
                </div>
                <button type="button" class="aipkit-modal-close-btn aipkit-modal-shell-close" data-aipkit-provider-modal-close aria-label="<?php esc_attr_e('Close', 'gpt3-ai-content-generator'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>

            <div class="aipkit-modal-body aipkit-modal-shell-body aipkit_settings_provider_modal_body">
                <div class="aipkit_settings_provider_modal_fields">
                    <?php foreach ($aipkit_grouped_fields as $aipkit_section_key => $aipkit_section_fields) : ?>
                        <section class="aipkit_settings_provider_modal_section aipkit_settings_provider_modal_section--<?php echo esc_attr($aipkit_section_key); ?>">
                            <h3 class="aipkit_settings_provider_modal_section_title">
                                <?php echo esc_html((string) ($aipkit_section_labels[$aipkit_section_key] ?? $aipkit_section_labels['general'])); ?>
                            </h3>
                            <div class="aipkit_settings_provider_modal_section_fields">
                    <?php foreach ($aipkit_section_fields as $aipkit_field) :
                        $aipkit_field_id = 'aipkit_' . $aipkit_slug . '_' . (string) $aipkit_field['id'];
                        $aipkit_field_type = (string) $aipkit_field['type'];
                        ?>
                        <div
                            class="aipkit_settings_provider_modal_row"
                            <?php echo !empty($aipkit_field['row_id']) ? 'id="' . esc_attr((string) $aipkit_field['row_id']) . '"' : ''; ?>
                            <?php echo !empty($aipkit_field['hidden']) ? 'hidden' : ''; ?>
                        >
                            <div class="aipkit_settings_provider_modal_copy">
                                <label class="aipkit_settings_provider_modal_label" for="<?php echo esc_attr($aipkit_field_id); ?>"><?php echo esc_html((string) $aipkit_field['label']); ?></label>
                                <span class="aipkit_settings_provider_modal_helper"><?php echo esc_html((string) $aipkit_field['description']); ?></span>
                            </div>
                            <div class="aipkit_settings_provider_modal_control">
                                <?php if ($aipkit_field_type === 'select') : ?>
                                    <select id="<?php echo esc_attr($aipkit_field_id); ?>" name="<?php echo esc_attr((string) $aipkit_field['name']); ?>" class="aipkit_form-input aipkit_autosave_trigger">
                                        <?php foreach ((array) ($aipkit_field['options'] ?? []) as $aipkit_option_value => $aipkit_option_label) : ?>
                                            <option value="<?php echo esc_attr((string) $aipkit_option_value); ?>" <?php selected((string) $aipkit_field['value'], (string) $aipkit_option_value); ?>><?php echo esc_html((string) $aipkit_option_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($aipkit_field_type === 'toggle') : ?>
                                    <label class="aipkit_switch" for="<?php echo esc_attr($aipkit_field_id); ?>">
                                        <input
                                            type="checkbox"
                                            id="<?php echo esc_attr($aipkit_field_id); ?>"
                                            name="<?php echo esc_attr((string) $aipkit_field['name']); ?>"
                                            class="aipkit_autosave_trigger"
                                            value="1"
                                            <?php checked((string) $aipkit_field['value'], '1'); ?>
                                            <?php echo !empty($aipkit_field['controls']) ? 'aria-controls="' . esc_attr((string) $aipkit_field['controls']) . '"' : ''; ?>
                                        />
                                        <span class="aipkit_switch_slider" aria-hidden="true"></span>
                                    </label>
                                <?php else : ?>
                                    <div class="aipkit_settings_provider_modal_input_wrap">
                                        <input
                                            type="<?php echo esc_attr($aipkit_field_type); ?>"
                                            id="<?php echo esc_attr($aipkit_field_id); ?>"
                                            class="aipkit_form-input aipkit_autosave_trigger<?php echo !empty($aipkit_field['monospace']) ? ' is-monospace' : ''; ?>"
                                            value="<?php echo esc_attr((string) $aipkit_field['value']); ?>"
                                            <?php echo !empty($aipkit_field['name']) ? 'name="' . esc_attr((string) $aipkit_field['name']) . '"' : ''; ?>
                                            <?php echo !empty($aipkit_field['global_name']) ? 'data-aipkit-global-setting="' . esc_attr((string) $aipkit_field['global_name']) . '"' : ''; ?>
                                            <?php echo isset($aipkit_field['min']) ? 'min="' . esc_attr((string) $aipkit_field['min']) . '"' : ''; ?>
                                            <?php echo isset($aipkit_field['max']) ? 'max="' . esc_attr((string) $aipkit_field['max']) . '"' : ''; ?>
                                            <?php echo isset($aipkit_field['step']) ? 'step="' . esc_attr((string) $aipkit_field['step']) . '"' : ''; ?>
                                        />
                                        <?php if (!empty($aipkit_field['suffix'])) : ?>
                                            <span class="aipkit_settings_provider_modal_suffix"><?php echo esc_html((string) $aipkit_field['suffix']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($aipkit_field['reset'])) : ?>
                                            <button
                                                type="button"
                                                class="aipkit_settings_icon_button aipkit_settings_provider_reset"
                                                data-aipkit-reset-target="<?php echo esc_attr($aipkit_field_id); ?>"
                                                data-default-value="<?php echo esc_attr((string) ($aipkit_field['default'] ?? '')); ?>"
                                                aria-label="<?php esc_attr_e('Restore default value', 'gpt3-ai-content-generator'); ?>"
                                                title="<?php esc_attr_e('Restore default value', 'gpt3-ai-content-generator'); ?>"
                                            >
                                                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
<?php endforeach; ?>
