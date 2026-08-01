<?php
/**
 * Partial: Content Writing Automated Task - Knowledge Base Settings
 * Mirrors the Content Writer context layout inside the AutoGPT advanced panel.
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$embedding_provider_options = \WPAICG\AIPKit_Providers::get_embedding_provider_map('autogpt_content_writing_ui');
$default_embedding_provider_key = isset($embedding_provider_options['openai'])
    ? 'openai'
    : (array_key_first($embedding_provider_options) ?: 'openai');

$aipkit_autogpt_context_config = [
    'scope' => 'cw',
    'name_prefix' => '',
    'root_classes' => 'aipkit_cw_vector_section aipkit_task_cw_kb_section',
    'use_autosave_class' => true,
    'show_confidence_threshold' => true,
    'top_k_default' => 3,
    'confidence_default' => 20,
];

include dirname(__DIR__) . '/shared/context-settings.php';
