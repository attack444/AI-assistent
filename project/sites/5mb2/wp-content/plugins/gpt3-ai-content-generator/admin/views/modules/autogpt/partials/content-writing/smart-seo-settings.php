<?php
/**
 * Partial: Content Writing Automated Task - Smart SEO Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_task_cw_smart_seo_is_pro = class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
$aipkit_task_cw_seo_profile = class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')
    ? \WPAICG\SEO\AIPKit_SEO_Helper::get_active_plugin_profile()
    : [
        'profile' => 'aipkit',
        'label' => __('AIPKit SEO', 'gpt3-ai-content-generator'),
        'logo_url' => '',
        'logo_initials' => 'AI',
    ];
$aipkit_task_cw_seo_profile_label = isset($aipkit_task_cw_seo_profile['label']) ? (string) $aipkit_task_cw_seo_profile['label'] : __('AIPKit SEO', 'gpt3-ai-content-generator');
$aipkit_task_cw_seo_profile_key = isset($aipkit_task_cw_seo_profile['profile']) ? (string) $aipkit_task_cw_seo_profile['profile'] : 'aipkit';
$aipkit_task_cw_has_seo_plugin = isset($aipkit_task_cw_seo_profile['plugin']) && (string) $aipkit_task_cw_seo_profile['plugin'] !== 'none';
$aipkit_task_cw_seo_rules_class = '\\WPAICG\\ContentWriter\\SEO\\AIPKit_Content_Writer_Smart_SEO_Rules';
if (!class_exists($aipkit_task_cw_seo_rules_class) && defined('WPAICG_LIB_DIR')) {
    $aipkit_task_cw_seo_rules_path = WPAICG_LIB_DIR . 'content-writer/seo/class-aipkit-content-writer-smart-seo-rules.php';
    if (file_exists($aipkit_task_cw_seo_rules_path)) {
        require_once $aipkit_task_cw_seo_rules_path;
    }
}
$aipkit_task_cw_seo_rules_available = class_exists($aipkit_task_cw_seo_rules_class) && !empty($aipkit_task_cw_seo_rules_class::rule_catalog());
$aipkit_task_cw_seo_default_disabled_rules = class_exists('\\WPAICG\\ContentWriter\\SEO\\AIPKit_Content_Writer_SEO_Config')
    ? \WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config::default_disabled_rules()
    : '[]';
$aipkit_task_cw_smart_seo_upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
?>

<div
    class="aipkit_cw_ai_row aipkit_autogpt_question_row aipkit_cw_seo_settings_row aipkit_cw_smart_seo_feature_card aipkit_task_cw_smart_seo_settings_row<?php echo $aipkit_task_cw_smart_seo_is_pro ? '' : ' is-pro-locked'; ?>"
    data-aipkit-task-smart-seo-settings-row
    data-aipkit-seo-active-profile="<?php echo esc_attr($aipkit_task_cw_seo_profile_key); ?>"
    data-aipkit-seo-active-profile-label="<?php echo esc_attr($aipkit_task_cw_seo_profile_label); ?>"
    data-aipkit-seo-has-plugin="<?php echo $aipkit_task_cw_has_seo_plugin ? '1' : '0'; ?>"
>
    <div class="aipkit_cw_panel_label_wrap">
        <span class="aipkit_cw_panel_label aipkit_autogpt_question">
            <span class="aipkit_seo_settings_label">
                <?php if ($aipkit_task_cw_has_seo_plugin) : ?>
                    <?php
                    printf(
                        wp_kses(
                            /* translators: %s: active SEO plugin name. */
                            __('You are using <strong>%s</strong>. Optimize each post for SEO?', 'gpt3-ai-content-generator'),
                            ['strong' => []]
                        ),
                        esc_html($aipkit_task_cw_seo_profile_label)
                    );
                    ?>
                <?php else : ?>
                    <?php esc_html_e('Optimize each post for SEO?', 'gpt3-ai-content-generator'); ?>
                <?php endif; ?>
            </span>
        </span>
    </div>
    <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
        <div class="aipkit_cw_seo_inline_actions">
            <?php if ($aipkit_task_cw_smart_seo_is_pro) : ?>
                <select
                    id="aipkit_task_cw_seo_score_improvement_enabled"
                    name="seo_score_improvement_enabled"
                    class="aipkit_autosave_trigger"
                    data-aipkit-task-smart-seo-control
                    data-aipkit-task-smart-seo-main-toggle
                    data-aipkit-segmented-select
                >
                    <option value="0"><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    <option value="1" selected><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                </select>
            <?php else : ?>
                <a
                    class="aipkit_btn aipkit_btn-primary aipkit_autogpt_seo_inline_upgrade aipkit_pro_upgrade_button"
                    href="<?php echo esc_url($aipkit_task_cw_smart_seo_upgrade_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?>
                </a>
                <input type="hidden" name="seo_score_improvement_enabled" value="0" data-aipkit-task-smart-seo-control>
            <?php endif; ?>
        </div>
    </div>
    <input type="hidden" name="seo_score_continue_until_target" value="1" data-aipkit-task-smart-seo-control>
    <input type="hidden" name="seo_score_target" value="100" data-aipkit-task-smart-seo-control>
    <input type="hidden" name="seo_score_max_passes" value="3" data-aipkit-task-smart-seo-control>
    <input type="hidden" name="seo_score_profile" value="auto" data-aipkit-task-smart-seo-control>
    <input type="hidden" name="seo_score_disabled_rules" value="<?php echo esc_attr($aipkit_task_cw_seo_default_disabled_rules); ?>" data-aipkit-task-smart-seo-control data-aipkit-smart-seo-disabled-rules>
