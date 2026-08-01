<?php
// REVISED FILE

namespace WPAICG\Images\Providers\Google;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles parsing responses from Google Image Generation models.
 */
class GoogleImageResponseParser {

    const IMAGEN_TOKENS_PER_IMAGE = 1500; // Define hardcoded token cost for Imagen

    /**
     * Parses the response from Google Image Generation API.
     *
     * @param array  $decoded_response The decoded JSON response body.
     * @param string $model_id The model ID used for the request.
     * @return array Array of image data objects or WP_Error on failure.
     *               Expected structure: ['images' => [['b64_json' => base64_string], ...], 'usage' => array|null]
     */
    public static function parse(array $decoded_response, string $model_id) {
        $images = [];
        $usage = null; // Initialize usage to null
        $has_text_part = false;

        if (strpos($model_id, 'gemini') !== false) { // Handling for Gemini models
            if (isset($decoded_response['candidates'][0]['content']['parts']) && is_array($decoded_response['candidates'][0]['content']['parts'])) {
                foreach ($decoded_response['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']['mimeType']) &&
                        strpos($part['inlineData']['mimeType'], 'image/') === 0 &&
                        isset($part['inlineData']['data']) &&
                        !empty($part['inlineData']['data'])) {
                        $images[] = ['b64_json' => $part['inlineData']['data']];
                    }
                    if (isset($part['text']) && !empty(trim($part['text']))) {
                        $has_text_part = true;
                    }
                }
            }
            // Extract usage data if present for Gemini
            if (isset($decoded_response['usageMetadata']) && is_array($decoded_response['usageMetadata'])) {
                $usage = [
                    'input_tokens'  => $decoded_response['usageMetadata']['promptTokenCount'] ?? 0,
                    'output_tokens' => $decoded_response['usageMetadata']['candidatesTokenCount'] ?? 0,
                    'total_tokens'  => $decoded_response['usageMetadata']['totalTokenCount'] ?? 0,
                    'provider_raw'  => $decoded_response['usageMetadata'],
                ];
            }
        } elseif (strpos($model_id, 'imagen') !== false) { // Handling for Imagen models
            
            if (isset($decoded_response['predictions']) && is_array($decoded_response['predictions'])) {
                
                foreach ($decoded_response['predictions'] as $index => $prediction) {
                    
                    if (isset($prediction['bytesBase64Encoded']) && !empty($prediction['bytesBase64Encoded'])) {
                        $images[] = ['b64_json' => $prediction['bytesBase64Encoded']];
                    }
                    
                    if (isset($prediction['text']) && !empty(trim($prediction['text']))) {
                        $has_text_part = true;
                    }
                }
            } 
            // Calculate hardcoded token usage for Imagen models
            $num_images_generated = count($images);
            
            if ($num_images_generated > 0) {
                $total_tokens_for_imagen = $num_images_generated * self::IMAGEN_TOKENS_PER_IMAGE;
                $usage = [
                    'input_tokens'  => 0, // Prompt tokens are not explicitly returned for Imagen cost model
                    'output_tokens' => $total_tokens_for_imagen,
                    'total_tokens'  => $total_tokens_for_imagen,
                    'provider_raw'  => [
                        'source' => 'hardcoded_imagen_cost',
                        'images_generated' => $num_images_generated,
                        'cost_per_image_tokens' => self::IMAGEN_TOKENS_PER_IMAGE,
                    ],
                ];
            }
        } else {
            return new WP_Error('unsupported_google_image_model_for_parsing', __('Unsupported Google image model for response parsing.', 'gpt3-ai-content-generator'));
        }

        if (empty($images)) {
            
            $error_message = __('No image data found in Google API response.', 'gpt3-ai-content-generator');
            $error_code = 'no_images_in_google_response';
            
            if (isset($decoded_response['promptFeedback']['blockReason'])) {
                /* translators: %s: The reason for blocking the image generation request. */
                $error_message = sprintf(__('Image generation request blocked by Google due to: %s', 'gpt3-ai-content-generator'), $decoded_response['promptFeedback']['blockReason']);
                $error_code = 'google_image_prompt_blocked';
            } elseif (isset($decoded_response['candidates'][0]['finishReason']) && $decoded_response['candidates'][0]['finishReason'] !== 'STOP') {
                /* translators: %s: The reason for incomplete image generation. */
                $error_message = sprintf(__('Image generation incomplete. Finish reason: %s', 'gpt3-ai-content-generator'), $decoded_response['candidates'][0]['finishReason']);
                $error_code = 'google_image_generation_incomplete';
            } elseif ($has_text_part) {
                $error_message = __('Google API returned text but no image data was extracted. Please check API response format or prompt.', 'gpt3-ai-content-generator');
                $error_code = 'google_image_text_but_no_image_extracted';
            }
            
            return new WP_Error($error_code, $error_message);
        }

        return ['images' => $images, 'usage' => $usage];
    }

     /**
     * Parses error response from Google API (general).
     */
    public static function parse_error($response_body, int $status_code): string {
        $message = __('An unknown Google API error occurred.', 'gpt3-ai-content-generator');
        $decoded = is_string($response_body) ? json_decode($response_body, true) : $response_body;

        if (is_array($decoded) && !empty($decoded['error']['message'])) {
            $message = $decoded['error']['message'];
            if (!empty($decoded['error']['details'][0]['reason'])) {
                $message .= " (Reason: " . $decoded['error']['details'][0]['reason'] . ")";
            } elseif (!empty($decoded['error']['details'][0]['message'])) {
                $message .= " (" . $decoded['error']['details'][0]['message'] . ")";
            }
        } elseif (is_string($response_body)) {
             $message = substr($response_body, 0, 200);
        }
        return trim($message);
    }
}