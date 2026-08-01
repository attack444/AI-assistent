<?php
/**
 * Partial: Automated Task Form - Content Enhancement Source Settings Wrapper
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$aipkit_premium_partial = WPAICG_LIB_DIR . 'views/modules/autogpt/partials/content-enhancement/source-settings.php';

if (!empty($is_pro) && file_exists($aipkit_premium_partial)) {
    include $aipkit_premium_partial;
    return;
}

$aipkit_feature_promo_class = 'aipkit_feature_promo--content-enhance';
$aipkit_feature_promo_dashicon = 'dashicons-update';
$aipkit_feature_promo_title = __('Rewrite existing content', 'gpt3-ai-content-generator');
$aipkit_feature_promo_subtitle = __('Refresh and improve existing WordPress content automatically.', 'gpt3-ai-content-generator');
$aipkit_feature_promo_steps = [
    __('Choose existing posts', 'gpt3-ai-content-generator'),
    __('AI rewrites the content', 'gpt3-ai-content-generator'),
    __('Update automatically', 'gpt3-ai-content-generator'),
];
$aipkit_feature_promo_cards = [
    ['dashicon' => 'dashicons-edit', 'label' => __('Rewrite and polish', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-admin-generic', 'label' => __('Custom instructions', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-update', 'label' => __('Bulk processing', 'gpt3-ai-content-generator')],
];
$aipkit_feature_promo_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
$aipkit_feature_promo_compact = true;
$aipkit_feature_promo_show_pro_badge = true;
$aipkit_feature_promo_upgrade_label = __('Upgrade', 'gpt3-ai-content-generator');
include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/feature-promo.php';
