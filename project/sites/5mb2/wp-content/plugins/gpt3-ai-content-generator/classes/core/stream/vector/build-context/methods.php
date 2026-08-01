<?php

namespace WPAICG\Core\Stream\Vector\BuildContext;

use WPAICG\AIPKit_Providers;
use WPAICG\Core\AIPKit_AI_Caller;
use WP_Error;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- check-prerequisites.php ---
/**
 * Checks the prerequisites for building vector search context.
 *
 * @param bool $vector_store_enabled
 * @param string $user_message
 * @param \WPAICG\Core\AIPKit_AI_Caller|null $ai_caller
 * @param \WPAICG\Vector\AIPKit_Vector_Store_Manager|null $vector_store_manager
 * @return bool True if prerequisites are met, false otherwise.
 */
function check_prerequisites_logic(
    bool $vector_store_enabled,
    string $user_message,
    ?\WPAICG\Core\AIPKit_AI_Caller $ai_caller,
    ?\WPAICG\Vector\AIPKit_Vector_Store_Manager $vector_store_manager
): bool {
    if (!$vector_store_enabled || empty($user_message)) {
        return false;
    }

    if (!$ai_caller || !$vector_store_manager) {
        return false;
    }
    return true;
}

// --- normalize-embedding-provider.php ---
/**
 * Normalizes the embedding provider key to a standard name.
 *
 * @param string $embedding_provider_key The key from settings (e.g., 'openai', 'google').
 * @return string The normalized provider name (e.g., 'OpenAI', 'Google').
 */
function normalize_embedding_provider_logic(string $embedding_provider_key): string
{
    $provider_lookup = sanitize_key((string) strtolower($embedding_provider_key));

    return AIPKit_Providers::normalize_embedding_provider_name(
        $provider_lookup,
        'stream_vector_build_context'
    );
}

// --- resolve-embedding-vector.php ---
/**
 * Generates an embedding vector for the given user message using the specified provider and model.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller $ai_caller
 * @param string $user_message
 * @param string $embedding_provider_normalized
 * @param string $embedding_model
 * @return array|WP_Error The embedding vector values or WP_Error on failure.
 */
function resolve_embedding_vector_logic(
    AIPKit_AI_Caller $ai_caller,
    string $user_message,
    string $embedding_provider_normalized,
    string $embedding_model
) {
    $embedding_options = ['model' => $embedding_model];
    $embedding_result = $ai_caller->generate_embeddings($embedding_provider_normalized, $user_message, $embedding_options);

    if (is_wp_error($embedding_result) || empty($embedding_result['embeddings'][0])) {
        $error_message = is_wp_error($embedding_result) ? $embedding_result->get_error_message() : 'No embeddings returned.';
        return new WP_Error('embedding_failed_for_query', $error_message);
    }

    return $embedding_result['embeddings'][0];
}

// --- build-score-item.php ---
/**
 * Adds optional vector metadata to a score item before it is stored in logs.
 *
 * @param array<string,mixed> $score_item
 * @param array<string,mixed> $metadata
 * @return array<string,mixed>
 */
function build_vector_search_score_item_logic(array $score_item, array $metadata = []): array
{
    $chunk_number = null;
    $chunk_index = null;

    if (isset($metadata['chunk_number']) && is_numeric($metadata['chunk_number'])) {
        $chunk_number = max(1, (int) $metadata['chunk_number']);
        $chunk_index = $chunk_number - 1;
    } elseif (isset($metadata['chunk_index']) && is_numeric($metadata['chunk_index'])) {
        $chunk_index = max(0, (int) $metadata['chunk_index']);
        $chunk_number = $chunk_index + 1;
    }

    $total_chunks = isset($metadata['total_chunks']) && is_numeric($metadata['total_chunks'])
        ? (int) $metadata['total_chunks']
        : null;

    if ($chunk_number !== null && $total_chunks !== null && $total_chunks > 0) {
        $score_item['chunk_index'] = $chunk_index;
        $score_item['chunk_number'] = $chunk_number;
        $score_item['total_chunks'] = $total_chunks;
    }

    foreach (['original_filename', 'filename'] as $file_key) {
        if (!empty($metadata[$file_key]) && is_scalar($metadata[$file_key])) {
            $score_item['file_name'] = sanitize_text_field((string) $metadata[$file_key]);
            break;
        }
    }

    if (isset($metadata['char_start']) && is_numeric($metadata['char_start'])) {
        $score_item['char_start'] = (int) $metadata['char_start'];
    }
    if (isset($metadata['char_end']) && is_numeric($metadata['char_end'])) {
        $score_item['char_end'] = (int) $metadata['char_end'];
    }

    return $score_item;
}

