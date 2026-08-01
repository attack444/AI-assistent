<?php


/**
 * AIPKit Chatbot - Settings Manager (Refactored)
 * Handles getting/saving/defaulting chatbot settings stored as post meta.
 * Delegates actual logic to new helper classes.
 */

namespace WPAICG\Chat\Storage;

use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Chat\Storage\SiteWideBotManager;
// use WPAICG\Chat\Storage\BotSettingsManager; // Self-reference not needed
use WP_Error;
use WPAICG\Chat\Storage\AIPKit_Bot_Settings_Getter;
use WPAICG\Chat\Storage\AIPKit_Bot_Settings_Saver; // Use the new saver class
use WPAICG\Chat\Storage\AIPKit_Bot_Settings_Initializer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BotSettingsManager
{
    // --- Constants for Default Settings ---
    public const DEFAULT_TEMPERATURE = 1.0;
    public const DEFAULT_MAX_COMPLETION_TOKENS = 4000;
    public const DEFAULT_MAX_MESSAGES = 15;
    public const DEFAULT_ENABLE_FULLSCREEN = '1';
    public const DEFAULT_ENABLE_DOWNLOAD = '0';
    public const DEFAULT_ENABLE_COPY_BUTTON = '1';
    public const DEFAULT_ENABLE_FEEDBACK = '1';
    public const DEFAULT_ENABLE_CONSENT_COMPLIANCE = '0';
    public const DEFAULT_POPUP_DELAY = 0;
    public const DEFAULT_ENABLE_CONVERSATION_STARTERS = '1';
    public const DEFAULT_ENABLE_CONVERSATION_SIDEBAR = '0';
    public const DEFAULT_POPUP_ICON_TYPE = 'default';
    public const DEFAULT_POPUP_ICON_STYLE = 'circle';
    public const DEFAULT_POPUP_ICON_VALUE = 'spark';
    public const DEFAULT_POPUP_ICON_SIZE = 'medium'; // allowed: small|medium|large|xlarge
    // --- Popup Hint/Label Defaults ---
    public const DEFAULT_POPUP_LABEL_ENABLED = '1';
    public const DEFAULT_POPUP_LABEL_TEXT = 'Need help? Ask me!';
    public const DEFAULT_POPUP_LABEL_MODE = 'on_delay'; // allowed: always|on_delay|until_open|until_dismissed
    public const DEFAULT_POPUP_LABEL_DELAY_SECONDS = 1;
    public const DEFAULT_POPUP_LABEL_AUTO_HIDE_SECONDS = 0; // 0 = never auto-hide
    public const DEFAULT_POPUP_LABEL_DISMISSIBLE = '1';
    public const DEFAULT_POPUP_LABEL_FREQUENCY = 'once_per_visitor'; // allowed: always|once_per_session|once_per_visitor
    public const DEFAULT_POPUP_LABEL_SHOW_ON_MOBILE = '1';
    public const DEFAULT_POPUP_LABEL_SHOW_ON_DESKTOP = '1';
    public const DEFAULT_POPUP_LABEL_VERSION = '';
    public const DEFAULT_POPUP_LABEL_SIZE = 'large'; // allowed: small|medium|large|xlarge
    // --- Header Defaults ---
    public const DEFAULT_HEADER_AVATAR_URL = '';
    public const DEFAULT_HEADER_AVATAR_TYPE = 'default';
    public const DEFAULT_HEADER_AVATAR_VALUE = 'spark';
    public const DEFAULT_HEADER_ONLINE_TEXT = 'Online';
    public const DEFAULT_CONTENT_AWARE_ENABLED = '0';
    public const DEFAULT_TOKEN_GUEST_LIMIT = null;
    public const DEFAULT_TOKEN_USER_LIMIT = null;
    public const DEFAULT_TOKEN_RESET_PERIOD = 'never';
    public const DEFAULT_TOKEN_LIMIT_MESSAGE = 'You have reached your quota for this period.';
    public const DEFAULT_TOKEN_LIMIT_MODE = 'general';
    public const DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_TYPE = 'none';
    public const DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_LABEL = '';
    public const DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_URL = '';
    public const DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_TYPE = 'none';
    public const DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_LABEL = '';
    public const DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_URL = '';
    public const DEFAULT_TTS_ENABLED = '0';
    public const DEFAULT_TTS_PROVIDER = 'Google';
    public const DEFAULT_TTS_OPENAI_MODEL_ID = 'tts-1';
    public const DEFAULT_TTS_ELEVENLABS_MODEL_ID = '';
    public const DEFAULT_TTS_AUTO_PLAY = '0';
    public const DEFAULT_ENABLE_VOICE_INPUT = '0';
    public const DEFAULT_STT_PROVIDER = 'OpenAI';
    public const DEFAULT_STT_OPENAI_MODEL_ID = 'whisper-1';
    public const DEFAULT_STT_AZURE_MODEL_ID = '';
    public const DEFAULT_IMAGE_TRIGGERS = '/image, /generate';
    public const DEFAULT_CHAT_IMAGE_MODEL_ID = 'gpt-image-2';
    public const DEFAULT_ENABLE_IMAGE_GENERATION = '0';
    public const DEFAULT_ENABLE_FILE_UPLOAD = '0';
    public const DEFAULT_ENABLE_IMAGE_UPLOAD = '0';
    public const DEFAULT_OPENAI_CONVERSATION_STATE_ENABLED = '0';
    // --- Typing Indicator Defaults ---
    public const DEFAULT_CUSTOM_TYPING_TEXT = '';
    // --- Vector Store Constants ---
    public const DEFAULT_ENABLE_VECTOR_STORE = '0';
    public const DEFAULT_VECTOR_STORE_PROVIDER = 'openai';
    public const DEFAULT_OPENAI_VECTOR_STORE_ID = ''; // Legacy, will be array now
    public const DEFAULT_VECTOR_STORE_TOP_K = 3;
    public const DEFAULT_VECTOR_STORE_CONFIDENCE_THRESHOLD = 20; // NEW
    // --- Pinecone & Embedding Specific Constants ---
    public const DEFAULT_PINECONE_INDEX_NAME = '';
    public const DEFAULT_VECTOR_EMBEDDING_PROVIDER = 'openai';
    public const DEFAULT_VECTOR_EMBEDDING_MODEL = 'text-embedding-3-small';
    // --- Qdrant Specific Constants ---
    public const DEFAULT_QDRANT_COLLECTION_NAME = '';
    // --- OpenAI Web Search Constants ---
    public const DEFAULT_OPENAI_WEB_SEARCH_ENABLED = '0'; // Master switch in bot settings
    public const DEFAULT_OPENAI_WEB_SEARCH_CONTEXT_SIZE = 'medium';
    public const DEFAULT_OPENAI_WEB_SEARCH_LOC_TYPE = 'none';
    // --- Claude Web Search Constants ---
    public const DEFAULT_CLAUDE_WEB_SEARCH_ENABLED = '0';
    public const DEFAULT_CLAUDE_WEB_SEARCH_MAX_USES = 5;
    public const DEFAULT_CLAUDE_WEB_SEARCH_LOC_TYPE = 'none';
    public const DEFAULT_CLAUDE_WEB_SEARCH_CACHE_TTL = 'none';
    // --- OpenRouter Web Search Constants ---
    public const DEFAULT_OPENROUTER_WEB_SEARCH_ENABLED = '0';
    public const DEFAULT_OPENROUTER_WEB_SEARCH_ENGINE = 'auto';
    public const DEFAULT_OPENROUTER_WEB_SEARCH_MAX_RESULTS = 5;
    public const DEFAULT_OPENROUTER_WEB_SEARCH_SEARCH_PROMPT = '';
    // --- xAI Web Search Constants ---
    public const DEFAULT_XAI_WEB_SEARCH_ENABLED = '0';
    // --- Frontend Web Toggle Defaults ---
    public const DEFAULT_WEB_TOGGLE_DEFAULT_ON = '0';
    public const DEFAULT_SHOW_SOURCES = '1';
    public const DEFAULT_SOURCES_LABEL = '';
    public const DEFAULT_SEARCHING_WEB_TEXT = '';
    public const DEFAULT_RETRIEVING_CONTEXT_TEXT = '';
    public const DEFAULT_GOOGLE_SEARCH_GROUNDING_ENABLED = '0'; // Master switch for bot
    public const DEFAULT_GOOGLE_GROUNDING_MODE = 'DEFAULT_MODE'; // Default: use Search as Tool for Gemini 2.0+, Retrieval for 1.5 Flash
    public const DEFAULT_GOOGLE_GROUNDING_DYNAMIC_THRESHOLD = 0.3;
    public const DEFAULT_ENABLE_REALTIME_VOICE = '0';
    public const DEFAULT_DIRECT_VOICE_MODE = '0';
    public const DEFAULT_REALTIME_MODEL = 'gpt-4o-realtime-preview';
    public const DEFAULT_REALTIME_VOICE = 'alloy';
    public const DEFAULT_TURN_DETECTION = 'server_vad';
    public const DEFAULT_SPEED = 1.0;
    public const DEFAULT_INPUT_AUDIO_FORMAT = 'pcm16';
    public const DEFAULT_OUTPUT_AUDIO_FORMAT = 'pcm16';
    public const DEFAULT_INPUT_AUDIO_NOISE_REDUCTION = '1';
    public const DEFAULT_REASONING_EFFORT = 'none';

    public const DEFAULT_CUSTOM_THEME_FONT_FAMILY = 'inherit';
    public const DEFAULT_CUSTOM_THEME_BUBBLE_BORDER_RADIUS = 18;
    public const DEFAULT_CTS_PRIMARY_COLOR = '#0F766E';
    public const DEFAULT_CTS_SECONDARY_COLOR = '#ECFEFF';
    public const DEFAULT_CTS_ACCENT_COLOR = '#111111';
    public const DEFAULT_CTS_APP_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_SURFACE_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_AUTO_TEXT_CONTRAST = '1';
    public const DEFAULT_CTS_CONTAINER_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_CONTAINER_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_CONTAINER_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_CONTAINER_BORDER_RADIUS = 10; // Assuming this is a new general radius
    public const DEFAULT_CTS_HEADER_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_HEADER_TEXT_COLOR = '#6B6B6B';
    public const DEFAULT_CTS_HEADER_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_MESSAGES_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_MESSAGES_SCROLLBAR_THUMB_COLOR = '#E5E5E5'; // Actual color default
    public const DEFAULT_CTS_MESSAGES_SCROLLBAR_TRACK_COLOR = 'transparent'; // Actual color default
    public const DEFAULT_CTS_BOT_BUBBLE_BG_COLOR = '#F3F3F3';
    public const DEFAULT_CTS_BOT_BUBBLE_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_USER_BUBBLE_BG_COLOR = '#006CFF';
    public const DEFAULT_CTS_USER_BUBBLE_TEXT_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_INPUT_AREA_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_INPUT_AREA_BORDER_COLOR = '#E5E5E5'; // Actual color default
    public const DEFAULT_CTS_INPUT_WRAPPER_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_INPUT_WRAPPER_BORDER_COLOR = '#E5E5E5'; // Actual color default
    public const DEFAULT_CTS_INPUT_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_INPUT_PLACEHOLDER_COLOR = '#737373'; // Actual color default
    public const DEFAULT_CTS_INPUT_FOCUS_BORDER_COLOR = '#111111'; // Actual color default
    public const DEFAULT_CTS_INPUT_FOCUS_SHADOW_COLOR = 'rgba(0, 0, 0, 0.12)'; // Actual color default
    public const DEFAULT_CTS_SEND_BUTTON_BG_COLOR = '#111111';
    public const DEFAULT_CTS_SEND_BUTTON_TEXT_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_ACTION_BUTTON_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_ACTION_BUTTON_COLOR = '#6B6B6B';
    public const DEFAULT_CTS_ACTION_BUTTON_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_ACTION_BUTTON_HOVER_BG_COLOR = '#EDEDED';
    public const DEFAULT_CTS_ACTION_BUTTON_HOVER_COLOR = '#111111';
    public const DEFAULT_CTS_ACTION_BUTTON_HOVER_BORDER_COLOR = '#1F1F1F'; // Actual color default
    public const DEFAULT_CTS_FOOTER_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_FOOTER_TEXT_COLOR = '#6B6B6B';
    public const DEFAULT_CTS_FOOTER_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_SIDEBAR_BG_COLOR = '#FBFBFB';
    public const DEFAULT_CTS_SIDEBAR_TEXT_COLOR = '#262626';
    public const DEFAULT_CTS_SIDEBAR_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_SIDEBAR_ACTIVE_BG_COLOR = '#F5F5F5';
    public const DEFAULT_CTS_SIDEBAR_ACTIVE_TEXT_COLOR = '#343434';
    public const DEFAULT_CTS_SIDEBAR_HOVER_BG_COLOR = '#F5F5F5';
    public const DEFAULT_CTS_SIDEBAR_HOVER_TEXT_COLOR = '#343434';
    public const DEFAULT_CTS_ACTION_MENU_BG_COLOR = '#FFFFFF';
    public const DEFAULT_CTS_ACTION_MENU_BORDER_COLOR = '#E5E5E5';
    public const DEFAULT_CTS_ACTION_MENU_ITEM_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_ACTION_MENU_ITEM_HOVER_BG_COLOR = '#EDEDED';
    public const DEFAULT_CTS_ACTION_MENU_ITEM_HOVER_TEXT_COLOR = '#111111';
    public const DEFAULT_CTS_CONTAINER_MAX_WIDTH = 896; // px
    public const DEFAULT_CTS_POPUP_WIDTH = 450;         // px
    public const DEFAULT_CTS_CONTAINER_HEIGHT = 560;    // px
    public const DEFAULT_CTS_CONTAINER_MAX_HEIGHT = 70; // vh (number only)
    public const DEFAULT_CTS_CONTAINER_MIN_HEIGHT = 320;  // px
    public const DEFAULT_CTS_POPUP_HEIGHT = 560;        // px (can inherit from container_height)
    public const DEFAULT_CTS_POPUP_MIN_HEIGHT = 320;    // px (can inherit)
    public const DEFAULT_CTS_POPUP_MAX_HEIGHT = 70;     // vh (can inherit, number only)


    private $site_wide_manager;
    private $settings_saver;

    public function __construct()
    {
        // Load SiteWideBotManager
        if (!class_exists(SiteWideBotManager::class)) {
            $site_wide_path = __DIR__ . '/class-aipkit_site_wide_bot_manager.php';
            if (file_exists($site_wide_path)) {
                require_once $site_wide_path;
            }
        }
        if (class_exists(SiteWideBotManager::class)) {
            $this->site_wide_manager = new SiteWideBotManager();
        }

        // Load and instantiate AIPKit_Bot_Settings_Saver
        $saver_path = __DIR__ . '/class-aipkit-bot-settings-saver.php';
        if (!class_exists(AIPKit_Bot_Settings_Saver::class)) {
            if (file_exists($saver_path)) {
                require_once $saver_path;
            }
        }
        if (class_exists(AIPKit_Bot_Settings_Saver::class) && $this->site_wide_manager) {
            $this->settings_saver = new AIPKit_Bot_Settings_Saver($this->site_wide_manager);
        }
    }

    public function get_chatbot_settings(int $bot_id): array
    {
        $getter_path = __DIR__ . '/class-aipkit-bot-settings-getter.php';
        if (!class_exists(AIPKit_Bot_Settings_Getter::class)) {
            if (file_exists($getter_path)) {
                require_once $getter_path;
            } else {
                return [];
            }
        }
        return AIPKit_Bot_Settings_Getter::get($bot_id);
    }

    /**
     * @return bool|\WP_Error
     */
    public function save_bot_settings(int $botId, array $settings)
    {
        if (!$this->settings_saver) {
            return new \WP_Error('dependency_missing_manager_save', __('Settings saving component is missing.', 'gpt3-ai-content-generator'));
        }
        return $this->settings_saver->save($botId, $settings);
    }

    /**
     * Apply factory settings to a chatbot.
     *
     * @param int    $post_id     Chatbot post ID.
     * @param string $botName     Chatbot name.
     * @param string $deploy_mode Initial deployment mode: popup or inline.
     */
    public static function set_initial_bot_settings(int $post_id, string $botName, string $deploy_mode = 'popup')
    {
        $initializer_path = __DIR__ . '/class-aipkit-bot-settings-initializer.php';
        if (!class_exists(AIPKit_Bot_Settings_Initializer::class)) {
            if (file_exists($initializer_path)) {
                require_once $initializer_path;
            } else {
                return;
            }
        }
        AIPKit_Bot_Settings_Initializer::initialize($post_id, $botName, $deploy_mode);
    }

    /**
     * Returns the default conversation starter prompts for newly initialized bots.
     */
    public static function get_default_conversation_starters(): array
    {
        return [
            __('What can you do?', 'gpt3-ai-content-generator'),
            __('Tell me a fun fact', 'gpt3-ai-content-generator'),
        ];
    }

    /**
     * Returns the default conversation starter prompts as JSON for storage.
     */
    public static function get_default_conversation_starters_json(): string
    {
        $json = wp_json_encode(self::get_default_conversation_starters(), JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '[]';
    }

    /**
     * Returns the default quota reached message.
     */
    public static function get_default_token_limit_message(): string
    {
        return __('You have reached your quota for this period.', 'gpt3-ai-content-generator');
    }

    /**
     * Returns the supported recovery action types for quota notices.
     *
     * @return string[]
     */
    public static function get_token_limit_action_types(): array
    {
        return [
            'none',
            'dashboard_usage',
            'dashboard_credits',
            'dashboard_purchases',
            'buy_credits',
            'custom_url',
        ];
    }

    /**
     * Returns the UI labels for supported quota recovery action types.
     *
     * @return array<string, string>
     */
    public static function get_token_limit_action_options(): array
    {
        return [
            'none' => __('No button', 'gpt3-ai-content-generator'),
            'dashboard_usage' => __('Customer dashboard: Usage', 'gpt3-ai-content-generator'),
            'dashboard_credits' => __('Customer dashboard: Credits', 'gpt3-ai-content-generator'),
            'dashboard_purchases' => __('Customer dashboard: Purchases', 'gpt3-ai-content-generator'),
            'buy_credits' => __('Buy credits page', 'gpt3-ai-content-generator'),
            'custom_url' => __('Custom URL', 'gpt3-ai-content-generator'),
        ];
    }

    /**
     * Returns the default label for a quota recovery action type.
     */
    public static function get_token_limit_action_default_label(string $action_type): string
    {
        switch ($action_type) {
            case 'dashboard_usage':
                return __('View usage', 'gpt3-ai-content-generator');
            case 'dashboard_credits':
                return __('View credits', 'gpt3-ai-content-generator');
            case 'dashboard_purchases':
                return __('View purchases', 'gpt3-ai-content-generator');
            case 'buy_credits':
                return __('Buy credits', 'gpt3-ai-content-generator');
            case 'custom_url':
                return __('Open link', 'gpt3-ai-content-generator');
            case 'none':
            default:
                return '';
        }
    }

    /**
     * Returns default quota recovery action settings for newly initialized bots.
     *
     * @return array<string, string>
     */
    public static function get_default_token_limit_action_settings(): array
    {
        return [
            'primary_type' => self::DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_TYPE,
            'primary_label' => self::DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_LABEL,
            'primary_url' => self::DEFAULT_TOKEN_LIMIT_PRIMARY_ACTION_URL,
            'secondary_type' => self::DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_TYPE,
            'secondary_label' => self::DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_LABEL,
            'secondary_url' => self::DEFAULT_TOKEN_LIMIT_SECONDARY_ACTION_URL,
        ];
    }

    /**
     * Returns an array of default values for custom theme settings.
     * @return array
     */
    public static function get_custom_theme_defaults(): array
    {
        return [
            'primary_color' => self::DEFAULT_CTS_PRIMARY_COLOR,
            'secondary_color' => self::DEFAULT_CTS_SECONDARY_COLOR,
            'accent_color' => self::DEFAULT_CTS_ACCENT_COLOR,
            'app_bg_color' => self::DEFAULT_CTS_APP_BG_COLOR,
            'surface_color' => self::DEFAULT_CTS_SURFACE_COLOR,
            'text_color' => self::DEFAULT_CTS_TEXT_COLOR,
            'border_color' => self::DEFAULT_CTS_BORDER_COLOR,
            'auto_text_contrast' => self::DEFAULT_CTS_AUTO_TEXT_CONTRAST,
            'font_family' => self::DEFAULT_CUSTOM_THEME_FONT_FAMILY,
            'bubble_border_radius' => self::DEFAULT_CUSTOM_THEME_BUBBLE_BORDER_RADIUS,
            'container_border_radius' => self::DEFAULT_CTS_CONTAINER_BORDER_RADIUS,
            'container_bg_color' => self::DEFAULT_CTS_CONTAINER_BG_COLOR,
            'container_text_color' => self::DEFAULT_CTS_CONTAINER_TEXT_COLOR,
            'container_border_color' => self::DEFAULT_CTS_CONTAINER_BORDER_COLOR,
            'header_bg_color' => self::DEFAULT_CTS_HEADER_BG_COLOR,
            'header_text_color' => self::DEFAULT_CTS_HEADER_TEXT_COLOR,
            'header_border_color' => self::DEFAULT_CTS_HEADER_BORDER_COLOR,
            'messages_bg_color' => self::DEFAULT_CTS_MESSAGES_BG_COLOR,
            'messages_scrollbar_thumb_color' => self::DEFAULT_CTS_MESSAGES_SCROLLBAR_THUMB_COLOR,
            'messages_scrollbar_track_color' => self::DEFAULT_CTS_MESSAGES_SCROLLBAR_TRACK_COLOR,
            'bot_bubble_bg_color' => self::DEFAULT_CTS_BOT_BUBBLE_BG_COLOR,
            'bot_bubble_text_color' => self::DEFAULT_CTS_BOT_BUBBLE_TEXT_COLOR,
            'user_bubble_bg_color' => self::DEFAULT_CTS_USER_BUBBLE_BG_COLOR,
            'user_bubble_text_color' => self::DEFAULT_CTS_USER_BUBBLE_TEXT_COLOR,
            'input_area_bg_color' => self::DEFAULT_CTS_INPUT_AREA_BG_COLOR,
            'input_area_border_color' => self::DEFAULT_CTS_INPUT_AREA_BORDER_COLOR,
            'input_wrapper_bg_color' => self::DEFAULT_CTS_INPUT_WRAPPER_BG_COLOR,
            'input_wrapper_border_color' => self::DEFAULT_CTS_INPUT_WRAPPER_BORDER_COLOR,
            'input_text_color' => self::DEFAULT_CTS_INPUT_TEXT_COLOR,
            'input_placeholder_color' => self::DEFAULT_CTS_INPUT_PLACEHOLDER_COLOR,
            'input_focus_border_color' => self::DEFAULT_CTS_INPUT_FOCUS_BORDER_COLOR,
            'input_focus_shadow_color' => self::DEFAULT_CTS_INPUT_FOCUS_SHADOW_COLOR,
            'send_button_bg_color' => self::DEFAULT_CTS_SEND_BUTTON_BG_COLOR,
            'send_button_text_color' => self::DEFAULT_CTS_SEND_BUTTON_TEXT_COLOR,
            'action_button_bg_color' => self::DEFAULT_CTS_ACTION_BUTTON_BG_COLOR,
            'action_button_color' => self::DEFAULT_CTS_ACTION_BUTTON_COLOR,
            'action_button_border_color' => self::DEFAULT_CTS_ACTION_BUTTON_BORDER_COLOR,
            'action_button_hover_bg_color' => self::DEFAULT_CTS_ACTION_BUTTON_HOVER_BG_COLOR,
            'action_button_hover_color' => self::DEFAULT_CTS_ACTION_BUTTON_HOVER_COLOR,
            'action_button_hover_border_color' => self::DEFAULT_CTS_ACTION_BUTTON_HOVER_BORDER_COLOR,
            'footer_bg_color' => self::DEFAULT_CTS_FOOTER_BG_COLOR,
            'footer_text_color' => self::DEFAULT_CTS_FOOTER_TEXT_COLOR,
            'footer_border_color' => self::DEFAULT_CTS_FOOTER_BORDER_COLOR,
            'sidebar_bg_color' => self::DEFAULT_CTS_SIDEBAR_BG_COLOR,
            'sidebar_text_color' => self::DEFAULT_CTS_SIDEBAR_TEXT_COLOR,
            'sidebar_border_color' => self::DEFAULT_CTS_SIDEBAR_BORDER_COLOR,
            'sidebar_active_bg_color' => self::DEFAULT_CTS_SIDEBAR_ACTIVE_BG_COLOR,
            'sidebar_active_text_color' => self::DEFAULT_CTS_SIDEBAR_ACTIVE_TEXT_COLOR,
            'sidebar_hover_bg_color' => self::DEFAULT_CTS_SIDEBAR_HOVER_BG_COLOR,
            'sidebar_hover_text_color' => self::DEFAULT_CTS_SIDEBAR_HOVER_TEXT_COLOR,
            'action_menu_bg_color' => self::DEFAULT_CTS_ACTION_MENU_BG_COLOR,
            'action_menu_border_color' => self::DEFAULT_CTS_ACTION_MENU_BORDER_COLOR,
            'action_menu_item_text_color' => self::DEFAULT_CTS_ACTION_MENU_ITEM_TEXT_COLOR,
            'action_menu_item_hover_bg_color' => self::DEFAULT_CTS_ACTION_MENU_ITEM_HOVER_BG_COLOR,
            'action_menu_item_hover_text_color' => self::DEFAULT_CTS_ACTION_MENU_ITEM_HOVER_TEXT_COLOR,
            'container_max_width' => self::DEFAULT_CTS_CONTAINER_MAX_WIDTH,
            'popup_width' => self::DEFAULT_CTS_POPUP_WIDTH,
            'container_height' => self::DEFAULT_CTS_CONTAINER_HEIGHT,
            'container_max_height' => self::DEFAULT_CTS_CONTAINER_MAX_HEIGHT,
            'container_min_height' => self::DEFAULT_CTS_CONTAINER_MIN_HEIGHT,
            'popup_height' => self::DEFAULT_CTS_POPUP_HEIGHT,
            'popup_min_height' => self::DEFAULT_CTS_POPUP_MIN_HEIGHT,
            'popup_max_height' => self::DEFAULT_CTS_POPUP_MAX_HEIGHT,
        ];
    }

    /**
     * Removes deprecated custom theme overrides stored in post meta.
     */
    public static function cleanup_custom_theme_meta(int $bot_id): void
    {
        $allowed_keys = array_keys(self::get_custom_theme_defaults());
        $allowed_meta_keys = [];
        foreach ($allowed_keys as $key) {
            $allowed_meta_keys['_aipkit_cts_' . $key] = true;
        }

        $all_meta = get_post_meta($bot_id);
        foreach ($all_meta as $meta_key => $_) {
            if (strpos($meta_key, '_aipkit_cts_') === 0 && !isset($allowed_meta_keys[$meta_key])) {
                delete_post_meta($bot_id, $meta_key);
            }
        }
    }

    /**
     * Returns the preset palettes shown for the custom theme picker.
     * @return array<int, array<string, string>>
     */
    public static function get_custom_theme_presets(): array
    {
        return [
            [
                'key' => 'lagoon',
                'label' => __('Lagoon', 'gpt3-ai-content-generator'),
                'secondary' => '#ECFEFF',
                'primary' => '#0F766E',
            ],
            [
                'key' => 'ocean',
                'label' => __('Ocean', 'gpt3-ai-content-generator'),
                'secondary' => '#F1F5FF',
                'primary' => '#0B5FFF',
            ],
            [
                'key' => 'forest',
                'label' => __('Forest', 'gpt3-ai-content-generator'),
                'secondary' => '#F2FBF5',
                'primary' => '#1B5E20',
            ],
            [
                'key' => 'ember',
                'label' => __('Ember', 'gpt3-ai-content-generator'),
                'secondary' => '#FFF7ED',
                'primary' => '#C2410C',
            ],
            [
                'key' => 'rose',
                'label' => __('Rose', 'gpt3-ai-content-generator'),
                'secondary' => '#FFF1F2',
                'primary' => '#BE123C',
            ],
            [
                'key' => 'berry',
                'label' => __('Berry', 'gpt3-ai-content-generator'),
                'secondary' => '#F5F3FF',
                'primary' => '#7C3AED',
            ],
            [
                'key' => 'midnight',
                'label' => __('Midnight', 'gpt3-ai-content-generator'),
                'secondary' => '#0F172A',
                'primary' => '#60A5FA',
            ],
            [
                'key' => 'citrine',
                'label' => __('Citrine', 'gpt3-ai-content-generator'),
                'secondary' => '#FFFBEB',
                'primary' => '#CA8A04',
            ],
        ];
    }
}
