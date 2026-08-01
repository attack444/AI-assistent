<?php

namespace WPAICG\Vector\PostProcessor\Chroma;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles fetching Chroma API configuration for post processing.
 */
class ChromaConfig
{
    /**
     * @return mixed[]|\WP_Error
     */
    public function get_config()
    {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return new WP_Error('dependency_missing_config_chroma', 'AIPKit_Providers class not found for Chroma config.');
            }
        }

        $chroma_data = AIPKit_Providers::get_provider_data('Chroma');
        if (empty($chroma_data['url'])) {
            return new WP_Error('missing_chroma_url_config', __('Chroma URL is not configured in global settings.', 'gpt3-ai-content-generator'));
        }

        return [
            'url' => $chroma_data['url'],
            'api_key' => $chroma_data['api_key'] ?? '',
            'tenant' => $chroma_data['tenant'] ?? 'default_tenant',
            'database' => $chroma_data['database'] ?? 'default_database',
        ];
    }
}
