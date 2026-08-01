<?php


namespace WPAICG\Dashboard\Ajax;

use WPAICG\AIPKit_Providers;
use WPAICG\Core\AIPKit_Models_API;
use WPAICG\Core\Providers\ProviderStrategyFactory;
use WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory;
use WPAICG\Images\AIPKit_Image_Provider_Strategy_Factory;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles AJAX requests for syncing AI models from providers.
 * Also handles syncing TTS voices AND models, and Vector Store indexes/collections.
 */
class ModelsAjaxHandler extends BaseDashboardAjaxHandler
{
    private $vector_store_manager;
    private $vector_store_registry;

    public function __construct()
    {
        $this->vector_store_manager = $this->create_vector_store_manager();
        $this->vector_store_registry = $this->create_vector_store_registry();

        // General dependencies for logic within this handler
        if (!class_exists(\WPAICG\AIPKit_Providers::class)) {
            $providers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php';
            if (file_exists($providers_path)) {
                require_once $providers_path;
            }
        }
        if (!class_exists(\WPAICG\Core\AIPKit_Models_API::class)) {
            $models_api_path = WPAICG_PLUGIN_DIR . 'classes/core/models_api.php';
            if (file_exists($models_api_path)) {
                require_once $models_api_path;
            }
        }
        if (!class_exists(\WPAICG\Speech\AIPKit_TTS_Provider_Strategy_Factory::class)) {
            $tts_factory_path = WPAICG_PLUGIN_DIR . 'classes/speech/class-aipkit-tts-provider-strategy-factory.php';
            if (file_exists($tts_factory_path)) {
                require_once $tts_factory_path;
            }
        }
    }

