<?php

namespace WPAICG\Images\Providers\Google;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles formatting request payloads for Google Video Generation models (Veo 3).
 */
class GoogleVideoPayloadFormatter {

    /**
     * Formats the payload for Google Video Generation API.
     *
     * @param string $prompt The text prompt.
     * @param array  $options Generation options including 'model' (full ID), 'aspect_ratio', 'negative_prompt', etc.
     * @return array The formatted request body data.
     */
    public static function format(string $prompt, array $options): array {
        $model_id = $options['model'] ?? '';
        $payload = [];

        // Build instances; many video models accept prompt-only instances
        $instance = [ 'prompt' => $prompt ];
        $payload = [ 'instances' => [$instance] ];

        // Optional parameters, applied generically. Models may ignore unknowns.
        $parameters = [];
        if (isset($options['aspect_ratio'])) {
            $parameters['aspectRatio'] = $options['aspect_ratio'];
        }
        if (isset($options['negative_prompt']) && !empty($options['negative_prompt'])) {
            $parameters['negativePrompt'] = $options['negative_prompt'];
        }
        if (isset($options['person_generation'])) {
            $parameters['personGeneration'] = $options['person_generation'];
        }
        if (!empty($parameters)) {
            $payload['parameters'] = $parameters;
        }
        
        return $payload;
    }
} 
