<?php

namespace WPAICG\Includes\DependencyLoaders;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Asset_Handlers_Loader
{
    public static function load()
    {
        require_once WPAICG_PLUGIN_DIR . 'admin/assets/class-aipkit-admin-assets.php';
    }
}

class Provider_Dependencies_Loader
{
    public static function load()
    {
        $providers_path = WPAICG_PLUGIN_DIR . 'classes/core/providers/';
        $traits_path = $providers_path . 'traits/';
        require_once $providers_path . 'interface-provider-strategy.php';
        require_once $providers_path . 'base-provider-strategy.php';
        require_once $traits_path . 'trait-aipkit-chat-completions-payload.php';
        require_once $traits_path . 'trait-aipkit-chat-completions-response-parser.php';
        require_once $traits_path . 'trait-aipkit-chat-completions-sse-parser.php';

        // Load OpenAI provider and its component wrappers.
        require_once $providers_path . 'openai/bootstrap-provider-strategy.php';


        // Load other providers
        $openrouter_dir = $providers_path . 'openrouter/';
        if (file_exists($openrouter_dir . 'bootstrap-provider-strategy.php')) {
            require_once $openrouter_dir . 'bootstrap-provider-strategy.php';
        }

        $google_dir = $providers_path . 'google/';
        if (file_exists($google_dir . 'bootstrap-provider-strategy.php')) {
            require_once $google_dir . 'bootstrap-provider-strategy.php';
        }

        require_once $providers_path . 'azure/bootstrap-provider-strategy.php';
        if (file_exists($providers_path . 'claude/bootstrap-provider-strategy.php')) {
            require_once $providers_path . 'claude/bootstrap-provider-strategy.php';
        }
        require_once $providers_path . 'deepseek-provider-strategy.php';
        if (file_exists($providers_path . 'xai/bootstrap-provider-strategy.php')) {
            require_once $providers_path . 'xai/bootstrap-provider-strategy.php';
        }
        // Load Ollama bootstrap (Pro addon strategy lives under lib)
        if (file_exists($providers_path . 'ollama/bootstrap-provider-strategy.php')) {
            require_once $providers_path . 'ollama/bootstrap-provider-strategy.php';
        }
        require_once $providers_path . 'provider-strategy-factory.php';

    }
}

class Core_Services_Loader {
    public static function load() {
        $core_path = WPAICG_PLUGIN_DIR . 'classes/core/';
        $token_manager_path = $core_path . 'token-manager/AIPKit_Token_Manager.php';
        if (file_exists($token_manager_path)) {
            require_once $token_manager_path;
        }
        require_once $core_path . 'class-aipkit-http-request.php';
        require_once $core_path . 'class-aipkit_ai_caller.php';
        require_once $core_path . 'models_api.php';
        require_once $core_path . 'class-aipkit-openai-reasoning.php';
        require_once $core_path . 'class-aipkit-instruction-manager.php';
        require_once $core_path . 'class-aipkit-content-moderator.php';
        require_once $core_path . 'class-aipkit-payload-sanitizer.php';
        require_once $core_path . 'class-aipkit-event-registry.php';
        require_once $core_path . 'class-aipkit-event-webhooks-settings.php';
        require_once $core_path . 'class-aipkit-event-payload-builder.php';
        require_once $core_path . 'class-aipkit-event-delivery-policy.php';
        require_once $core_path . 'class-aipkit-event-queue-store.php';
        require_once $core_path . 'class-aipkit-event-queue-worker.php';
        require_once $core_path . 'class-aipkit-event-signature.php';
        require_once $core_path . 'class-aipkit-event-delivery-manager.php';
        require_once $core_path . 'class-aipkit-event-dispatcher.php';
        require_once $core_path . 'class-aipkit-event-webhooks.php';

        if (class_exists(\WPAICG\Core\AIPKit_Event_Webhooks_Settings::class)) {
            \WPAICG\Core\AIPKit_Event_Webhooks_Settings::init();
        }

        if (class_exists(\WPAICG\Core\AIPKit_Event_Delivery_Policy::class)) {
            \WPAICG\Core\AIPKit_Event_Delivery_Policy::register_hooks();
        }

        if (class_exists(\WPAICG\Core\AIPKit_Event_Queue_Worker::class)) {
            \WPAICG\Core\AIPKit_Event_Queue_Worker::register_hooks();
        }

        $stream_path_base = $core_path . 'stream/';
        $sse_classes_to_load = [
            $stream_path_base . 'formatter/class-sse-response-formatter.php',
            $stream_path_base . 'cache/class-sse-message-cache.php',
            $stream_path_base . 'vector/class-sse-vector-context-helper.php',
            $stream_path_base . 'contexts/chat/class-chat-context-handler.php',
            $stream_path_base . 'contexts/content-writer/class-content-writer-context-handler.php',
            $stream_path_base . 'contexts/ai-forms/class-ai-forms-context-handler.php',
            $stream_path_base . 'request/class-sse-request-handler.php',
            $stream_path_base . 'processor/class-sse-stream-processor.php',
            $stream_path_base . 'handler/class-sse-handler.php',
        ];
        foreach ($sse_classes_to_load as $sse_class_file) {
            if (file_exists($sse_class_file)) {
                require_once $sse_class_file;
            }
        }
    }
}

