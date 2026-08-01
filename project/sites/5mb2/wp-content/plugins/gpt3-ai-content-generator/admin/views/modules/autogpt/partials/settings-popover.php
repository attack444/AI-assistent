<?php
/**
 * Partial: AutoGPT Settings Popover
 * Current: Cron status summary (future options will be added here).
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!empty($aipkit_autogpt_cron_summary)) : ?>
    <?php
    $aipkit_cron_state = !empty($aipkit_autogpt_cron_summary['state']) ? (string) $aipkit_autogpt_cron_summary['state'] : 'enabled';
    $aipkit_cron_health_copy = __('Automation scheduling is healthy.', 'gpt3-ai-content-generator');
    $aipkit_cron_health_icon = 'dashicons-yes-alt';
    if ($aipkit_cron_state === 'disabled') {
        $aipkit_cron_health_copy = __('Automated tasks will not run.', 'gpt3-ai-content-generator');
        $aipkit_cron_health_icon = 'dashicons-warning';
    } elseif ($aipkit_cron_state === 'overdue') {
        $aipkit_cron_health_copy = __('The next scheduled run is delayed.', 'gpt3-ai-content-generator');
        $aipkit_cron_health_icon = 'dashicons-warning';
    }
    ?>
    <div class="aipkit_autogpt_settings_section">
        <h3 class="aipkit_autogpt_settings_title"><?php esc_html_e('Cron status', 'gpt3-ai-content-generator'); ?></h3>
        <div class="aipkit_autogpt_cron_health">
            <span class="aipkit_autogpt_cron_health_icon" aria-hidden="true">
                <span class="dashicons <?php echo esc_attr($aipkit_cron_health_icon); ?>"></span>
            </span>
            <span class="aipkit_autogpt_cron_health_copy">
                <strong><?php echo esc_html($aipkit_autogpt_cron_summary['status_label']); ?></strong>
                <span><?php echo esc_html($aipkit_cron_health_copy); ?></span>
            </span>
        </div>
        <div class="aipkit_autogpt_settings_list">
            <div class="aipkit_autogpt_settings_item">
                <span class="aipkit_autogpt_settings_key"><?php esc_html_e('Next run', 'gpt3-ai-content-generator'); ?></span>
                <?php $aipkit_cron_next_ts = !empty($aipkit_autogpt_cron_summary['next_timestamp']) ? (int) $aipkit_autogpt_cron_summary['next_timestamp'] : 0; ?>
                <span
                    class="aipkit_autogpt_settings_value"
                    <?php if ($aipkit_cron_next_ts > 0) : ?>
                        data-aipkit-cron-timestamp="<?php echo esc_attr($aipkit_cron_next_ts); ?>"
                    <?php endif; ?>
                >
                    <?php echo esc_html($aipkit_autogpt_cron_summary['next_label']); ?>
                </span>
            </div>
            <div class="aipkit_autogpt_settings_item">
                <span class="aipkit_autogpt_settings_key"><?php esc_html_e('Scheduled tasks', 'gpt3-ai-content-generator'); ?></span>
                <span class="aipkit_autogpt_settings_value"><?php echo esc_html(number_format_i18n((int) $aipkit_autogpt_cron_summary['task_count'])); ?></span>
            </div>
        </div>
        <?php if (!empty($aipkit_autogpt_cron_summary['tip'])) : ?>
            <p class="aipkit_autogpt_settings_tip"><?php echo wp_kses_post($aipkit_autogpt_cron_summary['tip']); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