    /**
     * AJAX callback to sync models or voices from the selected provider.
     */
    public function ajax_sync_models()
    {
        $permission_check = $this->check_module_access_permissions('settings');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions().
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        $default_valid_providers = ['OpenAI', 'OpenRouter', 'Google', 'Azure', 'Claude', 'DeepSeek', 'xAI', 'xAIImage', 'ElevenLabs', 'ElevenLabsModels', 'OpenAIVectorStores', 'PineconeIndexes', 'QdrantCollections', 'ChromaCollections', 'Replicate'];
        $valid_providers = apply_filters('aipkit_sync_provider_allowlist', $default_valid_providers);
        if (!is_array($valid_providers) || empty($valid_providers)) {
            $valid_providers = $default_valid_providers;
        }
        $valid_providers = array_values(array_unique(array_filter(array_map(
            static function ($provider_name) {
                return sanitize_text_field((string) $provider_name);
            },
            $valid_providers
        ))));
        if (!in_array($provider, $valid_providers, true)) {
            wp_send_json_error(['message' => __('Invalid provider selection.', 'gpt3-ai-content-generator')]);
            return;
        }

        $provider_data_key = $provider;
        if ($provider === 'ElevenLabsModels') {
            $provider_data_key = 'ElevenLabs';
        } elseif ($provider === 'OpenAIVectorStores') {
            $provider_data_key = 'OpenAI';
        } elseif ($provider === 'xAIImage') {
            $provider_data_key = 'xAI';
        } elseif ($provider === 'PineconeIndexes') {
            $provider_data_key = 'Pinecone';
        } elseif ($provider === 'QdrantCollections') {
            $provider_data_key = 'Qdrant';
        } elseif ($provider === 'ChromaCollections') {
            $provider_data_key = 'Chroma';
        }

        $provData = AIPKit_Providers::get_provider_data($provider_data_key);

        // Remap Azure 'endpoint' to 'azure_endpoint' for consistency with AI_Caller and strategy expectations.
        $api_params = [
            'api_key'                 => $provData['api_key'] ?? '',
            'base_url'                => $provData['base_url'] ?? '',
            'url'                     => $provData['url'] ?? '', // For Qdrant/Chroma
            'tenant'                  => $provData['tenant'] ?? 'default_tenant',
            'database'                => $provData['database'] ?? 'default_database',
            'api_version'             => $provData['api_version'] ?? '',
            'api_version_authoring'   => $provData['api_version_authoring'] ?? '2023-03-15-preview',
            'api_version_inference'   => $provData['api_version_inference'] ?? '2024-02-01',
            'azure_endpoint'          => ($provider === 'Azure' || $provider_data_key === 'Azure') ? ($provData['endpoint'] ?? '') : '',
        ];


        if (empty($api_params['api_key']) && in_array($provider, ['OpenAI', 'OpenRouter', 'Azure', 'Claude', 'DeepSeek', 'xAI', 'xAIImage', 'ElevenLabs', 'ElevenLabsModels', 'PineconeIndexes', 'QdrantCollections', 'Replicate'], true)) {
            /* translators: %s: The provider name that was attempted to be used for model sync. */
            wp_send_json_error(['message' => sprintf(__('%s API key is required.', 'gpt3-ai-content-generator'), $provider_data_key)]);
            return;
        }
        if ($provider === 'QdrantCollections' && empty($api_params['url'])) {
            wp_send_json_error(['message' => __('Qdrant URL is required to sync collections.', 'gpt3-ai-content-generator')]);
            return;
        }
        if ($provider === 'ChromaCollections' && empty($api_params['url'])) {
            wp_send_json_error(['message' => __('Chroma URL is required to sync collections.', 'gpt3-ai-content-generator')]);
            return;
        }

        $result = null;

        switch ($provider) {
            case 'ElevenLabs':
                $strategy = AIPKit_TTS_Provider_Strategy_Factory::get_strategy('ElevenLabs');
                $result = is_wp_error($strategy) ? $strategy : $strategy->get_voices($api_params);
                break;
            case 'ElevenLabsModels':
                $strategy = AIPKit_TTS_Provider_Strategy_Factory::get_strategy('ElevenLabs');
                $result = (is_wp_error($strategy) || !method_exists($strategy, 'get_models'))
                    ? new WP_Error('tts_model_sync_not_supported', 'TTS Model sync not supported for ElevenLabs.')
                    : $strategy->get_models($api_params);
                break;
            case 'OpenAIVectorStores':
                if (!$this->vector_store_manager) {
                    $result = new WP_Error('vsm_missing', 'Vector Store Manager not available.');
                    break;
                }
                // Full sync pass for Dashboard autosync (no paging needed here)
                $result = $this->vector_store_manager->list_all_indexes('OpenAI', $api_params, 100, 'desc', null, null);
                break;
            case 'PineconeIndexes':
                if (!$this->vector_store_manager) {
                    $result = new WP_Error('vsm_missing', 'Vector Store Manager not available.');
                    break;
                }
                $result = $this->vector_store_manager->list_all_indexes('Pinecone', $api_params);
                break;
            case 'QdrantCollections':
                if (!$this->vector_store_manager) {
                    $result = new WP_Error('vsm_missing', 'Vector Store Manager not available.');
                    break;
                }
                $result = $this->vector_store_manager->list_all_indexes('Qdrant', $api_params);
                break;
            case 'ChromaCollections':
                if (!$this->vector_store_manager) {
                    $result = new WP_Error('vsm_missing', 'Vector Store Manager not available.');
                    break;
                }
                $result = $this->vector_store_manager->list_all_indexes('Chroma', $api_params);
                break;
            case 'Replicate':
                if (!class_exists(\WPAICG\Images\AIPKit_Image_Provider_Strategy_Factory::class)) {
                    $factory_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-provider-strategy-factory.php';
                    if (file_exists($factory_path)) {
                        require_once $factory_path;
                    }
                }
                $strategy = \WPAICG\Images\AIPKit_Image_Provider_Strategy_Factory::get_strategy('Replicate');
                $result = is_wp_error($strategy) ? $strategy : $strategy->get_models($api_params);
                break;
            case 'xAIImage':
                if (!class_exists(AIPKit_Image_Provider_Strategy_Factory::class)) {
                    $factory_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-provider-strategy-factory.php';
                    if (file_exists($factory_path)) {
                        require_once $factory_path;
                    }
                }
                $strategy = AIPKit_Image_Provider_Strategy_Factory::get_strategy('xAI');
                $result = (is_wp_error($strategy) || !method_exists($strategy, 'get_models'))
                    ? new WP_Error('xai_image_model_sync_not_supported', __('xAI image model sync is not available.', 'gpt3-ai-content-generator'))
                    : $strategy->get_models($api_params);
                break;
            default: // Handles OpenAI, OpenRouter, Google, Azure, DeepSeek, xAI
                $result = AIPKit_Models_API::get_models($provider, $api_params);
                break;
        }

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status_code = isset($error_data['status']) ? (int)$error_data['status'] : 500;
            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
            return;
        }

