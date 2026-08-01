<?php
/**
 * Partial: Other settings page
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="aipkit_form-group aipkit_settings_simple_row" id="aipkit_settings_backup_row">
    <div class="aipkit_form-label">
        <?php esc_html_e('Settings backup', 'gpt3-ai-content-generator'); ?>
        <span class="aipkit_form-label-helper"><?php esc_html_e('Export or import every Settings tab as JSON. Backups include API keys and connection secrets, so store them securely.', 'gpt3-ai-content-generator'); ?></span>
    </div>
    <div class="aipkit_settings_action_buttons">
        <button
            type="button"
            id="aipkit_settings_export_button"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="export-backup"
        >
            <?php esc_html_e('Export JSON', 'gpt3-ai-content-generator'); ?>
        </button>
        <button
            type="button"
            id="aipkit_settings_import_trigger"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="import-trigger"
        >
            <?php esc_html_e('Import JSON', 'gpt3-ai-content-generator'); ?>
        </button>
        <input
            type="file"
            id="aipkit_settings_import_file"
            class="aipkit_settings_hidden_file_input"
            accept=".json,application/json"
            hidden
        />
    </div>
</div>

<div class="aipkit_form-group aipkit_settings_simple_row" id="aipkit_settings_restore_point_row">
    <div class="aipkit_form-label">
        <?php esc_html_e('Restore point', 'gpt3-ai-content-generator'); ?>
        <span class="aipkit_form-label-helper"><?php esc_html_e('Save or roll back to a saved state.', 'gpt3-ai-content-generator'); ?></span>
    </div>
    <div class="aipkit_settings_action_buttons">
        <button
            type="button"
            id="aipkit_settings_create_restore_point"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="create-restore-point"
        >
            <?php esc_html_e('Create restore point', 'gpt3-ai-content-generator'); ?>
        </button>
        <button
            type="button"
            id="aipkit_settings_restore_restore_point"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="restore-restore-point"
        >
            <?php esc_html_e('Restore last point', 'gpt3-ai-content-generator'); ?>
        </button>
    </div>
</div>

<div class="aipkit_form-group aipkit_settings_simple_row" id="aipkit_settings_caches_row">
    <div class="aipkit_form-label">
        <?php esc_html_e('Caches', 'gpt3-ai-content-generator'); ?>
        <span class="aipkit_form-label-helper"><?php esc_html_e('Clear cached model lists or transient data.', 'gpt3-ai-content-generator'); ?></span>
    </div>
    <div class="aipkit_settings_action_buttons">
        <button
            type="button"
            id="aipkit_settings_clear_model_cache"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="clear-model-cache"
        >
            <?php esc_html_e('Clear model cache', 'gpt3-ai-content-generator'); ?>
        </button>
        <button
            type="button"
            id="aipkit_settings_clear_transients"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="clear-transients"
        >
            <?php esc_html_e('Clear transients', 'gpt3-ai-content-generator'); ?>
        </button>
    </div>
</div>

<div class="aipkit_form-group aipkit_settings_simple_row" id="aipkit_settings_sync_all_row">
    <div class="aipkit_form-label">
        <?php esc_html_e('Sync models', 'gpt3-ai-content-generator'); ?>
        <span class="aipkit_form-label-helper"><?php esc_html_e('Refresh available models for every connected provider.', 'gpt3-ai-content-generator'); ?></span>
    </div>
    <div class="aipkit_settings_action_buttons">
        <button
            type="button"
            id="aipkit_settings_sync_all_models"
            class="aipkit_btn aipkit_btn-secondary"
            data-aipkit-settings-action="sync-all-models"
        >
            <?php esc_html_e('Sync all', 'gpt3-ai-content-generator'); ?>
        </button>
    </div>
</div>

<?php include __DIR__ . '/settings-product-promotions.php'; ?>