// --- resolve-openai-context.php ---
/**
 * Resolves OpenAI vector search context.
 *
 * @param AIPKit_Vector_Store_Manager $vector_store_manager Instance of Vector Store Manager.
 * @param string $user_message The user's current message.
 * @param array $bot_settings The settings of the current bot.
 * @param string $main_provider The main AI provider being used for the chat.
 * @param string|null $frontend_active_openai_vs_id Optional active OpenAI Vector Store ID from frontend.
 * @param int $vector_top_k Number of results to fetch.
 * @param array|null &$vector_search_scores_output Optional reference to capture scores for logging.
 * @return string Formatted OpenAI context results.
 */
function resolve_openai_context_logic(
    AIPKit_Vector_Store_Manager $vector_store_manager,
    string $user_message,
    array $bot_settings,
    string $main_provider,
    ?string $frontend_active_openai_vs_id,
    int $vector_top_k,
    ?array &$vector_search_scores_output = null
): string {
    // For OpenAI main provider, use File Search tool instead of prompt injection.
    // For non-OpenAI providers, inject a concise context string from OpenAI vector stores.
    $openai_results = "";
    $should_inject_context = ($main_provider !== 'OpenAI');

    if (!$should_inject_context) {
        // OpenAI chat requests attach the file_search tool elsewhere; pre-searching here duplicates retrieval and adds latency.
        return "";
    }

    $openai_vector_store_ids_from_settings = $bot_settings['openai_vector_store_ids'] ?? [];
    $confidence_threshold_percent = (int)($bot_settings['vector_store_confidence_threshold'] ?? 20);
    $openai_score_threshold = round($confidence_threshold_percent / 100, 4); // Convert to 0.0-1.0 scale for OpenAI and round to avoid precision issues

    $final_openai_vector_store_ids = $openai_vector_store_ids_from_settings;
    if ($frontend_active_openai_vs_id && !in_array($frontend_active_openai_vs_id, $final_openai_vector_store_ids, true)) {
        $final_openai_vector_store_ids[] = $frontend_active_openai_vs_id;
    }
    $final_openai_vector_store_ids = array_unique(array_filter($final_openai_vector_store_ids));

    if (!empty($final_openai_vector_store_ids)) {
        if (!class_exists(AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            } else {
                return "";
            }
        }
        $openai_api_config = AIPKit_Providers::get_provider_data('OpenAI');
        // Only proceed if the OpenAI API key is available for the pre-search.
        if (!empty($openai_api_config['api_key'])) {
            $total_results_added = 0;
            foreach ($final_openai_vector_store_ids as $current_vs_id) {
                if (empty($current_vs_id)) {
                    continue;
                }

                // Add ranking_options to the search query for OpenAI server-side filtering
                $search_query_vector = [
                    'query_text' => $user_message,
                    'ranking_options' => [
                        'score_threshold' => $openai_score_threshold
                    ]
                ];
                
                $search_results = $vector_store_manager->query_vectors('OpenAI', $current_vs_id, $search_query_vector, $vector_top_k, [], $openai_api_config);

                if (!is_wp_error($search_results) && !empty($search_results)) {
                    $current_store_results = "";
                    foreach ($search_results as $item) {
                        if (isset($item['score']) && (float)$item['score'] < $openai_score_threshold) {
                            continue;
                        }

                        if (!empty($item['content'])) {
                            $textContent = is_array($item['content']) ? implode(" ", array_column(array_filter($item['content'], fn ($p) => $p['type'] === 'text'), 'text')) : $item['content'];
                            if (!empty(trim($textContent))) {
                                $total_results_added++;
                                if ($should_inject_context) {
                                    $current_store_results .= "- " . trim($textContent) . "\n";
                                }
                                
                                // Capture score data if reference provided
                                if ($vector_search_scores_output !== null && isset($item['score'])) {
                                    // Get store name from registry
                                    $store_name = $current_vs_id; // fallback to ID
                                    $openai_stores = AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('OpenAI');
                                    foreach ($openai_stores as $store) {
                                        if (isset($store['id']) && $store['id'] === $current_vs_id) {
                                            $store_name = $store['name'] ?? $current_vs_id;
                                            break;
                                        }
                                    }
                                    
                                    $vector_search_scores_output[] = [
                                        'provider' => 'OpenAI',
                                        'store_id' => $current_vs_id,
                                        'store_name' => $store_name,
                                        'result_id' => $item['id'] ?? null,
                                        'score' => $item['score'],
                                        'content_preview' => wp_trim_words(trim($textContent), 10, '...')
                                    ];
                                }
                            }
                        }
                    }
                    if ($should_inject_context && !empty($current_store_results)) {
                        $store_label = sanitize_text_field($store_name ?? $current_vs_id);
                        $openai_results .= "Context from OpenAI Vector Store ({$store_label}):\n" . $current_store_results . "\n";
                    }
                }
            }
        }
    }
    return $openai_results;
}

