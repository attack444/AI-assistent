<?php

if (! defined('ABSPATH')) exit;

$options_model = new \AiBuddy\Models();
$models_list = $options_model->get_models_list();
$general_setting = get_option('ai_buddy', array());
$api_keys = \AiBuddy\ApiManager::getAvailableApiKeys();
$saved_model = isset($general_setting['fse']['model']) ? $general_setting['fse']['model'] : false;
$default_model = \AiBuddy\ApiManager::getDefaultModel($saved_model);

foreach ($models_list as $brand => $models) {
?>
    <optgroup label="<?php echo esc_attr($brand); ?>">
        <?php
        foreach ($models as $model => $text) {
            $providerKey = '';
            switch ($brand) {
                case 'OpenAI':
                    $providerKey = \AiBuddy\ApiManager::PROVIDER_OPENAI;
                    break;
                case 'Google':
                    $providerKey = \AiBuddy\ApiManager::PROVIDER_GOOGLE;
                    break;
                case 'Claude':
                    $providerKey = \AiBuddy\ApiManager::PROVIDER_CLAUDE;
                    break;
                case 'OpenRouter':
                    $providerKey = \AiBuddy\ApiManager::PROVIDER_OPENROUTER;
                    break;
            }
            
            $is_enabled = \AiBuddy\ApiManager::hasProviderApiKey($providerKey);
            $error_message = '';
            
            if (!$is_enabled) {
                $error_message = __(' (API Key not entered)', 'aibuddy-openai-chatgpt');
            }
        ?>
            <option value="<?php echo esc_attr($model); ?>" 
                    <?php echo $is_enabled ? '' : 'disabled'; ?>
                    <?php echo $default_model === $model ? 'selected' : ''; ?>>
                <?php echo esc_html($text) . esc_html($error_message); ?>
            </option>
        <?php
        }
        ?>
    </optgroup>
<?php
}
