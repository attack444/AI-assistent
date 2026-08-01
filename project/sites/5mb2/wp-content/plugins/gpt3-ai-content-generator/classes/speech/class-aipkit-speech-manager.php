<?php

// MODIFIED FILE

namespace WPAICG\Speech;

use WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory;
use WPAICG\aipkit_dashboard;
use WPAICG\AIPKit_Providers; // Needed for API Keys
use WPAICG\Chat\Utils\Utils as ChatUtils; // Use ChatUtils for the cleaning function
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Speech_Manager
 * Main class for handling Text-to-Speech (TTS) functionality.
 * UPDATED: Passes openai_model_id to OpenAI strategy.
 */
class AIPKit_Speech_Manager
{
    public function __construct()
    {
        // Potentially load required dependencies or setup initial state
        // Ensure ChatUtils class is loaded if not using autoloading
        if (!class_exists(ChatUtils::class)) {
            $utils_path = WPAICG_PLUGIN_DIR . 'classes/chat/utils/class-aipkit_chat_utils.php';
            if (file_exists($utils_path)) {
                require_once $utils_path;
            }
        }
    }

    /**
     * Register hooks for AJAX actions, etc.
     */
    public function init_hooks()
    {
        // The AJAX handler is now in ConversationAjaxHandler
    }

    /**
     * Converts text to speech using the specified provider.
     * Cleans the text before sending it to the TTS API.
     * ADDED: Retrieves openai_model_id from bot settings and passes it to the options.
     *
     * @param string $text The text to convert.
     * @param array $options Optional parameters (provider, voice, speed, format etc.).
     *                       Must contain 'provider' and 'voice'.
     *                       For ElevenLabs, can also contain 'elevenlabs_model_id'.
     *                       For OpenAI, can also contain 'openai_model_id'.
     * @return string|WP_Error Base64 encoded audio data string or WP_Error on failure.
     */
    public function text_to_speech(string $text, array $options = [])
    {
        // 1. Get required options
        $provider = $options['provider'] ?? null;
        $voice_id = $options['voice'] ?? null;
        // Get ElevenLabs model ID if provider is ElevenLabs
        $elevenlabs_model_id = ($provider === 'ElevenLabs' && isset($options['elevenlabs_model_id']))
                                ? $options['elevenlabs_model_id']
                                : null;
        // Get OpenAI model ID if provider is OpenAI
        $openai_model_id = ($provider === 'OpenAI' && isset($options['openai_model_id']))
                                ? $options['openai_model_id']
                                : null;

        if (empty($provider) || empty($voice_id)) {
            return new WP_Error('missing_tts_options', __('TTS Provider and Voice ID are required.', 'gpt3-ai-content-generator'));
        }
        if (empty($text)) {
            return new WP_Error('empty_tts_text', __('Text cannot be empty for speech generation.', 'gpt3-ai-content-generator'));
        }

        // --- Clean the text before sending to TTS API ---
        if (class_exists(ChatUtils::class)) {
            $cleaned_text = ChatUtils::aipkit_clean_text_for_tts($text);
            if (empty($cleaned_text)) {
                return new WP_Error('empty_cleaned_tts_text', __('Text became empty after cleaning formatting.', 'gpt3-ai-content-generator'));
            }
        } else {
            $cleaned_text = $text; // Fallback if utils class missing
        }
        // --- End: Clean Text ---


        // 3. Get provider strategy
        $strategy = AIPKit_TTS_Provider_Strategy_Factory::get_strategy($provider);
        if (is_wp_error($strategy)) {
            return $strategy;
        }

        // 4. Get API credentials and specific settings
        $api_params = [];
        // Ensure Providers class is loaded
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return new WP_Error('dependency_missing', __('Internal configuration error (Providers).', 'gpt3-ai-content-generator'));
            }
        }
        // Fetch provider data using the loaded class
        $provider_data = AIPKit_Providers::get_provider_data($provider);
        $api_params['api_key'] = $provider_data['api_key'] ?? null;
        $api_params['base_url'] = $provider_data['base_url'] ?? null; // Pass base URL if needed
        $api_params['api_version'] = $provider_data['api_version'] ?? null; // Pass API version if needed

        if (empty($api_params['api_key'])) {
            /* translators: %s: The provider name that was attempted to be used for TTS generation. */
            return new WP_Error('missing_api_key', sprintf(__('API Key for %s provider is missing in main settings.', 'gpt3-ai-content-generator'), $provider), ['status' => 500]);
        }


        // 5. Prepare synthesis options (pass voice, potentially speed, format etc.)
        $synthesis_options = [
            'voice' => $voice_id,
            'format' => $options['format'] ?? 'mp3', // Default to mp3
        ];
        // Add ElevenLabs model ID to synthesis options if available
        if ($provider === 'ElevenLabs' && !empty($elevenlabs_model_id)) {
            $synthesis_options['model_id'] = $elevenlabs_model_id;
        }
        // Add OpenAI model ID to synthesis options if available
        if ($provider === 'OpenAI' && !empty($openai_model_id)) {
            $synthesis_options['model_id'] = $openai_model_id; // Pass the OpenAI TTS model ID
        }
        // Add OpenAI speed if available
        if ($provider === 'OpenAI' && isset($options['speed'])) {
            $synthesis_options['speed'] = $options['speed'];
        }


        // 6. Call strategy's generate method using the CLEANED text
        $result = $strategy->generate_speech($cleaned_text, $api_params, $synthesis_options);


        // 7. Handle result (strategy should return base64 string or WP_Error)
        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }
}
