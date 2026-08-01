<?php


namespace WPAICG\SEO\RankMath;

use WPAICG\SEO\AIPKit_Base_SEO_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handler for Rank Math SEO plugin interactions.
 * Delegates logic to namespaced functions.
 */
class AIPKit_Rank_Math_Handler extends AIPKit_Base_SEO_Handler
{
    protected const LOGIC_DIR = __DIR__;
    protected const LOGIC_NAMESPACE = __NAMESPACE__;
}
