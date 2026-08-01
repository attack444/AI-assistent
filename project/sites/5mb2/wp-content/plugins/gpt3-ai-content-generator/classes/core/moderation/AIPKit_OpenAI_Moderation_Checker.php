<?php

namespace WPAICG\Core\Moderation;

use WPAICG\AIPKit_Providers;
use WPAICG\Core\Providers\ProviderStrategyFactory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_OpenAI_Moderation_Checker
 *
 * Checks if OpenAI Moderation should be performed and, if so, calls the OpenAI provider.
 */
class AIPKit_OpenAI_Moderation_Checker {
    /**
     * Checks if text should be moderated by OpenAI and performs the check if applicable.
     *
     * @param string $text The text content to check.
     * @param array $bot_settings Current request settings/context (used for provider selection and moderation execution).
     * @return WP_Error|null WP_Error if flagged by OpenAI, null otherwise or if not applicable.
     */
    public static function check(string $text, array $bot_settings): ?WP_Error {
        $global_moderation_settings = class_exists(AIPKit_Global_Security_Settings::class)
            ? AIPKit_Global_Security_Settings::get_openai_moderation_settings()
            : [
                'enabled' => '0',
                'message' => self::get_default_flagged_message(),
            ];
        $moderation_settings = $bot_settings;
        $moderation_settings['openai_moderation_enabled'] = $global_moderation_settings['enabled'] ?? '0';
        $moderation_settings['openai_moderation_message'] = $global_moderation_settings['message']
            ?? self::get_default_flagged_message();

        $moderation_enabled = $moderation_settings['openai_moderation_enabled'] ?? '0';
        if (!($moderation_enabled === '1' || $moderation_enabled === 1 || $moderation_enabled === true)) {
            return null;
        }

        $provider_from_bot = $moderation_settings['provider'] ?? null;
        $global_default_provider = null;
        if (class_exists(AIPKit_Providers::class)) {
            $global_default_provider = AIPKit_Providers::get_current_provider();
        }
        $current_provider = $provider_from_bot ?: $global_default_provider;
        if ($current_provider !== 'OpenAI') {
            return null;
        }

        if (!class_exists(ProviderStrategyFactory::class) || !class_exists(AIPKit_Providers::class)) {
            return null;
        }

        $strategy = ProviderStrategyFactory::get_strategy('OpenAI');
        if (is_wp_error($strategy) || !method_exists($strategy, 'moderate_text')) {
            return null;
        }

        $api_params = AIPKit_Providers::get_provider_data('OpenAI');
        if (empty($api_params['api_key'])) {
            return null;
        }

        $moderation_result = $strategy->moderate_text($text, $api_params);
        if (is_wp_error($moderation_result) || $moderation_result !== true) {
            return null;
        }

        $message = trim((string) ($moderation_settings['openai_moderation_message'] ?? ''));
        if ($message === '') {
            $message = self::get_default_flagged_message();
        }

        return new WP_Error('content_flagged_by_openai', $message, ['status' => 400]);
    }

    private static function get_default_flagged_message(): string {
        return __('Your message was flagged by the moderation system and could not be sent.', 'gpt3-ai-content-generator');
    }
}