class Dashboard_Base_Classes_Loader {
    public static function load() {
        $dashboard_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/';
        require_once $dashboard_path . 'class-aipkit_providers.php';
        require_once $dashboard_path . 'class-aipkit_provider_model_list_builder.php';
        require_once $dashboard_path . 'class-aipkit_ai_settings.php';
        require_once $dashboard_path . 'class-aipkit_dashboard.php';
        require_once $dashboard_path . 'class-aipkit_role_manager.php';
        require_once WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_stats.php';
    }
}

class Base_Ajax_Handlers_Loader
{
    public static function load()
    {
        $traits_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/ajax/traits/';
        $trait_files = [
            'Trait_CheckModuleAccess.php',
            'Trait_CheckFrontendPermissions.php',
            'Trait_SendWPError.php',
        ];

        foreach ($trait_files as $trait_file) {
            $full_trait_path = $traits_path . $trait_file;
            if (file_exists($full_trait_path)) {
                require_once $full_trait_path;
            }
        }
        // Now load the base handler class that uses these traits
        $base_chat_ajax_handler_path = WPAICG_PLUGIN_DIR . 'classes/chat/admin/ajax/base_ajax_handler.php';
        if (file_exists($base_chat_ajax_handler_path)) {
            require_once $base_chat_ajax_handler_path;
        }

        // Continue loading other base handlers if any
        $base_dashboard_ajax_handler_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/ajax/class-aipkit-base-dashboard-ajax-handler.php';
        if (file_exists($base_dashboard_ajax_handler_path)) {
            require_once $base_dashboard_ajax_handler_path;
        }

        if (!self::should_load_concrete_handlers()) {
            return;
        }

        $dashboard_ajax_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/ajax/';
        $settings_ajax_handler_path = $dashboard_ajax_path . 'class-aipkit-settings-ajax-handler.php';
        if (file_exists($settings_ajax_handler_path)) {
            require_once $settings_ajax_handler_path;
        }
        $event_webhook_delivery_issues_handler_path = $dashboard_ajax_path . 'class-aipkit-event-webhook-delivery-issues-ajax-handler.php';
        if (file_exists($event_webhook_delivery_issues_handler_path)) {
            require_once $event_webhook_delivery_issues_handler_path;
        }
        $models_ajax_handler_path = $dashboard_ajax_path . 'class-aipkit-models-ajax-handler.php';
        if (file_exists($models_ajax_handler_path)) {
            require_once $models_ajax_handler_path;
        }


        $core_ajax_handler_path = WPAICG_PLUGIN_DIR . 'classes/core/ajax/class-aipkit-core-ajax-handler.php';
        if (file_exists($core_ajax_handler_path)) {
            require_once $core_ajax_handler_path;
        }

        $semantic_search_ajax_handler_path = WPAICG_PLUGIN_DIR . 'classes/core/ajax/class-aipkit-semantic-search-ajax-handler.php';
        if (file_exists($semantic_search_ajax_handler_path)) {
            require_once $semantic_search_ajax_handler_path;
        }
    }

