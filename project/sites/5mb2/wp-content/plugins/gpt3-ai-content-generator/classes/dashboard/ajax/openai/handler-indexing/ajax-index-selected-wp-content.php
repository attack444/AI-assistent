<?php


namespace WPAICG\Dashboard\Ajax\OpenAI\HandlerIndexing;

use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler;
use WPAICG\AIPKit_Providers;
use WP_Error;
use WP_Query;

// DO NOT require_once the fn-*.php files from here; they are loaded by Vector_Store_Ajax_Handlers_Loader
// However, the new handler-indexing/ajax-*.php WILL be required by the methods below.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles the logic for fetching and indexing WordPress content into an OpenAI Vector Store.
 * Called by AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler::ajax_index_selected_wp_content().
 */
function do_ajax_index_selected_wp_content_logic(AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler $handler_instance): void
{
    // Permission check already done by the handler calling this

    $openai_post_processor = $handler_instance->get_openai_post_processor();
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$openai_post_processor || !$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('processor_missing', __('Vector processing components are missing.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_data = wp_unslash($_POST);
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $post_ids_raw = isset($post_data['post_ids']) && is_array($post_data['post_ids']) ? $post_data['post_ids'] : [];
    $post_ids = array_map('absint', $post_ids_raw);
    $post_ids = array_filter($post_ids, function ($id) {
        return $id > 0;
    });
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $target_store_id = isset($post_data['target_store_id']) ? sanitize_text_field($post_data['target_store_id']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $new_store_name = isset($post_data['new_store_name_openai']) ? sanitize_text_field($post_data['new_store_name_openai']) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in the calling handler method.
    $provider = isset($post_data['provider']) ? sanitize_key($post_data['provider']) : 'openai';

    if (empty($post_ids)) {
        wp_send_json_error(['message' => __('No posts selected for indexing.', 'gpt3-ai-content-generator')], 400);
        return;
    }
    if (empty($target_store_id) && (empty($new_store_name) || $provider !== 'openai')) {
        wp_send_json_error(['message' => __('Please select an existing vector store or provide a name for a new OpenAI store.', 'gpt3-ai-content-generator')], 400);
        return;
    }
    if ($provider !== 'openai') {
        wp_send_json_error(['message' => __('Currently, only OpenAI vector stores are supported for WordPress content indexing from this UI.', 'gpt3-ai-content-generator')], 400);
        return;
    }

    $openai_config = AIPKit_Providers::get_provider_data('OpenAI');
    if (empty($openai_config['api_key'])) {
        $handler_instance->send_wp_error(new WP_Error('missing_openai_key', __('OpenAI API Key is not configured in global settings.', 'gpt3-ai-content-generator')));
        return;
    }

    $actual_store_id = $target_store_id;
    $actual_store_name = '';
    $is_new_store_created = false;

    if (!empty($new_store_name) && $provider === 'openai') {
        $index_config = ['metadata' => ['source_type' => 'wp_content_ai_training']];
        $create_result = $vector_store_manager->create_index_if_not_exists('OpenAI', $new_store_name, $index_config, $openai_config);
        if (is_wp_error($create_result)) {
            wp_send_json_error(['message' => 'Failed to create new vector store: ' . $create_result->get_error_message()], 500);
            return;
        }
        if (is_array($create_result) && isset($create_result['id'])) {
            $actual_store_id = $create_result['id'];
            $actual_store_name = $create_result['name'] ?? $new_store_name;
            $is_new_store_created = true;
            if ($vector_store_registry) {
                $vector_store_registry->add_registered_store('OpenAI', $create_result);
            }
        } else {
            wp_send_json_error(['message' => 'Failed to create or identify vector store ID after creation attempt.'], 500);
            return;
        }
    } elseif (!empty($actual_store_id)) {
        $existing_store_details = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
        if (!is_wp_error($existing_store_details) && isset($existing_store_details['name'])) {
            $actual_store_name = $existing_store_details['name'];
        }
    }
    if (empty($actual_store_id)) {
        wp_send_json_error(['message' => __('Could not determine target vector store ID.', 'gpt3-ai-content-generator')], 500);
        return;
    }

    $processed_count = 0;
    $successful_posts = [];
    $failed_posts_log = [];

    foreach ($post_ids as $post_id) {
        // --- FIXED: Changed 4th argument from $openai_config (array) to true (bool) ---
        $result = $openai_post_processor->index_single_post_to_store($post_id, $actual_store_id, $actual_store_name);
        // --- END FIX ---
        if ($result['status'] === 'success') {
            $successful_posts[] = $post_id;
            $processed_count++;
        } else {
            $failed_posts_log[$post_id] = $result['message'];
        }
    }

    if ($vector_store_registry) {
        $updated_store_data = $vector_store_manager->describe_single_index('OpenAI', $actual_store_id, $openai_config);
        if (!is_wp_error($updated_store_data) && is_array($updated_store_data) && isset($updated_store_data['id'])) {
            $vector_store_registry->add_registered_store('OpenAI', $updated_store_data);
        }
    }

    /* translators: %1$d: The number of posts processed, %2$s: The name of the vector store. */
    $response_message = sprintf(_n('%1$d post processed and submitted to vector store "%2$s".', '%1$d posts processed and submitted to vector store "%2$s".', $processed_count, 'gpt3-ai-content-generator'), $processed_count, esc_html($actual_store_name ?: $actual_store_id));

    if (!empty($failed_posts_log)) {
        /* translators: %d: Number of failed posts */
        $response_message .= ' ' . sprintf(__('Some posts failed: %d. Check data source logs for details.', 'gpt3-ai-content-generator'), count($failed_posts_log));
    }

    wp_send_json_success([
        'message' => $response_message,
        'processed_count' => $processed_count,
        'total_count' => count($post_ids),
        'new_store_id' => ($is_new_store_created ? $actual_store_id : null),
        'failed_posts_summary' => array_keys($failed_posts_log)
    ]);
}
