<?php

namespace WPAICG\Dashboard\Ajax\Chroma\HandlerCollections;

use WP_Error;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Chroma_Ajax_Handler;

if (!defined('ABSPATH')) {
    exit;
}

function _aipkit_chroma_normalize_collection_for_ui(array $collection): array
{
    $api_id = isset($collection['id']) ? (string) $collection['id'] : '';
    $name = isset($collection['name']) && (string) $collection['name'] !== ''
        ? (string) $collection['name']
        : $api_id;

    $collection['chroma_id'] = $api_id;
    $collection['id'] = $name;
    $collection['name'] = $name;
    $collection['provider'] = 'Chroma';

    return $collection;
}

function _aipkit_chroma_ajax_list_collections_logic(AIPKit_Vector_Store_Chroma_Ajax_Handler $handler_instance): void
{
    $vector_store_manager = $handler_instance->get_vector_store_manager();
    $vector_store_registry = $handler_instance->get_vector_store_registry();

    if (!$vector_store_manager || !$vector_store_registry) {
        $handler_instance->send_wp_error(new WP_Error('manager_not_ready_list_chroma', __('Vector Store components not available for Chroma.', 'gpt3-ai-content-generator'), ['status' => 500]));
        return;
    }

    $chroma_config = $handler_instance->_get_chroma_config();
    if (is_wp_error($chroma_config)) {
        $handler_instance->send_wp_error($chroma_config);
        return;
    }

    $response = $vector_store_manager->list_all_indexes('Chroma', $chroma_config, 100);
    if (is_wp_error($response)) {
        $handler_instance->send_wp_error($response);
        return;
    }

    $detailed_collections = [];
    foreach ((array) $response as $collection_summary) {
        if (!is_array($collection_summary)) {
            continue;
        }
        $collection_name = $collection_summary['name'] ?? ($collection_summary['id'] ?? null);
        if (!$collection_name) {
            continue;
        }
        $details = $vector_store_manager->describe_single_index('Chroma', (string) $collection_name, $chroma_config);
        $collection = is_wp_error($details) ? $collection_summary : array_merge($collection_summary, $details);
        $detailed_collections[] = _aipkit_chroma_normalize_collection_for_ui($collection);
    }

    $detailed_collections = $vector_store_registry->replace_provider_cache('Chroma', $detailed_collections);

    wp_send_json_success([
        'collections' => $detailed_collections,
        'message' => __('Chroma collections synced successfully.', 'gpt3-ai-content-generator'),
    ]);
}
