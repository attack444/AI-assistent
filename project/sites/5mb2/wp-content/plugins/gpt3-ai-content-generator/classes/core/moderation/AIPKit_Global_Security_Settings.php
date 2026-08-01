<?php

namespace WPAICG\Core\Moderation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Stores global security settings shared across modules.
 */
class AIPKit_Global_Security_Settings
{
    private const OPTIONS_KEY = 'aipkit_options';
    private const SETTINGS_KEY = 'security';

    /**
     * Returns the default global security settings.
     *
     * @return array<string, mixed>
     */
    public static function get_defaults(): array
    {
        return [
            'enable_ip_anonymization' => '0',
            'openai_moderation_enabled' => '0',
            'openai_moderation_message' => self::get_default_openai_moderation_message(),
            'blocklists' => [
                'banned_words' => '',
                'banned_words_message' => '',
                'banned_ips' => '',
                'banned_ips_message' => '',
            ],
        ];
    }

    /**
     * Returns normalized global security settings.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array
    {
        $options = get_option(self::OPTIONS_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $raw_settings = isset($options[self::SETTINGS_KEY]) && is_array($options[self::SETTINGS_KEY])
            ? $options[self::SETTINGS_KEY]
            : [];

        return self::sanitize_settings_input($raw_settings);
    }

    /**
     * Returns whether IP anonymization is enabled globally.
     */
    public static function is_ip_anonymization_enabled(): bool
    {
        $settings = self::get_settings();

        return isset($settings['enable_ip_anonymization']) && (string) $settings['enable_ip_anonymization'] === '1';
    }

    /**
     * Returns the normalized global OpenAI moderation settings.
     *
     * @return array{enabled: string, message: string}
     */
    public static function get_openai_moderation_settings(): array
    {
        $settings = self::get_settings();

        return [
            'enabled' => isset($settings['openai_moderation_enabled']) && (string) $settings['openai_moderation_enabled'] === '1'
                ? '1'
                : '0',
            'message' => isset($settings['openai_moderation_message'])
                ? (string) $settings['openai_moderation_message']
                : self::get_default_openai_moderation_message(),
        ];
    }

    /**
     * Resolves a guest session identifier without persisting raw IP addresses when anonymization is enabled.
     */
    public static function resolve_guest_session_id(?string $session_id, ?string $client_ip): ?string
    {
        $normalized_session_id = is_string($session_id) ? sanitize_text_field($session_id) : '';
        if ($normalized_session_id !== '') {
            return $normalized_session_id;
        }

        $normalized_ip = is_string($client_ip) ? sanitize_text_field($client_ip) : '';
        if ($normalized_ip === '') {
            return null;
        }

        if (!self::is_ip_anonymization_enabled()) {
            return $normalized_ip;
        }

        return 'anon-' . substr(hash_hmac('sha256', $normalized_ip, wp_salt('auth')), 0, 32);
    }