// --- resolve-pinecone-context.php ---
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Pinecone context resolution only reads plugin-owned vector log tables with prepared scalar values.

/**
 * Resolves Pinecone vector search context.
 *
 * @param AIPKit_AI_Caller $ai_caller Instance of AI Caller.
 * @param AIPKit_Vector_Store_Manager $vector_store_manager Instance of Vector Store Manager.
 * @param string $user_message The user's current message.
 * @param array $bot_settings The settings of the current bot.
 * @param string|null $frontend_active_pinecone_index_name Optional active Pinecone index name from frontend.
 * @param string|null $frontend_active_pinecone_namespace Optional active Pinecone namespace from frontend.
 * @param int $vector_top_k Number of results to fetch.
 * @param \wpdb $wpdb WordPress database instance.
 * @param string $data_source_table_name Vector data source table name.
 * @param array|null &$vector_search_scores_output Optional reference to capture scores for logging.
 * @return string Formatted Pinecone context results.
 */
function resolve_pinecone_context_logic(
    AIPKit_AI_Caller $ai_caller,
    AIPKit_Vector_Store_Manager $vector_store_manager,
    string $user_message,
    array $bot_settings,
    ?string $frontend_active_pinecone_index_name,
    ?string $frontend_active_pinecone_namespace,
    int $vector_top_k,
    \wpdb $wpdb,
    string $data_source_table_name,
    ?array &$vector_search_scores_output = null
): string {
    $pinecone_results = "";
    $pinecone_index_name_from_settings = $bot_settings['pinecone_index_name'] ?? '';
    $vector_embedding_provider = $bot_settings['vector_embedding_provider'] ?? '';
    $vector_embedding_model = $bot_settings['vector_embedding_model'] ?? '';
    $confidence_threshold_percent = (int)($bot_settings['vector_store_confidence_threshold'] ?? 20);
    $pinecone_score_threshold = round($confidence_threshold_percent / 100, 4); // Normalize 0-100 to 0-1 scale and round to avoid precision issues

    if (empty($pinecone_index_name_from_settings) || empty($vector_embedding_provider) || empty($vector_embedding_model)) {
        return "";
    }

    if (!class_exists(AIPKit_Providers::class)) {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($providers_path)) {
            require_once $providers_path;
        } else {
            return "";
        }
    }
    $pinecone_api_config = AIPKit_Providers::get_provider_data('Pinecone');
    if (empty($pinecone_api_config['api_key'])) {
        return "";
    }

    $embedding_provider_normalized = normalize_embedding_provider_logic($vector_embedding_provider);
    $query_vector_values_or_error = resolve_embedding_vector_logic($ai_caller, $user_message, $embedding_provider_normalized, $vector_embedding_model);

    if (is_wp_error($query_vector_values_or_error)) {
        return ""; // Error already logged by resolver
    }
    $query_vector_values = $query_vector_values_or_error;

    $index_to_query = $pinecone_index_name_from_settings;
    $pinecone_results_this_pass = "";

    // 1. Search with file-specific namespace if provided
    if (!empty($frontend_active_pinecone_namespace)) {
        $query_vector_for_file_context = ['vector' => $query_vector_values, 'namespace' => $frontend_active_pinecone_namespace];
        $file_search_results = $vector_store_manager->query_vectors('Pinecone', $index_to_query, $query_vector_for_file_context, $vector_top_k, [], $pinecone_api_config);
        if (!is_wp_error($file_search_results) && !empty($file_search_results)) {
            $formatted_file_results = "";
            foreach ($file_search_results as $item) {
                if (isset($item['score']) && (float)$item['score'] < $pinecone_score_threshold) {
                    continue;
                }
                // END NEW
                $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
                $content_snippet = $metadata['original_content'] ?? ($metadata['text_content'] ?? null);
                if (empty($content_snippet) && isset($item['id'])) {
                    $cache_key = 'aipkit_vds_content_' . md5('pinecone_file_' . $index_to_query . $frontend_active_pinecone_namespace . $item['id']);
                    $cache_group = 'aipkit_vector_source_content';
                    $log_entry = wp_cache_get($cache_key, $cache_group);

                    if (false === $log_entry) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caches will be invalidated.
                        $log_entry = $wpdb->get_row($wpdb->prepare("SELECT indexed_content FROM {$data_source_table_name} WHERE provider = %s AND vector_store_id = %s AND batch_id = %s AND file_id = %s ORDER BY timestamp DESC LIMIT 1", 'Pinecone', $index_to_query, $frontend_active_pinecone_namespace, $item['id']), ARRAY_A);
                        wp_cache_set($cache_key, $log_entry, $cache_group, HOUR_IN_SECONDS);
                    }

                    if ($log_entry && !empty($log_entry['indexed_content'])) {
                        $content_snippet = $log_entry['indexed_content'];
                    }
                }
                if (!empty($content_snippet)) {
                    $formatted_file_results .= "- " . trim($content_snippet) . "\n";
                    
                    // Capture score data if reference provided
                    if ($vector_search_scores_output !== null && isset($item['score'])) {
                        $vector_search_scores_output[] = build_vector_search_score_item_logic(
                            [
                                'provider' => 'Pinecone',
                                'index_name' => $index_to_query,
                                'namespace' => $frontend_active_pinecone_namespace,
                                'result_id' => $item['id'] ?? null,
                                'score' => $item['score'],
                                'content_preview' => wp_trim_words(trim($content_snippet), 10, '...')
                            ],
                            $metadata
                        );
                    }
                }
            }
            if (!empty($formatted_file_results)) {
                $pinecone_results_this_pass .= "Context from Uploaded File (Index {$index_to_query}, Namespace: {$frontend_active_pinecone_namespace}):\n" . $formatted_file_results . "\n";
            }
        }
    }

    // 2. Search general bot knowledge (default/empty namespace)
    $query_vector_for_general_context = ['vector' => $query_vector_values]; // No namespace implies default
    $general_search_results = $vector_store_manager->query_vectors('Pinecone', $index_to_query, $query_vector_for_general_context, $vector_top_k, [], $pinecone_api_config);
    if (!is_wp_error($general_search_results) && !empty($general_search_results)) {
        $formatted_general_results = "";
        foreach ($general_search_results as $item) {
            if (isset($item['score']) && (float)$item['score'] < $pinecone_score_threshold) {
                continue;
            }
            // END NEW
            // Skip if this result was already part of the file-specific context (if a namespace was used)
            $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
            if (!empty($frontend_active_pinecone_namespace) && ($metadata['namespace'] ?? null) === $frontend_active_pinecone_namespace) {
                continue;
            }
            $content_snippet = $metadata['original_content'] ?? ($metadata['text_content'] ?? null);
            if (empty($content_snippet) && isset($item['id'])) {
                $cache_key = 'aipkit_vds_content_' . md5('pinecone_general_' . $index_to_query . $item['id']);
                $cache_group = 'aipkit_vector_source_content';
                $log_entry = wp_cache_get($cache_key, $cache_group);

                if (false === $log_entry) {
                    // Preferred query for legacy general records (no batch)
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query to custom table
                    $log_entry = $wpdb->get_row($wpdb->prepare(
                        "SELECT indexed_content FROM {$data_source_table_name} WHERE provider = 'Pinecone' AND vector_store_id = %s AND (batch_id IS NULL OR batch_id = '') AND file_id = %s ORDER BY timestamp DESC LIMIT 1",
                        $index_to_query,
                        $item['id']
                    ), ARRAY_A);

                    // Fallback: allow any batch_id (covers file uploads where batch_id is set)
                    if (!$log_entry) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query to custom table; results are cached.
                        $log_entry = $wpdb->get_row($wpdb->prepare(
                            "SELECT indexed_content FROM {$data_source_table_name} WHERE provider = 'Pinecone' AND vector_store_id = %s AND file_id = %s ORDER BY timestamp DESC LIMIT 1",
                            $index_to_query,
                            $item['id']
                        ), ARRAY_A);
                    }

                    // Fallback 2: if metadata contains batch_id, query by it explicitly for precision
                    if (!$log_entry && !empty($metadata['batch_id'])) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query to custom table; results are cached.
                        $log_entry = $wpdb->get_row($wpdb->prepare(
                            "SELECT indexed_content FROM {$data_source_table_name} WHERE provider = 'Pinecone' AND vector_store_id = %s AND batch_id = %s AND file_id = %s ORDER BY timestamp DESC LIMIT 1",
                            $index_to_query,
                            $metadata['batch_id'],
                            $item['id']
                        ), ARRAY_A);
                    }

                    wp_cache_set($cache_key, $log_entry, $cache_group, HOUR_IN_SECONDS);
                }

                if ($log_entry && !empty($log_entry['indexed_content'])) {
                    $content_snippet = $log_entry['indexed_content'];
                }
            }
            if (!empty($content_snippet)) {
                $formatted_general_results .= "- " . trim($content_snippet) . "\n";
                
                // Capture score data if reference provided
                if ($vector_search_scores_output !== null && isset($item['score'])) {
                    $vector_search_scores_output[] = build_vector_search_score_item_logic(
                        [
                            'provider' => 'Pinecone',
                            'index_name' => $index_to_query,
                            'namespace' => null, // General context has no specific namespace
                            'result_id' => $item['id'] ?? null,
                            'score' => $item['score'],
                            'content_preview' => wp_trim_words(trim($content_snippet), 10, '...')
                        ],
                        $metadata
                    );
                }
            }
        }
        if (!empty($formatted_general_results)) {
            $pinecone_results_this_pass .= "General Knowledge from Bot (Index {$index_to_query}):\n" . $formatted_general_results . "\n";
        }
    }

    if (!empty($pinecone_results_this_pass)) {
        $pinecone_results = $pinecone_results_this_pass;
    }

    return $pinecone_results;
}

