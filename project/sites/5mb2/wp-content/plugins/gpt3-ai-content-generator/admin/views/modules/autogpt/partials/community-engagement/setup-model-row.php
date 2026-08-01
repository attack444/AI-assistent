<?php
/**
 * Comment replies setup panel wrapper.
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_autogpt_setup_config = [
    'scope' => 'cc',
    'name_prefix' => 'cc_',
    'model_question' => __('Model', 'gpt3-ai-content-generator'),
    'temperature_question' => __('Writing style', 'gpt3-ai-content-generator'),
    'max_tokens_question' => __('Maximum reply length', 'gpt3-ai-content-generator'),
    'reasoning_question' => __('Reasoning level', 'gpt3-ai-content-generator'),
    'has_max_tokens' => true,
    'default_max_tokens' => isset($cw_default_max_tokens) ? $cw_default_max_tokens : '4000',
];

include dirname(__DIR__) . '/shared/setup-panel.php';
