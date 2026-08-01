<?php
// MODIFIED FILE - Ensure OpenAI strategy class is loaded

namespace WPAICG\Speech;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory for creating Text-to-Speech Provider Strategy instances.
 * Uses singleton pattern for instances.
 */
class AIPKit_TTS_Provider_Strategy_Factory {

    /** @var array<string, AIPKit_TTS_Provider_Strategy_Interface> */
    private static $instances = [];

    /**
     * Get the strategy instance for a given TTS provider.
     *
     * @param string $provider Provider name ('OpenAI', 'Google', 'ElevenLabs').
     * @return AIPKit_TTS_Provider_Strategy_Interface|WP_Error The strategy instance or WP_Error if unsupported.
     */
    public static function get_strategy(string $provider) {
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        if (
            class_exists(AIPKit_Providers::class)
            && !AIPKit_Providers::provider_supports_capability($provider, 'tts')
        ) {
            return new WP_Error(
                'tts_provider_not_supported',
                sprintf(
                    /* translators: %s: The provider name. */
                    __('Text-to-speech is not supported by %s in this integration.', 'gpt3-ai-content-generator'),
                    esc_html($provider)
                ),
                ['status' => 501]
            );
        }

        $strategy_path_base = __DIR__ . '/';
        $strategies_to_load = [
            'OpenAI'     => 'class-aipkit-tts-openai-provider-strategy.php', // Ensure this is the correct filename
            'Google'     => 'class-aipkit-tts-google-provider-strategy.php',
            'ElevenLabs' => 'class-aipkit-tts-elevenlabs-provider-strategy.php',
        ];

        // Load required class file if it exists
        if (isset($strategies_to_load[$provider])) {
             $strategy_file = $strategy_path_base . $strategies_to_load[$provider];
             if (file_exists($strategy_file)) {
                 // Ensure base class and interface are loaded first (if not autoloaded)
                 $base_path = $strategy_path_base . 'class-aipkit-tts-base-provider-strategy.php';
                 $interface_path = $strategy_path_base . 'interface-aipkit-tts-provider-strategy.php';
                 if (file_exists($interface_path) && !interface_exists(AIPKit_TTS_Provider_Strategy_Interface::class)) require_once $interface_path;
                 if (file_exists($base_path) && !class_exists(AIPKit_TTS_Base_Provider_Strategy::class)) require_once $base_path;

                 require_once $strategy_file;
             } else {
                 /* translators: %s: The provider name that was attempted to be used for TTS generation. */
                 return new WP_Error('tts_strategy_file_not_found', sprintf(__('TTS Strategy file not found for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
             }
        }

        // Instantiate the strategy (Ensure classes use the correct namespace)
        $class_name = null;
        switch ($provider) {
            case 'OpenAI':     $class_name = AIPKit_TTS_OpenAI_Provider_Strategy::class; break; // Correct class name
            case 'Google':     $class_name = AIPKit_TTS_Google_Provider_Strategy::class; break;
            case 'ElevenLabs': $class_name = AIPKit_TTS_ElevenLabs_Provider_Strategy::class; break;
            default:
                /* translators: %s: The provider name that was attempted to be used for TTS generation. */
                return new WP_Error('unsupported_tts_provider_strategy', sprintf(__('TTS Provider strategy "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        if (class_exists($class_name)) {
            self::$instances[$provider] = new $class_name();
        } else {
            /* translators: %s: The provider name that was attempted to be used for TTS generation. */
            return new WP_Error('tts_strategy_instantiation_failed', sprintf(__('Failed to load TTS strategy for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        return self::$instances[$provider];
    }
}
