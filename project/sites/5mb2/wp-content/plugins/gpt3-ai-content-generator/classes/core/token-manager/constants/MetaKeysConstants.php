<?php


namespace WPAICG\Core\TokenManager\Constants;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MetaKeysConstants
{
    // User's persistent token balance (from purchases)
    public const TOKEN_BALANCE_META_KEY = '_aipkit_token_balance';

    // Chat specific prefixes
    public const CHAT_USAGE_META_KEY_PREFIX = '_aipkit_token_usage_';
    public const CHAT_RESET_META_KEY_PREFIX = '_aipkit_last_token_reset_';

    // Image Generator specific prefixes (user meta)
    public const IMG_USAGE_META_KEY = '_aipkit_img_tokens_used';
    public const IMG_RESET_META_KEY = '_aipkit_img_tokens_reset';

    // AI Forms specific prefixes (user meta)
    public const AIFORMS_USAGE_META_KEY = '_aipkit_aiforms_tokens_used';
    public const AIFORMS_RESET_META_KEY = '_aipkit_aiforms_tokens_reset';
}
