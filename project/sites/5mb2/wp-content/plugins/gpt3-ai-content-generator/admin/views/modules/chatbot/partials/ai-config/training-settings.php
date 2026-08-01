<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
?>
<section id="aipkit_sources_training_card" class="aipkit_builder_card aipkit_builder_card--training">
    <div class="aipkit_training_decision" data-aipkit-training-decision>
        <div class="aipkit_training_decision_header">
            <div class="aipkit_training_header_copy">
                <p class="aipkit_training_decision_title">
                    <?php esc_html_e('Knowledge', 'gpt3-ai-content-generator'); ?>
                </p>
                <p class="aipkit_training_decision_subtitle">
                    <?php esc_html_e('Give this chatbot reliable sources to answer from.', 'gpt3-ai-content-generator'); ?>
                </p>
            </div>
            <button
                type="button"
                class="aipkit_training_add_source"
                aria-haspopup="dialog"
                aria-controls="aipkit_training_source_popover"
                aria-expanded="false"
            >
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <span><?php esc_html_e('Add source', 'gpt3-ai-content-generator'); ?></span>
            </button>
        </div>

        <?php
        $training_source_popover_path = __DIR__ . '/training-source-popover.php';
        if (file_exists($training_source_popover_path)) {
            include $training_source_popover_path;
        }
        ?>

        <div class="aipkit_training_source_overview" data-aipkit-training-source-overview>
            <div class="aipkit_training_source_empty" data-aipkit-training-source-empty>
                <span class="dashicons dashicons-database" aria-hidden="true"></span>
                <div>
                    <strong><?php esc_html_e('No sources yet', 'gpt3-ai-content-generator'); ?></strong>
                    <span><?php esc_html_e('Add website content, a Q&A, text, or files.', 'gpt3-ai-content-generator'); ?></span>
                </div>
            </div>
            <div class="aipkit_training_managed_sources" data-aipkit-training-managed-sources hidden></div>
        </div>

        <div class="aipkit_training_decision_actions">
            <div class="aipkit_training_action_meta">
                <div class="aipkit_training_state_wrap" data-aipkit-training-state-wrap>
                    <button
                        type="button"
                        class="aipkit_training_state is-pending"
                        data-aipkit-training-state
                        aria-live="polite"
                        aria-busy="true"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="aipkit_training_state_menu"
                        aria-label="<?php esc_attr_e('Knowledge status', 'gpt3-ai-content-generator'); ?>"
                        disabled
                    >
                        <span class="aipkit_training_state_dot" aria-hidden="true"></span>
                        <span class="aipkit_training_state_value" data-aipkit-training-state-value>
                            <?php esc_html_e('No knowledge yet', 'gpt3-ai-content-generator'); ?>
                        </span>
                        <span class="aipkit_training_state_help" aria-hidden="true">?</span>
                    </button>
                    <div
                        id="aipkit_training_state_menu"
                        class="aipkit_training_state_menu"
                        data-aipkit-training-state-menu
                        role="dialog"
                        aria-label="<?php esc_attr_e('Knowledge details', 'gpt3-ai-content-generator'); ?>"
                        hidden
                    >
                        <div class="aipkit_training_state_menu_title" data-aipkit-training-report-status>
                            <?php esc_html_e('Adding knowledge', 'gpt3-ai-content-generator'); ?>
                        </div>
                        <div class="aipkit_training_state_report">
                            <div class="aipkit_training_state_metrics">
                                <div class="aipkit_training_state_metric aipkit_training_state_metric--sources">
                                    <strong data-aipkit-training-report-trained>0</strong>
                                    <span><?php esc_html_e('Sources', 'gpt3-ai-content-generator'); ?></span>
                                </div>
                                <div class="aipkit_training_state_metric aipkit_training_state_metric--processing">
                                    <strong data-aipkit-training-report-processing>0</strong>
                                    <span><?php esc_html_e('Processing', 'gpt3-ai-content-generator'); ?></span>
                                </div>
                                <div class="aipkit_training_state_metric aipkit_training_state_metric--failed">
                                    <strong data-aipkit-training-report-failed>0</strong>
                                    <span><?php esc_html_e('Failed', 'gpt3-ai-content-generator'); ?></span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="aipkit_training_state_sources" data-aipkit-view-training-sources>
                            <span><?php esc_html_e('Manage sources', 'gpt3-ai-content-generator'); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="aipkit_training_state_stop" data-aipkit-stop-training>
                            <?php esc_html_e('Stop training', 'gpt3-ai-content-generator'); ?>
                        </button>
                    </div>
                </div>
                <span class="aipkit_training_status" id="aipkit_training_status" data-aipkit-training-status aria-live="polite"></span>
            </div>
        </div>
    </div>

    <select id="aipkit_vs_wp_content_status" class="aipkit_training_site_hidden_select" aria-hidden="true" tabindex="-1">
        <option value="publish" selected><?php esc_html_e('Published', 'gpt3-ai-content-generator'); ?></option>
    </select>
    <select id="aipkit_vs_global_target_select" class="aipkit_training_site_target_select" aria-hidden="true" tabindex="-1">
        <option value=""></option>
    </select>

    <button
        type="button"
        class="aipkit_training_sources_btn aipkit_builder_sheet_trigger"
        data-base-label="<?php echo esc_attr__('Knowledge sources', 'gpt3-ai-content-generator'); ?>"
        data-sheet-title="<?php echo esc_attr__('Knowledge sources', 'gpt3-ai-content-generator'); ?>"
        data-sheet-description="<?php echo esc_attr__('Review and manage what this chatbot knows.', 'gpt3-ai-content-generator'); ?>"
        data-sheet-content="sources"
        hidden
    >
        <span class="aipkit_training_sources_label">
            <?php echo esc_html__('Knowledge sources', 'gpt3-ai-content-generator'); ?>
        </span>
        <span class="aipkit_training_sources_count" aria-hidden="true">0</span>
    </button>
</section>
