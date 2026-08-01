<?php

namespace WPAICG\Admin\Ajax\AIForms;

use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether the nested AI Form structure has at least one configured field.
 *
 * @param mixed $structure
 * @return bool
 */
function aipkit_aiform_structure_has_elements($structure): bool
{
    if (!is_array($structure) || empty($structure)) {
        return false;
    }

    foreach ($structure as $row) {
        if (!is_array($row) || empty($row['columns']) || !is_array($row['columns'])) {
            continue;
        }
        foreach ($row['columns'] as $column) {
            if (!is_array($column) || empty($column['elements']) || !is_array($column['elements'])) {
                continue;
            }
            if (!empty($column['elements'])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Handles the logic for saving an AI form.
 * Called by AIPKit_AI_Form_Ajax_Handler::ajax_save_ai_form().
 *
 * @param AIPKit_AI_Form_Ajax_Handler $handler_instance
 * @return void
 */
function do_ajax_save_form_logic(AIPKit_AI_Form_Ajax_Handler $handler_instance): void
{

    $form_storage = $handler_instance->get_form_storage();

    if (!$form_storage) {
        $handler_instance->send_wp_error(new WP_Error('storage_missing', __('Form storage component is not available.', 'gpt3-ai-content-generator')), 500);
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling class method.
    $post_data = wp_unslash($_POST);

    $form_id = isset($post_data['form_id']) && !empty($post_data['form_id']) ? absint($post_data['form_id']) : null;
    $allow_empty_structure = isset($post_data['allow_empty_structure']) && $post_data['allow_empty_structure'] === '1';
    $title = isset($post_data['title']) ? sanitize_text_field($post_data['title']) : '';
    $template_key = isset($post_data['template_key']) ? sanitize_key($post_data['template_key']) : '';
    $allowed_template_keys = ['lead_capture', 'customer_feedback', 'book_appointment', 'support_request', 'waitlist_signup'];
    if (!in_array($template_key, $allowed_template_keys, true)) {
        $template_key = '';
    }
    $prompt_template = isset($post_data['prompt_template']) ? AIPKit_Prompt_Sanitizer::sanitize($post_data['prompt_template']) : '';
    $form_structure_json = isset($post_data['form_structure']) ? wp_kses_post($post_data['form_structure']) : '[]';

    if (
        array_key_exists('workflow_config', $post_data)
        && !apply_filters('aipkit_ai_forms_allow_workflow_config_save', false, $post_data, $form_id)
    ) {
        $handler_instance->send_wp_error(new WP_Error('workflow_requires_pro', __('AI Form workflows require a Pro plan.', 'gpt3-ai-content-generator')), 403);
        return;
    }

    // Process labels - now receiving as a JSON string from JavaScript
    $default_labels = [
        'generate_button' => __('Generate', 'gpt3-ai-content-generator'),
        'stop_button'     => __('Stop', 'gpt3-ai-content-generator'),
        'download_button' => __('Download', 'gpt3-ai-content-generator'),
        'save_button'     => __('Save', 'gpt3-ai-content-generator'),
        'copy_button'     => __('Copy', 'gpt3-ai-content-generator'),
        'provider_label'  => __('AI Provider', 'gpt3-ai-content-generator'),
        'model_label'     => __('AI Model', 'gpt3-ai-content-generator'),
        'conversation_back_button' => __('Back', 'gpt3-ai-content-generator'),
        'conversation_next_button' => __('Next', 'gpt3-ai-content-generator'),
        'conversation_step_title' => __('Step {number}', 'gpt3-ai-content-generator'),
        'conversation_step_progress' => __('Step {current} of {total}', 'gpt3-ai-content-generator'),
        'conversation_validation_message' => __('Please complete this step before continuing.', 'gpt3-ai-content-generator'),
    ];

    $submitted_labels = [];
    if (isset($post_data['labels']) && !empty($post_data['labels'])) {
        $labels_json = $post_data['labels'];
        $decoded_labels = json_decode($labels_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_labels)) {
            $submitted_labels = $decoded_labels;
        }
    }

    $existing_labels = [];
    if (!empty($form_id)) {
        $existing_labels_json = get_post_meta($form_id, '_aipkit_ai_form_labels', true);
        $decoded_existing_labels = json_decode($existing_labels_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_existing_labels)) {
            $existing_labels = $decoded_existing_labels;
        }
    }

    // Merge defaults: use submitted value if present, otherwise preserve existing saved values, otherwise use default.
    $final_labels = [];
    foreach ($default_labels as $key => $default_value) {
        $has_submitted_value = array_key_exists($key, $submitted_labels);
        $submitted_value = $has_submitted_value ? trim((string) $submitted_labels[$key]) : '';
        $existing_value = isset($existing_labels[$key]) ? trim((string) $existing_labels[$key]) : '';
        if ($has_submitted_value) {
            $final_value = !empty($submitted_value) ? $submitted_value : $default_value;
        } elseif (!empty($existing_value)) {
            $final_value = $existing_value;
        } else {
            $final_value = $default_value;
        }
        $final_labels[sanitize_key($key)] = sanitize_text_field($final_value);
    }


    // --- Get AI config fields from POST ---
    $ai_provider = isset($post_data['ai_provider']) ? sanitize_text_field($post_data['ai_provider']) : null;
    $ai_model = isset($post_data['ai_model']) ? sanitize_text_field($post_data['ai_model']) : null;
    $temperature = isset($post_data['temperature']) ? sanitize_text_field($post_data['temperature']) : null;
    $max_tokens = isset($post_data['max_tokens']) ? absint($post_data['max_tokens']) : null;
    $top_p = isset($post_data['top_p']) ? sanitize_text_field($post_data['top_p']) : null;
    $frequency_penalty = isset($post_data['frequency_penalty']) ? sanitize_text_field($post_data['frequency_penalty']) : null;
    $presence_penalty = isset($post_data['presence_penalty']) ? sanitize_text_field($post_data['presence_penalty']) : null;
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($post_data['reasoning_effort'] ?? '');
    if ($reasoning_effort === '') {
        $reasoning_effort = 'none';
    }
    $conversation_ui_preset = null;
    if (isset($post_data['conversation_ui_preset']) && $post_data['conversation_ui_preset'] !== '') {
        $conversation_ui_preset = sanitize_key($post_data['conversation_ui_preset']);
        if (!in_array($conversation_ui_preset, ['full', 'compact', 'minimal', 'none'], true)) {
            $conversation_ui_preset = 'full';
        }
    }

    // --- Get Vector config fields from POST ---
    $enable_vector_store = isset($post_data['enable_vector_store']) && $post_data['enable_vector_store'] === '1' ? '1' : '0';
    $vector_store_provider = isset($post_data['vector_store_provider']) ? sanitize_key($post_data['vector_store_provider']) : 'openai';
    $openai_vector_store_ids = isset($post_data['openai_vector_store_ids']) && is_array($post_data['openai_vector_store_ids']) ? array_map('sanitize_text_field', $post_data['openai_vector_store_ids']) : [];
    $pinecone_index_name = isset($post_data['pinecone_index_name']) ? sanitize_text_field($post_data['pinecone_index_name']) : '';
    $qdrant_collection_name = isset($post_data['qdrant_collection_name']) ? sanitize_text_field($post_data['qdrant_collection_name']) : '';
    $chroma_collection_name = isset($post_data['chroma_collection_name']) ? sanitize_text_field($post_data['chroma_collection_name']) : '';
    $vector_embedding_provider = isset($post_data['vector_embedding_provider']) ? sanitize_key($post_data['vector_embedding_provider']) : 'openai';
    $vector_embedding_model = isset($post_data['vector_embedding_model']) ? sanitize_text_field($post_data['vector_embedding_model']) : '';
    $vector_store_top_k = isset($post_data['vector_store_top_k']) ? absint($post_data['vector_store_top_k']) : 3;
    $vector_store_confidence_threshold = isset($post_data['vector_store_confidence_threshold']) ? absint($post_data['vector_store_confidence_threshold']) : 20;

    // --- Get Web Search config fields from POST ---
    $openai_web_search_enabled = isset($post_data['openai_web_search_enabled']) && $post_data['openai_web_search_enabled'] === '1' ? '1' : '0';
    $claude_web_search_enabled = isset($post_data['claude_web_search_enabled']) && $post_data['claude_web_search_enabled'] === '1' ? '1' : '0';
    $openrouter_web_search_enabled = isset($post_data['openrouter_web_search_enabled']) && $post_data['openrouter_web_search_enabled'] === '1' ? '1' : '0';
    $xai_web_search_enabled = isset($post_data['xai_web_search_enabled']) && $post_data['xai_web_search_enabled'] === '1' ? '1' : '0';
    $google_search_grounding_enabled = isset($post_data['google_search_grounding_enabled']) && $post_data['google_search_grounding_enabled'] === '1' ? '1' : '0';
    
    // OpenAI Web Search sub-settings
    $openai_web_search_context_size = isset($post_data['openai_web_search_context_size']) ? sanitize_text_field($post_data['openai_web_search_context_size']) : 'medium';
    $openai_web_search_loc_type = isset($post_data['openai_web_search_loc_type']) ? sanitize_text_field($post_data['openai_web_search_loc_type']) : 'none';
    $openai_web_search_loc_country = isset($post_data['openai_web_search_loc_country']) ? sanitize_text_field($post_data['openai_web_search_loc_country']) : '';
    $openai_web_search_loc_city = isset($post_data['openai_web_search_loc_city']) ? sanitize_text_field($post_data['openai_web_search_loc_city']) : '';
    $openai_web_search_loc_region = isset($post_data['openai_web_search_loc_region']) ? sanitize_text_field($post_data['openai_web_search_loc_region']) : '';
    $openai_web_search_loc_timezone = isset($post_data['openai_web_search_loc_timezone']) ? sanitize_text_field($post_data['openai_web_search_loc_timezone']) : '';

    // Claude Web Search sub-settings
    $claude_web_search_max_uses = isset($post_data['claude_web_search_max_uses']) ? absint($post_data['claude_web_search_max_uses']) : 5;
    $claude_web_search_max_uses = max(1, min($claude_web_search_max_uses, 20));
    $claude_web_search_loc_type = isset($post_data['claude_web_search_loc_type']) ? sanitize_text_field($post_data['claude_web_search_loc_type']) : 'none';
    if (!in_array($claude_web_search_loc_type, ['none', 'approximate'], true)) {
        $claude_web_search_loc_type = 'none';
    }
    $claude_web_search_loc_country = isset($post_data['claude_web_search_loc_country']) ? sanitize_text_field($post_data['claude_web_search_loc_country']) : '';
    $claude_web_search_loc_city = isset($post_data['claude_web_search_loc_city']) ? sanitize_text_field($post_data['claude_web_search_loc_city']) : '';
    $claude_web_search_loc_region = isset($post_data['claude_web_search_loc_region']) ? sanitize_text_field($post_data['claude_web_search_loc_region']) : '';
    $claude_web_search_loc_timezone = isset($post_data['claude_web_search_loc_timezone']) ? sanitize_text_field($post_data['claude_web_search_loc_timezone']) : '';
    $normalize_domains = static function (string $domains_raw): string {
        $parts = preg_split('/[\r\n,]+/', $domains_raw);
        if (!is_array($parts)) {
            return '';
        }
        $domains = [];
        foreach ($parts as $part) {
            $domain = strtolower(trim((string) $part));
            if ($domain === '') {
                continue;
            }
            $domain = preg_replace('/^https?:\/\//', '', $domain);
            $domain = trim((string) $domain, " \t\n\r\0\x0B/");
            if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
                continue;
            }
            $domains[] = $domain;
        }
        $domains = array_unique($domains);
        return implode("\n", $domains);
    };
    $claude_web_search_allowed_domains = isset($post_data['claude_web_search_allowed_domains']) ? $normalize_domains((string) $post_data['claude_web_search_allowed_domains']) : '';
    $claude_web_search_blocked_domains = isset($post_data['claude_web_search_blocked_domains']) ? $normalize_domains((string) $post_data['claude_web_search_blocked_domains']) : '';
    if ($claude_web_search_allowed_domains !== '') {
        $claude_web_search_blocked_domains = '';
    }
    $claude_web_search_cache_ttl = isset($post_data['claude_web_search_cache_ttl']) ? sanitize_text_field($post_data['claude_web_search_cache_ttl']) : 'none';
    if (!in_array($claude_web_search_cache_ttl, ['none', '5m', '1h'], true)) {
        $claude_web_search_cache_ttl = 'none';
    }

    // OpenRouter Web Search sub-settings
    $openrouter_web_search_engine = isset($post_data['openrouter_web_search_engine']) ? sanitize_key((string) $post_data['openrouter_web_search_engine']) : 'auto';
    if (!in_array($openrouter_web_search_engine, ['auto', 'native', 'exa'], true)) {
        $openrouter_web_search_engine = 'auto';
    }
    $openrouter_web_search_max_results = isset($post_data['openrouter_web_search_max_results']) ? absint($post_data['openrouter_web_search_max_results']) : 5;
    $openrouter_web_search_max_results = max(1, min($openrouter_web_search_max_results, 10));
    $openrouter_web_search_search_prompt = isset($post_data['openrouter_web_search_search_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($post_data['openrouter_web_search_search_prompt']) : '';
    
    // Google Search Grounding sub-settings
    $google_grounding_mode = isset($post_data['google_grounding_mode']) ? sanitize_text_field($post_data['google_grounding_mode']) : 'DEFAULT_MODE';
    $google_grounding_dynamic_threshold = isset($post_data['google_grounding_dynamic_threshold']) ? floatval($post_data['google_grounding_dynamic_threshold']) : 0.30;

    $decoded_structure = json_decode($form_structure_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_structure)) {
        $handler_instance->send_wp_error(new WP_Error('invalid_structure_json', __('Invalid form structure data submitted.', 'gpt3-ai-content-generator')), 400);
        return;
    }
    if ($form_id && !$allow_empty_structure && !aipkit_aiform_structure_has_elements($decoded_structure)) {
        $existing_structure_json = get_post_meta($form_id, '_aipkit_ai_form_structure', true);
        $existing_structure = json_decode((string) $existing_structure_json, true);
        if (is_array($existing_structure) && aipkit_aiform_structure_has_elements($existing_structure)) {
            $handler_instance->send_wp_error(
                new WP_Error(
                    'empty_structure_confirmation_required',
                    __('This form currently has no fields. Confirm saving to overwrite and remove the existing fields.', 'gpt3-ai-content-generator')
                ),
                400
            );
            return;
        }
    }

    if (empty($title)) {
        $handler_instance->send_wp_error(new WP_Error('title_required', __('Form title cannot be empty.', 'gpt3-ai-content-generator')), 400);
        return;
    }
    if (empty($prompt_template)) {
        $handler_instance->send_wp_error(new WP_Error('prompt_required', __('Prompt template is required.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    $settings = [
        'template_key' => $template_key,
        'prompt_template' => $prompt_template,
        'form_structure'  => $form_structure_json,
        'ai_provider' => $ai_provider,
        'ai_model' => $ai_model,
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
        'top_p' => $top_p,
        'frequency_penalty' => $frequency_penalty,
        'presence_penalty' => $presence_penalty,
        'reasoning_effort' => $reasoning_effort,
        'conversation_ui_preset' => $conversation_ui_preset,
        // Vector settings
        'enable_vector_store' => $enable_vector_store,
        'vector_store_provider' => $vector_store_provider,
        'openai_vector_store_ids' => $openai_vector_store_ids,
        'pinecone_index_name' => $pinecone_index_name,
        'qdrant_collection_name' => $qdrant_collection_name,
        'chroma_collection_name' => $chroma_collection_name,
        'vector_embedding_provider' => $vector_embedding_provider,
        'vector_embedding_model' => $vector_embedding_model,
        'vector_store_top_k' => $vector_store_top_k,
        'vector_store_confidence_threshold' => $vector_store_confidence_threshold,
        // Web Search settings
        'openai_web_search_enabled' => $openai_web_search_enabled,
        'claude_web_search_enabled' => $claude_web_search_enabled,
        'openrouter_web_search_enabled' => $openrouter_web_search_enabled,
        'xai_web_search_enabled' => $xai_web_search_enabled,
        'google_search_grounding_enabled' => $google_search_grounding_enabled,
        // OpenAI Web Search sub-settings
        'openai_web_search_context_size' => $openai_web_search_context_size,
        'openai_web_search_loc_type' => $openai_web_search_loc_type,
        'openai_web_search_loc_country' => $openai_web_search_loc_country,
        'openai_web_search_loc_city' => $openai_web_search_loc_city,
        'openai_web_search_loc_region' => $openai_web_search_loc_region,
        'openai_web_search_loc_timezone' => $openai_web_search_loc_timezone,
        // Claude Web Search sub-settings
        'claude_web_search_max_uses' => $claude_web_search_max_uses,
        'claude_web_search_loc_type' => $claude_web_search_loc_type,
        'claude_web_search_loc_country' => $claude_web_search_loc_country,
        'claude_web_search_loc_city' => $claude_web_search_loc_city,
        'claude_web_search_loc_region' => $claude_web_search_loc_region,
        'claude_web_search_loc_timezone' => $claude_web_search_loc_timezone,
        'claude_web_search_allowed_domains' => $claude_web_search_allowed_domains,
        'claude_web_search_blocked_domains' => $claude_web_search_blocked_domains,
        'claude_web_search_cache_ttl' => $claude_web_search_cache_ttl,
        // OpenRouter Web Search sub-settings
        'openrouter_web_search_engine' => $openrouter_web_search_engine,
        'openrouter_web_search_max_results' => $openrouter_web_search_max_results,
        'openrouter_web_search_search_prompt' => $openrouter_web_search_search_prompt,
        // Google Search Grounding sub-settings
        'google_grounding_mode' => $google_grounding_mode,
        'google_grounding_dynamic_threshold' => $google_grounding_dynamic_threshold,
        // Save protection flags
        'allow_empty_structure' => $allow_empty_structure,
        // Labels
        'labels' => $final_labels,
    ];

    $settings = apply_filters('aipkit_ai_forms_sanitize_save_settings', $settings, $post_data, $form_id);
    if (is_wp_error($settings)) {
        $handler_instance->send_wp_error($settings, 400);
        return;
    }
    if (!is_array($settings)) {
        $handler_instance->send_wp_error(new WP_Error('invalid_save_settings', __('Invalid form settings submitted.', 'gpt3-ai-content-generator')), 400);
        return;
    }

    if ($form_id) {
        $updated_post_id = wp_update_post([
            'ID' => $form_id,
            'post_title' => $title,
        ], true);

        if (is_wp_error($updated_post_id)) {
            $handler_instance->send_wp_error($updated_post_id);
            return;
        }
        $saved = $form_storage->save_form_settings($form_id, $settings);
        if (!$saved) {
            $handler_instance->send_wp_error(new WP_Error('save_failed', __('Unable to save form settings.', 'gpt3-ai-content-generator')), 500);
            return;
        }
        $connected_apps = class_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes')
            && method_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes', 'get_ai_form_connected_apps_payload')
            ? \WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes::get_ai_form_connected_apps_payload($form_id)
            : [
                'count' => 0,
                'summary' => '',
                'recipes' => [],
            ];
        wp_send_json_success([
            'message' => __('Form updated successfully.', 'gpt3-ai-content-generator'),
            'form_id' => $form_id,
            'connected_apps' => $connected_apps,
        ]);
    } else {
        $result = $form_storage->create_form($title, $settings);
        if (is_wp_error($result)) {
            $handler_instance->send_wp_error($result);
        } else {
            $new_form_id = absint($result);
            $connected_apps = class_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes')
                && method_exists('\WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes', 'get_ai_form_connected_apps_payload')
                ? \WPAICG\Lib\Integrations\Recipes\AIPKit_Stored_Recipes::get_ai_form_connected_apps_payload($new_form_id)
                : [
                    'count' => 0,
                    'summary' => '',
                    'recipes' => [],
                ];
            wp_send_json_success([
                'message' => __('Form created successfully.', 'gpt3-ai-content-generator'),
                'form_id' => $new_form_id,
                'connected_apps' => $connected_apps,
            ]);
        }
    }
}