        $option_map = [
            'OpenAI' => 'aipkit_openai_model_list', 'OpenRouter' => 'aipkit_openrouter_model_list',
            'Google' => 'aipkit_google_model_list', 'Azure' => 'aipkit_azure_deployment_list', 'Claude' => 'aipkit_claude_model_list', 'AzureImage' => 'aipkit_azure_image_model_list', 'DeepSeek' => 'aipkit_deepseek_model_list', 'xAI' => 'aipkit_xai_model_list', 'ElevenLabs' => 'aipkit_elevenlabs_voice_list',
            'ElevenLabsModels' => 'aipkit_elevenlabs_model_list',
            'xAIImage' => 'aipkit_xai_image_model_list',
            'PineconeIndexes' => 'aipkit_pinecone_index_list',
            'QdrantCollections' => 'aipkit_qdrant_collection_list',
            'ChromaCollections' => 'aipkit_chroma_collection_list',
            'Replicate' => 'aipkit_replicate_model_list',
            'AzureEmbedding' => 'aipkit_azure_embedding_model_list',
            'Ollama' => 'aipkit_ollama_model_list',
        ];

        $option_name = $option_map[$provider] ?? null;
        $response_models = $result; // Default to raw result
        $extra_response_payload = [];
        $value_to_save = $result;

        if ($provider === 'OpenAIVectorStores' && $this->vector_store_registry) {
            $stores_payload = $result;
            if (is_array($stores_payload) && isset($stores_payload['data']) && is_array($stores_payload['data'])) {
                $stores_payload = $stores_payload['data'];
            }

            $active_stores = [];
            if (is_array($stores_payload)) {
                foreach ($stores_payload as $store_item) {
                    if (isset($store_item['status']) && $store_item['status'] === 'expired') {
                        continue;
                    }
                    $active_stores[] = $store_item;
                }
            }

            $this->vector_store_registry->update_registered_stores_for_provider('OpenAI', $active_stores);
            $response_models = $active_stores;
            $extra_response_payload['stores'] = $active_stores;
        }

