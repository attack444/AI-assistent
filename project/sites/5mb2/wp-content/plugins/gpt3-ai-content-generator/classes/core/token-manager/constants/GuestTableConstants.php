<?php

namespace WPAICG\Core\TokenManager\Constants;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GuestTableConstants {
    const GUEST_TABLE_NAME_SUFFIX = 'aipkit_guest_token_usage';
    const IMG_GEN_GUEST_CONTEXT_ID = 0; // Special context ID for image generator guest usage
    const AI_FORMS_GUEST_CONTEXT_ID = 1; // Special context ID for AI Forms guest usage
    const CONTENT_WRITER_GUEST_CONTEXT_ID = 2; // Special context ID for Content Writer guest usage
}