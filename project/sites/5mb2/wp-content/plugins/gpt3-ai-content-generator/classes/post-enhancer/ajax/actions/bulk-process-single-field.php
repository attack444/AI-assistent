<?php


namespace WPAICG\PostEnhancer\Ajax\Actions;

use WPAICG\PostEnhancer\Ajax\Base\AIPKit_Post_Enhancer_Base_Ajax_Action;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner;
use WPAICG\SEO\AIPKit_SEO_Helper;
use WP_Error;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Core\Stream\Vector as VectorContextBuilder;

use function WPAICG\PostEnhancer\Ajax\Base\get_post_full_content;
use function WPAICG\PostEnhancer\Ajax\Base\log_enhancer_bulk_update_logic;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(AIPKit_Content_Writer_Output_Cleaner::class)) {
    $aipkit_output_cleaner_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-content-writer-output-cleaner.php';
    if (file_exists($aipkit_output_cleaner_path)) {
        require_once $aipkit_output_cleaner_path;
    }
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$vector_logic_base_path = WPAICG_PLUGIN_DIR . 'classes/core/stream/vector/';
if (file_exists($vector_logic_base_path . 'fn-build-vector-search-context.php')) {
    require_once $vector_logic_base_path . 'fn-build-vector-search-context.php';
}

/**
 * AJAX handler for processing a single field of a post during bulk enhancement.
 */
class AIPKit_PostEnhancer_Bulk_Process_Single_Field extends AIPKit_Post_Enhancer_Base_Ajax_Action
{
    public function handle(): void
    {
        $permission_check = $this->check_permissions('aipkit_generate_title_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_error_response($permission_check);
            return;
        }

        $post = $this->get_post();
        if (is_wp_error($post)) {
            $this->send_error_response($post);
            return;
        }
        $feature_permission = $this->check_content_update_permissions($post);
        if (is_wp_error($feature_permission)) {
            $this->send_error_response($feature_permission);
            return;
        }

    // Optional: a conversation UUID to aggregate all steps into one log record
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
    $conv_uuid = isset($_POST['conversation_uuid']) ? sanitize_key(wp_unslash($_POST['conversation_uuid'])) : null;

        // Get the specific field to process
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
        $field = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';
        if (empty($field)) {
            $this->send_error_response(new WP_Error('missing_field', __('No field specified for processing.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        $is_pro = class_exists('\\WPAICG\\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
        $enhancer_source = isset($_POST['enhancer_source']) ? sanitize_key(wp_unslash($_POST['enhancer_source'])) : '';
        $bypass_pro_checks = ($enhancer_source === 'post_enhancer');

        if (!$is_pro && !$bypass_pro_checks) {
            if ($post->post_type === 'product') {
                $this->send_error_response(new WP_Error('pro_required', __('Optimize Products is a Pro feature.', 'gpt3-ai-content-generator'), ['status' => 403]));
                return;
            }
            if ($post->post_type !== 'attachment' && !in_array($field, ['title', 'content'], true)) {
                $this->send_error_response(new WP_Error('pro_required', __('SEO field updates are available on Pro.', 'gpt3-ai-content-generator'), ['status' => 403]));
                return;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: Nonce is checked in check_permissions.
        $item_config_json = isset($_POST['enhancements']) ? wp_unslash($_POST['enhancements']) : '{}';
        $item_config = json_decode($item_config_json, true);

        if (empty($item_config) || !is_array($item_config) || empty($item_config[$field]['prompt'])) {
            $this->send_error_response(new WP_Error('invalid_field_config', __('Invalid configuration for the specified field.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        // AI setup
        $ai_caller = new AIPKit_AI_Caller();
        $global_config = AIPKit_Providers::get_default_provider_config();
        $global_ai_params = AIPKIT_AI_Settings::get_ai_parameters();

        // Use AI config from the request, with fallback to globals
        $provider_raw = $item_config['ai_provider'] ?? $global_config['provider'];
        switch (strtolower($provider_raw)) {
            case 'openai':
                $provider = 'OpenAI';
                break;
            case 'openrouter':
                $provider = 'OpenRouter';
                break;
            case 'google':
                $provider = 'Google';
                break;
            case 'azure':
                $provider = 'Azure';
                break;
            case 'claude':
                $provider = 'Claude';
                break;
            case 'deepseek':
                $provider = 'DeepSeek';
                break;
            case 'xai':
                $provider = 'xAI';
                break;
            case 'ollama':
                $provider = 'Ollama';
                break;
            default:
                $provider = ucfirst(strtolower($provider_raw));
                break;
        }
        $model = $item_config['ai_model'] ?? $global_config['model'];
        $ai_params = [
            'temperature' => isset($item_config['temperature']) ? floatval($item_config['temperature']) : ($global_ai_params['temperature'] ?? 1.0),
            'top_p' => isset($item_config['top_p']) ? floatval($item_config['top_p']) : ($global_ai_params['top_p'] ?? 1.0),
            'max_completion_tokens' => isset($item_config['max_tokens']) ? absint($item_config['max_tokens']) : ($global_ai_params['max_completion_tokens'] ?? 4000),
        ];
        if ($provider === 'OpenAI') {
            $reasoning_effort = AIPKit_OpenAI_Reasoning::normalize_effort_for_model(
                (string) $model,
                $item_config['reasoning_effort'] ?? ''
            );
            if ($reasoning_effort !== '') {
                $ai_params['reasoning'] = ['effort' => $reasoning_effort];
            }
        }

        // Extract Vector Store Settings
        $vector_store_enabled = ($item_config['enable_vector_store'] ?? '0') === '1';
        $vector_store_provider = $item_config['vector_store_provider'] ?? null;
        $vector_store_top_k = isset($item_config['vector_store_top_k']) ? absint($item_config['vector_store_top_k']) : 3;
        $openai_vector_store_ids = $item_config['openai_vector_store_ids'] ?? [];
        $pinecone_index_name = $item_config['pinecone_index_name'] ?? null;
        $qdrant_collection_name = $item_config['qdrant_collection_name'] ?? null;
        $vector_embedding_provider = $item_config['vector_embedding_provider'] ?? null;
        $vector_embedding_model = $item_config['vector_embedding_model'] ?? null;

        // Prepare OpenAI vector tools parameter if needed
        if ($vector_store_enabled && $provider === 'OpenAI' && $vector_store_provider === 'openai' && !empty($openai_vector_store_ids)) {
            $ai_params['vector_store_tool_config'] = [
                'type'             => 'file_search',
                'vector_store_ids' => $openai_vector_store_ids,
                'max_num_results'  => $vector_store_top_k,
            ];
        }

        $system_instruction = 'You are an expert SEO copywriter. You follow instructions precisely. Your response must contain ONLY the generated text, with no introductory phrases, labels, or quotation marks.';

        // Gather placeholders
        $original_meta = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: (get_post_meta($post->ID, '_aioseo_description', true) ?: '');
        $original_focus_keyword = AIPKit_SEO_Helper::get_focus_keyword($post->ID);
        $original_tags = AIPKit_SEO_Helper::get_tags_as_string($post->ID);
        $categories = AIPKit_SEO_Helper::get_categories_as_string($post->ID);
        $original_alt = '';
        $original_caption = '';
        $original_description = '';
        $file_name = '';

        if ($post->post_type === 'attachment') {
            $original_alt = (string) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $original_caption = (string) $post->post_excerpt;
            $original_description = (string) $post->post_content;
            $attached_file = (string) get_attached_file($post->ID);
            if ($attached_file) {
                $file_name = wp_basename($attached_file);
            }
        }

        $placeholders = [
            '{original_title}' => $post->post_title,
            '{original_content}' => get_post_full_content($post),
            '{original_excerpt}' => $post->post_excerpt,
            '{original_meta_description}' => $original_meta,
            '{original_focus_keyword}' => $original_focus_keyword ?: '',
            '{original_tags}' => $original_tags,
            '{categories}' => $categories,
            '{original_caption}' => $original_caption,
            '{original_description}' => $original_description,
            '{original_alt}' => $original_alt,
            '{file_name}' => $file_name,
        ];

        // Add WooCommerce placeholders if applicable
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $placeholders['{price}'] = $product->get_price();
                $placeholders['{regular_price}'] = $product->get_regular_price();
                $placeholders['{sale_price}'] = $product->get_sale_price();
                $placeholders['{sku}'] = $product->get_sku();
                $placeholders['{stock_quantity}'] = $product->get_stock_quantity() ?? 'N/A';
                $placeholders['{stock_status}'] = $product->get_stock_status();
                $placeholders['{weight}'] = $product->get_weight();
                $placeholders['{length}'] = $product->get_length();
                $placeholders['{width}'] = $product->get_width();
                $placeholders['{height}'] = $product->get_height();
                $placeholders['{short_description}'] = wp_strip_all_tags($product->get_short_description());
                $placeholders['{purchase_note}'] = $product->get_purchase_note();
                
                $category_terms = get_the_terms($post->ID, 'product_cat');
                if (!is_wp_error($category_terms) && !empty($category_terms)) {
                    $category_names = wp_list_pluck($category_terms, 'name');
                    $placeholders['{product_categories}'] = implode(', ', $category_names);
                } else {
                    $placeholders['{product_categories}'] = '';
                }

                $attributes = $product->get_attributes();
                $attribute_string = '';
                foreach ($attributes as $attribute) {
                    if ($attribute->is_taxonomy()) {
                        $terms = wp_get_post_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $attribute_string .= wc_attribute_label($attribute->get_name()) . ': ' . implode(', ', $terms) . '; ';
                        }
                    } else {
                        $attribute_string .= wc_attribute_label($attribute->get_name()) . ': ' . implode(', ', $attribute->get_options()) . '; ';
                    }
                }
                $placeholders['{attributes}'] = rtrim($attribute_string, '; ');
            }
        }

        // Vector Context Logic
        $vector_context = '';
        $vector_store_manager = null;
        if ($vector_store_enabled) {
            if (class_exists(AIPKit_Vector_Store_Manager::class)) {
                $vector_store_manager = new AIPKit_Vector_Store_Manager();
            }

            if ($vector_store_manager && function_exists('\WPAICG\Core\Stream\Vector\build_vector_search_context_logic')) {
                $vector_context = VectorContextBuilder\build_vector_search_context_logic(
                    $ai_caller,
                    $vector_store_manager,
                    $post->post_title, // Use post title as the query
                    $item_config,      // Pass the whole config as it contains all vector settings
                    $provider,         // Main AI provider
                    null,
                    null,
                    null,
                    null,
                    null // No frontend context in bulk enhancer
                );
            }
        }
        if (!empty($vector_context)) {
            $system_instruction = "## Relevant information from knowledge base:\n" . trim($vector_context) . "\n##\n\n" . $system_instruction;
        }

        $image_context = '';
        $image_fields = ['keyword', 'title', 'excerpt', 'content'];
        if ($post->post_type === 'attachment' && in_array($field, $image_fields, true)) {
            $image_context = $this->get_image_context_for_attachment($post->ID, $provider, $ai_caller);
            if (!empty($image_context)) {
                $placeholders['{image_context}'] = $image_context;
            }
        }

        // Process the specific field
        $raw_prompt = $item_config[$field]['prompt'];
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $raw_prompt);
        if (!empty($image_context) && strpos($raw_prompt, '{image_context}') === false) {
            $prompt .= "\n\nImage context: " . $image_context;
        }
        
    $ai_result = $ai_caller->make_standard_call($provider, $model, [['role' => 'user', 'content' => $prompt]], $ai_params, $system_instruction, ['post_id' => $post->ID]);

        if (is_wp_error($ai_result)) {
            // Log error to Admin Logs as well
            $error_message = 'Error: ' . $ai_result->get_error_message();
            $error_data = $ai_result->get_error_data() ?? [];
            $request_payload_log = $error_data['request_payload'] ?? null;
            log_enhancer_bulk_update_logic(
                (int) $post->ID,
                $field,
                $prompt,
                $error_message,
                $provider,
                $model,
                null,
                is_array($request_payload_log) ? $request_payload_log : null,
                $conv_uuid
            );
            $this->send_error_response($ai_result);
            return;
        }

        if (empty($ai_result['content'])) {
            // Log empty response as an error entry
            $request_payload_log = $ai_result['request_payload_log'] ?? null;
            log_enhancer_bulk_update_logic(
                (int) $post->ID,
                $field,
                $prompt,
                'Error: AI returned empty response for this field.',
                $provider,
                $model,
                $ai_result['usage'] ?? null,
                is_array($request_payload_log) ? $request_payload_log : null,
                $conv_uuid
            );
            $this->send_error_response(new WP_Error('empty_response', __('AI returned empty response for this field.', 'gpt3-ai-content-generator'), ['status' => 500]));
            return;
        }

        $new_value = trim(str_replace('"', '', $ai_result['content']));
        $success_message = '';
        $updated_value = null;

        // Log success before updating the post based on field type so Admin Logs capture the generated content
        log_enhancer_bulk_update_logic(
            (int) $post->ID,
            $field,
            $prompt,
            $new_value,
            $provider,
            $model,
            $ai_result['usage'] ?? null,
            $ai_result['request_payload_log'] ?? null,
            $conv_uuid
        );

        // Update the post based on field type
        switch ($field) {
            case 'keyword':
                if ($post->post_type === 'attachment') {
                    update_post_meta($post->ID, '_wp_attachment_image_alt', sanitize_text_field($new_value));
                    $success_message = 'Alt text updated successfully';
                } else {
                    AIPKit_SEO_Helper::update_focus_keyword($post->ID, $new_value);
                    $success_message = 'Focus keyword updated successfully';
                }
                break;
            case 'title':
                wp_update_post(['ID' => $post->ID, 'post_title' => sanitize_text_field($new_value)]);
                $updated_value = get_post_field('post_title', $post->ID);
                $success_message = 'Title updated successfully';
                break;
            case 'excerpt':
                wp_update_post(['ID' => $post->ID, 'post_excerpt' => wp_kses_post($new_value)]);
                $success_message = 'Excerpt updated successfully';
                break;
            case 'content':
                $html_content = AIPKit_Content_Writer_Output_Cleaner::convert_basic_markdown_to_html($new_value);
                wp_update_post(['ID' => $post->ID, 'post_content' => wp_kses_post($html_content)]);
                $success_message = 'Content updated successfully';
                break;
            case 'tags':
                if (class_exists('\\WPAICG\\SEO\\AIPKit_SEO_Helper')) {
                    $result = \WPAICG\SEO\AIPKit_SEO_Helper::update_tags($post->ID, sanitize_text_field($new_value));
                    if ($result) {
                        $success_message = 'Tags updated successfully';
                    } else {
                        $this->send_error_response(new WP_Error('tags_update_failed', __('Failed to update tags.', 'gpt3-ai-content-generator'), ['status' => 500]));
                        return;
                    }
                } else {
                    $this->send_error_response(new WP_Error('tags_helper_missing', __('Tags helper class not available.', 'gpt3-ai-content-generator'), ['status' => 500]));
                    return;
                }
                break;
            case 'meta':
                AIPKit_SEO_Helper::update_meta_description($post->ID, sanitize_text_field($new_value));
                $success_message = 'Meta description updated successfully';
                break;
            default:
                $this->send_error_response(new WP_Error('unsupported_field', __('Unsupported field type.', 'gpt3-ai-content-generator'), ['status' => 400]));
                return;
        }

        if ($post->post_type === 'attachment' && $updated_value === null) {
            switch ($field) {
                case 'keyword':
                    $updated_value = sanitize_text_field($new_value);
                    break;
                case 'excerpt':
                case 'content':
                    $updated_value = trim(wp_strip_all_tags($new_value));
                    break;
            }
        }

        $response_payload = [
            'message' => $success_message,
            'field' => $field,
            'post_id' => $post->ID
        ];
        if ($updated_value !== null) {
            $response_payload['updated_value'] = $updated_value;
        }

        wp_send_json_success($response_payload);
    }

    private function get_image_context_for_attachment(int $attachment_id, string $provider, AIPKit_AI_Caller $ai_caller): string
    {
        if ($provider !== 'OpenAI') {
            return '';
        }

        $openai_config = AIPKit_Providers::get_provider_data('OpenAI');
        if (empty($openai_config['api_key'])) {
            return '';
        }

        $file_path = $this->get_attachment_image_path($attachment_id);
        $file_mtime = $file_path && file_exists($file_path) ? (int) filemtime($file_path) : 0;
        $transient_key = 'aipkit_cw_img_ctx_' . $attachment_id . '_' . $file_mtime;
        $cached_context = get_transient($transient_key);
        if (is_string($cached_context) && $cached_context !== '') {
            return $cached_context;
        }

        $image_payload = $this->get_attachment_image_payload($attachment_id);
        if (empty($image_payload['base64']) || empty($image_payload['type'])) {
            return '';
        }

        $analysis_prompt = 'Describe the image in one short sentence for SEO context. Return only the description.';
        $analysis_params = [
            'temperature' => 0.2,
            'max_completion_tokens' => 60,
            'image_inputs' => [
                [
                    'base64' => $image_payload['base64'],
                    'type' => $image_payload['type'],
                    'detail' => 'low',
                ],
            ],
        ];

        $analysis_result = $ai_caller->make_standard_call(
            'OpenAI',
            'gpt-4.1-mini',
            [['role' => 'user', 'content' => $analysis_prompt]],
            $analysis_params,
            null,
            ['post_id' => $attachment_id]
        );

        if (is_wp_error($analysis_result) || empty($analysis_result['content'])) {
            return '';
        }

        $context = trim(preg_replace('/\s+/', ' ', $analysis_result['content']));
        if ($context === '') {
            return '';
        }

        set_transient($transient_key, $context, 30 * MINUTE_IN_SECONDS);
        return $context;
    }

    private function get_attachment_image_path(int $attachment_id): string
    {
        $original_path = (string) get_attached_file($attachment_id);
        if ($original_path === '') {
            return '';
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($meta) && !empty($meta['sizes']['medium']['file'])) {
            $dir = trailingslashit(pathinfo($original_path, PATHINFO_DIRNAME));
            $medium_path = $dir . $meta['sizes']['medium']['file'];
            if (file_exists($medium_path)) {
                return $medium_path;
            }
        }

        return file_exists($original_path) ? $original_path : '';
    }

    private function get_attachment_image_payload(int $attachment_id): array
    {
        $file_path = $this->get_attachment_image_path($attachment_id);
        if ($file_path !== '') {
            $payload = $this->get_image_payload_from_path($file_path);
            if (!empty($payload)) {
                return $payload;
            }
        }

        $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
        if (empty($image_url)) {
            return [];
        }

        $response = wp_remote_get($image_url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return [];
        }

        $content_type = (string) wp_remote_retrieve_header($response, 'content-type');
        $mime_type = trim(strtok($content_type, ';'));
        if ($mime_type === '' || strpos($mime_type, 'image/') !== 0) {
            return [];
        }

        return [
            'base64' => base64_encode($body),
            'type' => $mime_type,
        ];
    }

    private function get_image_payload_from_path(string $file_path): array
    {
        if (!is_readable($file_path)) {
            return [];
        }

        $file_size = filesize($file_path);
        if ($file_size !== false && $file_size > 50 * 1024 * 1024) {
            return [];
        }

        $image_bytes = file_get_contents($file_path);
        if ($image_bytes === false) {
            return [];
        }

        $mime_type = wp_get_image_mime($file_path);
        if (empty($mime_type)) {
            $filetype = wp_check_filetype($file_path);
            $mime_type = $filetype['type'] ?? '';
        }

        if ($mime_type === '' || strpos($mime_type, 'image/') !== 0) {
            return [];
        }

        return [
            'base64' => base64_encode($image_bytes),
            'type' => $mime_type,
        ];
    }
}