    private static function should_load_concrete_handlers(): bool
    {
        return is_admin() || wp_doing_ajax();
    }
}

class Chat_Dependencies_Loader
{
    public static function load()
    {
        $chat_base_path = WPAICG_PLUGIN_DIR . 'classes/chat/';
        $frontend_ajax_handlers_path = $chat_base_path . 'frontend/ajax/'; // Path for ChatFormSubmissionAjaxHandler

        $paths = [
            'core/ai_service.php',
            'core/class-aipkit_content_aware.php',
            $frontend_ajax_handlers_path . 'class-chat-form-submission-ajax-handler.php',
            'utils/class-aipkit_chat_utils.php',
            'utils/class-aipkit-svg-icons.php',
            'utils/class-log-config.php',
            'admin/chat_admin_setup.php',
            'storage/class-aipkit_log_query_helper.php', 'storage/class-aipkit_site_wide_bot_manager.php',
            'storage/class-aipkit_default_bot_setup.php', 'storage/class-aipkit_bot_settings_manager.php',
            'storage/class-aipkit_bot_lifecycle_manager.php',
            'storage/logger/methods.php',
            'storage/class-aipkit_conversation_logger.php',
            'storage/class-aipkit_conversation_reader.php',
            'storage/class-aipkit_feedback_manager.php',
            'storage/class-aipkit_log_manager.php',
            'storage/class-aipkit_log_cron_manager.php', // NEW
            'storage/class-aipkit_chat_bot_storage.php',
            'storage/class-aipkit_chat_log_storage.php', 'storage/class-aipkit-bot-settings-getter.php',
            'storage/class-aipkit-bot-settings-saver.php', 'storage/class-aipkit-bot-settings-initializer.php',
            'frontend/chat_assets.php', 'frontend/chat_shortcode.php',
            'frontend/shortcode/shortcode_configurator.php', 'frontend/shortcode/shortcode_dataprovider.php',
            'frontend/shortcode/shortcode_featuremanager.php', 'frontend/shortcode/shortcode_renderer.php',
            'frontend/shortcode/shortcode_sitewidehandler.php', 'frontend/shortcode/shortcode_validator.php',
            'class-aipkit_chat_initializer.php'
        ];

        if (self::should_load_ajax_handlers()) {
            $paths = array_merge(
                $paths,
                [
                    'admin/ajax/chatbot_ajax_handler.php',
                    'admin/ajax/conversation_ajax_handler.php',
                    'admin/ajax/user_credits_ajax_handler.php',
                    'admin/ajax/class-aipkit-chatbot-image-ajax-handler.php',
                ]
            );
        }

        foreach ($paths as $file) {
            if (strpos($file, $frontend_ajax_handlers_path) === 0) {
                $full_path = $file;
            } else {
                $full_path = $chat_base_path . $file;
            }

            if (file_exists($full_path)) {
                $class_name_from_file = basename($file, '.php');
                if (strpos($class_name_from_file, 'class-') === 0) {
                    $class_name_from_file = (string) substr($class_name_from_file, strlen('class-'));
                }
                $class_name_from_file = str_replace('-', '_', $class_name_from_file);
                $class_name_from_file = preg_replace_callback('/(?:^|_)([a-z])/', function ($matches) {
                    return strtoupper($matches[1]);
                }, $class_name_from_file);

                $namespace_parts = explode('/', dirname(str_replace($chat_base_path, '', $full_path)));
                $namespace = 'WPAICG\\Chat';
                foreach ($namespace_parts as $part) {
                    if ($part !== '.' && !empty($part)) {
                        $namespace .= '\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
                    }
                }
                $potential_class_name = rtrim($namespace, '\\') . '\\' . $class_name_from_file;

                $final_class_check_name = $potential_class_name;
                if ($file === 'frontend/ajax/class-chat-form-submission-ajax-handler.php') {
                    $final_class_check_name = \WPAICG\Chat\Frontend\Ajax\ChatFormSubmissionAjaxHandler::class;
                } elseif ($file === 'admin/ajax/class-aipkit-chatbot-image-ajax-handler.php') {
                    $final_class_check_name = \WPAICG\Chat\Admin\Ajax\ChatbotImageAjaxHandler::class;
                }


                if (!class_exists($final_class_check_name) && !function_exists($final_class_check_name . '_logic') && substr($file, -4) === '.php') {
                    require_once $full_path;
                } elseif (substr($file, -4) !== '.php') {
                    require_once $full_path;
                }
            }
        }
    }