// --- resolve-qdrant-context.php ---
/**
 * Resolves context from Qdrant vector store.
 *
 * @param \WPAICG\Core\AIPKit_AI_Caller $ai_caller
 * @param \WPAICG\Vector\AIPKit_Vector_Store_Manager $vector_store_manager
 * @param string $user_message
 * @param array $bot_settings
 * @param string|null $frontend_active_qdrant_collection_name
 * @param string|null $frontend_active_qdrant_file_upload_context_id
 * @param int $vector_top_k
 * @param \wpdb $wpdb
 * @param string $data_source_table_name
 * @param array|null &$vector_search_scores_output Optional reference to capture scores for logging.
 * @return string Formatted Qdrant context results.
 */
function resolve_qdrant_context_logic(
    AIPKit_AI_Caller $ai_caller,
    AIPKit_Vector_Store_Manager $vector_store_manager,
    string $user_message,
    array $bot_settings,
    ?string $frontend_active_qdrant_collection_name, // Note: Qdrant doesn't use frontend-defined collection name for bot-level context, bot setting is primary
    ?string $frontend_active_qdrant_file_upload_context_id,
    int $vector_top_k,
    \wpdb $wpdb,
    string $data_source_table_name,
    ?array &$vector_search_scores_output = null
): string {
    $qdrant_results = "";
    $qdrant_collection_name_from_settings = $bot_settings['qdrant_collection_name'] ?? '';
    $qdrant_collection_names_multi = [];
    if (!empty($bot_settings['qdrant_collection_names']) && is_array($bot_settings['qdrant_collection_names'])) {
        $qdrant_collection_names_multi = array_values(array_unique(array_filter(array_map('strval', $bot_settings['qdrant_collection_names']))));
    } elseif (!empty($qdrant_collection_name_from_settings)) {
        $qdrant_collection_names_multi = [$qdrant_collection_name_from_settings];
    }
    $vector_embedding_provider = $bot_settings['vector_embedding_provider'] ?? '';
    $vector_embedding_model = $bot_settings['vector_embedding_model'] ?? ''; // This is the correctly defined variable
    $confidence_threshold_percent = (int)($bot_settings['vector_store_confidence_threshold'] ?? 20);
    $qdrant_score_threshold = round($confidence_threshold_percent / 100, 4); // Normalize 0-100 to 0-1 scale and round to avoid precision issues

    if (empty($qdrant_collection_names_multi) || empty($vector_embedding_provider) || empty($vector_embedding_model)) {
        // If $vector_embedding_model is empty, this condition is met, and it returns early.
        // This prevents "Undefined variable" if the model is essential and missing.
        return "";
    }

    if (!class_exists(AIPKit_Providers::class)) {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($providers_path)) {
            require_once $providers_path;
        } else {
            return "";
        }
    }
    $qdrant_api_config = AIPKit_Providers::get_provider_data('Qdrant');
    if (empty($qdrant_api_config['url']) || empty($qdrant_api_config['api_key'])) {
        return "";
    }

    $embedding_provider_normalized = normalize_embedding_provider_logic($vector_embedding_provider);

    $query_vector_values_or_error = resolve_embedding_vector_logic(
        $ai_caller,
        $user_message,
        $embedding_provider_normalized,
        $vector_embedding_model // Use the defined $vector_embedding_model
    );

    if (is_wp_error($query_vector_values_or_error)) {
        // Error is already logged by resolve_embedding_vector_logic if it fails.
        return "";
    }
    $query_vector_values = $query_vector_values_or_error;

    // We'll search across all selected collections and aggregate
    $collections_to_query = $qdrant_collection_names_multi;
    $qdrant_results_aggregate = "";

    // 1. Search with file-specific context ID if provided
    if (!empty($frontend_active_qdrant_file_upload_context_id)) {
        foreach ($collections_to_query as $collection_to_query) {
            $file_specific_filter = [
                'must' => [
                    ['key' => 'file_upload_context_id', 'match' => ['value' => $frontend_active_qdrant_file_upload_context_id]]
                ]
            ];
            $query_vector_for_file_context = ['vector' => $query_vector_values];
            $file_search_results = $vector_store_manager->query_vectors('Qdrant', $collection_to_query, $query_vector_for_file_context, $vector_top_k, $file_specific_filter, $qdrant_api_config);

            if (!is_wp_error($file_search_results) && !empty($file_search_results)) {
                $formatted_file_results = "";
                foreach ($file_search_results as $item) {
                    if (isset($item['score']) && (float)$item['score'] < $qdrant_score_threshold) {
                        continue;
                    }
                    $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
                    $content_snippet = $metadata['original_content'] ?? ($metadata['text_content'] ?? null);
                    if (!empty($content_snippet)) {
                        $formatted_file_results .= "- " . trim($content_snippet) . "\n";

                        if ($vector_search_scores_output !== null && isset($item['score'])) {
                            $vector_search_scores_output[] = build_vector_search_score_item_logic(
                                [
                                    'provider' => 'Qdrant',
                                    'collection_name' => $collection_to_query,
                                    'file_context_id' => $frontend_active_qdrant_file_upload_context_id,
                                    'result_id' => $item['id'] ?? null,
                                    'score' => $item['score'],
                                    'content_preview' => wp_trim_words(trim($content_snippet), 10, '...')
                                ],
                                $metadata
                            );
                        }
                    }
                }
                if (!empty($formatted_file_results)) {
                    $qdrant_results_aggregate .= "Context from Uploaded File (Collection {$collection_to_query}, File Context ID: {$frontend_active_qdrant_file_upload_context_id}):\n" . $formatted_file_results . "\n";
                }
            }
        }
    }

    // 2. Search general bot knowledge
    $general_knowledge_filter = [];
    if (!empty($frontend_active_qdrant_file_upload_context_id)) {
        $general_knowledge_filter = [
            'must_not' => [
                ['key' => 'file_upload_context_id', 'match' => ['value' => $frontend_active_qdrant_file_upload_context_id]]
            ]
        ];
    }

    foreach ($collections_to_query as $collection_to_query) {
        $query_vector_for_general_context = ['vector' => $query_vector_values];
        $general_search_results = $vector_store_manager->query_vectors('Qdrant', $collection_to_query, $query_vector_for_general_context, $vector_top_k, $general_knowledge_filter, $qdrant_api_config);

        if (!is_wp_error($general_search_results) && !empty($general_search_results)) {
            $formatted_general_results = "";
            foreach ($general_search_results as $item) {
                if (isset($item['score']) && (float)$item['score'] < $qdrant_score_threshold) {
                    continue;
                }
                $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
                $content_snippet = $metadata['original_content'] ?? ($metadata['text_content'] ?? null);
                if (empty($content_snippet) && isset($item['id'])) {
                    $cache_key = 'aipkit_vds_content_' . md5('qdrant_general_' . $collection_to_query . $item['id']);
                    $cache_group = 'aipkit_vector_source_content';
                    $log_entry = wp_cache_get($cache_key, $cache_group);

                    if (false === $log_entry) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reason: Direct query to a custom table. Caches will be invalidated.
                        $log_entry = $wpdb->get_row($wpdb->prepare("SELECT indexed_content FROM {$data_source_table_name} WHERE provider = 'Qdrant' AND vector_store_id = %s AND file_id = %s AND (batch_id IS NULL OR batch_id = '' OR batch_id NOT LIKE %s) ORDER BY timestamp DESC LIMIT 1", $collection_to_query, $item['id'], 'qdrant_chat_file_%'), ARRAY_A);
                        wp_cache_set($cache_key, $log_entry, $cache_group, HOUR_IN_SECONDS);
                    }
                    
                    if ($log_entry && !empty($log_entry['indexed_content'])) {
                        $content_snippet = $log_entry['indexed_content'];
                    }
                }
                if (!empty($content_snippet)) {
                    $formatted_general_results .= "- " . trim($content_snippet) . "\n";
                    
                    if ($vector_search_scores_output !== null && isset($item['score'])) {
                        $vector_search_scores_output[] = build_vector_search_score_item_logic(
                            [
                                'provider' => 'Qdrant',
                                'collection_name' => $collection_to_query,
                                'file_context_id' => null,
                                'result_id' => $item['id'] ?? null,
                                'score' => $item['score'],
                                'content_preview' => wp_trim_words(trim($content_snippet), 10, '...')
                            ],
                            $metadata
                        );
                    }
                }
            }
            if (!empty($formatted_general_results)) {
                $qdrant_results_aggregate .= "General Knowledge from Bot (Collection {$collection_to_query}):\n" . $formatted_general_results . "\n";
            }
        }
    }

    if (!empty($qdrant_results_aggregate)) {
        $qdrant_results = $qdrant_results_aggregate;
    }

    return $qdrant_results;
}

