<?php
/**
 * Partial: Content Writing Automated Task - CSV Input Mode
 * UPDATED: Replaced textarea with a file input for direct CSV uploads.
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div id="aipkit_task_cw_input_mode_csv" class="aipkit_task_cw_input_mode_section">
    <div class="aipkit_csv_import_container aipkit_cw_source_mode_shell aipkit_cw_source_mode_shell--csv">
        <div class="aipkit_cw_source_mode_stage">
            <div class="aipkit_csv_upload_zone" data-csv-upload-zone>
                <label for="aipkit_task_cw_csv_file_input" class="aipkit_csv_dropzone">
                    <span class="aipkit_csv_dropzone_icon" aria-hidden="true">
                        <span class="dashicons dashicons-upload"></span>
                    </span>
                    <span class="aipkit_csv_dropzone_text">
                        <span class="aipkit_csv_dropzone_primary"><?php esc_html_e('Drop your CSV file here', 'gpt3-ai-content-generator'); ?></span>
                        <span class="aipkit_csv_dropzone_secondary"><?php esc_html_e('or click to browse', 'gpt3-ai-content-generator'); ?></span>
                    </span>
                    <input
                        type="file"
                        id="aipkit_task_cw_csv_file_input"
                        name="csv_file_input"
                        class="aipkit_csv_file_input_hidden"
                        accept=".csv, text/csv"
                    >
                </label>
            </div>

            <div class="aipkit_csv_status_container" id="aipkit_task_cw_csv_status_container" data-csv-status hidden>
                <div class="aipkit_csv_status_card" data-csv-status-card>
                    <div class="aipkit_csv_status_icon" data-csv-status-icon>
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    </div>
                    <div class="aipkit_csv_status_content">
                        <span class="aipkit_csv_file_name" data-csv-file-name></span>
                        <span id="aipkit_task_cw_csv_analysis_results" class="aipkit_csv_analysis_results" data-csv-message></span>
                    </div>
                    <button type="button" class="aipkit_csv_clear_btn" data-csv-clear aria-label="<?php esc_attr_e('Remove file', 'gpt3-ai-content-generator'); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>

        <?php // This hidden textarea will be populated by JS with the parsed CSV data (pipe-separated) ?>
        <textarea name="content_title" id="aipkit_task_cw_csv_data_holder" class="aipkit_csv_data_holder" style="display: none;" readonly></textarea>

        <p class="aipkit_cw_source_format_hint">
            <span><?php esc_html_e('Topic | Keywords | Category ID | Author Login | Post Type | Schedule.', 'gpt3-ai-content-generator'); ?></span>
            <a href="https://docs.google.com/spreadsheets/d/1WOnO_UKkbRCoyjRxQnDDTy0i-RsnrY_MDKD3Ks09JJk/edit?usp=sharing" target="_blank" rel="noopener noreferrer" class="aipkit_cw_paste_sample_link"><?php esc_html_e('View sample CSV', 'gpt3-ai-content-generator'); ?></a>
        </p>
    </div>
</div>
