<?php

namespace WPAICG\AutoGPT\Ajax\Actions\SaveTask;

use WPAICG\AutoGPT\Ajax\AIPKit_Save_Automated_Task_Action;
use WPAICG\AutoGPT\Helpers;
use WP_Error;
use WPAICG\Core\AIPKit_OpenAI_Reasoning;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Template_Manager;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Image_Provider_Options;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config;
use WPAICG\AIPKit_Providers;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Scheduler;
use WPAICG\AutoGPT\Cron\AIPKit_Automated_Task_Content_Queuer;
use WPAICG\AutoGPT\Cron\EventProcessor\Trigger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- validate-task-request.php ---
require_once WPAICG_PLUGIN_DIR . 'classes/autogpt/helpers/task-type-access.php';

/**
 * Checks whether manual content-writing input contains at least one real topic.
 *
 * Manual rows can include pipe-separated metadata after the topic:
 * topic | keywords | category | author | post type | schedule.
 * Only the first column should count as the required topic input.
 *
 * @param string $value Raw manual topic input.
 * @return bool True when at least one line has a non-empty topic.
 */
function content_writing_has_manual_topic(string $value): bool
{
    $lines = preg_split('/\r?\n/', trim($value));
    if (!is_array($lines)) {
        return false;
    }

    foreach ($lines as $line) {
        $parts = array_map('trim', explode('|', (string) $line));
        if (($parts[0] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Checks whether the settings required by a provider are present.
 *
 * This is intentionally a local setup check, not a remote credential test.
 *
 * @param string $provider_key Normalized provider key.
 * @return bool|null True/false for known providers, null for unknown providers.
 */
function provider_setup_is_present(string $provider_key): ?bool
{
    if (!class_exists(AIPKit_Providers::class)) {
        return null;
    }

    $provider_key = strtolower(sanitize_key($provider_key));
    $provider_names = [
        'openai' => 'OpenAI',
        'google' => 'Google',
        'claude' => 'Claude',
        'openrouter' => 'OpenRouter',
        'azure' => 'Azure',
        'ollama' => 'Ollama',
        'deepseek' => 'DeepSeek',
        'xai' => 'xAI',
        'replicate' => 'Replicate',
        'pexels' => 'Pexels',
        'pixabay' => 'Pixabay',
        'pinecone' => 'Pinecone',
        'qdrant' => 'Qdrant',
        'chroma' => 'Chroma',
    ];
    if (!isset($provider_names[$provider_key])) {
        return null;
    }

    $data = AIPKit_Providers::get_provider_data($provider_names[$provider_key]);
    if ($provider_key === 'azure') {
        return !empty($data['api_key']) && !empty($data['endpoint']);
    }
    if ($provider_key === 'ollama') {
        return !empty($data['base_url']);
    }
    if ($provider_key === 'qdrant') {
        return !empty($data['api_key']) && !empty($data['url']);
    }
    if ($provider_key === 'chroma') {
        return !empty($data['url']);
    }

    return !empty($data['api_key']);
}

/**
 * Returns a save-time setup error for a provider, if one is known to be missing.
 *
 * @param string $provider_key Provider key from the request.
 * @return WP_Error|null Error when setup is missing; otherwise null.
 */
function provider_setup_error(string $provider_key): ?WP_Error
{
    $provider_key = strtolower(sanitize_key($provider_key));
    if ($provider_key === '' || provider_setup_is_present($provider_key) !== false) {
        return null;
    }

    $labels = [
        'openai' => 'OpenAI', 'google' => 'Google', 'claude' => 'Anthropic',
        'openrouter' => 'OpenRouter', 'azure' => 'Azure', 'ollama' => 'Ollama',
        'deepseek' => 'DeepSeek', 'xai' => 'xAI', 'replicate' => 'Replicate',
        'pexels' => 'Pexels', 'pixabay' => 'Pixabay', 'pinecone' => 'Pinecone',
        'qdrant' => 'Qdrant', 'chroma' => 'Chroma',
    ];
    $label = $labels[$provider_key] ?? ucfirst($provider_key);

    return new WP_Error(
        'provider_setup_required',
        sprintf(
            /* translators: %s: provider name. */
            __('Set up %s before using it in this automation.', 'gpt3-ai-content-generator'),
            $label
        ),
        ['status' => 400, 'provider' => $provider_key]
    );
}

/**
 * Validates provider setup for the task configuration being saved.
 *
 * @param string $task_type Task type.
 * @param array  $post_data Submitted task data.
 * @return WP_Error|null Error when a selected provider needs setup.
 */
function validate_task_provider_setup(string $task_type, array $post_data): ?WP_Error
{
    $providers = [];

    if (strpos($task_type, 'content_writing') === 0) {
        $providers[] = $post_data['ai_provider'] ?? '';
        if (($post_data['generate_images_enabled'] ?? '0') === '1' || ($post_data['generate_featured_image'] ?? '0') === '1') {
            $providers[] = $post_data['image_provider'] ?? '';
        }
        if (($post_data['enable_vector_store'] ?? '0') === '1') {
            $vector_provider = (string) ($post_data['vector_store_provider'] ?? 'openai');
            $providers[] = $vector_provider;
            if (in_array(strtolower($vector_provider), ['pinecone', 'qdrant', 'chroma'], true)) {
                $providers[] = $post_data['vector_embedding_provider'] ?? '';
            }
        }
    } elseif ($task_type === 'enhance_existing_content') {
        $providers[] = $post_data['ai_provider'] ?? '';
        if (($post_data['enable_vector_store'] ?? '0') === '1') {
            $vector_provider = (string) ($post_data['vector_store_provider'] ?? 'openai');
            $providers[] = $vector_provider;
            if (in_array(strtolower($vector_provider), ['pinecone', 'qdrant', 'chroma'], true)) {
                $providers[] = $post_data['vector_embedding_provider'] ?? '';
            }
        }
    } elseif ($task_type === 'community_reply_comments') {
        $providers[] = $post_data['cc_ai_provider'] ?? '';
    } elseif ($task_type === 'content_indexing') {
        $vector_provider = (string) ($post_data['target_store_provider'] ?? 'openai');
        $providers[] = $vector_provider;
        if (in_array(strtolower($vector_provider), ['pinecone', 'qdrant', 'chroma'], true)) {
            $providers[] = $post_data['embedding_provider'] ?? '';
        }
    }

    foreach (array_unique(array_filter(array_map('strval', $providers))) as $provider) {
        $error = provider_setup_error($provider);
        if (is_wp_error($error)) {
            return $error;
        }
    }

    return null;
}

/**
* Validates the AJAX request for saving an automated task.
*
* @param AIPKit_Save_Automated_Task_Action $handler The handler instance.
* @param array $post_data The raw POST data.
* @return array|WP_Error An array of validated parameters or a WP_Error on failure.
*/
function validate_task_request_logic(AIPKit_Save_Automated_Task_Action $handler, array $post_data)
{
    // Permission and nonce checks are now handled by the caller.
    // This function now only validates the presence and format of required parameters.

    $task_id = isset($post_data['task_id']) && !empty($post_data['task_id']) ? absint($post_data['task_id']) : 0;
    $task_name = isset($post_data['task_name']) ? sanitize_text_field($post_data['task_name']) : '';
    $task_type = isset($post_data['task_type']) ? sanitize_key($post_data['task_type']) : '';

    if (empty($task_name)) {
        return new WP_Error('missing_task_name', __('Task name is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (empty($task_type)) {
        return new WP_Error('missing_task_type', __('Task type is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (Helpers\task_type_requires_pro_plan($task_type) && !Helpers\is_pro_plan_active()) {
        return new WP_Error('task_type_requires_pro_plan', __('This is a Pro feature.', 'gpt3-ai-content-generator'), ['status' => 403]);
    }
    $provider_setup_error = validate_task_provider_setup($task_type, $post_data);
    if (is_wp_error($provider_setup_error)) {
        return $provider_setup_error;
    }

    return [
        'task_id' => $task_id,
        'task_name' => $task_name,
        'task_type' => $task_type,
    ];
}

// --- build-task-config-indexing.php ---
/**
* Builds and validates the configuration for a 'content_indexing' task.
*
* @param array $post_data The raw POST data.
* @return array|WP_Error The validated config array or WP_Error on failure.
*/
function build_task_config_indexing_logic(array $post_data)
{
    $task_config = [];
    $task_config['post_types'] = isset($post_data['post_types']) && is_array($post_data['post_types']) ? array_map('sanitize_key', $post_data['post_types']) : [];
    $task_config['specific_post_ids'] = isset($post_data['specific_post_ids']) && is_array($post_data['specific_post_ids'])
        ? array_values(array_filter(array_map('absint', $post_data['specific_post_ids'])))
        : [];
    $task_config['target_store_provider'] = isset($post_data['target_store_provider']) ? sanitize_key($post_data['target_store_provider']) : 'openai';
    $task_config['target_store_id'] = isset($post_data['target_store_id']) ? sanitize_text_field($post_data['target_store_id']) : '';
    $task_config['indexing_frequency'] = isset($post_data['task_frequency']) ? sanitize_key($post_data['task_frequency']) : 'daily';
    $task_config['index_existing_now_flag'] = isset($post_data['index_existing_now_flag']) ? '1' : '0';
    $task_config['only_new_updated_flag'] = isset($post_data['only_new_updated_flag']) ? '1' : '0';
    $task_config['source_context'] = isset($post_data['source_context']) ? sanitize_key($post_data['source_context']) : '';
    $task_config['chatbot_id'] = isset($post_data['chatbot_id']) ? absint($post_data['chatbot_id']) : 0;

    if (empty($task_config['post_types']) && empty($task_config['specific_post_ids'])) {
        return new WP_Error('missing_post_types', __('Please select at least one post type for content indexing.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if (empty($task_config['target_store_id'])) {
        return new WP_Error('missing_target_store', __('Target vector store/index is required for content indexing.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }
    if ($task_config['index_existing_now_flag'] !== '1' && $task_config['only_new_updated_flag'] !== '1') {
        return new WP_Error('missing_indexing_behavior', __('Choose whether to index existing content, keep future content in sync, or both.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    if (
        $task_config['target_store_provider'] === 'pinecone' ||
        $task_config['target_store_provider'] === 'qdrant' ||
        $task_config['target_store_provider'] === 'chroma'
    ) {
        // The frontend already split the provider and model.
        $task_config['embedding_provider'] = isset($post_data['embedding_provider']) ? sanitize_key($post_data['embedding_provider']) : null;
        $task_config['embedding_model'] = isset($post_data['embedding_model']) ? sanitize_text_field($post_data['embedding_model']) : null;

        if (empty($task_config['embedding_provider']) || empty($task_config['embedding_model'])) {
            return new WP_Error('missing_embedding_config', __('An embedding model is required.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }
    }
    return $task_config;
}

// --- build-task-config-writing.php ---
if (!class_exists(AIPKit_Content_Writer_Image_Provider_Options::class)) {
    $aipkit_image_provider_options_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/class-aipkit-content-writer-image-provider-options.php';
    if (file_exists($aipkit_image_provider_options_path)) {
        require_once $aipkit_image_provider_options_path;
    }
}

/**
* Builds and validates the configuration for a 'content_writing' task.
* UPDATED: Now handles different generation modes (RSS, GSheets, URL) and saves their specific data.
* UPDATED: Now handles vector store settings.
*
* @param array $post_data The raw POST data.
* @return array|WP_Error The validated config array or WP_Error on failure.
*/
function build_task_config_writing_logic(array $post_data)
{
    $content_writer_config = [];
    if (class_exists(AIPKit_Content_Writer_Template_Manager::class)) {
        // This list should ideally mirror the one in AIPKit_Content_Writer_Template_Manager for consistency.
        $allowed_keys_from_template_manager = [
            'ai_provider', 'ai_model', 'content_title_bulk', 'content_keywords',
            'ai_temperature', 'post_type', 'post_author',
            'content_length',
            'post_status',
            'schedule_mode', 'smart_schedule_start_datetime', 'smart_schedule_interval_value', 'smart_schedule_interval_unit',
            'post_categories',
            'prompt_mode', 'custom_title_prompt', 'custom_content_prompt',
            'generate_meta_description', 'custom_meta_prompt',
            'generate_focus_keyword', 'custom_keyword_prompt',
            'generate_excerpt', 'custom_excerpt_prompt',
            'generate_tags', 'custom_tags_prompt',
            'cw_generation_mode', 'rss_feeds',
            'gsheets_sheet_id', 'gsheets_credentials',
            'url_list',
            'content_title',
            'generate_toc',
            'generate_seo_slug', // NEW: Add generate_seo_slug
            'seo_score_improvement_enabled', 'seo_score_continue_until_target',
            'seo_score_target', 'seo_score_max_passes', 'seo_score_profile', 'seo_score_disabled_rules',
            'generate_images_enabled', 'image_provider', 'image_model', 'image_provider_options', 'image_prompt',
            'image_count', 'image_placement', 'image_placement_param_x', 'image_alignment', 'image_size',
            'generate_featured_image', 'featured_image_prompt',
            'pexels_orientation', 'pexels_size', 'pexels_color',
            'pixabay_orientation', 'pixabay_image_type', 'pixabay_category',
            'enable_vector_store', 'vector_store_provider', 'openai_vector_store_ids',
            'pinecone_index_name', 'qdrant_collection_name', 'chroma_collection_name', 'vector_embedding_provider',
            'vector_embedding_model', 'vector_store_top_k', 'vector_store_confidence_threshold',
            'rss_include_keywords', 'rss_exclude_keywords',
            'reasoning_effort',
        ];
        $prompt_template_keys = [
            'custom_title_prompt', 'custom_content_prompt', 'custom_meta_prompt',
            'custom_keyword_prompt', 'custom_excerpt_prompt', 'custom_tags_prompt',
            'image_prompt', 'featured_image_prompt',
        ];
        $textarea_keys = [
            'content_title_bulk', 'rss_feeds', 'url_list', 'rss_include_keywords',
            'rss_exclude_keywords', 'content_title', 'smart_schedule_start_datetime',
        ];

        foreach ($allowed_keys_from_template_manager as $key) {
            if (isset($post_data[$key])) {
                if (in_array($key, $prompt_template_keys, true)) {
                    $content_writer_config[$key] = AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data[$key]));
                } elseif (in_array($key, $textarea_keys, true)) {
                    $content_writer_config[$key] = sanitize_textarea_field(wp_unslash($post_data[$key]));
                } elseif ($key === 'gsheets_credentials') {
                    if (class_exists('\WPAICG\Lib\Utils\AIPKit_Google_Credentials_Handler')) {
                        // The handler returns an array or null, which will be properly JSON encoded later.
                        $content_writer_config[$key] = \WPAICG\Lib\Utils\AIPKit_Google_Credentials_Handler::process_credentials($post_data[$key]);
                    } else {
                        $content_writer_config[$key] = null;
                    }
                } elseif ($key === 'image_provider_options') {
                    $content_writer_config[$key] = class_exists(AIPKit_Content_Writer_Image_Provider_Options::class)
                        ? AIPKit_Content_Writer_Image_Provider_Options::sanitize_options_json($post_data[$key], $post_data)
                        : '{}';
                } elseif ($key === 'ai_provider') {
                    $provider_raw = sanitize_text_field(wp_unslash($post_data[$key]));
                    $provider_key = strtolower($provider_raw);
                    $provider_map = [
                        'openai' => 'OpenAI',
                        'openrouter' => 'OpenRouter',
                        'google' => 'Google',
                        'azure' => 'Azure',
                        'claude' => 'Claude',
                        'deepseek' => 'DeepSeek',
                        'ollama' => 'Ollama',
                        'xai' => 'xAI',
                    ];
                    $content_writer_config[$key] = $provider_map[$provider_key] ?? ucfirst($provider_key);
                } elseif ($key === 'image_provider') {
                    $image_provider_key = sanitize_key(wp_unslash($post_data[$key]));
                    $allowed_image_providers = ['openai', 'openrouter', 'google', 'azure', 'xai', 'replicate', 'pexels', 'pixabay'];
                    $content_writer_config[$key] = in_array($image_provider_key, $allowed_image_providers, true) ? $image_provider_key : 'openai';
                } elseif (in_array($key, ['generate_meta_description', 'generate_focus_keyword', 'generate_excerpt', 'generate_tags', 'generate_toc', 'generate_images_enabled', 'generate_featured_image', 'enable_vector_store', 'generate_seo_slug', 'seo_score_improvement_enabled', 'seo_score_continue_until_target'], true)) {
                    $content_writer_config[$key] = ($post_data[$key] === '1' || $post_data[$key] === true || $post_data[$key] === 1) ? '1' : '0';
                } elseif ($key === 'post_categories' && is_array($post_data[$key])) {
                    $content_writer_config[$key] = array_map('absint', $post_data[$key]);
                } elseif ($key === 'post_author' || in_array($key, ['image_count', 'image_placement_param_x', 'vector_store_top_k', 'vector_store_confidence_threshold', 'smart_schedule_interval_value'], true)) {
                    $content_writer_config[$key] = absint($post_data[$key]);
                } elseif ($key === 'seo_score_target') {
                    $raw = isset($post_data[$key]) ? absint($post_data[$key]) : 100;
                    $content_writer_config[$key] = (string) max(80, min($raw, 100));
                } elseif ($key === 'seo_score_max_passes') {
                    $raw = isset($post_data[$key]) ? absint($post_data[$key]) : 3;
                    $content_writer_config[$key] = (string) max(1, min($raw, 5));
                } elseif ($key === 'seo_score_disabled_rules') {
                    $content_writer_config[$key] = class_exists(AIPKit_Content_Writer_SEO_Config::class)
                        ? AIPKit_Content_Writer_SEO_Config::sanitize_disabled_rules($post_data[$key])
                        : '[]';
                } elseif ($key === 'ai_temperature') {
                    $content_writer_config[$key] = (string)floatval($post_data[$key]);
                } elseif ($key === 'openai_vector_store_ids' && is_array($post_data[$key])) {
                    $content_writer_config[$key] = array_map('sanitize_text_field', $post_data[$key]);
                } elseif ($key === 'reasoning_effort') {
                    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($post_data[$key] ?? '');
                    $content_writer_config[$key] = $reasoning_effort !== '' ? $reasoning_effort : 'none';
                } elseif ($key === 'seo_score_profile') {
                    $profile = sanitize_key($post_data[$key]);
                    $allowed_profiles = ['auto', 'aipkit', 'yoast', 'rank_math', 'aioseo', 'framework'];
                    $content_writer_config[$key] = in_array($profile, $allowed_profiles, true) ? $profile : 'auto';
                } elseif (in_array($key, ['schedule_mode', 'smart_schedule_interval_unit', 'content_length'], true)) {
                    $content_writer_config[$key] = sanitize_key($post_data[$key]);
                } elseif (is_string($post_data[$key])) {
                    $content_writer_config[$key] = sanitize_text_field(wp_unslash($post_data[$key]));
                } else {
                    $content_writer_config[$key] = $post_data[$key];
                }
            }
        }

        $task_type = sanitize_key($post_data['task_type'] ?? 'content_writing_bulk');
        $mode = str_replace('content_writing_', '', $task_type);
        if ($mode === 'content_writing') {
            $mode = 'bulk';
        } // The base type means bulk

        // Keep exactly one content source. Inactive source values may still be
        // present in the form so users can switch back without losing drafts.
        $source_keys_by_mode = [
            'bulk' => ['content_title_bulk'],
            'csv' => ['content_title'],
            'rss' => ['rss_feeds', 'rss_include_keywords', 'rss_exclude_keywords'],
            'url' => ['url_list'],
            'gsheets' => ['gsheets_sheet_id', 'gsheets_credentials'],
        ];
        $all_source_keys = array_unique(array_merge(...array_values($source_keys_by_mode)));
        $active_source_keys = $source_keys_by_mode[$mode] ?? $source_keys_by_mode['bulk'];
        foreach ($all_source_keys as $source_key) {
            if (!in_array($source_key, $active_source_keys, true)) {
                unset($content_writer_config[$source_key]);
            }
        }

        // Bulk rows use a separate form field; normalize it only when Batch
        // Editor is the selected source so it can never overwrite CSV data.
        if ($mode === 'bulk' && !empty($content_writer_config['content_title_bulk'])) {
            $content_writer_config['content_title'] = $content_writer_config['content_title_bulk'];
        }
        unset($content_writer_config['content_title_bulk']);

        $image_provider = sanitize_key((string) ($content_writer_config['image_provider'] ?? 'openai'));
        $allowed_image_providers = ['openai', 'openrouter', 'google', 'azure', 'xai', 'replicate', 'pexels', 'pixabay'];
        $content_writer_config['image_provider'] = in_array($image_provider, $allowed_image_providers, true) ? $image_provider : 'openai';
        if (isset($content_writer_config['image_model'])) {
            $image_model = sanitize_text_field((string) $content_writer_config['image_model']);
            if ($content_writer_config['image_provider'] === 'openai' && class_exists(AIPKit_Providers::class)) {
                $image_model = AIPKit_Providers::normalize_openai_image_model($image_model);
            } elseif ($content_writer_config['image_provider'] === 'xai' && class_exists(AIPKit_Providers::class)) {
                $image_model = AIPKit_Providers::normalize_xai_image_model($image_model);
            } elseif (in_array($content_writer_config['image_provider'], ['pexels', 'pixabay'], true)) {
                $image_model = '';
            }
            $content_writer_config['image_model'] = $image_model;
        }

        $content_writer_config['cw_generation_mode'] = $mode;

        if ($mode === 'bulk' && !content_writing_has_manual_topic((string) ($content_writer_config['content_title'] ?? ''))) {
            return new WP_Error('missing_content_title_cw', __('Please add at least one topic.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }

        $content_writer_config['task_frequency'] = isset($post_data['task_frequency']) ? sanitize_key($post_data['task_frequency']) : 'daily';
        $content_writer_config['task_status_on_creation'] = isset($post_data['task_status']) ? sanitize_key($post_data['task_status']) : 'active';

        $content_writer_config = AIPKit_Content_Writer_Template_Manager::finalize_task_config($content_writer_config);
        if (is_wp_error($content_writer_config)) {
            return $content_writer_config;
        }

        if (($content_writer_config['enable_vector_store'] ?? '0') === '1') {
            $vector_provider = $content_writer_config['vector_store_provider'] ?? 'openai';
            $has_vector_source = false;
            if ($vector_provider === 'openai') {
                $has_vector_source = !empty($content_writer_config['openai_vector_store_ids']);
            } elseif ($vector_provider === 'pinecone') {
                $has_vector_source = !empty($content_writer_config['pinecone_index_name']);
            } elseif ($vector_provider === 'qdrant') {
                $has_vector_source = !empty($content_writer_config['qdrant_collection_name']);
            } elseif ($vector_provider === 'chroma') {
                $has_vector_source = !empty($content_writer_config['chroma_collection_name']);
            }
            if (!$has_vector_source) {
                return new WP_Error('missing_vector_source', __('Please select a knowledge source before enabling context.', 'gpt3-ai-content-generator'), ['status' => 400]);
            }
        }
    }
    return $content_writer_config;
}

// --- build-task-config-comment-reply.php ---
/**
 * Builds and validates the configuration for a 'community_reply_comments' task.
 *
 * @param array $post_data The raw POST data.
 * @return array|WP_Error The validated config array or WP_Error on failure.
 */
function build_task_config_comment_reply_logic(array $post_data)
{
    $task_config = [];
    // AI & Prompt Settings
    $provider_raw = $post_data['cc_ai_provider'] ?? 'openai';
    switch (strtolower($provider_raw)) {
        case 'openai':
            $task_config['ai_provider'] = 'OpenAI';
            break;
        case 'openrouter':
            $task_config['ai_provider'] = 'OpenRouter';
            break;
        case 'google':
            $task_config['ai_provider'] = 'Google';
            break;
        case 'azure':
            $task_config['ai_provider'] = 'Azure';
            break;
        case 'claude':
            $task_config['ai_provider'] = 'Claude';
            break;
        case 'deepseek':
            $task_config['ai_provider'] = 'DeepSeek';
            break;
        case 'xai':
            $task_config['ai_provider'] = 'xAI';
            break;
        case 'ollama':
            $task_config['ai_provider'] = 'Ollama';
            break;
        default:
            $task_config['ai_provider'] = ucfirst(strtolower($provider_raw));
            break;
    }

    $task_config['ai_model'] = $post_data['cc_ai_model'] ?? '';
    $task_config['ai_temperature'] = isset($post_data['cc_ai_temperature']) ? floatval($post_data['cc_ai_temperature']) : 1.0;
    $task_config['content_max_tokens'] = isset($post_data['cc_content_max_tokens']) ? absint($post_data['cc_content_max_tokens']) : 4000;
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($post_data['cc_reasoning_effort'] ?? '');
    $task_config['reasoning_effort'] = $reasoning_effort !== '' ? $reasoning_effort : 'none';
    $task_config['custom_content_prompt'] = isset($post_data['cc_custom_content_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data['cc_custom_content_prompt'])) : '';

    // Comment-specific settings
    $task_config['post_types_for_comments'] = isset($post_data['post_types_for_comments']) && is_array($post_data['post_types_for_comments']) ? array_map('sanitize_key', $post_data['post_types_for_comments']) : [];
    $task_config['reply_action'] = isset($post_data['reply_action']) && in_array($post_data['reply_action'], ['approve', 'hold'], true) ? $post_data['reply_action'] : 'hold';
    $task_config['no_reply_to_replies'] = isset($post_data['no_reply_to_replies']) ? '1' : '0';

    // Filters
    $task_config['include_keywords'] = isset($post_data['include_keywords']) ? sanitize_textarea_field(wp_unslash($post_data['include_keywords'])) : '';
    $task_config['exclude_keywords'] = isset($post_data['exclude_keywords']) ? sanitize_textarea_field(wp_unslash($post_data['exclude_keywords'])) : '';

    // Task Frequency
    $task_config['task_frequency'] = isset($post_data['task_frequency']) ? sanitize_key($post_data['task_frequency']) : 'hourly';

    // Validation
    if (empty($task_config['ai_provider']) || empty($task_config['ai_model'])) {
        return new WP_Error('missing_ai_config_comments', __('AI Provider and Model are required.', 'gpt3-ai-content-generator'));
    }
    if (empty($task_config['post_types_for_comments'])) {
        return new WP_Error('missing_post_types_comments', __('Please select at least one post type to monitor for comments.', 'gpt3-ai-content-generator'));
    }
    if (empty($task_config['custom_content_prompt'])) {
        return new WP_Error('missing_prompt_comments', __('The reply prompt cannot be empty.', 'gpt3-ai-content-generator'));
    }

    return $task_config;
}

// --- build-task-config-enhancement.php ---
/**
* Builds and validates the configuration for an 'enhance_existing_content' task.
*
* @param array $post_data The raw POST data.
* @return array|WP_Error The validated config array or WP_Error on failure.
*/
function build_task_config_enhancement_logic(array $post_data)
{
    $task_config = [];
    $task_config['post_types'] = isset($post_data['post_types']) && is_array($post_data['post_types']) ? array_map('sanitize_key', $post_data['post_types']) : [];
    $task_config['post_categories'] = isset($post_data['post_categories']) && is_array($post_data['post_categories']) ? array_map('absint', $post_data['post_categories']) : [];
    $task_config['post_authors'] = isset($post_data['post_authors']) && is_array($post_data['post_authors']) ? array_map('absint', $post_data['post_authors']) : [];
    $task_config['post_statuses'] = isset($post_data['post_statuses']) && is_array($post_data['post_statuses']) ? array_map('sanitize_key', $post_data['post_statuses']) : ['publish'];

    // Fields to enhance (respect explicit '1'/'0' values from frontend)
    $task_config['update_title'] = (isset($post_data['update_title']) && $post_data['update_title'] === '1') ? '1' : '0';
    $task_config['update_excerpt'] = (isset($post_data['update_excerpt']) && $post_data['update_excerpt'] === '1') ? '1' : '0';
    $task_config['update_meta'] = (isset($post_data['update_meta']) && $post_data['update_meta'] === '1') ? '1' : '0';
    $task_config['update_content'] = (isset($post_data['update_content']) && $post_data['update_content'] === '1') ? '1' : '0';

    // Prompts
    $task_config['title_prompt'] = isset($post_data['title_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data['title_prompt'])) : '';
    $task_config['excerpt_prompt'] = isset($post_data['excerpt_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data['excerpt_prompt'])) : '';
    $task_config['meta_prompt'] = isset($post_data['meta_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data['meta_prompt'])) : '';
    $task_config['content_prompt'] = isset($post_data['content_prompt']) ? AIPKit_Prompt_Sanitizer::sanitize(wp_unslash($post_data['content_prompt'])) : '';

    // AI Settings
    $provider_raw = $post_data['ai_provider'] ?? 'openai';
    switch (strtolower($provider_raw)) {
        case 'openai':
            $task_config['ai_provider'] = 'OpenAI';
            break;
        case 'openrouter':
            $task_config['ai_provider'] = 'OpenRouter';
            break;
        case 'google':
            $task_config['ai_provider'] = 'Google';
            break;
        case 'azure':
            $task_config['ai_provider'] = 'Azure';
            break;
        case 'claude':
            $task_config['ai_provider'] = 'Claude';
            break;
        case 'deepseek':
            $task_config['ai_provider'] = 'DeepSeek';
            break;
        case 'xai':
            $task_config['ai_provider'] = 'xAI';
            break;
        case 'ollama':
            $task_config['ai_provider'] = 'Ollama';
            break;
        default:
            $task_config['ai_provider'] = ucfirst(strtolower($provider_raw));
            break;
    }
    $task_config['ai_model'] = $post_data['ai_model'] ?? '';
    $task_config['ai_temperature'] = isset($post_data['ai_temperature']) ? floatval($post_data['ai_temperature']) : 1.0;
    $task_config['content_max_tokens'] = isset($post_data['content_max_tokens']) ? absint($post_data['content_max_tokens']) : 4000;
    $reasoning_effort = AIPKit_OpenAI_Reasoning::sanitize_effort($post_data['reasoning_effort'] ?? '');
    $task_config['reasoning_effort'] = $reasoning_effort !== '' ? $reasoning_effort : 'none';

    // Task Frequency
    $task_config['task_frequency'] = isset($post_data['task_frequency']) ? sanitize_key($post_data['task_frequency']) : 'daily';

    // One-time run flag (explicit value)
    $task_config['enhance_existing_now_flag'] = (isset($post_data['enhance_existing_now_flag']) && $post_data['enhance_existing_now_flag'] === '1') ? '1' : '0';

    // --- START: NEW Vector Store Settings ---
    $task_config['enable_vector_store'] = isset($post_data['enable_vector_store']) && $post_data['enable_vector_store'] === '1' ? '1' : '0';
    if ($task_config['enable_vector_store'] === '1') {
        $task_config['vector_store_provider'] = isset($post_data['vector_store_provider']) ? sanitize_key($post_data['vector_store_provider']) : 'openai';
        $task_config['vector_store_top_k'] = isset($post_data['vector_store_top_k']) ? absint($post_data['vector_store_top_k']) : 3;

        if ($task_config['vector_store_provider'] === 'openai') {
            $task_config['openai_vector_store_ids'] = isset($post_data['openai_vector_store_ids']) && is_array($post_data['openai_vector_store_ids']) ? array_map('sanitize_text_field', $post_data['openai_vector_store_ids']) : [];
        } elseif ($task_config['vector_store_provider'] === 'pinecone') {
            $task_config['pinecone_index_name'] = isset($post_data['pinecone_index_name']) ? sanitize_text_field($post_data['pinecone_index_name']) : '';
        } elseif ($task_config['vector_store_provider'] === 'qdrant') {
            $task_config['qdrant_collection_name'] = isset($post_data['qdrant_collection_name']) ? sanitize_text_field($post_data['qdrant_collection_name']) : '';
        } elseif ($task_config['vector_store_provider'] === 'chroma') {
            $task_config['chroma_collection_name'] = isset($post_data['chroma_collection_name']) ? sanitize_text_field($post_data['chroma_collection_name']) : '';
        }

        if (
            $task_config['vector_store_provider'] === 'pinecone' ||
            $task_config['vector_store_provider'] === 'qdrant' ||
            $task_config['vector_store_provider'] === 'chroma'
        ) {
            $task_config['vector_embedding_provider'] = isset($post_data['vector_embedding_provider']) ? sanitize_key($post_data['vector_embedding_provider']) : 'openai';
            $task_config['vector_embedding_model'] = isset($post_data['vector_embedding_model']) ? sanitize_text_field($post_data['vector_embedding_model']) : '';
        }

        $has_vector_source = false;
        if ($task_config['vector_store_provider'] === 'openai') {
            $has_vector_source = !empty($task_config['openai_vector_store_ids']);
        } elseif ($task_config['vector_store_provider'] === 'pinecone') {
            $has_vector_source = !empty($task_config['pinecone_index_name']);
        } elseif ($task_config['vector_store_provider'] === 'qdrant') {
            $has_vector_source = !empty($task_config['qdrant_collection_name']);
        } elseif ($task_config['vector_store_provider'] === 'chroma') {
            $has_vector_source = !empty($task_config['chroma_collection_name']);
        }
        if (!$has_vector_source) {
            return new WP_Error('missing_vector_source_enhance', __('Please select a knowledge source before enabling context.', 'gpt3-ai-content-generator'), ['status' => 400]);
        }
    }
    // --- END: NEW ---

    // Validation
    if (empty($task_config['post_types'])) {
        return new WP_Error('missing_post_types_enhance', __('Please select at least one post type to update.', 'gpt3-ai-content-generator'));
    }
    if ($task_config['update_title'] !== '1' && $task_config['update_excerpt'] !== '1' && $task_config['update_meta'] !== '1' && $task_config['update_content'] !== '1') {
        return new WP_Error('no_enhancement_selected', __('Please select at least one field to update (Title, Excerpt, Content, or Meta Description).', 'gpt3-ai-content-generator'));
    }

    return $task_config;
}

// --- save-task-to-database.php ---
/**
* Inserts or updates a task in the database.
*
* @param string $task_name The name of the task.
* @param string $task_type The type of the task.
* @param array $task_config The task's configuration data.
* @param string $task_status The status ('active' or 'paused').
* @param int $task_id The task ID (0 for new tasks).
* @return int|WP_Error The ID of the saved task, or a WP_Error on failure.
*/
function save_task_to_database_logic(string $task_name, string $task_type, array $task_config, string $task_status, int $task_id)
{
    global $wpdb;
    $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';

    $data = [
    'task_name' => $task_name,
    'task_type' => $task_type,
    'task_config' => wp_json_encode($task_config),
    'status' => $task_status,
    'updated_at' => current_time('mysql', 1),
    ];
    $formats = ['%s', '%s', '%s', '%s', '%s'];

    if ($task_id > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct lookup in a custom table before updating a task.
        $existing_task_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . esc_sql($tasks_table_name) . " WHERE id = %d", $task_id));
        if ($existing_task_id <= 0) {
            return new WP_Error('task_not_found', __('Task not found.', 'gpt3-ai-content-generator'), ['status' => 404]);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table. Caching is handled at the read level.
        $result = $wpdb->update($tasks_table_name, $data, ['id' => $task_id], $formats, ['%d']);
        if ($result === false) {
            return new WP_Error('db_error_update_task', __('Failed to update task.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        return $task_id;
    } else {
        $data['created_at'] = current_time('mysql', 1);
        $formats[] = '%s';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Direct insert into a custom table.
        $result = $wpdb->insert($tasks_table_name, $data, $formats);
        if ($result === false) {
            return new WP_Error('db_error_insert_task', __('Failed to save new task.', 'gpt3-ai-content-generator'), ['status' => 500]);
        }
        return $wpdb->insert_id;
    }
}

// --- finalize-task-save.php ---
if (file_exists(WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/event-processor/trigger/trigger-content-enhancement-task.php')) {
    require_once WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/event-processor/trigger/trigger-content-enhancement-task.php';
}

/**
* Finalizes the task saving process by scheduling the cron event and queueing initial content if necessary.
*
* @param int $task_id The ID of the saved task.
* @param array $task_config The configuration of the task.
* @param string $task_status The status of the task ('active' or 'paused').
* @param bool $is_new_task Whether the task was just created.
* @return void
*/
function finalize_task_save_logic(int $task_id, array $task_config, string $task_status, bool $is_new_task): void
{
    if (class_exists(AIPKit_Automated_Task_Scheduler::class)) {
        $frequency = $task_config['task_frequency'] ?? ($task_config['indexing_frequency'] ?? 'daily');
        AIPKit_Automated_Task_Scheduler::schedule_task_event($task_id, $frequency, $task_status);
    }
    if (
        $is_new_task &&
        $task_status === 'active' &&
        ($task_config['task_type'] ?? '') === 'content_indexing' &&
        ($task_config['index_existing_now_flag'] ?? '0') === '1' &&
        class_exists(AIPKit_Automated_Task_Content_Queuer::class)
    ) {
        AIPKit_Automated_Task_Content_Queuer::maybe_queue_initial_indexing_content($task_id, $task_config);
    } elseif (
        $is_new_task &&
        $task_status === 'active' &&
        ($task_config['task_type'] ?? '') === 'enhance_existing_content' &&
        ($task_config['enhance_existing_now_flag'] ?? '0') === '1' &&
        function_exists('\WPAICG\AutoGPT\Cron\EventProcessor\Trigger\trigger_content_enhancement_task_logic')
    ) {
        // Queue all existing content immediately. The last_run_time is null.
        Trigger\trigger_content_enhancement_task_logic($task_id, $task_config, null);

        // Disable the flag so it doesn't run again on the next cron trigger.
        global $wpdb;
        $tasks_table_name = $wpdb->prefix . 'aipkit_automated_tasks';
        $task_config['enhance_existing_now_flag'] = '0';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: Direct update to a custom table is necessary to update task state.
        $wpdb->update(
            $tasks_table_name,
            ['task_config' => wp_json_encode($task_config)],
            ['id' => $task_id],
            ['%s'],
            ['%d']
        );
    }
}
