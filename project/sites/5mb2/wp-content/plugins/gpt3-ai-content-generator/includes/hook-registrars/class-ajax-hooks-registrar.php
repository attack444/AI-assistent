<?php


namespace {
    if (!defined('ABSPATH')) {
        exit; // Exit if accessed directly
    }
}

namespace WPAICG\Includes\HookRegistrars {

// --- Use statements for AJAX handlers used by this registrar ---
// AJAX Handlers
use WPAICG\Images\AIPKit_Image_Settings_Ajax_Handler;
use WPAICG\Vector\AIPKit_Vector_Post_Processor_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Stores_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Pinecone_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Qdrant_Ajax_Handler;
use WPAICG\Dashboard\Ajax\AIPKit_Vector_Store_Chroma_Ajax_Handler;
use WPAICG\Core\Ajax\AIPKit_Core_Ajax_Handler;
use WPAICG\AutoGPT\AIPKit_Automated_Task_Manager;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Init_Stream_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Standard_Generation_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Title_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Excerpt_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Tags_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Save_Post_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Create_Task_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Prepare_Batch_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Images_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Meta_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Keyword_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Parse_Csv_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Fetch_Posts_Action;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Prompt_Library_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler; // NEW
use WPAICG\Chat\Frontend\Ajax\ChatFormSubmissionAjaxHandler;
use WPAICG\Lib\Chat\Frontend\Ajax\ChatFileUploadAjaxDispatcher as LibChatFileUploadAjaxDispatcher;
use WPAICG\Dashboard\Ajax\SettingsAjaxHandler;
use WPAICG\Dashboard\Ajax\AIPKit_Event_Webhook_Delivery_Issues_Ajax_Handler;
use WPAICG\Dashboard\Ajax\ModelsAjaxHandler;
// Post Enhancer Actions Handler.
use WPAICG\PostEnhancer\Ajax\AIPKit_Enhancer_Actions_Ajax_Handler;
use WPAICG\Core\Ajax\AIPKit_Semantic_Search_Ajax_Handler;
use WPAICG\Lib\Chat\Frontend\Ajax\Handlers\AIPKit_Realtime_Session_Ajax_Handler;


/**
 * Registers AJAX action hooks.
 */
class Ajax_Hooks_Registrar
{
    public static function register(
        AIPKit_Image_Settings_Ajax_Handler $image_settings_ajax_handler,
        AIPKit_Vector_Post_Processor_Ajax_Handler $vector_post_processor_ajax_handler,
        AIPKit_OpenAI_Vector_Stores_Ajax_Handler $openai_vs_stores_ajax_handler,
        AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler $openai_vs_files_ajax_handler,
        AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler $openai_wp_content_indexing_ajax_handler,
        AIPKit_Vector_Store_Pinecone_Ajax_Handler $pinecone_vector_store_ajax_handler,
        AIPKit_Vector_Store_Qdrant_Ajax_Handler $qdrant_vector_store_ajax_handler,
        AIPKit_Vector_Store_Chroma_Ajax_Handler $chroma_vector_store_ajax_handler,
        AIPKit_Core_Ajax_Handler $core_ajax_handler,
        AIPKit_Automated_Task_Manager $automated_task_manager,
        AIPKit_Content_Writer_Init_Stream_Action $content_writer_init_stream_action,
        AIPKit_Content_Writer_Standard_Generation_Action $content_writer_standard_gen_action,
        AIPKit_Content_Writer_Generate_Title_Action $content_writer_generate_title_action,
        AIPKit_Content_Writer_Save_Post_Action $content_writer_save_post_action,
        AIPKit_Content_Writer_Create_Task_Action $content_writer_create_task_action,
        ?AIPKit_Content_Writer_Template_Ajax_Handler $content_writer_template_ajax_handler = null,
        ?AIPKit_AI_Form_Ajax_Handler $ai_form_ajax_handler = null,
        ?AIPKit_AI_Form_Settings_Ajax_Handler $ai_form_settings_ajax_handler = null, // NEW
        ?ChatFormSubmissionAjaxHandler $chat_form_submission_ajax_handler = null,
        ?LibChatFileUploadAjaxDispatcher $chat_file_upload_ajax_dispatcher = null,
        ?SettingsAjaxHandler $settings_ajax_handler = null,
        ?AIPKit_Event_Webhook_Delivery_Issues_Ajax_Handler $event_webhook_delivery_issues_ajax_handler = null,
        ?ModelsAjaxHandler $models_ajax_handler = null,
        ?AIPKit_Realtime_Session_Ajax_Handler $realtime_session_ajax_handler = null,
        ?AIPKit_Semantic_Search_Ajax_Handler $semantic_search_ajax_handler = null, // ADDED
        ?AIPKit_Content_Writer_Prompt_Library_Ajax_Handler $content_writer_prompt_library_ajax_handler = null
    ) {
        $content_writer_generate_meta_action = class_exists(AIPKit_Content_Writer_Generate_Meta_Action::class) ? new AIPKit_Content_Writer_Generate_Meta_Action() : null;
        $content_writer_generate_keyword_action = class_exists(AIPKit_Content_Writer_Generate_Keyword_Action::class) ? new AIPKit_Content_Writer_Generate_Keyword_Action() : null;
        $content_writer_generate_excerpt_action = class_exists(AIPKit_Content_Writer_Generate_Excerpt_Action::class) ? new AIPKit_Content_Writer_Generate_Excerpt_Action() : null;
        $content_writer_generate_tags_action = class_exists(AIPKit_Content_Writer_Generate_Tags_Action::class) ? new AIPKit_Content_Writer_Generate_Tags_Action() : null;
        $content_writer_generate_images_action = class_exists(AIPKit_Content_Writer_Generate_Images_Action::class) ? new AIPKit_Content_Writer_Generate_Images_Action() : null;
        $content_writer_parse_csv_action = class_exists(AIPKit_Content_Writer_Parse_Csv_Action::class) ? new AIPKit_Content_Writer_Parse_Csv_Action() : null;
        $content_writer_fetch_posts_action = class_exists(AIPKit_Content_Writer_Fetch_Posts_Action::class) ? new AIPKit_Content_Writer_Fetch_Posts_Action() : null;
        $content_writer_prepare_batch_action = class_exists(AIPKit_Content_Writer_Prepare_Batch_Action::class) ? new AIPKit_Content_Writer_Prepare_Batch_Action() : null;
        $enhancer_actions_ajax_handler = class_exists(AIPKit_Enhancer_Actions_Ajax_Handler::class) ? new AIPKit_Enhancer_Actions_Ajax_Handler() : null;

        if ($settings_ajax_handler) {
            if (method_exists($settings_ajax_handler, 'ajax_save_settings')) {
                add_action('wp_ajax_aipkit_save_ai_settings', [$settings_ajax_handler, 'ajax_save_settings']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_save_semantic_search_settings')) {
                add_action('wp_ajax_aipkit_save_semantic_search_settings', [$settings_ajax_handler, 'ajax_save_semantic_search_settings']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_update_developer_credential')) {
                add_action('wp_ajax_aipkit_update_developer_credential', [$settings_ajax_handler, 'ajax_update_developer_credential']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_reveal_settings_credential')) {
                add_action('wp_ajax_aipkit_reveal_settings_credential', [$settings_ajax_handler, 'ajax_reveal_settings_credential']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_export_settings_backup')) {
                add_action('wp_ajax_aipkit_export_settings_backup', [$settings_ajax_handler, 'ajax_export_settings_backup']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_import_settings_backup')) {
                add_action('wp_ajax_aipkit_import_settings_backup', [$settings_ajax_handler, 'ajax_import_settings_backup']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_create_settings_restore_point')) {
                add_action('wp_ajax_aipkit_create_settings_restore_point', [$settings_ajax_handler, 'ajax_create_settings_restore_point']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_restore_settings_restore_point')) {
                add_action('wp_ajax_aipkit_restore_settings_restore_point', [$settings_ajax_handler, 'ajax_restore_settings_restore_point']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_clear_settings_model_cache')) {
                add_action('wp_ajax_aipkit_clear_settings_model_cache', [$settings_ajax_handler, 'ajax_clear_settings_model_cache']);
            }
            if (method_exists($settings_ajax_handler, 'ajax_clear_settings_transients')) {
                add_action('wp_ajax_aipkit_clear_settings_transients', [$settings_ajax_handler, 'ajax_clear_settings_transients']);
            }
        }

        if ($event_webhook_delivery_issues_ajax_handler) {
            if (method_exists($event_webhook_delivery_issues_ajax_handler, 'ajax_retry_event_webhook_delivery_issue')) {
                add_action('wp_ajax_aipkit_retry_event_webhook_delivery_issue', [$event_webhook_delivery_issues_ajax_handler, 'ajax_retry_event_webhook_delivery_issue']);
            }
            if (method_exists($event_webhook_delivery_issues_ajax_handler, 'ajax_clear_event_webhook_delivery_issue')) {
                add_action('wp_ajax_aipkit_clear_event_webhook_delivery_issue', [$event_webhook_delivery_issues_ajax_handler, 'ajax_clear_event_webhook_delivery_issue']);
            }
        }

        if ($models_ajax_handler) {
            if (method_exists($models_ajax_handler, 'ajax_sync_models')) {
                add_action('wp_ajax_aipkit_sync_models', [$models_ajax_handler, 'ajax_sync_models']);
            }
        }

        if (method_exists($image_settings_ajax_handler, 'ajax_save_image_settings')) {
            add_action('wp_ajax_aipkit_save_image_settings', [$image_settings_ajax_handler, 'ajax_save_image_settings']);
        }

        if ($ai_form_settings_ajax_handler && method_exists($ai_form_settings_ajax_handler, 'ajax_save_ai_forms_settings')) {
            add_action('wp_ajax_aipkit_save_ai_forms_settings', [$ai_form_settings_ajax_handler, 'ajax_save_ai_forms_settings']);
        }

        if (method_exists($vector_post_processor_ajax_handler, 'ajax_index_posts_to_vector_store')) {
            add_action('wp_ajax_aipkit_index_posts_to_vector_store', [$vector_post_processor_ajax_handler, 'ajax_index_posts_to_vector_store']);
        }

        add_action('wp_ajax_aipkit_list_vector_stores_openai', [$openai_vs_stores_ajax_handler, 'ajax_list_vector_stores_openai']);
        add_action('wp_ajax_aipkit_create_vector_store_openai', [$openai_vs_stores_ajax_handler, 'ajax_create_vector_store_openai']);
        add_action('wp_ajax_aipkit_delete_vector_store_openai', [$openai_vs_stores_ajax_handler, 'ajax_delete_vector_store_openai']);
        add_action('wp_ajax_aipkit_add_text_to_vector_store_openai', [$openai_vs_files_ajax_handler, 'ajax_add_text_to_vector_store_openai']);
        add_action('wp_ajax_aipkit_upload_and_add_file_to_store_direct_openai', [$openai_vs_files_ajax_handler, 'ajax_upload_and_add_file_to_store_direct_openai']);
        add_action('wp_ajax_aipkit_get_openai_file_batch_status', [$openai_vs_files_ajax_handler, 'ajax_get_openai_file_batch_status']);

        add_action('wp_ajax_aipkit_fetch_wp_content_for_indexing', [$openai_wp_content_indexing_ajax_handler, 'ajax_fetch_wp_content_for_indexing']);
        add_action('wp_ajax_aipkit_index_selected_wp_content', [$openai_wp_content_indexing_ajax_handler, 'ajax_index_selected_wp_content']);

        add_action('wp_ajax_aipkit_list_indexes_pinecone', [$pinecone_vector_store_ajax_handler, 'ajax_list_indexes_pinecone']);
        add_action('wp_ajax_aipkit_create_index_pinecone', [$pinecone_vector_store_ajax_handler, 'ajax_create_index_pinecone']);
        add_action('wp_ajax_aipkit_upsert_to_pinecone_index', [$pinecone_vector_store_ajax_handler, 'ajax_upsert_to_pinecone_index']);
        add_action('wp_ajax_aipkit_upload_file_and_upsert_to_pinecone', [$pinecone_vector_store_ajax_handler, 'ajax_upload_file_and_upsert_to_pinecone']);
        add_action('wp_ajax_aipkit_delete_index_pinecone', [$pinecone_vector_store_ajax_handler, 'ajax_delete_index_pinecone']);

        add_action('wp_ajax_aipkit_list_collections_qdrant', [$qdrant_vector_store_ajax_handler, 'ajax_list_collections_qdrant']);
        add_action('wp_ajax_aipkit_create_collection_qdrant', [$qdrant_vector_store_ajax_handler, 'ajax_create_collection_qdrant']);
        add_action('wp_ajax_aipkit_delete_collection_qdrant', [$qdrant_vector_store_ajax_handler, 'ajax_delete_collection_qdrant']);
        add_action('wp_ajax_aipkit_upsert_to_qdrant_collection', [$qdrant_vector_store_ajax_handler, 'ajax_upsert_to_qdrant_collection']);
        add_action('wp_ajax_aipkit_upload_file_and_upsert_to_qdrant', [$qdrant_vector_store_ajax_handler, 'ajax_upload_file_and_upsert_to_qdrant']);

        add_action('wp_ajax_aipkit_list_collections_chroma', [$chroma_vector_store_ajax_handler, 'ajax_list_collections_chroma']);
        add_action('wp_ajax_aipkit_create_collection_chroma', [$chroma_vector_store_ajax_handler, 'ajax_create_collection_chroma']);
        add_action('wp_ajax_aipkit_delete_collection_chroma', [$chroma_vector_store_ajax_handler, 'ajax_delete_collection_chroma']);
        add_action('wp_ajax_aipkit_upsert_to_chroma_collection', [$chroma_vector_store_ajax_handler, 'ajax_upsert_to_chroma_collection']);
        add_action('wp_ajax_aipkit_upload_file_and_upsert_to_chroma', [$chroma_vector_store_ajax_handler, 'ajax_upload_file_and_upsert_to_chroma']);

        if (method_exists($core_ajax_handler, 'ajax_get_global_vector_sources')) {
            add_action('wp_ajax_aipkit_get_global_vector_sources', [$core_ajax_handler, 'ajax_get_global_vector_sources']);
        }
        if (method_exists($core_ajax_handler, 'ajax_delete_vector_data_source_entry')) {
            add_action('wp_ajax_aipkit_delete_vector_data_source_entry', [$core_ajax_handler, 'ajax_delete_vector_data_source_entry']);
        }
        if (method_exists($core_ajax_handler, 'ajax_reindex_vector_data_source_entry')) {
            add_action('wp_ajax_aipkit_reindex_vector_data_source_entry', [$core_ajax_handler, 'ajax_reindex_vector_data_source_entry']);
        }
        if (method_exists($core_ajax_handler, 'ajax_get_cpt_indexing_options')) {
            add_action('wp_ajax_aipkit_get_cpt_indexing_options', [$core_ajax_handler, 'ajax_get_cpt_indexing_options']);
        }
        if (method_exists($core_ajax_handler, 'ajax_save_cpt_indexing_options')) {
            add_action('wp_ajax_aipkit_save_cpt_indexing_options', [$core_ajax_handler, 'ajax_save_cpt_indexing_options']);
        }
        if (method_exists($core_ajax_handler, 'ajax_get_stats_logs')) {
            add_action('wp_ajax_aipkit_stats_get_logs', [$core_ajax_handler, 'ajax_get_stats_logs']);
        }
        if (method_exists($core_ajax_handler, 'ajax_get_stats_log_detail')) {
            add_action('wp_ajax_aipkit_stats_get_log_detail', [$core_ajax_handler, 'ajax_get_stats_log_detail']);
        }
        if (method_exists($core_ajax_handler, 'ajax_set_stats_ip_block')) {
            add_action('wp_ajax_aipkit_stats_set_ip_block', [$core_ajax_handler, 'ajax_set_stats_ip_block']);
        }
        if (method_exists($core_ajax_handler, 'ajax_export_stats_logs')) {
            add_action('wp_ajax_aipkit_stats_export_logs', [$core_ajax_handler, 'ajax_export_stats_logs']);
        }
        if (method_exists($core_ajax_handler, 'ajax_delete_stats_log')) {
            add_action('wp_ajax_aipkit_stats_delete_log', [$core_ajax_handler, 'ajax_delete_stats_log']);
        }
        if (method_exists($core_ajax_handler, 'ajax_delete_stats_logs')) {
            add_action('wp_ajax_aipkit_stats_delete_logs', [$core_ajax_handler, 'ajax_delete_stats_logs']);
        }
        if (method_exists($core_ajax_handler, 'ajax_save_stats_settings')) {
            add_action('wp_ajax_aipkit_stats_save_settings', [$core_ajax_handler, 'ajax_save_stats_settings']);
        }
        if (method_exists($core_ajax_handler, 'ajax_get_stats_log_cron_status')) {
            add_action('wp_ajax_aipkit_stats_get_log_cron_status', [$core_ajax_handler, 'ajax_get_stats_log_cron_status']);
        }
        if (method_exists($core_ajax_handler, 'ajax_get_stats_pricing_management')) {
            add_action('wp_ajax_aipkit_stats_get_pricing_management', [$core_ajax_handler, 'ajax_get_stats_pricing_management']);
        }
        if (method_exists($core_ajax_handler, 'ajax_save_stats_pricing_rule')) {
            add_action('wp_ajax_aipkit_stats_save_pricing_rule', [$core_ajax_handler, 'ajax_save_stats_pricing_rule']);
        }
        if (method_exists($core_ajax_handler, 'ajax_delete_stats_pricing_rule')) {
            add_action('wp_ajax_aipkit_stats_delete_pricing_rule', [$core_ajax_handler, 'ajax_delete_stats_pricing_rule']);
        }
        if (method_exists($automated_task_manager, 'init_ajax_hooks')) {
            $automated_task_manager->init_ajax_hooks();
        }

        if (method_exists($content_writer_init_stream_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_init_stream', [$content_writer_init_stream_action, 'handle']);
        }
        if (method_exists($content_writer_standard_gen_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_standard', [$content_writer_standard_gen_action, 'handle']);
        }
        if (method_exists($content_writer_generate_title_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_title', [$content_writer_generate_title_action, 'handle']);
        }
        add_action('wp_ajax_aipkit_save_cw_template', [$content_writer_template_ajax_handler, 'ajax_save_template']);
        add_action('wp_ajax_aipkit_delete_cw_template', [$content_writer_template_ajax_handler, 'ajax_delete_template']);
        add_action('wp_ajax_aipkit_list_cw_templates', [$content_writer_template_ajax_handler, 'ajax_list_templates']);
        add_action('wp_ajax_aipkit_reset_cw_starter_templates', [$content_writer_template_ajax_handler, 'ajax_reset_starter_templates']);
        if ($content_writer_prompt_library_ajax_handler && method_exists($content_writer_prompt_library_ajax_handler, 'ajax_list_prompt_library')) {
            add_action('wp_ajax_aipkit_list_prompt_library', [$content_writer_prompt_library_ajax_handler, 'ajax_list_prompt_library']);
        }
        if ($content_writer_prompt_library_ajax_handler && method_exists($content_writer_prompt_library_ajax_handler, 'ajax_create_prompt_library_item')) {
            add_action('wp_ajax_aipkit_create_prompt_library_item', [$content_writer_prompt_library_ajax_handler, 'ajax_create_prompt_library_item']);
        }
        if ($content_writer_prompt_library_ajax_handler && method_exists($content_writer_prompt_library_ajax_handler, 'ajax_update_prompt_library_item')) {
            add_action('wp_ajax_aipkit_update_prompt_library_item', [$content_writer_prompt_library_ajax_handler, 'ajax_update_prompt_library_item']);
        }
        if ($content_writer_prompt_library_ajax_handler && method_exists($content_writer_prompt_library_ajax_handler, 'ajax_delete_prompt_library_item')) {
            add_action('wp_ajax_aipkit_delete_prompt_library_item', [$content_writer_prompt_library_ajax_handler, 'ajax_delete_prompt_library_item']);
        }

        if (method_exists($content_writer_save_post_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_save_post', [$content_writer_save_post_action, 'handle']);
        }
        if (method_exists($content_writer_create_task_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_create_task', [$content_writer_create_task_action, 'handle']);
        }
        if ($content_writer_prepare_batch_action && method_exists($content_writer_prepare_batch_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_prepare_batch', [$content_writer_prepare_batch_action, 'handle']);
        }
        if ($content_writer_generate_meta_action && method_exists($content_writer_generate_meta_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_meta_desc', [$content_writer_generate_meta_action, 'handle']);
        }
        if ($content_writer_generate_keyword_action && method_exists($content_writer_generate_keyword_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_focus_keyword', [$content_writer_generate_keyword_action, 'handle']);
        }
        if ($content_writer_generate_excerpt_action && method_exists($content_writer_generate_excerpt_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_excerpt', [$content_writer_generate_excerpt_action, 'handle']);
        }
        if ($content_writer_generate_tags_action && method_exists($content_writer_generate_tags_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_tags', [$content_writer_generate_tags_action, 'handle']);
        }
        if ($content_writer_generate_images_action && method_exists($content_writer_generate_images_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_generate_images', [$content_writer_generate_images_action, 'handle']);
        }
        if ($content_writer_parse_csv_action && method_exists($content_writer_parse_csv_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_parse_csv', [$content_writer_parse_csv_action, 'handle']);
        }
        if ($content_writer_fetch_posts_action && method_exists($content_writer_fetch_posts_action, 'handle')) {
            add_action('wp_ajax_aipkit_content_writer_fetch_existing_posts', [$content_writer_fetch_posts_action, 'handle']);
        }

        if ($ai_form_ajax_handler && method_exists($ai_form_ajax_handler, 'register_ajax_hooks')) {
            $ai_form_ajax_handler->register_ajax_hooks();
        }

        if ($chat_form_submission_ajax_handler && method_exists($chat_form_submission_ajax_handler, 'ajax_handle_form_submission')) {
            add_action('wp_ajax_aipkit_handle_form_submission', [$chat_form_submission_ajax_handler, 'ajax_handle_form_submission']);
            add_action('wp_ajax_nopriv_aipkit_handle_form_submission', [$chat_form_submission_ajax_handler, 'ajax_handle_form_submission']);
        }
        if ($chat_file_upload_ajax_dispatcher && method_exists($chat_file_upload_ajax_dispatcher, 'ajax_handle_frontend_file_upload')) {
            add_action('wp_ajax_aipkit_frontend_chat_upload_file', [$chat_file_upload_ajax_dispatcher, 'ajax_handle_frontend_file_upload']);
            add_action('wp_ajax_nopriv_aipkit_frontend_chat_upload_file', [$chat_file_upload_ajax_dispatcher, 'ajax_handle_frontend_file_upload']);
        }

        if ($realtime_session_ajax_handler && method_exists($realtime_session_ajax_handler, 'ajax_create_session')) {
            add_action('wp_ajax_aipkit_create_realtime_session', [$realtime_session_ajax_handler, 'ajax_create_session']);
            add_action('wp_ajax_nopriv_aipkit_create_realtime_session', [$realtime_session_ajax_handler, 'ajax_create_session']);
        }
        
        if ($realtime_session_ajax_handler && method_exists($realtime_session_ajax_handler, 'ajax_log_session_turn')) {
            add_action('wp_ajax_aipkit_log_realtime_session_turn', [$realtime_session_ajax_handler, 'ajax_log_session_turn']);
            add_action('wp_ajax_nopriv_aipkit_log_realtime_session_turn', [$realtime_session_ajax_handler, 'ajax_log_session_turn']);
        }

        if ($enhancer_actions_ajax_handler) {
            if (method_exists($enhancer_actions_ajax_handler, 'ajax_get_actions')) {
                add_action('wp_ajax_aipkit_get_enhancer_actions', [$enhancer_actions_ajax_handler, 'ajax_get_actions']);
            }
            if (method_exists($enhancer_actions_ajax_handler, 'ajax_save_action')) {
                add_action('wp_ajax_aipkit_save_enhancer_action', [$enhancer_actions_ajax_handler, 'ajax_save_action']);
            }
            if (method_exists($enhancer_actions_ajax_handler, 'ajax_delete_action')) {
                add_action('wp_ajax_aipkit_delete_enhancer_action', [$enhancer_actions_ajax_handler, 'ajax_delete_action']);
            }
            if (method_exists($enhancer_actions_ajax_handler, 'ajax_reset_actions')) {
                add_action('wp_ajax_aipkit_reset_enhancer_actions', [$enhancer_actions_ajax_handler, 'ajax_reset_actions']);
            }
            if (method_exists($enhancer_actions_ajax_handler, 'ajax_reorder_actions')) {
                add_action('wp_ajax_aipkit_reorder_enhancer_actions', [$enhancer_actions_ajax_handler, 'ajax_reorder_actions']);
            }
        }

        if ($semantic_search_ajax_handler && method_exists($semantic_search_ajax_handler, 'ajax_perform_semantic_search')) {
            add_action('wp_ajax_aipkit_perform_semantic_search', [$semantic_search_ajax_handler, 'ajax_perform_semantic_search']);
            add_action('wp_ajax_nopriv_aipkit_perform_semantic_search', [$semantic_search_ajax_handler, 'ajax_perform_semantic_search']);
        }
    }
}

}
