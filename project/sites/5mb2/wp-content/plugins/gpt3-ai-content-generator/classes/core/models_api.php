<?php

namespace WPAICG\Core; // *** Correct namespace ***

use WP_Error;
use WPAICG\AIPKit_Providers; // For accessing settings
use WPAICG\Core\Providers\ProviderStrategyFactory; // *** Use the factory from Core\Providers ***

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Models_API
 *
 * Manages fetching and formatting model/deployment lists from various AI providers
 * using the Provider Strategy pattern.
 */
class AIPKit_Models_API {

    /**
     * Fetches models or deployments from the provider using its strategy.
     *
     * @param string $provider 'OpenAI','OpenRouter','Google','Azure'.
     * @param array $api_params Contains API key, base_url, api_version, endpoints etc. from provider settings.
     * @return array|WP_Error List of models/deployments [['id' => ..., 'name' => ...]] or WP_Error.
     */
    public static function get_models($provider, $api_params = []) {
        $strategy = ProviderStrategyFactory::get_strategy($provider); // *** Use correct namespace ***
        if (is_wp_error($strategy)) {
            return $strategy;
        }

        // Delegate model fetching to the strategy
        return $strategy->get_models($api_params);
    }


    /**
     * Group OpenAI models into categories for UI.
     * This remains here as it's primarily UI-related grouping.
     */
    public static function group_openai_models($models) {
         $groups = [
            'gpt-5 models'      => [],
            'gpt-4 models'      => [],
            'gpt-3.5 models'    => [],
            'fine-tuned models' => [],
            'o1 models'         => [],
            'o3 models'         => [],
            'o4 models'         => [],
            'others'            => [],
        ];
        if (empty($models) || !is_array($models)) {
            return $groups;
        }
        foreach ($models as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;

            $idLower = strtolower($id);
            // OpenAI model grouping doesn't typically rely on 'owned_by' as much as it used to.
            // Rely more on naming conventions.

             // Grouping logic based on ID prefixes/contents
             if (strpos($id, 'ft:') === 0 || strpos($id, ':ft-') !== false) { // More robust fine-tune check
                $groups['fine-tuned models'][] = $model;
            } elseif (strpos($idLower, 'gpt-5') !== false) {
                $groups['gpt-5 models'][] = $model;
            } elseif (strpos($idLower, 'gpt-4') !== false) { // All GPT-4 variants (4o, turbo, vision, etc.)
                $groups['gpt-4 models'][] = $model;
            } elseif (strpos($idLower, 'gpt-3.5') !== false) {
                $groups['gpt-3.5 models'][] = $model;
            } elseif (strpos($idLower, 'o1') !== false) {
                $groups['o1 models'][] = $model;
            } elseif (strpos($idLower, 'o3') !== false) {
                $groups['o3 models'][] = $model;
            } elseif (strpos($idLower, 'o4') !== false) {
                $groups['o4 models'][] = $model;
            } else {
                $groups['others'][] = $model; // Catch-all for unexpected future models
            }
        }
        // Clean empty groups
        $groups = array_filter($groups);

        // Sort each group by ID (descending for newer models maybe?)
        foreach ($groups as &$items) {
            usort($items, fn($a, $b) => strcmp($b['id'], $a['id'])); // Sort descending by ID
        }
        unset($items);

        // Ensure specific order of groups
        $ordered_groups = [];
        $order = ['o1 models', 'o3 models', 'o4 models', 'gpt-3.5 models', 'gpt-4 models', 'gpt-5 models', 'fine-tuned models', 'others'];
        foreach($order as $key) {
            if (isset($groups[$key])) {
                $ordered_groups[$key] = $groups[$key];
            }
        }

        return $ordered_groups;
    }

}