// --- resolve-chroma-context.php ---
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Chroma context resolution only reads plugin-owned vector log tables with prepared scalar values.

/**
 * Resolves context from Chroma collections.
 *
 * @param AIPKit_AI_Caller $ai_caller
 * @param AIPKit_Vector_Store_Manager $vector_store_manager
 * @param string $user_message
 * @param array<string,mixed> $bot_settings
 * @param string|null $frontend_active_chroma_collection_name
 * @param string|null $frontend_active_chroma_file_upload_context_id
 * @param int $vector_top_k
 * @param \wpdb $wpdb
 * @param string $data_source_table_name
 * @param array|null &$vector_search_scores_output Optional reference to capture scores for logging.
 * @return string Formatted Chroma context results.
 */
function resolve_chroma_context_logic(
    AIPKit_AI_Caller $ai_caller,
    AIPKit_Vector_Store_Manager $vector_store_manager,
    string $user_message,
    array $bot_settings,
    ?string $frontend_active_chroma_collection_name,
    ?string $frontend_active_chroma_file_upload_context_id,
    int $vector_top_k,
    \wpdb $wpdb,
    string $data_source_table_name,
    ?array &$vector_search_scores_output = null
): string {
    $chroma_collection_name_from_settings = $bot_settings['chroma_collection_name'] ?? '';
    $chroma_collection_names_multi = [];
    if (!empty($bot_settings['chroma_collection_names']) && is_array($bot_settings['chroma_collection_names'])) {
        $chroma_collection_names_multi = array_values(array_unique(array_filter(array_map('strval', $bot_settings['chroma_collection_names']))));
    } elseif (!empty($chroma_collection_name_from_settings)) {
        $chroma_collection_names_multi = [$chroma_collection_name_from_settings];
    }

    $active_chroma_collection_name = $frontend_active_chroma_collection_name !== null
        ? trim(sanitize_text_field($frontend_active_chroma_collection_name))
        : (isset($bot_settings['active_chroma_collection_name']) ? trim((string) $bot_settings['active_chroma_collection_name']) : '');
    if ($active_chroma_collection_name !== '' && !in_array($active_chroma_collection_name, $chroma_collection_names_multi, true)) {
        $chroma_collection_names_multi[] = $active_chroma_collection_name;
    }

    $vector_embedding_provider = $bot_settings['vector_embedding_provider'] ?? '';
    $vector_embedding_model = $bot_settings['vector_embedding_model'] ?? '';
    $confidence_threshold_percent = (int) ($bot_settings['vector_store_confidence_threshold'] ?? 20);
    $chroma_score_threshold = round($confidence_threshold_percent / 100, 4);

    if (empty($chroma_collection_names_multi) || empty($vector_embedding_provider) || empty($vector_embedding_model)) {
        return '';
    }

    if (!class_exists(AIPKit_Providers::class)) {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
        if (file_exists($providers_path)) {
            require_once $providers_path;
        } else {
            return '';
        }
    }

    $chroma_api_config = AIPKit_Providers::get_provider_data('Chroma');
    if (empty($chroma_api_config['url'])) {
        return '';
    }

    $embedding_provider_normalized = normalize_embedding_provider_logic($vector_embedding_provider);
    $query_vector_values_or_error = resolve_embedding_vector_logic(
        $ai_caller,
        $user_message,
        $embedding_provider_normalized,
        $vector_embedding_model
    );

    if (is_wp_error($query_vector_values_or_error)) {
        return '';
    }

    $query_vector_values = $query_vector_values_or_error;
    $collections_to_query = $chroma_collection_names_multi;
    $active_file_context_id = $frontend_active_chroma_file_upload_context_id !== null
        ? sanitize_text_field($frontend_active_chroma_file_upload_context_id)
        : '';
    foreach (['active_chroma_file_upload_context_id', 'chroma_file_upload_context_id'] as $context_key) {
        if ($active_file_context_id !== '') {
            break;
        }
        if (!empty($bot_settings[$context_key])) {
            $active_file_context_id = sanitize_text_field((string) $bot_settings[$context_key]);
            break;
        }
    }

    $chroma_results_aggregate = '';

    if ($active_file_context_id !== '') {
        foreach ($collections_to_query as $collection_to_query) {
            $file_specific_filter = [
                'where' => [
                    'file_upload_context_id' => $active_file_context_id,
                ],
            ];
            $file_search_results = $vector_store_manager->query_vectors(
                'Chroma',
                $collection_to_query,
                ['vector' => $query_vector_values],
                $vector_top_k,
                $file_specific_filter,
                $chroma_api_config
            );

            if (!is_wp_error($file_search_results) && !empty($file_search_results)) {
                $formatted_file_results = '';
                foreach ($file_search_results as $item) {
                    if (isset($item['score']) && (float) $item['score'] < $chroma_score_threshold) {
                        continue;
                    }
                    $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
                    $content_snippet = resolve_chroma_content_snippet_logic($item, $metadata, $collection_to_query, $wpdb, $data_source_table_name);
                    if ($content_snippet === '') {
                        continue;
                    }
                    $formatted_file_results .= '- ' . trim($content_snippet) . "\n";

                    if ($vector_search_scores_output !== null && isset($item['score'])) {
                        $vector_search_scores_output[] = build_vector_search_score_item_logic(
                            [
                                'provider' => 'Chroma',
                                'collection_name' => $collection_to_query,
                                'file_context_id' => $active_file_context_id,
                                'result_id' => $item['id'] ?? null,
                                'score' => $item['score'],
                                'content_preview' => wp_trim_words(trim($content_snippet), 10, '...'),
                            ],
                            $metadata
                        );
                    }
                }
                if ($formatted_file_results !== '') {
                    $chroma_results_aggregate .= "Context from Uploaded File (Collection {$collection_to_query}, File Context ID: {$active_file_context_id}):\n" . $formatted_file_results . "\n";
                }
            }
        }
    }

    foreach ($collections_to_query as $collection_to_query) {
        $general_search_results = $vector_store_manager->query_vectors(
            'Chroma',
            $collection_to_query,
            ['vector' => $query_vector_values],
            $vector_top_k,
            [],
            $chroma_api_config
        );

        if (is_wp_error($general_search_results) || empty($general_search_results)) {
            continue;
        }

        $formatted_general_results = '';
        foreach ($general_search_results as $item) {
            if (isset($item['score']) && (float) $item['score'] < $chroma_score_threshold) {
                continue;
            }

            $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : [];
            if ($active_file_context_id !== '' && ($metadata['file_upload_context_id'] ?? '') === $active_file_context_id) {
                continue;
            }

            $content_snippet = resolve_chroma_content_snippet_logic($item, $metadata, $collection_to_query, $wpdb, $data_source_table_name);
            if ($content_snippet === '') {
                continue;
            }

            $formatted_general_results .= '- ' . trim($content_snippet) . "\n";

            if ($vector_search_scores_output !== null && isset($item['score'])) {
                $vector_search_scores_output[] = build_vector_search_score_item_logic(
                    [
                        'provider' => 'Chroma',
                        'collection_name' => $collection_to_query,
                        'file_context_id' => null,
                        'result_id' => $item['id'] ?? null,
                        'score' => $item['score'],
                        'content_preview' => wp_trim_words(trim($content_snippet), 10, '...'),
                    ],
                    $metadata
                );
            }
        }

        if ($formatted_general_results !== '') {
            $chroma_results_aggregate .= "General Knowledge from Bot (Collection {$collection_to_query}):\n" . $formatted_general_results . "\n";
        }
    }

    return $chroma_results_aggregate;
}

