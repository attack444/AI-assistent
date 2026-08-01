<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
?>

<button
    type="button"
    class="aipkit_cw_settings_icon_trigger"
    id="aipkit_cw_kb_settings_trigger"
    data-aipkit-popover-target="aipkit_cw_kb_settings_popover"
    data-aipkit-popover-placement="left"
    aria-controls="aipkit_cw_kb_settings_popover"
    aria-expanded="false"
    aria-label="<?php esc_attr_e('Context settings', 'gpt3-ai-content-generator'); ?>"
    title="<?php esc_attr_e('Context settings', 'gpt3-ai-content-generator'); ?>"
>
    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
</button>

<div class="aipkit_model_settings_popover aipkit_cw_settings_popover" id="aipkit_cw_kb_settings_popover" aria-hidden="true">
    <div class="aipkit_model_settings_popover_panel aipkit_cw_settings_popover_panel aipkit_cw_kb_settings_popover_panel" role="dialog" aria-label="<?php esc_attr_e('Context settings', 'gpt3-ai-content-generator'); ?>">
        <div class="aipkit_model_settings_popover_header aipkit_cw_settings_sheet_header">
            <span class="aipkit_model_settings_popover_title"><?php esc_html_e('Context settings', 'gpt3-ai-content-generator'); ?></span>
        </div>
        <div class="aipkit_model_settings_popover_body aipkit_cw_settings_popover_body aipkit_cw_settings_sheet_body">
            <div class="aipkit_popover_options_list">
                <div id="aipkit_cw_kb_embedding_section" hidden>
                    <div class="aipkit_popover_option_row aipkit_popover_option_row--force-divider">
                        <div class="aipkit_popover_option_main">
                            <div class="aipkit_cw_settings_option_text">
                                <label class="aipkit_popover_option_label" for="aipkit_cw_vector_embedding_provider">
                                    <?php esc_html_e('Embedding Provider', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <span class="aipkit_popover_option_helper">
                                    <?php esc_html_e('Provider for embeddings.', 'gpt3-ai-content-generator'); ?>
                                </span>
                            </div>
                            <select
                                id="aipkit_cw_vector_embedding_provider"
                                name="vector_embedding_provider"
                                class="aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                            >
                                <?php foreach ($embedding_provider_options as $provider_key => $provider_label): ?>
                                    <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($provider_key, $default_embedding_provider_key); ?>>
                                        <?php echo esc_html($provider_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_popover_option_row--force-divider">
                        <div class="aipkit_popover_option_main">
                            <div class="aipkit_cw_settings_option_text">
                                <label class="aipkit_popover_option_label" for="aipkit_cw_vector_embedding_model">
                                    <?php esc_html_e('Embedding Model', 'gpt3-ai-content-generator'); ?>
                                </label>
                                <span class="aipkit_popover_option_helper">
                                    <?php esc_html_e('Model for embeddings.', 'gpt3-ai-content-generator'); ?>
                                </span>
                            </div>
                            <select
                                id="aipkit_cw_vector_embedding_model"
                                name="vector_embedding_model"
                                class="aipkit_autosave_trigger aipkit_popover_option_select aipkit_popover_option_select--fit aipkit_cw_blended_chevron_select"
                                disabled
                            >
                                <option value=""><?php esc_html_e('Select provider first', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <div class="aipkit_cw_settings_option_text">
                            <label class="aipkit_popover_option_label" for="aipkit_cw_vector_store_top_k">
                                <?php esc_html_e('Results Limit', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <span class="aipkit_popover_option_helper">
                                <?php esc_html_e('How many matches to use.', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>
                        <input
                            type="number"
                            id="aipkit_cw_vector_store_top_k"
                            name="vector_store_top_k"
                            class="aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_input aipkit_popover_option_input--compact"
                            value="3"
                            min="1"
                            max="20"
                            step="1"
                        >
                    </div>
                </div>

                <div class="aipkit_popover_option_row">
                    <div class="aipkit_popover_option_main">
                        <div class="aipkit_cw_settings_option_text">
                            <label class="aipkit_popover_option_label" for="aipkit_cw_vector_store_confidence_threshold">
                                <?php esc_html_e('Confidence Threshold', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <span class="aipkit_popover_option_helper">
                                <?php esc_html_e('Minimum confidence to include.', 'gpt3-ai-content-generator'); ?>
                            </span>
                        </div>
                        <input
                            type="number"
                            id="aipkit_cw_vector_store_confidence_threshold"
                            name="vector_store_confidence_threshold"
                            class="aipkit_form-input aipkit_autosave_trigger aipkit_popover_option_input aipkit_popover_option_input--compact"
                            value="20"
                            min="0"
                            max="100"
                            step="1"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
