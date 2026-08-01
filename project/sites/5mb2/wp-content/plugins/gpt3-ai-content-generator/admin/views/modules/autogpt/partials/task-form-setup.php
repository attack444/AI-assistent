<?php
/**
 * Partial: Automated Task Form - Task Setup Section
 * UPDATED: Replaced single Task Type dropdown with a two-step Category/Type selection.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
// Variables from parent: $task_categories, $frequencies, $aipkit_task_statuses_for_select, etc.
?>
<select id="aipkit_automated_task_category" name="task_category" class="aipkit_form-input" hidden aria-hidden="true" tabindex="-1">
    <?php foreach ($task_categories as $cat_key => $cat_label) : ?>
        <option value="<?php echo esc_attr($cat_key); ?>"><?php echo esc_html($cat_label); ?></option>
    <?php endforeach; ?>
</select>
<div id="aipkit_task_ci_source_wrapper" class="aipkit_task_config_section" style="display: none;">
    <?php include __DIR__ . '/content-indexing/source-settings.php'; ?>
</div>
<div id="aipkit_task_cw_input_modes_wrapper" class="aipkit_task_config_section" style="display: none;">
    <?php // This hidden input will be set by JS based on the selected task_type?>
    <input type="hidden" name="cw_generation_mode" id="aipkit_task_cw_generation_mode" value="">
    <select id="aipkit_automated_task_type" name="task_type" class="aipkit_form-input" hidden aria-hidden="true" tabindex="-1" disabled>
        <option value=""><?php esc_html_e('-- Select a category first --', 'gpt3-ai-content-generator'); ?></option>
    </select>

    <?php include __DIR__ . '/content-writing/input-mode-bulk.php'; ?>
    <?php include __DIR__ . '/content-writing/input-mode-csv.php'; ?>

    <?php // Pro Feature: RSS Mode?>
    <div id="aipkit_task_cw_input_mode_rss" class="aipkit_task_cw_input_mode_section" style="display:none;">
        <?php
        $rss_partial = WPAICG_PLUGIN_DIR . 'admin/views/modules/content-writer/partials/form-inputs/mode-rss.php';
        if (file_exists($rss_partial)) {
            $aipkit_show_rss_fetch_button = true;
            $aipkit_autogpt_source_mode = true;
            include $rss_partial;
            unset($aipkit_show_rss_fetch_button, $aipkit_autogpt_source_mode);
        } else {
            echo '<p>This is a Pro feature. Please upgrade to access the RSS feature.</p>';
        }
        ?>
    </div>

    <?php // Pro Feature: URL Mode?>
    <div id="aipkit_task_cw_input_mode_url" class="aipkit_task_cw_input_mode_section" style="display:none;">
        <?php
$url_partial = WPAICG_PLUGIN_DIR . 'admin/views/modules/content-writer/partials/form-inputs/mode-url.php';
if (file_exists($url_partial)) {
    $aipkit_autogpt_source_mode = true;
    include $url_partial;
    unset($aipkit_autogpt_source_mode);
} else {
    echo '<p>This is a Pro feature. Please upgrade to access the URL feature.</p>';
}
?>
    </div>

    <?php // Pro Feature: Google Sheets Mode?>
    <div id="aipkit_task_cw_input_mode_gsheets" class="aipkit_task_cw_input_mode_section" style="display:none;">
        <?php
$gsheets_partial = WPAICG_PLUGIN_DIR . 'admin/views/modules/content-writer/partials/form-inputs/mode-gsheets.php';
if (file_exists($gsheets_partial)) {
    $aipkit_autogpt_source_mode = true;
    include $gsheets_partial;
    unset($aipkit_autogpt_source_mode);
} else {
    echo '<p>This is a Pro feature. Please upgrade to access the Google Sheets feature.</p>';
}
?>
    </div>
</div>
<div id="aipkit_task_cc_source_wrapper" class="aipkit_task_config_section" style="display: none;">
    <?php
$comment_reply_source_partial = __DIR__ . '/community-engagement/source-settings.php';
if (file_exists($comment_reply_source_partial)) {
    include $comment_reply_source_partial;
} else {
    echo '<p>Error: Comment Reply Source Settings UI partial is missing.</p>';
}
?>
</div>
<div id="aipkit_task_ce_content_selection_wrapper" class="aipkit_task_config_section" style="display: none;">
    <?php
    $content_enhancement_source_partial = __DIR__ . '/content-enhancement/source-settings.php';
	if (file_exists($content_enhancement_source_partial)) {
	    include $content_enhancement_source_partial;
	} else {
	    echo '<p>Error: Content Enhancement Source Settings UI partial is missing.</p>';
	}
?>
</div>
