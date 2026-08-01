<?php


namespace WPAICG\Images;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory for creating Image Generation Provider Strategy instances.
 * Uses singleton pattern for instances.
 * Ensures relevant sub-components are loaded when a strategy is requested.
 */
class AIPKit_Image_Provider_Strategy_Factory
{
    /** @var array<string, AIPKit_Image_Provider_Strategy_Interface> */
    private static $instances = [];

    /**
     * Get the strategy instance for a given Image Generation provider.
     *
     * @param string $provider Provider name ('OpenAI', 'Azure', 'Replicate', 'Google').
     * @return AIPKit_Image_Provider_Strategy_Interface|WP_Error The strategy instance or WP_Error if unsupported.
     */
    public static function get_strategy(string $provider)
    {
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        if (
            class_exists(AIPKit_Providers::class)
            && !AIPKit_Providers::provider_supports_capability($provider, 'image_generation')
        ) {
            return new WP_Error(
                'image_provider_not_supported',
                sprintf(
                    /* translators: %s: The provider name. */
                    __('Image generation is not supported by %s in this integration.', 'gpt3-ai-content-generator'),
                    esc_html($provider)
                ),
                ['status' => 501]
            );
        }

        $strategy_path_base = __DIR__ . '/providers/'; // Base path for strategy classes

        $strategies_to_load = [
            'OpenAI'          => 'class-aipkit-image-openai-provider-strategy.php',
            'Azure' => 'class-aipkit-image-azure-provider-strategy.php', 'Google'          => 'class-aipkit-image-google-provider-strategy.php',
            'OpenRouter'      => 'class-aipkit-image-openrouter-provider-strategy.php',
            'xAI'             => 'class-aipkit-image-xai-provider-strategy.php',
            'Pexels'          => 'class-aipkit-image-pexels-provider-strategy.php',
            'Pixabay'         => 'class-aipkit-image-pixabay-provider-strategy.php',
            'Replicate'       => 'class-aipkit-image-replicate-provider-strategy.php',
        ];

        // Load required class file if it exists
        if (isset($strategies_to_load[$provider])) {
            $strategy_file = $strategy_path_base . $strategies_to_load[$provider];
            if (file_exists($strategy_file)) {
                // Ensure base class and interface are loaded first
                $base_path = __DIR__ . '/class-aipkit-image-base-provider-strategy.php';
                $interface_path = __DIR__ . '/interface-aipkit-image-provider-strategy.php';
                if (file_exists($interface_path) && !interface_exists(__NAMESPACE__ . '\AIPKit_Image_Provider_Strategy_Interface')) {
                    require_once $interface_path;
                }
                if (file_exists($base_path) && !class_exists(__NAMESPACE__ . '\AIPKit_Image_Base_Provider_Strategy')) {
                    require_once $base_path;
                }

                require_once $strategy_file; // Load the strategy class itself
            } else {
                /* translators: %s: The provider name that was attempted to be used for image generation. */
                return new WP_Error('image_strategy_file_not_found', sprintf(__('Image Strategy file not found for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
            }
        } else {
            /* translators: %s: The provider key that was attempted to be used for image generation. */
            return new WP_Error('unsupported_image_provider_key', sprintf(__('Provider key "%s" is not configured for image strategy loading.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        // Instantiate the strategy
        $class_name = null;
        switch ($provider) {
            // Use fully qualified class names with the Providers sub-namespace
            case 'OpenAI':          $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_OpenAI_Provider_Strategy';
                break;
            case 'Azure': $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_Azure_Provider_Strategy';
                break;
            case 'Google':          $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_Google_Provider_Strategy';
                break;
            case 'OpenRouter':      $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_OpenRouter_Provider_Strategy';
                break;
            case 'xAI':             $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_XAI_Provider_Strategy';
                break;
            case 'Pexels':          $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_Pexels_Provider_Strategy';
                break;
            case 'Pixabay':         $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_Pixabay_Provider_Strategy';
                break;
            case 'Replicate':       $class_name = __NAMESPACE__ . '\Providers\AIPKit_Image_Replicate_Provider_Strategy';
                break;
            default:
                /* translators: %s: The provider name that was attempted to be used for image generation. */
                return new WP_Error('unsupported_image_provider_strategy', sprintf(__('Image Generation Provider strategy "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        if (class_exists($class_name)) {
            self::$instances[$provider] = new $class_name();
        } else {
            /* translators: %s: The provider name that was attempted to be used for image generation. */
            return new WP_Error('image_strategy_instantiation_failed', sprintf(__('Failed to load Image Generation strategy for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        return self::$instances[$provider];
    }
}
