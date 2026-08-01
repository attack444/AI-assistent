<?php
/**
 * Content Writer Google Sheets Mode tab (module-specific).
 *
 * @since NEXT_VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// $is_pro is available from the parent scope (loader-vars.php)
$aipkit_premium_partial = WPAICG_LIB_DIR . 'views/modules/content-writer/partials/form-inputs/mode-gsheets.php';

if ($is_pro && file_exists($aipkit_premium_partial)) {
    include $aipkit_premium_partial;
    return;
}

$aipkit_feature_promo_class = 'aipkit_feature_promo--gsheets';
$aipkit_feature_promo_dashicon = 'dashicons-media-spreadsheet';
$aipkit_feature_promo_title = __('Google Sheets content import', 'gpt3-ai-content-generator');
$aipkit_feature_promo_subtitle = __('Turn spreadsheet rows into ready-to-publish WordPress posts.', 'gpt3-ai-content-generator');
$aipkit_feature_promo_steps = [
    __('Connect a sheet', 'gpt3-ai-content-generator'),
    __('Map your columns', 'gpt3-ai-content-generator'),
    __('Generate in bulk', 'gpt3-ai-content-generator'),
];
$aipkit_feature_promo_cards = [
    ['dashicon' => 'dashicons-update', 'label' => __('Live sync', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-editor-table', 'label' => __('Column mapping', 'gpt3-ai-content-generator')],
    ['dashicon' => 'dashicons-controls-repeat', 'label' => __('Bulk generation', 'gpt3-ai-content-generator')],
];
$aipkit_feature_promo_compact = true;
$aipkit_feature_promo_show_pro_badge = true;
$aipkit_feature_promo_upgrade_label = __('Upgrade', 'gpt3-ai-content-generator');
$aipkit_feature_promo_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/feature-promo.php';
