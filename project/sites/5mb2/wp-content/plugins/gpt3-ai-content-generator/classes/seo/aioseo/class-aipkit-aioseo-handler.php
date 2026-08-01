<?php


namespace WPAICG\SEO\AIOSEO;

use WPAICG\SEO\AIPKit_Base_SEO_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handler for All in One SEO (AIOSEO) plugin interactions.
 * Delegates logic to namespaced functions.
 */
class AIPKit_AIOSEO_Handler extends AIPKit_Base_SEO_Handler
{
    protected const LOGIC_DIR = __DIR__;
    protected const LOGIC_NAMESPACE = __NAMESPACE__;
}
