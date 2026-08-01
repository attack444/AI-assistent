<?php

namespace WPAICG\Core\Providers;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory for creating AI Provider Strategy instances.
 * Uses singleton pattern for instances.
 * ADDED: DeepSeek case.
 * MODIFIED: Updated OpenAI strategy path.
 * MODIFIED: Updated Azure strategy path to point to bootstrap.
 * MODIFIED: Updated OpenRouter strategy path to point to bootstrap.
 * MODIFIED: Updated Google strategy path to point to bootstrap.
 */
class ProviderStrategyFactory {

    /** @var array<string, ProviderStrategyInterface> */
    private static $instances = [];

    /**
     * Get the strategy instance for a given provider.
     *
     * @param string $provider Provider name ('OpenAI', 'OpenRouter', 'Google', 'Azure', 'DeepSeek', 'xAI').
     * @return ProviderStrategyInterface|WP_Error The strategy instance or WP_Error if unsupported.
     */
    public static function get_strategy(string $provider) {
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        $strategy_path_base = __DIR__ . '/'; // This will be classes/core/providers/
        $strategies_to_load = [
            'OpenAI'     => 'openai/bootstrap-provider-strategy.php',
            'OpenRouter' => 'openrouter/bootstrap-provider-strategy.php',
            'Google'     => 'google/bootstrap-provider-strategy.php', // MODIFIED PATH
            'Azure'      => 'azure/bootstrap-provider-strategy.php',
            'Claude'     => 'claude/bootstrap-provider-strategy.php',
            'DeepSeek'   => 'deepseek-provider-strategy.php',
            'xAI'        => 'xai/bootstrap-provider-strategy.php',
            'Ollama'     => 'ollama/bootstrap-provider-strategy.php',
        ];

        // Ensure the Interface and Base Class are loaded before any specific strategy.
        // This is also handled by ProviderDependenciesLoader, but as a safeguard:
        if (!interface_exists(ProviderStrategyInterface::class)) {
            $interface_path = $strategy_path_base . 'interface-provider-strategy.php';
            if (file_exists($interface_path)) require_once $interface_path;
            else return new WP_Error('strategy_interface_missing', 'ProviderStrategyInterface is missing.');
        }
        if (!class_exists(BaseProviderStrategy::class)) {
            $base_class_path = $strategy_path_base . 'base-provider-strategy.php';
            if (file_exists($base_class_path)) require_once $base_class_path;
            else return new WP_Error('base_strategy_class_missing', 'BaseProviderStrategy class is missing.');
        }


        if (isset($strategies_to_load[$provider])) {
             $strategy_file = $strategy_path_base . $strategies_to_load[$provider];
             if (file_exists($strategy_file)) {
                 require_once $strategy_file;
             } else {
                 /* translators: %s: The name of the AI provider (e.g., 'OpenAI'). */
                 return new WP_Error('strategy_file_not_found', sprintf(__('Strategy file not found for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
             }
        } else {
             /* translators: %s: The name of the AI provider. */
             return new WP_Error('unsupported_provider_strategy', sprintf(__('Provider strategy for "%s" is not defined in loader map.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        switch ($provider) {
            case 'OpenAI':
                if (class_exists(OpenAIProviderStrategy::class)) {
                    self::$instances[$provider] = new OpenAIProviderStrategy();
                }
                break;
            case 'OpenRouter':
                 if (class_exists(OpenRouterProviderStrategy::class)) {
                    self::$instances[$provider] = new OpenRouterProviderStrategy();
                 }
                break;
            case 'Google':
                 if (class_exists(GoogleProviderStrategy::class)) {
                    self::$instances[$provider] = new GoogleProviderStrategy();
                 }
                break;
            case 'Azure':
                 if (class_exists(AzureProviderStrategy::class)) { 
                    self::$instances[$provider] = new AzureProviderStrategy();
                 }
                 break;
            case 'Claude':
                 if (class_exists(ClaudeProviderStrategy::class)) {
                    self::$instances[$provider] = new ClaudeProviderStrategy();
                 }
                 break;
             case 'DeepSeek':
                 if (class_exists(DeepSeekProviderStrategy::class)) {
                     self::$instances[$provider] = new DeepSeekProviderStrategy();
                 }
                 break;
            case 'xAI':
                if (class_exists(XAIProviderStrategy::class)) {
                    self::$instances[$provider] = new XAIProviderStrategy();
                }
                break;
            case 'Ollama':
                if (class_exists(AIPKit_Ollama_Strategy::class)) {
                    self::$instances[$provider] = new AIPKit_Ollama_Strategy();
                }
                break;
            default:
                /* translators: %s: The name of the AI provider. */
                return new WP_Error('unsupported_provider_strategy', sprintf(__('Provider strategy "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        if (!isset(self::$instances[$provider])) {
            /* translators: %s: The name of the AI provider. */
            return new WP_Error('strategy_instantiation_failed', sprintf(__('Failed to load strategy for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        return self::$instances[$provider];
    }
}
