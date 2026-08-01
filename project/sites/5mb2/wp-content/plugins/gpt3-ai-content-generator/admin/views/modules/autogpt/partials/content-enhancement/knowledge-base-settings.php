<?php
/**
 * Content enhancement context panel wrapper.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$embedding_provider_options = \WPAICG\AIPKit_Providers::get_embedding_provider_map('autogpt_content_enhancement_ui');
$default_embedding_provider_key = isset($embedding_provider_options['openai'])
    ? 'openai'
    : (array_key_first($embedding_provider_options) ?: 'openai');

$aipkit_autogpt_context_config = [
    'scope' => 'ce',
    'name_prefix' => 'ce_',
    'root_classes' => 'aipkit_cw_vector_section aipkit_task_cw_kb_section aipkit_task_ce_kb_section',
    'use_autosave_class' => false,
    'show_confidence_threshold' => false,
    'top_k_default' => 3,
];

include dirname(__DIR__) . '/shared/context-settings.php';
