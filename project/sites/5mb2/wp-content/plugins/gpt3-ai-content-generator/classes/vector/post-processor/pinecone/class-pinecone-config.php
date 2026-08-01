<?php

namespace WPAICG\Vector\PostProcessor\Pinecone;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles fetching Pinecone API configuration for post processing.
 */
class PineconeConfig {

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_config() {
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) require_once $providers_path;
            else return new WP_Error('dependency_missing_config_pinecone', 'AIPKit_Providers class not found for Pinecone config.');
        }
        $pinecone_data = AIPKit_Providers::get_provider_data('Pinecone');
        if (empty($pinecone_data['api_key'])) {
            return new WP_Error('missing_pinecone_key_config', __('Pinecone API Key is not configured in global settings.', 'gpt3-ai-content-generator'));
        }
        return ['api_key' => $pinecone_data['api_key']];
    }
}