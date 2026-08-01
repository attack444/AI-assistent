<?php

namespace WPAICG\Vector\PostProcessor\Qdrant;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles fetching Qdrant API configuration for post processing.
 */
class QdrantConfig {

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_config() {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) require_once $providers_path;
            else return new WP_Error('dependency_missing_config_qdrant', 'AIPKit_Providers class not found for Qdrant config.');
        }
        $qdrant_data = AIPKit_Providers::get_provider_data('Qdrant');
        if (empty($qdrant_data['url'])) {
            return new WP_Error('missing_qdrant_url_config', __('Qdrant URL is not configured in global settings.', 'gpt3-ai-content-generator'));
        }
        if (empty($qdrant_data['api_key'])) {
            return new WP_Error('missing_qdrant_api_key_config', __('Qdrant API Key is not configured in global settings (required for Qdrant Cloud).', 'gpt3-ai-content-generator'));
        }
        return ['url' => $qdrant_data['url'], 'api_key' => $qdrant_data['api_key']];
    }
}