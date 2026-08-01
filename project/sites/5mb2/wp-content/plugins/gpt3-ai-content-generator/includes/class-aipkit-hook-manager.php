<?php


namespace WPAICG\Includes;

if (!defined('ABSPATH')) {
    exit;
}

// --- Use statements for ALL handlers/services needed by ANY registrar ---
// Core Functionality
use WPAICG\WP_AI_Content_Generator_i18n;
use WPAICG\PublicFrontend\WP_AI_Content_Generator_Public;
use WPAICG\Includes\AIPKit_Blocks_Manager;
use WPAICG\Shortcodes\AIPKit_Shortcodes_Manager;
use WPAICG\PostEnhancer\Core as PostEnhancerCore;
use WPAICG\Speech\AIPKit_Speech_Manager;
use WPAICG\STT\AIPKit_STT_Manager;
use WPAICG\Images\AIPKit_Image_Manager;
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
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Save_Post_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Create_Task_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Images_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Meta_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Generate_Keyword_Action;
use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Parse_Csv_Action;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Template_Ajax_Handler;
use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Prompt_Library_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Ajax_Handler;
use WPAICG\AIForms\Admin\AIPKit_AI_Form_Settings_Ajax_Handler; // NEW
use WPAICG\Chat\Frontend\Ajax\ChatFormSubmissionAjaxHandler;
use WPAICG\Lib\Chat\Frontend\Ajax\ChatFileUploadAjaxDispatcher as LibChatFileUploadAjaxDispatcher;
use WPAICG\Dashboard\Ajax\SettingsAjaxHandler;
use WPAICG\Dashboard\Ajax\AIPKit_Event_Webhook_Delivery_Issues_Ajax_Handler;
use WPAICG\Dashboard\Ajax\ModelsAjaxHandler;
use WPAICG\Core\Ajax\AIPKit_Semantic_Search_Ajax_Handler;
use WPAICG\Lib\Chat\Frontend\Ajax\Handlers\AIPKit_Realtime_Session_Ajax_Handler;
use WPAICG\REST\AIPKit_REST_Controller;

// --- Use statements for the NEW Hook Registrar classes ---
use WPAICG\Includes\HookRegistrars\Core_Hooks_Registrar;
use WPAICG\Includes\HookRegistrars\Admin_Asset_Hooks_Registrar;
use WPAICG\Includes\HookRegistrars\Ajax_Hooks_Registrar;
use WPAICG\Includes\HookRegistrars\Rest_Api_Hooks_Registrar;
use WPAICG\Includes\HookRegistrars\Module_Initializer_Hooks_Registrar;


/**
 * AIPKit_Hook_Manager
 * Centralizes the registration of WordPress hooks for the plugin by orchestrating sub-registrars.
 * MODIFIED: Conditional instantiation and error logging for LibChatFileUploadAjaxDispatcher based on Pro plan and addon status.
 */
