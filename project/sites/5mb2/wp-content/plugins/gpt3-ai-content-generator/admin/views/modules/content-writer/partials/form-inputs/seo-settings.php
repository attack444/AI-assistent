<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$is_pro = class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
$seo_profile = class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')
    ? \WPAICG\SEO\AIPKit_SEO_Helper::get_active_plugin_profile()
    : [
        'profile' => 'aipkit',
        'label' => __('AIPKit SEO', 'gpt3-ai-content-generator'),
    ];
$seo_profile_label = isset($seo_profile['label']) ? (string) $seo_profile['label'] : __('AIPKit SEO', 'gpt3-ai-content-generator');
$seo_profile_key = isset($seo_profile['profile']) ? (string) $seo_profile['profile'] : 'aipkit';
$seo_profile_logo_url = isset($seo_profile['logo_url']) ? (string) $seo_profile['logo_url'] : '';
$seo_profile_logo_initials = isset($seo_profile['logo_initials']) ? (string) $seo_profile['logo_initials'] : 'SEO';
$seo_rules_class = '\\WPAICG\\ContentWriter\\SEO\\AIPKit_Content_Writer_Smart_SEO_Rules';
if (!class_exists($seo_rules_class) && defined('WPAICG_LIB_DIR')) {
    $seo_rules_path = WPAICG_LIB_DIR . 'content-writer/seo/class-aipkit-content-writer-smart-seo-rules.php';
    if (file_exists($seo_rules_path)) {
        require_once $seo_rules_path;
    }
}
$seo_rules_available = class_exists($seo_rules_class) && !empty($seo_rules_class::rule_catalog());
$seo_default_disabled_rules = class_exists('\\WPAICG\\ContentWriter\\SEO\\AIPKit_Content_Writer_SEO_Config')
    ? \WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config::default_disabled_rules()
    : '[]';
$upgrade_url = admin_url('admin.php?page=wpaicg-pricing');
?>

<div
    class="aipkit_cw_ai_row aipkit_cw_seo_settings_row aipkit_cw_smart_seo_feature_card<?php echo $is_pro ? '' : ' is-pro-locked'; ?>"
    data-aipkit-seo-settings-row
    data-aipkit-seo-active-profile="<?php echo esc_attr($seo_profile_key); ?>"
    data-aipkit-seo-active-profile-label="<?php echo esc_attr($seo_profile_label); ?>"
    data-aipkit-seo-active-profile-logo="<?php echo esc_url($seo_profile_logo_url); ?>"
    data-aipkit-seo-active-profile-initials="<?php echo esc_attr($seo_profile_logo_initials); ?>"
>
    <div class="aipkit_cw_panel_label_wrap">
        <label class="aipkit_cw_panel_label"<?php echo $is_pro ? ' for="aipkit_cw_seo_score_improvement_enabled"' : ''; ?>>
            <span class="aipkit_seo_settings_label">
                <span><?php esc_html_e('Smart SEO', 'gpt3-ai-content-generator'); ?></span>
                <span
                    class="aipkit_seo_profile_logo aipkit_seo_settings_logo"
                    title="<?php echo esc_attr($seo_profile_label); ?>"
                    aria-label="<?php echo esc_attr($seo_profile_label); ?>"
                    role="img"
                >
                    <?php if ($seo_profile_logo_url !== '') : ?>
                        <img src="<?php echo esc_url($seo_profile_logo_url); ?>" alt="">
                    <?php else : ?>
                        <span><?php echo esc_html($seo_profile_logo_initials); ?></span>
                    <?php endif; ?>
                </span>
            </span>
        </label>
        <span class="aipkit_cw_smart_seo_feature_helper">
            <?php esc_html_e('Automatically refines content until it achieves a higher SEO score.', 'gpt3-ai-content-generator'); ?>
        </span>
    </div>
    <div class="aipkit_cw_ai_control aipkit_cw_ai_control--compact">
        <div class="aipkit_cw_seo_inline_actions">
            <?php if ($is_pro): ?>
                <label class="aipkit_switch aipkit_cw_seo_inline_switch" title="<?php esc_attr_e('Smart SEO auto-improvement', 'gpt3-ai-content-generator'); ?>">
                    <input
                        type="checkbox"
                        id="aipkit_cw_seo_score_improvement_enabled"
                        name="seo_score_improvement_enabled"
                        class="aipkit_toggle_switch aipkit_autosave_trigger"
                        value="1"
                        data-aipkit-seo-control
                        data-aipkit-seo-main-toggle
                    >
                    <span class="aipkit_switch_slider"></span>
                </label>
                <?php if ($seo_rules_available) : ?>
                    <button
                        type="button"
                        class="aipkit_cw_settings_icon_trigger aipkit_cw_seo_rules_trigger"
                        id="aipkit_cw_smart_seo_rules_trigger"
                        data-aipkit-popover-target="aipkit_cw_smart_seo_rules_popover"
                        data-aipkit-popover-placement="left"
                        aria-controls="aipkit_cw_smart_seo_rules_popover"
                        aria-expanded="false"
                        aria-label="<?php esc_attr_e('Smart SEO rules', 'gpt3-ai-content-generator'); ?>"
                        title="<?php esc_attr_e('Smart SEO rules', 'gpt3-ai-content-generator'); ?>"
                    >
                        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <a
                    class="aipkit_cw_seo_upgrade_btn aipkit_pro_badge"
                    href="<?php echo esc_url($upgrade_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php esc_html_e('Pro', 'gpt3-ai-content-generator'); ?>
                </a>
                <input type="hidden" name="seo_score_improvement_enabled" value="0" data-aipkit-seo-control>
            <?php endif; ?>
            <input type="hidden" name="seo_score_continue_until_target" value="1" data-aipkit-seo-control>
            <input type="hidden" name="seo_score_target" value="100" data-aipkit-seo-control>
            <input type="hidden" name="seo_score_max_passes" value="3" data-aipkit-seo-control>
            <input type="hidden" name="seo_score_profile" value="auto" data-aipkit-seo-control>
            <input type="hidden" name="seo_score_disabled_rules" value="<?php echo esc_attr($seo_default_disabled_rules); ?>" class="aipkit_autosave_trigger" data-aipkit-seo-control data-aipkit-smart-seo-disabled-rules>
        </div>
    </div>
</div>

<?php
$aipkit_smart_seo_rules_popover_id = 'aipkit_cw_smart_seo_rules_popover';
$aipkit_smart_seo_rules_profile_key = $seo_profile_key;
$aipkit_smart_seo_rules_profile_label = $seo_profile_label;
$aipkit_smart_seo_rules_modal_mode = false;
$aipkit_smart_seo_rules_popover_path = defined('WPAICG_LIB_DIR') ? WPAICG_LIB_DIR . 'views/modules/shared/smart-seo-rules-popover.php' : '';
if ($is_pro && $seo_rules_available && $aipkit_smart_seo_rules_popover_path !== '' && file_exists($aipkit_smart_seo_rules_popover_path)) {
    include $aipkit_smart_seo_rules_popover_path;
}
?>
