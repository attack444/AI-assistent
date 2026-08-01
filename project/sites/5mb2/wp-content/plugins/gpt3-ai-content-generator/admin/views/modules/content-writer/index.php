<?php
/**
 * AIPKit Content Writer Module - Main View
 * UPDATED: Re-architected into a two-column layout with a central tabbed input panel and action bar.
 * MODIFIED: Moved status indicators above the mode selector.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

require_once __DIR__ . '/partials/form-inputs/loader-vars.php';

$content_writer_nonce = wp_create_nonce('aipkit_content_writer_nonce');
$content_writer_template_nonce = wp_create_nonce('aipkit_content_writer_template_nonce');
$frontend_stream_nonce = wp_create_nonce('aipkit_frontend_chat_nonce');
$aipkit_cw_seo_profile = class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')
    ? \WPAICG\SEO\AIPKit_SEO_Helper::get_active_plugin_profile()
    : [
        'label' => __('AIPKit SEO', 'gpt3-ai-content-generator'),
        'logo_url' => '',
        'logo_initials' => 'AI',
    ];
$aipkit_cw_seo_profile_label = isset($aipkit_cw_seo_profile['label']) ? (string) $aipkit_cw_seo_profile['label'] : __('AIPKit SEO', 'gpt3-ai-content-generator');
$aipkit_cw_seo_profile_logo_url = isset($aipkit_cw_seo_profile['logo_url']) ? (string) $aipkit_cw_seo_profile['logo_url'] : '';
$aipkit_cw_seo_profile_logo_initials = isset($aipkit_cw_seo_profile['logo_initials']) ? (string) $aipkit_cw_seo_profile['logo_initials'] : 'SEO';

$aipkit_cw_max_execution_time = function_exists('ini_get') ? (int) ini_get('max_execution_time') : 0;
$aipkit_cw_socket_timeout = function_exists('ini_get') ? (int) ini_get('default_socket_timeout') : 0;
$aipkit_cw_timeout_warnings = [];
if ($aipkit_cw_max_execution_time > 0 && $aipkit_cw_max_execution_time <= 30) {
    $aipkit_cw_timeout_warnings[] = sprintf('max_execution_time=%ds', $aipkit_cw_max_execution_time);
}
if ($aipkit_cw_socket_timeout > 0 && $aipkit_cw_socket_timeout <= 30) {
    $aipkit_cw_timeout_warnings[] = sprintf('default_socket_timeout=%ds', $aipkit_cw_socket_timeout);
}
?>
<?php
$aipkit_notice_id = 'aipkit_provider_notice_content_writer';
$aipkit_notice_context = __('write content', 'gpt3-ai-content-generator');
include WPAICG_PLUGIN_DIR . 'admin/views/shared/provider-key-notice.php';
include WPAICG_PLUGIN_DIR . 'admin/views/shared/seo-plugin-conflict-notice.php';
?>
<?php if (!empty($aipkit_cw_timeout_warnings)) : ?>
<div class="aipkit_notification_bar aipkit_notification_bar--warning" data-aipkit-dismissible-notice="content-writer-low-php-timeouts-v1">
    <div class="aipkit_notification_bar__icon" aria-hidden="true">
        <span class="dashicons dashicons-clock"></span>
    </div>
    <div class="aipkit_notification_bar__content">
        <p>
            <?php
            printf(
                /* translators: %s: comma-separated list of PHP timeout settings that are too low. */
                esc_html__(
                    'Low PHP timeouts detected (%s). Long content generations may time out. Increase max_execution_time/default_socket_timeout in php.ini and any web-server timeouts.',
                    'gpt3-ai-content-generator'
                ),
                esc_html(implode(', ', $aipkit_cw_timeout_warnings))
            );
            ?>
        </p>
    </div>
    <button type="button" class="aipkit_notification_bar__close" data-aipkit-dismiss-notice aria-label="<?php esc_attr_e('Dismiss notice', 'gpt3-ai-content-generator'); ?>">
        &times;
    </button>