class AIPKit_Hook_Manager
{
    /**
     * Define the core hooks (actions and filters) by calling specialized registrars.
     *
     * @param string $plugin_version The current plugin version.
     */
    public static function register_hooks(string $plugin_version)
    {
        $admin_like_request = is_admin() || wp_doing_ajax();

        // --- Instantiate ALL services/handlers needed by ANY registrar ---
        $i18n            = new WP_AI_Content_Generator_i18n();
        $public_handler  = new WP_AI_Content_Generator_Public();
        $blocks_manager  = new AIPKit_Blocks_Manager($plugin_version);
        $shortcodes      = new AIPKit_Shortcodes_Manager($plugin_version);
        $post_enhancer   = ($admin_like_request && class_exists(PostEnhancerCore::class)) ? new PostEnhancerCore() : null;
        $speech_manager  = class_exists(AIPKit_Speech_Manager::class) ? new AIPKit_Speech_Manager() : null;
        $stt_manager     = class_exists(AIPKit_STT_Manager::class) ? new AIPKit_STT_Manager() : null;
        $image_manager   = class_exists(AIPKit_Image_Manager::class) ? new AIPKit_Image_Manager() : null;
        $rest_controller = class_exists(AIPKit_REST_Controller::class) ? new AIPKit_REST_Controller() : null;

        $image_settings_ajax_handler = null;
        $vector_post_processor_ajax_handler = null;
        $openai_vs_stores_ajax_handler = null;
        $openai_vs_files_ajax_handler = null;
        $openai_wp_content_indexing_ajax_handler = null;
        $pinecone_vector_store_ajax_handler = null;
        $qdrant_vector_store_ajax_handler = null;
        $chroma_vector_store_ajax_handler = null;
        $core_ajax_handler = null;
        $automated_task_manager = null;
        $content_writer_init_stream_action = null;
        $content_writer_standard_gen_action = null;
        $content_writer_generate_title_action = null;
        $content_writer_save_post_action = null;
        $content_writer_create_task_action = null;
        $content_writer_template_ajax_handler = null;
        $content_writer_prompt_library_ajax_handler = null;
        $ai_form_ajax_handler = null;
        $ai_form_settings_ajax_handler = null;
        $settings_ajax_handler = null;
        $event_webhook_delivery_issues_ajax_handler = null;
        $models_ajax_handler = null;
        $semantic_search_ajax_handler = null;
        $chat_form_submission_ajax_handler = null;
        $chat_file_upload_ajax_dispatcher = null;
        $realtime_session_ajax_handler = null;

        if ($admin_like_request) {
            $image_settings_ajax_handler = class_exists(AIPKit_Image_Settings_Ajax_Handler::class) ? new AIPKit_Image_Settings_Ajax_Handler() : null;
            $vector_post_processor_ajax_handler = class_exists(AIPKit_Vector_Post_Processor_Ajax_Handler::class) ? new AIPKit_Vector_Post_Processor_Ajax_Handler() : null;
            $openai_vs_stores_ajax_handler = class_exists(AIPKit_OpenAI_Vector_Stores_Ajax_Handler::class) ? new AIPKit_OpenAI_Vector_Stores_Ajax_Handler() : null;
            $openai_vs_files_ajax_handler = class_exists(AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler::class) ? new AIPKit_OpenAI_Vector_Store_Files_Ajax_Handler() : null;
            $openai_wp_content_indexing_ajax_handler = class_exists(AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler::class) ? new AIPKit_OpenAI_WP_Content_Indexing_Ajax_Handler() : null;
            $pinecone_vector_store_ajax_handler = class_exists(AIPKit_Vector_Store_Pinecone_Ajax_Handler::class) ? new AIPKit_Vector_Store_Pinecone_Ajax_Handler() : null;
            $qdrant_vector_store_ajax_handler = class_exists(AIPKit_Vector_Store_Qdrant_Ajax_Handler::class) ? new AIPKit_Vector_Store_Qdrant_Ajax_Handler() : null;
            $chroma_vector_store_ajax_handler = class_exists(AIPKit_Vector_Store_Chroma_Ajax_Handler::class) ? new AIPKit_Vector_Store_Chroma_Ajax_Handler() : null;
            $core_ajax_handler = class_exists(AIPKit_Core_Ajax_Handler::class) ? new AIPKit_Core_Ajax_Handler() : null;
            $automated_task_manager = class_exists(AIPKit_Automated_Task_Manager::class) ? new AIPKit_Automated_Task_Manager() : null;
            $content_writer_init_stream_action = class_exists(AIPKit_Content_Writer_Init_Stream_Action::class) ? new AIPKit_Content_Writer_Init_Stream_Action() : null;
            $content_writer_standard_gen_action = class_exists(AIPKit_Content_Writer_Standard_Generation_Action::class) ? new AIPKit_Content_Writer_Standard_Generation_Action() : null;
            $content_writer_generate_title_action = class_exists(AIPKit_Content_Writer_Generate_Title_Action::class) ? new AIPKit_Content_Writer_Generate_Title_Action() : null;
            $content_writer_save_post_action = class_exists(AIPKit_Content_Writer_Save_Post_Action::class) ? new AIPKit_Content_Writer_Save_Post_Action() : null;
            $content_writer_create_task_action = class_exists(AIPKit_Content_Writer_Create_Task_Action::class) ? new AIPKit_Content_Writer_Create_Task_Action() : null;
            $content_writer_template_ajax_handler = class_exists(AIPKit_Content_Writer_Template_Ajax_Handler::class) ? new AIPKit_Content_Writer_Template_Ajax_Handler() : null;
            $content_writer_prompt_library_ajax_handler = class_exists(AIPKit_Content_Writer_Prompt_Library_Ajax_Handler::class) ? new AIPKit_Content_Writer_Prompt_Library_Ajax_Handler() : null;
            $ai_form_ajax_handler = class_exists(AIPKit_AI_Form_Ajax_Handler::class) ? new AIPKit_AI_Form_Ajax_Handler() : null;
            $ai_form_settings_ajax_handler = class_exists(AIPKit_AI_Form_Settings_Ajax_Handler::class) ? new AIPKit_AI_Form_Settings_Ajax_Handler() : null;
            $settings_ajax_handler = class_exists(SettingsAjaxHandler::class) ? new SettingsAjaxHandler() : null;
            $event_webhook_delivery_issues_ajax_handler = class_exists(AIPKit_Event_Webhook_Delivery_Issues_Ajax_Handler::class) ? new AIPKit_Event_Webhook_Delivery_Issues_Ajax_Handler() : null;
            $models_ajax_handler = class_exists(ModelsAjaxHandler::class) ? new ModelsAjaxHandler() : null;
            $semantic_search_ajax_handler = class_exists(AIPKit_Semantic_Search_Ajax_Handler::class) ? new AIPKit_Semantic_Search_Ajax_Handler() : null;

            if (class_exists(ChatFormSubmissionAjaxHandler::class)) {
                $chat_form_submission_ajax_handler = new ChatFormSubmissionAjaxHandler();
            }

            if (
                class_exists('\WPAICG\aipkit_dashboard') &&
                method_exists('\WPAICG\aipkit_dashboard', 'is_pro_plan') &&
                \WPAICG\aipkit_dashboard::is_pro_plan() &&
                class_exists(LibChatFileUploadAjaxDispatcher::class)
            ) {
                $chat_file_upload_ajax_dispatcher = new LibChatFileUploadAjaxDispatcher();
            }

            if (class_exists('\WPAICG\aipkit_dashboard') && \WPAICG\aipkit_dashboard::is_pro_plan()) {
                if (class_exists(AIPKit_Realtime_Session_Ajax_Handler::class)) {
                    $realtime_session_ajax_handler = new AIPKit_Realtime_Session_Ajax_Handler();
                }
            }
        }

        // --- End Instantiations ---

        // --- Call Specialized Registrars ---
        if (class_exists(Core_Hooks_Registrar::class)) {
            Core_Hooks_Registrar::register(
                $i18n,
                $public_handler,
                $blocks_manager,
                $shortcodes,
                $post_enhancer,
                $speech_manager,
                $stt_manager,
                $image_manager
            );
        }

        if ($admin_like_request && class_exists(Admin_Asset_Hooks_Registrar::class)) {
            Admin_Asset_Hooks_Registrar::register();
        }

        if ($admin_like_request && class_exists(Ajax_Hooks_Registrar::class) &&
            $image_settings_ajax_handler && $vector_post_processor_ajax_handler &&
            $openai_vs_stores_ajax_handler && $openai_vs_files_ajax_handler &&
            $openai_wp_content_indexing_ajax_handler && $pinecone_vector_store_ajax_handler &&
            $qdrant_vector_store_ajax_handler && $chroma_vector_store_ajax_handler && $core_ajax_handler &&
            $automated_task_manager && $content_writer_init_stream_action &&
            $content_writer_standard_gen_action && $content_writer_generate_title_action &&
            $content_writer_save_post_action && $content_writer_create_task_action &&
            $content_writer_template_ajax_handler && $ai_form_ajax_handler &&
            $ai_form_settings_ajax_handler && // NEW CHECK
            $chat_form_submission_ajax_handler && $settings_ajax_handler && $models_ajax_handler &&
            $semantic_search_ajax_handler // ADDED
        ) {
            Ajax_Hooks_Registrar::register(
                $image_settings_ajax_handler,
                $vector_post_processor_ajax_handler,
                $openai_vs_stores_ajax_handler,
                $openai_vs_files_ajax_handler,
                $openai_wp_content_indexing_ajax_handler,
                $pinecone_vector_store_ajax_handler,
                $qdrant_vector_store_ajax_handler,
                $chroma_vector_store_ajax_handler,
                $core_ajax_handler,
                $automated_task_manager,
                $content_writer_init_stream_action,
                $content_writer_standard_gen_action,
                $content_writer_generate_title_action,
                $content_writer_save_post_action,
                $content_writer_create_task_action,
                $content_writer_template_ajax_handler,
                $ai_form_ajax_handler,
                $ai_form_settings_ajax_handler, // NEW: Pass handler
                $chat_form_submission_ajax_handler,
                $chat_file_upload_ajax_dispatcher,
                $settings_ajax_handler,
                $event_webhook_delivery_issues_ajax_handler,
                $models_ajax_handler,
                $realtime_session_ajax_handler,
                $semantic_search_ajax_handler, // ADDED
                $content_writer_prompt_library_ajax_handler
            );
        }


        if ($rest_controller && class_exists(Rest_Api_Hooks_Registrar::class)) {
            Rest_Api_Hooks_Registrar::register($rest_controller);
        }

        if (class_exists(Module_Initializer_Hooks_Registrar::class)) {
            Module_Initializer_Hooks_Registrar::register();
        }
    }
}
