<?php

namespace WPAICG\Images\Providers\OpenAI;

use WPAICG\AIPKit_Providers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles parsing responses from the OpenAI Image Generation API.
 */
class OpenAIImageResponseParser {

    // Token cost estimates for OpenAI image generation (when API doesn't provide usage data)
    const OPENAI_LEGACY_IMAGE_TOKENS_PER_IMAGE = 2000;
    const GPT_IMAGE_TOKENS_PER_IMAGE = 2500;

    /**
     * Parses the successful response from OpenAI Image Generation API.
     *
     * @param array $decoded_response The decoded JSON response body.
     * @param string $model The model used for generation (for fallback token estimation).
     * @param string $prompt The original prompt (for token estimation).
     * @return array Array of image data objects and usage.
     *               Structure: ['images' => [['url'=>..., 'b64_json'=>..., 'revised_prompt'=>...], ...], 'usage' => array|null]
     */
    public static function parse(array $decoded_response, string $model = '', string $prompt = ''): array {
        $images = [];
        $usage = $decoded_response['usage'] ?? null; // Capture usage if available

        if (isset($decoded_response['data']) && is_array($decoded_response['data'])) {
            foreach ($decoded_response['data'] as $imageData) {
                $images[] = [
                    'url'            => $imageData['url'] ?? null,
                    'b64_json'       => $imageData['b64_json'] ?? null,
                    'revised_prompt' => $imageData['revised_prompt'] ?? null,
                ];
            }
        }

        // If OpenAI didn't provide usage data, create fallback estimation
        if ($usage === null && !empty($images)) {
            $usage = self::estimate_token_usage($images, $model, $prompt);
        }

        return ['images' => $images, 'usage' => $usage];
    }

    /**
     * Estimates token usage when OpenAI API doesn't provide it.
     *
     * @param array $images Array of generated images.
     * @param string $model The model used for generation.
     * @param string $prompt The original prompt.
     * @return array Estimated usage data.
     */
    private static function estimate_token_usage(array $images, string $model, string $prompt): array {
        $num_images = count($images);
        
        // Estimate tokens per image based on model
        $tokens_per_image = AIPKit_Providers::is_openai_gpt_image_model($model)
            ? self::GPT_IMAGE_TOKENS_PER_IMAGE
            : self::OPENAI_LEGACY_IMAGE_TOKENS_PER_IMAGE;
        
        // Estimate input tokens based on prompt length (rough approximation)
        $prompt_words = str_word_count($prompt);
        $estimated_input_tokens = max(1, intval($prompt_words * 1.3)); // Rough token-to-word ratio
        
        // Output tokens are based on images generated
        $estimated_output_tokens = $num_images * $tokens_per_image;
        $total_tokens = $estimated_input_tokens + $estimated_output_tokens;
        
        return [
            'input_tokens' => $estimated_input_tokens,
            'output_tokens' => $estimated_output_tokens,
            'total_tokens' => $total_tokens,
            'provider_raw' => [
                'source' => 'estimated_openai_cost',
                'model' => $model,
                'images_generated' => $num_images,
                'tokens_per_image' => $tokens_per_image,
                'prompt_tokens_estimated' => $estimated_input_tokens,
            ],
        ];
    }

    /**
     * Parses error response from OpenAI API.
     *
     * @param mixed $response_body The raw or decoded error response body.
     * @param int $status_code The HTTP status code.
     * @return string A user-friendly error message.
     */
    public static function parse_error($response_body, int $status_code): string {
        $message = __('An unknown API error occurred.', 'gpt3-ai-content-generator');
        $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

        if (is_array($decoded) && !empty($decoded['error']['message'])) {
            $message = $decoded['error']['message'];
            if (!empty($decoded['error']['code'])) { $message .= ' (Code: ' . $decoded['error']['code'] . ')'; }
            if (!empty($decoded['error']['type'])) { $message .= ' Type: ' . $decoded['error']['type']; }
        } elseif (is_string($response_body)) {
             $message = substr($response_body, 0, 200); // Raw snippet if not JSON
        }

        return trim($message);
    }
}
