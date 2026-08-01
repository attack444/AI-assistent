<?php

namespace WPAICG\STT; // Use new STT namespace

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory for creating Speech-to-Text Provider Strategy instances.
 * Uses singleton pattern for instances.
 */
class AIPKit_STT_Provider_Strategy_Factory {

    /** @var array<string, AIPKit_STT_Provider_Strategy_Interface> */
    private static $instances = [];

    /**
     * Get the strategy instance for a given STT provider.
     *
     * @param string $provider Provider name ('OpenAI', 'Azure').
     * @return AIPKit_STT_Provider_Strategy_Interface|WP_Error The strategy instance or WP_Error if unsupported.
     */
    public static function get_strategy(string $provider) {
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        if (
            class_exists(AIPKit_Providers::class)
            && !AIPKit_Providers::provider_supports_capability($provider, 'stt')
        ) {
            return new WP_Error(
                'stt_provider_not_supported',
                sprintf(
                    /* translators: %s: The provider name. */
                    __('Speech-to-text is not supported by %s in this integration.', 'gpt3-ai-content-generator'),
                    esc_html($provider)
                ),
                ['status' => 501]
            );
        }

        $strategy_path_base = __DIR__ . '/';
        $strategies_to_load = [
            'OpenAI'     => 'class-aipkit-stt-openai-provider-strategy.php',
            'Azure'      => 'class-aipkit-stt-azure-provider-strategy.php', // Added Azure strategy file
        ];

        // Load required class file if it exists
        if (isset($strategies_to_load[$provider])) {
             $strategy_file = $strategy_path_base . $strategies_to_load[$provider];
             if (file_exists($strategy_file)) {
                 // Ensure base class and interface are loaded first
                 $base_path = $strategy_path_base . 'class-aipkit-stt-base-provider-strategy.php';
                 $interface_path = $strategy_path_base . 'interface-aipkit-stt-provider-strategy.php';
                 if (file_exists($interface_path) && !interface_exists(AIPKit_STT_Provider_Strategy_Interface::class)) require_once $interface_path;
                 if (file_exists($base_path) && !class_exists(AIPKit_STT_Base_Provider_Strategy::class)) require_once $base_path;

                 require_once $strategy_file;
             } else {
                 /* translators: %s: The provider name. */
                 return new WP_Error('stt_strategy_file_not_found', sprintf(__('STT Strategy file not found for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
             }
        }

        // Instantiate the strategy
        $class_name = null;
        switch ($provider) {
            case 'OpenAI':     $class_name = AIPKit_STT_OpenAI_Provider_Strategy::class; break;
            case 'Azure':      $class_name = AIPKit_STT_Azure_Provider_Strategy::class; break; // Added Azure class name
            default:
                /* translators: %s: The provider name. */
                return new WP_Error('unsupported_stt_provider_strategy', sprintf(__('STT Provider strategy "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        if (class_exists($class_name)) {
            self::$instances[$provider] = new $class_name();
        } else {
            /* translators: %s: The provider name. */
            return new WP_Error('stt_strategy_instantiation_failed', sprintf(__('Failed to load STT strategy for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        return self::$instances[$provider];
    }
}
