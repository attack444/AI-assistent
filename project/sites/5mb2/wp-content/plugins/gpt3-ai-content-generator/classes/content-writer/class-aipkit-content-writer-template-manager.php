<?php


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

namespace WPAICG\ContentWriter;

use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_SEO_Config;
use WP_Error;

// Load template manager logic files.
require_once __DIR__ . '/template-manager/methods.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Manages CRUD operations for Content Writer templates.
* This class now acts as a facade, delegating its methods to namespaced functions.
*/
class AIPKit_Content_Writer_Template_Manager
{
    private $wpdb;
    private $table_name;
    private $allowed_config_keys = [
        'ai_provider', 'ai_model', 'content_title', 'content_title_bulk',
        'content_keywords',
        'ai_temperature', 'content_length', 'content_max_tokens',
        'post_type', 'post_author', 'post_status',
        'post_schedule_date', 'post_schedule_time',
        'post_categories', 'prompt_mode', 'custom_title_prompt', 'custom_content_prompt',
        'generate_title', 'generate_content',
        'generate_meta_description', 'custom_meta_prompt',
        'generate_focus_keyword', 'custom_keyword_prompt',
        'generate_excerpt', 'custom_excerpt_prompt',
        'generate_tags', 'custom_tags_prompt',
        'custom_title_prompt_update', 'custom_content_prompt_update',
        'custom_meta_prompt_update', 'custom_keyword_prompt_update',
        'custom_excerpt_prompt_update', 'custom_tags_prompt_update',
        'cw_generation_mode', 'rss_feeds',
        'gsheets_sheet_id', 'gsheets_credentials',
        'url_list',
        'generate_toc',
        'generate_seo_slug', // NEW: Add generate_seo_slug
        'seo_score_improvement_enabled', 'seo_score_continue_until_target',
        'seo_score_target', 'seo_score_max_passes', 'seo_score_profile', 'seo_score_disabled_rules',
        'generate_images_enabled', 'image_provider', 'image_model', 'image_provider_options', 'image_prompt',
        'image_prompt_update',
        'generate_image_title', 'generate_image_alt_text', 'generate_image_caption', 'generate_image_description',
        'image_title_prompt', 'image_alt_text_prompt', 'image_caption_prompt', 'image_description_prompt',
        'image_title_prompt_update', 'image_alt_text_prompt_update', 'image_caption_prompt_update', 'image_description_prompt_update',
        'image_count', 'image_placement', 'image_placement_param_x', 'image_alignment', 'image_size',
        'generate_featured_image', 'featured_image_prompt',
        'featured_image_prompt_update',
    'enable_vector_store', 'vector_store_provider', 'openai_vector_store_ids', 'pinecone_index_name', 'qdrant_collection_name', 'chroma_collection_name', 'vector_embedding_provider', 'vector_embedding_model', 'vector_store_top_k', 'vector_store_confidence_threshold',
        'rss_include_keywords', 'rss_exclude_keywords',
        'pexels_orientation', 'pexels_size', 'pexels_color',
        'pixabay_orientation', 'pixabay_image_type', 'pixabay_category',
        'update_title', 'update_excerpt', 'update_content', 'update_meta',
        'title_prompt', 'excerpt_prompt', 'content_prompt', 'meta_prompt',
        'title', 'excerpt', 'content', 'meta', 'keyword', 'tags', // These are the keys for bulk enhancer templates
        'reasoning_effort' // For o1 models reasoning effort setting
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aipkit_content_writer_templates';
    }

    public static function ensure_default_template_exists()
    {
        TemplateManagerMethods\ensure_default_template_exists_logic(new self());
    }

    /**
     * @return int|\WP_Error
     */
    public function create_template(string $template_name, array $config, string $template_type = 'content_writer')
    {
        return TemplateManagerMethods\create_template_logic($this, $template_name, $config, $template_type);
    }

    /**
     * @return bool|\WP_Error
     */
    public function update_template(int $template_id, string $template_name, array $config)
    {
        return TemplateManagerMethods\update_template_logic($this, $template_id, $template_name, $config);
    }

    /**
     * @return bool|\WP_Error
     */
    public function delete_template(int $template_id)
    {
        return TemplateManagerMethods\delete_template_logic($this, $template_id);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function get_template(int $template_id, ?int $user_id_override = null)
    {
        return TemplateManagerMethods\get_template_logic($this, $template_id, $user_id_override);
    }

    public function get_templates_for_user(string $type = 'content_writer'): array
    {
        return TemplateManagerMethods\get_templates_for_user_logic($this, $type);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function reset_starter_templates()
    {
        return TemplateManagerMethods\reset_starter_templates_logic($this);
    }

    // --- Getters for use by namespaced functions ---
    public function get_wpdb()
    {
        return $this->wpdb;
    }
    public function get_table_name(): string
    {
        return $this->table_name;
    }
    public function get_allowed_config_keys(): array
    {
        return $this->allowed_config_keys;
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public static function finalize_task_config(array $content_writer_config)
    {
        $content_writer_config['seo_score_improvement_enabled'] = $content_writer_config['seo_score_improvement_enabled'] ?? '0';
        $content_writer_config['seo_score_continue_until_target'] = $content_writer_config['seo_score_continue_until_target'] ?? '1';
        $content_writer_config['seo_score_target'] = $content_writer_config['seo_score_target'] ?? '100';
        $content_writer_config['seo_score_max_passes'] = $content_writer_config['seo_score_max_passes'] ?? '3';
        $content_writer_config['seo_score_profile'] = $content_writer_config['seo_score_profile'] ?? 'auto';
        $content_writer_config['seo_score_disabled_rules'] = $content_writer_config['seo_score_disabled_rules']
            ?? (class_exists(AIPKit_Content_Writer_SEO_Config::class) ? AIPKit_Content_Writer_SEO_Config::default_disabled_rules() : '[]');
        $content_writer_config['image_provider_options'] = class_exists(AIPKit_Content_Writer_Image_Provider_Options::class)
            ? AIPKit_Content_Writer_Image_Provider_Options::sanitize_options_json($content_writer_config['image_provider_options'] ?? '{}', $content_writer_config)
            : ($content_writer_config['image_provider_options'] ?? '{}');

        if (class_exists(AIPKit_Content_Writer_SEO_Config::class)) {
            $seo_permission_check = AIPKit_Content_Writer_SEO_Config::require_pro_for_improvement($content_writer_config);
            if (is_wp_error($seo_permission_check)) {
                return $seo_permission_check;
            }
            $content_writer_config = AIPKit_Content_Writer_SEO_Config::normalize($content_writer_config);
        }

        return $content_writer_config;
    }
}
