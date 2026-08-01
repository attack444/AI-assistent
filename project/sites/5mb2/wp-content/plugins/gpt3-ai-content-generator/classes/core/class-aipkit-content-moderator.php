<?php

namespace WPAICG\Core;

// Use statements for the new checker components
use WPAICG\Core\Moderation\AIPKit_BannedIP_Checker;
use WPAICG\Core\Moderation\AIPKit_BannedWords_Checker;
use WPAICG\Core\Moderation\AIPKit_Global_Security_Settings;
use WPAICG\Core\Moderation\AIPKit_OpenAI_Moderation_Checker;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AIPKit_Content_Moderator (Facade)
 *
 * Centralized class for handling content moderation checks.
 * Delegates specific checks to specialized checker classes.
 */
class AIPKit_Content_Moderator {
    /**
     * Checks the provided text and context against configured moderation rules.
     *
     * @param string $text The text content to check (e.g., user message).
     * @param array $context Associative array containing context information.
     *                      Expected keys:
     *                      - 'client_ip': (string) The IP address of the user making the request.
     *                      - 'module': (string) The current module slug (e.g. chat, image_generator, ai_forms).
     *                      - 'bot_settings': (array) Current request settings/context (provider and module-specific options).
     * @return WP_Error|null Returns a WP_Error if the content is flagged (with user-facing message),
     *                       or null if the content passes all checks or moderation is not applicable/failed internally.
     */
    public static function check_content(string $text, array $context = []): ?WP_Error {
        $client_ip = $context['client_ip'] ?? null;
        $module = isset($context['module']) ? sanitize_key((string) $context['module']) : 'chat';
        $bot_settings = $context['bot_settings'] ?? [];
        $global_blocklists = class_exists(AIPKit_Global_Security_Settings::class)
            ? AIPKit_Global_Security_Settings::get_blocklists_for_module($module)
            : [
                'banned_ips_settings' => ['ips' => '', 'message' => ''],
                'banned_words_settings' => ['words' => '', 'message' => ''],
            ];

        $banned_ips_settings = isset($global_blocklists['banned_ips_settings']) && is_array($global_blocklists['banned_ips_settings'])
            ? $global_blocklists['banned_ips_settings']
            : ['ips' => '', 'message' => ''];
        $ip_check_result = AIPKit_BannedIP_Checker::check($client_ip, $banned_ips_settings);
        if (is_wp_error($ip_check_result)) {
            return $ip_check_result;
        }

        // IP blocking also applies to requests that contain only an image or
        // another non-text input. Skip the text-specific checks after the IP
        // check so empty input never triggers an external moderation request.
        if (trim($text) === '') {
            return null;
        }

        $banned_words_settings = isset($global_blocklists['banned_words_settings']) && is_array($global_blocklists['banned_words_settings'])
            ? $global_blocklists['banned_words_settings']
            : ['words' => '', 'message' => ''];
        $words_check_result = AIPKit_BannedWords_Checker::check($text, $banned_words_settings);
        if (is_wp_error($words_check_result)) {
            return $words_check_result;
        }

        // OpenAI Moderation API check.
        $openai_mod_check_result = AIPKit_OpenAI_Moderation_Checker::check($text, $bot_settings);
        if (is_wp_error($openai_mod_check_result)) {
            return $openai_mod_check_result;
        }

        // All checks passed
        return null;
    }
}
