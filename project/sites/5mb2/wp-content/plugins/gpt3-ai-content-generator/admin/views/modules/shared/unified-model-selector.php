<?php
/**
 * Shared searchable model selector.
 *
 * Expected configuration in $aipkit_unified_model_selector_config:
 * - trigger_id (string)
 * - initial_label (string)
 * - source_id (string, optional)
 * - class_name (string, optional)
 * - search_placeholder (string, optional)
 * - empty_text (string, optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_unified_model_selector_config = isset($aipkit_unified_model_selector_config) && is_array($aipkit_unified_model_selector_config)
    ? $aipkit_unified_model_selector_config
    : [];
$aipkit_unified_model_trigger_id = isset($aipkit_unified_model_selector_config['trigger_id'])
    ? (string) $aipkit_unified_model_selector_config['trigger_id']
    : 'aipkit_unified_model_trigger';
$aipkit_unified_model_popover_id = $aipkit_unified_model_trigger_id . '_popover';
$aipkit_unified_model_initial_label = isset($aipkit_unified_model_selector_config['initial_label'])
    ? (string) $aipkit_unified_model_selector_config['initial_label']
    : __('Select model', 'gpt3-ai-content-generator');
$aipkit_unified_model_source_id = isset($aipkit_unified_model_selector_config['source_id'])
    ? (string) $aipkit_unified_model_selector_config['source_id']
    : '';
$aipkit_unified_model_class_name = isset($aipkit_unified_model_selector_config['class_name'])
    ? trim((string) $aipkit_unified_model_selector_config['class_name'])
    : '';
$aipkit_unified_model_search_placeholder = isset($aipkit_unified_model_selector_config['search_placeholder'])
    ? (string) $aipkit_unified_model_selector_config['search_placeholder']
    : __('Search models...', 'gpt3-ai-content-generator');
$aipkit_unified_model_empty_text = isset($aipkit_unified_model_selector_config['empty_text'])
    ? (string) $aipkit_unified_model_selector_config['empty_text']
    : __('No models found', 'gpt3-ai-content-generator');
?>
<div
    class="aipkit_unified_model_selector<?php echo $aipkit_unified_model_class_name !== '' ? ' ' . esc_attr($aipkit_unified_model_class_name) : ''; ?>"
    data-aipkit-unified-model-selector
    <?php echo $aipkit_unified_model_source_id !== '' ? 'data-aipkit-unified-model-source-id="' . esc_attr($aipkit_unified_model_source_id) . '"' : ''; ?>
>
    <button
        type="button"
        id="<?php echo esc_attr($aipkit_unified_model_trigger_id); ?>"
        class="aipkit_unified_model_trigger"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($aipkit_unified_model_popover_id); ?>"
        data-aipkit-unified-model-trigger
    >
        <span class="aipkit_unified_model_logo" data-aipkit-unified-model-logo aria-hidden="true"></span>
        <span class="aipkit_unified_model_name" data-aipkit-unified-model-name><?php echo esc_html($aipkit_unified_model_initial_label); ?></span>
    </button>
    <div
        id="<?php echo esc_attr($aipkit_unified_model_popover_id); ?>"
        class="aipkit_unified_model_popover"
        data-aipkit-unified-model-popover
        hidden
    >
        <div class="aipkit_unified_model_search">
            <input
                type="search"
                class="aipkit_unified_model_search_input"
                placeholder="<?php echo esc_attr($aipkit_unified_model_search_placeholder); ?>"
                aria-label="<?php echo esc_attr($aipkit_unified_model_search_placeholder); ?>"
                data-aipkit-unified-model-search
            />
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
        </div>
        <div class="aipkit_unified_model_list" role="listbox" data-aipkit-unified-model-list></div>
        <div class="aipkit_unified_model_empty" data-aipkit-unified-model-empty hidden>
            <?php echo esc_html($aipkit_unified_model_empty_text); ?>
        </div>
    </div>
</div>