</div>

<?php if ($aipkit_task_cw_smart_seo_is_pro && $aipkit_task_cw_seo_rules_available) : ?>
    <div
        class="aipkit_cw_ai_row aipkit_autogpt_question_row aipkit_autogpt_seo_approach_row"
        data-aipkit-smart-seo-rules-action
        hidden
    >
        <div class="aipkit_cw_panel_label_wrap">
            <span
                class="aipkit_cw_panel_label aipkit_autogpt_question"
                id="aipkit_task_cw_smart_seo_approach_label"
            >
                <?php esc_html_e('How should we optimize it?', 'gpt3-ai-content-generator'); ?>
            </span>
        </div>
        <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
            <button
                type="button"
                class="aipkit_autogpt_seo_approach_trigger"
                id="aipkit_task_cw_smart_seo_rules_trigger"
                data-aipkit-smart-seo-modal-trigger
                data-aipkit-smart-seo-modal-target="aipkit_task_cw_smart_seo_rules_popover"
                aria-controls="aipkit_task_cw_smart_seo_rules_popover"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-labelledby="aipkit_task_cw_smart_seo_approach_label aipkit_task_cw_smart_seo_approach_value"
            >
                <span id="aipkit_task_cw_smart_seo_approach_value" data-aipkit-smart-seo-trigger-value>
                    <?php esc_html_e('Balanced', 'gpt3-ai-content-generator'); ?>
                </span>
                <span class="aipkit_autogpt_seo_approach_chevron" aria-hidden="true"></span>
            </button>
        </div>
    </div>
<?php endif; ?>

<div class="aipkit_cw_ai_row aipkit_autogpt_question_row aipkit_autogpt_seo_output_row">
    <div class="aipkit_cw_panel_label_wrap">
        <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_task_cw_generate_seo_slug">
            <?php esc_html_e('Optimize URL', 'gpt3-ai-content-generator'); ?>
        </label>
        <span class="aipkit_autogpt_question_helper">
            <?php esc_html_e('Create a concise, search-friendly URL.', 'gpt3-ai-content-generator'); ?>
        </span>
    </div>
    <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
        <select
            id="aipkit_task_cw_generate_seo_slug"
            name="generate_seo_slug"
            class="aipkit_autosave_trigger"
            data-aipkit-segmented-select
        >
            <option value="0" selected><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
            <option value="1"><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
        </select>
    </div>
</div>

<div class="aipkit_cw_ai_row aipkit_autogpt_question_row aipkit_autogpt_seo_output_row">
    <div class="aipkit_cw_panel_label_wrap">
        <label class="aipkit_cw_panel_label aipkit_autogpt_question" for="aipkit_task_cw_generate_toc">
            <?php esc_html_e('Table of contents', 'gpt3-ai-content-generator'); ?>
        </label>
        <span class="aipkit_autogpt_question_helper">
            <?php esc_html_e('Add navigation links to each post.', 'gpt3-ai-content-generator'); ?>
        </span>
    </div>
    <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
        <select
            id="aipkit_task_cw_generate_toc"
            name="generate_toc"
            class="aipkit_autosave_trigger"
            data-aipkit-segmented-select
        >
            <option value="0" selected><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
            <option value="1"><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
        </select>
    </div>
</div>

<?php
$aipkit_smart_seo_rules_popover_id = 'aipkit_task_cw_smart_seo_rules_popover';
$aipkit_smart_seo_rules_profile_key = $aipkit_task_cw_seo_profile_key;
$aipkit_smart_seo_rules_profile_label = $aipkit_task_cw_seo_profile_label;
$aipkit_smart_seo_rules_modal_mode = true;
$aipkit_smart_seo_rules_popover_path = defined('WPAICG_LIB_DIR') ? WPAICG_LIB_DIR . 'views/modules/shared/smart-seo-rules-popover.php' : '';
if ($aipkit_task_cw_smart_seo_is_pro && $aipkit_task_cw_seo_rules_available && $aipkit_smart_seo_rules_popover_path !== '' && file_exists($aipkit_smart_seo_rules_popover_path)) {
    include $aipkit_smart_seo_rules_popover_path;
}
?>
