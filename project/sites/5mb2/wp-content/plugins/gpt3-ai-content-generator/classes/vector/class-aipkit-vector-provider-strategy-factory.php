<?php

namespace WPAICG\Vector;

use WPAICG\AIPKit_Providers;
use WP_Error;
// Use statement for Qdrant is no longer direct, it's via bootstrap
// use WPAICG\Vector\Providers\AIPKit_Vector_Qdrant_Strategy;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory for creating Vector Store Provider Strategy instances.
 */
class AIPKit_Vector_Provider_Strategy_Factory {

    /** @var array<string, AIPKit_Vector_Provider_Strategy_Interface> */
    private static $instances = [];

    /**
     * Get the strategy instance for a given Vector Store provider.
     *
     * @param string $provider Provider name (e.g., 'Pinecone', 'Qdrant', 'OpenAI', 'Chroma').
     * @return AIPKit_Vector_Provider_Strategy_Interface|WP_Error The strategy instance or WP_Error if unsupported.
     */
    public static function get_strategy(string $provider) {
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        if (
            class_exists(AIPKit_Providers::class)
            && !AIPKit_Providers::provider_supports_capability($provider, 'vector_stores')
        ) {
            return new WP_Error(
                'vector_provider_not_supported',
                sprintf(
                    /* translators: %s: The provider name. */
                    __('Vector stores are not supported by %s in this integration.', 'gpt3-ai-content-generator'),
                    esc_html($provider)
                ),
                ['status' => 501]
            );
        }

        $strategy_path_base = __DIR__ . '/providers/';
        $strategies_map = [
            'Pinecone' => 'pinecone/bootstrap.php',
            'Qdrant'   => 'qdrant/bootstrap.php', // MODIFIED: Point to bootstrap for Qdrant
            'OpenAI'   => 'openai/bootstrap.php',
            'Chroma'   => 'chroma/bootstrap.php',
        ];

        if (!isset($strategies_map[$provider])) {
            /* translators: %s is the vector store provider name */
            return new WP_Error('unsupported_vector_provider', sprintf(__('Vector store provider "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        $strategy_file = $strategy_path_base . $strategies_map[$provider];
        $class_name = null;

        switch ($provider) {
            case 'Pinecone':
                $class_name = \WPAICG\Vector\Providers\AIPKit_Vector_Pinecone_Strategy::class;
                break;
            case 'Qdrant':
                $class_name = \WPAICG\Vector\Providers\AIPKit_Vector_Qdrant_Strategy::class; // Use namespaced class name
                break;
            case 'OpenAI':
                $class_name = \WPAICG\Vector\Providers\AIPKit_Vector_OpenAI_Strategy::class;
                break;
            case 'Chroma':
                $class_name = \WPAICG\Vector\Providers\AIPKit_Vector_Chroma_Strategy::class;
                break;
            default:
            /* translators: %s is the vector store provider name */
                return new WP_Error('unsupported_vector_provider_strategy', sprintf(__('Vector store provider strategy "%s" is not supported.', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        $interface_path = __DIR__ . '/interface-aipkit-vector-provider-strategy.php';
        $base_class_path = __DIR__ . '/class-aipkit-vector-base-provider-strategy.php';

        if (file_exists($interface_path) && !interface_exists(AIPKit_Vector_Provider_Strategy_Interface::class)) {
            require_once $interface_path;
        }
        if (file_exists($base_class_path) && !class_exists(AIPKit_Vector_Base_Provider_Strategy::class)) {
            require_once $base_class_path;
        }

        if (!class_exists($class_name)) {
            if (file_exists($strategy_file)) {
                require_once $strategy_file;
            } else {
                /* translators: %s is the vector store provider name */
                return new WP_Error('vector_strategy_file_not_found', sprintf(__('Vector Strategy file not found for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
            }
        }
        
        if (class_exists($class_name)) {
            self::$instances[$provider] = new $class_name();
        } else {
            /* translators: %s is the vector store provider name */
            return new WP_Error('vector_strategy_instantiation_failed', sprintf(__('Failed to load Vector Store strategy for provider: %s', 'gpt3-ai-content-generator'), esc_html($provider)));
        }

        return self::$instances[$provider];
    }
}
