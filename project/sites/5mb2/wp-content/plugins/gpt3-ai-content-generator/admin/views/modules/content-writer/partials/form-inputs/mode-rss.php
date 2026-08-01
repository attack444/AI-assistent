<?php
/**
 * Content Writer RSS Mode tab (module-specific).
 *
 * @since NEXT_VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// $is_pro is available from the parent scope (loader-vars.php)
$aipkit_show_rss_fetch_button = !empty($aipkit_show_rss_fetch_button);
$aipkit_premium_partial = WPAICG_LIB_DIR . 'views/modules/content-writer/partials/form-inputs/mode-rss.php';

if ($is_pro && file_exists($aipkit_premium_partial)) {
    include $aipkit_premium_partial;
    return;
}

$aipkit_feature_promo_class = 'aipkit_feature_promo--rss';
$aipkit_feature_promo_dashicon = 'dashicons-rss';
$aipkit_feature_promo_title = __('RSS feed content generation', 'gpt3-ai-content-generator');
$aipkit_feature_promo_subtitle = __('Turn RSS feeds into unique, AI-written posts — hands-free.', 'gpt3-ai-content-generator');
$aipkit_feature_promo_steps = [
    __('Add feed URLs', 'gpt3-ai-content-generator'),
    __('AI rewrites each item', 'gpt3-ai-content-generator'),
    __('Publish automatically', 'gpt3-ai-content-generator'),
];
$aipkit_feature_promo_cards = [
    ['dashicon' => 'dashicons-screenoptions', 'label' => __('Multiple feeds', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-admin-generic', 'label' => __('Smart parsing', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-clock', 'label' => __('Auto-schedule', 'gpt3-ai-content-generator')],
];
$aipkit_feature_promo_compact = true;
$aipkit_feature_promo_show_pro_badge = true;
$aipkit_feature_promo_upgrade_label = __('Upgrade', 'gpt3-ai-content-generator');
$aipkit_feature_promo_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/feature-promo.php';