        if ($option_name) {
            $value_to_save = $result;
            // OpenAI and Google have multiple model types, split them here
            if ($provider === 'OpenAI') {
                $chat_models = [];
                $tts_models = [];
                $stt_models = [];
                $embedding_models = [];
                foreach ($result as $model) {
                    $id_lower = strtolower($model['id']);
                    if (strpos($id_lower, 'tts-') === 0) {
                        $tts_models[] = $model;
                    } elseif (strpos($id_lower, 'whisper') !== false) {
                        $stt_models[] = $model;
                    } elseif (strpos($id_lower, 'embedding') !== false) {
                        $embedding_models[] = $model;
                    } else {
                        $chat_models[] = $model;
                    }
                }
                $value_to_save = AIPKit_Models_API::group_openai_models($chat_models);
                $response_models = $value_to_save; // Set response to the grouped models
                update_option('aipkit_openai_tts_model_list', $tts_models, 'no');
                update_option('aipkit_openai_stt_model_list', $stt_models, 'no');
                update_option('aipkit_openai_embedding_model_list', $embedding_models, 'no');
            } elseif ($provider === 'Google') {
                $chat_models = [];
                $image_models = [];
                $video_models = [];
                $embedding_models = [];
                foreach ($result as $model) {
                    // Prefer capability-based detection using supportedGenerationMethods from Google API
                    $methods = [];
                    if (isset($model['supportedGenerationMethods']) && is_array($model['supportedGenerationMethods'])) {
                        // Normalize to lowercase for safe comparison
                        $methods = array_map('strtolower', $model['supportedGenerationMethods']);
                    }

                    $id = $model['id'] ?? '';
                    $id_lower = strtolower($id);
                    $is_embedding = in_array('embedcontent', $methods, true);
                    $is_image = in_array('predict', $methods, true)
                        // Include Gemini native image models that use generateContent.
                        || (
                            strpos($id_lower, 'gemini') !== false
                            && (
                                strpos($id_lower, 'image-generation') !== false
                                || strpos($id_lower, 'flash-image') !== false
                                || strpos($id_lower, 'pro-image') !== false
                            )
                        );
                    $is_video = in_array('predictlongrunning', $methods, true)
                        // Heuristic fallback: Veo or other video-prefixed names
                        || (strpos($id_lower, 'veo') !== false);

                    if ($is_embedding) {
                        $embedding_models[] = $model;
                    } elseif ($is_video) {
                        $video_models[] = $model;
                    } elseif ($is_image) {
                        $image_models[] = $model;
                    } else {
                        $chat_models[] = $model;
                    }
                }
                $value_to_save = $chat_models;
                $response_models = $value_to_save; // Set response to just the chat models
                update_option('aipkit_google_embedding_model_list', $embedding_models, 'no');
                update_option('aipkit_google_image_model_list', $image_models, 'no');
                update_option('aipkit_google_video_model_list', $video_models, 'no');
            } elseif ($provider === 'OpenRouter') {
                $existing_embedding_models = get_option('aipkit_openrouter_embedding_model_list', []);
                $openrouter_embedding_models = is_array($existing_embedding_models) ? $existing_embedding_models : [];
                $openrouter_image_models = [];

                $openrouter_strategy = ProviderStrategyFactory::get_strategy('OpenRouter');
                if (!is_wp_error($openrouter_strategy) && method_exists($openrouter_strategy, 'get_embedding_models')) {
                    $embedding_models_result = $openrouter_strategy->get_embedding_models($api_params);
                    if (!is_wp_error($embedding_models_result) && is_array($embedding_models_result)) {
                        $openrouter_embedding_models = $embedding_models_result;
                        update_option('aipkit_openrouter_embedding_model_list', $openrouter_embedding_models, 'no');
                    }
                }
                if (!is_wp_error($openrouter_strategy) && method_exists($openrouter_strategy, 'get_image_models')) {
                    $image_models_result = $openrouter_strategy->get_image_models($api_params);
                    if (!is_wp_error($image_models_result) && is_array($image_models_result)) {
                        $openrouter_image_models = $image_models_result;
                        $value_to_save = AIPKit_Providers::merge_model_rows($value_to_save, $openrouter_image_models);
                    }
                }
                delete_option('aipkit_openrouter_image_model_list');
                $extra_response_payload['embedding_models'] = $openrouter_embedding_models;
                $extra_response_payload['image_models'] = $openrouter_image_models;
            } elseif ($provider === 'xAI') {
                if (!class_exists(AIPKit_Image_Provider_Strategy_Factory::class)) {
                    $factory_path = WPAICG_PLUGIN_DIR . 'classes/images/class-aipkit-image-provider-strategy-factory.php';
                    if (file_exists($factory_path)) {
                        require_once $factory_path;
                    }
                }
                $xai_image_models = get_option('aipkit_xai_image_model_list', []);
                $xai_image_models = is_array($xai_image_models) ? $xai_image_models : [];
                if (class_exists(AIPKit_Image_Provider_Strategy_Factory::class)) {
                    $xai_image_strategy = AIPKit_Image_Provider_Strategy_Factory::get_strategy('xAI');
                    if (!is_wp_error($xai_image_strategy) && method_exists($xai_image_strategy, 'get_models')) {
                        $xai_image_models_result = $xai_image_strategy->get_models($api_params);
                        if (!is_wp_error($xai_image_models_result) && is_array($xai_image_models_result)) {
                            $xai_image_models = $xai_image_models_result;
                            update_option('aipkit_xai_image_model_list', $xai_image_models, 'no');
                        }
                    }
                }
                $extra_response_payload['image_models'] = $xai_image_models;
            } elseif ($provider === 'Ollama') {
                $chat_models = is_array($result) ? $result : [];
                $embedding_models = [];
                $vision_models = [];
                $all_models = is_array($result) ? $result : [];
                $option_updates = [];

                /**
                 * Allow Pro/Ollama layer to classify synced Ollama models using
                 * provider-specific capabilities (e.g. /api/show metadata).
                 *
                 * Expected return shape:
                 * [
                 *   'chat_models' => array,
                 *   'embedding_models' => array,
                 *   'vision_models' => array,
                 *   'model_details_failures' => array,
                 *   'cache_stats' => array,
                 * ]
                 */
                $classification_result = apply_filters(
                    'aipkit_ollama_sync_models_classification',
                    null,
                    $result,
                    $api_params
                );

                if (is_array($classification_result)) {
                    if (isset($classification_result['all_models']) && is_array($classification_result['all_models'])) {
                        $all_models = $classification_result['all_models'];
                    }
                    if (isset($classification_result['chat_models']) && is_array($classification_result['chat_models'])) {
                        $chat_models = $classification_result['chat_models'];
                    }
                    if (isset($classification_result['embedding_models']) && is_array($classification_result['embedding_models'])) {
                        $embedding_models = $classification_result['embedding_models'];
                    }
                    if (isset($classification_result['vision_models']) && is_array($classification_result['vision_models'])) {
                        $vision_models = $classification_result['vision_models'];
                    }
                    if (!empty($classification_result['model_details_failures']) && is_array($classification_result['model_details_failures'])) {
                        $extra_response_payload['model_details_failures'] = $classification_result['model_details_failures'];
                    }
                    if (!empty($classification_result['cache_stats']) && is_array($classification_result['cache_stats'])) {
                        $extra_response_payload['capability_cache'] = $classification_result['cache_stats'];
                    }
                    if (isset($classification_result['option_updates']) && is_array($classification_result['option_updates'])) {
                        $option_updates = $classification_result['option_updates'];
                    }
                } else {
                    // Backward-compatible fallback when capability classifier is unavailable.
                    $chat_models = [];
                    $embedding_models = [];
                    $vision_models = [];
                    foreach ($all_models as $model) {
                        if (!is_array($model)) {
                            continue;
                        }
                        $id_lower = strtolower((string) ($model['id'] ?? ''));
                        $name_lower = strtolower((string) ($model['name'] ?? ''));
                        $haystack = trim($id_lower . ' ' . $name_lower);
                        $is_embedding = strpos($haystack, 'embed') !== false || strpos($haystack, 'embedding') !== false;
                        $is_vision = strpos($haystack, 'vision') !== false
                            || strpos($haystack, '-vl') !== false
                            || strpos($haystack, '_vl') !== false
                            || strpos($haystack, 'llava') !== false
                            || strpos($haystack, 'moondream') !== false
                            || strpos($haystack, 'gemma3') !== false;

                        if ($is_embedding) {
                            $embedding_models[] = $model;
                            continue;
                        }
                        if ($is_vision) {
                            $vision_models[] = $model;
                        }
                        $chat_models[] = $model;
                    }
                }

                if (empty($option_updates)) {
                    $option_updates = [
                        'aipkit_ollama_embedding_model_list' => $embedding_models,
                        'aipkit_ollama_vision_model_list' => $vision_models,
                        'aipkit_ollama_model_capability_list' => $all_models,
                    ];
                }

                $value_to_save = $chat_models;
                $response_models = $value_to_save; // Set response to just the chat models

                foreach ($option_updates as $option_key => $option_value) {
                    $option_key = sanitize_key((string) $option_key);
                    if (!is_array($option_value) || $option_key === '' || strpos($option_key, 'aipkit_') !== 0) {
                        continue;
                    }
                    update_option($option_key, $option_value, 'no');
                }

                $extra_response_payload['embedding_models'] = $embedding_models;
                if (!empty($vision_models)) {
                    $extra_response_payload['vision_models'] = $vision_models;
                }
            } elseif ($provider === 'Azure') {
                $chat_deployments = [];
                $image_deployments = [];
                $embedding_deployments = [];
                if (is_array($result)) {
                    foreach ($result as $deployment) {
                        $deployment_haystack = strtolower(trim(
                            (string) ($deployment['name'] ?? '') . ' ' .
                            (string) ($deployment['model'] ?? '') . ' ' .
                            (string) ($deployment['id'] ?? '')
                        ));
                        $is_embedding_deployment = strpos($deployment_haystack, 'embedding') !== false
                            || strpos($deployment_haystack, 'embed') !== false;
                        $is_image_deployment = strpos($deployment_haystack, 'gpt-image') !== false
                            || (
                                strpos($deployment_haystack, 'image') !== false
                                && strpos($deployment_haystack, 'embedding') === false
                                && strpos($deployment_haystack, 'embed') === false
                                && strpos($deployment_haystack, 'vision') === false
                            );

                        if ($is_image_deployment) {
                            $image_deployments[] = $deployment;
                        } elseif ($is_embedding_deployment) {
                            $embedding_deployments[] = $deployment;
                        } else {
                            $chat_deployments[] = $deployment;
                        }
                    }
                }
                update_option('aipkit_azure_image_model_list', $image_deployments, 'no');
                update_option('aipkit_azure_embedding_model_list', $embedding_deployments, 'no');
                $value_to_save = $chat_deployments;
                
                // Return grouped models for dashboard display
                $grouped_models = [];
                if (!empty($chat_deployments)) {
                    $grouped_models['Chat Models'] = $chat_deployments;
                }
                if (!empty($embedding_deployments)) {
                    $grouped_models['Embedding Models'] = $embedding_deployments;
                }
                if (!empty($image_deployments)) {
                    $grouped_models['Image Models'] = $image_deployments;
                }
                $response_models = $grouped_models;
            } elseif ($provider === 'PineconeIndexes' && $this->vector_store_registry) {
                // Enrich with describe results to capture total_vector_count
                $pinecone_config = [
                    'api_key' => $api_params['api_key'] ?? ''
                ];
                $enriched = [];
                if ($this->vector_store_manager && is_array($value_to_save)) {
                    foreach ($value_to_save as $idx) {
                        $name = $idx['name'] ?? $idx['id'] ?? null;
                        if (!$name) continue;
                        $details = $this->vector_store_manager->describe_single_index('Pinecone', $name, $pinecone_config);
                        $enriched[] = is_wp_error($details) ? $idx : array_merge($idx, $details);
                    }
                }
                if (!empty($enriched)) {
                    $value_to_save = $enriched;
                }
                $this->vector_store_registry->update_registered_stores_for_provider('Pinecone', $value_to_save);
            } elseif ($provider === 'QdrantCollections' && $this->vector_store_registry) {
                // Enrich with describe results to capture vectors_count
                $qdrant_config = [
                    'url' => $api_params['url'] ?? '',
                    'api_key' => $api_params['api_key'] ?? ''
                ];
                $enriched = [];
                if ($this->vector_store_manager && is_array($value_to_save)) {
                    foreach ($value_to_save as $col) {
                        $name = $col['name'] ?? $col['id'] ?? null;
                        if (!$name) continue;
                        $details = $this->vector_store_manager->describe_single_index('Qdrant', $name, $qdrant_config);
                        $enriched[] = is_wp_error($details) ? $col : array_merge($col, $details);
                    }
                }
                if (!empty($enriched)) {
                    $value_to_save = $enriched;
                }
                $this->vector_store_registry->update_registered_stores_for_provider('Qdrant', $value_to_save);
            } elseif ($provider === 'ChromaCollections' && $this->vector_store_registry) {
                $chroma_config = [
                    'url' => $api_params['url'] ?? '',
                    'api_key' => $api_params['api_key'] ?? '',
                    'tenant' => $api_params['tenant'] ?? 'default_tenant',
                    'database' => $api_params['database'] ?? 'default_database',
                ];
                $enriched = [];
                if ($this->vector_store_manager && is_array($value_to_save)) {
                    foreach ($value_to_save as $collection) {
                        $name = $collection['name'] ?? $collection['id'] ?? null;
                        if (!$name) continue;
                        $details = $this->vector_store_manager->describe_single_index('Chroma', $name, $chroma_config);
                        $enriched[] = is_wp_error($details) ? $collection : array_merge($collection, $details);
                    }
                }
                if (!empty($enriched)) {
                    $value_to_save = $enriched;
                }
                $this->vector_store_registry->update_registered_stores_for_provider('Chroma', $value_to_save);
            }
            update_option($option_name, $value_to_save, 'no');
        }

