<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only sets local variables for the shared view partial.

$aipkit_image_display_settings_id_prefix = 'aipkit_cw';
$aipkit_image_display_settings_render_mode = $aipkit_cw_image_display_settings_render_mode ?? 'both';
$aipkit_image_display_settings_autosave = true;
$aipkit_image_display_settings_trigger_hidden = false;
$aipkit_image_display_settings_placement_extra_class = '';

include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/image-display-settings.php';
