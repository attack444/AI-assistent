<?php

namespace WPAICG\Chat\Core\AIService;

use WPAICG\AIPKit_Providers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for determining the AI provider and model for a chat interaction.
 * This function was extracted from AIService::_determine_provider_model.
 * It can be called statically.
 *
 * @param \WPAICG\Chat\Core\AIService|null $serviceInstance The instance of the AIService class (can be null if called statically).
 * @param array $bot_settings Settings for the specific bot.
 * @return array ['provider' => string, 'model' => string]
 */
function determine_provider_model(?\WPAICG\Chat\Core\AIService $serviceInstance, array $bot_settings): array {
    $provider = !empty($bot_settings['provider']) ? $bot_settings['provider'] : null;
    $model = !empty($bot_settings['model']) ? $bot_settings['model'] : null;

    if (empty($provider) || empty($model)) {
        // Ensure AIPKit_Providers class is available
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return ['provider' => 'OpenAI', 'model' => '']; // Fallback
            }
        }
        $global_config = \WPAICG\AIPKit_Providers::get_default_provider_config();
        if (empty($provider)) $provider = $global_config['provider'];
        if (empty($model)) $model = $global_config['model'];
    }
    return ['provider' => $provider ?: 'OpenAI', 'model' => $model ?: ''];
}