    /**
     * Saves normalized global security settings into aipkit_options.
     *
     * @param mixed $raw_settings
     * @return array<string, mixed>
     */
    public static function save_settings($raw_settings): array
    {
        $sanitized_settings = self::sanitize_settings_input($raw_settings);

        $options = get_option(self::OPTIONS_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        $options[self::SETTINGS_KEY] = $sanitized_settings;
        update_option(self::OPTIONS_KEY, $options, 'no');

        return $sanitized_settings;
    }

    /**
     * Returns global banned IP and banned word settings.
     *
     * @param string $module Ignored. Global blocklists apply to every supported module.
     * @return array<string, array<string, string>>
     */
    public static function get_blocklists_for_module(string $module): array
    {
        $settings = self::get_settings();
        $blocklists = isset($settings['blocklists']) && is_array($settings['blocklists'])
            ? $settings['blocklists']
            : [];

        return [
            'banned_ips_settings' => [
                'ips' => (string) ($blocklists['banned_ips'] ?? ''),
                'message' => (string) ($blocklists['banned_ips_message'] ?? ''),
            ],
            'banned_words_settings' => [
                'words' => (string) ($blocklists['banned_words'] ?? ''),
                'message' => (string) ($blocklists['banned_words_message'] ?? ''),
            ],
        ];
    }

    /**
     * Returns whether an exact IP address is present in the global blocklist.
     */
    public static function is_ip_blocked(string $ip_address): bool
    {
        $ip_address = trim($ip_address);
        if (filter_var($ip_address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $settings = self::get_settings();
        $blocked_ips = isset($settings['blocklists']['banned_ips'])
            ? array_map('trim', explode(',', (string) $settings['blocklists']['banned_ips']))
            : [];

        return in_array($ip_address, array_filter($blocked_ips), true);
    }

    /**
     * Adds or removes an exact IP address from the global blocklist.
     */
    public static function set_ip_blocked(string $ip_address, bool $blocked): bool
    {
        $ip_address = trim($ip_address);
        if (filter_var($ip_address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $settings = self::get_settings();
        $blocked_ips = isset($settings['blocklists']['banned_ips'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $settings['blocklists']['banned_ips']))))
            : [];

        if ($blocked) {
            if (!in_array($ip_address, $blocked_ips, true)) {
                $blocked_ips[] = $ip_address;
            }
        } else {
            $blocked_ips = array_values(array_filter($blocked_ips, static function ($candidate) use ($ip_address): bool {
                return $candidate !== $ip_address;
            }));
        }

        $settings['blocklists']['banned_ips'] = implode(',', array_unique($blocked_ips));
        self::save_settings($settings);

        return self::is_ip_blocked($ip_address) === $blocked;
    }

    /**
     * Sanitizes raw global security settings.
     *
     * @param mixed $raw_settings
     * @return array<string, mixed>
     */
    public static function sanitize_settings_input($raw_settings): array
    {
        $defaults = self::get_defaults();
        $sanitized = $defaults;

        if (!is_array($raw_settings)) {
            return $sanitized;
        }

        $raw_blocklists = isset($raw_settings['blocklists']) && is_array($raw_settings['blocklists'])
            ? $raw_settings['blocklists']
            : $raw_settings;

        $sanitized['enable_ip_anonymization'] =
            isset($raw_settings['enable_ip_anonymization']) && (string) $raw_settings['enable_ip_anonymization'] === '1'
                ? '1'
                : '0';
        $sanitized['openai_moderation_enabled'] =
            isset($raw_settings['openai_moderation_enabled']) && (string) $raw_settings['openai_moderation_enabled'] === '1'
                ? '1'
                : '0';
        $sanitized['openai_moderation_message'] = isset($raw_settings['openai_moderation_message'])
            ? sanitize_text_field((string) $raw_settings['openai_moderation_message'])
            : self::get_default_openai_moderation_message();

        $sanitized['blocklists']['banned_words'] = self::sanitize_banned_words(
            isset($raw_blocklists['banned_words']) ? (string) $raw_blocklists['banned_words'] : ''
        );
        $sanitized['blocklists']['banned_words_message'] = isset($raw_blocklists['banned_words_message'])
            ? sanitize_text_field((string) $raw_blocklists['banned_words_message'])
            : '';
        $sanitized['blocklists']['banned_ips'] = self::sanitize_banned_ips(
            isset($raw_blocklists['banned_ips']) ? (string) $raw_blocklists['banned_ips'] : ''
        );
        $sanitized['blocklists']['banned_ips_message'] = isset($raw_blocklists['banned_ips_message'])
            ? sanitize_text_field((string) $raw_blocklists['banned_ips_message'])
            : '';

        return $sanitized;
    }

    /**
     * Sanitizes a comma-separated banned words string.
     */
    private static function sanitize_banned_words(string $raw_words): string
    {
        $banned_words = array_map(
            'trim',
            explode(',', strtolower(sanitize_textarea_field($raw_words)))
        );

        return implode(',', array_filter($banned_words, static function ($word): bool {
            return $word !== '';
        }));
    }

    /**
     * Sanitizes a comma-separated banned IP string.
     */
    private static function sanitize_banned_ips(string $raw_ips): string
    {
        $candidate_ips = array_map(
            'trim',
            explode(',', sanitize_textarea_field($raw_ips))
        );

        $valid_ips = array_filter($candidate_ips, static function ($ip): bool {
            return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
        });

        return implode(',', array_unique($valid_ips));
    }

    /**
     * Returns the default message shown when OpenAI moderation blocks a request.
     */
    private static function get_default_openai_moderation_message(): string
    {
        return __('Your message was flagged by the moderation system and could not be sent.', 'gpt3-ai-content-generator');
    }
}
