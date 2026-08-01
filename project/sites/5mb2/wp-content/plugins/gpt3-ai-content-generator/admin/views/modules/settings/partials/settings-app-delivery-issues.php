<?php
/**
 * Partial: App Delivery Issues
 */
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$is_pro_plan = class_exists('\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
$log_store_class = '\WPAICG\\Lib\\Integrations\\Logs\\AIPKit_Recipe_Delivery_Log_Store';
$recipe_store_class = '\WPAICG\\Lib\\Integrations\\Recipes\\AIPKit_Stored_Recipes';

if (
    !$is_pro_plan ||
    !class_exists($log_store_class) ||
    !class_exists($recipe_store_class) ||
    !method_exists($log_store_class, 'get_recent_failed_attempts')
) {
    return;
}

$delivery_issues = $log_store_class::get_recent_failed_attempts(5);
if (empty($delivery_issues)) {
    return;
}
?>
<section id="aipkit_settings_app_delivery_issues_section">
    <div class="aipkit_form-group aipkit_settings_simple_row aipkit_settings_simple_row--app-delivery-issues" id="aipkit_settings_app_delivery_issues_row">
        <div class="aipkit_form-label">
            <?php esc_html_e('Delivery Issues', 'gpt3-ai-content-generator'); ?>
            <span class="aipkit_form-label-helper"><?php esc_html_e('Showing the 5 most recent failed recipe deliveries.', 'gpt3-ai-content-generator'); ?></span>
        </div>
        <div class="aipkit_settings_app_delivery_issues_main">
            <div class="aipkit_settings_app_delivery_issues_toolbar">
                <span class="aipkit_settings_app_delivery_issues_toolbar_note"><?php esc_html_e('Clear removes items from this list immediately. Old rows are still purged automatically by retention cleanup.', 'gpt3-ai-content-generator'); ?></span>
                <button type="button" class="button button-secondary aipkit_btn aipkit_btn-danger" data-aipkit-clear-all-delivery-issues>
                    <span class="aipkit_btn-text"><?php esc_html_e('Clear All', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_spinner"></span>
                </button>
            </div>
            <div class="aipkit_settings_app_delivery_issue_list" data-aipkit-app-delivery-issue-list>
                <?php foreach ($delivery_issues as $issue) : ?>
                    <?php
                    $issue_uuid = sanitize_text_field((string) ($issue['attempt_uuid'] ?? ''));
                    $request_summary = isset($issue['request_summary']) && is_array($issue['request_summary']) ? $issue['request_summary'] : [];
                    $response_summary = isset($issue['response_summary']) && is_array($issue['response_summary']) ? $issue['response_summary'] : [];
                    $issue_recipe = method_exists($recipe_store_class, 'get_recipe_by_id')
                        ? $recipe_store_class::get_recipe_by_id((string) ($issue['recipe_id'] ?? ''))
                        : null;
                    $issue_type = sanitize_key((string) ($response_summary['status'] ?? 'error'));
                    $issue_label = $issue_type === 'reauth_required'
                        ? __('Reauth Required', 'gpt3-ai-content-generator')
                        : __('Failed', 'gpt3-ai-content-generator');
                    $issue_message = sanitize_text_field((string) (($response_summary['message'] ?? '') ?: ($issue['error_message'] ?? '')));
                    $issue_recipe_name = sanitize_text_field((string) (($request_summary['recipe_name'] ?? '') ?: ($issue_recipe['name'] ?? __('Recipe', 'gpt3-ai-content-generator'))));
                    $issue_connection_name = sanitize_text_field((string) ($request_summary['connection_name'] ?? ''));
                    $is_replayable = $issue_uuid !== '' && !empty($issue['event_snapshot']) && is_array($issue_recipe);
                    ?>
                    <article class="aipkit_settings_app_delivery_issue" data-aipkit-app-delivery-issue data-attempt-uuid="<?php echo esc_attr($issue_uuid); ?>">
                        <div class="aipkit_settings_app_delivery_issue_header">
                            <div class="aipkit_settings_app_delivery_issue_heading">
                                <strong><?php echo esc_html($issue_recipe_name); ?></strong>
                                <span class="aipkit_settings_app_delivery_issue_meta">
                                    <?php echo esc_html(sanitize_text_field((string) ($issue['event_name'] ?? ''))); ?>
                                    <?php if ($issue_connection_name !== '') : ?>
                                        <?php echo esc_html(' • ' . $issue_connection_name); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="aipkit_settings_app_delivery_issue_status aipkit_settings_app_delivery_issue_status--<?php echo esc_attr($issue_type === 'reauth_required' ? 'reauth' : 'failed'); ?>">
                                <?php echo esc_html($issue_label); ?>
                            </span>
                        </div>
                        <p class="aipkit_settings_app_delivery_issue_message"><?php echo esc_html($issue_message); ?></p>
                        <div class="aipkit_settings_app_delivery_issue_footer">
                            <span class="aipkit_settings_app_delivery_issue_time"><?php echo esc_html(sanitize_text_field((string) ($issue['created_at'] ?? ''))); ?></span>
                            <div class="aipkit_settings_app_delivery_issue_actions">
                                <button type="button" class="button button-secondary aipkit_btn aipkit_btn-danger" data-aipkit-clear-delivery-issue data-attempt-uuid="<?php echo esc_attr($issue_uuid); ?>">
                                    <span class="aipkit_btn-text"><?php esc_html_e('Clear', 'gpt3-ai-content-generator'); ?></span>
                                    <span class="aipkit_spinner"></span>
                                </button>
                                <?php if ($is_replayable) : ?>
                                    <button type="button" class="button button-secondary aipkit_btn" data-aipkit-replay-delivery-issue data-attempt-uuid="<?php echo esc_attr($issue_uuid); ?>">
                                        <span class="aipkit_btn-text"><?php esc_html_e('Replay', 'gpt3-ai-content-generator'); ?></span>
                                        <span class="aipkit_spinner"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
