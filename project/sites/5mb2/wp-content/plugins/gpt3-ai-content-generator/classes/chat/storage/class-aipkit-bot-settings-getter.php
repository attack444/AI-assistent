<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\Chat\Storage;

use WPAICG\AIPKit_Providers;
use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Chat\Storage\BotSettingsManager;
use WP_Error; // Added for return type hinting

// Load getter logic files.
require_once __DIR__ . '/getter/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AIPKit_Bot_Settings_Getter
{
    /**
     * Retrieves and structures all settings for a given chatbot ID.
     * Delegates specific setting groups to individual logic functions.
     * MODIFIED: Accepts an optional array of prefetched meta to optimize database calls.
     *
     * @param int $bot_id The ID of the chatbot post.
     * @param array|null $prefetched_meta Optional. An array of already fetched meta for this bot.
     *                                     Format: [meta_key => single_meta_value, ...].
     * @return array|WP_Error An associative array of settings or WP_Error on failure.
     */
    public static function get(int $bot_id, ?array $prefetched_meta = null)
    {
        $validation_result = GetterMethods\validate_bot_post_logic($bot_id);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        $bot_post = $validation_result; // Validated WP_Post object

        $get_meta_fn = function ($key, $default = '') use ($bot_id, $prefetched_meta) {
            $value = '';
            $found_in_prefetched = false;

            if ($prefetched_meta !== null && array_key_exists($key, $prefetched_meta)) {
                $value = $prefetched_meta[$key];
                $found_in_prefetched = true;
            } else {
                $value = get_post_meta($bot_id, $key, true);
            }

            // Special handling for token limits where empty string means unlimited, not 0
            if (in_array($key, ['_aipkit_token_guest_limit', '_aipkit_token_user_limit'], true)) {
                return ($value === '') ? '' : $value; // Return empty string as is, otherwise actual value
            }
            // For other keys, if value is empty string (either from prefetched or get_post_meta) use default
            return ($value !== '') ? $value : $default;
        };
        // END MODIFICATION

        $bot_name = $bot_post->post_title ?: __('Chatbot', 'gpt3-ai-content-generator');

        $settings = [];
        $current_provider_from_main_settings = class_exists(AIPKit_Providers::class) ? AIPKit_Providers::get_current_provider() : 'OpenAI';

        $settings = array_merge($settings, GetterMethods\get_general_bot_settings_logic($bot_id, $bot_name, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_ai_configuration_logic($bot_id, $current_provider_from_main_settings, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_appearance_settings_logic($bot_id, $bot_name, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_conversation_starters_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_contextual_settings_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_vector_store_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_tts_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_stt_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_token_management_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_openai_specific_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_claude_specific_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_openrouter_specific_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_xai_specific_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_google_specific_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_trigger_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_voice_agent_config_logic($bot_id, $get_meta_fn));
        $settings = array_merge($settings, GetterMethods\get_embed_settings_logic($bot_id, $get_meta_fn)); // ADDED

        return $settings;
    }
}
