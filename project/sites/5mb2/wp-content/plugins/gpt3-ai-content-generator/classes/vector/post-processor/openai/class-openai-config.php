<?php

namespace WPAICG\Vector\PostProcessor\OpenAI;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles fetching OpenAI API configuration for post processing.
 */
class OpenAIConfig {

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_config() {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) require_once $providers_path;
            else return new WP_Error('dependency_missing_config_openai', 'AIPKit_Providers class not found for OpenAI config.');
        }
        $openai_data = AIPKit_Providers::get_provider_data('OpenAI');
        if (empty($openai_data['api_key'])) {
            return new WP_Error('missing_openai_key_config', __('OpenAI API Key is not configured in global settings.', 'gpt3-ai-content-generator'));
        }
        return [
            'api_key'     => $openai_data['api_key'],
            'base_url'    => $openai_data['base_url'] ?? 'https://api.openai.com',
            'api_version' => $openai_data['api_version'] ?? 'v1',
        ];
    }
}