<?php

namespace AiBuddy;

/**
 * Class to handle API key and model management across different providers
 */
class ApiManager {
    // Provider keys and corresponding brand names
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_GOOGLE = 'googleai';
    const PROVIDER_CLAUDE = 'claude';
    const PROVIDER_OPENROUTER = 'openrouter';
    
    const BRAND_OPENAI = 'OpenAI';
    const BRAND_GOOGLE = 'Google';
    const BRAND_CLAUDE = 'Claude';
    const BRAND_OPENROUTER = 'OpenRouter';
    
    /**
     * Get all available API keys from settings
     *
     * @return array Associative array of API key availability
     */
    public static function getAvailableApiKeys() {
        $settings = get_option('ai_buddy', array());
        
        return [
            self::PROVIDER_OPENAI => !empty($settings[self::PROVIDER_OPENAI]['apikey']),
            self::PROVIDER_GOOGLE => !empty($settings[self::PROVIDER_GOOGLE]['apikey']),
            self::PROVIDER_CLAUDE => !empty($settings[self::PROVIDER_CLAUDE]['apikey']),
            self::PROVIDER_OPENROUTER => !empty($settings[self::PROVIDER_OPENROUTER]['apikey'])
        ];
    }
    
    /**
     * Check if any API key is available
     *
     * @return bool True if at least one API key is available
     */
    public static function hasAnyApiKey() {
        $keys = self::getAvailableApiKeys();
        return in_array(true, $keys);
    }
    
    /**
     * Check if a specific provider's API key is available
     *
     * @param string $provider Provider identifier
     * @return bool True if the provider's API key is available
     */
    public static function hasProviderApiKey($provider) {
        $keys = self::getAvailableApiKeys();
        return isset($keys[$provider]) && $keys[$provider];
    }
    
    /**
     * Get the API key for a specific provider
     *
     * @param string $provider Provider identifier
     * @return string|null The API key or null if not available
     */
    public static function getApiKey($provider) {
        $settings = get_option('ai_buddy', array());
        
        if (isset($settings[$provider]['apikey']) && !empty($settings[$provider]['apikey'])) {
            return $settings[$provider]['apikey'];
        }
        
        return null;
    }
    
    /**
     * Determine which provider a model belongs to
     *
     * @param string $model Model identifier
     * @return string|null Provider identifier or null if unknown
     */
    public static function getProviderForModel($model) {
        if (strpos($model, 'gpt-') === 0 || strpos($model, 'chatgpt-') === 0) {
            return self::PROVIDER_OPENAI;
        } elseif (strpos($model, 'gemini-') === 0) {
            return self::PROVIDER_GOOGLE;
        } elseif (strpos($model, 'claude-') === 0) {
            return self::PROVIDER_CLAUDE;
        } elseif (strpos($model, '/') !== false) {
            return self::PROVIDER_OPENROUTER;
        }
        
        return null;
    }
    
    /**
     * Check if the required API key for a model is available
     *
     * @param string $model Model identifier
     * @return bool True if the required API key is available
     */
    public static function isModelKeyAvailable($model) {
        $provider = self::getProviderForModel($model);
        
        if ($provider === null) {
            return false;
        }
        
        return self::hasProviderApiKey($provider);
    }
    
    /**
     * Get the default model based on available API keys
     * 
     * @param string|null $saved_model Previously saved model if available
     * @return string The model to use as default
     */
    public static function getDefaultModel($saved_model = null) {
        $api_keys = self::getAvailableApiKeys();
        $models_list = (new Models())->get_models_list();
        
        // First check if saved model is valid with current API keys
        if ($saved_model && self::isModelKeyAvailable($saved_model)) {
            return $saved_model;
        }
        
        // If no valid saved model, select best available model
        // Priority order: Google, Claude, OpenAI, OpenRouter
        if ($api_keys[self::PROVIDER_GOOGLE]) {
            return self::getLatestModelFromList($models_list, self::BRAND_GOOGLE);
        } elseif ($api_keys[self::PROVIDER_CLAUDE]) {
            return self::getLatestModelFromList($models_list, self::BRAND_CLAUDE);
        } elseif ($api_keys[self::PROVIDER_OPENAI]) {
            return self::getLatestModelFromList($models_list, self::BRAND_OPENAI);
        } elseif ($api_keys[self::PROVIDER_OPENROUTER]) {
            return self::getLatestModelFromList($models_list, self::BRAND_OPENROUTER);
        }
        
        // Fallback if no keys available
        return self::getLatestModelFromList($models_list, self::BRAND_OPENAI);
    }
    
    /**
     * Get the latest model from the list for a specific provider
     * 
     * @param array $models_list Full list of models
     * @param string $provider The provider to get the latest model for
     * @return string Model identifier
     */
    public static function getLatestModelFromList($models_list, $provider) {
        if (!isset($models_list[$provider]) || empty($models_list[$provider])) {
            return 'gpt-4o'; // Fallback
        }
        
        $provider_models = $models_list[$provider];
        
        // Special handling for OpenRouter - look for OpenAI models
        if ($provider === self::BRAND_OPENROUTER) {
            // Look for the latest OpenAI models through OpenRouter
            foreach (array_keys($provider_models) as $model) {
                // Check for gpt-4o models first
                if (strpos($model, 'openai/gpt-4o') === 0) {
                    return $model;
                }
            }
        }
        
        // For all other providers, the models are already organized with the latest ones at the top
        // Just grab the first key from the provider's models array
        return array_key_first($provider_models);
    }
} 