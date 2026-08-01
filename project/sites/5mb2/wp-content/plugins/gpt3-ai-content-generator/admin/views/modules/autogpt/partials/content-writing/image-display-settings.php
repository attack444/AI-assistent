<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only sets local variables for the shared view partial.

$aipkit_image_display_settings_id_prefix = 'aipkit_task_cw';
$aipkit_image_display_settings_render_mode = 'inline';
$aipkit_image_display_settings_autosave = false;
$aipkit_image_display_settings_excluded_common_fields = [
    'image_count',
    'image_placement',
    'image_placement_param_x',
    'image_size',
    'image_alignment',
];
$aipkit_image_display_settings_placement_extra_class = 'aipkit_task_cw_image_placement_select';
$aipkit_image_display_settings_pixabay_orientation_helper = __('Choose landscape or portrait images.', 'gpt3-ai-content-generator');
$aipkit_image_display_settings_pixabay_type_helper = __('Filter results by image type.', 'gpt3-ai-content-generator');
$aipkit_image_display_settings_pixabay_category_helper = __('Limit results to a subject area.', 'gpt3-ai-content-generator');

include WPAICG_PLUGIN_DIR . 'admin/views/modules/shared/image-display-settings.php';
