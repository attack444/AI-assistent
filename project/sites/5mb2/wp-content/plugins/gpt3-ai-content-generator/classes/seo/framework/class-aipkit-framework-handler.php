<?php


namespace WPAICG\SEO\Framework;

use WPAICG\SEO\AIPKit_Base_SEO_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handler for The SEO Framework plugin interactions.
 * Delegates logic to namespaced functions.
 */
class AIPKit_Framework_Handler extends AIPKit_Base_SEO_Handler
{
    protected const LOGIC_DIR = __DIR__;
    protected const LOGIC_NAMESPACE = __NAMESPACE__;
}