    private static function should_load_ajax_handlers(): bool
    {
        return is_admin() || wp_doing_ajax();
    }
}

class Speech_Dependencies_Loader
{
    public static function load()
    {
        $speech_base_path = WPAICG_PLUGIN_DIR . 'classes/speech/';
        $paths = [
            'class-aipkit-speech-manager.php', 'interface-aipkit-tts-provider-strategy.php',
            'class-aipkit-tts-base-provider-strategy.php', 'class-aipkit-tts-provider-strategy-factory.php',
            'class-aipkit-tts-openai-provider-strategy.php', 'class-aipkit-tts-google-provider-strategy.php',
            'class-aipkit-tts-elevenlabs-provider-strategy.php',
        ];
        foreach ($paths as $file) {
            $full_path = $speech_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Stt_Dependencies_Loader
{
    public static function load()
    {
        $stt_base_path = WPAICG_PLUGIN_DIR . 'classes/stt/';
        $paths = [
            'class-aipkit-stt-manager.php', 'interface-aipkit-stt-provider-strategy.php',
            'class-aipkit-stt-base-provider-strategy.php', 'class-aipkit-stt-provider-strategy-factory.php',
            'class-aipkit-stt-openai-provider-strategy.php',
            'class-aipkit-stt-azure-provider-strategy.php',
        ];
        foreach ($paths as $file) {
            $full_path = $stt_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Rest_Dependencies_Loader
{
    public static function load()
    {
        $rest_base_path = WPAICG_PLUGIN_DIR . 'classes/rest/';
        $base_handler_path = $rest_base_path . 'handlers/class-aipkit-rest-base-handler.php';
        if (file_exists($base_handler_path)) {
            require_once $base_handler_path;
        }
        $handlers_to_load = [
            'class-aipkit-rest-text-handler.php',
            'class-aipkit-rest-image-handler.php',
            'class-aipkit-rest-embeddings-handler.php',
            'class-aipkit-rest-chat-handler.php',
            'class-aipkit-rest-vector-store-handler.php',
            'class-aipkit-rest-chatbot-embed-handler.php', // NEW: Embed handler
            'class-aipkit-rest-logs-handler.php', // NEW: Logs handler
        ];
        foreach ($handlers_to_load as $handler_file) {
            $full_path = $rest_base_path . 'handlers/' . $handler_file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
        $rest_controller_path = $rest_base_path . 'class-aipkit-rest-controller.php';
        if (file_exists($rest_controller_path)) {
            require_once $rest_controller_path;
        }
    }
}

class Image_Dependencies_Loader
{
    public static function load()
    {
        $images_base_path = WPAICG_PLUGIN_DIR . 'classes/images/';
        $image_settings_ajax_handler_path = $images_base_path . 'class-aipkit-image-settings-ajax-handler.php';
        if (file_exists($image_settings_ajax_handler_path)) {
            require_once $image_settings_ajax_handler_path;
        }
        $paths = [
            'interface-aipkit-image-provider-strategy.php', 'class-aipkit-image-base-provider-strategy.php',
            'class-aipkit-image-manager.php', 'class-aipkit-image-provider-strategy-factory.php',
            'class-aipkit-image-storage-helper.php',
            'providers/class-aipkit-image-openai-provider-strategy.php',
            'providers/class-aipkit-image-azure-provider-strategy.php',
            'providers/class-aipkit-image-google-provider-strategy.php',
            'providers/class-aipkit-image-pexels-provider-strategy.php',
            'providers/class-aipkit-image-pixabay-provider-strategy.php',
            'providers/class-aipkit-image-replicate-provider-strategy.php',
        ];
        foreach ($paths as $file) {
            $full_path = $images_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
        $provider_helper_paths = [
            'providers/openai/OpenAIImageUrlBuilder.php',
            'providers/openai/OpenAIPayloadFormatter.php',
            'providers/openai/OpenAIImageResponseParser.php',
            'providers/google/GoogleImageUrlBuilder.php',
            'providers/google/GoogleImagePayloadFormatter.php',
            'providers/google/GoogleImageResponseParser.php',
            'providers/google/GoogleImageTokenCounter.php',
            'providers/google/GoogleVideoUrlBuilder.php',
            'providers/google/GoogleVideoPayloadFormatter.php',
            'providers/google/GoogleVideoResponseParser.php',
        ];
        foreach ($provider_helper_paths as $file) {
            $full_path = $images_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Vector_Store_Dependencies_Loader
{
    public static function load()
    {
        $vector_base_path = WPAICG_PLUGIN_DIR . 'classes/vector/';
        $providers_path = $vector_base_path . 'providers/'; // Base path for provider strategies
        $manager_methods_path = $vector_base_path . 'manager/'; // NEW: Path for manager methods

        // Core vector store classes (interfaces, base classes, factories, main manager)
        $core_paths = [
            $vector_base_path . 'interface-aipkit-vector-provider-strategy.php',
            $vector_base_path . 'class-aipkit-vector-base-provider-strategy.php',
            $vector_base_path . 'class-aipkit-vector-text-chunker.php',
            $vector_base_path . 'class-aipkit-vector-embedding-batch-policy.php',
            $vector_base_path . 'class-aipkit-vector-text-ingestion-service.php',
            $vector_base_path . 'class-aipkit-vector-provider-strategy-factory.php',
            $vector_base_path . 'class-aipkit-vector-store-manager.php', // This class now loads its own method files
            $vector_base_path . 'class-aipkit-vector-store-registry.php',
        ];

        foreach ($core_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Provider-specific strategy bootstrap files
        $provider_bootstraps = [
            $providers_path . 'pinecone/bootstrap.php',
            $providers_path . 'qdrant/bootstrap.php',
            $providers_path . 'openai/bootstrap.php',
            $providers_path . 'chroma/bootstrap.php',
        ];

        foreach ($provider_bootstraps as $bootstrap_file) {
            if (file_exists($bootstrap_file)) {
                require_once $bootstrap_file;
            }
        }
    }
}

class Vector_Store_Ajax_Handlers_Loader
{
    public static function load()
    {
        $ajax_handlers_path = WPAICG_PLUGIN_DIR . 'classes/dashboard/ajax/';
        $openai_ajax_base_path = $ajax_handlers_path . 'openai/';

        // Load OpenAI handler classes (these are now thin wrappers)
        $openai_handler_classes_to_load = [
            'class-aipkit-openai-vector-stores-ajax-handler.php',
            'class-aipkit-openai-vector-store-files-ajax-handler.php',
            'class-aipkit-openai-wp-content-indexing-ajax-handler.php',
        ];
        foreach ($openai_handler_classes_to_load as $handler_file) {
            $full_path = $openai_ajax_base_path . $handler_file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }

        // Load external vector store handler classes from their locations.
        $other_handlers_to_load = [
            'pinecone/class-aipkit-vector-store-pinecone-ajax-handler.php', // Updated path
            'qdrant/class-aipkit-vector-store-qdrant-ajax-handler.php',   // Updated path
            'chroma/class-aipkit-vector-store-chroma-ajax-handler.php',
        ];
        foreach ($other_handlers_to_load as $handler_file_rel_path) {
            $full_path = $ajax_handlers_path . $handler_file_rel_path;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }

        // REMOVED loading of old fn-*.php files for OpenAI, Pinecone, Qdrant, and Chroma.
        // The logic is now in handler-files/, handler-stores/, handler-indexing/ (for OpenAI)
        // or handler-indexes/ (for Pinecone), or handler-collections/ (for Qdrant)
        // and these are included by the respective handler class methods.

        // Load OpenAI helper function files (these were in the old fn-*.php structure but might still be needed as general utils within OpenAI Ajax scope)
        // These are loaded here because they are used across different OpenAI handler methods.
        $openai_utility_functions_to_load = [
            'fn-log-entry.php',
            'fn-temp-file.php',
        ];
        foreach ($openai_utility_functions_to_load as $util_fn_file) {
            $full_path = $openai_ajax_base_path . $util_fn_file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Vector_Post_Processor_Classes_Loader
{
    public static function load()
    {
        $vpp_base_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/';

        // Load the Base Class first
        $base_class_path = $vpp_base_path . 'base/class-aipkit-vector-post-processor-base.php';
        if (file_exists($base_class_path) && !class_exists(\WPAICG\Vector\PostProcessor\Base\AIPKit_Vector_Post_Processor_Base::class)) {
            require_once $base_class_path;
        }

        // Load provider-specific directories and their components
        $provider_dirs = ['openai', 'pinecone', 'qdrant', 'chroma'];

        foreach ($provider_dirs as $provider_dir) {
            $provider_path = $vpp_base_path . $provider_dir . '/';
            $class_config_file = 'class-' . $provider_dir . '-config.php';
            $class_embedding_handler_file = 'class-' . $provider_dir . '-embedding-handler.php';
            $class_processor_file = 'class-' . $provider_dir . '-post-processor.php';

            // Config class
            $config_full_path = $provider_path . $class_config_file;
            $config_class_name = '\\WPAICG\\Vector\\PostProcessor\\' . ucfirst($provider_dir) . '\\' . ucfirst($provider_dir) . 'Config';
            if (file_exists($config_full_path) && !class_exists($config_class_name)) {
                require_once $config_full_path;
            }

            // Embedding Handler (not all providers have one)
            if ($provider_dir === 'pinecone' || $provider_dir === 'qdrant' || $provider_dir === 'chroma') {
                $embed_full_path = $provider_path . $class_embedding_handler_file;
                $embed_class_name = '\\WPAICG\\Vector\\PostProcessor\\' . ucfirst($provider_dir) . '\\' . ucfirst($provider_dir) . 'EmbeddingHandler';
                if (file_exists($embed_full_path) && !class_exists($embed_class_name)) {
                    require_once $embed_full_path;
                }
            }

            // Main Processor class
            $processor_full_path = $provider_path . $class_processor_file;
            $processor_class_name = '\\WPAICG\\Vector\\PostProcessor\\' . ucfirst($provider_dir) . '\\' . ucfirst($provider_dir) . 'PostProcessor';
            if (file_exists($processor_full_path) && !class_exists($processor_class_name)) {
                require_once $processor_full_path;
            }
        }

        // Load the main AJAX Handler (which uses the above processors)
        $ajax_handler_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/class-aipkit-vector-post-processor-ajax-handler.php';
        if (file_exists($ajax_handler_path) && !class_exists(\WPAICG\Vector\AIPKit_Vector_Post_Processor_Ajax_Handler::class)) {
            require_once $ajax_handler_path;
        }

        // Load the List Screen class (handles post list screen features)
        $list_screen_path = WPAICG_PLUGIN_DIR . 'classes/vector/post-processor/class-aipkit-vector-post-processor-list-screen.php';
        if (file_exists($list_screen_path) && !class_exists(\WPAICG\Vector\PostProcessor\AIPKit_Vector_Post_Processor_List_Screen::class)) {
            require_once $list_screen_path;
        }
    }
}

class Content_Writer_Dependencies_Loader
{
    public static function load()
    {
        $content_writer_base_path = WPAICG_PLUGIN_DIR . 'classes/content-writer/';
        $files_to_load = [
            $content_writer_base_path . 'ajax/class-aipkit-content-writer-base-ajax-action.php',
            $content_writer_base_path . 'seo/class-aipkit-content-writer-seo-config.php',
            $content_writer_base_path . 'class-aipkit-content-writer-prompts.php',
            $content_writer_base_path . 'class-aipkit-content-writer-image-provider-options.php',
            $content_writer_base_path . 'class-aipkit-content-writer-output-cleaner.php',
            $content_writer_base_path . 'class-aipkit-content-writer-prompt-library-manager.php',
            $content_writer_base_path . 'class-aipkit-content-writer-template-manager.php',
            $content_writer_base_path . 'ajax/class-aipkit-content-writer-template-ajax-handler.php',
            $content_writer_base_path . 'ajax/class-aipkit-content-writer-prompt-library-ajax-handler.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-init-stream-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-standard-generation-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-title-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-save-post-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-create-task-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-prepare-batch-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-meta-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-keyword-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-excerpt-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-tags-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-generate-images-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-parse-csv-action.php',
            $content_writer_base_path . 'ajax/actions/class-aipkit-content-writer-fetch-posts-action.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-system-instruction-builder.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-user-prompt-builder.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-summarizer.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-meta-prompt-builder.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-keyword-prompt-builder.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-excerpt-prompt-builder.php',
            $content_writer_base_path . 'prompt/class-aipkit-content-writer-tags-prompt-builder.php',
            $content_writer_base_path . 'class-aipkit-content-writer-image-handler.php',
            $content_writer_base_path . 'class-aipkit-image-injector.php',
        ];
        foreach ($files_to_load as $full_path) {
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Addon_Dependencies_Loader
{
    public static function load()
    {
        $addons_path = WPAICG_PLUGIN_DIR . 'classes/addons/';
        $ip_anon_path = $addons_path . 'class-aipkit-ip-anonymization.php';
        if (file_exists($ip_anon_path)) {
            require_once $ip_anon_path;
        }
    }
}

class Post_Enhancer_Core_Loader
{
    public static function load()
    {
        $post_enhancer_core_path = WPAICG_PLUGIN_DIR . 'classes/post-enhancer/class-aipkit-post-enhancer-core.php';
        $post_enhancer_ajax_path = WPAICG_PLUGIN_DIR . 'classes/post-enhancer/class-aipkit-post-enhancer-ajax.php';
        $post_enhancer_actions_ajax_path = WPAICG_PLUGIN_DIR . 'classes/post-enhancer/ajax/class-aipkit-enhancer-actions-ajax-handler.php';

        if (file_exists($post_enhancer_core_path)) {
            require_once $post_enhancer_core_path;
        }

        if (file_exists($post_enhancer_ajax_path)) {
            require_once $post_enhancer_ajax_path;
        }

        if (file_exists($post_enhancer_actions_ajax_path)) {
            require_once $post_enhancer_actions_ajax_path;
        }
    }
}

class Woocommerce_Writer_Loader
{
    /**
     * Registers an action to initialize integrations after all plugins are loaded.
     */
    public static function load()
    {
        add_action('plugins_loaded', [__CLASS__, 'init_integrations']);
    }

    /**
     * Initializes WooCommerce-dependent features after ensuring WooCommerce is active.
     */
    public static function init_integrations()
    {
        // Only load any of this if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        $woo_base_path = WPAICG_PLUGIN_DIR . 'classes/woocommerce/';

        $woo_integration_path = $woo_base_path . 'class-aipkit-woocommerce-integration.php';
        if (file_exists($woo_integration_path)) {
            require_once $woo_integration_path;

            // Get the singleton instance to register hooks
            \WPAICG\WooCommerce\AIPKit_WooCommerce_Integration::get_instance();
        }
    }
}

class Automated_Task_Dependencies_Loader
{
    public static function load()
    {
        $autogpt_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/';
        $main_manager_path = $autogpt_path . 'class-aipkit-automated-task-manager.php';
        $main_cron_path = $autogpt_path . 'class-aipkit-automated-task-cron.php';
        $prompt_definitions_path = $autogpt_path . 'helpers/class-aipkit-autogpt-prompt-definitions.php';

        if (file_exists($main_manager_path)) {
            require_once $main_manager_path;
        }

        if (file_exists($main_cron_path)) {
            require_once $main_cron_path;
        }

        if (file_exists($prompt_definitions_path)) {
            require_once $prompt_definitions_path;
        }
    }
}

class Automated_Task_Ajax_Handlers_Loader
{
    public static function load()
    {
        $ajax_base_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/ajax/';
        $ajax_actions = [
            'class-aipkit-automated-task-base-ajax-action.php',
            'class-aipkit-save-automated-task-action.php',
            'class-aipkit-get-automated-tasks-action.php',
            'class-aipkit-delete-automated-task-action.php',
            'class-aipkit-update-automated-task-status-action.php',
            'class-aipkit-run-automated-task-now-action.php',
            'class-aipkit-get-automated-task-queue-items-action.php',
            'class-aipkit-delete-automated-task-queue-item-action.php',
            'class-aipkit-delete-automated-task-queue-items-action.php',
            'class-aipkit-retry-automated-task-queue-item-action.php',
        ];
        foreach ($ajax_actions as $file) {
            $full_path = $ajax_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class Automated_Task_Cron_Helpers_Loader
{
    public static function load()
    {
        $cron_base_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/';
        $cron_helpers = [
            'class-aipkit-automated-task-scheduler.php',
            'class-aipkit-automated-task-content-queuer.php',
            'class-aipkit-automated-task-event-processor.php',
        ];
        foreach ($cron_helpers as $file) {
            $full_path = $cron_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

/**
 * AIPKit_Hook_Registrars_Loader
 * Handles loading all the hook registrar classes.
 */
class Hook_Registrars_Loader {

    public static function load() {
        $registrars_path = WPAICG_PLUGIN_DIR . 'includes/hook-registrars/';

        $registrar_files = [
            'class-core-hooks-registrar.php',
            'class-admin-asset-hooks-registrar.php',
            'class-ajax-hooks-registrar.php',
            'class-rest-api-hooks-registrar.php',
            'class-module-initializer-hooks-registrar.php',
            // Add any other registrar files here
        ];

        foreach ($registrar_files as $file) {
            $full_path = $registrars_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

/**
 * AIPKit_AI_Forms_Dependencies_Loader
 * Handles loading all necessary PHP class dependencies for the AI Forms module.
 */
class AI_Forms_Dependencies_Loader
{
    public static function load()
    {
        $ai_forms_base_path = WPAICG_PLUGIN_DIR . 'classes/ai-forms/';

        // Define paths to AI Forms classes
        $paths = [
            'admin/class-aipkit-ai-form-admin-setup.php',
            'admin/class-aipkit-ai-form-settings-ajax-handler.php', // NEW: This handles token settings
            'frontend/class-aipkit-ai-form-shortcode.php',
            // REMOVED old processor logic files
            // Then load the facade and storage
            'core/class-aipkit-ai-form-processor.php',
            'storage/class-aipkit-ai-form-storage.php',
            'class-aipkit-ai-form-initializer.php',
        ];

        if (self::should_load_admin_ajax_handlers()) {
            $paths[] = 'admin/class-aipkit-ai-form-ajax-handler.php';
        }

        foreach ($paths as $file) {
            $full_path = $ai_forms_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }

    private static function should_load_admin_ajax_handlers(): bool
    {
        return is_admin() || wp_doing_ajax();
    }
}

/**
 * AIPKit_Core_Moderation_Dependencies_Loader
 * Handles loading core moderation classes.
 */
class Core_Moderation_Dependencies_Loader {

    public static function load() {
        $moderation_base_path = WPAICG_PLUGIN_DIR . 'classes/core/moderation/';

        $moderation_files = [
            'AIPKit_Global_Security_Settings.php',
            'AIPKit_BannedIP_Checker.php',
            'AIPKit_BannedWords_Checker.php',
            'AIPKit_OpenAI_Moderation_Checker.php',
        ];

        foreach ($moderation_files as $file) {
            $full_path = $moderation_base_path . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

class WP_AI_Client_Dependencies_Loader
{
    public static function load(): void
    {
        // Early PHP 7.4 builds have a covariant return compile bug that the WP AI Client DTOs trigger.
        if (PHP_VERSION_ID < 70412) {
            return;
        }

        $base_path = WPAICG_PLUGIN_DIR . 'classes/wp-ai-client/';

        $settings_path = $base_path . 'class-aipkit-wp-ai-client-settings.php';
        if (file_exists($settings_path)) {
            require_once $settings_path;
        }

        if (!class_exists(\WPAICG\WP_AI_Client\AIPKit_WP_AI_Client_Settings::class)
            || !\WPAICG\WP_AI_Client\AIPKit_WP_AI_Client_Settings::is_supported()
        ) {
            return;
        }

        $classes_path = $base_path . 'classes.php';
        if (file_exists($classes_path)) {
            require_once $classes_path;
        }

        if (class_exists(\WPAICG\WP_AI_Client\AIPKit_WP_AI_Client_Integration::class)) {
            \WPAICG\WP_AI_Client\AIPKit_WP_AI_Client_Integration::register_hooks();
        }
    }
}