/**
 * @param array<string,mixed> $item
 * @param array<string,mixed> $metadata
 */
function resolve_chroma_content_snippet_logic(array $item, array $metadata, string $collection_name, \wpdb $wpdb, string $data_source_table_name): string
{
    $content_snippet = $metadata['original_content'] ?? ($metadata['text_content'] ?? null);
    if (!empty($content_snippet)) {
        return trim((string) $content_snippet);
    }

    $result_id = isset($item['id']) ? (string) $item['id'] : '';
    if ($result_id === '') {
        return '';
    }

    $cache_key = 'aipkit_vds_content_' . md5('chroma_general_' . $collection_name . $result_id);
    $cache_group = 'aipkit_vector_source_content';
    $log_entry = wp_cache_get($cache_key, $cache_group);

    if (false === $log_entry) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT indexed_content FROM {$data_source_table_name} WHERE provider = 'Chroma' AND vector_store_id = %s AND file_id = %s ORDER BY timestamp DESC LIMIT 1",
                $collection_name,
                $result_id
            ),
            ARRAY_A
        );
        wp_cache_set($cache_key, $log_entry, $cache_group, HOUR_IN_SECONDS);
    }

    if ($log_entry && !empty($log_entry['indexed_content'])) {
        return trim((string) $log_entry['indexed_content']);
    }

    return '';
}