        AIPKit_Providers::clear_model_caches();

        // Keep one trustworthy freshness timestamp per synced provider. This is
        // written only after the provider request and model-list persistence
        // both succeed, so UI surfaces never report a failed sync as current.
        $synced_at = time();
        $model_sync_timestamps = get_option('aipkit_model_sync_timestamps', []);
        if (!is_array($model_sync_timestamps)) {
            $model_sync_timestamps = [];
        }
        $model_sync_timestamps[$provider] = $synced_at;
        update_option('aipkit_model_sync_timestamps', $model_sync_timestamps, 'no');

        $recommended_models = $this->get_recommended_models_for_response(
            $provider,
            $response_models,
            $value_to_save
        );

        $success_message = sprintf(
            /* translators: %s: the provider name that was synced. */
            __('%s synced successfully.', 'gpt3-ai-content-generator'),
            $provider_data_key
        );
        wp_send_json_success(
            array_merge(
                [
                    'message' => $success_message,
                    'models'  => $response_models,
                    'recommended_models' => $recommended_models,
                    'synced_at' => $synced_at,
                ],
                $extra_response_payload
            )
        );
    }

    /**
     * Builds a recommended-model payload for sync responses so UI grouping
     * can be rendered immediately without a page refresh.
     *
     * @param string     $provider       Provider key.
     * @param mixed      $response_models Models returned to UI.
     * @param mixed      $stored_models   Models persisted to options.
     * @return array<int, array{id:string,name:string}>
     */
    private function get_recommended_models_for_response(string $provider, $response_models, $stored_models): array
    {
        $default_supported = ['OpenAI', 'OpenRouter', 'Google', 'Azure', 'Claude', 'DeepSeek', 'xAI', 'xAIImage'];
        $supported = apply_filters('aipkit_recommended_model_supported_providers', $default_supported);
        $supported = is_array($supported) ? $supported : $default_supported;
        if (!in_array($provider, $supported, true)) {
            return [];
        }

        $recommended = AIPKit_Providers::get_recommended_models($provider);
        if (!empty($recommended)) {
            return is_array($recommended) ? $recommended : [];
        }

        // Keep fallback behavior aligned with model-list builder for providers
        // that derive recommended entries from existing synced rows.
        $default_fallback_supported = ['Azure', 'DeepSeek', 'xAI', 'xAIImage'];
        $fallback_supported = apply_filters('aipkit_recommended_model_fallback_providers', $default_fallback_supported);
        $fallback_supported = is_array($fallback_supported) ? $fallback_supported : $default_fallback_supported;
        if (!in_array($provider, $fallback_supported, true)) {
            return [];
        }

        $source_rows = $provider === 'Azure' ? $stored_models : $response_models;
        if (!is_array($source_rows)) {
            return [];
        }

        $fallback = [];
        foreach ($source_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $fallback[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? $id),
            ];
        }

        return array_slice($fallback, 0, 3);
    }
}