</div>
<?php endif; ?>
<div class="aipkit_module_content_writer" id="aipkit_content_writer_container">
    <form id="aipkit_content_writer_form" onsubmit="return false;">
        <input type="hidden" name="_ajax_nonce" id="aipkit_content_writer_nonce" value="<?php echo esc_attr($content_writer_nonce); ?>">
        <input type="hidden" id="aipkit_content_writer_frontend_stream_nonce" value="<?php echo esc_attr($frontend_stream_nonce); ?>">
        <input type="hidden" id="aipkit_content_writer_template_nonce_field" value="<?php echo esc_attr($content_writer_template_nonce); ?>">
        <input type="hidden" name="stream_cache_key" id="aipkit_content_writer_stream_cache_key" value="">
        <input type="hidden" name="image_data" id="aipkit_cw_image_data_holder" value="">

        <div class="aipkit_global_status_area aipkit_content_writer_status_bar" aria-live="polite">
            <span id="aipkit_content_writer_form_status" class="aipkit_cw_status_badge"></span>
            <div id="aipkit_content_writer_messages" class="aipkit_settings_messages" role="status" aria-live="polite"></div>
        </div>

        <div class="aipkit_content_writer_layout">
            <div class="aipkit_content_writer_column aipkit_content_writer_sources">
                <div class="aipkit_sub_container aipkit_cw_sources_card">
                    <div class="aipkit_sub_container_body">
                        <div class="aipkit_cw_sources_stack">
                            <?php include __DIR__ . '/partials/source-selector.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="aipkit_content_writer_column aipkit_content_writer_output">
                <?php include __DIR__ . '/partials/form-inputs/generation-mode.php'; ?>

                <div id="aipkit_cw_batch_queue" class="aipkit_cw_batch_queue" hidden>
                    <div class="aipkit_cw_batch_workspace aipkit_cw_output_workspace aipkit_cw_output_workspace--studio">
                        <aside class="aipkit_cw_output_brief aipkit_cw_batch_brief">
                            <div class="aipkit_cw_studio_panel">
                                <div class="aipkit_cw_studio_panel_header">
                                    <span class="aipkit_cw_studio_panel_label"><?php esc_html_e('Summary', 'gpt3-ai-content-generator'); ?></span>
                                </div>
                                <dl class="aipkit_cw_studio_brief_list">
                                    <div class="aipkit_cw_studio_brief_row">
                                        <dt><?php esc_html_e('Source', 'gpt3-ai-content-generator'); ?></dt>
                                        <dd id="aipkit_cw_batch_brief_source_value" class="is-placeholder"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                                    </div>
                                    <div class="aipkit_cw_studio_brief_row">
                                        <dt><?php esc_html_e('Mode', 'gpt3-ai-content-generator'); ?></dt>
                                        <dd id="aipkit_cw_batch_brief_mode_value" class="is-placeholder"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                                    </div>
                                    <div class="aipkit_cw_studio_brief_row">
                                        <dt><?php esc_html_e('Items', 'gpt3-ai-content-generator'); ?></dt>
                                        <dd id="aipkit_cw_batch_brief_items_value" class="is-placeholder"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                                    </div>
                                    <div class="aipkit_cw_studio_brief_row">
                                        <dt><?php esc_html_e('Publish Target', 'gpt3-ai-content-generator'); ?></dt>
                                        <dd id="aipkit_cw_batch_brief_publish_value" class="is-placeholder"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                                    </div>
                                    <div class="aipkit_cw_studio_brief_row">
                                        <dt><?php esc_html_e('Outputs', 'gpt3-ai-content-generator'); ?></dt>
                                        <dd id="aipkit_cw_batch_brief_outputs_value" class="is-placeholder"><?php esc_html_e('Not set', 'gpt3-ai-content-generator'); ?></dd>
                                    </div>
                                </dl>
                            </div>
                        </aside>

                        <div class="aipkit_cw_output_main aipkit_cw_batch_main">
                            <section class="aipkit_cw_output_chunk aipkit_cw_output_chunk--primary aipkit_cw_batch_ledger_surface">
                                <div class="aipkit_cw_chunk_header">
                                    <div class="aipkit_cw_chunk_title_row">
                                        <span class="aipkit_cw_chunk_label"><?php esc_html_e('Queue', 'gpt3-ai-content-generator'); ?></span>
                                    </div>
                                </div>
                                <div class="aipkit_cw_batch_queue_body">
                                    <div class="aipkit_cw_batch_list" role="list"></div>
                                </div>
                            </section>
                        </div>

                        <aside class="aipkit_cw_output_sidebar aipkit_cw_batch_sidebar">
                            <section class="aipkit_cw_output_sidebar_card aipkit_cw_output_sidebar_card--session aipkit_cw_batch_session_card">
                                <div class="aipkit_cw_output_sidebar_header aipkit_cw_output_sidebar_header--progress">
                                    <div class="aipkit_cw_output_sidebar_header_copy">
                                        <div class="aipkit_cw_output_sidebar_title"><?php esc_html_e('Progress', 'gpt3-ai-content-generator'); ?></div>
                                    </div>
                                </div>

                                <div class="aipkit_cw_batch_header_actions"></div>

                                <div class="aipkit_cw_batch_session_actions" hidden>
                                    <button
                                        type="button"
                                        id="aipkit_cw_batch_start_over_btn"
                                        class="button aipkit_btn aipkit_cw_output_action_btn aipkit_cw_output_action_btn--reset"
                                    >
                                        <span class="aipkit_btn-text"><?php esc_html_e('Start Over', 'gpt3-ai-content-generator'); ?></span>
                                    </button>
                                </div>

                                <div class="aipkit_cw_batch_summary">
                                    <div class="aipkit_cw_batch_summary_main">
                                        <span class="aipkit_cw_batch_count">0 <?php esc_html_e('of', 'gpt3-ai-content-generator'); ?> 0</span>
                                        <span class="aipkit_cw_batch_label"><?php esc_html_e('Processed', 'gpt3-ai-content-generator'); ?></span>
                                    </div>
                                </div>

                                <div class="aipkit_cw_batch_progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <span class="aipkit_cw_batch_progress_bar"></span>
                                </div>

                                <div class="aipkit_cw_batch_stats" aria-label="<?php esc_attr_e('Batch status', 'gpt3-ai-content-generator'); ?>">
                                    <span id="aipkit_cw_batch_stat_waiting" class="aipkit_cw_batch_metric aipkit_cw_batch_metric--queued" hidden>
                                        <span class="aipkit_cw_batch_metric_value">0</span>
                                        <span class="aipkit_cw_batch_metric_label"><?php esc_html_e('Queued', 'gpt3-ai-content-generator'); ?></span>
                                    </span>
                                    <span id="aipkit_cw_batch_stat_running" class="aipkit_cw_batch_metric aipkit_cw_batch_metric--running" hidden>
                                        <span class="aipkit_cw_batch_metric_value">0</span>
                                        <span class="aipkit_cw_batch_metric_label"><?php esc_html_e('Running', 'gpt3-ai-content-generator'); ?></span>
                                    </span>
                                    <span id="aipkit_cw_batch_stat_success" class="aipkit_cw_batch_metric aipkit_cw_batch_metric--done" hidden>
                                        <span class="aipkit_cw_batch_metric_value">0</span>
                                        <span class="aipkit_cw_batch_metric_label"><?php esc_html_e('Done', 'gpt3-ai-content-generator'); ?></span>
                                    </span>
                                    <span id="aipkit_cw_batch_stat_failed" class="aipkit_cw_batch_metric aipkit_cw_batch_metric--failed" hidden>
                                        <span class="aipkit_cw_batch_metric_value">0</span>
                                        <span class="aipkit_cw_batch_metric_label"><?php esc_html_e('Failed', 'gpt3-ai-content-generator'); ?></span>
                                    </span>
                                    <span id="aipkit_cw_batch_stat_stopped" class="aipkit_cw_batch_metric aipkit_cw_batch_metric--stopped" hidden>
                                        <span class="aipkit_cw_batch_metric_value">0</span>
                                        <span class="aipkit_cw_batch_metric_label"><?php esc_html_e('Stopped', 'gpt3-ai-content-generator'); ?></span>
                                    </span>
                                </div>

                                <div class="aipkit_cw_batch_seo_summary" data-aipkit-batch-seo-summary hidden>
                                    <span
                                        id="aipkit_cw_batch_stat_seo_profile"
                                        class="aipkit_cw_batch_seo_profile_stat"
                                        hidden
                                    >
                                        <span class="aipkit_seo_profile_logo" aria-hidden="true">
                                            <?php if ($aipkit_cw_seo_profile_logo_url !== '') : ?>
                                                <img src="<?php echo esc_url($aipkit_cw_seo_profile_logo_url); ?>" alt="">
                                            <?php else : ?>
                                                <span><?php echo esc_html($aipkit_cw_seo_profile_logo_initials); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span><?php echo esc_html($aipkit_cw_seo_profile_label); ?></span>
                                    </span>
                                    <span id="aipkit_cw_batch_stat_seo_average" class="aipkit_cw_batch_seo_average" hidden><?php esc_html_e('SEO Score avg --', 'gpt3-ai-content-generator'); ?></span>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>

                <?php include __DIR__ . '/partials/output-area.php'; ?>
            </div>

            <div class="aipkit_content_writer_column aipkit_content_writer_inputs">
                <?php include __DIR__ . '/partials/form-inputs.php'; ?>
                <?php include __DIR__ . '/partials/advanced-settings.php'; ?>
            </div>

        </div>
    </form>
</div>
