<?php
/**
 * Content Writing setup panel wrapper.
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_autogpt_setup_config = [
    'scope' => 'cw',
    'name_prefix' => '',
    'model_question' => __('Model', 'gpt3-ai-content-generator'),
    'length_question' => __('Content length', 'gpt3-ai-content-generator'),
    'temperature_question' => __('Writing style', 'gpt3-ai-content-generator'),
    'reasoning_question' => __('Reasoning level', 'gpt3-ai-content-generator'),
    'has_length' => true,
    'default_length' => 'medium',
];

include dirname(__DIR__) . '/shared/setup-panel.php';
