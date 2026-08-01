<?php
/**
 * Content Writer URL Mode tab (module-specific).
 *
 * @since NEXT_VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// $is_pro is available from the parent scope (loader-vars.php)
$aipkit_premium_partial = WPAICG_LIB_DIR . 'views/modules/content-writer/partials/form-inputs/mode-url.php';

if ($is_pro && file_exists($aipkit_premium_partial)) {
    include $aipkit_premium_partial;
    return;
}

$aipkit_feature_promo_class = 'aipkit_feature_promo--url';
$aipkit_feature_promo_dashicon = 'dashicons-admin-links';
$aipkit_feature_promo_title = __('URL content extraction', 'gpt3-ai-content-generator');
$aipkit_feature_promo_subtitle = __('Turn web pages into fresh, AI-written content.', 'gpt3-ai-content-generator');
$aipkit_feature_promo_steps = [
    __('Add page URLs', 'gpt3-ai-content-generator'),
    __('AI extracts the content', 'gpt3-ai-content-generator'),
    __('Create unique posts', 'gpt3-ai-content-generator'),
];
$aipkit_feature_promo_cards = [
    ['dashicon' => 'dashicons-admin-site-alt3', 'label' => __('Any website', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-filter', 'label' => __('Smart extraction', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-update', 'label' => __('Bulk processing', 'gpt3-ai-content-generator')],
];
$aipkit_feature_promo_compact = true;
$aipkit_feature_promo_show_pro_badge = true;
$aipkit_feature_promo_upgrade_label = __('Upgrade', 'gpt3-ai-content-generator');
$aipkit_feature_promo_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/feature-promo.php';
