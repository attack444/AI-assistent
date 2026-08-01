<?php
/**
 * Partial: Content Indexing Automated Task - Knowledge Base Settings
 * Mirrors the content writer knowledge base popover style.
 *
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="aipkit_ci_target_stack">
    <div class="aipkit_ci_target_row">
        <label class="aipkit_ci_target_label" for="aipkit_task_content_indexing_target_store_provider">
            <?php esc_html_e('Provider', 'gpt3-ai-content-generator'); ?>
        </label>
        <select
            id="aipkit_task_content_indexing_target_store_provider"
            name="target_store_provider"
            class="aipkit_form-input aipkit_ci_target_select aipkit_autosave_trigger"
        >
            <option value="openai" selected>OpenAI</option>
            <option value="pinecone">Pinecone</option>
            <option value="qdrant">Qdrant</option>
            <option value="chroma">Chroma</option>
        </select>
    </div>

    <div class="aipkit_ci_target_row">
        <label class="aipkit_ci_target_label" for="aipkit_task_content_indexing_target_store_id">
            <?php esc_html_e('Store / Index', 'gpt3-ai-content-generator'); ?>
        </label>
        <select
            id="aipkit_task_content_indexing_target_store_id"
            name="target_store_id"
            class="aipkit_form-input aipkit_ci_target_select aipkit_autosave_trigger"
        >
            <option value=""><?php esc_html_e('-- Select Store/Index --', 'gpt3-ai-content-generator'); ?></option>
            <?php // Options populated by JS. ?>
        </select>
    </div>

    <div class="aipkit_ci_target_row aipkit_ci_target_row--model" id="aipkit_task_content_indexing_embedding_model_group" hidden>
        <label class="aipkit_ci_target_label" for="aipkit_task_content_indexing_embedding_model_trigger">
            <?php esc_html_e('Embedding Model', 'gpt3-ai-content-generator'); ?>
        </label>
        <select
            id="aipkit_task_content_indexing_embedding_model"
            name="embedding_model"
            class="aipkit_autosave_trigger"
            data-aipkit-unified-model-source
            hidden
            aria-hidden="true"
            tabindex="-1"
        >
            <option value=""><?php esc_html_e('-- Select Model --', 'gpt3-ai-content-generator'); ?></option>
            <?php // Options and optgroups populated by JS. ?>
        </select>
        <?php
        $aipkit_unified_model_selector_config = [
            'trigger_id' => 'aipkit_task_content_indexing_embedding_model_trigger',
            'source_id' => 'aipkit_task_content_indexing_embedding_model',
            'initial_label' => __('Select model', 'gpt3-ai-content-generator'),
            'class_name' => 'aipkit_autogpt_unified_model_selector',
            'search_placeholder' => __('Search embedding models...', 'gpt3-ai-content-generator'),
            'empty_text' => __('No embedding models found', 'gpt3-ai-content-generator'),
        ];
        include dirname(__DIR__, 3) . '/shared/unified-model-selector.php';
        ?>
    </div>
</div>
